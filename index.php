<?php
// /var/www/html/calibraciones/index.php
declare(strict_types=1);

require_once __DIR__.'/config.php';
require_auth(); // cualquier usuario logueado

$username = $_SESSION['username'] ?? 'usuario';
$role     = $_SESSION['role']     ?? 'consulta';
$isAdmin  = ($role === 'admin');

// helper para escapar
function h(?string $s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
?>
<?php include __DIR__.'/partials/header.php'; ?>

<div class="hero mb-4 p-4" style="background:linear-gradient(135deg,#1b1b1d 0%,#121214 100%);border:1px solid #222325;border-radius:16px;">
  <div class="row align-items-center g-3">
    <div class="col-lg-8">
      <h1 class="h3 mb-2">Bienvenido, <?= h($username) ?></h1>
      <p class="text-secondary mb-0">
        Gestiona instrumentos, consulta estados e inventarios Golden, y genera reportes.
      </p>
    </div>
    <div class="col-lg-4 text-lg-end">
      <img src="imagenes/calibracion.png" alt="Calibración" class="img-fluid" style="max-height:120px;">
    </div>
  </div>
</div>

<?php if ($isAdmin): ?>
  <!-- ADMIN: Instrumentos de medición -->
  <div class="mb-2">
    <h2 class="h6 text-uppercase text-secondary mb-2">Instrumentos de medición</h2>
  </div>
  <div class="row g-3 mb-4">
    <div class="col-12 col-md-6 col-lg-4">
      <div class="p-3 h-100" style="border:1px solid #222325;border-radius:12px;">
        <h3 class="h6 mb-3"><i class="fa fa-screwdriver-wrench me-2"></i>Administrar</h3>
        <p class="text-secondary small">Alta, edición, carga de certificados y seguimiento.</p>
        <a href="admin.php" class="btn btn-primary w-100"><i class="fa fa-arrow-right me-2"></i>Ir al panel</a>
      </div>
    </div>

    <div class="col-12 col-md-6 col-lg-4">
      <div class="p-3 h-100" style="border:1px solid #222325;border-radius:12px;">
        <h3 class="h6 mb-3"><i class="fa fa-box-archive me-2"></i>Fuera de uso</h3>
        <p class="text-secondary small">Consulta y retorno a activo cuando corresponda.</p>
        <a href="out_of_use.php" class="btn btn-outline-secondary w-100"><i class="fa fa-arrow-right me-2"></i>Ver listado</a>
      </div>
    </div>

    <div class="col-12 col-md-6 col-lg-4">
      <div class="p-3 h-100" style="border:1px solid #222325;border-radius:12px;">
        <h3 class="h6 mb-3"><i class="fa fa-chart-column me-2"></i>Reportes</h3>
        <p class="text-secondary small">Generación de reportes y análisis de vencimientos.</p>
        <a href="report.php" class="btn btn-outline-secondary w-100"><i class="fa fa-arrow-right me-2"></i>Abrir reportes</a>
      </div>
    </div>
  </div>

  <!-- ADMIN: Golden -->
  <div class="mb-2">
    <h2 class="h6 text-uppercase text-secondary mb-2">Golden</h2>
  </div>
  <div class="row g-3">
    <div class="col-12 col-md-6">
      <div class="p-3 h-100" style="border:1px solid #222325;border-radius:12px;">
        <h3 class="h6 mb-3"><i class="fa fa-clipboard-list me-2"></i>Inventario</h3>
        <p class="text-secondary small">Consulta, historial y envío a Scrap.</p>
        <a href="golden_admin.php" class="btn btn-success w-100"><i class="fa fa-arrow-right me-2"></i>Abrir inventario</a>
      </div>
    </div>
    <div class="col-12 col-md-6">
      <div class="p-3 h-100" style="border:1px solid #222325;border-radius:12px;">
        <h3 class="h6 mb-3"><i class="fa fa-plus me-2"></i>Nuevo</h3>
        <p class="text-secondary small">Registro rápido desde móvil con foto y documento.</p>
        <a href="golden_add.php" class="btn btn-outline-success w-100"><i class="fa fa-arrow-right me-2"></i>Crear material</a>
      </div>
    </div>
  </div>

<?php else: ?>
  <!-- CONSULTA: accesos de solo lectura -->
  <div class="row g-3">
    <div class="col-12 col-lg-4">
      <div class="p-3 h-100" style="border:1px solid #222325;border-radius:12px;">
        <h3 class="h6 mb-3"><i class="fa fa-magnifying-glass me-2"></i>Consulta de instrumentos</h3>
        <p class="text-secondary small">Búsqueda y detalle (solo lectura).</p>
        <a href="consulta_dashboard.php" class="btn btn-primary w-100"><i class="fa fa-arrow-right me-2"></i>Ir a consulta</a>
      </div>
    </div>

    <div class="col-12 col-lg-4">
      <div class="p-3 h-100" style="border:1px solid #222325;border-radius:12px;">
        <h3 class="h6 mb-3"><i class="fa fa-chart-column me-2"></i>Reportes</h3>
        <p class="text-secondary small">Gráficas y tabla de próximos vencimientos.</p>
        <a href="report_view.php" class="btn btn-outline-secondary w-100"><i class="fa fa-arrow-right me-2"></i>Ver reportes</a>
      </div>
    </div>

    <div class="col-12 col-lg-4">
      <div class="p-3 h-100" style="border:1px solid #222325;border-radius:12px;">
        <h3 class="h6 mb-3"><i class="fa fa-crown me-2"></i>Golden – Inventario</h3>
        <p class="text-secondary small">Listado y revisión (solo lectura).</p>
        <a href="golden_view.php" class="btn btn-outline-success w-100"><i class="fa fa-arrow-right me-2"></i>Ver Golden</a>
      </div>
    </div>
  </div>
<?php endif; ?>

<?php include __DIR__.'/partials/footer.php'; ?>
