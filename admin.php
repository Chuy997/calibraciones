<?php
// /var/www/html/calibraciones/admin.php
declare(strict_types=1);

require __DIR__.'/config.php';
require_auth('admin');

// Consulta
$sql = <<<SQL
SELECT 
    i.ID,
    i.Picture,
    i.Description,
    i.Brand,
    i.Model,
    i.SerialNumber,
    i.Location,
    i.CalDate,
    i.DueDate,
    i.PdfPath AS CurrentPdf,
    i.Comments,
    i.Status AS status_bd,
    CASE
        WHEN CURRENT_DATE() > i.DueDate THEN 'Vencido'
        WHEN i.DueDate BETWEEN CURRENT_DATE() AND DATE_ADD(CURRENT_DATE(), INTERVAL 30 DAY) THEN 'Próxima calibración'
        ELSE 'Calibrado'
    END AS status_calculado
FROM instruments i
ORDER BY i.ID
SQL;

$stmt = pdo()->query($sql);
$rows = $stmt->fetchAll();

// Obtener todo el historial de PDFs para el dropdown multipdf
$pdfSql = "
SELECT InstrumentID, PdfPath, MAX(UpdatedAt) as LastUpdate
FROM updatehistory
WHERE PdfPath IS NOT NULL AND PdfPath != ''
GROUP BY InstrumentID, PdfPath
ORDER BY LastUpdate DESC
";
$pdfStmt = pdo()->query($pdfSql);
$allPdfs = [];
foreach ($pdfStmt->fetchAll() as $p) {
    if (!isset($allPdfs[$p['InstrumentID']])) {
        $allPdfs[$p['InstrumentID']] = [];
    }
    $path = $p['PdfPath'];
    if (!str_starts_with($path, '/')) {
        $path = '/' . ltrim($path, '/');
    }
    $allPdfs[$p['InstrumentID']][] = [
        'path' => $path,
        'date' => substr((string)$p['LastUpdate'], 0, 10)
    ];
}

function h(?string $s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
?>
<?php include __DIR__.'/partials/header.php'; ?>

<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
  <h1 class="h4 m-0">Instrumentos de medición</h1>
  <a class="btn btn-success btn-lg" href="add.php"><i class="fa fa-plus me-2"></i>Nuevo</a>
</div>

<div class="card p-3 table-density-comfort dt-container">
  <div class="dt-toolbar">
    <!-- Búsqueda -->
    <div class="input-group mb-2 mb-md-0" style="max-width:320px;">
      <span class="input-group-text"><i class="fa fa-magnifying-glass"></i></span>
      <input type="text" id="searchInput" class="form-control form-control-lg" placeholder="Buscar…">
    </div>

    <!-- Controles secundarios: solo en pantallas medianas+ -->
    <div class="d-none d-md-flex gap-2">
      <select class="form-select dt-density" style="max-width:180px;">
        <option value="comfort">Densidad: cómoda</option>
        <option value="compact"selected>Densidad: compacta</option>
      </select>

      <select class="form-select dt-rows-per-page" style="max-width:160px;">
        <option value="10">10 por página</option>
        <option value="20">20 por página</option>
        <option value="50"selected>50 por página</option>
        <option value="100">100 por página</option>
      </select>

      <div class="dropdown">
        <button class="btn btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">
          Columnas
        </button>
        <div class="dropdown-menu dropdown-menu-dark p-2 colvis-menu">
          <?php
            $cols = ['ID','Foto','Descripción','Marca','Modelo','Serie','Cal Date','Due Date','Estado','PDF','Acciones'];
            foreach ($cols as $i=>$c):
          ?>
            <label class="dropdown-item d-flex align-items-center gap-2">
              <input class="form-check-input me-2" type="checkbox" data-col="<?= $i ?>" checked>
              <span><?= h($c) ?></span>
            </label>
          <?php endforeach; ?>
        </div>
      </div>
    </div>

    <!-- Acciones de exportación -->
    <div class="ms-auto d-flex align-items-center gap-2 flex-wrap">
      <a href="instruments_inventory_print.php" target="_blank" 
         class="btn btn-outline-secondary" title="Imprimir Inventario Actual">
          <i class="fa fa-print me-1 d-none d-sm-inline"></i><span class="d-none d-sm-inline">Listado</span><span class="d-sm-none">Print</span>
      </a>

      <div class="vr mx-2 d-none d-md-block"></div>

      <!-- Formulario de reportes -->
      <form class="d-flex align-items-center gap-2 flex-wrap" action="generate_report.php" method="get">
        <select name="month" class="form-select form-select-sm" required style="min-width:100px;">
          <?php for ($m=1; $m<=12; $m++): ?>
            <option value="<?= $m ?>"><?= date('M', mktime(0,0,0,$m,1)) ?></option>
          <?php endfor; ?>
        </select>
        <select name="year" class="form-select form-select-sm" required style="min-width:80px;">
          <?php for ($y=(int)date('Y'); $y >= (int)date('Y')-10; $y--): ?>
            <option value="<?= $y ?>"><?= $y ?></option>
          <?php endfor; ?>
        </select>
        <button class="btn btn-info btn-sm" type="submit">
          <i class="fa fa-file-arrow-down me-1"></i><span class="d-none d-sm-inline">Reporte</span>
        </button>
      </form>
    </div>
  </div>

  <!-- Grid de Cards Estilo App -->
  <div class="instruments-grid" id="instrumentsGrid">
    <?php foreach ($rows as $r):
      $statusBd  = (string)($r['status_bd'] ?? '');
      $estado    = (string)($r['status_calculado'] ?? '');
      // Lógica híbrida: solo 'en proceso' es manual; el resto se calcula por fechas
      if ($statusBd === 'en proceso de calibracion') {
        $badge       = 'badge-proc';
        $stateIcon   = 'fa-rotate';
        $estadoLabel = 'En proceso de cal.';
      } else {
        // Badge automático por fecha
        $autoMap = [
          'Vencido'             => ['badge'=>'badge-ven',  'icon'=>'fa-circle-xmark'],
          'Próxima calibración' => ['badge'=>'badge-prox', 'icon'=>'fa-clock'],
          'Calibrado'           => ['badge'=>'badge-cal',  'icon'=>'fa-circle-check'],
        ];
        $ai = $autoMap[$estado] ?? ['badge'=>'badge-cal', 'icon'=>'fa-circle-question'];
        $badge       = $ai['badge'];
        $stateIcon   = $ai['icon'];
        $estadoLabel = $estado;
      }
    ?>
    <div class="instrument-card" data-id="<?= h($r['ID']) ?>">
      <!-- Card Header con Imagen y Estado -->
      <div class="card-header-img">
        <?php if (!empty($r['Picture'])): ?>
          <img src="<?= h($r['Picture']) ?>" alt="<?= h($r['Description']) ?>"
               class="card-img"
               data-bs-toggle="modal" data-bs-target="#imagePreviewModal"
               data-src="<?= h($r['Picture']) ?>">
        <?php else: ?>
          <div class="card-img-placeholder">
            <i class="fa fa-microscope fa-3x opacity-25"></i>
          </div>
        <?php endif; ?>
        <div class="card-status-overlay">
          <span class="badge badge-status <?= $badge ?>">
            <i class="fa <?= $stateIcon ?> me-1"></i><?= h($estadoLabel) ?>
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
          <div class="spec-item">
            <i class="fa fa-trademark text-muted me-1"></i>
            <span class="spec-label">Marca:</span>
            <span class="spec-value"><?= h($r['Brand']) ?></span>
          </div>
          <div class="spec-item">
            <i class="fa fa-cube text-muted me-1"></i>
            <span class="spec-label">Modelo:</span>
            <span class="spec-value"><?= h($r['Model']) ?></span>
          </div>
          <div class="spec-item">
            <i class="fa fa-barcode text-muted me-1"></i>
            <span class="spec-label">Serie:</span>
            <span class="spec-value"><?= h($r['SerialNumber']) ?></span>
          </div>
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
            <span class="date-label">Calibrado:</span>
            <span class="date-value"><?= h($r['CalDate']) ?></span>
          </div>
          <div class="date-item">
            <i class="fa fa-calendar-days text-warning me-1"></i>
            <span class="date-label">Vence:</span>
            <span class="date-value"><?= h($r['DueDate']) ?></span>
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
                  array_unshift($pdfs, ['path' => $curr, 'date' => $r['CalDate']]);
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
        
        <div class="action-buttons ms-auto d-flex gap-1 align-items-center">
          <!-- Dropdown cambiar status -->
          <div class="dropdown">
            <button class="btn btn-sm btn-outline-light dropdown-toggle status-btn"
                    type="button" data-bs-toggle="dropdown" aria-expanded="false"
                    title="Cambiar estado">
              <i class="fa fa-sliders me-1"></i>Estado
            </button>
            <ul class="dropdown-menu dropdown-menu-dark shadow status-menu"
                data-id="<?= h((string)$r['ID']) ?>"
                data-csrf="<?= h(csrf_token()) ?>">
              <li><h6 class="dropdown-header">Cambiar estado a:</h6></li>
              <li>
                <button class="dropdown-item d-flex align-items-center gap-2 status-option"
                        data-val="calibrado">
                  <i class="fa fa-circle-check text-success"></i>Calibrado
                </button>
              </li>
              <li>
                <button class="dropdown-item d-flex align-items-center gap-2 status-option"
                        data-val="en proceso de calibracion">
                  <i class="fa fa-rotate text-warning"></i>En proceso de calibración
                </button>
              </li>
              <li>
                <button class="dropdown-item d-flex align-items-center gap-2 status-option"
                        data-val="fuera de calibracion">
                  <i class="fa fa-circle-xmark text-danger"></i>Fuera de calibración
                </button>
              </li>
            </ul>
          </div>
          <a class="btn btn-primary btn-sm" 
             href="update.php?id=<?= urlencode((string)$r['ID']) ?>" 
             title="Editar">
            <i class="fa fa-pen-to-square"></i>
          </a>
          <a class="btn btn-info btn-sm" 
             href="history.php?id=<?= urlencode((string)$r['ID']) ?>" 
             title="Historial">
            <i class="fa fa-clock-rotate-left"></i>
          </a>
          <a class="btn btn-warning btn-sm" 
             href="move_out_of_use.php?id=<?= urlencode((string)$r['ID']) ?>" 
             title="Fuera de uso">
            <i class="fa fa-triangle-exclamation"></i>
          </a>
        </div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>

  <div class="d-flex justify-content-between align-items-center mt-2">
    <small class="text-secondary">Mostrando tabla local (orden, búsqueda y paginación en el navegador).</small>
    <div class="dt-pager"></div>
  </div>
</div>

<!-- Modal imagen (igual que en history.php) -->
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

// Funcionalidad de búsqueda/filtro para cards
const searchInput = document.getElementById('searchInput');
const cardsGrid = document.getElementById('instrumentsGrid');
const allCards = cardsGrid ? cardsGrid.querySelectorAll('.instrument-card') : [];

if (searchInput && allCards.length > 0) {
  searchInput.addEventListener('input', (e) => {
    const searchTerm = e.target.value.toLowerCase().trim();
    let visibleCount = 0;
    allCards.forEach(card => {
      const cardText = card.textContent.toLowerCase();
      if (searchTerm === '' || cardText.includes(searchTerm)) {
        card.style.display = '';
        visibleCount++;
      } else {
        card.style.display = 'none';
      }
    });
    let noResultsMsg = document.getElementById('noResultsMessage');
    if (visibleCount === 0 && searchTerm !== '') {
      if (!noResultsMsg) {
        noResultsMsg = document.createElement('div');
        noResultsMsg.id = 'noResultsMessage';
        noResultsMsg.className = 'alert alert-info mt-3';
        noResultsMsg.innerHTML = '<i class="fa fa-info-circle me-2"></i>No se encontraron instrumentos que coincidan con tu búsqueda.';
        cardsGrid.parentNode.insertBefore(noResultsMsg, cardsGrid.nextSibling);
      }
      noResultsMsg.style.display = 'block';
    } else if (noResultsMsg) {
      noResultsMsg.style.display = 'none';
    }
  });
}

// ===== CAMBIO DE STATUS VÍA AJAX =====
const STATUS_LABELS = {
  'calibrado':                 { label: 'Calibrado',               badge: 'badge-cal',  icon: 'fa-circle-check' },
  'fuera de calibracion':      { label: 'Fuera de calibración',    badge: 'badge-ven',  icon: 'fa-circle-xmark' },
  'en proceso de calibracion': { label: 'En proceso de cal.',      badge: 'badge-proc', icon: 'fa-rotate' },
};

document.addEventListener('click', async (e) => {
  const btn = e.target.closest('.status-option');
  if (!btn) return;

  const menu   = btn.closest('.status-menu');
  const card   = btn.closest('.instrument-card');
  const id     = menu?.dataset.id;
  const csrf   = menu?.dataset.csrf;
  const status = btn.dataset.val;

  if (!id || !status) return;

  // Deshabilitar el botón mientras se procesa
  btn.disabled = true;
  btn.innerHTML = '<i class="fa fa-spinner fa-spin me-2"></i>Guardando…';

  try {
    const body = new URLSearchParams({ id, status, csrf });
    const res  = await fetch('update_status.php', { method: 'POST', body });
    const data = await res.json();

    if (data.ok) {
      // Actualizar badge en la card
      const info = STATUS_LABELS[status] ?? { label: status, badge: 'badge-cal', icon: 'fa-circle-question' };
      const badgeEl = card?.querySelector('.badge-status');
      if (badgeEl) {
        // Remover clases de color anteriores
        badgeEl.classList.remove('badge-cal', 'badge-ven', 'badge-proc', 'badge-prox');
        badgeEl.classList.add(info.badge);
        badgeEl.innerHTML = `<i class="fa ${info.icon} me-1"></i>${info.label}`;
      }
      // Toast de éxito
      showStatusToast('Estado actualizado: ' + info.label, 'success');
    } else {
      showStatusToast('Error: ' + (data.error ?? 'Inténtalo de nuevo.'), 'danger');
    }
  } catch (err) {
    showStatusToast('Error de red. Inténtalo de nuevo.', 'danger');
  } finally {
    // Restaurar botón
    btn.disabled = false;
    const valInfo = STATUS_LABELS[status] ?? { label: status, icon: 'fa-circle-question' };
    btn.innerHTML = `<i class="fa ${valInfo.icon}"></i>${valInfo.label}`;
    // Cerrar el dropdown
    const ddEl = menu?.closest('.dropdown');
    if (ddEl) bootstrap.Dropdown.getInstance(ddEl.querySelector('[data-bs-toggle="dropdown"]'))?.hide();
  }
});

function showStatusToast(msg, type) {
  let container = document.getElementById('toastContainer');
  if (!container) {
    container = document.createElement('div');
    container.id = 'toastContainer';
    container.style.cssText = 'position:fixed;bottom:1.5rem;right:1.5rem;z-index:9999;display:flex;flex-direction:column;gap:.5rem;';
    document.body.appendChild(container);
  }
  const toast = document.createElement('div');
  toast.className = `alert alert-${type} shadow d-flex align-items-center gap-2 py-2 px-3 mb-0`;
  toast.style.cssText = 'min-width:240px;border-radius:10px;font-size:.9rem;animation:fadeInUp .3s ease;';
  toast.innerHTML = `<i class="fa fa-${type==='success'?'circle-check':'circle-xmark'}"></i>${msg}`;
  container.appendChild(toast);
  setTimeout(() => toast.remove(), 3500);
}
</script>

<!-- Estilos para diseño de Cards estilo App -->
<style>
/* ===== GRID DE CARDS ===== */
.instruments-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
  gap: 1.5rem;
  margin-top: 1.5rem;
}

/* ===== CARD PRINCIPAL ===== */
.instrument-card {
  background: linear-gradient(145deg, #1a1d23 0%, #2d3139 100%);
  border-radius: 16px;
  box-shadow: 0 4px 6px rgba(0, 0, 0, 0.3),
              0 1px 3px rgba(0, 0, 0, 0.2);
  transition: transform 0.2s ease, box-shadow 0.2s ease;
  display: flex;
  flex-direction: column;
  border: 1px solid rgba(255, 255, 255, 0.05);
}

.instrument-card:hover {
  transform: translateY(-4px);
  box-shadow: 0 12px 20px rgba(0, 0, 0, 0.4),
              0 4px 8px rgba(0, 0, 0, 0.3);
  border-color: rgba(255, 255, 255, 0.1);
}

/* ===== HEADER CON IMAGEN ===== */
.card-header-img {
  position: relative;
  width: 100%;
  height: 200px;
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
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

.card-img:hover {
  transform: scale(1.05);
}

.card-img-placeholder {
  width: 100%;
  height: 100%;
  display: flex;
  align-items: center;
  justify-content: center;
  background: linear-gradient(135deg, #434343 0%, #000000 100%);
  color: #fff;
}

.card-status-overlay {
  position: absolute;
  top: 12px;
  right: 12px;
  z-index: 10;
}

.badge-status {
  font-size: 0.85rem;
  padding: 0.5rem 0.85rem;
  border-radius: 20px;
  font-weight: 600;
  backdrop-filter: blur(10px);
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.3);
}

/* ===== BODY DEL CARD ===== */
.card-body-content {
  padding: 1.25rem;
  flex: 1;
}

.card-title-row {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 0.75rem;
  margin-bottom: 1rem;
}

.card-title {
  font-size: 1.1rem;
  font-weight: 600;
  color: #fff;
  line-height: 1.3;
  margin: 0;
  flex: 1;
}

.card-id {
  font-size: 0.75rem;
  padding: 0.25rem 0.6rem;
  border-radius: 6px;
  font-family: 'Courier New', monospace;
  white-space: nowrap;
}

/* ===== ESPECIFICACIONES ===== */
.card-specs {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
  margin-bottom: 1rem;
  padding: 0.75rem;
  background: rgba(0, 0, 0, 0.2);
  border-radius: 8px;
}

.spec-item {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  font-size: 0.9rem;
}

.spec-label {
  color: #adb5bd;
  min-width: 60px;
}

.spec-value {
  color: #e9ecef;
  font-weight: 500;
}

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
  background: rgba(0, 0, 0, 0.3);
  border-radius: 8px;
  border-left: 3px solid rgba(255, 255, 255, 0.2);
}

.date-label {
  font-size: 0.75rem;
  color: #adb5bd;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.date-value {
  font-size: 0.9rem;
  color: #fff;
  font-weight: 600;
  font-family: 'Courier New', monospace;
}

/* ===== FOOTER ACCIONES ===== */
.card-footer-actions {
  padding: 1rem 1.25rem;
  background: rgba(0, 0, 0, 0.2);
  border-top: 1px solid rgba(255, 255, 255, 0.05);
  border-radius: 0 0 16px 16px;
  display: flex;
  align-items: center;
  gap: 0.75rem;
}

.action-buttons {
  display: flex;
  gap: 0.5rem;
}

.card-footer-actions .btn {
  border-radius: 8px;
  font-weight: 500;
  transition: all 0.2s ease;
}

.card-footer-actions .btn:hover {
  transform: translateY(-2px);
  box-shadow: 0 4px 8px rgba(0, 0, 0, 0.3);
}

/* ===== BADGES DE ESTADO ===== */
.badge-ven {
  background: linear-gradient(135deg, #dc3545 0%, #bd2130 100%);
  color: #fff;
}

.badge-prox {
  background: linear-gradient(135deg, #ffc107 0%, #ff9800 100%);
  color: #000;
}

.badge-cal {
  background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
  color: #fff;
}

.badge-proc {
  background: linear-gradient(135deg, #6f42c1 0%, #a855f7 100%);
  color: #fff;
}

/* ===== RESPONSIVE MOBILE ===== */
@media (max-width: 767px) {
  .instruments-grid {
    grid-template-columns: 1fr;
    gap: 1rem;
  }
  
  .card-header-img {
    height: 180px;
  }
  
  .card-title {
    font-size: 1rem;
  }
  
  .card-dates {
    grid-template-columns: 1fr;
  }
  
  .action-buttons .btn {
    padding: 0.4rem 0.6rem;
    font-size: 0.85rem;
  }
}

/* ===== TABLETS ===== */
@media (min-width: 768px) and (max-width: 991px) {
  .instruments-grid {
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
  }
}

/* ===== DESKTOP GRANDE ===== */
@media (min-width: 1400px) {
  .instruments-grid {
    grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
  }
}

/* ===== ANIMACIONES ===== */
@keyframes cardFadeIn {
  from {
    opacity: 0;
    transform: translateY(20px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

.instrument-card {
  animation: cardFadeIn 0.4s ease-out;
}

/* ===== ESTILOS ORIGINALES PRESERVADOS ===== */
<style>
/* Mejoras generales para móvil */
@media (max-width: 767px) {
  /* Toolbar responsive */
  .dt-toolbar {
    flex-direction: column !important;
    gap: 0.75rem;
  }
  
  .dt-toolbar > * {
    width: 100%;
    max-width: 100% !important;
  }
  
  .dt-toolbar .input-group {
    max-width: 100% !important;
  }
  
  /* Inputs más grandes para touch */
  .form-control,
  .form-select {
    min-height: 44px;
    font-size: 16px; /* Evitar zoom en iOS */
  }
  
  .form-control-lg {
    min-height: 48px;
  }
  
  /* Botones más grandes */
  .btn-lg {
    min-height: 48px;
    padding: 0.75rem 1.25rem;
  }
  
  .btn, .btn-sm {
    min-height: 38px;
    padding: 0.5rem 0.75rem;
  }
  
  /* Tabla con scroll horizontal suave */
  .table-scroll {
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
  }
  
  /* Asegurar que la tabla no se comprima */
  #instrumentsTable {
    min-width: 800px;
  }
  
  /* Mejorar espaciado en celdas */
  #instrumentsTable td,
  #instrumentsTable th {
    padding: 0.75rem 0.5rem;
    white-space: nowrap;
  }
  
  /* Botones de acción en grupo */
  .btn-group .btn {
    padding: 0.4rem 0.6rem;
  }
  
  /* Imagen thumbnail más grande en móvil para mejor visualización */
  .img-thumb {
    width: 50px;
    height: 50px;
  }
  
  /* Cards con mejor padding */
  .card {
    padding: 1rem !important;
  }
  
  /* Paginador responsive */
  .dt-pager {
    flex-wrap: wrap;
    gap: 0.5rem;
  }
}

/* Mejoras para tablets (768px - 991px) */
@media (min-width: 768px) and (max-width: 991px) {
  .dt-toolbar {
    flex-wrap: wrap;
  }
  
  .dt-toolbar .input-group {
    flex: 1 1 auto;
  }
  
  /* Tabla más espaciosa en tablet */
  #instrumentsTable {
    font-size: 0.95rem;
  }
}

/* Mejoras generales para touch devices */
@media (hover: none) and (pointer: coarse) {
  /* Aumentar área de toque de botones */
  .btn {
    min-height: 44px;
  }
  
  /* Mejorar hit area de checkboxes */
  .form-check-input {
    width: 1.25rem;
    height: 1.25rem;
  }
  
  /* Links y botones con espaciado */
  a, button {
    padding: 0.25rem;
  }
}

/* Scroll horizontal indicator para tablas */
.table-scroll {
  position: relative;
}

.table-scroll::after {
  content: '';
  position: absolute;
  top: 0;
  right: 0;
  bottom: 0;
  width: 30px;
  background: linear-gradient(to left, rgba(0,0,0,0.3), transparent);
  pointer-events: none;
  opacity: 0;
  transition: opacity 0.3s;
}

@media (max-width: 767px) {
  .table-scroll::after {
    opacity: 1;
  }
}

/* Mejorar badges en móvil */
.badge-state {
  font-size: 0.8rem;
  padding: 0.35rem 0.65rem;
  white-space: nowrap;
}

/* Botones de grupo más espaciados en móvil */
@media (max-width: 767px) {
  .btn-group {
    display: flex;
    flex-wrap: nowrap;
  }
  
  .btn-group .btn {
    flex: 1;
  }
}

/* Modal responsivo */
@media (max-width: 575px) {
  .modal-dialog {
    margin: 0.5rem;
  }
  
  .modal-lg {
    max-width: calc(100% - 1rem);
  }
}

/* Mejorar contraste de inputs en dark mode */
.form-control,
.form-select {
  background-color: #212529;
  border-color: #495057;
}

.form-control:focus,
.form-select:focus {
  background-color: #2c3034;
  border-color: #0d6efd;
}

/* Mejorar visibilidad de iconos en botones pequeños */
.btn-sm i {
  font-size: 0.9rem;
}
</style>

<?php include __DIR__.'/partials/footer.php'; ?>
