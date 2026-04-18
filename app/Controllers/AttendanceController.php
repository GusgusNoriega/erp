<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
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
