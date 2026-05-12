<?php
// /var/www/html/calibraciones/mant_equipos_periodicidad.php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_auth(['admin','ingenieria']);

function h(?string $s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

$pdo = pdo();

$equipos = $pdo->query("
    SELECT
        ID,
        Description,
        Brand,
        Model,
        Location,
        MaintPeriod,
        CycleMonthly,
        CycleQuarterly,
        CycleYearly,
        CASE
            WHEN CURRENT_DATE() > NextMaintDate THEN 'Vencido'
            WHEN NextMaintDate BETWEEN CURRENT_DATE() AND DATE_ADD(CURRENT_DATE(), INTERVAL 30 DAY) THEN 'Próximo'
            ELSE 'Al corriente'
        END AS status_calc
    FROM mant_equipos
    ORDER BY Description ASC
")->fetchAll();

$csrf = csrf_token();
?>
<?php include __DIR__ . '/partials/header.php'; ?>

<!-- ===== ESTILOS ===== -->
<style>
/* ---- Layout ---- */
.periodo-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  flex-wrap: wrap;
  gap: 1rem;
  margin-bottom: 1.5rem;
}
.periodo-subtitle {
  font-size: 0.9rem;
  color: #8b92a7;
  margin-top: 0.25rem;
}

/* ---- Tabla ---- */
.periodo-table-wrap {
  background: linear-gradient(145deg, #16181e 0%, #1e2128 100%);
  border: 1px solid rgba(255,255,255,0.06);
  border-radius: 16px;
  overflow: hidden;
}
.periodo-table {
  width: 100%;
  border-collapse: collapse;
}
.periodo-table thead tr {
  background: rgba(0,0,0,0.35);
  border-bottom: 1px solid rgba(255,255,255,0.08);
}
.periodo-table thead th {
  padding: 0.9rem 1rem;
  font-size: 0.78rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.06em;
  color: #8b92a7;
  white-space: nowrap;
}
.periodo-table thead th.cycle-head {
  text-align: center;
  min-width: 110px;
}
.periodo-table tbody tr {
  border-bottom: 1px solid rgba(255,255,255,0.04);
  transition: background 0.18s ease;
}
.periodo-table tbody tr:last-child { border-bottom: none; }
.periodo-table tbody tr:hover { background: rgba(255,255,255,0.03); }
.periodo-table tbody tr.all-off {
  background: rgba(220,53,69,0.06);
}
.periodo-table tbody tr.all-off:hover {
  background: rgba(220,53,69,0.10);
}
.periodo-table td {
  padding: 0.85rem 1rem;
  vertical-align: middle;
  color: #d8dce8;
  font-size: 0.9rem;
}
.eq-id {
  font-family: 'Courier New', monospace;
  font-size: 0.8rem;
  color: #8b92a7;
}
.eq-name { font-weight: 600; color: #fff; }
.eq-meta {
  font-size: 0.78rem;
  color: #6c7386;
  margin-top: 2px;
}
.eq-location {
  font-size: 0.82rem;
  color: #6c9fe8;
}
.eq-location i { margin-right: 4px; }

/* ---- Columna de ciclo ---- */
.cycle-cell {
  text-align: center;
}

/* ---- Toggle Switch ---- */
.toggle-wrap {
  display: inline-flex;
  flex-direction: column;
  align-items: center;
  gap: 5px;
}
.toggle-switch {
  position: relative;
  display: inline-block;
  width: 52px;
  height: 28px;
  cursor: pointer;
}
.toggle-switch input {
  opacity: 0;
  width: 0;
  height: 0;
  position: absolute;
}
.toggle-slider {
  position: absolute;
  inset: 0;
  background: #2d3341;
  border: 1px solid rgba(255,255,255,0.1);
  border-radius: 28px;
  transition: background 0.25s ease, border-color 0.25s ease;
}
.toggle-slider::before {
  content: '';
  position: absolute;
  left: 3px;
  top: 50%;
  transform: translateY(-50%);
  width: 20px;
  height: 20px;
  background: #5a6378;
  border-radius: 50%;
  transition: left 0.25s ease, background 0.25s ease, box-shadow 0.25s ease;
}
.toggle-switch input:checked + .toggle-slider {
  background: linear-gradient(135deg, #1a8a4a, #20c997);
  border-color: rgba(32,201,151,0.4);
}
.toggle-switch input:checked + .toggle-slider::before {
  left: 27px;
  background: #fff;
  box-shadow: 0 2px 6px rgba(0,0,0,0.4);
}
.toggle-switch input:focus-visible + .toggle-slider {
  outline: 2px solid #20c997;
  outline-offset: 2px;
}
.toggle-label-txt {
  font-size: 0.72rem;
  font-weight: 600;
  letter-spacing: 0.04em;
  text-transform: uppercase;
  color: #5a6378;
  transition: color 0.2s ease;
  user-select: none;
}
.toggle-switch input:checked ~ .toggle-label-txt { color: #20c997; }

/* ---- Feedback icon overlay ---- */
.cycle-cell { position: relative; }
.toggle-feedback {
  display: inline-block;
  width: 18px;
  height: 18px;
  margin-left: 6px;
  vertical-align: middle;
  opacity: 0;
  transition: opacity 0.3s ease;
}
.toggle-feedback.show { opacity: 1; }
.toggle-feedback.saving {
  border: 2px solid #ffc107;
  border-top-color: transparent;
  border-radius: 50%;
  animation: spin 0.6s linear infinite;
  display: inline-block;
}
.toggle-feedback.saved { color: #20c997; font-size: 14px; }
.toggle-feedback.error { color: #dc3545; font-size: 14px; }
@keyframes spin { to { transform: rotate(360deg); } }

/* ---- Warning all-off ---- */
.all-off-warn {
  font-size: 0.7rem;
  color: #ff6b7a;
  margin-top: 4px;
  display: none;
}
tr.all-off .all-off-warn { display: block; }

/* ---- Leyenda de colores ---- */
.legend-box {
  display: flex;
  gap: 1.5rem;
  flex-wrap: wrap;
  align-items: center;
  font-size: 0.82rem;
  color: #8b92a7;
}
.legend-item { display: flex; align-items: center; gap: 6px; }
.legend-dot {
  width: 12px; height: 12px;
  border-radius: 50%;
}

/* ---- Cabeceras de ciclo con badge de color ---- */
.cycle-badge {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  padding: 4px 10px;
  border-radius: 20px;
  font-size: 0.75rem;
  font-weight: 700;
}
.cycle-badge-monthly   { background: rgba(99,102,241,0.18); color: #a5b4fc; }
.cycle-badge-quarterly { background: rgba(251,146,60,0.18); color: #fdba74; }
.cycle-badge-yearly    { background: rgba(32,201,151,0.18); color: #6ee7b7; }

/* ---- Toolbar ---- */
.periodo-toolbar {
  display: flex;
  gap: 0.75rem;
  align-items: center;
  flex-wrap: wrap;
  margin-bottom: 1rem;
}
.periodo-toolbar .search-wrap {
  max-width: 300px;
  flex: 1;
}
.active-count {
  font-size: 0.82rem;
  color: #8b92a7;
  white-space: nowrap;
}

/* ---- Responsive ---- */
@media (max-width: 767px) {
  .periodo-table thead { display: none; }
  .periodo-table, .periodo-table tbody, .periodo-table tr, .periodo-table td {
    display: block; width: 100%;
  }
  .periodo-table tbody tr {
    border-radius: 12px;
    margin-bottom: 0.75rem;
    border: 1px solid rgba(255,255,255,0.07);
    padding: 0.75rem;
  }
  .periodo-table td { border: none; padding: 0.4rem 0; }
  .cycle-cell { text-align: left; }
  .cycle-cell::before {
    content: attr(data-label);
    display: inline-block;
    min-width: 110px;
    font-size: 0.75rem;
    color: #8b92a7;
    font-weight: 600;
    text-transform: uppercase;
  }
}
</style>

<!-- ===== CONTENIDO ===== -->
<div class="periodo-header">
  <div>
    <h1 class="h4 m-0">
      <i class="fa fa-sliders me-2"></i>Configuración de Periodicidad
    </h1>
    <p class="periodo-subtitle">
      Activa o desactiva los ciclos de mantenimiento por equipo. Los ciclos desactivados no generarán alertas ni aparecerán en el calendario.
    </p>
  </div>
  <a href="mant_equipos_admin.php" class="btn btn-outline-secondary">
    <i class="fa fa-arrow-left me-2"></i>Volver al Panel
  </a>
</div>

<!-- Leyenda -->
<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
  <div class="legend-box">
    <span class="legend-item">
      <span class="legend-dot" style="background:#6366f1;"></span>Mensual (cada mes)
    </span>
    <span class="legend-item">
      <span class="legend-dot" style="background:#fb923c;"></span>Trimestral (cada 3 meses)
    </span>
    <span class="legend-item">
      <span class="legend-dot" style="background:#20c997;"></span>Anual (cada 12 meses)
    </span>
    <span class="legend-item" style="color:#ff6b7a;">
      <i class="fa fa-triangle-exclamation me-1"></i>Rojo = todos los ciclos desactivados
    </span>
  </div>
  <span class="active-count" id="activeCount"></span>
</div>

<!-- Toolbar de búsqueda -->
<div class="periodo-toolbar">
  <div class="search-wrap input-group">
    <span class="input-group-text"><i class="fa fa-magnifying-glass"></i></span>
    <input type="text" id="searchInput" class="form-control" placeholder="Buscar por ID, nombre, marca…">
  </div>
  <select id="filterCycle" class="form-select" style="max-width:190px;">
    <option value="">Todos los equipos</option>
    <option value="monthly">Con ciclo Mensual</option>
    <option value="quarterly">Con ciclo Trimestral</option>
    <option value="yearly">Con ciclo Anual</option>
    <option value="alloff">Sin ningún ciclo activo</option>
  </select>
</div>

<!-- Tabla -->
<div class="periodo-table-wrap">
  <table class="periodo-table" id="periodicidadTable">
    <thead>
      <tr>
        <th style="min-width:220px;">Equipo</th>
        <th>Ubicación</th>
        <th class="cycle-head">
          <span class="cycle-badge cycle-badge-monthly">
            <i class="fa fa-calendar-day"></i> Mensual
          </span>
        </th>
        <th class="cycle-head">
          <span class="cycle-badge cycle-badge-quarterly">
            <i class="fa fa-rotate"></i> Trimestral
          </span>
        </th>
        <th class="cycle-head">
          <span class="cycle-badge cycle-badge-yearly">
            <i class="fa fa-calendar-check"></i> Anual
          </span>
        </th>
      </tr>
    </thead>
    <tbody>
    <?php foreach ($equipos as $eq):
      $allOff = !$eq['CycleMonthly'] && !$eq['CycleQuarterly'] && !$eq['CycleYearly'];
      $rowClass = $allOff ? 'all-off' : '';
    ?>
      <tr class="<?= $rowClass ?>"
          data-id="<?= h($eq['ID']) ?>"
          data-monthly="<?= $eq['CycleMonthly'] ? '1' : '0' ?>"
          data-quarterly="<?= $eq['CycleQuarterly'] ? '1' : '0' ?>"
          data-yearly="<?= $eq['CycleYearly'] ? '1' : '0' ?>"
          data-search="<?= h(strtolower(
              $eq['ID'] . ' ' .
              $eq['Description'] . ' ' .
              ($eq['Brand'] ?? '') . ' ' .
              ($eq['Model'] ?? '') . ' ' .
              ($eq['Location'] ?? '')
          )) ?>">

        <!-- Equipo -->
        <td>
          <div class="eq-id"><?= h($eq['ID']) ?></div>
          <div class="eq-name"><?= h($eq['Description']) ?></div>
          <?php if ($eq['Brand'] || $eq['Model']): ?>
          <div class="eq-meta">
            <?= h(trim($eq['Brand'] . ' ' . $eq['Model'])) ?>
          </div>
          <?php endif; ?>
          <div class="all-off-warn">
            <i class="fa fa-triangle-exclamation me-1"></i>Sin ciclos activos
          </div>
        </td>

        <!-- Ubicación -->
        <td>
          <?php if (!empty($eq['Location'])): ?>
            <span class="eq-location">
              <i class="fa fa-location-dot"></i><?= h($eq['Location']) ?>
            </span>
          <?php else: ?>
            <span class="text-muted">—</span>
          <?php endif; ?>
        </td>

        <!-- Ciclo Mensual -->
        <td class="cycle-cell" data-label="Mensual: ">
          <div class="toggle-wrap">
            <label class="toggle-switch">
              <input type="checkbox"
                     data-equipo="<?= h($eq['ID']) ?>"
                     data-cycle="monthly"
                     <?= $eq['CycleMonthly'] ? 'checked' : '' ?>
                     aria-label="Ciclo mensual para <?= h($eq['Description']) ?>">
              <span class="toggle-slider"></span>
            </label>
            <span class="toggle-label-txt"><?= $eq['CycleMonthly'] ? 'Activo' : 'Inactivo' ?></span>
          </div>
        </td>

        <!-- Ciclo Trimestral -->
        <td class="cycle-cell" data-label="Trimestral: ">
          <div class="toggle-wrap">
            <label class="toggle-switch">
              <input type="checkbox"
                     data-equipo="<?= h($eq['ID']) ?>"
                     data-cycle="quarterly"
                     <?= $eq['CycleQuarterly'] ? 'checked' : '' ?>
                     aria-label="Ciclo trimestral para <?= h($eq['Description']) ?>">
              <span class="toggle-slider"></span>
            </label>
            <span class="toggle-label-txt"><?= $eq['CycleQuarterly'] ? 'Activo' : 'Inactivo' ?></span>
          </div>
        </td>

        <!-- Ciclo Anual -->
        <td class="cycle-cell" data-label="Anual: ">
          <div class="toggle-wrap">
            <label class="toggle-switch">
              <input type="checkbox"
                     data-equipo="<?= h($eq['ID']) ?>"
                     data-cycle="yearly"
                     <?= $eq['CycleYearly'] ? 'checked' : '' ?>
                     aria-label="Ciclo anual para <?= h($eq['Description']) ?>">
              <span class="toggle-slider"></span>
            </label>
            <span class="toggle-label-txt"><?= $eq['CycleYearly'] ? 'Activo' : 'Inactivo' ?></span>
          </div>
        </td>

      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>

<div class="d-flex justify-content-between align-items-center mt-3">
  <small class="text-secondary">Los cambios se guardan automáticamente al activar/desactivar.</small>
  <small id="rowCount" class="text-secondary"></small>
</div>

<!-- ===== SCRIPT ===== -->
<script>
const CSRF_TOKEN = <?= json_encode($csrf) ?>;

// ---- Toggle handler ----
document.querySelectorAll('.toggle-switch input[data-equipo]').forEach(chk => {
  chk.addEventListener('change', async function () {
    const equipoId = this.dataset.equipo;
    const cycle    = this.dataset.cycle;
    const enabled  = this.checked;
    const wrap     = this.closest('.toggle-wrap');
    const labelTxt = wrap.querySelector('.toggle-label-txt');
    const row      = this.closest('tr');

    // Deshabilitar temporalmente
    this.disabled = true;

    // Mostrar spinner inline
    let spinner = wrap.querySelector('.fb-spinner');
    if (!spinner) {
      spinner = document.createElement('div');
      spinner.className = 'toggle-feedback saving fb-spinner show';
      wrap.appendChild(spinner);
    } else {
      spinner.className = 'toggle-feedback saving fb-spinner show';
    }

    try {
      const res = await fetch('mant_equipos_periodicidad_toggle.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ csrf: CSRF_TOKEN, id: equipoId, cycle, enabled }),
      });

      const data = await res.json();

      if (data.ok) {
        // Actualizar label
        labelTxt.textContent = enabled ? 'Activo' : 'Inactivo';
        // Actualizar data attribute en la fila
        row.dataset[cycle] = enabled ? '1' : '0';
        // Verificar si todos OFF
        updateRowWarning(row);
        // Feedback verde breve
        spinner.className = 'toggle-feedback saved fb-spinner show';
        spinner.innerHTML = '<i class="fa fa-check"></i>';
        setTimeout(() => { spinner.className = 'toggle-feedback fb-spinner'; spinner.innerHTML = ''; }, 1500);
        // Actualizar contador
        updateActiveCount();
      } else {
        throw new Error(data.error || 'Error desconocido');
      }
    } catch (err) {
      // Revertir el toggle
      this.checked = !enabled;
      spinner.className = 'toggle-feedback error fb-spinner show';
      spinner.innerHTML = '<i class="fa fa-triangle-exclamation" title="' + err.message + '"></i>';
      setTimeout(() => { spinner.className = 'toggle-feedback fb-spinner'; spinner.innerHTML = ''; }, 2500);
      console.error('Error al guardar periodicidad:', err);
    }

    this.disabled = false;
  });
});

// ---- Verificar si todos los ciclos de una fila están OFF ----
function updateRowWarning(row) {
  const monthly   = row.dataset.monthly   === '1';
  const quarterly = row.dataset.quarterly === '1';
  const yearly    = row.dataset.yearly    === '1';
  const allOff    = !monthly && !quarterly && !yearly;
  row.classList.toggle('all-off', allOff);
}

// ---- Contador de equipos activos ----
function updateActiveCount() {
  const rows   = document.querySelectorAll('#periodicidadTable tbody tr');
  const active = [...rows].filter(r =>
    r.dataset.monthly === '1' || r.dataset.quarterly === '1' || r.dataset.yearly === '1'
  ).length;
  const el = document.getElementById('activeCount');
  if (el) el.textContent = active + ' de ' + rows.length + ' equipos con al menos un ciclo activo';
}

// ---- Búsqueda + filtro ----
const searchInput  = document.getElementById('searchInput');
const filterCycle  = document.getElementById('filterCycle');
const rowCountEl   = document.getElementById('rowCount');

function applyFilters() {
  const term  = (searchInput.value || '').toLowerCase().trim();
  const cycle = filterCycle.value;
  const rows  = document.querySelectorAll('#periodicidadTable tbody tr');
  let visible = 0;

  rows.forEach(row => {
    const search    = row.dataset.search || '';
    const monthly   = row.dataset.monthly   === '1';
    const quarterly = row.dataset.quarterly === '1';
    const yearly    = row.dataset.yearly    === '1';
    const allOff    = !monthly && !quarterly && !yearly;

    let matchCycle = true;
    if      (cycle === 'monthly')   matchCycle = monthly;
    else if (cycle === 'quarterly') matchCycle = quarterly;
    else if (cycle === 'yearly')    matchCycle = yearly;
    else if (cycle === 'alloff')    matchCycle = allOff;

    const matchTerm = !term || row.dataset.search.includes(term);

    if (matchTerm && matchCycle) {
      row.style.display = '';
      visible++;
    } else {
      row.style.display = 'none';
    }
  });

  if (rowCountEl) rowCountEl.textContent = visible + ' equipo' + (visible !== 1 ? 's' : '') + ' mostrado' + (visible !== 1 ? 's' : '');
}

searchInput.addEventListener('input', applyFilters);
filterCycle.addEventListener('change', applyFilters);

// Inicializar
applyFilters();
updateActiveCount();
</script>

<?php include __DIR__ . '/partials/footer.php'; ?>
