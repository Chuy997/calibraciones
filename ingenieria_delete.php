<?php
// /var/www/html/calibraciones/ingenieria_delete.php
// FUNCIÓN DESHABILITADA
declare(strict_types=1);

require_once __DIR__.'/config.php';
require_auth(['admin','ingenieria']);

http_response_code(403);
header('Location: ingenieria_admin.php');
exit;

function h(?string $s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

$pdo    = pdo();
$errors = [];

// --- Obtener y validar ID ---
$id = $_GET['id'] ?? $_POST['id'] ?? '';
if ($id === '' || !preg_match('/^[A-Za-z0-9._-]+$/', $id)) {
    http_response_code(400);
    exit('ID no proporcionado o inválido.');
}

// --- Cargar registro actual ---
$stmt = $pdo->prepare("
    SELECT ID, Description, Brand, Model, SerialNumber,
           Location, Department, Owner, Status, Picture, Document, Comments, Qty
    FROM ingenieria_items
    WHERE ID = :id
");
$stmt->execute([':id' => $id]);
$item = $stmt->fetch();

if (!$item) {
    http_response_code(404);
    exit('Material no encontrado.');
}

// --- POST: procesar eliminación definitiva ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF
    if (!isset($_POST['csrf']) || !csrf_validate($_POST['csrf'])) {
        $errors[] = 'Sesión expirada. Vuelve a intentar.';
    }

    $reason = trim((string)($_POST['reason'] ?? ''));
    if ($reason === '') {
        $errors[] = 'Debes especificar el motivo de la eliminación.';
    }

    if (!$errors) {
        try {
            $pdo->beginTransaction();

            // 1. Registrar en historial antes de borrar
            $hst = $pdo->prepare("
                INSERT INTO ingenieria_history
                  (IngenieriaID, Action, Description, Brand, Model, SerialNumber,
                   Location, Department, Owner, Status, Picture, Document, Comments, CreatedAt)
                VALUES
                  (:IngenieriaID, 'deleted', :Description, :Brand, :Model, :SerialNumber,
                   :Location, :Department, :Owner, :Status, :Picture, :Document, :Comments, NOW())
            ");
            $hst->execute([
                ':IngenieriaID' => $item['ID'],
                ':Description'  => $item['Description'],
                ':Brand'        => $item['Brand'],
                ':Model'        => $item['Model'],
                ':SerialNumber' => $item['SerialNumber'],
                ':Location'     => $item['Location'],
                ':Department'   => $item['Department'],
                ':Owner'        => $item['Owner'],
                ':Status'       => $item['Status'],
                ':Picture'      => $item['Picture'],
                ':Document'     => $item['Document'],
                ':Comments'     => trim(($item['Comments'] ? ($item['Comments']."\n") : '') . "[Eliminado] " . $reason),
            ]);

            // 2. Eliminar el registro principal
            $del = $pdo->prepare("DELETE FROM ingenieria_items WHERE ID = :id");
            $del->execute([':id' => $id]);

            $pdo->commit();
            header('Location: ingenieria_admin.php?deleted=1');
            exit;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $errors[] = 'Error al eliminar: ' . $e->getMessage();
        }
    }
}
?>
<?php include __DIR__.'/partials/header.php'; ?>

<div class="row justify-content-center">
  <div class="col-12 col-lg-8 col-xl-6">
    <h1 class="h4 my-3 text-danger"><i class="fa fa-trash me-2"></i>Eliminar Material</h1>

    <!-- Tarjeta resumen del material -->
    <div class="card p-3 mb-3 border border-danger border-opacity-50">
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

          <div class="small text-secondary mt-2">Estado actual / Qty</div>
          <?php
            $status = (string)$item['Status'];
            $badge  = $status === 'Scrap' ? 'badge-ven' : 'badge-cal';
          ?>
          <div>
            <span class="badge badge-state <?= $badge ?>"><?= h($status) ?></span>
            <span class="ms-2 text-secondary small">Qty: <?= h((string)($item['Qty'] ?? '—')) ?></span>
          </div>
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

    <!-- Alerta de advertencia -->
    <div class="alert alert-danger d-flex align-items-start gap-2" role="alert">
      <i class="fa fa-triangle-exclamation fa-lg mt-1 flex-shrink-0"></i>
      <div>
        <strong>¡Advertencia!</strong> Esta acción es <strong>permanente e irreversible</strong>.
        El material será eliminado del inventario. Se guardará un registro en el historial antes de eliminarlo.
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

    <!-- Formulario de confirmación -->
    <form method="POST" action="ingenieria_delete.php" class="needs-validation" novalidate
          onsubmit="return confirmDelete()">
      <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
      <input type="hidden" name="id" value="<?= h($item['ID']) ?>">

      <div class="mb-3">
        <label for="reason" class="form-label">
          Motivo de eliminación <span class="text-danger">*</span>
        </label>
        <textarea id="reason" name="reason" class="form-control" rows="3"
                  placeholder="Describe la razón por la que se elimina este material…"
                  required></textarea>
        <div class="form-text">Se registrará en el historial antes de eliminar el registro.</div>
      </div>

      <div class="d-flex gap-2">
        <a href="ingenieria_admin.php" class="btn btn-outline-secondary">
          <i class="fa fa-arrow-left me-1"></i>Cancelar
        </a>
        <button type="submit" class="btn btn-danger" id="btnDelete">
          <i class="fa fa-trash me-2"></i>Eliminar definitivamente
        </button>
      </div>
    </form>
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
function confirmDelete() {
  return confirm('¿Estás seguro de que deseas ELIMINAR este material de forma permanente? Esta acción no se puede deshacer.');
}

// Modal preview imagen
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
</script>

<?php include __DIR__.'/partials/footer.php'; ?>
