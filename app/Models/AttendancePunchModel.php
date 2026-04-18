<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use PDO;

class AttendancePunchModel
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
     *   employee_id: string,
     *   min_punches: string,
     *   sort_by: string,
     *   sort_dir: string
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

        $sortBy = (string) ($query['sort_by'] ?? 'date');
        $allowedSorts = ['date', 'employee', 'department', 'first_punch', 'last_punch', 'punch_count'];

        if (!in_array($sortBy, $allowedSorts, true)) {
            $sortBy = 'date';
        }

        $sortDir = strtolower((string) ($query['sort_dir'] ?? 'desc'));

        if (!in_array($sortDir, ['asc', 'desc'], true)) {
            $sortDir = 'desc';
        }

        return [
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'department_id' => $this->cleanNumericFilter($query['department_id'] ?? ''),
            'employee_id' => $this->cleanNumericFilter($query['employee_id'] ?? ''),
            'min_punches' => $this->cleanNumericFilter($query['min_punches'] ?? ''),
            'sort_by' => $sortBy,
            'sort_dir' => $sortDir,
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
     *   totals: array{employees: int, days: int, punches: int, duplicates: int, incomplete_days: int}
     * }
     */
    public function punchDays(array $filters): array
    {
        if ($filters['date_from'] === '' || $filters['date_to'] === '') {
            return [
                'rows' => [],
                'totals' => [
                    'employees' => 0,
                    'days' => 0,
                    'punches' => 0,
                    'duplicates' => 0,
                    'incomplete_days' => 0,
                ],
            ];
        }

        $conditions = ['p.work_date BETWEEN :date_from AND :date_to'];
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

        $having = '';

        if ($filters['min_punches'] !== '') {
            $having = 'HAVING punch_count >= :min_punches';
            $params['min_punches'] = $filters['min_punches'];
        }

        $orderBy = $this->orderByClause($filters['sort_by'], $filters['sort_dir']);

        $sql = '
            SELECT
                e.id AS employee_id,
                e.external_code,
                e.display_name,
                COALESCE(d.name, current_department.name, "Sin departamento") AS department_name,
                p.work_date,
                COUNT(*) AS punch_count,
                SUM(CASE WHEN p.is_duplicate = 1 THEN 1 ELSE 0 END) AS duplicate_count,
                MIN(p.punch_time) AS first_punch,
                MAX(p.punch_time) AS last_punch,
                GROUP_CONCAT(DATE_FORMAT(p.punch_time, "%H:%i") ORDER BY p.sequence_number ASC, p.punch_time ASC SEPARATOR ",") AS punches_csv,
                MAX(b.source_filename) AS source_filename
            FROM attendance_punches p
            INNER JOIN employees e ON e.id = p.employee_id
            LEFT JOIN attendance_daily_records dr ON dr.employee_id = p.employee_id AND dr.work_date = p.work_date
            LEFT JOIN departments d ON d.id = dr.department_id
            LEFT JOIN departments current_department ON current_department.id = e.current_department_id
            LEFT JOIN attendance_import_batches b ON b.id = p.source_import_batch_id
            WHERE ' . implode(' AND ', $conditions) . '
            GROUP BY
                e.id,
                e.external_code,
                e.display_name,
                department_name,
                p.work_date
            ' . $having . '
            ORDER BY ' . $orderBy . '
        ';

        $statement = $this->db->prepare($sql);
        $statement->execute($params);
        $rows = $statement->fetchAll();

        $employeeIds = [];
        $totals = [
            'employees' => 0,
            'days' => count($rows),
            'punches' => 0,
            'duplicates' => 0,
            'incomplete_days' => 0,
        ];

        foreach ($rows as $row) {
            $employeeIds[(string) $row['employee_id']] = true;
            $punchCount = (int) $row['punch_count'];

            $totals['punches'] += $punchCount;
            $totals['duplicates'] += (int) $row['duplicate_count'];

            if ($punchCount < 2 || $punchCount % 2 !== 0) {
                $totals['incomplete_days']++;
            }
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
            ->query('SELECT MIN(work_date) AS date_from, MAX(work_date) AS date_to FROM attendance_punches')
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

    private function orderByClause(string $sortBy, string $sortDir): string
    {
        $direction = strtoupper($sortDir) === 'ASC' ? 'ASC' : 'DESC';

        return match ($sortBy) {
            'employee' => 'e.display_name ' . $direction . ', p.work_date DESC',
            'department' => 'department_name ' . $direction . ', e.display_name ASC, p.work_date DESC',
            'first_punch' => 'first_punch ' . $direction . ', e.display_name ASC',
            'last_punch' => 'last_punch ' . $direction . ', e.display_name ASC',
            'punch_count' => 'punch_count ' . $direction . ', e.display_name ASC, p.work_date DESC',
            default => 'p.work_date ' . $direction . ', e.display_name ASC',
        };
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

