<?php
/** @var array<string, mixed>|null $result */
/** @var string|null $error */
/** @var array<int, array<string, mixed>> $recentBatches */

$result = $result ?? null;
$error = $error ?? null;
$recentBatches = $recentBatches ?? [];

$formatSummary = static function (string|null $json, string $key): string {
    if ($json === null || $json === '') {
        return '0';
    }

    $data = json_decode($json, true);

    if (!is_array($data)) {
        return '0';
    }

    return number_format((int) ($data[$key] ?? 0), 0, ',', '.');
};
?>

<section class="headline-card">
  <p class="eyebrow"><?= htmlspecialchars($viewTag ?? 'Importacion', ENT_QUOTES, 'UTF-8') ?></p>
  <h1><?= htmlspecialchars($viewTitle ?? 'Subir y procesar Excel', ENT_QUOTES, 'UTF-8') ?></h1>
  <p><?= htmlspecialchars($viewDescription ?? '', ENT_QUOTES, 'UTF-8') ?></p>
</section>

<?php if ($error !== null): ?>
  <section class="notice-card error">
    <strong>No se pudo procesar el archivo.</strong>
    <p><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p>
  </section>
<?php endif; ?>

<?php if (is_array($result)): ?>
  <section class="notice-card success">
    <strong>Archivo procesado correctamente.</strong>
    <p>
      Periodo:
      <?= htmlspecialchars((string) ($result['period']['start'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
      a
      <?= htmlspecialchars((string) ($result['period']['end'] ?? ''), ENT_QUOTES, 'UTF-8') ?>.
    </p>
  </section>

  <section class="metrics-grid schedule-metrics" aria-label="Resultado de importacion">
    <article class="metric-card">
      <p class="metric-label">Turnos</p>
      <p class="metric-value"><?= number_format((int) ($result['summary']['schedules'] ?? 0), 0, ',', '.') ?></p>
      <p class="metric-trend up">Hoja de turnos</p>
    </article>

    <article class="metric-card">
      <p class="metric-label">Resumenes</p>
      <p class="metric-value"><?= number_format((int) ($result['summary']['period_summaries'] ?? 0), 0, ',', '.') ?></p>
      <p class="metric-trend up">Hoja estadistica</p>
    </article>

    <article class="metric-card">
      <p class="metric-label">Marcaciones</p>
      <p class="metric-value"><?= number_format((int) ($result['summary']['punches'] ?? 0), 0, ',', '.') ?></p>
      <p class="metric-trend warn">Se actualizan si fecha y hora coinciden</p>
    </article>

    <article class="metric-card">
      <p class="metric-label">Excepciones</p>
      <p class="metric-value"><?= number_format((int) ($result['summary']['exceptions'] ?? 0), 0, ',', '.') ?></p>
      <p class="metric-trend down">Retardos, faltas y salidas</p>
    </article>
  </section>
<?php endif; ?>

<section class="split-grid import-grid">
  <article class="panel import-panel">
    <div class="panel-heading">
      <div>
        <p class="panel-kicker">Carga</p>
        <h2>Seleccionar archivo</h2>
      </div>
      <span class="panel-chip">Formato .xls</span>
    </div>

    <form class="upload-form" method="post" action="<?= htmlspecialchars(url('/asistencia/importar'), ENT_QUOTES, 'UTF-8') ?>" enctype="multipart/form-data">
      <label class="upload-drop">
        <span>Archivo Excel generado por el sistema</span>
        <input type="file" name="attendance_file" accept=".xls" required />
      </label>

      <div class="import-rules">
        <p><strong>Reglas aplicadas</strong></p>
        <p>Se detecta el periodo interno del Excel y se procesan las hojas conocidas.</p>
        <p>Empleados y departamentos se actualizan por codigo y nombre normalizado.</p>
        <p>Si una marcacion coincide en empleado, fecha y hora exacta, se actualiza en lugar de duplicarse.</p>
        <p>Si un turno coincide en empleado y fecha, se reemplaza con el ultimo dato importado.</p>
      </div>

      <button class="ghost-btn primary-action upload-action" type="submit">Procesar Excel</button>
    </form>
  </article>

  <article class="panel import-panel">
    <div class="panel-heading">
      <div>
        <p class="panel-kicker">Destino</p>
        <h2>Datos que alimenta</h2>
      </div>
    </div>

    <div class="import-targets">
      <span>Empleados</span>
      <span>Departamentos</span>
      <span>Turnos por dia</span>
      <span>Resumen estadistico</span>
      <span>Marcaciones</span>
      <span>Excepciones</span>
      <span>Consolidado diario</span>
    </div>
  </article>
</section>

<section class="panel table-panel import-history-panel">
  <div class="panel-heading">
    <div>
      <p class="panel-kicker">Auditoria</p>
      <h2>Ultimas cargas procesadas</h2>
    </div>
    <span class="panel-chip">Historial</span>
  </div>

  <div class="table-wrap">
    <table class="import-history-table">
      <thead>
        <tr>
          <th>ID</th>
          <th>Archivo</th>
          <th>Periodo</th>
          <th>Estado</th>
          <th>Turnos</th>
          <th>Resumenes</th>
          <th>Marcaciones</th>
          <th>Procesado</th>
        </tr>
      </thead>
      <tbody>
        <?php if ($recentBatches === []): ?>
          <tr>
            <td colspan="8" class="empty-state-cell">Todavia no hay cargas registradas.</td>
          </tr>
        <?php endif; ?>

        <?php foreach ($recentBatches as $batch): ?>
          <tr>
            <td class="number-cell" data-label="ID"><?= (int) $batch['id'] ?></td>
            <td data-label="Archivo"><?= htmlspecialchars((string) $batch['source_filename'], ENT_QUOTES, 'UTF-8') ?></td>
            <td data-label="Periodo">
              <?= htmlspecialchars((string) $batch['period_start'], ENT_QUOTES, 'UTF-8') ?>
              a
              <?= htmlspecialchars((string) $batch['period_end'], ENT_QUOTES, 'UTF-8') ?>
            </td>
            <td data-label="Estado">
              <span class="stat-pill <?= ($batch['status'] ?? '') === 'processed' ? 'ok' : 'warn' ?>">
                <?= htmlspecialchars((string) $batch['status'], ENT_QUOTES, 'UTF-8') ?>
              </span>
            </td>
            <td class="number-cell" data-label="Turnos"><?= $formatSummary($batch['summary_json'] ?? null, 'schedules') ?></td>
            <td class="number-cell" data-label="Resumenes"><?= $formatSummary($batch['summary_json'] ?? null, 'period_summaries') ?></td>
            <td class="number-cell" data-label="Marcaciones"><?= $formatSummary($batch['summary_json'] ?? null, 'punches') ?></td>
            <td data-label="Procesado"><?= htmlspecialchars((string) ($batch['processed_at'] ?? $batch['created_at']), ENT_QUOTES, 'UTF-8') ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</section>
