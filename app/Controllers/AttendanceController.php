<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Models\AttendanceManualRecordModel;
use App\Models\AttendancePunchModel;
use App\Models\AttendanceScheduleModel;
use App\Models\AttendanceStatisticsModel;
use App\Services\AttendanceImportService;
use Throwable;

class AttendanceController extends Controller
{
    public function schedules(string $currentPath = '/asistencia/turnos'): void
    {
        Auth::requireLogin();
        $model = new AttendanceScheduleModel();
        $filters = $model->filtersFromQuery($_GET);
        $matrix = $model->scheduleMatrix($filters);

        $this->render('attendance/schedules', [
            'pageTitle' => 'Reporte de Turnos',
            'currentPath' => $currentPath,
            'viewTag' => 'Asistencia',
            'viewTitle' => 'Reporte de Turnos',
            'viewDescription' => 'Consulta la programacion por empleado y fecha con filtros por periodo, departamento, persona y tipo de turno.',
            'filters' => $filters,
            'departments' => $model->departments(),
            'employees' => $model->employees(),
            'shiftTypes' => $model->shiftTypes(),
            'dates' => $model->dateColumns($filters),
            'scheduleRows' => $matrix['rows'],
            'totals' => $matrix['totals'],
        ]);
    }

    public function statistics(string $currentPath = '/asistencia/estadisticas'): void
    {
        Auth::requireLogin();
        $model = new AttendanceStatisticsModel();
        $filters = $model->filtersFromQuery($_GET);
        $summary = $model->periodSummaries($filters);

        $this->render('attendance/statistics', [
            'pageTitle' => 'Reporte Estadistico',
            'currentPath' => $currentPath,
            'viewTag' => 'Asistencia',
            'viewTitle' => 'Reporte Estadistico',
            'viewDescription' => 'Revisa el resumen por empleado del periodo: horas, retardos, salidas temprano, faltas, permisos, tiempo extra y pago real.',
            'filters' => $filters,
            'departments' => $model->departments(),
            'employees' => $model->employees(),
            'summaryRows' => $summary['rows'],
            'totals' => $summary['totals'],
        ]);
    }

    public function punches(string $currentPath = '/asistencia/marcaciones'): void
    {
        Auth::requireLogin();
        $model = new AttendancePunchModel();
        $filters = $model->filtersFromQuery($_GET);
        $punchDays = $model->punchDays($filters);

        $this->render('attendance/punches', [
            'pageTitle' => 'Reporte de Asistencia',
            'currentPath' => $currentPath,
            'viewTag' => 'Asistencia',
            'viewTitle' => 'Reporte de Asistencia',
            'viewDescription' => 'Ordena y revisa las marcaciones diarias por empleado, fecha, primera hora, ultima hora y cantidad de eventos.',
            'filters' => $filters,
            'departments' => $model->departments(),
            'employees' => $model->employees(),
            'punchRows' => $punchDays['rows'],
            'totals' => $punchDays['totals'],
        ]);
    }

    public function exportPunches(string $currentPath = '/asistencia/marcaciones/exportar'): void
    {
        Auth::requireLogin();
        @set_time_limit(0);

        $model = new AttendancePunchModel();
        $filters = $model->filtersFromQuery($_GET);
        $searchTerm = mb_substr(trim((string) ($_GET['search'] ?? '')), 0, 120, 'UTF-8');
        $normalizedSearch = mb_strtolower($searchTerm, 'UTF-8');
        $filename = sprintf(
            'reporte_asistencia_%s_a_%s.xls',
            $filters['date_from'] !== '' ? $filters['date_from'] : 'sin_fecha',
            $filters['date_to'] !== '' ? $filters['date_to'] : 'sin_fecha'
        );

        header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');

        $escape = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $formatTime = static function (string|null $time): string {
            if ($time === null || $time === '') {
                return '-';
            }

            return substr($time, 0, 5);
        };
        $statusLabel = static function (int $punchCount, int $duplicateCount): string {
            if ($punchCount < 2 || $punchCount % 2 !== 0) {
                return 'Revisar';
            }

            if ($duplicateCount > 0) {
                return 'Duplicadas: ' . $duplicateCount;
            }

            return 'Completo';
        };
        $exportValues = static function (array $row) use ($formatTime, $statusLabel): array {
            $punches = implode(', ', array_filter(explode(',', (string) ($row['punches_csv'] ?? ''))));
            $punchCount = (int) ($row['punch_count'] ?? 0);
            $duplicateCount = (int) ($row['duplicate_count'] ?? 0);

            return [
                'Fecha' => (string) ($row['work_date'] ?? ''),
                'Empleado' => trim((string) ($row['display_name'] ?? '') . "\n" . (string) ($row['external_code'] ?? '')),
                'Departamento' => (string) ($row['department_name'] ?? ''),
                'Primera' => $formatTime($row['first_punch'] ?? null),
                'Ultima' => $formatTime($row['last_punch'] ?? null),
                'Cantidad' => (string) $punchCount,
                'Marcaciones' => $punches,
                'Estado' => $statusLabel($punchCount, $duplicateCount),
                'Archivo' => (string) ($row['source_filename'] ?? ''),
            ];
        };

        echo "\xEF\xBB\xBF";
        echo '<!DOCTYPE html><html><head><meta charset="UTF-8" />';
        echo '<style>table{border-collapse:collapse;}th,td{border:1px solid #9ca3af;padding:6px;mso-number-format:"\@";}th{font-weight:bold;background:#e5edf8;}.number{text-align:right;}</style>';
        echo '</head><body><table>';
        echo '<thead><tr>';

        foreach (['Fecha', 'Empleado', 'Departamento', 'Primera', 'Ultima', 'Cantidad', 'Marcaciones', 'Estado', 'Archivo'] as $header) {
            echo '<th>' . $escape($header) . '</th>';
        }

        echo '</tr></thead><tbody>';

        $rowNumber = 0;
        $model->streamPunchDays($filters, static function (array $row) use ($escape, $exportValues, $normalizedSearch, &$rowNumber): void {
            $values = $exportValues($row);

            if ($normalizedSearch !== '') {
                $rowText = mb_strtolower(implode(' ', $values), 'UTF-8');

                if (!str_contains($rowText, $normalizedSearch)) {
                    return;
                }
            }

            echo '<tr>';
            echo '<td>' . $escape($values['Fecha']) . '</td>';
            echo '<td>' . str_replace("\n", '<br />', $escape($values['Empleado'])) . '</td>';
            echo '<td>' . $escape($values['Departamento']) . '</td>';
            echo '<td>' . $escape($values['Primera']) . '</td>';
            echo '<td>' . $escape($values['Ultima']) . '</td>';
            echo '<td class="number">' . $escape($values['Cantidad']) . '</td>';
            echo '<td>' . $escape($values['Marcaciones']) . '</td>';
            echo '<td>' . $escape($values['Estado']) . '</td>';
            echo '<td>' . $escape($values['Archivo']) . '</td>';
            echo '</tr>';

            $rowNumber++;

            if ($rowNumber % 500 === 0) {
                if (ob_get_level() > 0) {
                    ob_flush();
                }

                flush();
            }
        });

        echo '</tbody></table></body></html>';
    }

    public function manualRecords(string $currentPath = '/asistencia/manual'): void
    {
        Auth::requireLogin();
        $model = new AttendanceManualRecordModel();
        $filters = $model->filtersFromQuery($_GET);
        $records = $model->records($filters);

        $this->render('attendance/manual', [
            'pageTitle' => 'Edicion Manual de Asistencia',
            'currentPath' => $currentPath,
            'viewTag' => 'Asistencia',
            'viewTitle' => 'Edicion manual de registros',
            'viewDescription' => 'Ajusta departamento, estado, marcaciones consolidadas y notas cuando un registro requiera correccion manual.',
            'filters' => $filters,
            'departments' => $model->departments(),
            'employees' => $model->employees(),
            'statuses' => $model->statuses(),
            'recordRows' => $records['rows'],
            'totals' => $records['totals'],
            'error' => null,
            'success' => null,
        ]);
    }

    public function updateManualRecord(string $currentPath = '/asistencia/manual'): void
    {
        Auth::requireLogin();
        $model = new AttendanceManualRecordModel();
        $error = null;
        $success = null;

        try {
            $model->updateFromPost($_POST);
            $success = 'Registro actualizado correctamente.';
        } catch (Throwable $exception) {
            $error = $exception->getMessage();
        }

        $filters = $model->filtersFromQuery([
            'date_from' => $_POST['filter_date_from'] ?? '',
            'date_to' => $_POST['filter_date_to'] ?? '',
            'department_id' => $_POST['filter_department_id'] ?? '',
            'employee_id' => $_POST['filter_employee_id'] ?? '',
            'status' => $_POST['filter_status'] ?? '',
        ]);
        $records = $model->records($filters);

        $this->render('attendance/manual', [
            'pageTitle' => 'Edicion Manual de Asistencia',
            'currentPath' => $currentPath,
            'viewTag' => 'Asistencia',
            'viewTitle' => 'Edicion manual de registros',
            'viewDescription' => 'Ajusta departamento, estado, marcaciones consolidadas y notas cuando un registro requiera correccion manual.',
            'filters' => $filters,
            'departments' => $model->departments(),
            'employees' => $model->employees(),
            'statuses' => $model->statuses(),
            'recordRows' => $records['rows'],
            'totals' => $records['totals'],
            'error' => $error,
            'success' => $success,
        ]);
    }

    public function importForm(string $currentPath = '/asistencia/importar'): void
    {
        Auth::requireLogin();
        $service = new AttendanceImportService();

        $this->render('attendance/import', [
            'pageTitle' => 'Importar Excel de Asistencia',
            'currentPath' => $currentPath,
            'viewTag' => 'Importacion',
            'viewTitle' => 'Subir y procesar Excel',
            'viewDescription' => 'Carga reportes de asistencia del sistema, procesa sus hojas internas y actualiza los datos sin duplicar marcaciones exactas.',
            'result' => null,
            'error' => null,
            'recentBatches' => $service->recentBatches(),
        ]);
    }

    public function processImport(string $currentPath = '/asistencia/importar'): void
    {
        Auth::requireLogin();
        $service = new AttendanceImportService();
        $result = null;
        $error = null;

        try {
            $result = $service->processUploadedFile($_FILES['attendance_file'] ?? []);
        } catch (Throwable $exception) {
            $error = $exception->getMessage();
        }

        $this->render('attendance/import', [
            'pageTitle' => 'Importar Excel de Asistencia',
            'currentPath' => $currentPath,
            'viewTag' => 'Importacion',
            'viewTitle' => 'Subir y procesar Excel',
            'viewDescription' => 'Carga reportes de asistencia del sistema, procesa sus hojas internas y actualiza los datos sin duplicar marcaciones exactas.',
            'result' => $result,
            'error' => $error,
            'recentBatches' => $service->recentBatches(),
        ]);
    }
}
