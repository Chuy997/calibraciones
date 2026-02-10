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
    i.CalDate,
    i.DueDate,
    (
        SELECT uh.PdfPath
        FROM updatehistory uh
        WHERE uh.InstrumentID = i.ID
          AND uh.PdfPath IS NOT NULL
        ORDER BY uh.UpdatedAt DESC
        LIMIT 1
    ) AS LastPdfPath,
    i.Comments,
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
      $estado = $r['status_calculado'] ?? '';
      $badge = $estado==='Vencido' ? 'badge-ven' : ($estado==='Próxima calibración' ? 'badge-prox' : 'badge-cal');
      $stateIcon = $estado==='Vencido' ? 'fa-circle-xmark' : ($estado==='Próxima calibración' ? 'fa-clock' : 'fa-circle-check');
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
            <i class="fa <?= $stateIcon ?> me-1"></i><?= h($estado) ?>
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
        <?php if (!empty($r['LastPdfPath'])): ?>
          <a href="<?= h($r['LastPdfPath']) ?>" target="_blank" 
             class="btn btn-sm btn-outline-light" title="Ver PDF">
            <i class="fa fa-file-pdf me-1"></i>PDF
          </a>
        <?php endif; ?>
        
        <div class="action-buttons ms-auto">
          <a class="btn btn-primary btn-sm" 
             href="update.php?id=<?= urlencode((string)$r['ID']) ?>" 
             title="Editar">
            <i class="fa fa-pen-to-square me-1"></i>Editar
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
      // Buscar en todo el texto de la card
      const cardText = card.textContent.toLowerCase();
      
      if (searchTerm === '' || cardText.includes(searchTerm)) {
        card.style.display = '';
        visibleCount++;
      } else {
        card.style.display = 'none';
      }
    });
    
    // Mostrar mensaje si no hay resultados
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
  overflow: hidden;
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
