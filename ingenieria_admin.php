<?php
// /var/www/html/calibraciones/ingenieria_admin.php
declare(strict_types=1);

require_once __DIR__.'/config.php';
require_auth(['admin','ingenieria']);

function h(?string $s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
?>
<?php include __DIR__.'/partials/header.php'; ?>

<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
  <h1 class="h4 m-0">Activos Ingeniería – Inventario</h1>
  <div class="d-flex gap-2">
    <a class="btn btn-primary" href="ingenieria_audit.php"><i class="fa fa-clipboard-check me-1"></i> Auditar</a>
    <a class="btn btn-info text-white" href="ingenieria_packages.php"><i class="fa fa-box-open me-1"></i> Paquetes</a>
    <a class="btn btn-warning text-dark" href="ingenieria_scrap.php"><i class="fa fa-triangle-exclamation me-1"></i> Scrap</a>
    <a class="btn btn-success" href="ingenieria_add.php"><i class="fa fa-plus me-1"></i> Nuevo</a>
  </div>
</div>

<?php if (!empty($_GET['deleted'])): ?>
<div class="position-fixed bottom-0 end-0 p-3" style="z-index:1100">
  <div id="deleteToast" class="toast align-items-center text-bg-danger border-0 show" role="alert">
    <div class="d-flex">
      <div class="toast-body"><i class="fa fa-trash me-2"></i><strong>Material eliminado</strong> correctamente.</div>
      <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
    </div>
  </div>
</div>
<script>setTimeout(()=>{ const t=document.getElementById('deleteToast'); if(t) new bootstrap.Toast(t,{delay:4000}).hide(); },4000);</script>
<?php endif; ?>

<div class="card p-3 table-density-comfort dt-container">
  <!-- ── Toolbar ── -->
  <div class="dt-toolbar mb-3">
    <div class="input-group" style="max-width:340px;">
      <span class="input-group-text"><i class="fa fa-magnifying-glass"></i></span>
      <input type="text" id="dtSearch" class="form-control" placeholder="Buscar…" autocomplete="off">
      <button class="btn btn-outline-secondary" id="btnClearSearch" title="Limpiar" style="display:none;">
        <i class="fa fa-xmark"></i>
      </button>
    </div>

    <select class="form-select" id="dtRowsPer" style="max-width:160px;">
      <option value="25">25 por página</option>
      <option value="50" selected>50 por página</option>
      <option value="100">100 por página</option>
    </select>

    <div class="dropdown">
      <button class="btn btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">Columnas</button>
      <div class="dropdown-menu dropdown-menu-dark p-2 colvis-menu">
        <?php
          $cols = [
            ['id'=>'col-id',    'label'=>'ID',            'checked'=>true],
            ['id'=>'col-foto',  'label'=>'Foto',          'checked'=>true],
            ['id'=>'col-desc',  'label'=>'Descripción',   'checked'=>true],
            ['id'=>'col-marca', 'label'=>'Marca',         'checked'=>true],
            ['id'=>'col-modelo','label'=>'Modelo',        'checked'=>true],
            ['id'=>'col-serie', 'label'=>'Serie',         'checked'=>true],
            ['id'=>'col-qty',   'label'=>'Qty',           'checked'=>true],
            ['id'=>'col-hw',    'label'=>'HW Asset',      'checked'=>false],
            ['id'=>'col-zl',    'label'=>'ZL Asset',      'checked'=>false],
            ['id'=>'col-ai',    'label'=>'AI Asset',      'checked'=>false],
            ['id'=>'col-xy',    'label'=>'XY Asset',      'checked'=>true],
            ['id'=>'col-years', 'label'=>'Years',         'checked'=>true],
            ['id'=>'col-come',  'label'=>'Come Form',     'checked'=>true],
            ['id'=>'col-recv',  'label'=>'Received Date', 'checked'=>true],
            ['id'=>'col-ubi',   'label'=>'Ubicación',     'checked'=>true],
            ['id'=>'col-depto', 'label'=>'Depto',         'checked'=>true],
            ['id'=>'col-resp',  'label'=>'Responsable',   'checked'=>true],
            ['id'=>'col-est',   'label'=>'Estado',        'checked'=>true],
            ['id'=>'col-doc',   'label'=>'Documento',     'checked'=>true],
            ['id'=>'col-ped',   'label'=>'Pedimento',     'checked'=>true],
            ['id'=>'col-acc',   'label'=>'Acciones',      'checked'=>true],
          ];
          foreach ($cols as $c): ?>
          <label class="dropdown-item d-flex align-items-center gap-2">
            <input class="form-check-input me-2 colvis-chk" type="checkbox"
                   data-col="<?= h($c['id']) ?>" <?= $c['checked'] ? 'checked' : '' ?>>
            <span><?= h($c['label']) ?></span>
          </label>
        <?php endforeach; ?>
      </div>
    </div>

    <div class="ms-auto d-flex gap-2 align-items-center">
      <small class="text-secondary" id="dtInfo">Cargando…</small>
      <div class="dropdown">
        <button class="btn btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
          <i class="fa fa-download"></i> Exportar
        </button>
        <ul class="dropdown-menu dropdown-menu-dark p-2">
          <li>
            <a class="dropdown-item d-flex align-items-center gap-2" href="ingenieria_export.php">
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
      <select class="form-select" id="dtFilterStatus" style="max-width:200px;">
        <option value="">Estado: todos</option>
        <option value="Activo">Activo</option>
      </select>
    </div>
  </div>

  <!-- ── Tabla ── -->
  <div class="table-wrap">
    <div class="table-scroll">
      <table class="table table-striped table-hover align-middle" id="ingenieriaTable">
        <thead>
          <tr>
            <th data-col="col-id">ID</th>
            <th data-col="col-foto">Foto</th>
            <th data-col="col-desc">Descripción</th>
            <th data-col="col-marca">Marca</th>
            <th data-col="col-modelo">Modelo</th>
            <th data-col="col-serie">Serie</th>
            <th data-col="col-qty">Qty</th>
            <th data-col="col-hw" style="display:none;">HW Asset</th>
            <th data-col="col-zl" style="display:none;">ZL Asset</th>
            <th data-col="col-ai" style="display:none;">AI Asset</th>
            <th data-col="col-xy">XY Asset</th>
            <th data-col="col-years">Years</th>
            <th data-col="col-come">Come Form</th>
            <th data-col="col-recv">Received Date</th>
            <th data-col="col-ubi">Ubicación</th>
            <th data-col="col-depto">Depto</th>
            <th data-col="col-resp">Responsable</th>
            <th data-col="col-est">Estado</th>
            <th data-col="col-doc">Documento</th>
            <th data-col="col-ped">Pedimento</th>
            <th data-col="col-acc">Acciones</th>
          </tr>
        </thead>
        <tbody id="dtBody">
          <tr><td colspan="21" class="text-center py-4">
            <div class="spinner-border spinner-border-sm text-secondary me-2"></div>Cargando inventario…
          </td></tr>
        </tbody>
      </table>
    </div>
  </div>

  <!-- ── Paginación ── -->
  <div class="d-flex justify-content-between align-items-center mt-2 flex-wrap gap-2">
    <small class="text-secondary">Búsqueda en servidor · resultados en tiempo real</small>
    <div id="dtPager" class="d-flex gap-1 flex-wrap"></div>
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
(function () {
  'use strict';

  // ── Estado ──────────────────────────────────────────────────────────────────
  let page    = 1;
  let debTimer= null;

  const body     = document.getElementById('dtBody');
  const pager    = document.getElementById('dtPager');
  const info     = document.getElementById('dtInfo');
  const search   = document.getElementById('dtSearch');
  const perSel   = document.getElementById('dtRowsPer');
  const statusSel= document.getElementById('dtFilterStatus');
  const clearBtn = document.getElementById('btnClearSearch');

  // ── Fetch ────────────────────────────────────────────────────────────────────
  function load() {
    const q      = search.value.trim();
    const status = statusSel.value;
    const per    = perSel.value;
    const url    = `ingenieria_ajax.php?q=${encodeURIComponent(q)}&status=${encodeURIComponent(status)}&page=${page}&per=${per}`;

    // Mostrar spinner solo si el tbody no tiene contenido real
    if (!body.querySelector('tr td[colspan]')) {
      body.style.opacity = '0.4';
    }

    fetch(url)
      .then(r => r.json())
      .then(data => {
        body.style.opacity = '';
        body.innerHTML = data.html || '<tr><td colspan="21" class="text-center text-secondary py-4">Sin resultados.</td></tr>';
        info.textContent = `${data.total.toLocaleString()} registro(s) · página ${data.page}/${data.pages}`;
        renderPager(data.pages, data.page);
        // Re-bind image modal a las nuevas filas
        bindImageModal();
        // Aplicar visibilidad de columnas actual
        applyColVis();
      })
      .catch(() => {
        body.style.opacity = '';
        body.innerHTML = '<tr><td colspan="21" class="text-center text-danger py-3">Error al cargar datos.</td></tr>';
      });
  }

  // ── Paginador ────────────────────────────────────────────────────────────────
  function renderPager(pages, current) {
    pager.innerHTML = '';
    if (pages <= 1) return;

    const mkBtn = (label, p, disabled, active) => {
      const btn = document.createElement('button');
      btn.className = 'btn btn-sm ' + (active ? 'btn-primary' : 'btn-outline-secondary');
      btn.textContent = label;
      btn.disabled = disabled;
      if (!disabled) btn.addEventListener('click', () => { page = p; load(); });
      return btn;
    };

    pager.appendChild(mkBtn('«', 1,       current === 1,     false));
    pager.appendChild(mkBtn('‹', current-1, current === 1,   false));

    // Ventana de páginas
    let start = Math.max(1, current - 2);
    let end   = Math.min(pages, start + 4);
    start     = Math.max(1, end - 4);
    for (let p = start; p <= end; p++) {
      pager.appendChild(mkBtn(p, p, false, p === current));
    }

    pager.appendChild(mkBtn('›', current+1, current === pages, false));
    pager.appendChild(mkBtn('»', pages,     current === pages, false));
  }

  // ── Debounce búsqueda ────────────────────────────────────────────────────────
  search.addEventListener('input', () => {
    clearTimeout(debTimer);
    clearBtn.style.display = search.value ? '' : 'none';
    debTimer = setTimeout(() => { page = 1; load(); }, 350);
  });

  clearBtn.addEventListener('click', () => {
    search.value = '';
    clearBtn.style.display = 'none';
    page = 1; load();
  });

  perSel.addEventListener('change',    () => { page = 1; load(); });
  statusSel.addEventListener('change', () => { page = 1; load(); });

  // ── Visibilidad de columnas ──────────────────────────────────────────────────
  const table = document.getElementById('ingenieriaTable');

  function applyColVis() {
    document.querySelectorAll('.colvis-chk').forEach(chk => {
      const colId = chk.dataset.col;
      const show  = chk.checked;
      table.querySelectorAll(`[data-col="${colId}"]`)
           .forEach(el => el.style.display = show ? '' : 'none');
    });
  }

  document.querySelectorAll('.colvis-chk').forEach(chk => {
    chk.addEventListener('change', applyColVis);
  });
  applyColVis(); // aplicar defaults (ocultar HW/ZL/AI)

  // ── Modal imagen ─────────────────────────────────────────────────────────────
  function bindImageModal() {
    const modal = document.getElementById('imagePreviewModal');
    if (!modal) return;
    // Bootstrap ya delega por data-bs-toggle; sólo actualizar src
    modal.addEventListener('show.bs.modal', ev => {
      const img = ev.relatedTarget;
      document.getElementById('previewImage').src = img?.dataset?.src || img?.src || '';
    }, { once: false });
  }

  const imgModal = document.getElementById('imagePreviewModal');
  if (imgModal) {
    imgModal.addEventListener('show.bs.modal', ev => {
      const img = ev.relatedTarget;
      document.getElementById('previewImage').src = img?.dataset?.src || img?.src || '';
    });
    imgModal.addEventListener('hidden.bs.modal', () => {
      document.getElementById('previewImage').src = '';
    });
  }

  // ── Carga inicial ────────────────────────────────────────────────────────────
  load();
})();
</script>

<?php include __DIR__.'/partials/footer.php'; ?>
