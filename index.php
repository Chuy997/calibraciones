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
    <h2 class="h6 text-uppercase text-secondary mb-2"><i class="fa fa-microscope me-2"></i>Instrumentos de medición</h2>
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
    <div class="col-12 col-lg-4">
      <div class="p-3 h-100" style="border:1px solid #222325;border-radius:12px;">
        <h3 class="h6 mb-3"><i class="fa fa-chart-column me-2"></i>Reportes</h3>
        <p class="text-secondary small">Generación de reportes y análisis de vencimientos.</p>
        <a href="report.php" class="btn btn-outline-secondary w-100"><i class="fa fa-arrow-right me-2"></i>Abrir reportes</a>
      </div>
    </div>
  </div>

  <!-- ADMIN: Golden -->
  <div class="mb-2">
    <h2 class="h6 text-uppercase text-secondary mb-2"><i class="fa fa-crown me-2"></i>Golden</h2>
  </div>
  <div class="row g-3 mb-4">
    <div class="col-12 col-md-6 col-lg-4">
      <div class="p-3 h-100" style="border:1px solid #222325;border-radius:12px;">
        <h3 class="h6 mb-3"><i class="fa fa-clipboard-list me-2"></i>Inventario</h3>
        <p class="text-secondary small">Consulta, historial, auditoría y scrap.</p>
        <div class="d-grid gap-2">
           <a href="golden_admin.php" class="btn btn-success btn-sm"><i class="fa fa-arrow-right me-2"></i>Inventario</a>
           <a href="golden_scrap.php" class="btn btn-outline-secondary btn-sm"><i class="fa fa-dumpster me-2"></i>Ver Scrap</a>
        </div>
      </div>
    </div>
    <div class="col-12 col-md-6 col-lg-4">
      <div class="p-3 h-100" style="border:1px solid #222325;border-radius:12px;">
        <h3 class="h6 mb-3"><i class="fa fa-clipboard-check me-2"></i>Auditoría</h3>
        <p class="text-secondary small">Realizar auditorías físicas periódicas.</p>
        <a href="golden_audit.php" class="btn btn-outline-success w-100"><i class="fa fa-play me-2"></i>Iniciar auditoría</a>
      </div>
    </div>
    <div class="col-12 col-md-6 col-lg-4">
      <div class="p-3 h-100" style="border:1px solid #222325;border-radius:12px;">
        <h3 class="h6 mb-3"><i class="fa fa-plus me-2"></i>Nuevo</h3>
        <p class="text-secondary small">Registro rápido desde móvil con foto y documento.</p>
        <a href="golden_add.php" class="btn btn-outline-success w-100"><i class="fa fa-plus me-2"></i>Crear material</a>
      </div>
    </div>
  </div>

  <!-- ADMIN: Activos Ingeniería -->
  <div class="mb-2">
    <h2 class="h6 text-uppercase text-secondary mb-2"><i class="fa fa-industry me-2"></i>Activos Ingeniería</h2>
  </div>
  <div class="row g-3 mb-4">
    <div class="col-12 col-md-6 col-lg-4">
      <div class="p-3 h-100" style="border:1px solid #222325;border-radius:12px;">
        <h3 class="h6 mb-3"><i class="fa fa-clipboard-list me-2"></i>Inventario</h3>
        <p class="text-secondary small">Gestión de activos generales de ingeniería.</p>
        <div class="d-grid gap-2">
           <a href="ingenieria_admin.php" class="btn btn-primary btn-sm"><i class="fa fa-arrow-right me-2"></i>Inventario</a>
           <a href="ingenieria_scrap.php" class="btn btn-outline-secondary btn-sm"><i class="fa fa-dumpster me-2"></i>Ver Scrap</a>
        </div>
      </div>
    </div>
    <div class="col-12 col-md-6 col-lg-4">
      <div class="p-3 h-100" style="border:1px solid #222325;border-radius:12px;">
        <h3 class="h6 mb-3"><i class="fa fa-clipboard-check me-2"></i>Auditoría</h3>
        <p class="text-secondary small">Auditoría de activos generales.</p>
        <a href="ingenieria_audit.php" class="btn btn-outline-primary w-100"><i class="fa fa-play me-2"></i>Iniciar auditoría</a>
      </div>
    </div>
    <div class="col-12 col-md-6 col-lg-4">
      <div class="p-3 h-100" style="border:1px solid #222325;border-radius:12px;">
        <h3 class="h6 mb-3"><i class="fa fa-plus me-2"></i>Nuevo</h3>
        <p class="text-secondary small">Alta de nuevo activo.</p>
        <a href="ingenieria_add.php" class="btn btn-outline-primary w-100"><i class="fa fa-plus me-2"></i>Registrar</a>
      </div>
    </div>
  </div>

  <!-- ADMIN: Assets HW -->
  <div class="mb-2">
    <h2 class="h6 text-uppercase text-secondary mb-2"><i class="fa fa-server me-2"></i>Assets HW</h2>
  </div>
  <div class="row g-3 mb-4">
    <div class="col-12 col-md-6 col-lg-4">
      <div class="p-3 h-100" style="border:1px solid #222325;border-radius:12px;">
        <h3 class="h6 mb-3"><i class="fa fa-clipboard-list me-2"></i>Inventario</h3>
        <p class="text-secondary small">Hardware, servidores y equipos IT.</p>
        <div class="d-grid gap-2">
           <a href="assets_hw_admin.php" class="btn btn-info btn-sm"><i class="fa fa-arrow-right me-2"></i>Inventario</a>
           <a href="assets_hw_scrap.php" class="btn btn-outline-secondary btn-sm"><i class="fa fa-dumpster me-2"></i>Ver Scrap</a>
        </div>
      </div>
    </div>
    <div class="col-12 col-md-6 col-lg-4">
      <div class="p-3 h-100" style="border:1px solid #222325;border-radius:12px;">
        <h3 class="h6 mb-3"><i class="fa fa-clipboard-check me-2"></i>Auditoría</h3>
        <p class="text-secondary small">Control de inventario IT.</p>
        <a href="assets_hw_audit.php" class="btn btn-outline-info w-100"><i class="fa fa-play me-2"></i>Iniciar auditoría</a>
      </div>
    </div>
    <div class="col-12 col-md-6 col-lg-4">
      <div class="p-3 h-100" style="border:1px solid #222325;border-radius:12px;">
        <h3 class="h6 mb-3"><i class="fa fa-plus me-2"></i>Nuevo</h3>
        <p class="text-secondary small">Registrar nuevo hardware.</p>
        <a href="assets_hw_add.php" class="btn btn-outline-info w-100"><i class="fa fa-plus me-2"></i>Registrar</a>
      </div>
    </div>
  </div>

  <!-- ADMIN: Laboratorio (Agrupado) -->
  <div class="mb-2">
    <h2 class="h6 text-uppercase text-secondary mb-2"><i class="fa fa-flask me-2"></i>Laboratorio</h2>
  </div>
  <div class="row g-3 mb-4">
    <!-- Linpu -->
    <div class="col-12 col-md-6 col-lg-4">
      <div class="p-3 h-100" style="border:1px solid #222325;border-radius:12px;">
        <h3 class="h6 mb-3"><i class="fa fa-list-check me-2"></i>Linpu F1200</h3>
        <p class="text-secondary small">Registros de calibración óptica.</p>
        <div class="d-grid gap-2">
          <a href="linpu_admin.php" class="btn btn-secondary btn-sm"><i class="fa fa-list me-2"></i>Ver registros</a>
          <a href="linpu_add.php" class="btn btn-outline-secondary btn-sm"><i class="fa fa-plus me-2"></i>Nuevo registro</a>
        </div>
      </div>
    </div>

    <!-- Mediciones -->
    <div class="col-12 col-md-6 col-lg-4">
      <div class="p-3 h-100" style="border:1px solid #222325;border-radius:12px;">
        <h3 class="h6 mb-3"><i class="fa fa-chart-line me-2"></i>Mediciones</h3>
        <p class="text-secondary small">Partículas 0.5 µm y 5.0 µm.</p>
        <div class="d-grid gap-2">
           <a href="mediciones/dashboard.php" class="btn btn-warning btn-sm"><i class="fa fa-chart-line me-2"></i>Dashboard</a>
           <a href="mediciones/index.html" class="btn btn-outline-warning btn-sm"><i class="fa fa-plus me-2"></i>Capturar</a>
        </div>
      </div>
    </div>

    <!-- Torques -->
    <div class="col-12 col-md-6 col-lg-4">
      <div class="p-3 h-100" style="border:1px solid #222325;border-radius:12px;">
        <h3 class="h6 mb-3"><i class="fa fa-tachometer-alt me-2"></i>Torques</h3>
        <p class="text-secondary small">KPIs y historial de torques.</p>
        <div class="d-grid gap-2">
           <a href="torque/dashboard.php" class="btn btn-info btn-sm"><i class="fa fa-tachometer-alt me-2"></i>Dashboard</a>
           <a href="torque/index.php" class="btn btn-outline-info btn-sm"><i class="fa fa-list me-2"></i>Registros</a>
        </div>
      </div>
    </div>
  </div>

<?php else: ?>
  <!-- CONSULTA: accesos de solo lectura -->
  <div class="row g-3 mb-4">
    <!-- Instrumentos -->
    <div class="col-12 col-lg-4">
      <div class="p-3 h-100" style="border:1px solid #222325;border-radius:12px;">
        <h3 class="h6 mb-3"><i class="fa fa-magnifying-glass me-2"></i>Consulta de instrumentos</h3>
        <p class="text-secondary small">Búsqueda y detalle (solo lectura).</p>
        <a href="consulta_dashboard.php" class="btn btn-primary w-100"><i class="fa fa-arrow-right me-2"></i>Ir a consulta</a>
      </div>
    </div>

    <!-- Golden (View) -->
    <div class="col-12 col-lg-4">
      <div class="p-3 h-100" style="border:1px solid #222325;border-radius:12px;">
        <h3 class="h6 mb-3"><i class="fa fa-crown me-2"></i>Golden – Inventario</h3>
        <p class="text-secondary small">Listado y revisión (solo lectura).</p>
        <a href="golden_view.php" class="btn btn-outline-success w-100"><i class="fa fa-arrow-right me-2"></i>Ver Golden</a>
      </div>
    </div>

    <!-- Ingeniería (View) -->
    <div class="col-12 col-lg-4">
      <div class="p-3 h-100" style="border:1px solid #222325;border-radius:12px;">
        <h3 class="h6 mb-3"><i class="fa fa-industry me-2"></i>Ingeniería – Inventario</h3>
        <p class="text-secondary small">Listado y revisión (solo lectura).</p>
        <a href="ingenieria_view.php" class="btn btn-outline-primary w-100"><i class="fa fa-arrow-right me-2"></i>Ver Ingeniería</a>
      </div>
    </div>

    <!-- Assets HW (View) -->
    <div class="col-12 col-lg-4">
      <div class="p-3 h-100" style="border:1px solid #222325;border-radius:12px;">
        <h3 class="h6 mb-3"><i class="fa fa-server me-2"></i>Assets HW – Inventario</h3>
        <p class="text-secondary small">Listado y revisión (solo lectura).</p>
        <a href="assets_hw_view.php" class="btn btn-outline-info w-100"><i class="fa fa-arrow-right me-2"></i>Ver Hardware</a>
      </div>
    </div>

    <!-- Reportes -->
    <div class="col-12 col-lg-4">
      <div class="p-3 h-100" style="border:1px solid #222325;border-radius:12px;">
        <h3 class="h6 mb-3"><i class="fa fa-chart-column me-2"></i>Reportes</h3>
        <p class="text-secondary small">Gráficas y tabla de próximos vencimientos.</p>
        <a href="report_view.php" class="btn btn-outline-secondary w-100"><i class="fa fa-arrow-right me-2"></i>Ver reportes</a>
      </div>
    </div>
  </div>

  <!-- CONSULTA: Laboratorio (Mediciones + Torques) -->
  <div class="mb-2">
    <h2 class="h6 text-uppercase text-secondary mb-2"><i class="fa fa-flask me-2"></i>Laboratorio</h2>
  </div>
  <div class="row g-3">
    <!-- Mediciones -->
    <div class="col-12 col-lg-4">
      <div class="p-3 h-100" style="border:1px solid #222325;border-radius:12px;">
        <h3 class="h6 mb-3"><i class="fa fa-chart-line me-2"></i>Mediciones</h3>
        <p class="text-secondary small">Gráficas de partículas 0.5 µm y 5.0 µm.</p>
        <a href="mediciones/dashboard.php" class="btn btn-warning w-100"><i class="fa fa-arrow-right me-2"></i>Ver dashboard</a>
      </div>
    </div>
    
    <!-- Torques: Dashboard -->
    <div class="col-12 col-lg-4">
      <div class="p-3 h-100" style="border:1px solid #222325;border-radius:12px;">
        <h3 class="h6 mb-3"><i class="fa fa-tachometer-alt me-2"></i>Torques (KPIs)</h3>
        <p class="text-secondary small">KPIs y tendencias de calibración.</p>
        <a href="torque/dashboard.php" class="btn btn-info w-100"><i class="fa fa-arrow-right me-2"></i>Ver dashboard</a>
      </div>
    </div>
    
    <!-- Torques: Registros -->
    <div class="col-12 col-lg-4">
      <div class="p-3 h-100" style="border:1px solid #222325;border-radius:12px;">
        <h3 class="h6 mb-3"><i class="fa fa-list me-2"></i>Torques (Reg)</h3>
        <p class="text-secondary small">Listado de calibraciones recientes.</p>
        <a href="torque/index.php" class="btn btn-outline-info w-100"><i class="fa fa-arrow-right me-2"></i>Ver registros</a>
      </div>
    </div>
  </div>
<?php endif; ?>

<?php include __DIR__.'/partials/footer.php'; ?>