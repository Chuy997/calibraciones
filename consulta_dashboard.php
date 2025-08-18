<?php
// /var/www/html/calibraciones/consulta_dashboard.php
declare(strict_types=1);

require_once __DIR__.'/config.php';
require_auth(); // consulta o admin

function h(?string $s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

$pdo = pdo();
$rows = $pdo->query("
  SELECT
    ID, Description, Brand, Model, SerialNumber, CalDate, DueDate,
    CASE
      WHEN CURRENT_DATE() > DueDate THEN 'Vencido'
      WHEN DueDate BETWEEN CURRENT_DATE() AND DATE_ADD(CURRENT_DATE(), INTERVAL 30 DAY) THEN 'Próxima calibración'
      ELSE 'Calibrado'
    END AS status_calculado,
    PdfPath, Picture
  FROM instruments
  ORDER BY DueDate ASC, ID ASC
")->fetchAll();
?>
<?php include __DIR__.'/partials/header.php'; ?>

<style>
  /* Miniaturas */
  .thumb {
    width: 56px;           /* tamaño de miniatura */
    height: 56px;
    object-fit: cover;     /* recorte elegante */
    border-radius: 6px;
    border: 1px solid #333;
    background: #1b1b1d;
  }
  .thumb-btn {
    padding: 2px 6px;
    border-radius: 6px;
    display: inline-flex;
    align-items: center;
    gap: .35rem;
  }
  .thumb-empty {
    display:inline-flex; width:56px; height:56px; align-items:center; justify-content:center;
    border:1px dashed #333; border-radius:6px; color:#666; font-size:.8rem;
  }
  /* Modal imagen: que no exceda viewport */
  .img-zoom {
    max-width: min(90vw, 950px);
    max-height: 80vh;
    object-fit: contain;
  }
</style>

<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
  <h1 class="h4 m-0">Consulta de instrumentos (solo lectura)</h1>
  <div class="d-flex gap-2">
    <a class="btn btn-outline-info btn-sm" href="report_view.php"><i class="fa fa-chart-column me-1"></i> Ver reportes</a>
  </div>
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
      <option value="50"selected>50 por página</option>
    </select>

    <div class="dropdown">
      <button class="btn btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">Columnas</button>
      <div class="dropdown-menu dropdown-menu-dark p-2 colvis-menu">
        <?php
        $cols = ['ID','Desc','Marca','Modelo','Serial','Cal.Date','Due.Date','Estado','PDF','Imagen','Historial'];
        foreach ($cols as $i=>$c): ?>
          <label class="dropdown-item d-flex align-items-center gap-2">
            <input class="form-check-input me-2" type="checkbox" data-col="<?= $i ?>" checked>
            <span><?= h($c) ?></span>
          </label>
        <?php endforeach; ?>
      </div>
    </div>

    <div class="ms-auto d-flex gap-2">
      <select class="form-select dt-filter" data-col="7" style="max-width:220px;">
        <option value="">Estado: todos</option>
        <option>Calibrado</option>
        <option>Próxima calibración</option>
        <option>Vencido</option>
      </select>
    </div>
  </div>

  <div class="table-wrap">
    <div class="table-scroll">
      <table class="table table-striped table-hover align-middle">
        <thead>
          <tr>
            <th class="th-sort" data-sort="text">ID <span class="sort-ind">▲▼</span></th>
            <th class="th-sort" data-sort="text">Desc <span class="sort-ind">▲▼</span></th>
            <th class="th-sort" data-sort="text">Marca <span class="sort-ind">▲▼</span></th>
            <th class="th-sort" data-sort="text">Modelo <span class="sort-ind">▲▼</span></th>
            <th class="th-sort" data-sort="text">Serial <span class="sort-ind">▲▼</span></th>
            <th class="th-sort" data-sort="date">Cal.Date <span class="sort-ind">▲▼</span></th>
            <th class="th-sort" data-sort="date">Due.Date <span class="sort-ind">▲▼</span></th>
            <th class="th-sort" data-sort="text">Estado <span class="sort-ind">▲▼</span></th>
            <th>PDF</th>
            <th>Imagen</th>
            <th>Historial</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($rows as $r):
            $estado = (string)$r['status_calculado'];
            $badge = $estado==='Vencido' ? 'badge-ven' : ($estado==='Próxima calibración' ? 'badge-prox' : 'badge-cal');
            $pic   = (string)($r['Picture'] ?? '');
          ?>
          <tr>
            <td><?= h($r['ID']) ?></td>
            <td><?= h($r['Description']) ?></td>
            <td><?= h($r['Brand']) ?></td>
            <td><?= h($r['Model']) ?></td>
            <td><?= h($r['SerialNumber']) ?></td>
            <td><?= h($r['CalDate']) ?></td>
            <td><?= h($r['DueDate']) ?></td>
            <td><span class="badge badge-state <?= $badge ?>"><?= h($estado) ?></span></td>
            <td class="text-center">
              <?php if (!empty($r['PdfPath'])): ?>
                <a class="btn btn-sm btn-outline-light" href="<?= h($r['PdfPath']) ?>" target="_blank" title="Ver PDF">
                  <i class="fa fa-file-pdf"></i>
                </a>
              <?php else: ?>—<?php endif; ?>
            </td>
            <td class="text-center">
              <?php if ($pic): ?>
                <button type="button"
                        class="btn btn-outline-light btn-sm thumb-btn js-open-img"
                        data-bs-toggle="modal"
                        data-bs-target="#imgModal"
                        data-full="<?= h($pic) ?>"
                        title="Ver imagen">
                  <img src="<?= h($pic) ?>" alt="miniatura" class="thumb" loading="lazy"
                       onerror="this.onerror=null;this.src='data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%2256%22 height=%2256%22%3E%3Crect width=%2256%22 height=%2256%22 fill=%22%231b1b1d%22/%3E%3Cpath d=%22M14 38 L26 24 L36 34 L42 28 L50 38 Z%22 fill=%22%23333%22/%3E%3Ccircle cx=%2221%22 cy=%2221%22 r=%225%22 fill=%22%23333%22/%3E%3C/svg%3E';">
                </button>
              <?php else: ?>
                <span class="thumb-empty" title="Sin imagen">—</span>
              <?php endif; ?>
            </td>
            <td class="text-center">
              <a class="btn btn-sm btn-info" href="history.php?id=<?= urlencode((string)$r['ID']) ?>" title="Ver historial">
                <i class="fa fa-history"></i>
              </a>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <div class="d-flex justify-content-between align-items-center mt-2">
    <small class="text-secondary">Solo lectura: no hay acciones de edición.</small>
    <div class="dt-pager"></div>
  </div>
</div>

<!-- Modal para ver imagen en grande -->
<div class="modal fade" id="imgModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content" style="background:#121214;border:1px solid #292a2d;">
      <div class="modal-header">
        <h5 class="modal-title"><i class="fa fa-image me-2"></i>Vista de imagen</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
      </div>
      <div class="modal-body d-flex justify-content-center">
        <img id="imgModalPic" class="img-zoom" alt="imagen instrumento">
      </div>
      <div class="modal-footer">
        <a id="imgModalOpenNew" class="btn btn-outline-light" href="#" target="_blank">
          <i class="fa fa-arrow-up-right-from-square me-1"></i> Abrir en pestaña nueva
        </a>
        <button type="button" class="btn btn-primary" data-bs-dismiss="modal">Cerrar</button>
      </div>
    </div>
  </div>
</div>

<script>
// Abre modal con la imagen en grande
document.addEventListener('click', (e)=>{
  const btn = e.target.closest('.js-open-img');
  if (!btn) return;
  const src = btn.getAttribute('data-full');
  const img = document.getElementById('imgModalPic');
  const a   = document.getElementById('imgModalOpenNew');
  img.src = src || '';
  a.href  = src || '#';
});
</script>

<script>
// Mini-datatable (mismo comportamiento que en admin/report)
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

  // Sort
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

  // Search + filter
  function applyFilters(){
    const q = (search?.value || '').toLowerCase();
    const f = (filter?.value || '');
    Array.from(tbody.rows).forEach(tr=>{
      const matchText = tr.innerText.toLowerCase().includes(q);
      const estado = tr.cells[7]?.innerText.trim() || '';
      const matchEstado = !f || estado === f;
      tr.style.display = (matchText && matchEstado) ? '' : 'none';
    });
    paginate();
  }
  search?.addEventListener('input', applyFilters);
  filter?.addEventListener('change', applyFilters);

  // Column visibility
  colvis.forEach(chk=>{
    chk.addEventListener('change', ()=>{
      const col = parseInt(chk.dataset.col,10);
      table.querySelectorAll(`thead th:nth-child(${col+1}), tbody td:nth-child(${col+1})`)
           .forEach(el=>el.style.display = chk.checked ? '' : 'none');
    });
  });

  // Pagination
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
