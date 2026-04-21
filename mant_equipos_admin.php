<?php
// /var/www/html/calibraciones/mant_equipos_admin.php
declare(strict_types=1);

require __DIR__.'/config.php';
require_auth(['admin','ingenieria']);

$sql = <<<SQL
SELECT
    e.ID,
    e.Picture,
    e.Description,
    e.Brand,
    e.Model,
    e.SerialNumber,
    e.Location,
    e.LastMaintDate,
    e.NextMaintDate,
    e.MaintPeriod,
    e.Comments,
    e.PdfPath AS CurrentPdf,
    CASE
        WHEN CURRENT_DATE() > e.NextMaintDate THEN 'Vencido'
        WHEN e.NextMaintDate BETWEEN CURRENT_DATE() AND DATE_ADD(CURRENT_DATE(), INTERVAL 30 DAY) THEN 'Próximo mantenimiento'
        ELSE 'Al corriente'
    END AS status_calculado
FROM mant_equipos e
ORDER BY e.NextMaintDate ASC
SQL;

$stmt = pdo()->query($sql);
$rows = $stmt->fetchAll();

// Historial de PDFs
$pdfSql = "
SELECT EquipoID, PdfPath, MAX(UpdatedAt) as LastUpdate
FROM mant_equipos_history
WHERE PdfPath IS NOT NULL AND PdfPath != ''
GROUP BY EquipoID, PdfPath
ORDER BY LastUpdate DESC
";
$pdfStmt = pdo()->query($pdfSql);
$allPdfs = [];
foreach ($pdfStmt->fetchAll() as $p) {
    if (!isset($allPdfs[$p['EquipoID']])) {
        $allPdfs[$p['EquipoID']] = [];
    }
    $path = $p['PdfPath'];
    if (!str_starts_with($path, '/')) {
        $path = '/' . ltrim($path, '/');
    }
    $allPdfs[$p['EquipoID']][] = [
        'path' => $path,
        'date' => substr((string)$p['LastUpdate'], 0, 10)
    ];
}

function h(?string $s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

$periodLabels = ['3M' => 'Cada 3 meses', '6M' => 'Cada 6 meses', '1Y' => 'Anual'];
?>
<?php include __DIR__.'/partials/header.php'; ?>

<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
  <h1 class="h4 m-0"><i class="fa fa-gears me-2"></i>Mantenimientos de Maquinaria</h1>
  <a class="btn btn-success btn-lg" href="mant_equipos_add.php"><i class="fa fa-plus me-2"></i>Nuevo Equipo</a>
</div>

<div class="card p-3 table-density-comfort dt-container">
  <div class="dt-toolbar">
    <!-- Búsqueda -->
    <div class="input-group mb-2 mb-md-0" style="max-width:320px;">
      <span class="input-group-text"><i class="fa fa-magnifying-glass"></i></span>
      <input type="text" id="searchInput" class="form-control form-control-lg" placeholder="Buscar equipo…">
    </div>

    <!-- Filtro por estado -->
    <div class="d-flex gap-2 flex-wrap">
      <select class="form-select" id="filterStatus" style="max-width:200px;">
        <option value="">Todos los estados</option>
        <option value="Al corriente">Al corriente</option>
        <option value="Próximo mantenimiento">Próximo</option>
        <option value="Vencido">Vencido</option>
      </select>
      <select class="form-select" id="filterPeriod" style="max-width:180px;">
        <option value="">Todos los periodos</option>
        <option value="3M">Cada 3 meses</option>
        <option value="6M">Cada 6 meses</option>
        <option value="1Y">Anual</option>
      </select>
    </div>
  </div>

  <!-- Grid de Cards -->
  <div class="mant-grid" id="mantGrid">
    <?php foreach ($rows as $r):
      $estado = $r['status_calculado'] ?? '';
      $badge  = $estado === 'Vencido' ? 'badge-ven' : ($estado === 'Próximo mantenimiento' ? 'badge-prox' : 'badge-cal');
      $stateIcon = $estado === 'Vencido' ? 'fa-circle-xmark' : ($estado === 'Próximo mantenimiento' ? 'fa-clock' : 'fa-circle-check');
      $period = $r['MaintPeriod'] ?? '1Y';
      $periodLabel = $periodLabels[$period] ?? $period;
    ?>
    <div class="mant-card" data-id="<?= h($r['ID']) ?>" data-status="<?= h($estado) ?>" data-period="<?= h($period) ?>">
      <!-- Card Header con Imagen y Estado -->
      <div class="card-header-img">
        <?php if (!empty($r['Picture'])): ?>
          <img src="<?= h($r['Picture']) ?>" alt="<?= h($r['Description']) ?>"
               class="card-img"
               data-bs-toggle="modal" data-bs-target="#imagePreviewModal"
               data-src="<?= h($r['Picture']) ?>">
        <?php else: ?>
          <div class="card-img-placeholder">
            <i class="fa fa-gears fa-3x opacity-25"></i>
          </div>
        <?php endif; ?>
        <div class="card-status-overlay">
          <span class="badge badge-status <?= $badge ?>">
            <i class="fa <?= $stateIcon ?> me-1"></i><?= h($estado) ?>
          </span>
        </div>
        <div class="card-period-overlay">
          <span class="badge bg-dark bg-opacity-75">
            <i class="fa fa-rotate me-1"></i><?= h($periodLabel) ?>
          </span>
        </div>
      </div>

      <!-- Card Body -->
      <div class="card-body-content">
        <div class="card-title-row">
          <h5 class="card-title mb-1"><?= h($r['Description']) ?></h5>
          <span class="card-id badge bg-secondary"><?= h($r['ID']) ?></span>
        </div>

        <div class="card-specs">
          <?php if (!empty($r['Brand'])): ?>
          <div class="spec-item">
            <i class="fa fa-trademark text-muted me-1"></i>
            <span class="spec-label">Marca:</span>
            <span class="spec-value"><?= h($r['Brand']) ?></span>
          </div>
          <?php endif; ?>
          <?php if (!empty($r['Model'])): ?>
          <div class="spec-item">
            <i class="fa fa-cube text-muted me-1"></i>
            <span class="spec-label">Modelo:</span>
            <span class="spec-value"><?= h($r['Model']) ?></span>
          </div>
          <?php endif; ?>
          <?php if (!empty($r['SerialNumber'])): ?>
          <div class="spec-item">
            <i class="fa fa-barcode text-muted me-1"></i>
            <span class="spec-label">Serie:</span>
            <span class="spec-value"><?= h($r['SerialNumber']) ?></span>
          </div>
          <?php endif; ?>
          <?php if (!empty($r['Location'])): ?>
          <div class="spec-item">
            <i class="fa fa-location-dot text-info me-1"></i>
            <span class="spec-label">Ubicación:</span>
            <span class="spec-value"><?= h($r['Location']) ?></span>
          </div>
          <?php endif; ?>
        </div>

        <div class="card-dates">
          <div class="date-item">
            <i class="fa fa-calendar-check text-success me-1"></i>
            <span class="date-label">Último mant.:</span>
            <span class="date-value"><?= h($r['LastMaintDate'] ?? '—') ?></span>
          </div>
          <div class="date-item">
            <i class="fa fa-calendar-days text-warning me-1"></i>
            <span class="date-label">Próximo:</span>
            <span class="date-value"><?= h($r['NextMaintDate'] ?? '—') ?></span>
          </div>
        </div>
      </div>

      <!-- Card Footer con Acciones -->
      <div class="card-footer-actions">
        <?php
          $pdfs = $allPdfs[$r['ID']] ?? [];
          if (!empty($r['CurrentPdf'])) {
              $curr = $r['CurrentPdf'];
              if (!str_starts_with($curr, '/')) $curr = '/' . ltrim($curr, '/');
              $found = false;
              foreach ($pdfs as $p) {
                  if ($p['path'] === $curr) { $found = true; break; }
              }
              if (!$found) {
                  array_unshift($pdfs, ['path' => $curr, 'date' => $r['LastMaintDate']]);
              }
          }
        ?>
        <?php if (count($pdfs) === 1): ?>
          <a href="<?= h($pdfs[0]['path']) ?>" target="_blank"
             class="btn btn-sm btn-outline-light" title="Ver PDF">
            <i class="fa fa-file-pdf me-1"></i>PDF
          </a>
        <?php elseif (count($pdfs) > 1): ?>
          <div class="dropdown">
            <button class="btn btn-sm btn-outline-light dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
              <i class="fa fa-file-pdf me-1"></i>PDFs (<?= count($pdfs) ?>)
            </button>
            <ul class="dropdown-menu dropdown-menu-dark shadow">
              <?php foreach ($pdfs as $index => $pdf): ?>
                <li>
                  <a class="dropdown-item d-flex align-items-center justify-content-between" href="<?= h($pdf['path']) ?>" target="_blank">
                    <span>
                      <i class="fa fa-file-pdf me-2 text-danger"></i>
                      <?= $index === 0 ? 'Más reciente' : 'Anterior ' . $index ?>
                    </span>
                    <small class="text-secondary ms-3"><?= h($pdf['date']) ?></small>
                  </a>
                </li>
              <?php endforeach; ?>
            </ul>
          </div>
        <?php endif; ?>

        <div class="action-buttons ms-auto">
          <a class="btn btn-primary btn-sm"
             href="mant_equipos_update.php?id=<?= urlencode((string)$r['ID']) ?>"
             title="Registrar mantenimiento">
            <i class="fa fa-pen-to-square me-1"></i>Actualizar
          </a>
          <a class="btn btn-info btn-sm"
             href="mant_equipos_history.php?id=<?= urlencode((string)$r['ID']) ?>"
             title="Historial">
            <i class="fa fa-clock-rotate-left"></i>
          </a>
        </div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>

  <div class="d-flex justify-content-between align-items-center mt-3">
    <small class="text-secondary">Filtro y búsqueda local en el navegador.</small>
    <small id="countLabel" class="text-secondary"></small>
  </div>
</div>

<!-- Modal imagen -->
<div class="modal fade" id="imagePreviewModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content bg-dark">
      <div class="modal-header border-0">
        <h5 class="modal-title">Vista previa</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
      </div>
      <div class="modal-body d-flex justify-content-center">
        <img id="previewImage" src="" alt="Imagen" class="img-fluid rounded" style="max-height:75vh;">
      </div>
    </div>
  </div>
</div>

<script>
// Modal de imagen
const imgModal = document.getElementById('imagePreviewModal');
if (imgModal) {
  imgModal.addEventListener('show.bs.modal', (ev) => {
    const img = ev.relatedTarget;
    const src = img?.getAttribute('data-src') || img?.getAttribute('src');
    document.getElementById('previewImage').setAttribute('src', src || '');
  });
  imgModal.addEventListener('hidden.bs.modal', () => {
    document.getElementById('previewImage').setAttribute('src', '');
  });
}

// Búsqueda + filtros
const searchInput  = document.getElementById('searchInput');
const filterStatus = document.getElementById('filterStatus');
const filterPeriod = document.getElementById('filterPeriod');
const grid         = document.getElementById('mantGrid');
const countLabel   = document.getElementById('countLabel');

function applyFilters() {
  const term   = (searchInput?.value || '').toLowerCase().trim();
  const status = (filterStatus?.value || '').toLowerCase();
  const period = (filterPeriod?.value || '').toLowerCase();
  const cards  = grid ? grid.querySelectorAll('.mant-card') : [];
  let visible  = 0;

  cards.forEach(card => {
    const text   = card.textContent.toLowerCase();
    const cStat  = (card.dataset.status || '').toLowerCase();
    const cPer   = (card.dataset.period || '').toLowerCase();

    const matchTerm   = !term   || text.includes(term);
    const matchStatus = !status || cStat.includes(status);
    const matchPeriod = !period || cPer === period;

    if (matchTerm && matchStatus && matchPeriod) {
      card.style.display = '';
      visible++;
    } else {
      card.style.display = 'none';
    }
  });

  if (countLabel) {
    countLabel.textContent = visible + ' equipo' + (visible !== 1 ? 's' : '') + ' mostrado' + (visible !== 1 ? 's' : '');
  }

  let noMsg = document.getElementById('noResultsMsg');
  if (visible === 0) {
    if (!noMsg) {
      noMsg = document.createElement('div');
      noMsg.id = 'noResultsMsg';
      noMsg.className = 'alert alert-info mt-3';
      noMsg.innerHTML = '<i class="fa fa-info-circle me-2"></i>No se encontraron equipos con los filtros aplicados.';
      grid.parentNode.insertBefore(noMsg, grid.nextSibling);
    }
    noMsg.style.display = 'block';
  } else if (noMsg) {
    noMsg.style.display = 'none';
  }
}

[searchInput, filterStatus, filterPeriod].forEach(el => {
  if (el) el.addEventListener('input', applyFilters);
});
applyFilters();
</script>

<style>
/* ===== GRID ===== */
.mant-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
  gap: 1.5rem;
  margin-top: 1.5rem;
}

/* ===== CARD ===== */
.mant-card {
  background: linear-gradient(145deg, #1a1d23 0%, #2d3139 100%);
  border-radius: 16px;
  box-shadow: 0 4px 6px rgba(0,0,0,0.3), 0 1px 3px rgba(0,0,0,0.2);
  transition: transform 0.2s ease, box-shadow 0.2s ease;
  display: flex;
  flex-direction: column;
  border: 1px solid rgba(255,255,255,0.05);
  animation: cardFadeIn 0.4s ease-out;
}
.mant-card:hover {
  transform: translateY(-4px);
  box-shadow: 0 12px 20px rgba(0,0,0,0.4), 0 4px 8px rgba(0,0,0,0.3);
  border-color: rgba(255,255,255,0.1);
}

/* ===== HEADER IMAGEN ===== */
.card-header-img {
  position: relative;
  width: 100%;
  height: 200px;
  background: linear-gradient(135deg, #1a8a4a 0%, #0f5c31 100%);
  overflow: hidden;
  border-radius: 16px 16px 0 0;
}
.card-img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  cursor: pointer;
  transition: transform 0.3s ease;
}
.card-img:hover { transform: scale(1.05); }
.card-img-placeholder {
  width: 100%;
  height: 100%;
  display: flex;
  align-items: center;
  justify-content: center;
  background: linear-gradient(135deg, #1a3a2a 0%, #0d1f15 100%);
  color: #fff;
}
.card-status-overlay {
  position: absolute;
  top: 12px;
  right: 12px;
  z-index: 10;
}
.card-period-overlay {
  position: absolute;
  bottom: 12px;
  left: 12px;
  z-index: 10;
}

/* ===== BADGES ===== */
.badge-status {
  font-size: 0.85rem;
  padding: 0.5rem 0.85rem;
  border-radius: 20px;
  font-weight: 600;
  backdrop-filter: blur(10px);
  box-shadow: 0 2px 8px rgba(0,0,0,0.3);
}
.badge-ven  { background: linear-gradient(135deg, #dc3545 0%, #bd2130 100%); color: #fff; }
.badge-prox { background: linear-gradient(135deg, #ffc107 0%, #ff9800 100%); color: #000; }
.badge-cal  { background: linear-gradient(135deg, #28a745 0%, #20c997 100%); color: #fff; }

/* ===== BODY ===== */
.card-body-content { padding: 1.25rem; flex: 1; }
.card-title-row {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 0.75rem;
  margin-bottom: 1rem;
}
.card-title { font-size: 1.1rem; font-weight: 600; color: #fff; line-height: 1.3; margin: 0; flex: 1; }
.card-id    { font-size: 0.75rem; padding: 0.25rem 0.6rem; border-radius: 6px; font-family: 'Courier New', monospace; white-space: nowrap; }

/* ===== ESPECIFICACIONES ===== */
.card-specs {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
  margin-bottom: 1rem;
  padding: 0.75rem;
  background: rgba(0,0,0,0.2);
  border-radius: 8px;
}
.spec-item  { display: flex; align-items: center; gap: 0.5rem; font-size: 0.9rem; }
.spec-label { color: #adb5bd; min-width: 65px; }
.spec-value { color: #e9ecef; font-weight: 500; }

/* ===== FECHAS ===== */
.card-dates {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 0.75rem;
}
.date-item {
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
  padding: 0.6rem;
  background: rgba(0,0,0,0.3);
  border-radius: 8px;
  border-left: 3px solid rgba(255,255,255,0.2);
}
.date-label { font-size: 0.75rem; color: #adb5bd; text-transform: uppercase; letter-spacing: 0.5px; }
.date-value { font-size: 0.9rem; color: #fff; font-weight: 600; font-family: 'Courier New', monospace; }

/* ===== FOOTER ACCIONES ===== */
.card-footer-actions {
  padding: 1rem 1.25rem;
  background: rgba(0,0,0,0.2);
  border-top: 1px solid rgba(255,255,255,0.05);
  border-radius: 0 0 16px 16px;
  display: flex;
  align-items: center;
  gap: 0.75rem;
}
.action-buttons { display: flex; gap: 0.5rem; }
.card-footer-actions .btn { border-radius: 8px; font-weight: 500; transition: all 0.2s ease; }
.card-footer-actions .btn:hover { transform: translateY(-2px); box-shadow: 0 4px 8px rgba(0,0,0,0.3); }

/* ===== ANIMACIÓN ===== */
@keyframes cardFadeIn {
  from { opacity: 0; transform: translateY(20px); }
  to   { opacity: 1; transform: translateY(0); }
}

/* ===== RESPONSIVE ===== */
@media (max-width: 767px) {
  .mant-grid { grid-template-columns: 1fr; gap: 1rem; }
  .card-header-img { height: 180px; }
  .card-title { font-size: 1rem; }
  .card-dates { grid-template-columns: 1fr; }
  .action-buttons .btn { padding: 0.4rem 0.6rem; font-size: 0.85rem; }
  .dt-toolbar { flex-direction: column !important; gap: 0.75rem; }
}
@media (min-width: 768px) and (max-width: 991px) {
  .mant-grid { grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); }
}
@media (min-width: 1400px) {
  .mant-grid { grid-template-columns: repeat(auto-fill, minmax(350px, 1fr)); }
}
</style>

<?php include __DIR__.'/partials/footer.php'; ?>
