<?php
/** @var string $content */
$currentPath = $currentPath ?? '/';
$pageTitle = $pageTitle ?? 'ERP';
$currentUser = \App\Core\Auth::user();

$menuItems = [
    ['label' => 'Importar Excel', 'path' => '/asistencia/importar'],
    ['label' => 'Reporte de Turnos', 'path' => '/asistencia/turnos'],
    ['label' => 'Reporte Estadistico', 'path' => '/asistencia/estadisticas'],
    ['label' => 'Reporte de Asistencia', 'path' => '/asistencia/marcaciones'],
];

if (($currentUser['role'] ?? '') === 'admin') {
    $menuItems[] = ['label' => 'Usuarios', 'path' => '/usuarios'];
}
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

  <div class="app-shell">
    <aside class="sidebar" id="sidebar" aria-label="Menu principal">
      <div class="sidebar-header">
        <div class="brand-badge">E</div>
        <div>
          <p class="brand-title">ERP Pulse</p>
          <p class="brand-subtitle">Control de operaciones</p>
        </div>
        <button class="icon-btn close-sidebar" id="closeSidebar" aria-label="Cerrar menu">x</button>
      </div>

      <p class="sidebar-section-title">Modulos</p>

      <nav class="module-nav" aria-label="Modulos ERP">
        <section class="module-item open">
          <button class="module-trigger" type="button" data-module-trigger aria-expanded="true">
            <span class="module-main">
                <span class="module-icon">AS</span>
                <span class="module-title-wrap">
                <span class="module-title">Asistencia</span>
                <span class="module-count"><?= count($menuItems) ?> vistas</span>
              </span>
            </span>
            <span class="caret">></span>
          </button>

          <div class="module-views">
            <?php foreach ($menuItems as $item): ?>
              <?php $active = $currentPath === $item['path'] ? ' active' : ''; ?>
              <a class="module-view-link<?= $active ?>" href="<?= htmlspecialchars(url($item['path']), ENT_QUOTES, 'UTF-8') ?>">
                <?= htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8') ?>
              </a>
            <?php endforeach; ?>
          </div>
        </section>
      </nav>

      <div class="sidebar-footer">
        <p class="sidebar-foot-label">Estado del sistema</p>
        <p class="sidebar-foot-value">Todos los servicios activos</p>
      </div>
    </aside>

    <div class="content-shell">
      <header class="topbar">
        <div class="topbar-left">
          <button class="icon-btn menu-btn" id="openSidebar" aria-label="Abrir menu">
            <span class="hamburger"></span>
          </button>

          <label class="search-box" for="globalSearch">
            <span class="search-icon" aria-hidden="true">/</span>
            <input
              id="globalSearch"
              type="search"
              placeholder="Buscar cliente, orden o factura"
              autocomplete="off"
            />
          </label>
        </div>

        <div class="topbar-right">
          <button class="theme-toggle" id="themeToggle" aria-label="Cambiar tema">
            <span class="theme-toggle-icon" aria-hidden="true"></span>
            <span id="themeLabel">Modo oscuro</span>
          </button>

          <?php if ($currentUser !== null): ?>
            <div class="profile-btn" aria-label="Perfil de usuario">
              <span class="profile-avatar"><?= htmlspecialchars(strtoupper(substr($currentUser['name'], 0, 2)), ENT_QUOTES, 'UTF-8') ?></span>
              <span class="profile-name"><?= htmlspecialchars($currentUser['name'], ENT_QUOTES, 'UTF-8') ?></span>
            </div>

            <form method="post" action="<?= htmlspecialchars(url('/logout'), ENT_QUOTES, 'UTF-8') ?>">
              <button class="ghost-btn logout-btn" type="submit">Salir</button>
            </form>
          <?php endif; ?>
        </div>
      </header>

      <main class="main-content" id="mainContent">
        <?= $content ?>
      </main>
    </div>
  </div>

  <div class="backdrop" id="backdrop" aria-hidden="true"></div>

  <script src="<?= htmlspecialchars(url('/app.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
</body>
</html>
