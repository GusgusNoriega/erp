<?php
/** @var string $content */
$pageTitle = $pageTitle ?? 'Iniciar sesion';
?>
<!DOCTYPE html>
<html lang="es" data-theme="light">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@500;600;700;800&family=Space+Grotesk:wght@500;600;700&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="<?= htmlspecialchars(url('/styles.css'), ENT_QUOTES, 'UTF-8') ?>" />
</head>
<body>
  <div class="page-bg" aria-hidden="true"></div>
  <main class="auth-shell">
    <?= $content ?>
  </main>
  <script src="<?= htmlspecialchars(url('/app.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
</body>
</html>

