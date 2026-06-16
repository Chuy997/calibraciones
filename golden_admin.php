<?php
// /var/www/html/calibraciones/golden_admin.php
declare(strict_types=1);

require_once __DIR__.'/config.php';
require_auth(['admin','ingenieria','golden_consulta']); // administradores y consulta
$isGoldenConsulta = ($_SESSION['role'] ?? '') === 'golden_consulta';

function h(?string $s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

// Consulta básica de inventario Golden (sin tocar BD)
$sql = <<<SQL
SELECT
  ID,
  Description,
  Brand,
  Model,
  SerialNumber,
  Location,
  Department,
  Owner,
  Status,
  Picture,
  Document,
  Pedimento,
  Comments,
  CreatedAt,
  UpdatedAt
FROM golden_items
WHERE Status NOT IN ('Scrap', 'Destruido')
ORDER BY ID ASC
SQL;

$rows = pdo()->query($sql)->fetchAll();
?>
<?php include __DIR__.'/partials/header.php'; ?>

<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
  <h1 class="h4 m-0">Golden – Inventario</h1>
  <?php if (!$isGoldenConsulta): ?>
  <div class="d-flex gap-2">
    <a class="btn btn-primary" href="golden_audit.php"><i class="fa fa-clipboard-check me-1"></i> Auditar</a>
    <a class="btn btn-info text-white" href="golden_packages.php"><i class="fa fa-box-open me-1"></i> Paquetes</a>
    <a class="btn btn-success" href="golden_add.php"><i class="fa fa-plus me-1"></i> Nuevo</a>
    <a class="btn btn-danger" href="golden_scrap_session.php"><i class="fa fa-boxes-packing me-1"></i> Scrap Lotes</a>
  </div>
  <?php endif; ?>
</div>

<div class="card p-3 table-density-comfort dt-container">
  <div class="dt-toolbar">
    <div class="input-group" style="max-width:320px;">
      <span class="input-group-text"><i class="fa fa-magnifying-glass"></i></span>
      <input type="text" class="form-control dt-search" placeholder="Buscar…">
    </div>

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
      <button class="btn btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">Columnas</button>
      <div class="dropdown-menu dropdown-menu-dark p-2 colvis-menu">
        <?php
          $cols = [
            'ID','Foto','Descripción','Marca','Modelo','Serie',
            'Ubicación','Depto','Responsable','Estado','Documento','Pedimento','Acciones'
          ];
          foreach ($cols as $i=>$c): ?>
          <label class="dropdown-item d-flex align-items-center gap-2">
            <input class="form-check-input me-2" type="checkbox" data-col="<?= $i ?>" checked>
            <span><?= h($c) ?></span>
          </label>
        <?php endforeach; ?>
      </div>
    </div>

    <div class="ms-auto d-flex gap-2">
      <div class="dropdown">
        <button class="btn btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
          <i class="fa fa-download"></i> Exportar
        </button>
        <ul class="dropdown-menu dropdown-menu-dark p-2">
          <li>
            <a class="dropdown-item d-flex align-items-center gap-2" href="golden_export.php">
              <i class="fa fa-file-excel text-success"></i> <span>Excel (CSV)</span>
            </a>
          </li>
          <li>
            <a class="dropdown-item d-flex align-items-center gap-2" href="golden_inventory_print.php" target="_blank">
              <i class="fa fa-print text-white"></i> <span>Imprimir / PDF</span>
            </a>
          </li>
        </ul>
      </div>
      <select class="form-select dt-filter" data-col="9" style="max-width:220px;">
        <option value="">Estado: todos</option>
        <option>Activo</option>
      </select>
    </div>
  </div>

  <div class="table-wrap d-none d-md-block">
    <div class="table-scroll">
      <table class="table table-striped table-hover align-middle" id="goldenTable">
        <thead>
          <tr>
            <th class="th-sort" data-sort="text">ID <span class="sort-ind">▲▼</span></th>
            <th>Foto</th>
            <th class="th-sort" data-sort="text">Descripción <span class="sort-ind">▲▼</span></th>
            <th class="th-sort" data-sort="text">Marca <span class="sort-ind">▲▼</span></th>
            <th class="th-sort" data-sort="text">Modelo <span class="sort-ind">▲▼</span></th>
            <th class="th-sort" data-sort="text">Serie <span class="sort-ind">▲▼</span></th>
            <th class="th-sort" data-sort="text">Ubicación <span class="sort-ind">▲▼</span></th>
            <th class="th-sort" data-sort="text">Depto <span class="sort-ind">▲▼</span></th>
            <th class="th-sort" data-sort="text">Responsable <span class="sort-ind">▲▼</span></th>
            <th class="th-sort" data-sort="text">Estado <span class="sort-ind">▲▼</span></th>
            <th>Documento</th>
            <th class="th-sort" data-sort="text">Pedimento <span class="sort-ind">▲▼</span></th>
            <th>Acciones</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($rows as $r):
          $status = (string)($r['Status'] ?? '');
          $badge = $status === 'Scrap' ? 'badge-ven' : 'badge-cal'; // reuso estilos (rojo para Scrap / verde para Activo)
        ?>
          <tr>
            <td><?= h($r['ID']) ?></td>
            <td class="text-center">
              <?php if (!empty($r['Picture'])): ?>
                <!-- Miniatura con modal (igual a history) -->
                <img src="<?= h($r['Picture']) ?>" class="img-thumb" alt="img"
                     data-bs-toggle="modal" data-bs-target="#imagePreviewModal"
                     data-src="<?= h($r['Picture']) ?>">
              <?php else: ?>—<?php endif; ?>
            </td>
            <td><?= h($r['Description']) ?></td>
            <td><?= h($r['Brand']) ?></td>
            <td><?= h($r['Model']) ?></td>
            <td><?= h($r['SerialNumber']) ?></td>
            <td><?= h($r['Location']) ?></td>
            <td><?= h($r['Department']) ?></td>
            <td><?= h($r['Owner']) ?></td>
            <td><span class="badge badge-state <?= $badge ?>"><?= h($status) ?></span></td>
            <td class="text-center">
              <?php if (!empty($r['Document'])): ?>
                <a href="<?= h($r['Document']) ?>" target="_blank" class="btn btn-sm btn-outline-light" title="Ver documento PDF">
                  <i class="fa fa-file-pdf"></i>
                </a>
              <?php else: ?>—<?php endif; ?>
            </td>
            <td><?= h($r['Pedimento']) ?></td>
            <td>
              <div class="btn-group">
                <?php if (!$isGoldenConsulta): ?>
                <a class="btn btn-primary btn-sm" href="golden_update.php?id=<?= urlencode((string)$r['ID']) ?>" title="Editar">
                  <i class="fa fa-pen-to-square"></i>
                </a>
                <?php endif; ?>
                <a class="btn btn-info btn-sm" href="golden_history.php?id=<?= urlencode((string)$r['ID']) ?>" title="Historial">
                  <i class="fa fa-clock-rotate-left"></i>
                </a>
                <?php if (!$isGoldenConsulta): ?>
                <?php if ($status !== 'Scrap'): ?>
                  <a class="btn btn-warning btn-sm" href="golden_scrap.php?id=<?= urlencode((string)$r['ID']) ?>" title="Enviar a Scrap">
                    <i class="fa fa-triangle-exclamation"></i>
                  </a>
                <?php else: ?>
                  <button class="btn btn-secondary btn-sm" disabled title="Ya en Scrap">
                    <i class="fa fa-ban"></i>
                  </button>
                <?php endif; ?>
                <?php endif; ?>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Vista de Cards Móvil (solo visible en dispositivos móviles) -->
  <div class="instruments-grid d-block d-md-none" id="instrumentsGridMobile">
    <?php foreach ($rows as $r):
      $status = (string)($r['Status'] ?? '');
      $badge = $status === 'Scrap' ? 'badge-ven' : 'badge-cal';
      $stateIcon = $status === 'Scrap' ? 'fa-triangle-exclamation' : 'fa-circle-check';
    ?>
    <div class="instrument-card" data-id="<?= h($r['ID']) ?>">
      <div class="card-header-img">
        <?php if (!empty($r['Picture'])): ?>
          <img src="<?= h($r['Picture']) ?>" alt="<?= h($r['Description']) ?>" class="card-img" data-bs-toggle="modal" data-bs-target="#imagePreviewModal" data-src="<?= h($r['Picture']) ?>">
        <?php else: ?>
          <div class="card-img-placeholder">
            <i class="fa fa-microscope fa-3x opacity-25"></i>
          </div>
        <?php endif; ?>
        <div class="card-status-overlay">
          <span class="badge badge-status <?= $badge ?>">
            <i class="fa <?= $stateIcon ?> me-1"></i><?= h($status) ?>
          </span>
        </div>
      </div>
      
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
          <?php if (!empty($r['Department'])): ?>
          <div class="spec-item">
            <i class="fa fa-building text-info me-1"></i>
            <span class="spec-label">Depto:</span>
            <span class="spec-value"><?= h($r['Department']) ?></span>
          </div>
          <?php endif; ?>
          <?php if (!empty($r['Owner'])): ?>
          <div class="spec-item">
            <i class="fa fa-user text-info me-1"></i>
            <span class="spec-label">Resp:</span>
            <span class="spec-value"><?= h($r['Owner']) ?></span>
          </div>
          <?php endif; ?>
        </div>
      </div>
      
      <div class="card-footer-actions">
        <?php if (!empty($r['Document'])): ?>
          <a href="<?= h($r['Document']) ?>" target="_blank" class="btn btn-sm btn-outline-light" title="Ver documento PDF">
            <i class="fa fa-file-pdf"></i> PDF
          </a>
        <?php endif; ?>
        
        <div class="action-buttons ms-auto">
          <?php if (!$isGoldenConsulta): ?>
          <a class="btn btn-primary btn-sm" href="golden_update.php?id=<?= urlencode((string)$r['ID']) ?>" title="Editar">
            <i class="fa fa-pen-to-square"></i>
          </a>
          <?php endif; ?>
          <a class="btn btn-info btn-sm" href="golden_history.php?id=<?= urlencode((string)$r['ID']) ?>" title="Historial">
            <i class="fa fa-clock-rotate-left"></i>
          </a>
          <?php if (!$isGoldenConsulta): ?>
          <?php if ($status !== 'Scrap'): ?>
            <a class="btn btn-warning btn-sm" href="golden_scrap.php?id=<?= urlencode((string)$r['ID']) ?>" title="Enviar a Scrap">
              <i class="fa fa-triangle-exclamation"></i>
            </a>
          <?php else: ?>
            <button class="btn btn-secondary btn-sm" disabled title="Ya en Scrap">
              <i class="fa fa-ban"></i>
            </button>
          <?php endif; ?>
          <?php endif; ?>
        </div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>

  <div class="d-flex justify-content-between align-items-center mt-2">
    <small class="text-secondary">Búsqueda, orden y paginación en el navegador.</small>
    <div class="dt-pager"></div>
  </div>
</div>

<!-- Modal imagen (reutiliza el de history) -->
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
// Modal de imagen (igual a history)
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

// Mini-datatable
(function(){
  const container = document.querySelector('.dt-container');
  if (!container) return;
  const table   = container.querySelector('table');
  const tbody   = table.tBodies[0];
  const search  = container.querySelector('.dt-search');
  const rowsSel = container.querySelector('.dt-rows-per-page');
  const pagerEl = container.querySelector('.dt-pager');
  const colvis  = container.querySelectorAll('.colvis-menu input[type="checkbox"]');
  const filter  = container.querySelector('.dt-filter');
  const density = container.querySelector('.dt-density');

  // Orden
  let sortCol = 0, sortDir = 1;
  table.querySelectorAll('th.th-sort').forEach((th,idx)=>{
    th.addEventListener('click', ()=>{
      const type = th.dataset.sort || 'text';
      sortCol = idx; sortDir *= -1;
      const rows = Array.from(tbody.rows);
      rows.sort((a,b)=>{
        const A = a.cells[sortCol].innerText.trim();
        const B = b.cells[sortCol].innerText.trim();
        if (type==='num') return (parseFloat(A)||0 - (parseFloat(B)||0))*sortDir;
        if (type==='date') return (new Date(A) - new Date(B))*sortDir;
        return A.localeCompare(B, undefined, {numeric:true}) * sortDir;
      });
      rows.forEach(r=>tbody.appendChild(r));
    });
  });

  // Búsqueda + filtro por estado
  function applyFilters(){
    const q = (search?.value || '').toLowerCase();
    const f = (filter?.value || '');

    // Filtro para la tabla
    Array.from(tbody.rows).forEach(tr=>{
      const matchText = tr.innerText.toLowerCase().includes(q);
      const estado = tr.cells[9]?.innerText.trim() || '';
      const matchEstado = !f || estado === f;
      tr.dataset.filtered = (matchText && matchEstado) ? '0' : '1';
    });

    // Filtro para cards móviles
    const mobileCards = container.querySelectorAll('.instrument-card');
    mobileCards.forEach(card => {
      const matchText = card.innerText.toLowerCase().includes(q);
      const estadoBadge = card.querySelector('.badge-status');
      const estado = estadoBadge ? estadoBadge.innerText.trim() : '';
      const matchEstado = !f || estado === f;
      card.dataset.filtered = (matchText && matchEstado) ? '0' : '1';
    });

    page = 1;
    paginate();
  }
  search?.addEventListener('input', applyFilters);
  filter?.addEventListener('change', applyFilters);

  // Visibilidad de columnas
  colvis.forEach(chk=>{
    chk.addEventListener('change', ()=>{
      const col = parseInt(chk.dataset.col,10);
      table.querySelectorAll(`thead th:nth-child(${col+1}), tbody td:nth-child(${col+1})`)
           .forEach(el=>el.style.display = chk.checked ? '' : 'none');
    });
  });

  // Densidad
  density?.addEventListener('change', ()=>{
    const val = density.value; // comfort | compact
    container.classList.toggle('table-density-compact', val === 'compact');
    container.classList.toggle('table-density-comfort', val !== 'compact');
  });

  // Paginación
  let page = 1;
  function paginate(){
    const per  = parseInt(rowsSel.value, 10);
    
    // Paginación Tabla
    const allRows = Array.from(tbody.rows);
    const visiRows = allRows.filter(r => r.dataset.filtered !== '1');
    const pages = Math.max(1, Math.ceil(visiRows.length / per));
    page = Math.min(page, pages);
    
    allRows.forEach(tr => tr.style.display = 'none');
    visiRows.forEach((tr, i) => {
      if (i >= (page - 1) * per && i < page * per) tr.style.display = '';
    });

    // Paginación Cards
    const allCards = Array.from(container.querySelectorAll('.instrument-card'));
    const visiCards = allCards.filter(c => c.dataset.filtered !== '1');
    allCards.forEach(c => c.style.display = 'none');
    visiCards.forEach((c, i) => {
      if (i >= (page - 1) * per && i < page * per) c.style.display = '';
    });

    pagerEl.innerHTML = '';
    for (let p=1; p<=pages; p++){
      const btn = document.createElement('button');
      btn.className = 'btn btn-sm '+(p===page?'btn-primary':'btn-outline-secondary');
      btn.textContent = p;
      btn.addEventListener('click', ()=>{ page=p; paginate(); });
      pagerEl.appendChild(btn);
    }
  }
  rowsSel?.addEventListener('change', ()=>{ page=1; paginate(); });

  applyFilters();
})();
</script>

<style>
/* ===== GRID DE CARDS (MÓVIL) ===== */
.instruments-grid {
  display: grid;
  grid-template-columns: 1fr;
  gap: 1.5rem;
  margin-top: 1.5rem;
}

@media (min-width: 768px) {
  .instruments-grid {
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
  }
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

/* ===== BADGES ===== */
.badge-ven { background: linear-gradient(135deg, #dc3545 0%, #bd2130 100%); color: #fff; }
.badge-cal { background: linear-gradient(135deg, #28a745 0%, #20c997 100%); color: #fff; }

@media (max-width: 767px) {
  .instruments-grid { gap: 1rem; }
  .card-header-img { height: 180px; }
  .card-title { font-size: 1rem; }
  .action-buttons .btn { padding: 0.4rem 0.6rem; font-size: 0.85rem; }
}
</style>

<?php include __DIR__.'/partials/footer.php'; ?>
