<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Core\Database;
use PDO;
use RuntimeException;
use Throwable;

class AttendanceImportService
{
    private PDO $db;
    private BiffXlsReader $reader;

    public function __construct()
    {
        $this->db = Database::connection();
        $this->reader = new BiffXlsReader();
    }

    /**
     * @param array<string, mixed> $file
     * @return array<string, mixed>
     */
    public function processUploadedFile(array $file): array
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new RuntimeException('No se recibio un archivo valido para procesar.');
        }

        $tmpPath = (string) ($file['tmp_name'] ?? '');
        $originalName = basename((string) ($file['name'] ?? 'reporte.xls'));

        if ($tmpPath === '' || !is_uploaded_file($tmpPath)) {
            throw new RuntimeException('El archivo temporal no esta disponible.');
        }

        $storedPath = $this->storeUploadedFile($tmpPath, $originalName);

        return $this->processFile($storedPath, $originalName, $storedPath);
    }

    /**
     * @return array<string, mixed>
     */
    public function processFile(string $path, string $originalName, ?string $storedPath = null): array
    {
        if (!is_file($path)) {
            throw new RuntimeException('El archivo indicado no existe.');
        }

        $hash = hash_file('sha256', $path);

        if ($hash === false) {
            throw new RuntimeException('No se pudo calcular el hash del archivo.');
        }

        $sheets = $this->reader->read($path);
        $period = $this->detectPeriod($sheets);
        $summary = [
            'departments' => 0,
            'employees' => 0,
            'schedules' => 0,
            'period_summaries' => 0,
            'punches' => 0,
            'exceptions' => 0,
            'daily_records' => 0,
        ];

        $this->db->beginTransaction();

        try {
            $batchId = $this->upsertBatch($originalName, $storedPath ?? $path, $hash, $period);

            $summary['schedules'] = $this->processSchedules($sheets, $period, $batchId);
            $summary['period_summaries'] = $this->processStatistics($sheets, $period, $batchId);
            $punchResult = $this->processPunches($sheets, $period, $batchId);
            $summary['punches'] = $punchResult['punches'];
            $summary['daily_records'] += $punchResult['daily_records'];
            $summary['exceptions'] = $this->processExceptions($sheets, $batchId);

            $employeeCount = $this->db->query('SELECT COUNT(*) FROM employees')->fetchColumn();
            $departmentCount = $this->db->query('SELECT COUNT(*) FROM departments')->fetchColumn();
            $summary['employees'] = (int) $employeeCount;
            $summary['departments'] = (int) $departmentCount;

            $this->markBatchProcessed($batchId, $summary);
            $this->db->commit();

            return [
                'ok' => true,
                'batch_id' => $batchId,
                'period' => $period,
                'summary' => $summary,
            ];
        } catch (Throwable $exception) {
            $this->db->rollBack();
            throw $exception;
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function recentBatches(int $limit = 12): array
    {
        $statement = $this->db->prepare('
            SELECT
                id,
                source_filename,
                period_start,
                period_end,
                status,
                processed_at,
                summary_json,
                created_at
            FROM attendance_import_batches
            ORDER BY id DESC
            LIMIT :limit
        ');
        $statement->bindValue('limit', $limit, PDO::PARAM_INT);
        $statement->execute();

        return $statement->fetchAll();
    }

    private function storeUploadedFile(string $tmpPath, string $originalName): string
    {
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

        if (!in_array($extension, ['xls'], true)) {
            throw new RuntimeException('Por ahora el importador procesa archivos .xls del sistema de asistencia.');
        }

        $directory = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'attendance';

        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new RuntimeException('No se pudo crear el directorio de cargas.');
        }

        $safeName = preg_replace('/[^A-Za-z0-9_.-]+/', '_', $originalName) ?: 'reporte.xls';
        $storedPath = $directory . DIRECTORY_SEPARATOR . date('Ymd_His') . '_' . $safeName;

        if (!move_uploaded_file($tmpPath, $storedPath)) {
            throw new RuntimeException('No se pudo guardar el archivo subido.');
        }

        return $storedPath;
    }

    /**
     * @param array<string, array<int, array<int, string|int|float>>> $sheets
     * @return array{start: string, end: string, raw: string}
     */
    private function detectPeriod(array $sheets): array
    {
        foreach ($sheets as $rows) {
            foreach (array_slice($rows, 0, 8, true) as $row) {
                foreach ($row as $value) {
                    $text = (string) $value;

                    if (preg_match('/(\d{4}-\d{2}-\d{2})\s*~\s*(\d{4}-\d{2}-\d{2})/', $text, $matches)) {
                        return ['start' => $matches[1], 'end' => $matches[2], 'raw' => $matches[0]];
                    }
                }
            }
        }

        throw new RuntimeException('No se encontro el periodo del reporte dentro del Excel.');
    }

    /**
     * @param array{start: string, end: string, raw: string} $period
     */
    private function upsertBatch(string $filename, string $storedPath, string $hash, array $period): int
    {
        $existing = $this->db->prepare('SELECT id FROM attendance_import_batches WHERE file_hash = :hash LIMIT 1');
        $existing->execute(['hash' => $hash]);
        $existingId = $existing->fetchColumn();

        if ($existingId !== false) {
            $statement = $this->db->prepare('
                UPDATE attendance_import_batches
                SET
                    source_filename = :filename,
                    stored_path = :stored_path,
                    period_start = :period_start,
                    period_end = :period_end,
                    raw_period_text = :raw_period_text,
                    uploaded_by_user_id = :uploaded_by_user_id,
                    status = "processing",
                    error_message = NULL,
                    updated_at = NOW()
                WHERE id = :id
            ');
            $statement->execute([
                'filename' => $filename,
                'stored_path' => $storedPath,
                'period_start' => $period['start'],
                'period_end' => $period['end'],
                'raw_period_text' => $period['raw'],
                'uploaded_by_user_id' => Auth::id(),
                'id' => (int) $existingId,
            ]);

            return (int) $existingId;
        }

        $statement = $this->db->prepare('
            INSERT INTO attendance_import_batches (
                source_filename,
                stored_path,
                file_hash,
                period_start,
                period_end,
                raw_period_text,
                uploaded_by_user_id,
                status,
                created_at
            ) VALUES (
                :filename,
                :stored_path,
                :file_hash,
                :period_start,
                :period_end,
                :raw_period_text,
                :uploaded_by_user_id,
                "processing",
                NOW()
            )
        ');
        $statement->execute([
            'filename' => $filename,
            'stored_path' => $storedPath,
            'file_hash' => $hash,
            'period_start' => $period['start'],
            'period_end' => $period['end'],
            'raw_period_text' => $period['raw'],
            'uploaded_by_user_id' => Auth::id(),
        ]);

        return (int) $this->db->lastInsertId();
    }

    /**
     * @param array<string, array<int, array<int, string|int|float>>> $sheets
     * @param array{start: string, end: string, raw: string} $period
     */
    private function processSchedules(array $sheets, array $period, int $batchId): int
    {
        $sheet = $this->findSheet($sheets, ['Reporte de Turnos']);

        if ($sheet === null) {
            return 0;
        }

        $dates = $this->dateRange($period['start'], $period['end']);
        $count = 0;

        foreach ($sheet as $rowIndex => $row) {
            $externalCode = $this->cellText($row, 0);

            if ($rowIndex < 4 || $externalCode === '' || $this->normalizeText($externalCode) === 'id') {
                continue;
            }

            $name = $this->cellText($row, 1) ?: $externalCode;
            $departmentId = $this->upsertDepartment($this->cellText($row, 2) ?: 'Sin departamento');
            $employeeId = $this->upsertEmployee($externalCode, $name, $departmentId);
            $this->updateEmployeeDepartmentForPeriod($employeeId, $departmentId, $period['start'], $period['end'], $batchId);

            foreach ($dates as $dateIndex => $date) {
                $rawCode = $this->cellText($row, 3 + $dateIndex);

                if ($rawCode === '') {
                    continue;
                }

                $shiftId = $this->upsertShiftType($rawCode);
                $scheduleId = $this->upsertSchedule($employeeId, $departmentId, $date, $shiftId, $rawCode, $batchId, $rowIndex + 1, 4 + $dateIndex);
                $this->upsertDailyRecordFromSchedule($employeeId, $departmentId, $date, $scheduleId, $shiftId, $batchId);
                $count++;
            }
        }

        return $count;
    }

    /**
     * @param array<string, array<int, array<int, string|int|float>>> $sheets
     * @param array{start: string, end: string, raw: string} $period
     */
    private function processStatistics(array $sheets, array $period, int $batchId): int
    {
        $sheet = $this->findSheet($sheets, ['Reporte Estadistico', 'Reporte Estadístico']);

        if ($sheet === null) {
            return 0;
        }

        $count = 0;

        foreach ($sheet as $rowIndex => $row) {
            $externalCode = $this->cellText($row, 0);

            if ($rowIndex < 4 || $externalCode === '' || $this->normalizeText($externalCode) === 'id') {
                continue;
            }

            $departmentId = $this->upsertDepartment($this->cellText($row, 2) ?: 'Sin departamento');
            $employeeId = $this->upsertEmployee($externalCode, $this->cellText($row, 1) ?: $externalCode, $departmentId);
            $this->updateEmployeeDepartmentForPeriod($employeeId, $departmentId, $period['start'], $period['end'], $batchId);
            [$scheduledDays, $attendedDays] = $this->parseDayPair($this->cellText($row, 11));

            $statement = $this->db->prepare('
                INSERT INTO attendance_period_summaries (
                    import_batch_id,
                    employee_id,
                    department_id,
                    period_start,
                    period_end,
                    normal_work_minutes,
                    actual_work_minutes,
                    late_count,
                    late_minutes,
                    early_leave_count,
                    early_leave_minutes,
                    extra_workday_minutes,
                    extra_holiday_minutes,
                    scheduled_days,
                    attended_days,
                    exit_days,
                    absence_days,
                    permission_days,
                    increment_notes,
                    increment_overtime_amount,
                    increment_subsidy_amount,
                    deduction_late_early_amount,
                    deduction_permission_amount,
                    deduction_charge_amount,
                    real_payment_amount,
                    notes,
                    created_at
                ) VALUES (
                    :batch_id,
                    :employee_id,
                    :department_id,
                    :period_start,
                    :period_end,
                    :normal_work_minutes,
                    :actual_work_minutes,
                    :late_count,
                    :late_minutes,
                    :early_leave_count,
                    :early_leave_minutes,
                    :extra_workday_minutes,
                    :extra_holiday_minutes,
                    :scheduled_days,
                    :attended_days,
                    :exit_days,
                    :absence_days,
                    :permission_days,
                    :increment_notes,
                    :increment_overtime_amount,
                    :increment_subsidy_amount,
                    :deduction_late_early_amount,
                    :deduction_permission_amount,
                    :deduction_charge_amount,
                    :real_payment_amount,
                    :notes,
                    NOW()
                )
                ON DUPLICATE KEY UPDATE
                    import_batch_id = VALUES(import_batch_id),
                    department_id = VALUES(department_id),
                    normal_work_minutes = VALUES(normal_work_minutes),
                    actual_work_minutes = VALUES(actual_work_minutes),
                    late_count = VALUES(late_count),
                    late_minutes = VALUES(late_minutes),
                    early_leave_count = VALUES(early_leave_count),
                    early_leave_minutes = VALUES(early_leave_minutes),
                    extra_workday_minutes = VALUES(extra_workday_minutes),
                    extra_holiday_minutes = VALUES(extra_holiday_minutes),
                    scheduled_days = VALUES(scheduled_days),
                    attended_days = VALUES(attended_days),
                    exit_days = VALUES(exit_days),
                    absence_days = VALUES(absence_days),
                    permission_days = VALUES(permission_days),
                    increment_notes = VALUES(increment_notes),
                    increment_overtime_amount = VALUES(increment_overtime_amount),
                    increment_subsidy_amount = VALUES(increment_subsidy_amount),
                    deduction_late_early_amount = VALUES(deduction_late_early_amount),
                    deduction_permission_amount = VALUES(deduction_permission_amount),
                    deduction_charge_amount = VALUES(deduction_charge_amount),
                    real_payment_amount = VALUES(real_payment_amount),
                    notes = VALUES(notes)
            ');
            $statement->execute([
                'batch_id' => $batchId,
                'employee_id' => $employeeId,
                'department_id' => $departmentId,
                'period_start' => $period['start'],
                'period_end' => $period['end'],
                'normal_work_minutes' => $this->parseDurationMinutes($this->cellText($row, 3)),
                'actual_work_minutes' => $this->parseDurationMinutes($this->cellText($row, 4)),
                'late_count' => $this->cellInt($row, 5),
                'late_minutes' => $this->cellInt($row, 6),
                'early_leave_count' => $this->cellInt($row, 7),
                'early_leave_minutes' => $this->cellInt($row, 8),
                'extra_workday_minutes' => $this->parseDurationMinutes($this->cellText($row, 9)),
                'extra_holiday_minutes' => $this->parseDurationMinutes($this->cellText($row, 10)),
                'scheduled_days' => $scheduledDays,
                'attended_days' => $attendedDays,
                'exit_days' => $this->cellFloat($row, 12),
                'absence_days' => $this->cellFloat($row, 13),
                'permission_days' => $this->cellFloat($row, 14),
                'increment_notes' => $this->cellText($row, 15) ?: null,
                'increment_overtime_amount' => $this->nullableFloat($row, 16),
                'increment_subsidy_amount' => $this->nullableFloat($row, 17),
                'deduction_late_early_amount' => $this->nullableFloat($row, 18),
                'deduction_permission_amount' => $this->nullableFloat($row, 19),
                'deduction_charge_amount' => $this->nullableFloat($row, 20),
                'real_payment_amount' => $this->nullableFloat($row, 21),
                'notes' => $this->cellText($row, 22) ?: null,
            ]);
            $count++;
        }

        return $count;
    }

    /**
     * @param array<string, array<int, array<int, string|int|float>>> $sheets
     * @param array{start: string, end: string, raw: string} $period
     * @return array{punches: int, daily_records: int}
     */
    private function processPunches(array $sheets, array $period, int $batchId): array
    {
        $sheet = $this->findSheet($sheets, ['Reporte de Asistencia']);

        if ($sheet === null) {
            return ['punches' => 0, 'daily_records' => 0];
        }

        $dates = $this->dateRange($period['start'], $period['end']);
        $punches = 0;
        $touched = [];

        foreach ($sheet as $rowIndex => $row) {
            if ($this->normalizeText($this->cellText($row, 0)) !== 'id') {
                continue;
            }

            $externalCode = $this->cellText($row, 4);
            $name = $this->cellText($row, 10) ?: $externalCode;
            $departmentId = $this->upsertDepartment($this->cellText($row, 21) ?: 'Sin departamento');
            $employeeId = $this->upsertEmployee($externalCode, $name, $departmentId);
            $this->updateEmployeeDepartmentForPeriod($employeeId, $departmentId, $period['start'], $period['end'], $batchId);
            $eventRow = $sheet[$rowIndex + 1] ?? [];

            foreach ($dates as $dateIndex => $date) {
                $rawCell = $this->cellText($eventRow, $dateIndex);

                if ($rawCell === '') {
                    continue;
                }

                preg_match_all('/\d{1,2}:\d{2}/', $rawCell, $matches);
                $seenTimes = [];
                $sequence = 1;

                foreach ($matches[0] as $time) {
                    $normalizedTime = $this->normalizeTime($time);

                    if ($normalizedTime === null) {
                        continue;
                    }

                    $duplicate = isset($seenTimes[$normalizedTime]) ? 1 : 0;
                    $seenTimes[$normalizedTime] = true;
                    $this->upsertPunch($employeeId, $date, $normalizedTime, $sequence, $rawCell, $batchId, $rowIndex + 2, $dateIndex + 1, $duplicate);
                    $touched[$employeeId . '|' . $date] = [$employeeId, $date];
                    $punches++;
                    $sequence++;
                }
            }
        }

        $dailyRecords = 0;

        foreach ($touched as [$employeeId, $date]) {
            $this->recomputeDailyRecord((int) $employeeId, (string) $date, $batchId);
            $dailyRecords++;
        }

        return ['punches' => $punches, 'daily_records' => $dailyRecords];
    }

    /**
     * @param array<string, array<int, array<int, string|int|float>>> $sheets
     */
    private function processExceptions(array $sheets, int $batchId): int
    {
        $sheet = $this->findSheet($sheets, ['Reporte de Excepciones']);

        if ($sheet === null) {
            return 0;
        }

        $count = 0;

        foreach ($sheet as $rowIndex => $row) {
            $externalCode = $this->cellText($row, 0);
            $date = $this->cleanDate($this->cellText($row, 3));

            if ($rowIndex < 4 || $externalCode === '' || $date === null || $this->normalizeText($externalCode) === 'id') {
                continue;
            }

            $departmentId = $this->upsertDepartment($this->cellText($row, 2) ?: 'Sin departamento');
            $employeeId = $this->upsertEmployee($externalCode, $this->cellText($row, 1) ?: $externalCode, $departmentId);
            $dailyRecordId = $this->ensureDailyRecord($employeeId, $departmentId, $date, $batchId);

            $statement = $this->db->prepare('
                INSERT INTO attendance_daily_exceptions (
                    daily_record_id,
                    employee_id,
                    work_date,
                    first_schedule_in,
                    first_schedule_out,
                    second_schedule_in,
                    second_schedule_out,
                    late_minutes,
                    early_leave_minutes,
                    absence_minutes,
                    total_minutes,
                    notes,
                    source_import_batch_id,
                    created_at
                ) VALUES (
                    :daily_record_id,
                    :employee_id,
                    :work_date,
                    :first_schedule_in,
                    :first_schedule_out,
                    :second_schedule_in,
                    :second_schedule_out,
                    :late_minutes,
                    :early_leave_minutes,
                    :absence_minutes,
                    :total_minutes,
                    :notes,
                    :batch_id,
                    NOW()
                )
                ON DUPLICATE KEY UPDATE
                    daily_record_id = VALUES(daily_record_id),
                    first_schedule_in = VALUES(first_schedule_in),
                    first_schedule_out = VALUES(first_schedule_out),
                    second_schedule_in = VALUES(second_schedule_in),
                    second_schedule_out = VALUES(second_schedule_out),
                    late_minutes = VALUES(late_minutes),
                    early_leave_minutes = VALUES(early_leave_minutes),
                    absence_minutes = VALUES(absence_minutes),
                    total_minutes = VALUES(total_minutes),
                    notes = VALUES(notes),
                    source_import_batch_id = VALUES(source_import_batch_id)
            ');
            $statement->execute([
                'daily_record_id' => $dailyRecordId,
                'employee_id' => $employeeId,
                'work_date' => $date,
                'first_schedule_in' => $this->nullableTime($this->cellText($row, 4)),
                'first_schedule_out' => $this->nullableTime($this->cellText($row, 5)),
                'second_schedule_in' => $this->nullableTime($this->cellText($row, 6)),
                'second_schedule_out' => $this->nullableTime($this->cellText($row, 7)),
                'late_minutes' => $this->cellInt($row, 8),
                'early_leave_minutes' => $this->cellInt($row, 9),
                'absence_minutes' => $this->cellInt($row, 10),
                'total_minutes' => $this->cellInt($row, 11),
                'notes' => $this->cellText($row, 12) ?: null,
                'batch_id' => $batchId,
            ]);

            $this->updateDailyExceptionTotals($dailyRecordId, $row);
            $count++;
        }

        return $count;
    }

    private function upsertDepartment(string $name): int
    {
        $name = $name !== '' ? $name : 'Sin departamento';
        $normalized = $this->normalizeText($name);
        $statement = $this->db->prepare('
            INSERT INTO departments (name, normalized_name, active, created_at)
            VALUES (:name, :normalized_name, 1, NOW())
            ON DUPLICATE KEY UPDATE
                id = LAST_INSERT_ID(id),
                name = VALUES(name),
                active = 1,
                updated_at = NOW()
        ');
        $statement->execute(['name' => $name, 'normalized_name' => $normalized]);

        return (int) $this->db->lastInsertId();
    }

    private function upsertEmployee(string $externalCode, string $displayName, int $departmentId): int
    {
        $externalCode = $externalCode !== '' ? $externalCode : $displayName;
        $displayName = $displayName !== '' ? $displayName : $externalCode;
        $statement = $this->db->prepare('
            INSERT INTO employees (
                external_code,
                display_name,
                current_department_id,
                employment_status,
                active,
                created_at
            ) VALUES (
                :external_code,
                :display_name,
                :department_id,
                "active",
                1,
                NOW()
            )
            ON DUPLICATE KEY UPDATE
                id = LAST_INSERT_ID(id),
                display_name = VALUES(display_name),
                current_department_id = VALUES(current_department_id),
                employment_status = "active",
                active = 1,
                updated_at = NOW()
        ');
        $statement->execute([
            'external_code' => $externalCode,
            'display_name' => $displayName,
            'department_id' => $departmentId,
        ]);

        return (int) $this->db->lastInsertId();
    }

    private function updateEmployeeDepartmentForPeriod(int $employeeId, int $departmentId, string $periodStart, string $periodEnd, int $batchId): void
    {
        $statement = $this->db->prepare('
            UPDATE attendance_daily_records
            SET
                department_id = :department_id,
                last_import_batch_id = :batch_id,
                updated_at = NOW()
            WHERE employee_id = :employee_id
                AND work_date BETWEEN :period_start AND :period_end
        ');
        $statement->execute([
            'department_id' => $departmentId,
            'batch_id' => $batchId,
            'employee_id' => $employeeId,
            'period_start' => $periodStart,
            'period_end' => $periodEnd,
        ]);
    }

    private function upsertShiftType(string $code): int
    {
        $category = match ($this->normalizeText($code)) {
            '25' => 'permission',
            '26' => 'exit',
            'nulo' => 'vacation',
            '0' => 'work',
            default => 'unknown',
        };
        $name = match ($category) {
            'permission' => 'Permiso',
            'exit' => 'Salida',
            'vacation' => 'Vacaciones',
            'work' => 'Turno normal',
            default => 'Turno ' . $code,
        };
        $statement = $this->db->prepare('
            INSERT INTO shift_types (
                external_code,
                name,
                category,
                paid,
                counts_as_attendance,
                active,
                created_at
            ) VALUES (
                :code,
                :name,
                :category,
                :paid,
                :counts_as_attendance,
                1,
                NOW()
            )
            ON DUPLICATE KEY UPDATE
                id = LAST_INSERT_ID(id),
                name = VALUES(name),
                category = VALUES(category),
                active = 1,
                updated_at = NOW()
        ');
        $statement->execute([
            'code' => $code,
            'name' => $name,
            'category' => $category,
            'paid' => in_array($category, ['work', 'permission', 'exit', 'vacation'], true) ? 1 : 0,
            'counts_as_attendance' => in_array($category, ['work', 'exit'], true) ? 1 : 0,
        ]);

        return (int) $this->db->lastInsertId();
    }

    private function upsertSchedule(int $employeeId, int $departmentId, string $date, int $shiftId, string $rawCode, int $batchId, int $sourceRow, int $sourceColumn): int
    {
        $statement = $this->db->prepare('
            INSERT INTO employee_day_schedules (
                employee_id,
                work_date,
                shift_type_id,
                department_id,
                raw_shift_code,
                source_import_batch_id,
                source_sheet,
                source_row,
                source_column,
                created_at
            ) VALUES (
                :employee_id,
                :work_date,
                :shift_type_id,
                :department_id,
                :raw_shift_code,
                :batch_id,
                "Reporte de Turnos",
                :source_row,
                :source_column,
                NOW()
            )
            ON DUPLICATE KEY UPDATE
                id = LAST_INSERT_ID(id),
                shift_type_id = VALUES(shift_type_id),
                department_id = VALUES(department_id),
                raw_shift_code = VALUES(raw_shift_code),
                source_import_batch_id = VALUES(source_import_batch_id),
                source_sheet = VALUES(source_sheet),
                source_row = VALUES(source_row),
                source_column = VALUES(source_column),
                updated_at = NOW()
        ');
        $statement->execute([
            'employee_id' => $employeeId,
            'work_date' => $date,
            'shift_type_id' => $shiftId,
            'department_id' => $departmentId,
            'raw_shift_code' => $rawCode,
            'batch_id' => $batchId,
            'source_row' => $sourceRow,
            'source_column' => $sourceColumn,
        ]);

        return (int) $this->db->lastInsertId();
    }

    private function upsertDailyRecordFromSchedule(int $employeeId, int $departmentId, string $date, int $scheduleId, int $shiftId, int $batchId): void
    {
        $category = $this->shiftCategory($shiftId);
        $status = match ($category) {
            'permission' => 'permission',
            'vacation' => 'vacation',
            'exit' => 'exit',
            'rest' => 'rest',
            'absence' => 'absent',
            default => 'unknown',
        };

        $statement = $this->db->prepare('
            INSERT INTO attendance_daily_records (
                employee_id,
                department_id,
                work_date,
                schedule_id,
                status,
                last_import_batch_id,
                created_at
            ) VALUES (
                :employee_id,
                :department_id,
                :work_date,
                :schedule_id,
                :status,
                :batch_id,
                NOW()
            )
            ON DUPLICATE KEY UPDATE
                department_id = VALUES(department_id),
                schedule_id = VALUES(schedule_id),
                status = IF(status IN ("present", "incomplete"), status, VALUES(status)),
                last_import_batch_id = VALUES(last_import_batch_id),
                updated_at = NOW()
        ');
        $statement->execute([
            'employee_id' => $employeeId,
            'department_id' => $departmentId,
            'work_date' => $date,
            'schedule_id' => $scheduleId,
            'status' => $status,
            'batch_id' => $batchId,
        ]);
    }

    private function upsertPunch(int $employeeId, string $date, string $time, int $sequence, string $rawCell, int $batchId, int $sourceRow, int $sourceColumn, int $duplicate): void
    {
        $statement = $this->db->prepare('
            INSERT INTO attendance_punches (
                employee_id,
                work_date,
                punch_time,
                punch_datetime,
                sequence_number,
                raw_cell_value,
                source_import_batch_id,
                source_sheet,
                source_row,
                source_column,
                is_duplicate,
                created_at
            ) VALUES (
                :employee_id,
                :work_date,
                :punch_time,
                :punch_datetime,
                :sequence_number,
                :raw_cell_value,
                :batch_id,
                "Reporte de Asistencia",
                :source_row,
                :source_column,
                :is_duplicate,
                NOW()
            )
            ON DUPLICATE KEY UPDATE
                sequence_number = VALUES(sequence_number),
                punch_datetime = VALUES(punch_datetime),
                raw_cell_value = VALUES(raw_cell_value),
                source_import_batch_id = VALUES(source_import_batch_id),
                source_sheet = VALUES(source_sheet),
                source_row = VALUES(source_row),
                source_column = VALUES(source_column),
                is_duplicate = VALUES(is_duplicate)
        ');
        $statement->execute([
            'employee_id' => $employeeId,
            'work_date' => $date,
            'punch_time' => $time,
            'punch_datetime' => $date . ' ' . $time,
            'sequence_number' => $sequence,
            'raw_cell_value' => $rawCell,
            'batch_id' => $batchId,
            'source_row' => $sourceRow,
            'source_column' => $sourceColumn,
            'is_duplicate' => $duplicate,
        ]);
    }

    private function ensureDailyRecord(int $employeeId, ?int $departmentId, string $date, int $batchId): int
    {
        $departmentId = $departmentId !== null && $departmentId > 0 ? $departmentId : null;
        $statement = $this->db->prepare('
            INSERT INTO attendance_daily_records (
                employee_id,
                department_id,
                work_date,
                status,
                last_import_batch_id,
                created_at
            ) VALUES (
                :employee_id,
                :department_id,
                :work_date,
                "unknown",
                :batch_id,
                NOW()
            )
            ON DUPLICATE KEY UPDATE
                id = LAST_INSERT_ID(id),
                department_id = COALESCE(VALUES(department_id), department_id),
                last_import_batch_id = VALUES(last_import_batch_id),
                updated_at = NOW()
        ');
        $statement->execute([
            'employee_id' => $employeeId,
            'department_id' => $departmentId,
            'work_date' => $date,
            'batch_id' => $batchId,
        ]);

        return (int) $this->db->lastInsertId();
    }

    private function recomputeDailyRecord(int $employeeId, string $date, int $batchId): void
    {
        $statement = $this->db->prepare('
            SELECT DATE_FORMAT(punch_time, "%H:%i:%s") AS punch_time
            FROM attendance_punches
            WHERE employee_id = :employee_id AND work_date = :work_date
            ORDER BY punch_time ASC
        ');
        $statement->execute(['employee_id' => $employeeId, 'work_date' => $date]);
        $times = array_column($statement->fetchAll(), 'punch_time');

        $metaStatement = $this->db->prepare('
            SELECT
                s.id AS schedule_id,
                s.department_id,
                s.expected_minutes,
                e.current_department_id
            FROM employees e
            LEFT JOIN employee_day_schedules s ON s.employee_id = e.id AND s.work_date = :work_date
            WHERE e.id = :employee_id
            ORDER BY s.id DESC
            LIMIT 1
        ');
        $metaStatement->execute(['employee_id' => $employeeId, 'work_date' => $date]);
        $meta = $metaStatement->fetch() ?: [];

        $count = count($times);
        $status = $count >= 2 && $count % 2 === 0 ? 'present' : 'incomplete';
        $workedMinutes = 0;

        for ($i = 0; $i + 1 < $count; $i += 2) {
            $workedMinutes += $this->minutesBetween($times[$i], $times[$i + 1]);
        }

        $departmentId = $meta['department_id'] ?? $meta['current_department_id'] ?? null;
        $departmentId = $departmentId !== null ? (int) $departmentId : null;
        $dailyRecordId = $this->ensureDailyRecord($employeeId, $departmentId, $date, $batchId);

        $update = $this->db->prepare('
            UPDATE attendance_daily_records
            SET
                department_id = COALESCE(:department_id, department_id),
                schedule_id = :schedule_id,
                status = :status,
                first_in = :first_in,
                first_out = :first_out,
                second_in = :second_in,
                second_out = :second_out,
                expected_minutes = :expected_minutes,
                worked_minutes = :worked_minutes,
                last_import_batch_id = :batch_id,
                updated_at = NOW()
            WHERE id = :id
        ');
        $update->execute([
            'schedule_id' => $meta['schedule_id'] ?? null,
            'department_id' => $departmentId !== null && $departmentId > 0 ? $departmentId : null,
            'status' => $status,
            'first_in' => $times[0] ?? null,
            'first_out' => $times[1] ?? null,
            'second_in' => $times[2] ?? null,
            'second_out' => $times[3] ?? null,
            'expected_minutes' => $meta['expected_minutes'] ?? null,
            'worked_minutes' => $workedMinutes > 0 ? $workedMinutes : null,
            'batch_id' => $batchId,
            'id' => $dailyRecordId,
        ]);
    }

    /**
     * @param array<int, string|int|float> $row
     */
    private function updateDailyExceptionTotals(int $dailyRecordId, array $row): void
    {
        $statement = $this->db->prepare('
            UPDATE attendance_daily_records
            SET
                late_minutes = :late_minutes,
                early_leave_minutes = :early_leave_minutes,
                absence_minutes = :absence_minutes,
                notes = :notes,
                updated_at = NOW()
            WHERE id = :id
        ');
        $statement->execute([
            'late_minutes' => $this->cellInt($row, 8),
            'early_leave_minutes' => $this->cellInt($row, 9),
            'absence_minutes' => $this->cellInt($row, 10),
            'notes' => $this->cellText($row, 12) ?: null,
            'id' => $dailyRecordId,
        ]);
    }

    private function markBatchProcessed(int $batchId, array $summary): void
    {
        $statement = $this->db->prepare('
            UPDATE attendance_import_batches
            SET
                status = "processed",
                processed_at = NOW(),
                summary_json = :summary,
                error_message = NULL,
                updated_at = NOW()
            WHERE id = :id
        ');
        $statement->execute([
            'id' => $batchId,
            'summary' => json_encode($summary, JSON_UNESCAPED_UNICODE),
        ]);
    }

    /**
     * @param array<string, array<int, array<int, string|int|float>>> $sheets
     * @param array<int, string> $aliases
     * @return array<int, array<int, string|int|float>>|null
     */
    private function findSheet(array $sheets, array $aliases): ?array
    {
        $normalizedAliases = array_map(fn (string $alias): string => $this->normalizeText($alias), $aliases);

        foreach ($sheets as $name => $rows) {
            if (in_array($this->normalizeText((string) $name), $normalizedAliases, true)) {
                return $rows;
            }
        }

        return null;
    }

    /**
     * @return array<int, string>
     */
    private function dateRange(string $start, string $end): array
    {
        $dates = [];
        $current = new \DateTimeImmutable($start);
        $last = new \DateTimeImmutable($end);

        while ($current <= $last) {
            $dates[] = $current->format('Y-m-d');
            $current = $current->modify('+1 day');
        }

        return $dates;
    }

    /**
     * @param array<int, string|int|float> $row
     */
    private function cellText(array $row, int $column): string
    {
        if (!array_key_exists($column, $row)) {
            return '';
        }

        return trim((string) $row[$column]);
    }

    /**
     * @param array<int, string|int|float> $row
     */
    private function cellInt(array $row, int $column): int
    {
        return (int) round((float) str_replace(',', '.', $this->cellText($row, $column)));
    }

    /**
     * @param array<int, string|int|float> $row
     */
    private function cellFloat(array $row, int $column): float
    {
        return (float) str_replace(',', '.', $this->cellText($row, $column));
    }

    /**
     * @param array<int, string|int|float> $row
     */
    private function nullableFloat(array $row, int $column): ?float
    {
        $value = $this->cellText($row, $column);

        return $value === '' ? null : (float) str_replace(',', '.', $value);
    }

    private function parseDurationMinutes(string $value): int
    {
        if (!preg_match('/^(-?\d+):(\d{2})$/', trim($value), $matches)) {
            return (int) round((float) str_replace(',', '.', $value));
        }

        $hours = (int) $matches[1];
        $minutes = (int) $matches[2];

        return ($hours * 60) + ($hours < 0 ? -$minutes : $minutes);
    }

    /**
     * @return array{0: float|null, 1: float|null}
     */
    private function parseDayPair(string $value): array
    {
        if (!str_contains($value, '/')) {
            return [null, null];
        }

        [$scheduled, $attended] = array_pad(explode('/', $value, 2), 2, null);

        return [
            $scheduled !== null ? (float) str_replace(',', '.', trim($scheduled)) : null,
            $attended !== null ? (float) str_replace(',', '.', trim($attended)) : null,
        ];
    }

    private function normalizeTime(string $value): ?string
    {
        if (!preg_match('/^(\d{1,2}):(\d{2})$/', trim($value), $matches)) {
            return null;
        }

        $hour = (int) $matches[1];
        $minute = (int) $matches[2];

        if ($hour > 23 || $minute > 59) {
            return null;
        }

        return sprintf('%02d:%02d:00', $hour, $minute);
    }

    private function nullableTime(string $value): ?string
    {
        return $value === '' ? null : $this->normalizeTime($value);
    }

    private function cleanDate(string $value): ?string
    {
        $value = trim($value);

        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) ? $value : null;
    }

    private function minutesBetween(string $start, string $end): int
    {
        [$startHour, $startMinute] = array_map('intval', explode(':', substr($start, 0, 5)));
        [$endHour, $endMinute] = array_map('intval', explode(':', substr($end, 0, 5)));
        $startTotal = ($startHour * 60) + $startMinute;
        $endTotal = ($endHour * 60) + $endMinute;

        if ($endTotal < $startTotal) {
            $endTotal += 24 * 60;
        }

        return $endTotal - $startTotal;
    }

    private function shiftCategory(int $shiftId): string
    {
        $statement = $this->db->prepare('SELECT category FROM shift_types WHERE id = :id');
        $statement->execute(['id' => $shiftId]);

        return (string) ($statement->fetchColumn() ?: 'unknown');
    }

    private function normalizeText(string $value): string
    {
        $value = mb_strtolower(trim($value), 'UTF-8');
        $value = strtr($value, [
            'á' => 'a',
            'é' => 'e',
            'í' => 'i',
            'ó' => 'o',
            'ú' => 'u',
            'ñ' => 'n',
        ]);
        $value = preg_replace('/[^a-z0-9]+/', ' ', $value) ?? $value;

        return trim(preg_replace('/\s+/', ' ', $value) ?? $value);
    }
}
