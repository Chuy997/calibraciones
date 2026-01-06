<?php
// /var/www/html/calibraciones/assets_hw_view.php
declare(strict_types=1);

require_once __DIR__.'/config.php';
require_auth(); // cualquier usuario (admin o consulta)

function h(?string $s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

// Consulta de materiales Ingenieria (solo lectura)
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
  Pedimento,
  Picture,
  Document,
  Comments,
  CreatedAt,
  UpdatedAt
FROM assets_hw_items
ORDER BY ID ASC
SQL;

$rows = pdo()->query($sql)->fetchAll();
?>
<?php include __DIR__.'/partials/header.php'; ?>

<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
  <h1 class="h4 m-0">Assets HW – Consulta (solo lectura)</h1>
</div>

<div class="card p-3 table-density-comfort dt-container">
  <div class="dt-toolbar">
    <div class="input-group" style="max-width:320px;">
      <span class="input-group-text"><i class="fa fa-magnifying-glass"></i></span>
      <input type="text" class="form-control dt-search" placeholder="Buscar…">
    </div>

    <select class="form-select dt-rows-per-page" style="max-width:160px;">
      <option value="10">10 por página</option>
      <option value="20">20 por página</option>
      <option value="50">50 por página</option>
      <option value="100" selected>100 por página</option>
    </select>

    <div class="dropdown">
      <button class="btn btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">Columnas</button>
      <div class="dropdown-menu dropdown-menu-dark p-2 colvis-menu">
        <?php
          $cols = [
            'ID','Foto','Descripción','Marca','Modelo','Serie',
            'Ubicación','Depto','Responsable','Estado','Documento','Asset No'
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
            <a class="dropdown-item d-flex align-items-center gap-2" href="assets_hw_export.php">
              <i class="fa fa-file-excel text-success"></i> <span>Excel (CSV)</span>
            </a>
          </li>
          <li>
            <button class="dropdown-item d-flex align-items-center gap-2" onclick="window.print()">
              <i class="fa fa-print text-white"></i> <span>Imprimir / PDF</span>
            </button>
          </li>
        </ul>
      </div>
      <select class="form-select dt-filter" data-col="9" style="max-width:220px;">
        <option value="">Estado: todos</option>
        <option>Activo</option>
        <option>Scrap</option>
      </select>
    </div>
  </div>

  <div class="table-wrap">
    <div class="table-scroll">
      <table class="table table-striped table-hover align-middle" id="ingenieriaViewTable">
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
            <th class="th-sort" data-sort="text">Asset No <span class="sort-ind">▲▼</span></th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($rows as $r):
          $status = (string)($r['Status'] ?? '');
          $badge  = $status === 'Scrap' ? 'badge-ven' : 'badge-cal'; // rojo para Scrap / verde para Activo
        ?>
          <tr>
            <td><?= h($r['ID']) ?></td>
            <td class="text-center">
              <?php if (!empty($r['Picture'])): ?>
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
            <td><?= h($r['Pedimento'] ?? '') ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <div class="d-flex justify-content-between align-items-center mt-2">
    <small class="text-secondary">Solo lectura.</small>
    <div class="dt-pager"></div>
  </div>
</div>

<!-- Modal imagen (misma UX que en history/assets_hw_admin) -->
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

// Mini-datatable (igual a otras vistas)
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

  // Ordenamiento
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
    Array.from(tbody.rows).forEach(tr=>{
      const matchText = tr.innerText.toLowerCase().includes(q);
      const estado = tr.cells[9]?.innerText.trim() || '';
      const matchEstado = !f || estado === f;
      tr.style.display = (matchText && matchEstado) ? '' : 'none';
    });
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

  // Paginación
  let page = 1;
  function paginate(){
    const per  = parseInt(rowsSel.value,10);
    const visi = Array.from(tbody.rows).filter(r=>r.style.display!=='none');
    const pages= Math.max(1, Math.ceil(visi.length/per));
    page = Math.min(page, pages);
    visi.forEach((tr,i)=> tr.style.display = (i>=(page-1)*per && i<page*per) ? tr.style.display : 'none');
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
  applyFilters();
})();
</script>

<?php include __DIR__.'/partials/footer.php'; ?>
