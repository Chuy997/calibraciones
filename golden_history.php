<?php
// /var/www/html/calibraciones/golden_history.php
declare(strict_types=1);

require_once __DIR__.'/config.php';
require_auth(['admin','ingenieria','golden_consulta']); // solo administradores y lectura

function h(?string $s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

// --- Validar ID ---
$id = $_GET['id'] ?? '';
if ($id === '' || !preg_match('/^[A-Za-z0-9._-]+$/', $id)) {
  http_response_code(400);
  exit('ID no proporcionado o inválido.');
}

// --- Traer historial ---
$sql = "
  SELECT
    GoldenID,
    Action,
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
    Comments,
    CreatedAt
  FROM golden_history
  WHERE GoldenID = :id
  ORDER BY CreatedAt DESC
";
$stmt = pdo()->prepare($sql);
$stmt->execute([':id' => $id]);
$rows = $stmt->fetchAll();

// Recolectar valores únicos para filtros (acción/estado)
$actions = [];
$statuses = [];
foreach ($rows as $r) {
  $a = (string)($r['Action'] ?? '');
  $s = (string)($r['Status'] ?? '');
  if ($a !== '' && !in_array($a, $actions, true)) $actions[] = $a;
  if ($s !== '' && !in_array($s, $statuses, true)) $statuses[] = $s;
}
sort($actions); sort($statuses);
?>
<?php include __DIR__.'/partials/header.php'; ?>

<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
  <div>
    <h1 class="h4 m-0">Golden – Historial</h1>
    <div class="text-secondary small mt-1">
      ID: <span class="text-light fw-semibold"><?= h($id) ?></span>
    </div>
  </div>
  <a href="golden_admin.php" class="btn btn-outline-secondary btn-sm">
    <i class="fa fa-arrow-left me-1"></i> Inventario
  </a>
</div>

<?php if ($rows): ?>
<div class="card p-3 table-density-comfort dt-container">
  <div class="dt-toolbar">
    <div class="input-group" style="max-width:320px;">
      <span class="input-group-text"><i class="fa fa-magnifying-glass"></i></span>
      <input type="text" class="form-control dt-search" placeholder="Buscar en historial…">
    </div>

    <select class="form-select dt-density" style="max-width:180px;">
      <option value="comfort">Densidad: cómoda</option>
      <option value="compact" selected>Densidad: compacta</option>
    </select>

    <select class="form-select dt-rows-per-page" style="max-width:160px;">
      <option value="10">10 por página</option>
      <option value="20">20 por página</option>
      <option value="50" selected>50 por página</option>
      <option value="100">100 por página</option>
    </select>

    <div class="dropdown">
      <button class="btn btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">Columnas</button>
      <div class="dropdown-menu dropdown-menu-dark p-2 colvis-menu">
        <?php
          $cols = ['Fecha','Acción','Descripción','Marca','Modelo','Serie','Ubicación','Depto','Responsable','Estado','Comentarios','Documento','Imagen'];
          foreach ($cols as $i=>$c): ?>
          <label class="dropdown-item d-flex align-items-center gap-2">
            <input class="form-check-input me-2" type="checkbox" data-col="<?= $i ?>" checked>
            <span><?= h($c) ?></span>
          </label>
        <?php endforeach; ?>
      </div>
    </div>

    <div class="ms-auto d-flex gap-2 flex-wrap">
      <select class="form-select dt-filter-action" style="max-width:200px;">
        <option value="">Acción: todas</option>
        <?php foreach ($actions as $a): ?>
          <option value="<?= h($a) ?>"><?= h(ucfirst($a)) ?></option>
        <?php endforeach; ?>
      </select>
      <select class="form-select dt-filter-status" style="max-width:200px;">
        <option value="">Estado: todos</option>
        <?php foreach ($statuses as $s): ?>
          <option value="<?= h($s) ?>"><?= h($s) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
  </div>

  <div class="table-wrap">
    <div class="table-scroll">
      <table class="table table-striped table-hover align-middle">
        <thead>
          <tr>
            <th class="th-sort" data-sort="date">Fecha <span class="sort-ind">▲▼</span></th>
            <th class="th-sort" data-sort="text">Acción <span class="sort-ind">▲▼</span></th>
            <th class="th-sort" data-sort="text">Descripción <span class="sort-ind">▲▼</span></th>
            <th class="th-sort" data-sort="text">Marca <span class="sort-ind">▲▼</span></th>
            <th class="th-sort" data-sort="text">Modelo <span class="sort-ind">▲▼</span></th>
            <th class="th-sort" data-sort="text">Serie <span class="sort-ind">▲▼</span></th>
            <th class="th-sort" data-sort="text">Ubicación <span class="sort-ind">▲▼</span></th>
            <th class="th-sort" data-sort="text">Depto <span class="sort-ind">▲▼</span></th>
            <th class="th-sort" data-sort="text">Responsable <span class="sort-ind">▲▼</span></th>
            <th class="th-sort" data-sort="text">Estado <span class="sort-ind">▲▼</span></th>
            <th>Comentarios</th>
            <th>Documento</th>
            <th>Imagen</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($rows as $r):
          $status = (string)($r['Status'] ?? '');
          $badge  = $status === 'Scrap' ? 'badge-ven' : 'badge-cal';
        ?>
          <tr>
            <td><?= h($r['CreatedAt']) ?></td>
            <td><?= h(ucfirst((string)$r['Action'])) ?></td>
            <td><?= h($r['Description']) ?></td>
            <td><?= h($r['Brand']) ?></td>
            <td><?= h($r['Model']) ?></td>
            <td><?= h($r['SerialNumber']) ?></td>
            <td><?= h($r['Location']) ?></td>
            <td><?= h($r['Department']) ?></td>
            <td><?= h($r['Owner']) ?></td>
            <td><span class="badge badge-state <?= $badge ?>"><?= h($status) ?></span></td>
            <td><?= h($r['Comments']) ?></td>
            <td class="text-center">
              <?php if (!empty($r['Document'])): ?>
                <a href="<?= h($r['Document']) ?>" target="_blank" class="btn btn-sm btn-outline-light" title="Ver documento PDF">
                  <i class="fa fa-file-pdf"></i>
                </a>
              <?php else: ?>—<?php endif; ?>
            </td>
            <td class="text-center">
              <?php if (!empty($r['Picture'])): ?>
                <img src="<?= h($r['Picture']) ?>" class="img-thumb" alt="img"
                     data-bs-toggle="modal" data-bs-target="#imagePreviewModal"
                     data-src="<?= h($r['Picture']) ?>">
              <?php else: ?>—<?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <div class="d-flex justify-content-between align-items-center mt-2">
    <small class="text-secondary">Búsqueda, orden y paginación en el navegador.</small>
    <div class="dt-pager"></div>
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

// Mini-datatable
(function(){
  const container = document.querySelector('.dt-container');
  if (!container) return;

  const table    = container.querySelector('table');
  const tbody    = table.tBodies[0];
  const search   = container.querySelector('.dt-search');
  const rowsSel  = container.querySelector('.dt-rows-per-page');
  const pagerEl  = container.querySelector('.dt-pager');
  const colvis   = container.querySelectorAll('.colvis-menu input[type="checkbox"]');
  const density  = container.querySelector('.dt-density');
  const fAction  = container.querySelector('.dt-filter-action');
  const fStatus  = container.querySelector('.dt-filter-status');

  // Orden
  let sortCol = 0, sortDir = 1;
  table.querySelectorAll('th.th-sort').forEach((th,idx)=>{
    th.addEventListener('click', ()=>{
      const type = th.dataset.sort || 'text';
      sortCol = idx; sortDir *= -1;
      const rows = Array.from(tbody.rows);
      rows.sort((a,b)=>{
        const A = a.cells[sortCol]?.innerText.trim() ?? '';
        const B = b.cells[sortCol]?.innerText.trim() ?? '';
        if (type==='num') return ((parseFloat(A)||0) - (parseFloat(B)||0)) * sortDir;
        if (type==='date') return ((new Date(A)) - (new Date(B))) * sortDir;
        return A.localeCompare(B, undefined, {numeric:true}) * sortDir;
      });
      rows.forEach(r=>tbody.appendChild(r));
      paginate();
    });
  });

  // Búsqueda + filtros (acción/estado)
  function applyFilters(){
    const q  = (search?.value || '').toLowerCase();
    const fa = (fAction?.value || '');
    const fs = (fStatus?.value || '');
    Array.from(tbody.rows).forEach(tr=>{
      const txt = tr.innerText.toLowerCase();
      const act = tr.cells[1]?.innerText.trim() || '';
      const est = tr.cells[9]?.innerText.trim() || '';
      const matchQ  = !q  || txt.includes(q);
      const matchA  = !fa || act === fa || act.toLowerCase() === fa.toLowerCase();
      const matchS  = !fs || est === fs || est.toLowerCase() === fs.toLowerCase();
      tr.style.display = (matchQ && matchA && matchS) ? '' : 'none';
    });
    paginate();
  }
  search?.addEventListener('input', applyFilters);
  fAction?.addEventListener('change', applyFilters);
  fStatus?.addEventListener('change', applyFilters);

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
    const per  = parseInt(rowsSel.value,10);
    const visi = Array.from(tbody.rows).filter(r=>r.style.display!=='none');
    const pages= Math.max(1, Math.ceil(visi.length/per));
    page = Math.min(page, pages);
    visi.forEach((tr,i)=> tr.style.display = (i>=(page-1)*per && i<page*per) ? '' : 'none');
    pagerEl.innerHTML = '';
    for (let p=1;p<=pages;p++){
      const btn = document.createElement('button');
      btn.className = 'btn btn-sm '+(p===page?'btn-primary':'btn-outline-secondary');
      btn.textContent = p;
      btn.addEventListener('click', ()=>{ page=p; paginate(); });
      pagerEl.appendChild(btn);
    }
  }
  rowsSel?.addEventListener('change', ()=>{ page=1; paginate(); });

  // Init
  applyFilters();
})();
</script>

<?php else: ?>
  <div class="card p-4">
    <div class="text-secondary">No hay historial para este material Golden.</div>
  </div>
<?php endif; ?>

<?php include __DIR__.'/partials/footer.php'; ?>
