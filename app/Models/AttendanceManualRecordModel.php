<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use PDO;
use RuntimeException;
use Throwable;

class AttendanceManualRecordModel
{
    private const STATUSES = [
        'present' => 'Presente',
        'absent' => 'Ausente',
        'permission' => 'Permiso',
        'vacation' => 'Vacaciones',
        'exit' => 'Salida',
        'rest' => 'Descanso',
        'holiday' => 'Festivo',
        'incomplete' => 'Incompleto',
        'unknown' => 'Sin clasificar',
    ];

    private PDO $db;

    public function __construct()
    {
        $this->db = Database::connection();
    }

    /**
     * @param array<string, mixed> $query
     * @return array{date_from: string, date_to: string, department_id: string, employee_id: string, status: string}
     */
    public function filtersFromQuery(array $query): array
    {
        $period = $this->defaultPeriod();
        $dateFrom = $this->cleanDate((string) ($query['date_from'] ?? '')) ?: $period['date_from'];
        $dateTo = $this->cleanDate((string) ($query['date_to'] ?? '')) ?: $period['date_to'];

        if ($dateFrom !== '' && $dateTo !== '' && $dateFrom > $dateTo) {
            [$dateFrom, $dateTo] = [$dateTo, $dateFrom];
        }

        $status = (string) ($query['status'] ?? '');

        if ($status !== '' && !array_key_exists($status, self::STATUSES)) {
            $status = '';
        }

        return [
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'department_id' => $this->cleanNumericFilter($query['department_id'] ?? ''),
            'employee_id' => $this->cleanNumericFilter($query['employee_id'] ?? ''),
            'status' => $status,
        ];
    }

    /** @return array<string, string> */
    public function statuses(): array
    {
        return self::STATUSES;
    }

    /** @return array<int, array{id: int, name: string}> */
    public function departments(): array
    {
        return $this->db
            ->query('SELECT id, name FROM departments WHERE active = 1 ORDER BY name')
            ->fetchAll();
    }

    /** @return array<int, array{id: int, external_code: string, display_name: string}> */
    public function employees(): array
    {
        return $this->db
            ->query('SELECT id, external_code, display_name FROM employees WHERE active = 1 ORDER BY display_name, external_code')
            ->fetchAll();
    }

    /**
     * @param array<string, string> $filters
     * @return array{
     *   rows: array<int, array<string, mixed>>,
     *   totals: array{records: int, employees: int, incomplete: int}
     * }
     */
    public function records(array $filters): array
    {
        if ($filters['date_from'] === '' || $filters['date_to'] === '') {
            return [
                'rows' => [],
                'totals' => ['records' => 0, 'employees' => 0, 'incomplete' => 0],
            ];
        }

        $conditions = ['dr.work_date BETWEEN :date_from AND :date_to'];
        $params = [
            'date_from' => $filters['date_from'],
            'date_to' => $filters['date_to'],
        ];

        if ($filters['department_id'] !== '') {
            $conditions[] = 'COALESCE(dr.department_id, e.current_department_id) = :department_id';
            $params['department_id'] = $filters['department_id'];
        }

        if ($filters['employee_id'] !== '') {
            $conditions[] = 'e.id = :employee_id';
            $params['employee_id'] = $filters['employee_id'];
        }

        if ($filters['status'] !== '') {
            $conditions[] = 'dr.status = :status';
            $params['status'] = $filters['status'];
        }

        $statement = $this->db->prepare('
            SELECT
                dr.*,
                e.external_code,
                e.display_name,
                COALESCE(d.name, current_department.name, "Sin departamento") AS department_name,
                b.source_filename
            FROM attendance_daily_records dr
            INNER JOIN employees e ON e.id = dr.employee_id
            LEFT JOIN departments d ON d.id = dr.department_id
            LEFT JOIN departments current_department ON current_department.id = e.current_department_id
            LEFT JOIN attendance_import_batches b ON b.id = dr.last_import_batch_id
            WHERE ' . implode(' AND ', $conditions) . '
            ORDER BY dr.work_date DESC, department_name, e.display_name, e.external_code
            LIMIT 250
        ');
        $statement->execute($params);
        $rows = $statement->fetchAll();

        $employeeIds = [];
        $incomplete = 0;

        foreach ($rows as $row) {
            $employeeIds[(string) $row['employee_id']] = true;

            if (($row['status'] ?? '') === 'incomplete') {
                $incomplete++;
            }
        }

        return [
            'rows' => $rows,
            'totals' => [
                'records' => count($rows),
                'employees' => count($employeeIds),
                'incomplete' => $incomplete,
            ],
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    public function updateFromPost(array $data): void
    {
        $recordId = (int) ($data['record_id'] ?? 0);

        if ($recordId <= 0) {
            throw new RuntimeException('Selecciona un registro valido para editar.');
        }

        $record = $this->findRecord($recordId);

        if ($record === null) {
            throw new RuntimeException('El registro indicado ya no existe.');
        }

        $departmentId = $this->nullableDepartmentId($data['department_id'] ?? '');
        $status = (string) ($data['status'] ?? 'unknown');

        if (!array_key_exists($status, self::STATUSES)) {
            throw new RuntimeException('Selecciona un estado valido.');
        }

        $firstIn = $this->nullableTime((string) ($data['first_in'] ?? ''));
        $firstOut = $this->nullableTime((string) ($data['first_out'] ?? ''));
        $secondIn = $this->nullableTime((string) ($data['second_in'] ?? ''));
        $secondOut = $this->nullableTime((string) ($data['second_out'] ?? ''));
        $workedMinutes = $this->workedMinutes([$firstIn, $firstOut, $secondIn, $secondOut]);
        $notes = trim((string) ($data['notes'] ?? ''));

        $this->db->beginTransaction();

        try {
            $statement = $this->db->prepare('
                UPDATE attendance_daily_records
                SET
                    department_id = :department_id,
                    status = :status,
                    first_in = :first_in,
                    first_out = :first_out,
                    second_in = :second_in,
                    second_out = :second_out,
                    worked_minutes = :worked_minutes,
                    notes = :notes,
                    updated_at = NOW()
                WHERE id = :id
            ');
            $statement->execute([
                'department_id' => $departmentId,
                'status' => $status,
                'first_in' => $firstIn,
                'first_out' => $firstOut,
                'second_in' => $secondIn,
                'second_out' => $secondOut,
                'worked_minutes' => $workedMinutes,
                'notes' => $notes !== '' ? $notes : null,
                'id' => $recordId,
            ]);

            $this->recordManualEvent(
                $recordId,
                (int) $record['employee_id'],
                (string) $record['work_date'],
                $notes
            );

            $this->db->commit();
        } catch (Throwable $exception) {
            $this->db->rollBack();
            throw $exception;
        }
    }

    /** @return array<string, mixed>|null */
    private function findRecord(int $id): ?array
    {
        $statement = $this->db->prepare('SELECT id, employee_id, work_date FROM attendance_daily_records WHERE id = :id');
        $statement->execute(['id' => $id]);
        $record = $statement->fetch();

        return $record === false ? null : $record;
    }

    private function nullableDepartmentId(mixed $value): ?int
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        if (!ctype_digit($value)) {
            throw new RuntimeException('Selecciona un departamento valido.');
        }

        $departmentId = (int) $value;
        $statement = $this->db->prepare('SELECT COUNT(*) FROM departments WHERE id = :id AND active = 1');
        $statement->execute(['id' => $departmentId]);

        if ((int) $statement->fetchColumn() === 0) {
            throw new RuntimeException('El departamento seleccionado no esta activo.');
        }

        return $departmentId;
    }

    /**
     * @param array<int, string|null> $times
     */
    private function workedMinutes(array $times): ?int
    {
        $total = 0;

        for ($i = 0; $i + 1 < count($times); $i += 2) {
            if ($times[$i] === null || $times[$i + 1] === null) {
                continue;
            }

            $total += $this->minutesBetween($times[$i], $times[$i + 1]);
        }

        return $total > 0 ? $total : null;
    }

    private function recordManualEvent(int $recordId, int $employeeId, string $date, string $notes): void
    {
        $statement = $this->db->prepare('
            INSERT INTO attendance_day_events (
                daily_record_id,
                employee_id,
                event_date,
                event_type,
                source,
                notes,
                created_at
            ) VALUES (
                :daily_record_id,
                :employee_id,
                :event_date,
                "manual_adjustment",
                "manual",
                :notes,
                NOW()
            )
        ');
        $statement->execute([
            'daily_record_id' => $recordId,
            'employee_id' => $employeeId,
            'event_date' => $date,
            'notes' => $notes !== '' ? $notes : 'Ajuste manual del registro diario.',
        ]);
    }

    /** @return array{date_from: string, date_to: string} */
    private function defaultPeriod(): array
    {
        $row = $this->db
            ->query('SELECT MIN(work_date) AS date_from, MAX(work_date) AS date_to FROM attendance_daily_records')
            ->fetch();

        if (($row['date_from'] ?? null) && ($row['date_to'] ?? null)) {
            return [
                'date_from' => (string) $row['date_from'],
                'date_to' => (string) $row['date_to'],
            ];
        }

        $batch = $this->db
            ->query('SELECT period_start, period_end FROM attendance_import_batches ORDER BY id DESC LIMIT 1')
            ->fetch();

        if (($batch['period_start'] ?? null) && ($batch['period_end'] ?? null)) {
            return [
                'date_from' => (string) $batch['period_start'],
                'date_to' => (string) $batch['period_end'],
            ];
        }

        return ['date_from' => '', 'date_to' => ''];
    }

    private function nullableTime(string $value): ?string
    {
        $value = trim($value);

        if ($value === '') {
            return null;
        }

        if (!preg_match('/^(\d{1,2}):(\d{2})(?::\d{2})?$/', $value, $matches)) {
            throw new RuntimeException('Ingresa las horas con formato HH:MM.');
        }

        $hour = (int) $matches[1];
        $minute = (int) $matches[2];

        if ($hour > 23 || $minute > 59) {
            throw new RuntimeException('Ingresa una hora valida.');
        }

        return sprintf('%02d:%02d:00', $hour, $minute);
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

    private function cleanDate(string $value): string
    {
        $value = trim($value);

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return '';
        }

        return $value;
    }

    private function cleanNumericFilter(mixed $value): string
    {
        $value = trim((string) $value);

        return ctype_digit($value) ? $value : '';
    }
}
