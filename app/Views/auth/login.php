<?php
/** @var string|null $error */
/** @var string $email */
$error = $error ?? null;
$email = $email ?? '';
?>

<section class="auth-card">
  <div class="auth-brand">
    <div class="brand-badge">E</div>
    <div>
      <p class="brand-title">ERP Pulse</p>
      <p class="brand-subtitle">Control de asistencia</p>
    </div>
  </div>

  <div class="auth-heading">
    <p class="eyebrow">Acceso</p>
    <h1>Iniciar sesion</h1>
    <p>Ingresa con tu usuario para administrar cargas, reportes y usuarios del sistema.</p>
  </div>

  <?php if ($error !== null): ?>
    <div class="notice-card error auth-notice">
      <strong>Acceso rechazado</strong>
      <p><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p>
    </div>
  <?php endif; ?>

  <form class="auth-form" method="post" action="<?= htmlspecialchars(url('/login'), ENT_QUOTES, 'UTF-8') ?>">
    <label class="filter-field">
      <span>Correo</span>
      <input type="email" name="email" value="<?= htmlspecialchars($email, ENT_QUOTES, 'UTF-8') ?>" required autofocus />
    </label>

    <label class="filter-field">
      <span>Contrasena</span>
      <input type="password" name="password" required />
    </label>

    <button class="ghost-btn primary-action auth-action" type="submit">Entrar</button>
  </form>

  <p class="auth-help">Usuario inicial: elvis@mail.com</p>
</section>

