<?php
/** @var array<string, string> $filters */
/** @var array<int, array{id: int, name: string}> $departments */
/** @var array<int, array{id: int, external_code: string, display_name: string}> $employees */
/** @var array<string, string> $statuses */
/** @var array<int, array<string, mixed>> $recordRows */
/** @var array{records: int, employees: int, incomplete: int} $totals */
/** @var string|null $error */
/** @var string|null $success */

$filters = $filters ?? [
    'date_from' => '',
    'date_to' => '',
    'department_id' => '',
    'employee_id' => '',
    'status' => '',
];
$departments = $departments ?? [];
$employees = $employees ?? [];
$statuses = $statuses ?? [];
$recordRows = $recordRows ?? [];
$totals = $totals ?? ['records' => 0, 'employees' => 0, 'incomplete' => 0];
$error = $error ?? null;
$success = $success ?? null;

$formatTimeValue = static function (string|null $time): string {
    if ($time === null || $time === '') {
        return '';
    }

    return substr($time, 0, 5);
};

$statusClass = static function (string $status): string {
    return match ($status) {
        'present', 'permission', 'vacation', 'exit', 'rest', 'holiday' => 'ok',
        'incomplete', 'unknown' => 'warn',
        default => 'alert',
    };
};
?>

<section class="headline-card">
  <p class="eyebrow"><?= htmlspecialchars($viewTag ?? 'Asistencia', ENT_QUOTES, 'UTF-8') ?></p>
  <h1><?= htmlspecialchars($viewTitle ?? 'Edicion manual de registros', ENT_QUOTES, 'UTF-8') ?></h1>
  <p><?= htmlspecialchars($viewDescription ?? '', ENT_QUOTES, 'UTF-8') ?></p>
</section>

<?php if ($error !== null): ?>
  <section class="notice-card error">
    <strong>No se pudo guardar el ajuste.</strong>
    <p><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p>
  </section>
<?php endif; ?>

<?php if ($success !== null): ?>
  <section class="notice-card success">
    <strong>Operacion completada.</strong>
    <p><?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?></p>
  </section>
<?php endif; ?>

<section class="metrics-grid schedule-metrics" aria-label="Resumen de registros manuales">
  <article class="metric-card">
    <p class="metric-label">Registros visibles</p>
    <p class="metric-value"><?= number_format((int) $totals['records'], 0, ',', '.') ?></p>
    <p class="metric-trend up">Maximo 250 por vista</p>
  </article>

  <article class="metric-card">
    <p class="metric-label">Empleados visibles</p>
    <p class="metric-value"><?= number_format((int) $totals['employees'], 0, ',', '.') ?></p>
    <p class="metric-trend up">Segun filtros activos</p>
  </article>

  <article class="metric-card">
    <p class="metric-label">Incompletos</p>
    <p class="metric-value"><?= number_format((int) $totals['incomplete'], 0, ',', '.') ?></p>
    <p class="metric-trend warn">Pendientes de revision</p>
  </article>
</section>

<section class="panel filter-panel">
  <div class="panel-heading">
    <div>
      <p class="panel-kicker">Filtros</p>
      <h2>Buscar registros diarios</h2>
    </div>
    <a class="ghost-link" href="<?= htmlspecialchars(url('/asistencia/manual'), ENT_QUOTES, 'UTF-8') ?>">Limpiar filtros</a>
  </div>

  <form class="filters-grid manual-filters" method="get" action="<?= htmlspecialchars(url('/asistencia/manual'), ENT_QUOTES, 'UTF-8') ?>">
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
      <span>Estado</span>
      <select name="status">
        <option value="">Todos</option>
        <?php foreach ($statuses as $value => $label): ?>
          <?php $selected = $value === $filters['status'] ? ' selected' : ''; ?>
          <option value="<?= htmlspecialchars($value, ENT_QUOTES, 'UTF-8') ?>"<?= $selected ?>>
            <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
          </option>
        <?php endforeach; ?>
      </select>
    </label>

    <div class="filter-actions">
      <button class="ghost-btn primary-action" type="submit">Aplicar</button>
    </div>
  </form>
</section>

<section class="panel table-panel manual-panel">
  <div class="panel-heading">
    <div>
      <p class="panel-kicker">Ajustes</p>
      <h2>Registros diarios editables</h2>
    </div>
    <span class="panel-chip">Guardar por fila</span>
  </div>

  <div class="table-wrap manual-table-wrap">
    <table class="manual-records-table">
      <thead>
        <tr>
          <th>Fecha</th>
          <th>Empleado</th>
          <th>Departamento</th>
          <th>Estado</th>
          <th>Primera entrada</th>
          <th>Primera salida</th>
          <th>Segunda entrada</th>
          <th>Segunda salida</th>
          <th>Notas</th>
          <th>Archivo</th>
          <th>Accion</th>
        </tr>
      </thead>
      <tbody>
        <?php if ($recordRows === []): ?>
          <tr>
            <td colspan="11" class="empty-state-cell">
              No hay registros diarios para los filtros seleccionados.
            </td>
          </tr>
        <?php endif; ?>

        <?php foreach ($recordRows as $row): ?>
          <?php
            $rowStatus = (string) ($row['status'] ?? 'unknown');
            $rowDepartment = (string) ($row['department_id'] ?? '');
            $formId = 'manual-record-' . (int) $row['id'];
          ?>
          <tr>
            <td class="number-cell" data-label="Fecha">
              <form id="<?= htmlspecialchars($formId, ENT_QUOTES, 'UTF-8') ?>" method="post" action="<?= htmlspecialchars(url('/asistencia/manual'), ENT_QUOTES, 'UTF-8') ?>"></form>
              <input form="<?= htmlspecialchars($formId, ENT_QUOTES, 'UTF-8') ?>" type="hidden" name="record_id" value="<?= (int) $row['id'] ?>" />
              <input form="<?= htmlspecialchars($formId, ENT_QUOTES, 'UTF-8') ?>" type="hidden" name="filter_date_from" value="<?= htmlspecialchars($filters['date_from'], ENT_QUOTES, 'UTF-8') ?>" />
              <input form="<?= htmlspecialchars($formId, ENT_QUOTES, 'UTF-8') ?>" type="hidden" name="filter_date_to" value="<?= htmlspecialchars($filters['date_to'], ENT_QUOTES, 'UTF-8') ?>" />
              <input form="<?= htmlspecialchars($formId, ENT_QUOTES, 'UTF-8') ?>" type="hidden" name="filter_department_id" value="<?= htmlspecialchars($filters['department_id'], ENT_QUOTES, 'UTF-8') ?>" />
              <input form="<?= htmlspecialchars($formId, ENT_QUOTES, 'UTF-8') ?>" type="hidden" name="filter_employee_id" value="<?= htmlspecialchars($filters['employee_id'], ENT_QUOTES, 'UTF-8') ?>" />
              <input form="<?= htmlspecialchars($formId, ENT_QUOTES, 'UTF-8') ?>" type="hidden" name="filter_status" value="<?= htmlspecialchars($filters['status'], ENT_QUOTES, 'UTF-8') ?>" />
              <?= htmlspecialchars((string) $row['work_date'], ENT_QUOTES, 'UTF-8') ?>
            </td>
              <td data-label="Empleado">
                <strong><?= htmlspecialchars((string) $row['display_name'], ENT_QUOTES, 'UTF-8') ?></strong>
                <span class="muted-cell"><?= htmlspecialchars((string) $row['external_code'], ENT_QUOTES, 'UTF-8') ?></span>
              </td>
              <td data-label="Departamento">
                <select class="compact-input" form="<?= htmlspecialchars($formId, ENT_QUOTES, 'UTF-8') ?>" name="department_id">
                  <option value="">Sin departamento</option>
                  <?php foreach ($departments as $department): ?>
                    <?php $selected = (string) $department['id'] === $rowDepartment ? ' selected' : ''; ?>
                    <option value="<?= (int) $department['id'] ?>"<?= $selected ?>>
                      <?= htmlspecialchars($department['name'], ENT_QUOTES, 'UTF-8') ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </td>
              <td data-label="Estado">
                <select class="compact-input" form="<?= htmlspecialchars($formId, ENT_QUOTES, 'UTF-8') ?>" name="status">
                  <?php foreach ($statuses as $value => $label): ?>
                    <?php $selected = $value === $rowStatus ? ' selected' : ''; ?>
                    <option value="<?= htmlspecialchars($value, ENT_QUOTES, 'UTF-8') ?>"<?= $selected ?>>
                      <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
                    </option>
                  <?php endforeach; ?>
                </select>
                <span class="stat-pill <?= htmlspecialchars($statusClass($rowStatus), ENT_QUOTES, 'UTF-8') ?>">
                  <?= htmlspecialchars($statuses[$rowStatus] ?? $rowStatus, ENT_QUOTES, 'UTF-8') ?>
                </span>
              </td>
              <td data-label="Primera entrada">
                <input class="compact-input time-input" form="<?= htmlspecialchars($formId, ENT_QUOTES, 'UTF-8') ?>" type="time" name="first_in" step="60" value="<?= htmlspecialchars($formatTimeValue($row['first_in'] ?? null), ENT_QUOTES, 'UTF-8') ?>" />
              </td>
              <td data-label="Primera salida">
                <input class="compact-input time-input" form="<?= htmlspecialchars($formId, ENT_QUOTES, 'UTF-8') ?>" type="time" name="first_out" step="60" value="<?= htmlspecialchars($formatTimeValue($row['first_out'] ?? null), ENT_QUOTES, 'UTF-8') ?>" />
              </td>
              <td data-label="Segunda entrada">
                <input class="compact-input time-input" form="<?= htmlspecialchars($formId, ENT_QUOTES, 'UTF-8') ?>" type="time" name="second_in" step="60" value="<?= htmlspecialchars($formatTimeValue($row['second_in'] ?? null), ENT_QUOTES, 'UTF-8') ?>" />
              </td>
              <td data-label="Segunda salida">
                <input class="compact-input time-input" form="<?= htmlspecialchars($formId, ENT_QUOTES, 'UTF-8') ?>" type="time" name="second_out" step="60" value="<?= htmlspecialchars($formatTimeValue($row['second_out'] ?? null), ENT_QUOTES, 'UTF-8') ?>" />
              </td>
              <td data-label="Notas">
                <input class="compact-input notes-input" form="<?= htmlspecialchars($formId, ENT_QUOTES, 'UTF-8') ?>" type="text" name="notes" value="<?= htmlspecialchars((string) ($row['notes'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" />
              </td>
              <td data-label="Archivo"><?= htmlspecialchars((string) ($row['source_filename'] ?? 'Manual'), ENT_QUOTES, 'UTF-8') ?></td>
              <td data-label="Accion">
                <button class="ghost-btn primary-action" form="<?= htmlspecialchars($formId, ENT_QUOTES, 'UTF-8') ?>" type="submit">Guardar</button>
              </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</section>
