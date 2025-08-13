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
      <h1 class="h3 mb-2">Bienvenido</h1>
      <p class="text-secondary mb-0">
        Gestiona instrumentos, consulta estados de calibración y genera reportes.
      </p>
    </div>
    <div class="col-lg-4 text-lg-end">
      <img src="imagenes/calibracion.png" alt="Calibración" class="img-fluid" style="max-height:120px;">
    </div>
  </div>
</div>

<div class="row g-3">
  <?php if ($isAdmin): ?>
    <div class="col-12 col-md-6 col-lg-4">
      <div class="p-3 h-100" style="border:1px solid #222325;border-radius:12px;">
        <h2 class="h6 mb-3"><i class="fa fa-tools me-2"></i>Administrar Instrumentos</h2>
        <p class="text-secondary small">Alta, edición, carga de certificados y seguimiento.</p>
        <a href="admin.php" class="btn btn-primary w-100"><i class="fa fa-arrow-right me-2"></i>Ir al panel</a>
      </div>
    </div>

    <div class="col-12 col-md-6 col-lg-4">
      <div class="p-3 h-100" style="border:1px solid #222325;border-radius:12px;">
        <h2 class="h6 mb-3"><i class="fa fa-box-archive me-2"></i>Instrumentos fuera de uso</h2>
        <p class="text-secondary small">Consulta y retorno a activo cuando corresponda.</p>
        <a href="out_of_use.php" class="btn btn-outline-secondary w-100"><i class="fa fa-arrow-right me-2"></i>Ver listado</a>
      </div>
    </div>

    <div class="col-12 col-md-6 col-lg-4">
      <div class="p-3 h-100" style="border:1px solid #222325;border-radius:12px;">
        <h2 class="h6 mb-3"><i class="fa fa-file-lines me-2"></i>Reportes</h2>
        <p class="text-secondary small">Generación de reportes mensuales/anuales de calibración.</p>
        <a href="report.php" class="btn btn-outline-secondary w-100"><i class="fa fa-arrow-right me-2"></i>Abrir reportes</a>
      </div>
    </div>
  <?php else: ?>
    <div class="col-12">
      <div class="p-4 d-flex align-items-center justify-content-between" style="border:1px solid #222325;border-radius:12px;">
        <div>
          <h2 class="h6 mb-2"><i class="fa fa-circle-info me-2"></i>Acceso de consulta</h2>
          <p class="text-secondary small mb-0">Tu cuenta no tiene permisos administrativos. Contacta a un administrador si necesitas más acceso.</p>
        </div>
        <a href="logout.php" class="btn btn-outline-light btn-sm"><i class="fa fa-right-from-bracket me-2"></i>Salir</a>
      </div>
    </div>
  <?php endif; ?>
</div>

<?php include __DIR__.'/partials/footer.php'; ?>
