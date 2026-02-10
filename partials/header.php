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
  
  <style>
    /* Modern Header Styles */
    .app-navbar {
      background: rgba(27, 29, 35, 0.8);
      backdrop-filter: blur(20px);
      border-bottom: 1px solid rgba(255, 255, 255, 0.08) !important;
      box-shadow: 0 4px 30px rgba(0, 0, 0, 0.2);
      position: sticky;
      top: 0;
      z-index: 1000;
    }
    
    .navbar-brand {
      font-size: 1.25rem;
      font-weight: 600;
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
      background-clip: text;
      transition: all 0.3s ease;
    }
    
    .navbar-brand:hover {
      transform: scale(1.02);
    }
    
    .brand-logo {
      width: 32px;
      height: 32px;
      filter: drop-shadow(0 2px 8px rgba(102, 126, 234, 0.3));
    }
    
    .nav-link {
      position: relative;
      font-weight: 500;
      font-size: 0.9rem;
      padding: 0.5rem 1rem !important;
      transition: all 0.3s ease;
      border-radius: 8px;
    }
    
    .nav-link::before {
      content: '';
      position: absolute;
      bottom: 0;
      left: 50%;
      width: 0;
      height: 2px;
      background: linear-gradient(90deg, #667eea, #764ba2);
      transform: translateX(-50%);
      transition: width 0.3s ease;
    }
    
    .nav-link:hover {
      background: rgba(255, 255, 255, 0.05);
      color: #fff !important;
    }
    
    .nav-link:hover::before {
      width: 80%;
    }
    
    .nav-link.active {
      background: linear-gradient(135deg, rgba(102, 126, 234, 0.15) 0%, rgba(118, 75, 162, 0.15) 100%);
      color: #a78bfa !important;
      box-shadow: 0 0 20px rgba(102, 126, 234, 0.2);
    }
    
    .dropdown-menu {
      background: rgba(27, 29, 35, 0.95);
      backdrop-filter: blur(20px);
      border: 1px solid rgba(255, 255, 255, 0.1);
      border-radius: 12px;
      box-shadow: 0 8px 32px rgba(0, 0, 0, 0.4);
      padding: 0.5rem;
      margin-top: 0.5rem !important;
    }
    
    .dropdown-item {
      border-radius: 8px;
      padding: 0.6rem 1rem;
      transition: all 0.2s ease;
      font-size: 0.9rem;
    }
    
    .dropdown-item:hover {
      background: rgba(102, 126, 234, 0.15);
      color: #fff;
      transform: translateX(4px);
    }
    
    .dropdown-item.active {
      background: linear-gradient(135deg, rgba(102, 126, 234, 0.2) 0%, rgba(118, 75, 162, 0.2) 100%);
      color: #a78bfa;
    }
    
    .dropdown-header {
      color: #8b92a7;
      font-size: 0.75rem;
      font-weight: 700;
      letter-spacing: 0.05em;
      text-transform: uppercase;
      padding: 0.5rem 1rem 0.25rem 1rem;
    }
    
    .dropdown-divider {
      border-color: rgba(255, 255, 255, 0.08);
      margin: 0.5rem 0;
    }
    
    .navbar-toggler {
      border: 1px solid rgba(255, 255, 255, 0.1);
      border-radius: 8px;
    }
    
    .navbar-toggler:focus {
      box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
    }
    
    .btn-outline-light {
      border-radius: 8px;
      font-weight: 500;
      border-color: rgba(255, 255, 255, 0.15);
      transition: all 0.3s ease;
    }
    
    .btn-outline-light:hover {
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      border-color: transparent;
      box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
      transform: translateY(-1px);
    }
    
    .user-info {
      font-size: 0.85rem;
      padding: 0.4rem 0.8rem;
      background: rgba(255, 255, 255, 0.03);
      border-radius: 8px;
      border: 1px solid rgba(255, 255, 255, 0.05);
    }
    
    @media (max-width: 991px) {
      .dropdown-menu {
        border: none;
        background: rgba(255, 255, 255, 0.03);
        margin-top: 0.25rem !important;
      }
      
      .navbar-collapse {
        padding-top: 1rem;
      }
    }
  </style>
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
        <?php $role = $_SESSION['role'] ?? ''; ?>
        
        <?php if ($role === 'admin'): ?>
          <!-- Dropdown Instrumentos de medición -->
          <?php
            $instPages = ['admin.php','out_of_use.php','report.php'];
            $instActive = in_array($current, $instPages, true) ? 'active' : '';
          ?>
          <li class="nav-item dropdown">
            <a class="nav-link dropdown-toggle <?= $instActive ?>"
               href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
              <i class="fa fa-microscope me-1"></i>Instrumentos
            </a>
            <ul class="dropdown-menu dropdown-menu-dark">
              <li><a class="dropdown-item <?= active('admin.php') ?>" href="admin.php"><i class="fa fa-screwdriver-wrench me-2"></i>Administrar</a></li>
              <li><a class="dropdown-item <?= active('out_of_use.php') ?>" href="out_of_use.php"><i class="fa fa-box-archive me-2"></i>Fuera de uso</a></li>
              <li><a class="dropdown-item <?= active('report.php') ?>" href="report.php"><i class="fa fa-chart-column me-2"></i>Reportes</a></li>
            </ul>
          </li>
        <?php endif; ?>

        <?php if ($role === 'admin' || $role === 'ingenieria'): ?>
          <!-- Dropdown Golden -->
          <?php
            $goldPages = ['golden_admin.php','golden_add.php','golden_audit.php','golden_update.php','golden_history.php','golden_scrap.php','golden_package_create.php'];
            $goldActive = in_array($current, $goldPages, true) ? 'active' : '';
          ?>
          <li class="nav-item dropdown">
            <a class="nav-link dropdown-toggle <?= $goldActive ?>"
               href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
              <i class="fa fa-crown me-1"></i>Golden
            </a>
            <ul class="dropdown-menu dropdown-menu-dark">
              <li><a class="dropdown-item <?= active('golden_admin.php') ?>" href="golden_admin.php"><i class="fa fa-clipboard-list me-2"></i>Inventario</a></li>
              <li><a class="dropdown-item <?= active('golden_audit.php') ?>" href="golden_audit.php"><i class="fa fa-clipboard-check me-2"></i>Auditoría</a></li>
              <li><a class="dropdown-item <?= active('golden_add.php') ?>" href="golden_add.php"><i class="fa fa-plus me-2"></i>Nuevo</a></li>
              <li><a class="dropdown-item <?= active('golden_package_create.php') ?>" href="golden_package_create.php"><i class="fa fa-box-archive me-2"></i>Crear Paquete</a></li>
              <li><hr class="dropdown-divider"></li>
              <li><a class="dropdown-item <?= active('golden_scrap.php') ?>" href="golden_scrap.php"><i class="fa fa-dumpster me-2"></i>Scrap</a></li>
            </ul>
          </li>

          <!-- Dropdown Activos Ingenieria -->
          <?php
            $ingPages = ['ingenieria_admin.php','ingenieria_add.php','ingenieria_audit.php','ingenieria_update.php','ingenieria_history.php','ingenieria_scrap.php','ingenieria_package_create.php'];
            $ingActive = in_array($current, $ingPages, true) ? 'active' : '';
          ?>
          <li class="nav-item dropdown">
            <a class="nav-link dropdown-toggle <?= $ingActive ?>"
               href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
              <i class="fa fa-industry me-1"></i>Ingeniería
            </a>
            <ul class="dropdown-menu dropdown-menu-dark">
              <li><a class="dropdown-item <?= active('ingenieria_admin.php') ?>" href="ingenieria_admin.php"><i class="fa fa-clipboard-list me-2"></i>Inventario</a></li>
              <li><a class="dropdown-item <?= active('ingenieria_audit.php') ?>" href="ingenieria_audit.php"><i class="fa fa-clipboard-check me-2"></i>Auditoría</a></li>
              <li><a class="dropdown-item <?= active('ingenieria_add.php') ?>" href="ingenieria_add.php"><i class="fa fa-plus me-2"></i>Nuevo</a></li>
              <li><a class="dropdown-item <?= active('ingenieria_package_create.php') ?>" href="ingenieria_package_create.php"><i class="fa fa-box-archive me-2"></i>Crear Paquete</a></li>
              <li><hr class="dropdown-divider"></li>
              <li><a class="dropdown-item <?= active('ingenieria_scrap.php') ?>" href="ingenieria_scrap.php"><i class="fa fa-dumpster me-2"></i>Scrap</a></li>
            </ul>
          </li>

          <!-- Dropdown Assets HW -->
          <?php
             $hwPages = ['assets_hw_admin.php','assets_hw_add.php','assets_hw_audit.php','assets_hw_update.php','assets_hw_history.php','assets_hw_scrap.php','assets_hw_package_create.php'];
             $hwActive = in_array($current, $hwPages, true) ? 'active' : '';
          ?>
           <li class="nav-item dropdown">
            <a class="nav-link dropdown-toggle <?= $hwActive ?>"
               href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
              <i class="fa fa-server me-1"></i>Assets HW
            </a>
            <ul class="dropdown-menu dropdown-menu-dark">
              <li><a class="dropdown-item <?= active('assets_hw_admin.php') ?>" href="assets_hw_admin.php"><i class="fa fa-clipboard-list me-2"></i>Inventario</a></li>
              <li><a class="dropdown-item <?= active('assets_hw_audit.php') ?>" href="assets_hw_audit.php"><i class="fa fa-clipboard-check me-2"></i>Auditoría</a></li>
              <li><a class="dropdown-item <?= active('assets_hw_add.php') ?>" href="assets_hw_add.php"><i class="fa fa-plus me-2"></i>Nuevo</a></li>
              <li><a class="dropdown-item <?= active('assets_hw_package_create.php') ?>" href="assets_hw_package_create.php"><i class="fa fa-box-archive me-2"></i>Crear Paquete</a></li>
              <li><hr class="dropdown-divider"></li>
              <li><a class="dropdown-item <?= active('assets_hw_scrap.php') ?>" href="assets_hw_scrap.php"><i class="fa fa-dumpster me-2"></i>Scrap</a></li>
            </ul>
          </li>
          
          <!-- Dropdown Laboratorio (Linpu, Mediciones, Torques) -->
           <li class="nav-item dropdown">
            <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
              <i class="fa fa-flask me-1"></i>Laboratorio
            </a>
            <ul class="dropdown-menu dropdown-menu-dark">
              <li><h6 class="dropdown-header">Linpu F1200</h6></li>
              <li><a class="dropdown-item <?= active('linpu_admin.php') ?>" href="linpu_admin.php"><i class="fa fa-list-check me-2"></i>Registros</a></li>
              <li><a class="dropdown-item <?= active('linpu_add.php') ?>" href="linpu_add.php"><i class="fa fa-plus me-2"></i>Nuevo</a></li>
              <li><hr class="dropdown-divider"></li>
              <li><h6 class="dropdown-header">Mediciones</h6></li>
              <li><a class="dropdown-item" href="mediciones/dashboard.php"><i class="fa fa-chart-line me-2"></i>Dashboard</a></li>
              <li><a class="dropdown-item" href="mediciones/index.html"><i class="fa fa-plus me-2"></i>Nuevo</a></li>
              <li><hr class="dropdown-divider"></li>
              <li><h6 class="dropdown-header">Torques</h6></li>
              <li><a class="dropdown-item" href="torque/dashboard.php"><i class="fa fa-tachometer-alt me-2"></i>Dashboard</a></li>
              <li><a class="dropdown-item" href="torque/index.php"><i class="fa fa-list me-2"></i>Registros</a></li>
            </ul>
          </li>
        <?php endif; ?>

        <?php if ($role === 'admin'): ?>
          <li class="nav-item">
            <a class="nav-link <?= active('users_admin.php') ?>" href="users_admin.php">
              <i class="fa fa-users-gear me-1"></i>Usuarios
            </a>
          </li>
        <?php endif; ?>

        <?php if ($role !== 'admin' && $role !== 'ingenieria'): ?>
          <li class="nav-item">
            <a class="nav-link <?= active('consulta_dashboard.php') ?>" href="consulta_dashboard.php">
              <i class="fa fa-magnifying-glass me-1"></i>Consulta
            </a>
          </li>
          
          <li class="nav-item dropdown">
            <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
              <i class="fa fa-boxes-stacked me-1"></i>Inventarios
            </a>
            <ul class="dropdown-menu dropdown-menu-dark">
              <li><a class="dropdown-item <?= active('golden_view.php') ?>" href="golden_view.php"><i class="fa fa-crown me-2"></i>Golden</a></li>
              <li><a class="dropdown-item <?= active('ingenieria_view.php') ?>" href="ingenieria_view.php"><i class="fa fa-industry me-2"></i>Ingeniería</a></li>
              <li><a class="dropdown-item <?= active('assets_hw_view.php') ?>" href="assets_hw_view.php"><i class="fa fa-server me-2"></i>Assets HW</a></li>
            </ul>
          </li>

          <!-- Dropdown Laboratorio (User) -->
           <li class="nav-item dropdown">
            <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
              <i class="fa fa-flask me-1"></i>Laboratorio
            </a>
            <ul class="dropdown-menu dropdown-menu-dark">
              <li><h6 class="dropdown-header">Mediciones</h6></li>
              <li><a class="dropdown-item" href="mediciones/dashboard.php"><i class="fa fa-chart-line me-2"></i>Dashboard</a></li>
              <li><hr class="dropdown-divider"></li>
              <li><h6 class="dropdown-header">Torques</h6></li>
              <li><a class="dropdown-item" href="torque/dashboard.php"><i class="fa fa-tachometer-alt me-2"></i>Dashboard</a></li>
              <li><a class="dropdown-item" href="torque/index.php"><i class="fa fa-list me-2"></i>Registros</a></li>
            </ul>
          </li>

          <li class="nav-item">
            <a class="nav-link <?= active('report_view.php') ?>" href="report_view.php">
              <i class="fa fa-chart-column me-1"></i>Reportes
            </a>
          </li>
        <?php endif; ?>
      </ul>
      <div class="d-flex align-items-center gap-3">
        <span class="user-info text-secondary">
          <i class="fa fa-user-circle me-1"></i>
          <?= htmlspecialchars($_SESSION['username'] ?? 'usuario', ENT_QUOTES, 'UTF-8'); ?>
          <span class="d-none d-lg-inline">— <?= htmlspecialchars($_SESSION['role'] ?? 'consulta', ENT_QUOTES, 'UTF-8'); ?></span>
        </span>
        <a href="logout.php" class="btn btn-outline-light btn-sm">
          <i class="fa fa-right-from-bracket me-1"></i>Salir
        </a>
      </div>
    </div>
  </div>
</nav>

<main class="container-fluid py-3">
