<?php
// /var/www/html/calibraciones/return_to_active.php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_auth('admin');

function h(?string $s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

$id = $_GET['id'] ?? $_POST['id'] ?? null;
if (!$id || !preg_match('/^[A-Za-z0-9._-]+$/', $id)) {
    http_response_code(400);
    exit('ID inválido.');
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf']) || !csrf_validate($_POST['csrf'])) {
        $errors[] = 'Sesión expirada. Intenta de nuevo.';
    }

    if (!$errors) {
        try {
            $pdo = pdo();
            $stmt = $pdo->prepare("CALL ReturnInstrumentToActive(:id)");
            $stmt->execute([':id' => $id]);
            while ($stmt->nextRowset()) {}
            $stmt->closeCursor();

            header('Location: out_of_use.php');
            exit;
        } catch (Throwable $e) {
            $errors[] = 'Error al regresar a uso: ' . $e->getMessage();
        }
    }
}
?>
<?php include __DIR__ . '/partials/header.php'; ?>

<div class="row justify-content-center">
  <div class="col-12 col-md-8 col-lg-6">
    <h1 class="h4 my-3">Regresar instrumento a uso</h1>

    <?php if ($errors): ?>
      <div class="alert alert-danger">
        <ul class="m-0 ps-3">
          <?php foreach ($errors as $err): ?><li><?= h($err) ?></li><?php endforeach; ?>
        </ul>
      </div>
    <?php endif; ?>

    <div class="card p-3">
      <form method="POST" action="return_to_active.php">
        <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
        <input type="hidden" name="id" value="<?= h($id) ?>">

        <div class="mb-3">
          <label class="form-label">ID</label>
          <input type="text" class="form-control" value="<?= h($id) ?>" disabled>
        </div>

        <div class="d-flex gap-2">
          <a href="out_of_use.php" class="btn btn-outline-secondary">Cancelar</a>
          <button type="submit" class="btn btn-primary">Regresar a uso</button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php include __DIR__ . '/partials/footer.php'; ?>
