<?php
// /var/www/html/calibraciones/partials/header.php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/../config.php';

$current = basename($_SERVER['PHP_SELF']);
function active(string $file): string {
  global $current;
  return $current === $file ? 'active' : '';
}
?>
<!DOCTYPE html>
<html lang="es" data-bs-theme="dark">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Calibraciones</title>

  <!-- Bootstrap 5 + Icons -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">

  <!-- App styles (mantiene proporciones de tus tablas) -->
  <link href="assets/app.css?v=1" rel="stylesheet">
</head>
<body>
<nav class="navbar navbar-expand-lg app-navbar border-bottom">
  <div class="container-fluid">
    <a class="navbar-brand d-flex align-items-center gap-2" href="index.php">
      <img src="imagenes/calibracion.png" alt="Logo" class="brand-logo">
      <span class="fw-semibold">Test Instruments</span>
    </a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#topnav">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div id="topnav" class="collapse navbar-collapse">
      <ul class="navbar-nav me-auto mb-2 mb-lg-0">
        <?php if (($_SESSION['role'] ?? '') === 'admin'): ?>

          <!-- Dropdown Instrumentos de medición -->
          <?php
            $instPages = ['admin.php','out_of_use.php','report.php'];
            $instActive = in_array($current, $instPages, true) ? 'active' : '';
          ?>
          <li class="nav-item dropdown">
            <a class="nav-link dropdown-toggle <?= $instActive ?>"
               href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
              <i class="fa fa-microscope me-1"></i>Instrumentos de medición
            </a>
            <ul class="dropdown-menu dropdown-menu-dark">
              <li>
                <a class="dropdown-item <?= active('admin.php') ?>" href="admin.php">
                  <i class="fa fa-screwdriver-wrench me-2"></i>Administrar
                </a>
              </li>
              <li>
                <a class="dropdown-item <?= active('out_of_use.php') ?>" href="out_of_use.php">
                  <i class="fa fa-box-archive me-2"></i>Fuera de uso
                </a>
              </li>
              <li>
                <a class="dropdown-item <?= active('report.php') ?>" href="report.php">
                  <i class="fa fa-chart-column me-2"></i>Reportes
                </a>
              </li>
            </ul>
          </li>

          <!-- Dropdown Golden -->
          <?php
            $goldPages = ['golden_admin.php','golden_add.php'];
            $goldActive = in_array($current, $goldPages, true) ? 'active' : '';
          ?>
          <li class="nav-item dropdown">
            <a class="nav-link dropdown-toggle <?= $goldActive ?>"
               href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
              <i class="fa fa-crown me-1"></i>Golden
            </a>
            <ul class="dropdown-menu dropdown-menu-dark">
              <li>
                <a class="dropdown-item <?= active('golden_admin.php') ?>" href="golden_admin.php">
                  <i class="fa fa-clipboard-list me-2"></i>Inventario
                </a>
              </li>
              <li>
                <a class="dropdown-item <?= active('golden_audit.php') ?>" href="golden_audit.php">
                  <i class="fa fa-clipboard-check me-2"></i>Auditoría
                </a>
              </li>
              <li>
                <a class="dropdown-item <?= active('golden_add.php') ?>" href="golden_add.php">
                  <i class="fa fa-plus me-2"></i>Nuevo
                </a>
              </li>
            </ul>
          </li>

          <li class="nav-item">
            <a class="nav-link <?= active('users_admin.php') ?>" href="users_admin.php">
              <i class="fa fa-users-gear me-1"></i>Usuarios
            </a>
          </li>

        <?php else: ?>
          <li class="nav-item">
            <a class="nav-link <?= active('consulta_dashboard.php') ?>" href="consulta_dashboard.php">
              <i class="fa fa-magnifying-glass me-1"></i>Consulta
            </a>
          </li>
          <li class="nav-item">
            <a class="nav-link <?= active('report_view.php') ?>" href="report_view.php">
              <i class="fa fa-chart-column me-1"></i>Reportes
            </a>
          </li>
        <?php endif; ?>
      </ul>
      <div class="d-flex align-items-center gap-3">
        <span class="text-secondary small">
          <i class="fa fa-user-circle me-1"></i>
          <?= htmlspecialchars($_SESSION['username'] ?? 'usuario', ENT_QUOTES, 'UTF-8'); ?>
          — <?= htmlspecialchars($_SESSION['role'] ?? 'consulta', ENT_QUOTES, 'UTF-8'); ?>
        </span>
        <a href="logout.php" class="btn btn-outline-light btn-sm">
          <i class="fa fa-right-from-bracket me-1"></i>Salir
        </a>
      </div>
    </div>
  </div>
</nav>

<main class="container-fluid py-3">
