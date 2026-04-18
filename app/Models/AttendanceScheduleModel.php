<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use DateInterval;
use DatePeriod;
use DateTimeImmutable;
use PDO;

class AttendanceScheduleModel
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
     *   shift_type_id: string
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
            'shift_type_id' => $this->cleanNumericFilter($query['shift_type_id'] ?? ''),
        ];
    }

    /** @return array<int, array{id: int, name: string}> */
    public function departments(): array
    {
        $sql = 'SELECT id, name FROM departments WHERE active = 1 ORDER BY name';

        return $this->db->query($sql)->fetchAll();
    }

    /** @return array<int, array{id: int, external_code: string, display_name: string}> */
    public function employees(): array
    {
        $sql = 'SELECT id, external_code, display_name FROM employees WHERE active = 1 ORDER BY display_name, external_code';

        return $this->db->query($sql)->fetchAll();
    }

    /** @return array<int, array{id: int, external_code: string, name: string, category: string}> */
    public function shiftTypes(): array
    {
        $sql = 'SELECT id, external_code, name, category FROM shift_types WHERE active = 1 ORDER BY category, name';

        return $this->db->query($sql)->fetchAll();
    }

    /**
     * @param array<string, string> $filters
     * @return array<int, array{date: string, day: string, label: string, weekend: bool}>
     */
    public function dateColumns(array $filters): array
    {
        if ($filters['date_from'] === '' || $filters['date_to'] === '') {
            return [];
        }

        $start = new DateTimeImmutable($filters['date_from']);
        $end = new DateTimeImmutable($filters['date_to']);
        $maxEnd = $start->modify('+45 days');

        if ($end > $maxEnd) {
            $end = $maxEnd;
        }

        $period = new DatePeriod($start, new DateInterval('P1D'), $end->modify('+1 day'));
        $columns = [];

        foreach ($period as $date) {
            $weekdayNumber = (int) $date->format('N');
            $columns[] = [
                'date' => $date->format('Y-m-d'),
                'day' => $this->weekdayShort($weekdayNumber),
                'label' => $date->format('d/m'),
                'weekend' => $weekdayNumber >= 6,
            ];
        }

        return $columns;
    }

    /**
     * @param array<string, string> $filters
     * @return array{
     *   rows: array<int, array{
     *     employee_id: int,
     *     external_code: string,
     *     display_name: string,
     *     department_name: string,
     *     days: array<string, array{label: string, title: string, category: string, class: string}>
     *   }>,
     *   totals: array{employees: int, dates: int, schedules: int, special: int}
     * }
     */
    public function scheduleMatrix(array $filters): array
    {
        $dates = $this->dateColumns($filters);

        if ($dates === []) {
            return [
                'rows' => [],
                'totals' => ['employees' => 0, 'dates' => 0, 'schedules' => 0, 'special' => 0],
            ];
        }

        $conditions = ['s.work_date BETWEEN :date_from AND :date_to'];
        $params = [
            'date_from' => $filters['date_from'],
            'date_to' => $filters['date_to'],
        ];

        if ($filters['department_id'] !== '') {
            $conditions[] = 'COALESCE(s.department_id, e.current_department_id) = :department_id';
            $params['department_id'] = $filters['department_id'];
        }

        if ($filters['employee_id'] !== '') {
            $conditions[] = 'e.id = :employee_id';
            $params['employee_id'] = $filters['employee_id'];
        }

        if ($filters['shift_type_id'] !== '') {
            $conditions[] = 's.shift_type_id = :shift_type_id';
            $params['shift_type_id'] = $filters['shift_type_id'];
        }

        $sql = '
            SELECT
                e.id AS employee_id,
                e.external_code,
                e.display_name,
                COALESCE(d.name, current_department.name, "Sin departamento") AS department_name,
                s.work_date,
                s.raw_shift_code,
                st.external_code AS shift_code,
                st.name AS shift_name,
                st.category AS shift_category
            FROM employee_day_schedules s
            INNER JOIN employees e ON e.id = s.employee_id
            LEFT JOIN departments d ON d.id = s.department_id
            LEFT JOIN departments current_department ON current_department.id = e.current_department_id
            LEFT JOIN shift_types st ON st.id = s.shift_type_id
            WHERE ' . implode(' AND ', $conditions) . '
            ORDER BY department_name, e.display_name, e.external_code, s.work_date
        ';

        $statement = $this->db->prepare($sql);
        $statement->execute($params);

        $rows = [];
        $scheduleCount = 0;
        $specialCount = 0;

        foreach ($statement->fetchAll() as $record) {
            $employeeId = (int) $record['employee_id'];

            if (!isset($rows[$employeeId])) {
                $rows[$employeeId] = [
                    'employee_id' => $employeeId,
                    'external_code' => (string) $record['external_code'],
                    'display_name' => (string) $record['display_name'],
                    'department_name' => (string) $record['department_name'],
                    'days' => [],
                ];
            }

            $category = (string) ($record['shift_category'] ?? 'unknown');
            $label = (string) ($record['raw_shift_code'] ?: $record['shift_code'] ?: '-');
            $title = (string) ($record['shift_name'] ?: 'Turno sin catalogar');

            $rows[$employeeId]['days'][(string) $record['work_date']] = [
                'label' => $label,
                'title' => $title,
                'category' => $category,
                'class' => $this->shiftClass($category),
            ];

            $scheduleCount++;

            if (in_array($category, ['permission', 'exit', 'vacation', 'absence'], true)) {
                $specialCount++;
            }
        }

        return [
            'rows' => array_values($rows),
            'totals' => [
                'employees' => count($rows),
                'dates' => count($dates),
                'schedules' => $scheduleCount,
                'special' => $specialCount,
            ],
        ];
    }

    /** @return array{date_from: string, date_to: string} */
    private function defaultPeriod(): array
    {
        $row = $this->db
            ->query('SELECT MIN(work_date) AS date_from, MAX(work_date) AS date_to FROM employee_day_schedules')
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

    private function weekdayShort(int $weekdayNumber): string
    {
        return match ($weekdayNumber) {
            1 => 'Lun',
            2 => 'Mar',
            3 => 'Mie',
            4 => 'Jue',
            5 => 'Vie',
            6 => 'Sab',
            default => 'Dom',
        };
    }

    private function shiftClass(string $category): string
    {
        return match ($category) {
            'work' => 'work',
            'permission' => 'permission',
            'exit' => 'exit',
            'vacation' => 'vacation',
            'rest' => 'rest',
            'absence' => 'absence',
            default => 'unknown',
        };
    }
}

