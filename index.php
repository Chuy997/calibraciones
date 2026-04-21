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

<style>
/* Modern Dashboard Styles */
.dashboard-hero {
  background: linear-gradient(135deg, #667eea 0%, #764ba2 50%, #f093fb 100%);
  border-radius: 24px;
  padding: 2.5rem;
  margin-bottom: 2.5rem;
  position: relative;
  overflow: hidden;
  box-shadow: 0 20px 60px rgba(102, 126, 234, 0.3);
}

.dashboard-hero::before {
  content: '';
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: rgba(255, 255, 255, 0.05);
  backdrop-filter: blur(10px);
}

.dashboard-hero-content {
  position: relative;
  z-index: 1;
}

.dashboard-section-title {
  font-size: 0.75rem;
  font-weight: 700;
  letter-spacing: 0.1em;
  text-transform: uppercase;
  color: #8b92a7;
  margin-bottom: 1.25rem;
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.dashboard-section-title i {
  font-size: 1rem;
}

.modern-card {
  background: rgba(255, 255, 255, 0.03);
  border: 1px solid rgba(255, 255, 255, 0.08);
  border-radius: 16px;
  padding: 1.75rem;
  height: 100%;
  transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
  backdrop-filter: blur(10px);
  position: relative;
  overflow: hidden;
}

.modern-card::before {
  content: '';
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  height: 3px;
  background: linear-gradient(90deg, transparent, var(--accent-color, #667eea), transparent);
  opacity: 0;
  transition: opacity 0.3s ease;
}

.modern-card:hover {
  transform: translateY(-4px);
  border-color: rgba(255, 255, 255, 0.15);
  box-shadow: 0 12px 40px rgba(0, 0, 0, 0.3);
}

.modern-card:hover::before {
  opacity: 1;
}

.modern-card--primary { --accent-color: #667eea; }
.modern-card--success { --accent-color: #10b981; }
.modern-card--warning { --accent-color: #f59e0b; }
.modern-card--info { --accent-color: #3b82f6; }
.modern-card--secondary { --accent-color: #6b7280; }

.card-icon {
  width: 48px;
  height: 48px;
  border-radius: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
  margin-bottom: 1rem;
  font-size: 1.25rem;
  background: linear-gradient(135deg, var(--accent-color, #667eea) 0%, rgba(var(--accent-rgb, 102, 126, 234), 0.6) 100%);
  box-shadow: 0 8px 24px rgba(var(--accent-rgb, 102, 126, 234), 0.25);
}

.modern-card h3 {
  font-size: 1.125rem;
  font-weight: 600;
  margin-bottom: 0.75rem;
  color: #fff;
}

.modern-card p {
  font-size: 0.875rem;
  color: #9ca3af;
  margin-bottom: 1.25rem;
  line-height: 1.6;
  min-height: 2.6em;
}

.modern-btn {
  border-radius: 10px;
  padding: 0.625rem 1.25rem;
  font-weight: 500;
  font-size: 0.875rem;
  transition: all 0.2s ease;
  border: 1px solid transparent;
  text-decoration: none;
  display: inline-block;
}

.modern-btn-primary {
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  color: #fff;
  box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
}

.modern-btn-primary:hover {
  box-shadow: 0 6px 20px rgba(102, 126, 234, 0.4);
  transform: translateY(-1px);
  color: #fff;
}

.modern-btn-outline {
  background: transparent;
  border: 1px solid rgba(255, 255, 255, 0.15);
  color: #d1d5db;
}

.modern-btn-outline:hover {
  background: rgba(255, 255, 255, 0.05);
  border-color: rgba(255, 255, 255, 0.25);
  color: #fff;
}

.grid-fade-in {
  animation: fadeInUp 0.6s ease backwards;
}

@keyframes fadeInUp {
  from {
    opacity: 0;
    transform: translateY(20px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

.row > div {
  animation: fadeInUp 0.6s ease backwards;
}

.row > div:nth-child(1) { animation-delay: 0.05s; }
.row > div:nth-child(2) { animation-delay: 0.1s; }
.row > div:nth-child(3) { animation-delay: 0.15s; }
.row > div:nth-child(4) { animation-delay: 0.2s; }
.row > div:nth-child(5) { animation-delay: 0.25s; }
.row > div:nth-child(6) { animation-delay: 0.3s; }

@media (max-width: 768px) {
  .dashboard-hero {
    padding: 1.5rem;
  }
  
  .modern-card {
    padding: 1.25rem;
  }
}
</style>

<!-- Hero Section -->
<div class="dashboard-hero">
  <div class="dashboard-hero-content">
    <div class="row align-items-center g-3">
      <div class="col-lg-8">
        <h1 class="h2 mb-2 fw-bold" style="color: #fff;">Bienvenido, <?= h($username) ?></h1>
        <p class="mb-0" style="color: rgba(255, 255, 255, 0.8); font-size: 1.05rem;">
          Sistema de gestión de instrumentos, inventarios y laboratorio
        </p>
      </div>
      
    </div>
  </div>
</div>

<?php if ($isAdmin): ?>
  <!-- ADMIN: Instrumentos de medición -->
  <div class="dashboard-section-title">
    <i class="fa fa-microscope"></i>
    Instrumentos de medición
  </div>
  <div class="row g-3 mb-4">
    <div class="col-12 col-md-6 col-lg-4">
      <div class="modern-card modern-card--primary">
        <div class="card-icon" style="--accent-rgb: 102, 126, 234;">
          <i class="fa fa-screwdriver-wrench"></i>
        </div>
        <h3>Administrar</h3>
        <p>Alta, edición, carga de certificados y seguimiento.</p>
        <a href="admin.php" class="modern-btn modern-btn-primary w-100">
          <i class="fa fa-arrow-right me-2"></i>Ir al panel
        </a>
      </div>
    </div>
    <div class="col-12 col-md-6 col-lg-4">
      <div class="modern-card modern-card--secondary">
        <div class="card-icon" style="--accent-rgb: 107, 114, 128;">
          <i class="fa fa-box-archive"></i>
        </div>
        <h3>Fuera de uso</h3>
        <p>Consulta y retorno a activo cuando corresponda.</p>
        <a href="out_of_use.php" class="modern-btn modern-btn-outline w-100">
          <i class="fa fa-arrow-right me-2"></i>Ver listado
        </a>
      </div>
    </div>
    <div class="col-12 col-lg-4">
      <div class="modern-card modern-card--info">
        <div class="card-icon" style="--accent-rgb: 59, 130, 246;">
          <i class="fa fa-chart-column"></i>
        </div>
        <h3>Reportes</h3>
        <p>Generación de reportes y análisis de vencimientos.</p>
        <a href="report.php" class="modern-btn modern-btn-outline w-100">
          <i class="fa fa-arrow-right me-2"></i>Abrir reportes
        </a>
      </div>
    </div>
  </div>

  <!-- ADMIN: Mantenimientos de Maquinaria -->
  <div class="dashboard-section-title">
    <i class="fa fa-gears"></i>
    Mantenimientos de Maquinaria
  </div>
  <div class="row g-3 mb-4">
    <div class="col-12 col-md-6 col-lg-4">
      <div class="modern-card modern-card--warning">
        <div class="card-icon" style="--accent-rgb: 245, 158, 11;">
          <i class="fa fa-clipboard-list"></i>
        </div>
        <h3>Panel de Equipos</h3>
        <p>Visualiza el estado de mantenimiento de toda la maquinaria.</p>
        <a href="mant_equipos_admin.php" class="modern-btn modern-btn-primary w-100">
          <i class="fa fa-arrow-right me-2"></i>Ver equipos
        </a>
      </div>
    </div>
    <div class="col-12 col-md-6 col-lg-4">
      <div class="modern-card modern-card--warning">
        <div class="card-icon" style="--accent-rgb: 245, 158, 11;">
          <i class="fa fa-plus"></i>
        </div>
        <h3>Registrar Equipo</h3>
        <p>Alta de nueva maquinaria con su período de mantenimiento.</p>
        <a href="mant_equipos_add.php" class="modern-btn modern-btn-outline w-100">
          <i class="fa fa-plus me-2"></i>Nuevo equipo
        </a>
      </div>
    </div>
    <div class="col-12 col-md-6 col-lg-4">
      <div class="modern-card modern-card--warning">
        <div class="card-icon" style="--accent-rgb: 245, 158, 11;">
          <i class="fa fa-chart-column"></i>
        </div>
        <h3>Reportes</h3>
        <p>Análisis gráfico y tabla de próximos mantenimientos.</p>
        <a href="mant_equipos_report.php" class="modern-btn modern-btn-outline w-100">
          <i class="fa fa-arrow-right me-2"></i>Ver reportes
        </a>
      </div>
    </div>
  </div>

  <!-- ADMIN: Golden -->
  <div class="dashboard-section-title">
    <i class="fa fa-crown"></i>
    Golden
  </div>
  <div class="row g-3 mb-4">
    <div class="col-12 col-md-6 col-lg-4">
      <div class="modern-card modern-card--success">
        <div class="card-icon" style="--accent-rgb: 16, 185, 129;">
          <i class="fa fa-clipboard-list"></i>
        </div>
        <h3>Inventario</h3>
        <p>Consulta, historial, auditoría y scrap.</p>
        <div class="d-grid gap-2">
          <a href="golden_admin.php" class="modern-btn modern-btn-primary btn-sm">
            <i class="fa fa-arrow-right me-2"></i>Inventario
          </a>
          <a href="golden_scrap.php" class="modern-btn modern-btn-outline btn-sm">
            <i class="fa fa-dumpster me-2"></i>Ver Scrap
          </a>
        </div>
      </div>
    </div>
    <div class="col-12 col-md-6 col-lg-4">
      <div class="modern-card modern-card--success">
        <div class="card-icon" style="--accent-rgb: 16, 185, 129;">
          <i class="fa fa-clipboard-check"></i>
        </div>
        <h3>Auditoría</h3>
        <p>Realizar auditorías físicas periódicas.</p>
        <a href="golden_audit.php" class="modern-btn modern-btn-outline w-100">
          <i class="fa fa-play me-2"></i>Iniciar auditoría
        </a>
      </div>
    </div>
    <div class="col-12 col-md-6 col-lg-4">
      <div class="modern-card modern-card--success">
        <div class="card-icon" style="--accent-rgb: 16, 185, 129;">
          <i class="fa fa-plus"></i>
        </div>
        <h3>Nuevo</h3>
        <p>Registro rápido desde móvil con foto y documento.</p>
        <a href="golden_add.php" class="modern-btn modern-btn-outline w-100">
          <i class="fa fa-plus me-2"></i>Crear material
        </a>
      </div>
    </div>
  </div>

  <!-- ADMIN: Activos Ingeniería -->
  <div class="dashboard-section-title">
    <i class="fa fa-industry"></i>
    Activos Ingeniería
  </div>
  <div class="row g-3 mb-4">
    <div class="col-12 col-md-6 col-lg-4">
      <div class="modern-card modern-card--primary">
        <div class="card-icon" style="--accent-rgb: 102, 126, 234;">
          <i class="fa fa-clipboard-list"></i>
        </div>
        <h3>Inventario</h3>
        <p>Gestión de activos generales de ingeniería.</p>
        <div class="d-grid gap-2">
          <a href="ingenieria_admin.php" class="modern-btn modern-btn-primary btn-sm">
            <i class="fa fa-arrow-right me-2"></i>Inventario
          </a>
          <a href="ingenieria_scrap.php" class="modern-btn modern-btn-outline btn-sm">
            <i class="fa fa-dumpster me-2"></i>Ver Scrap
          </a>
        </div>
      </div>
    </div>
    <div class="col-12 col-md-6 col-lg-4">
      <div class="modern-card modern-card--primary">
        <div class="card-icon" style="--accent-rgb: 102, 126, 234;">
          <i class="fa fa-clipboard-check"></i>
        </div>
        <h3>Auditoría</h3>
        <p>Auditoría de activos generales.</p>
        <a href="ingenieria_audit.php" class="modern-btn modern-btn-outline w-100">
          <i class="fa fa-play me-2"></i>Iniciar auditoría
        </a>
      </div>
    </div>
    <div class="col-12 col-md-6 col-lg-4">
      <div class="modern-card modern-card--primary">
        <div class="card-icon" style="--accent-rgb: 102, 126, 234;">
          <i class="fa fa-plus"></i>
        </div>
        <h3>Nuevo</h3>
        <p>Alta de nuevo activo.</p>
        <a href="ingenieria_add.php" class="modern-btn modern-btn-outline w-100">
          <i class="fa fa-plus me-2"></i>Registrar
        </a>
      </div>
    </div>
  </div>

  <!-- ADMIN: Assets HW -->
  <div class="dashboard-section-title">
    <i class="fa fa-server"></i>
    Assets HW
  </div>
  <div class="row g-3 mb-4">
    <div class="col-12 col-md-6 col-lg-4">
      <div class="modern-card modern-card--info">
        <div class="card-icon" style="--accent-rgb: 59, 130, 246;">
          <i class="fa fa-clipboard-list"></i>
        </div>
        <h3>Inventario</h3>
        <p>Hardware, servidores y equipos IT.</p>
        <div class="d-grid gap-2">
          <a href="assets_hw_admin.php" class="modern-btn modern-btn-primary btn-sm">
            <i class="fa fa-arrow-right me-2"></i>Inventario
          </a>
          <a href="assets_hw_scrap.php" class="modern-btn modern-btn-outline btn-sm">
            <i class="fa fa-dumpster me-2"></i>Ver Scrap
          </a>
        </div>
      </div>
    </div>
    <div class="col-12 col-md-6 col-lg-4">
      <div class="modern-card modern-card--info">
        <div class="card-icon" style="--accent-rgb: 59, 130, 246;">
          <i class="fa fa-clipboard-check"></i>
        </div>
        <h3>Auditoría</h3>
        <p>Control de inventario IT.</p>
        <a href="assets_hw_audit.php" class="modern-btn modern-btn-outline w-100">
          <i class="fa fa-play me-2"></i>Iniciar auditoría
        </a>
      </div>
    </div>
    <div class="col-12 col-md-6 col-lg-4">
      <div class="modern-card modern-card--info">
        <div class="card-icon" style="--accent-rgb: 59, 130, 246;">
          <i class="fa fa-plus"></i>
        </div>
        <h3>Nuevo</h3>
        <p>Registrar nuevo hardware.</p>
        <a href="assets_hw_add.php" class="modern-btn modern-btn-outline w-100">
          <i class="fa fa-plus me-2"></i>Registrar
        </a>
      </div>
    </div>
  </div>

  <!-- ADMIN: Laboratorio -->
  <div class="dashboard-section-title">
    <i class="fa fa-flask"></i>
    Laboratorio
  </div>
  <div class="row g-3 mb-4">
    <div class="col-12 col-md-6 col-lg-4">
      <div class="modern-card modern-card--secondary">
        <div class="card-icon" style="--accent-rgb: 107, 114, 128;">
          <i class="fa fa-list-check"></i>
        </div>
        <h3>Linpu F1200</h3>
        <p>Registros de calibración óptica.</p>
        <div class="d-grid gap-2">
          <a href="linpu_admin.php" class="modern-btn modern-btn-primary btn-sm">
            <i class="fa fa-list me-2"></i>Ver registros
          </a>
          <a href="linpu_add.php" class="modern-btn modern-btn-outline btn-sm">
            <i class="fa fa-plus me-2"></i>Nuevo registro
          </a>
        </div>
      </div>
    </div>

    <div class="col-12 col-md-6 col-lg-4">
      <div class="modern-card modern-card--warning">
        <div class="card-icon" style="--accent-rgb: 245, 158, 11;">
          <i class="fa fa-chart-line"></i>
        </div>
        <h3>Mediciones</h3>
        <p>Partículas 0.5 µm y 5.0 µm.</p>
        <div class="d-grid gap-2">
          <a href="mediciones/dashboard.php" class="modern-btn modern-btn-primary btn-sm">
            <i class="fa fa-chart-line me-2"></i>Dashboard
          </a>
          <a href="mediciones/index.html" class="modern-btn modern-btn-outline btn-sm">
            <i class="fa fa-plus me-2"></i>Capturar
          </a>
        </div>
      </div>
    </div>

    <div class="col-12 col-md-6 col-lg-4">
      <div class="modern-card modern-card--info">
        <div class="card-icon" style="--accent-rgb: 59, 130, 246;">
          <i class="fa fa-tachometer-alt"></i>
        </div>
        <h3>Torques</h3>
        <p>KPIs y historial de torques.</p>
        <div class="d-grid gap-2">
          <a href="torque/dashboard.php" class="modern-btn modern-btn-primary btn-sm">
            <i class="fa fa-tachometer-alt me-2"></i>Dashboard
          </a>
          <a href="torque/index.php" class="modern-btn modern-btn-outline btn-sm">
            <i class="fa fa-list me-2"></i>Registros
          </a>
        </div>
      </div>
    </div>
  </div>

<?php elseif ($role === 'ingenieria'): ?>
  <!-- INGENIERIA: Mantenimientos de Maquinaria -->
  <div class="dashboard-section-title">
    <i class="fa fa-gears"></i>
    Mantenimientos de Maquinaria
  </div>
  <div class="row g-3 mb-4">
    <div class="col-12 col-md-6 col-lg-4">
      <div class="modern-card modern-card--warning">
        <div class="card-icon" style="--accent-rgb: 245, 158, 11;">
          <i class="fa fa-clipboard-list"></i>
        </div>
        <h3>Panel de Equipos</h3>
        <p>Visualiza el estado de mantenimiento de toda la maquinaria.</p>
        <a href="mant_equipos_admin.php" class="modern-btn modern-btn-primary w-100">
          <i class="fa fa-arrow-right me-2"></i>Ver equipos
        </a>
      </div>
    </div>
    <div class="col-12 col-md-6 col-lg-4">
      <div class="modern-card modern-card--warning">
        <div class="card-icon" style="--accent-rgb: 245, 158, 11;">
          <i class="fa fa-plus"></i>
        </div>
        <h3>Registrar Equipo</h3>
        <p>Alta de nueva maquinaria con su período de mantenimiento.</p>
        <a href="mant_equipos_add.php" class="modern-btn modern-btn-outline w-100">
          <i class="fa fa-plus me-2"></i>Nuevo equipo
        </a>
      </div>
    </div>
    <div class="col-12 col-md-6 col-lg-4">
      <div class="modern-card modern-card--warning">
        <div class="card-icon" style="--accent-rgb: 245, 158, 11;">
          <i class="fa fa-chart-column"></i>
        </div>
        <h3>Reportes</h3>
        <p>Análisis gráfico y tabla de próximos mantenimientos.</p>
        <a href="mant_equipos_report.php" class="modern-btn modern-btn-outline w-100">
          <i class="fa fa-arrow-right me-2"></i>Ver reportes
        </a>
      </div>
    </div>
  </div>

  <!-- INGENIERIA: Activos Ingeniería -->
  <div class="dashboard-section-title">
    <i class="fa fa-industry"></i>
    Activos Ingeniería
  </div>
  <div class="row g-3 mb-4">
    <div class="col-12 col-md-6 col-lg-4">
      <div class="modern-card modern-card--primary">
        <div class="card-icon" style="--accent-rgb: 102, 126, 234;">
          <i class="fa fa-clipboard-list"></i>
        </div>
        <h3>Inventario</h3>
        <p>Gestión de activos generales de ingeniería.</p>
        <div class="d-grid gap-2">
          <a href="ingenieria_admin.php" class="modern-btn modern-btn-primary btn-sm">
            <i class="fa fa-arrow-right me-2"></i>Inventario
          </a>
          <a href="ingenieria_scrap.php" class="modern-btn modern-btn-outline btn-sm">
            <i class="fa fa-dumpster me-2"></i>Ver Scrap
          </a>
        </div>
      </div>
    </div>
    <div class="col-12 col-md-6 col-lg-4">
      <div class="modern-card modern-card--primary">
        <div class="card-icon" style="--accent-rgb: 102, 126, 234;">
          <i class="fa fa-clipboard-check"></i>
        </div>
        <h3>Auditoría</h3>
        <p>Auditoría de activos generales.</p>
        <a href="ingenieria_audit.php" class="modern-btn modern-btn-outline w-100">
          <i class="fa fa-play me-2"></i>Iniciar auditoría
        </a>
      </div>
    </div>
    <div class="col-12 col-md-6 col-lg-4">
      <div class="modern-card modern-card--primary">
        <div class="card-icon" style="--accent-rgb: 102, 126, 234;">
          <i class="fa fa-plus"></i>
        </div>
        <h3>Nuevo</h3>
        <p>Alta de nuevo activo.</p>
        <a href="ingenieria_add.php" class="modern-btn modern-btn-outline w-100">
          <i class="fa fa-plus me-2"></i>Registrar
        </a>
      </div>
    </div>
  </div>

  <!-- INGENIERIA: Assets HW -->
  <div class="dashboard-section-title">
    <i class="fa fa-server"></i>
    Assets HW
  </div>
  <div class="row g-3 mb-4">
    <div class="col-12 col-md-6 col-lg-4">
      <div class="modern-card modern-card--info">
        <div class="card-icon" style="--accent-rgb: 59, 130, 246;">
          <i class="fa fa-clipboard-list"></i>
        </div>
        <h3>Inventario</h3>
        <p>Hardware, servidores y equipos IT.</p>
        <div class="d-grid gap-2">
          <a href="assets_hw_admin.php" class="modern-btn modern-btn-primary btn-sm">
            <i class="fa fa-arrow-right me-2"></i>Inventario
          </a>
          <a href="assets_hw_scrap.php" class="modern-btn modern-btn-outline btn-sm">
            <i class="fa fa-dumpster me-2"></i>Ver Scrap
          </a>
        </div>
      </div>
    </div>
    <div class="col-12 col-md-6 col-lg-4">
      <div class="modern-card modern-card--info">
        <div class="card-icon" style="--accent-rgb: 59, 130, 246;">
          <i class="fa fa-clipboard-check"></i>
        </div>
        <h3>Auditoría</h3>
        <p>Control de inventario IT.</p>
        <a href="assets_hw_audit.php" class="modern-btn modern-btn-outline w-100">
          <i class="fa fa-play me-2"></i>Iniciar auditoría
        </a>
      </div>
    </div>
    <div class="col-12 col-md-6 col-lg-4">
      <div class="modern-card modern-card--info">
        <div class="card-icon" style="--accent-rgb: 59, 130, 246;">
          <i class="fa fa-plus"></i>
        </div>
        <h3>Nuevo</h3>
        <p>Registrar nuevo hardware.</p>
        <a href="assets_hw_add.php" class="modern-btn modern-btn-outline w-100">
          <i class="fa fa-plus me-2"></i>Registrar
        </a>
      </div>
    </div>
  </div>

<?php else: ?>
  <!-- CONSULTA: accesos de solo lectura -->
  <div class="row g-3 mb-4">
    <div class="col-12 col-lg-4">
      <div class="modern-card modern-card--primary">
        <div class="card-icon" style="--accent-rgb: 102, 126, 234;">
          <i class="fa fa-magnifying-glass"></i>
        </div>
        <h3>Consulta de instrumentos</h3>
        <p>Búsqueda y detalle (solo lectura).</p>
        <a href="consulta_dashboard.php" class="modern-btn modern-btn-primary w-100">
          <i class="fa fa-arrow-right me-2"></i>Ir a consulta
        </a>
      </div>
    </div>

    <div class="col-12 col-lg-4">
      <div class="modern-card modern-card--success">
        <div class="card-icon" style="--accent-rgb: 16, 185, 129;">
          <i class="fa fa-crown"></i>
        </div>
        <h3>Golden – Inventario</h3>
        <p>Listado y revisión (solo lectura).</p>
        <a href="golden_view.php" class="modern-btn modern-btn-outline w-100">
          <i class="fa fa-arrow-right me-2"></i>Ver Golden
        </a>
      </div>
    </div>

    <div class="col-12 col-lg-4">
      <div class="modern-card modern-card--primary">
        <div class="card-icon" style="--accent-rgb: 102, 126, 234;">
          <i class="fa fa-industry"></i>
        </div>
        <h3>Ingeniería – Inventario</h3>
        <p>Listado y revisión (solo lectura).</p>
        <a href="ingenieria_view.php" class="modern-btn modern-btn-outline w-100">
          <i class="fa fa-arrow-right me-2"></i>Ver Ingeniería
        </a>
      </div>
    </div>

    <div class="col-12 col-lg-4">
      <div class="modern-card modern-card--info">
        <div class="card-icon" style="--accent-rgb: 59, 130, 246;">
          <i class="fa fa-server"></i>
        </div>
        <h3>Assets HW – Inventario</h3>
        <p>Listado y revisión (solo lectura).</p>
        <a href="assets_hw_view.php" class="modern-btn modern-btn-outline w-100">
          <i class="fa fa-arrow-right me-2"></i>Ver Hardware
        </a>
      </div>
    </div>

    <div class="col-12 col-lg-4">
      <div class="modern-card modern-card--secondary">
        <div class="card-icon" style="--accent-rgb: 107, 114, 128;">
          <i class="fa fa-chart-column"></i>
        </div>
        <h3>Reportes</h3>
        <p>Gráficas y tabla de próximos vencimientos.</p>
        <a href="report_view.php" class="modern-btn modern-btn-outline w-100">
          <i class="fa fa-arrow-right me-2"></i>Ver reportes
        </a>
      </div>
    </div>
  </div>

  <!-- CONSULTA: Laboratorio -->
  <div class="dashboard-section-title">
    <i class="fa fa-flask"></i>
    Laboratorio
  </div>
  <div class="row g-3">
    <div class="col-12 col-lg-4">
      <div class="modern-card modern-card--warning">
        <div class="card-icon" style="--accent-rgb: 245, 158, 11;">
          <i class="fa fa-chart-line"></i>
        </div>
        <h3>Mediciones</h3>
        <p>Gráficas de partículas 0.5 µm y 5.0 µm.</p>
        <a href="mediciones/dashboard.php" class="modern-btn modern-btn-primary w-100">
          <i class="fa fa-arrow-right me-2"></i>Ver dashboard
        </a>
      </div>
    </div>
    
    <div class="col-12 col-lg-4">
      <div class="modern-card modern-card--info">
        <div class="card-icon" style="--accent-rgb: 59, 130, 246;">
          <i class="fa fa-tachometer-alt"></i>
        </div>
        <h3>Torques (KPIs)</h3>
        <p>KPIs y tendencias de calibración.</p>
        <a href="torque/dashboard.php" class="modern-btn modern-btn-primary w-100">
          <i class="fa fa-arrow-right me-2"></i>Ver dashboard
        </a>
      </div>
    </div>
    
    <div class="col-12 col-lg-4">
      <div class="modern-card modern-card--info">
        <div class="card-icon" style="--accent-rgb: 59, 130, 246;">
          <i class="fa fa-list"></i>
        </div>
        <h3>Torques (Reg)</h3>
        <p>Listado de calibraciones recientes.</p>
        <a href="torque/index.php" class="modern-btn modern-btn-outline w-100">
          <i class="fa fa-arrow-right me-2"></i>Ver registros
        </a>
      </div>
    </div>
  </div>
<?php endif; ?>

<?php include __DIR__.'/partials/footer.php'; ?>
