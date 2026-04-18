<?php
/** @var array<string, string> $filters */
/** @var array<int, array{id: int, name: string}> $departments */
/** @var array<int, array{id: int, external_code: string, display_name: string}> $employees */
/** @var array<int, array<string, mixed>> $summaryRows */
/** @var array{employees: int, normal_minutes: int, actual_minutes: int, late_minutes: int, early_leave_minutes: int, overtime_minutes: int, absence_days: float, permission_days: float} $totals */

$filters = $filters ?? [
    'date_from' => '',
    'date_to' => '',
    'department_id' => '',
    'employee_id' => '',
];
$departments = $departments ?? [];
$employees = $employees ?? [];
$summaryRows = $summaryRows ?? [];
$totals = $totals ?? [
    'employees' => 0,
    'normal_minutes' => 0,
    'actual_minutes' => 0,
    'late_minutes' => 0,
    'early_leave_minutes' => 0,
    'overtime_minutes' => 0,
    'absence_days' => 0.0,
    'permission_days' => 0.0,
];

$formatMinutes = static function (int|string|null $minutes): string {
    $minutes = (int) ($minutes ?? 0);
    $sign = $minutes < 0 ? '-' : '';
    $minutes = abs($minutes);

    return sprintf('%s%d:%02d', $sign, intdiv($minutes, 60), $minutes % 60);
};

$formatDays = static function (float|string|null $days): string {
    return number_format((float) ($days ?? 0), 2, ',', '.');
};

$formatMoney = static function (float|string|null $amount): string {
    if ($amount === null || $amount === '') {
        return '-';
    }

    return '$ ' . number_format((float) $amount, 2, ',', '.');
};
?>

<section class="headline-card">
  <p class="eyebrow"><?= htmlspecialchars($viewTag ?? 'Asistencia', ENT_QUOTES, 'UTF-8') ?></p>
  <h1><?= htmlspecialchars($viewTitle ?? 'Reporte Estadistico', ENT_QUOTES, 'UTF-8') ?></h1>
  <p><?= htmlspecialchars($viewDescription ?? '', ENT_QUOTES, 'UTF-8') ?></p>
</section>

<section class="metrics-grid schedule-metrics" aria-label="Resumen estadistico">
  <article class="metric-card">
    <p class="metric-label">Empleados visibles</p>
    <p class="metric-value"><?= number_format((int) $totals['employees'], 0, ',', '.') ?></p>
    <p class="metric-trend up">Segun filtros activos</p>
  </article>

  <article class="metric-card">
    <p class="metric-label">Horas reales</p>
    <p class="metric-value"><?= htmlspecialchars($formatMinutes($totals['actual_minutes']), ENT_QUOTES, 'UTF-8') ?></p>
    <p class="metric-trend up">Normal: <?= htmlspecialchars($formatMinutes($totals['normal_minutes']), ENT_QUOTES, 'UTF-8') ?></p>
  </article>

  <article class="metric-card">
    <p class="metric-label">Retardos</p>
    <p class="metric-value"><?= htmlspecialchars($formatMinutes($totals['late_minutes']), ENT_QUOTES, 'UTF-8') ?></p>
    <p class="metric-trend warn">Salidas temprano: <?= htmlspecialchars($formatMinutes($totals['early_leave_minutes']), ENT_QUOTES, 'UTF-8') ?></p>
  </article>

  <article class="metric-card">
    <p class="metric-label">Novedades</p>
    <p class="metric-value"><?= htmlspecialchars($formatDays($totals['absence_days'] + $totals['permission_days']), ENT_QUOTES, 'UTF-8') ?></p>
    <p class="metric-trend down">Faltas + permisos en dias</p>
  </article>
</section>

<section class="panel filter-panel">
  <div class="panel-heading">
    <div>
      <p class="panel-kicker">Filtros</p>
      <h2>Segunda hoja: resumen estadistico</h2>
    </div>
    <a class="ghost-link" href="<?= htmlspecialchars(url('/asistencia/estadisticas'), ENT_QUOTES, 'UTF-8') ?>">Limpiar filtros</a>
  </div>

  <form class="filters-grid statistics-filters" method="get" action="<?= htmlspecialchars(url('/asistencia/estadisticas'), ENT_QUOTES, 'UTF-8') ?>">
    <label class="filter-field">
      <span>Desde</span>
      <input type="date" name="date_from" value="<?= htmlspecialchars($filters['date_from'], ENT_QUOTES, 'UTF-8') ?>" />
    </label>

    <label class="filter-field">
      <span>Hasta</span>
      <input type="date" name="date_to" value="<?= htmlspecialchars($filters['date_to'], ENT_QUOTES, 'UTF-8') ?>" />
    </label>

    <label class="filter-field">
      <span>Departamento</span>
      <select name="department_id">
        <option value="">Todos</option>
        <?php foreach ($departments as $department): ?>
          <?php $selected = (string) $department['id'] === $filters['department_id'] ? ' selected' : ''; ?>
          <option value="<?= (int) $department['id'] ?>"<?= $selected ?>>
            <?= htmlspecialchars($department['name'], ENT_QUOTES, 'UTF-8') ?>
          </option>
        <?php endforeach; ?>
      </select>
    </label>

    <label class="filter-field">
      <span>Empleado</span>
      <select name="employee_id">
        <option value="">Todos</option>
        <?php foreach ($employees as $employee): ?>
          <?php $selected = (string) $employee['id'] === $filters['employee_id'] ? ' selected' : ''; ?>
          <option value="<?= (int) $employee['id'] ?>"<?= $selected ?>>
            <?= htmlspecialchars($employee['display_name'] . ' - ' . $employee['external_code'], ENT_QUOTES, 'UTF-8') ?>
          </option>
        <?php endforeach; ?>
      </select>
    </label>

    <div class="filter-actions">
      <button class="ghost-btn primary-action" type="submit">Aplicar</button>
    </div>
  </form>
</section>

<section class="panel table-panel statistics-panel">
  <div class="panel-heading">
    <div>
      <p class="panel-kicker">Detalle</p>
      <h2>Resumen por empleado y periodo</h2>
    </div>
    <span class="panel-chip">Datos de la hoja estadistica</span>
  </div>

  <div class="table-wrap statistics-table-wrap">
    <table class="statistics-table">
      <thead>
        <tr>
          <th>Empleado</th>
          <th>Departamento</th>
          <th>Periodo</th>
          <th>H. normal</th>
          <th>H. real</th>
          <th>Asistencia</th>
          <th>Retardos</th>
          <th>Salidas temprano</th>
          <th>Extra laboral</th>
          <th>Extra festivo</th>
          <th>Salida dias</th>
          <th>Falta dias</th>
          <th>Permiso dias</th>
          <th>Pago real</th>
          <th>Notas</th>
        </tr>
      </thead>
      <tbody>
        <?php if ($summaryRows === []): ?>
          <tr>
            <td colspan="15" class="empty-state-cell">
              No hay resumen estadistico cargado para los filtros seleccionados. Cuando el importador procese la hoja `Reporte Estadistico`, la informacion aparecera aqui.
            </td>
          </tr>
        <?php endif; ?>

        <?php foreach ($summaryRows as $row): ?>
          <?php
            $period = sprintf(
                '%s a %s',
                (string) ($row['period_start'] ?? ''),
                (string) ($row['period_end'] ?? '')
            );
            $attendance = sprintf(
                '%s / %s',
                $formatDays($row['attended_days'] ?? 0),
                $formatDays($row['scheduled_days'] ?? 0)
            );
          ?>
          <tr>
            <td data-label="Empleado">
              <strong><?= htmlspecialchars((string) $row['display_name'], ENT_QUOTES, 'UTF-8') ?></strong>
              <span class="muted-cell"><?= htmlspecialchars((string) $row['external_code'], ENT_QUOTES, 'UTF-8') ?></span>
            </td>
            <td data-label="Departamento"><?= htmlspecialchars((string) $row['department_name'], ENT_QUOTES, 'UTF-8') ?></td>
            <td data-label="Periodo"><?= htmlspecialchars($period, ENT_QUOTES, 'UTF-8') ?></td>
            <td class="number-cell" data-label="H. normal"><?= htmlspecialchars($formatMinutes($row['normal_work_minutes'] ?? 0), ENT_QUOTES, 'UTF-8') ?></td>
            <td class="number-cell" data-label="H. real"><?= htmlspecialchars($formatMinutes($row['actual_work_minutes'] ?? 0), ENT_QUOTES, 'UTF-8') ?></td>
            <td class="number-cell" data-label="Asistencia"><?= htmlspecialchars($attendance, ENT_QUOTES, 'UTF-8') ?></td>
            <td data-label="Retardos">
              <span class="stat-pill warn">
                <?= (int) ($row['late_count'] ?? 0) ?> / <?= htmlspecialchars($formatMinutes($row['late_minutes'] ?? 0), ENT_QUOTES, 'UTF-8') ?>
              </span>
            </td>
            <td data-label="Salidas temprano">
              <span class="stat-pill warn">
                <?= (int) ($row['early_leave_count'] ?? 0) ?> / <?= htmlspecialchars($formatMinutes($row['early_leave_minutes'] ?? 0), ENT_QUOTES, 'UTF-8') ?>
              </span>
            </td>
            <td class="number-cell" data-label="Extra laboral"><?= htmlspecialchars($formatMinutes($row['extra_workday_minutes'] ?? 0), ENT_QUOTES, 'UTF-8') ?></td>
            <td class="number-cell" data-label="Extra festivo"><?= htmlspecialchars($formatMinutes($row['extra_holiday_minutes'] ?? 0), ENT_QUOTES, 'UTF-8') ?></td>
            <td class="number-cell" data-label="Salida dias"><?= htmlspecialchars($formatDays($row['exit_days'] ?? 0), ENT_QUOTES, 'UTF-8') ?></td>
            <td class="number-cell" data-label="Falta dias"><?= htmlspecialchars($formatDays($row['absence_days'] ?? 0), ENT_QUOTES, 'UTF-8') ?></td>
            <td class="number-cell" data-label="Permiso dias"><?= htmlspecialchars($formatDays($row['permission_days'] ?? 0), ENT_QUOTES, 'UTF-8') ?></td>
            <td class="number-cell" data-label="Pago real"><?= htmlspecialchars($formatMoney($row['real_payment_amount'] ?? null), ENT_QUOTES, 'UTF-8') ?></td>
            <td data-label="Notas"><?= htmlspecialchars((string) ($row['notes'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</section>
