<?php
// /var/www/html/calibraciones/ingenieria_scrap.php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_auth(['admin', 'ingenieria']);
require_once __DIR__ . '/ingenieria_scrap_mail.php';

function h(?string $s): string
{
  return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

$pdo    = pdo();
$errors = [];
$success = '';

// ══════════════════════════════════════════════════════
// ACCIONES POST (solo en modo lista)
// ══════════════════════════════════════════════════════
$id   = trim((string)($_GET['id'] ?? $_POST['id'] ?? ''));
$mode = ($id !== '') ? 'form' : 'list';

if ($mode === 'list' && $_SERVER['REQUEST_METHOD'] === 'POST') {
  if (!isset($_POST['csrf']) || !csrf_validate($_POST['csrf'])) {
    $errors[] = 'Sesión expirada. Vuelve a intentar.';
  } else {
    $action = $_POST['action'] ?? '';

    // ── Marcar como Destruido ──────────────────────────────────
    if ($action === 'destroy') {
      $ids = $_POST['ids'] ?? [];
      $ids = array_filter(array_map('trim', (array)$ids));
      if (empty($ids)) {
        $errors[] = 'No seleccionaste ningún ítem.';
      } else {
        try {
          $pdo->beginTransaction();
          foreach ($ids as $did) {
            // Solo destruir los que estén en Scrap
            $stmt = $pdo->prepare("SELECT * FROM ingenieria_items WHERE ID=:id AND Status='Scrap'");
            $stmt->execute([':id' => $did]);
            $r = $stmt->fetch();
            if (!$r) continue;

            $pdo->prepare("UPDATE ingenieria_items SET Status='Destruido', UpdatedAt=NOW() WHERE ID=:id")
              ->execute([':id' => $did]);

            $pdo->prepare("INSERT INTO ingenieria_history
                            (IngenieriaID,Action,Description,Brand,Model,SerialNumber,
                             Location,Department,Owner,Status,Picture,Document,Comments,CreatedAt)
                            VALUES
                            (:iid,'destroy',:desc,:brand,:model,:sn,
                             :loc,:dept,:owner,'Destruido',:pic,:doc,:comm,NOW())")
              ->execute([
                ':iid'   => $r['ID'],
                ':desc'  => $r['Description'],
                ':brand' => $r['Brand'],
                ':model' => $r['Model'],
                ':sn'    => $r['SerialNumber'],
                ':loc' => $r['Location'],
                ':dept'  => $r['Department'],
                ':owner' => $r['Owner'],
                ':pic'   => $r['Picture'],
                ':doc' => $r['Document'],
                ':comm'  => $r['Comments'],
              ]);
          }
          $pdo->commit();
          $success = count($ids) . ' ítem(s) marcados como <strong>Destruido</strong>.';
        } catch (Throwable $e) {
          if ($pdo->inTransaction()) $pdo->rollBack();
          $errors[] = 'Error al actualizar: ' . $e->getMessage();
        }
      }
    }

    // ── Enviar correo a César ──────────────────────────────────
    if ($action === 'send_email') {
      $pending = $pdo->query("
                SELECT ID, Description, Brand, Model, SerialNumber,
                       Qty, Department, Owner, UpdatedAt
                FROM ingenieria_items
                WHERE Status = 'Scrap'
                ORDER BY UpdatedAt DESC
            ")->fetchAll();

      if (empty($pending)) {
        $errors[] = 'No hay materiales pendientes en Scrap para enviar.';
      } else {
        if (ingenieria_send_scrap_email($pending)) {
          $success = 'Correo enviado a <strong>cesar.gutierrez@xinya-la.com</strong> con ' . count($pending) . ' ítem(s) pendientes.';
        } else {
          $errors[] = 'Error al enviar el correo. Revisa los logs del servidor.';
        }
      }
    }
  }
}

// ══════════════════════════════════════════════════════
// MODO LISTA
// ══════════════════════════════════════════════════════
if ($mode === 'list') {
  $scrapItems = $pdo->query("
        SELECT ID, Description, Brand, Model, SerialNumber,
               Location, Department, Owner, Picture, Document,
               Qty, UpdatedAt, Comments, Status
        FROM ingenieria_items
        WHERE Status IN ('Scrap','Destruido')
        ORDER BY FIELD(Status,'Scrap','Destruido'), UpdatedAt DESC
    ")->fetchAll();

  $pendingCount = 0;
  foreach ($scrapItems as $r) {
    if ($r['Status'] === 'Scrap') $pendingCount++;
  }

  include __DIR__ . '/partials/header.php';
?>

  <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <h1 class="h4 m-0"><i class="fa fa-triangle-exclamation text-warning me-2"></i>Materiales en Scrap / Destruidos</h1>
    <div class="d-flex gap-2 flex-wrap">
      <!-- Botón Enviar correo -->
      <form method="POST" action="ingenieria_scrap.php" id="emailForm" class="d-inline">
        <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
        <input type="hidden" name="action" value="send_email">
        <button type="submit" class="btn btn-warning btn-sm" id="btnSendEmail"
          onclick="return confirm('¿Enviar correo a César con los <?= $pendingCount ?> ítem(s) en Scrap pendientes?');">
          <i class="fa fa-envelope me-1"></i>
          Enviar aprbación
          <?php if ($pendingCount > 0): ?>
            <span class="badge bg-dark ms-1"><?= $pendingCount ?></span>
          <?php endif; ?>
        </button>
      </form>
      <a class="btn btn-outline-secondary btn-sm" href="ingenieria_admin.php">
        <i class="fa fa-arrow-left me-1"></i>Volver al inventario
      </a>
    </div>
  </div>

  <?php if ($errors): ?>
    <div class="alert alert-danger">
      <ul class="m-0 ps-3"><?php foreach ($errors as $e): ?><li><?= h($e) ?></li><?php endforeach; ?></ul>
    </div>
  <?php endif; ?>
  <?php if ($success): ?>
    <div class="alert alert-success"><?= $success ?></div>
  <?php endif; ?>

  <?php if (empty($scrapItems)): ?>
    <div class="alert alert-secondary d-flex align-items-center gap-2">
      <i class="fa fa-circle-check fa-lg text-success"></i>
      <span>No hay materiales en Scrap ni Destruidos actualmente.</span>
    </div>
  <?php else: ?>

    <!-- Barra de acciones masivas (oculta hasta seleccionar) -->
    <div id="bulkBar" class="card p-2 mb-3 border-warning d-none">
      <form method="POST" action="ingenieria_scrap.php" id="destroyForm">
        <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
        <input type="hidden" name="action" value="destroy">
        <div class="d-flex align-items-center gap-3 flex-wrap">
          <span class="text-warning fw-semibold">
            <i class="fa fa-check-square me-1"></i>
            <span id="selectedCount">0</span> ítem(s) seleccionados
          </span>
          <div id="hiddenIds"></div>
          <button type="submit" class="btn btn-danger btn-sm"
            onclick="return confirm('¿Marcar los ítems seleccionados como DESTRUIDO? Esta acción queda registrada en el historial.');">
            <i class="fa fa-fire me-1"></i>Marcar como Destruido
          </button>
          <button type="button" class="btn btn-outline-secondary btn-sm" id="btnDeselectAll">
            <i class="fa fa-xmark me-1"></i>Deseleccionar todo
          </button>
        </div>
      </form>
    </div>

    <div class="card p-3 table-density-comfort dt-container">
      <div class="dt-toolbar mb-2">
        <div class="input-group" style="max-width:320px;">
          <span class="input-group-text"><i class="fa fa-magnifying-glass"></i></span>
          <input type="text" class="form-control dt-search" placeholder="Buscar…">
        </div>
        <!-- Filtro de status -->
        <select class="form-select ms-2" id="filterStatus" style="max-width:160px;">
          <option value="">Todos los status</option>
          <option value="Scrap">Solo Scrap</option>
          <option value="Destruido">Solo Destruido</option>
        </select>
        <div class="ms-auto d-flex gap-2 align-items-center flex-wrap">
          <small class="text-warning"><?= $pendingCount ?> en Scrap</small>
          <small class="text-secondary">|</small>
          <small class="text-danger"><?= count($scrapItems) - $pendingCount ?> Destruidos</small>
          <!-- Seleccionar todos los Scrap -->
          <button class="btn btn-outline-warning btn-sm" id="btnSelectAll">
            <i class="fa fa-check-double me-1"></i>Seleccionar Scrap
          </button>
        </div>
      </div>

      <div class="table-wrap">
        <div class="table-scroll">
          <table class="table table-striped table-hover align-middle" id="scrapTable">
            <thead>
              <tr>
                <th style="width:36px;"></th>
                <th>ID</th>
                <th>Foto</th>
                <th>Descripción</th>
                <th>Marca</th>
                <th>Modelo</th>
                <th>Serie</th>
                <th>Qty</th>
                <th>Ubicación</th>
                <th>Depto</th>
                <th>Responsable</th>
                <th>Fecha Scrap</th>
                <th>Status</th>
                <th>Documento</th>
                <th>Acciones</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($scrapItems as $r): ?>
                <?php
                $isScrap     = ($r['Status'] === 'Scrap');
                $isDestroyed = ($r['Status'] === 'Destruido');
                ?>
                <tr data-status="<?= h($r['Status']) ?>">
                  <td class="text-center">
                    <?php if ($isScrap): ?>
                      <input type="checkbox" class="form-check-input scrap-chk"
                        value="<?= h($r['ID']) ?>" style="cursor:pointer;">
                    <?php else: ?>
                      <i class="fa fa-fire text-danger" title="Destruido"></i>
                    <?php endif; ?>
                  </td>
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
                  <td><?= h((string)($r['Qty'] ?? '')) ?></td>
                  <td><?= h($r['Location']) ?></td>
                  <td><?= h($r['Department']) ?></td>
                  <td><?= h($r['Owner']) ?></td>
                  <td><?= h($r['UpdatedAt'] ?? '') ?></td>
                  <td>
                    <?php if ($isScrap): ?>
                      <span class="badge bg-warning text-dark">Scrap</span>
                    <?php else: ?>
                      <span class="badge bg-danger">Destruido</span>
                    <?php endif; ?>
                  </td>
                  <td class="text-center">
                    <?php if (!empty($r['Document'])): ?>
                      <a href="<?= h($r['Document']) ?>" target="_blank"
                        class="btn btn-sm btn-outline-light" title="Ver documento">
                        <i class="fa fa-file-pdf"></i>
                      </a>
                      <?php else: ?>—<?php endif; ?>
                  </td>
                  <td>
                    <div class="btn-group">
                      <a class="btn btn-primary btn-sm"
                        href="ingenieria_update.php?id=<?= urlencode((string)$r['ID']) ?>" title="Editar">
                        <i class="fa fa-pen-to-square"></i>
                      </a>
                      <a class="btn btn-info btn-sm"
                        href="ingenieria_history.php?id=<?= urlencode((string)$r['ID']) ?>" title="Historial">
                        <i class="fa fa-clock-rotate-left"></i>
                      </a>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>

      <div class="d-flex justify-content-between align-items-center mt-2">
        <small class="text-secondary">Búsqueda en el navegador.</small>
        <div class="dt-pager"></div>
      </div>
    </div>

    <!-- Modal imagen -->
    <div class="modal fade" id="imagePreviewModal" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content bg-dark">
          <div class="modal-header border-0">
            <h5 class="modal-title">Vista previa</h5>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body d-flex justify-content-center">
            <img id="previewImage" src="" alt="Imagen" class="img-fluid rounded" style="max-height:75vh;">
          </div>
        </div>
      </div>
    </div>

    <script>
      (function() {
        const table = document.getElementById('scrapTable');
        if (!table) return;
        const tbody = table.tBodies[0];
        const search = document.querySelector('.dt-search');
        const filterSt = document.getElementById('filterStatus');
        const pagerEl = document.querySelector('.dt-pager');
        const bulkBar = document.getElementById('bulkBar');
        const countEl = document.getElementById('selectedCount');
        const hiddenIds = document.getElementById('hiddenIds');
        const PER = 50;
        let page = 1;

        // ── Checkboxes ───────────────────────────────────────────────
        function getChecked() {
          return Array.from(tbody.querySelectorAll('.scrap-chk:checked'));
        }

        function updateBulkBar() {
          const checked = getChecked();
          countEl.textContent = checked.length;
          bulkBar.classList.toggle('d-none', checked.length === 0);
          // Rebuild hidden inputs
          hiddenIds.innerHTML = '';
          checked.forEach(chk => {
            const inp = document.createElement('input');
            inp.type = 'hidden';
            inp.name = 'ids[]';
            inp.value = chk.value;
            hiddenIds.appendChild(inp);
          });
        }

        tbody.addEventListener('change', e => {
          if (e.target.classList.contains('scrap-chk')) updateBulkBar();
        });

        document.getElementById('btnSelectAll')?.addEventListener('click', () => {
          tbody.querySelectorAll('.scrap-chk').forEach(chk => {
            const row = chk.closest('tr');
            if (row && row.style.display !== 'none') chk.checked = true;
          });
          updateBulkBar();
        });

        document.getElementById('btnDeselectAll')?.addEventListener('click', () => {
          tbody.querySelectorAll('.scrap-chk').forEach(chk => chk.checked = false);
          updateBulkBar();
        });

        // ── Filtros y paginación ─────────────────────────────────────
        function applyFilters() {
          const q = (search?.value || '').toLowerCase();
          const st = filterSt?.value || '';
          Array.from(tbody.rows).forEach(tr => {
            const matchQ = tr.innerText.toLowerCase().includes(q);
            const matchSt = st === '' || tr.dataset.status === st;
            tr.dataset.filtered = (matchQ && matchSt) ? '0' : '1';
          });
          page = 1;
          paginate();
        }

        search?.addEventListener('input', applyFilters);
        filterSt?.addEventListener('change', applyFilters);

        function paginate() {
          const all = Array.from(tbody.rows);
          const vis = all.filter(r => r.dataset.filtered !== '1');
          const pages = Math.max(1, Math.ceil(vis.length / PER));
          page = Math.min(page, pages);
          all.forEach(tr => tr.style.display = 'none');
          vis.forEach((tr, i) => {
            if (i >= (page - 1) * PER && i < page * PER) tr.style.display = '';
          });
          pagerEl.innerHTML = '';
          for (let p = 1; p <= pages; p++) {
            const btn = document.createElement('button');
            btn.className = 'btn btn-sm ' + (p === page ? 'btn-primary' : 'btn-outline-secondary');
            btn.textContent = p;
            btn.addEventListener('click', () => {
              page = p;
              paginate();
            });
            pagerEl.appendChild(btn);
          }
        }

        applyFilters();

        // ── Modal imagen ─────────────────────────────────────────────
        const imgModal = document.getElementById('imagePreviewModal');
        if (imgModal) {
          imgModal.addEventListener('show.bs.modal', ev => {
            document.getElementById('previewImage').src = ev.relatedTarget?.dataset.src || '';
          });
          imgModal.addEventListener('hidden.bs.modal', () => {
            document.getElementById('previewImage').src = '';
          });
        }
      })();
    </script>

  <?php endif; ?>

<?php
  include __DIR__ . '/partials/footer.php';
  exit;
}

// ══════════════════════════════════════════════════════════════════════════════
// MODO FORMULARIO – enviar un material específico a Scrap
// ══════════════════════════════════════════════════════════════════════════════

if (!preg_match('/^[A-Za-z0-9._-]+$/', $id)) {
  http_response_code(400);
  exit('ID inválido.');
}

$stmt = $pdo->prepare("
    SELECT ID, Description, Brand, Model, SerialNumber,
           Location, Department, Owner, Status, Picture, Document, Comments
    FROM ingenieria_items
    WHERE ID = :id
");
$stmt->execute([':id' => $id]);
$item = $stmt->fetch();

if (!$item) {
  http_response_code(404);
  exit('Material no encontrado.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (!isset($_POST['csrf']) || !csrf_validate($_POST['csrf'])) {
    $errors[] = 'Sesión expirada. Vuelve a intentar.';
  }
  $reason = trim((string)($_POST['reason'] ?? ''));
  if ($reason === '') $errors[] = 'Debes especificar el motivo de Scrap.';

  if (!$errors) {
    if ((string)$item['Status'] === 'Scrap') {
      header('Location: ingenieria_scrap.php');
      exit;
    }
    try {
      $pdo->beginTransaction();
      $pdo->prepare("
                UPDATE ingenieria_items
                SET Status='Scrap',
                    Comments=CONCAT(COALESCE(Comments,''),CASE WHEN COALESCE(Comments,'')='' THEN '' ELSE '\n' END,'[Scrap] ',:reason),
                    UpdatedAt=NOW()
                WHERE ID=:id
            ")->execute([':reason' => $reason, ':id' => $id]);

      $pdo->prepare("
                INSERT INTO ingenieria_history
                  (IngenieriaID,Action,Description,Brand,Model,SerialNumber,
                   Location,Department,Owner,Status,Picture,Document,Comments,CreatedAt)
                VALUES
                  (:iid,'scrap',:desc,:brand,:model,:sn,
                   :loc,:dept,:owner,'Scrap',:pic,:doc,:comm,NOW())
            ")->execute([
        ':iid'  => $item['ID'],
        ':desc'  => $item['Description'],
        ':brand' => $item['Brand'],
        ':model' => $item['Model'],
        ':sn'   => $item['SerialNumber'],
        ':loc' => $item['Location'],
        ':dept' => $item['Department'],
        ':owner' => $item['Owner'],
        ':pic'  => $item['Picture'],
        ':doc' => $item['Document'],
        ':comm' => trim(($item['Comments'] ? ($item['Comments'] . "\n") : '') . '[Scrap] ' . $reason),
      ]);
      $pdo->commit();
      header('Location: ingenieria_scrap.php');
      exit;
    } catch (Throwable $e) {
      if ($pdo->inTransaction()) $pdo->rollBack();
      $errors[] = 'Error al enviar a Scrap: ' . $e->getMessage();
    }
  }
}

include __DIR__ . '/partials/header.php';
?>

<div class="row justify-content-center">
  <div class="col-12 col-lg-8 col-xl-7">
    <div class="d-flex align-items-center gap-3 my-3">
      <a href="ingenieria_scrap.php" class="btn btn-outline-secondary btn-sm">
        <i class="fa fa-arrow-left"></i>
      </a>
      <h1 class="h4 m-0">Enviar a Scrap</h1>
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
          <?php $badge = ($item['Status'] === 'Scrap') ? 'badge-ven' : 'badge-cal'; ?>
          <div><span class="badge badge-state <?= $badge ?>"><?= h($item['Status']) ?></span></div>
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
                <i class="fa fa-file-pdf me-1"></i>Documento
              </a>
            </div>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <?php if ($errors): ?>
      <div class="alert alert-danger">
        <ul class="m-0 ps-3"><?php foreach ($errors as $err): ?><li><?= h($err) ?></li><?php endforeach; ?></ul>
      </div>
    <?php endif; ?>

    <?php if ($item['Status'] === 'Scrap'): ?>
      <div class="alert alert-secondary">Este material ya se encuentra en <strong>Scrap</strong>.</div>
      <a href="ingenieria_scrap.php" class="btn btn-outline-secondary">Ver listado Scrap</a>
    <?php else: ?>
      <form method="POST" action="ingenieria_scrap.php" class="needs-validation" novalidate>
        <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
        <input type="hidden" name="id" value="<?= h($item['ID']) ?>">
        <div class="mb-3">
          <label for="reason" class="form-label">Motivo de Scrap <span class="text-danger">*</span></label>
          <textarea id="reason" name="reason" class="form-control" rows="3"
            placeholder="Describe la razón por la que se da de baja…" required></textarea>
          <div class="form-text">Se registrará en el historial y en los comentarios del material.</div>
        </div>
        <div class="d-flex gap-2">
          <a href="ingenieria_scrap.php" class="btn btn-outline-secondary">Cancelar</a>
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
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
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
      document.getElementById('previewImage').src = ev.relatedTarget?.dataset.src || '';
    });
    imgModal.addEventListener('hidden.bs.modal', () => {
      document.getElementById('previewImage').src = '';
    });
  }
</script>

<?php include __DIR__ . '/partials/footer.php'; ?>