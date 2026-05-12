<?php
// /var/www/html/calibraciones/golden_scrap.php
declare(strict_types=1);

require_once __DIR__.'/config.php';
require_auth(['admin','ingenieria']);

function h(?string $s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

$pdo    = pdo();
$errors = [];

// --- Modo: listado (sin ID) o formulario de confirmación (con ID) ---
$id       = $_GET['id'] ?? $_POST['id'] ?? '';
$listMode = ($id === '' || !preg_match('/^[A-Za-z0-9._-]+$/', $id));

// ============================================================
// MODO LISTA: mostrar todos los ítems Golden en Scrap
// ============================================================
if ($listMode) {
  $rows = $pdo->query("
    SELECT ID, Description, Brand, Model, SerialNumber,
           Location, Department, Owner, Status, Pedimento,
           Picture, Document, Comments, UpdatedAt
    FROM golden_items
    WHERE Status = 'Scrap'
    ORDER BY UpdatedAt DESC
  ")->fetchAll();
?>
<?php include __DIR__.'/partials/header.php'; ?>

<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
  <h1 class="h4 m-0"><i class="fa fa-dumpster me-2 text-warning"></i>Golden – Ítems en Scrap</h1>
  <a href="golden_admin.php" class="btn btn-outline-secondary">
    <i class="fa fa-arrow-left me-1"></i>Volver al Inventario
  </a>
</div>

<?php if (empty($rows)): ?>
  <div class="alert alert-info">No hay ítems en Scrap actualmente.</div>
<?php else: ?>
<div class="card p-3 dt-container table-density-compact">
  <div class="dt-toolbar mb-3">
    <div class="input-group" style="max-width:320px;">
      <span class="input-group-text"><i class="fa fa-magnifying-glass"></i></span>
      <input type="text" class="form-control dt-search" placeholder="Buscar…">
    </div>
    <select class="form-select dt-rows-per-page ms-2" style="max-width:160px;">
      <option value="20" selected>20 por página</option>
      <option value="50">50 por página</option>
      <option value="100">100 por página</option>
    </select>
    <small class="text-secondary ms-auto align-self-center"><?= count($rows) ?> ítem(s) en Scrap</small>
  </div>

  <div class="table-scroll">
    <table class="table table-striped table-hover align-middle">
      <thead>
        <tr>
          <th class="th-sort" data-sort="text">ID <span class="sort-ind">▲▼</span></th>
          <th>Foto</th>
          <th class="th-sort" data-sort="text">Descripción <span class="sort-ind">▲▼</span></th>
          <th class="th-sort" data-sort="text">Marca <span class="sort-ind">▲▼</span></th>
          <th class="th-sort" data-sort="text">Modelo <span class="sort-ind">▲▼</span></th>
          <th class="th-sort" data-sort="text">Serie <span class="sort-ind">▲▼</span></th>
          <th class="th-sort" data-sort="text">Pedimento <span class="sort-ind">▲▼</span></th>
          <th class="th-sort" data-sort="text">Ubicación <span class="sort-ind">▲▼</span></th>
          <th class="th-sort" data-sort="date">Fecha Scrap <span class="sort-ind">▲▼</span></th>
          <th>Acciones</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($rows as $r): ?>
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
          <td><?= h($r['Pedimento'] ?? '') ?></td>
          <td><?= h($r['Location']) ?></td>
          <td><?= h($r['UpdatedAt']) ?></td>
          <td>
            <a href="golden_history.php?id=<?= urlencode((string)$r['ID']) ?>"
               class="btn btn-sm btn-info" title="Historial">
              <i class="fa fa-clock-rotate-left"></i>
            </a>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <div class="d-flex justify-content-between align-items-center mt-2">
    <small class="text-secondary">Búsqueda y paginación en el navegador.</small>
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
const imgModal = document.getElementById('imagePreviewModal');
if (imgModal) {
  imgModal.addEventListener('show.bs.modal', ev => {
    const img = ev.relatedTarget;
    const src = img?.getAttribute('data-src') || img?.getAttribute('src');
    document.getElementById('previewImage').setAttribute('src', src || '');
  });
  imgModal.addEventListener('hidden.bs.modal', () => {
    document.getElementById('previewImage').setAttribute('src', '');
  });
}

(function(){
  const container = document.querySelector('.dt-container');
  if (!container) return;
  const table   = container.querySelector('table');
  const tbody   = table.tBodies[0];
  const search  = container.querySelector('.dt-search');
  const rowsSel = container.querySelector('.dt-rows-per-page');
  const pagerEl = container.querySelector('.dt-pager');

  let sortCol = 0, sortDir = 1;
  table.querySelectorAll('th.th-sort').forEach((th, idx) => {
    th.addEventListener('click', () => {
      const type = th.dataset.sort || 'text';
      sortCol = idx; sortDir *= -1;
      const rows = Array.from(tbody.rows);
      rows.sort((a, b) => {
        const A = a.cells[sortCol].innerText.trim();
        const B = b.cells[sortCol].innerText.trim();
        if (type === 'date') return (new Date(A) - new Date(B)) * sortDir;
        return A.localeCompare(B, undefined, {numeric: true}) * sortDir;
      });
      rows.forEach(r => tbody.appendChild(r));
    });
  });

  function applyFilters() {
    const q = (search?.value || '').toLowerCase();
    Array.from(tbody.rows).forEach(tr => {
      tr.dataset.filtered = tr.innerText.toLowerCase().includes(q) ? '0' : '1';
    });
    page = 1;
    paginate();
  }
  search?.addEventListener('input', applyFilters);

  let page = 1;
  function paginate() {
    const per = parseInt(rowsSel.value, 10);
    const all = Array.from(tbody.rows);
    const vis = all.filter(r => r.dataset.filtered !== '1');
    const pages = Math.max(1, Math.ceil(vis.length / per));
    page = Math.min(page, pages);
    all.forEach(tr => tr.style.display = 'none');
    vis.forEach((tr, i) => {
      if (i >= (page - 1) * per && i < page * per) tr.style.display = '';
    });
    pagerEl.innerHTML = '';
    for (let p = 1; p <= pages; p++) {
      const btn = document.createElement('button');
      btn.className = 'btn btn-sm ' + (p === page ? 'btn-primary' : 'btn-outline-secondary');
      btn.textContent = p;
      btn.addEventListener('click', () => { page = p; paginate(); });
      pagerEl.appendChild(btn);
    }
  }
  rowsSel?.addEventListener('change', () => { page = 1; paginate(); });
  applyFilters();
})();
</script>
<?php endif; ?>

<?php include __DIR__.'/partials/footer.php'; ?>
<?php
  exit;
}

// ============================================================
// MODO FORMULARIO: confirmar Scrap de un ítem específico
// ============================================================
$stmt = $pdo->prepare("
  SELECT ID, Description, Brand, Model, SerialNumber,
         Location, Department, Owner, Status, Picture, Document, Comments
  FROM golden_items
  WHERE ID = :id
");
$stmt->execute([':id' => $id]);
$item = $stmt->fetch();

if (!$item) {
  http_response_code(404);
  exit('Material Golden no encontrado.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (!isset($_POST['csrf']) || !csrf_validate($_POST['csrf'])) {
    $errors[] = 'Sesión expirada. Vuelve a intentar.';
  }

  $reason = trim((string)($_POST['reason'] ?? ''));

  if ($reason === '') {
    $errors[] = 'Debes especificar el motivo de Scrap.';
  }

  if (!$errors) {
    if ((string)$item['Status'] === 'Scrap') {
      header('Location: golden_scrap.php');
      exit;
    }

    try {
      $pdo->beginTransaction();

      $upd = $pdo->prepare("
        UPDATE golden_items
        SET Status = 'Scrap',
            Comments = CONCAT(COALESCE(Comments,''), CASE WHEN COALESCE(Comments,'')='' THEN '' ELSE '\n' END, '[Scrap] ', :reason),
            UpdatedAt = NOW()
        WHERE ID = :id
      ");
      $upd->execute([':reason' => $reason, ':id' => $id]);

      $hst = $pdo->prepare("
        INSERT INTO golden_history
          (GoldenID, Action, Description, Brand, Model, SerialNumber,
           Location, Department, Owner, Status, Picture, Document, Comments, CreatedAt)
        VALUES
          (:GoldenID, 'scrap', :Description, :Brand, :Model, :SerialNumber,
           :Location, :Department, :Owner, 'Scrap', :Picture, :Document, :Comments, NOW())
      ");
      $hst->execute([
        ':GoldenID'     => $item['ID'],
        ':Description'  => $item['Description'],
        ':Brand'        => $item['Brand'],
        ':Model'        => $item['Model'],
        ':SerialNumber' => $item['SerialNumber'],
        ':Location'     => $item['Location'],
        ':Department'   => $item['Department'],
        ':Owner'        => $item['Owner'],
        ':Picture'      => $item['Picture'],
        ':Document'     => $item['Document'],
        ':Comments'     => trim(($item['Comments'] ? ($item['Comments']."\n") : '') . "[Scrap] ".$reason),
      ]);

      $pdo->commit();
      header('Location: golden_scrap.php');
      exit;
    } catch (Throwable $e) {
      if ($pdo->inTransaction()) $pdo->rollBack();
      $errors[] = 'Error al enviar a Scrap: ' . $e->getMessage();
    }
  }
}
?>
<?php include __DIR__.'/partials/header.php'; ?>

<div class="row justify-content-center">
  <div class="col-12 col-lg-8 col-xl-7">
    <div class="d-flex align-items-center gap-2 my-3">
      <a href="golden_scrap.php" class="btn btn-sm btn-outline-secondary">
        <i class="fa fa-arrow-left"></i>
      </a>
      <h1 class="h4 m-0">Enviar a Scrap – Golden</h1>
    </div>

    <div class="card p-3 mb-3">
      <div class="row g-2">
        <div class="col-12 col-md-8">
          <div class="small text-secondary">ID</div>
          <div class="fw-semibold"><?= h($item['ID']) ?></div>

          <div class="small text-secondary mt-2">Descripción</div>
          <div><?= h($item['Description']) ?></div>

          <div class="small text-secondary mt-2">Detalles</div>
          <div class="text-nowrap">
            <span class="me-3"><strong>Marca:</strong> <?= h($item['Brand']) ?></span>
            <span class="me-3"><strong>Modelo:</strong> <?= h($item['Model']) ?></span>
            <span class="me-3"><strong>Serie:</strong> <?= h($item['SerialNumber']) ?></span>
          </div>

          <div class="small text-secondary mt-2">Ubicación / Depto / Responsable</div>
          <div class="text-nowrap">
            <span class="me-3"><strong>Ubicación:</strong> <?= h($item['Location']) ?></span>
            <span class="me-3"><strong>Depto:</strong> <?= h($item['Department']) ?></span>
            <span class="me-3"><strong>Responsable:</strong> <?= h($item['Owner']) ?></span>
          </div>

          <div class="small text-secondary mt-2">Estado actual</div>
          <?php
            $status = (string)$item['Status'];
            $badge  = $status === 'Scrap' ? 'badge-ven' : 'badge-cal';
          ?>
          <div><span class="badge badge-state <?= $badge ?>"><?= h($status) ?></span></div>
        </div>
        <div class="col-12 col-md-4 text-center">
          <?php if (!empty($item['Picture'])): ?>
            <img src="<?= h($item['Picture']) ?>" class="img-thumb mt-2" alt="img"
                 data-bs-toggle="modal" data-bs-target="#imagePreviewModal"
                 data-src="<?= h($item['Picture']) ?>">
          <?php else: ?>
            <div class="text-secondary mt-2">Sin foto</div>
          <?php endif; ?>
          <?php if (!empty($item['Document'])): ?>
            <div class="mt-3">
              <a href="<?= h($item['Document']) ?>" target="_blank" class="btn btn-sm btn-outline-light">
                <i class="fa fa-file-pdf me-1"></i> Documento
              </a>
            </div>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <?php if ($errors): ?>
      <div class="alert alert-danger">
        <ul class="m-0 ps-3">
          <?php foreach ($errors as $err): ?>
            <li><?= h($err) ?></li>
          <?php endforeach; ?>
        </ul>
      </div>
    <?php endif; ?>

    <?php if ($item['Status'] === 'Scrap'): ?>
      <div class="alert alert-secondary">
        Este material ya se encuentra en <strong>Scrap</strong>.
      </div>
      <a href="golden_scrap.php" class="btn btn-outline-secondary">Volver al listado</a>
    <?php else: ?>
      <form method="POST" action="golden_scrap.php" class="needs-validation" novalidate>
        <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
        <input type="hidden" name="id" value="<?= h($item['ID']) ?>">

        <div class="mb-3">
          <label for="reason" class="form-label">Motivo de Scrap <span class="text-danger">*</span></label>
          <textarea id="reason" name="reason" class="form-control" rows="3"
                    placeholder="Describe la razón por la que se da de baja…" required></textarea>
          <div class="form-text">Se registrará en el historial y en los comentarios del material.</div>
        </div>

        <div class="d-flex gap-2">
          <a href="golden_admin.php" class="btn btn-outline-secondary">Cancelar</a>
          <button type="submit" class="btn btn-warning">
            <i class="fa fa-triangle-exclamation me-2"></i>Confirmar envío a Scrap
          </button>
        </div>
      </form>
    <?php endif; ?>
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
const imgModal = document.getElementById('imagePreviewModal');
if (imgModal) {
  imgModal.addEventListener('show.bs.modal', ev => {
    const img = ev.relatedTarget;
    const src = img?.getAttribute('data-src') || img?.getAttribute('src');
    document.getElementById('previewImage').setAttribute('src', src || '');
  });
  imgModal.addEventListener('hidden.bs.modal', () => {
    document.getElementById('previewImage').setAttribute('src', '');
  });
}
</script>

<?php include __DIR__.'/partials/footer.php'; ?>
