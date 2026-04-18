<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use PDO;

class AttendanceStatisticsModel
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::connection();
    }

    /**
     * @param array<string, mixed> $query
     * @return array{
     *   date_from: string,
     *   date_to: string,
     *   department_id: string,
     *   employee_id: string
     * }
     */
    public function filtersFromQuery(array $query): array
    {
        $period = $this->defaultPeriod();

        $dateFrom = $this->cleanDate((string) ($query['date_from'] ?? '')) ?: $period['date_from'];
        $dateTo = $this->cleanDate((string) ($query['date_to'] ?? '')) ?: $period['date_to'];

        if ($dateFrom !== '' && $dateTo !== '' && $dateFrom > $dateTo) {
            [$dateFrom, $dateTo] = [$dateTo, $dateFrom];
        }

        return [
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'department_id' => $this->cleanNumericFilter($query['department_id'] ?? ''),
            'employee_id' => $this->cleanNumericFilter($query['employee_id'] ?? ''),
        ];
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
     *   totals: array{employees: int, normal_minutes: int, actual_minutes: int, late_minutes: int, early_leave_minutes: int, overtime_minutes: int, absence_days: float, permission_days: float}
     * }
     */
    public function periodSummaries(array $filters): array
    {
        if ($filters['date_from'] === '' || $filters['date_to'] === '') {
            return [
                'rows' => [],
                'totals' => [
                    'employees' => 0,
                    'normal_minutes' => 0,
                    'actual_minutes' => 0,
                    'late_minutes' => 0,
                    'early_leave_minutes' => 0,
                    'overtime_minutes' => 0,
                    'absence_days' => 0.0,
                    'permission_days' => 0.0,
                ],
            ];
        }

        $conditions = [
            'ps.period_start <= :date_to',
            'ps.period_end >= :date_from',
        ];
        $params = [
            'date_from' => $filters['date_from'],
            'date_to' => $filters['date_to'],
        ];

        if ($filters['department_id'] !== '') {
            $conditions[] = 'COALESCE(ps.department_id, e.current_department_id) = :department_id';
            $params['department_id'] = $filters['department_id'];
        }

        if ($filters['employee_id'] !== '') {
            $conditions[] = 'e.id = :employee_id';
            $params['employee_id'] = $filters['employee_id'];
        }

        $sql = '
            SELECT
                ps.*,
                e.external_code,
                e.display_name,
                COALESCE(d.name, current_department.name, "Sin departamento") AS department_name,
                b.source_filename,
                b.status AS import_status
            FROM attendance_period_summaries ps
            INNER JOIN employees e ON e.id = ps.employee_id
            LEFT JOIN departments d ON d.id = ps.department_id
            LEFT JOIN departments current_department ON current_department.id = e.current_department_id
            LEFT JOIN attendance_import_batches b ON b.id = ps.import_batch_id
            WHERE ' . implode(' AND ', $conditions) . '
            ORDER BY ps.period_start DESC, department_name, e.display_name, e.external_code
        ';

        $statement = $this->db->prepare($sql);
        $statement->execute($params);
        $rows = $statement->fetchAll();

        $employeeIds = [];
        $totals = [
            'employees' => 0,
            'normal_minutes' => 0,
            'actual_minutes' => 0,
            'late_minutes' => 0,
            'early_leave_minutes' => 0,
            'overtime_minutes' => 0,
            'absence_days' => 0.0,
            'permission_days' => 0.0,
        ];

        foreach ($rows as $row) {
            $employeeIds[(string) $row['employee_id']] = true;
            $totals['normal_minutes'] += (int) $row['normal_work_minutes'];
            $totals['actual_minutes'] += (int) $row['actual_work_minutes'];
            $totals['late_minutes'] += (int) $row['late_minutes'];
            $totals['early_leave_minutes'] += (int) $row['early_leave_minutes'];
            $totals['overtime_minutes'] += (int) $row['extra_workday_minutes'] + (int) $row['extra_holiday_minutes'];
            $totals['absence_days'] += (float) $row['absence_days'];
            $totals['permission_days'] += (float) $row['permission_days'];
        }

        $totals['employees'] = count($employeeIds);

        return [
            'rows' => $rows,
            'totals' => $totals,
        ];
    }

    /** @return array{date_from: string, date_to: string} */
    private function defaultPeriod(): array
    {
        $row = $this->db
            ->query('SELECT MIN(period_start) AS date_from, MAX(period_end) AS date_to FROM attendance_period_summaries')
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

