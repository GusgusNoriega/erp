<?php
/** @var array<string, string> $filters */
/** @var array<int, array{id: int, name: string}> $departments */
/** @var array<int, array{id: int, external_code: string, display_name: string}> $employees */
/** @var array<int, array{id: int, external_code: string, name: string, category: string}> $shiftTypes */
/** @var array<int, array{date: string, day: string, label: string, weekend: bool}> $dates */
/** @var array<int, array{employee_id: int, external_code: string, display_name: string, department_name: string, days: array<string, array{label: string, title: string, category: string, class: string}>}> $scheduleRows */
/** @var array{employees: int, dates: int, schedules: int, special: int} $totals */

$filters = $filters ?? [
    'date_from' => '',
    'date_to' => '',
    'department_id' => '',
    'employee_id' => '',
    'shift_type_id' => '',
];
$departments = $departments ?? [];
$employees = $employees ?? [];
$shiftTypes = $shiftTypes ?? [];
$dates = $dates ?? [];
$scheduleRows = $scheduleRows ?? [];
$totals = $totals ?? ['employees' => 0, 'dates' => 0, 'schedules' => 0, 'special' => 0];
?>

<section class="headline-card">
  <p class="eyebrow"><?= htmlspecialchars($viewTag ?? 'Asistencia', ENT_QUOTES, 'UTF-8') ?></p>
  <h1><?= htmlspecialchars($viewTitle ?? 'Reporte de Turnos', ENT_QUOTES, 'UTF-8') ?></h1>
  <p><?= htmlspecialchars($viewDescription ?? '', ENT_QUOTES, 'UTF-8') ?></p>
</section>

<section class="metrics-grid schedule-metrics" aria-label="Resumen de turnos">
  <article class="metric-card">
    <p class="metric-label">Empleados visibles</p>
    <p class="metric-value"><?= number_format((int) $totals['employees'], 0, ',', '.') ?></p>
    <p class="metric-trend up">Segun filtros activos</p>
  </article>

  <article class="metric-card">
    <p class="metric-label">Dias del periodo</p>
    <p class="metric-value"><?= number_format((int) $totals['dates'], 0, ',', '.') ?></p>
    <p class="metric-trend warn">Maximo 46 dias por vista</p>
  </article>

  <article class="metric-card">
    <p class="metric-label">Turnos registrados</p>
    <p class="metric-value"><?= number_format((int) $totals['schedules'], 0, ',', '.') ?></p>
    <p class="metric-trend up">Celdas con programacion</p>
  </article>

  <article class="metric-card">
    <p class="metric-label">Turnos especiales</p>
    <p class="metric-value"><?= number_format((int) $totals['special'], 0, ',', '.') ?></p>
    <p class="metric-trend down">Permisos, salidas o vacaciones</p>
  </article>
</section>

<section class="panel filter-panel">
  <div class="panel-heading">
    <div>
      <p class="panel-kicker">Filtros</p>
      <h2>Primera hoja: turnos programados</h2>
    </div>
    <a class="ghost-link" href="<?= htmlspecialchars(url('/asistencia/turnos'), ENT_QUOTES, 'UTF-8') ?>">Limpiar filtros</a>
  </div>

  <form class="filters-grid" method="get" action="<?= htmlspecialchars(url('/asistencia/turnos'), ENT_QUOTES, 'UTF-8') ?>">
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

    <label class="filter-field">
      <span>Tipo de turno</span>
      <select name="shift_type_id">
        <option value="">Todos</option>
        <?php foreach ($shiftTypes as $shiftType): ?>
          <?php $selected = (string) $shiftType['id'] === $filters['shift_type_id'] ? ' selected' : ''; ?>
          <option value="<?= (int) $shiftType['id'] ?>"<?= $selected ?>>
            <?= htmlspecialchars($shiftType['external_code'] . ' - ' . $shiftType['name'], ENT_QUOTES, 'UTF-8') ?>
          </option>
        <?php endforeach; ?>
      </select>
    </label>

    <div class="filter-actions">
      <button class="ghost-btn primary-action" type="submit">Aplicar</button>
    </div>
  </form>
</section>

<section class="panel table-panel schedule-panel">
  <div class="panel-heading">
    <div>
      <p class="panel-kicker">Matriz</p>
      <h2>Turnos por empleado y fecha</h2>
    </div>
    <span class="panel-chip">Codigos del Excel</span>
  </div>

  <div class="schedule-legend" aria-label="Leyenda de turnos">
    <span><i class="legend-dot work"></i> Normal</span>
    <span><i class="legend-dot permission"></i> Permiso</span>
    <span><i class="legend-dot exit"></i> Salida</span>
    <span><i class="legend-dot vacation"></i> Vacaciones</span>
    <span><i class="legend-dot empty"></i> Sin turno</span>
  </div>

  <div class="table-wrap schedule-table-wrap">
    <table class="schedule-table">
      <thead>
        <tr>
          <th class="sticky-col employee-col">Empleado</th>
          <th>Departamento</th>
          <?php foreach ($dates as $date): ?>
            <th class="<?= $date['weekend'] ? 'weekend-col' : '' ?>">
              <span><?= htmlspecialchars($date['day'], ENT_QUOTES, 'UTF-8') ?></span>
              <strong><?= htmlspecialchars($date['label'], ENT_QUOTES, 'UTF-8') ?></strong>
            </th>
          <?php endforeach; ?>
        </tr>
      </thead>
      <tbody>
        <?php if ($scheduleRows === []): ?>
          <tr>
            <td colspan="<?= max(2 + count($dates), 2) ?>" class="empty-state-cell">
              No hay turnos cargados para los filtros seleccionados. Cuando el importador procese la hoja `Reporte de Turnos`, la informacion aparecera aqui.
            </td>
          </tr>
        <?php endif; ?>

        <?php foreach ($scheduleRows as $row): ?>
          <tr>
            <td class="sticky-col employee-col">
              <strong><?= htmlspecialchars($row['display_name'], ENT_QUOTES, 'UTF-8') ?></strong>
              <span><?= htmlspecialchars($row['external_code'], ENT_QUOTES, 'UTF-8') ?></span>
            </td>
            <td><?= htmlspecialchars($row['department_name'], ENT_QUOTES, 'UTF-8') ?></td>
            <?php foreach ($dates as $date): ?>
              <?php $day = $row['days'][$date['date']] ?? null; ?>
              <td class="<?= $date['weekend'] ? 'weekend-col' : '' ?>">
                <?php if ($day === null): ?>
                  <span class="shift-badge empty" title="Sin turno">-</span>
                <?php else: ?>
                  <span class="shift-badge <?= htmlspecialchars($day['class'], ENT_QUOTES, 'UTF-8') ?>" title="<?= htmlspecialchars($day['title'], ENT_QUOTES, 'UTF-8') ?>">
                    <?= htmlspecialchars($day['label'], ENT_QUOTES, 'UTF-8') ?>
                  </span>
                <?php endif; ?>
              </td>
            <?php endforeach; ?>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</section>

