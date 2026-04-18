<?php
/** @var array<int, array<string, mixed>> $users */
/** @var string|null $error */
/** @var string|null $success */
$users = $users ?? [];
$error = $error ?? null;
$success = $success ?? null;
?>

<section class="headline-card">
  <p class="eyebrow"><?= htmlspecialchars($viewTag ?? 'Administracion', ENT_QUOTES, 'UTF-8') ?></p>
  <h1><?= htmlspecialchars($viewTitle ?? 'Usuarios del sistema', ENT_QUOTES, 'UTF-8') ?></h1>
  <p><?= htmlspecialchars($viewDescription ?? '', ENT_QUOTES, 'UTF-8') ?></p>
</section>

<?php if ($error !== null): ?>
  <section class="notice-card error">
    <strong>No se pudo completar la operacion.</strong>
    <p><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p>
  </section>
<?php endif; ?>

<?php if ($success !== null): ?>
  <section class="notice-card success">
    <strong>Operacion completada.</strong>
    <p><?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?></p>
  </section>
<?php endif; ?>

<section class="split-grid users-grid">
  <article class="panel">
    <div class="panel-heading">
      <div>
        <p class="panel-kicker">Nuevo usuario</p>
        <h2>Crear acceso</h2>
      </div>
      <span class="panel-chip">Admin</span>
    </div>

    <form class="user-form" method="post" action="<?= htmlspecialchars(url('/usuarios'), ENT_QUOTES, 'UTF-8') ?>">
      <label class="filter-field">
        <span>Nombre</span>
        <input type="text" name="name" required />
      </label>

      <label class="filter-field">
        <span>Correo</span>
        <input type="email" name="email" required />
      </label>

      <label class="filter-field">
        <span>Contrasena</span>
        <input type="password" name="password" minlength="6" required />
      </label>

      <label class="filter-field">
        <span>Rol</span>
        <select name="role">
          <option value="operator">Operador</option>
          <option value="admin">Administrador</option>
        </select>
      </label>

      <button class="ghost-btn primary-action" type="submit">Crear usuario</button>
    </form>
  </article>

  <article class="panel">
    <div class="panel-heading">
      <div>
        <p class="panel-kicker">Permisos</p>
        <h2>Roles disponibles</h2>
      </div>
    </div>

    <div class="import-rules">
      <p><strong>Administrador</strong></p>
      <p>Puede crear usuarios, cambiar contrasenas, activar o desactivar accesos y administrar importaciones.</p>
      <p><strong>Operador</strong></p>
      <p>Puede entrar al sistema y trabajar con las vistas de asistencia. La administracion de usuarios queda reservada al administrador.</p>
    </div>
  </article>
</section>

<section class="panel table-panel">
  <div class="panel-heading">
    <div>
      <p class="panel-kicker">Usuarios</p>
      <h2>Cuentas registradas</h2>
    </div>
    <span class="panel-chip"><?= count($users) ?> usuarios</span>
  </div>

  <div class="table-wrap">
    <table class="users-table">
      <thead>
        <tr>
          <th>Usuario</th>
          <th>Correo</th>
          <th>Rol</th>
          <th>Estado</th>
          <th>Ultimo acceso</th>
          <th>Cambiar contrasena</th>
          <th>Acceso</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($users as $user): ?>
          <?php $isCurrent = (int) $user['id'] === \App\Core\Auth::id(); ?>
          <tr>
            <td>
              <strong><?= htmlspecialchars((string) $user['name'], ENT_QUOTES, 'UTF-8') ?></strong>
              <?php if ($isCurrent): ?>
                <span class="muted-cell">Sesion actual</span>
              <?php endif; ?>
            </td>
            <td><?= htmlspecialchars((string) $user['email'], ENT_QUOTES, 'UTF-8') ?></td>
            <td>
              <span class="stat-pill <?= ($user['role'] ?? '') === 'admin' ? 'ok' : 'warn' ?>">
                <?= htmlspecialchars((string) $user['role'], ENT_QUOTES, 'UTF-8') ?>
              </span>
            </td>
            <td>
              <span class="stat-pill <?= (int) $user['active'] === 1 ? 'ok' : 'alert' ?>">
                <?= (int) $user['active'] === 1 ? 'Activo' : 'Inactivo' ?>
              </span>
            </td>
            <td><?= htmlspecialchars((string) ($user['last_login_at'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></td>
            <td>
              <form class="inline-user-form" method="post" action="<?= htmlspecialchars(url('/usuarios/password'), ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="user_id" value="<?= (int) $user['id'] ?>" />
                <input type="password" name="password" minlength="6" placeholder="Nueva contrasena" required />
                <button class="ghost-btn" type="submit">Guardar</button>
              </form>
            </td>
            <td>
              <?php if (!$isCurrent): ?>
                <form method="post" action="<?= htmlspecialchars(url('/usuarios/status'), ENT_QUOTES, 'UTF-8') ?>">
                  <input type="hidden" name="user_id" value="<?= (int) $user['id'] ?>" />
                  <input type="hidden" name="active" value="<?= (int) $user['active'] === 1 ? '0' : '1' ?>" />
                  <button class="ghost-btn" type="submit">
                    <?= (int) $user['active'] === 1 ? 'Desactivar' : 'Activar' ?>
                  </button>
                </form>
              <?php else: ?>
                <span class="muted-cell">Bloqueado</span>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</section>

