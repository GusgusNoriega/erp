<?php
/** @var array<string, string> $filters */
/** @var array<int, array{id: int, name: string}> $departments */
/** @var array<int, array{id: int, external_code: string, display_name: string}> $employees */
/** @var array<int, array<string, mixed>> $punchRows */
/** @var array{employees: int, days: int, punches: int, duplicates: int, incomplete_days: int} $totals */

$filters = $filters ?? [
    'date_from' => '',
    'date_to' => '',
    'department_id' => '',
    'employee_id' => '',
    'min_punches' => '',
    'sort_by' => 'date',
    'sort_dir' => 'desc',
];
$departments = $departments ?? [];
$employees = $employees ?? [];
$punchRows = $punchRows ?? [];
$totals = $totals ?? [
    'employees' => 0,
    'days' => 0,
    'punches' => 0,
    'duplicates' => 0,
    'incomplete_days' => 0,
];

$sortOptions = [
    'date' => 'Fecha',
    'employee' => 'Empleado',
    'department' => 'Departamento',
    'first_punch' => 'Primera marcacion',
    'last_punch' => 'Ultima marcacion',
    'punch_count' => 'Cantidad de marcaciones',
];

$formatTime = static function (string|null $time): string {
    if ($time === null || $time === '') {
        return '-';
    }

    return substr($time, 0, 5);
};
?>

<section class="headline-card">
  <p class="eyebrow"><?= htmlspecialchars($viewTag ?? 'Asistencia', ENT_QUOTES, 'UTF-8') ?></p>
  <h1><?= htmlspecialchars($viewTitle ?? 'Reporte de Asistencia', ENT_QUOTES, 'UTF-8') ?></h1>
  <p><?= htmlspecialchars($viewDescription ?? '', ENT_QUOTES, 'UTF-8') ?></p>
</section>

<section class="metrics-grid schedule-metrics" aria-label="Resumen de marcaciones">
  <article class="metric-card">
    <p class="metric-label">Empleados visibles</p>
    <p class="metric-value"><?= number_format((int) $totals['employees'], 0, ',', '.') ?></p>
    <p class="metric-trend up">Segun filtros activos</p>
  </article>

  <article class="metric-card">
    <p class="metric-label">Dias con eventos</p>
    <p class="metric-value"><?= number_format((int) $totals['days'], 0, ',', '.') ?></p>
    <p class="metric-trend up">Agrupados por empleado y fecha</p>
  </article>

  <article class="metric-card">
    <p class="metric-label">Marcaciones</p>
    <p class="metric-value"><?= number_format((int) $totals['punches'], 0, ',', '.') ?></p>
    <p class="metric-trend warn">Duplicadas: <?= number_format((int) $totals['duplicates'], 0, ',', '.') ?></p>
  </article>

  <article class="metric-card">
    <p class="metric-label">Dias incompletos</p>
    <p class="metric-value"><?= number_format((int) $totals['incomplete_days'], 0, ',', '.') ?></p>
    <p class="metric-trend down">Menos de 2 o cantidad impar</p>
  </article>
</section>

<section class="panel filter-panel">
  <div class="panel-heading">
    <div>
      <p class="panel-kicker">Filtros y orden</p>
      <h2>Tercera hoja: eventos de asistencia</h2>
    </div>
    <a class="ghost-link" href="<?= htmlspecialchars(url('/asistencia/marcaciones'), ENT_QUOTES, 'UTF-8') ?>">Limpiar filtros</a>
  </div>

  <form class="filters-grid punch-filters" method="get" action="<?= htmlspecialchars(url('/asistencia/marcaciones'), ENT_QUOTES, 'UTF-8') ?>">
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
      <span>Min. marcaciones</span>
      <select name="min_punches">
        <option value="">Todas</option>
        <?php foreach ([1, 2, 3, 4, 5, 6] as $minimum): ?>
          <?php $selected = (string) $minimum === $filters['min_punches'] ? ' selected' : ''; ?>
          <option value="<?= $minimum ?>"<?= $selected ?>><?= $minimum ?> o mas</option>
        <?php endforeach; ?>
      </select>
    </label>

    <label class="filter-field">
      <span>Ordenar por</span>
      <select name="sort_by">
        <?php foreach ($sortOptions as $value => $label): ?>
          <?php $selected = $value === $filters['sort_by'] ? ' selected' : ''; ?>
          <option value="<?= htmlspecialchars($value, ENT_QUOTES, 'UTF-8') ?>"<?= $selected ?>>
            <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
          </option>
        <?php endforeach; ?>
      </select>
    </label>

    <label class="filter-field">
      <span>Direccion</span>
      <select name="sort_dir">
        <option value="desc"<?= $filters['sort_dir'] === 'desc' ? ' selected' : '' ?>>Descendente</option>
        <option value="asc"<?= $filters['sort_dir'] === 'asc' ? ' selected' : '' ?>>Ascendente</option>
      </select>
    </label>

    <div class="filter-actions">
      <button class="ghost-btn primary-action" type="submit">Aplicar</button>
    </div>
  </form>
</section>

<section class="panel table-panel punches-panel">
  <div class="panel-heading">
    <div>
      <p class="panel-kicker">Detalle</p>
      <h2>Marcaciones agrupadas por dia</h2>
    </div>
    <span class="panel-chip">Datos de la hoja de asistencia</span>
  </div>

  <div class="table-wrap punches-table-wrap">
    <table class="punches-table">
      <thead>
        <tr>
          <th>Fecha</th>
          <th>Empleado</th>
          <th>Departamento</th>
          <th>Primera</th>
          <th>Ultima</th>
          <th>Cantidad</th>
          <th>Marcaciones</th>
          <th>Estado</th>
          <th>Archivo</th>
        </tr>
      </thead>
      <tbody>
        <?php if ($punchRows === []): ?>
          <tr>
            <td colspan="9" class="empty-state-cell">
              No hay marcaciones cargadas para los filtros seleccionados. Cuando el importador procese la hoja `Reporte de Asistencia`, los eventos apareceran aqui.
            </td>
          </tr>
        <?php endif; ?>

        <?php foreach ($punchRows as $row): ?>
          <?php
            $punches = array_filter(explode(',', (string) ($row['punches_csv'] ?? '')));
            $punchCount = (int) ($row['punch_count'] ?? 0);
            $duplicateCount = (int) ($row['duplicate_count'] ?? 0);
            $isIncomplete = $punchCount < 2 || $punchCount % 2 !== 0;
          ?>
          <tr>
            <td class="number-cell" data-label="Fecha"><?= htmlspecialchars((string) $row['work_date'], ENT_QUOTES, 'UTF-8') ?></td>
            <td data-label="Empleado">
              <strong><?= htmlspecialchars((string) $row['display_name'], ENT_QUOTES, 'UTF-8') ?></strong>
              <span class="muted-cell"><?= htmlspecialchars((string) $row['external_code'], ENT_QUOTES, 'UTF-8') ?></span>
            </td>
            <td data-label="Departamento"><?= htmlspecialchars((string) $row['department_name'], ENT_QUOTES, 'UTF-8') ?></td>
            <td class="number-cell" data-label="Primera"><?= htmlspecialchars($formatTime($row['first_punch'] ?? null), ENT_QUOTES, 'UTF-8') ?></td>
            <td class="number-cell" data-label="Ultima"><?= htmlspecialchars($formatTime($row['last_punch'] ?? null), ENT_QUOTES, 'UTF-8') ?></td>
            <td class="number-cell" data-label="Cantidad"><?= $punchCount ?></td>
            <td data-label="Marcaciones">
              <div class="punch-list">
                <?php foreach ($punches as $punch): ?>
                  <span class="punch-chip"><?= htmlspecialchars($punch, ENT_QUOTES, 'UTF-8') ?></span>
                <?php endforeach; ?>
              </div>
            </td>
            <td data-label="Estado">
              <?php if ($isIncomplete): ?>
                <span class="stat-pill alert">Revisar</span>
              <?php elseif ($duplicateCount > 0): ?>
                <span class="stat-pill warn">Duplicadas: <?= $duplicateCount ?></span>
              <?php else: ?>
                <span class="stat-pill ok">Completo</span>
              <?php endif; ?>
            </td>
            <td data-label="Archivo"><?= htmlspecialchars((string) ($row['source_filename'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</section>
