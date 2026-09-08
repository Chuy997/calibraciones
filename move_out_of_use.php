<?php
// /var/www/html/calibraciones/move_out_of_use.php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_auth('admin'); // solo admins

function h(?string $s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

// Obtener ID por GET (mostrar form) o por POST (procesar)
$id = $_GET['id'] ?? $_POST['id'] ?? null;
if (!$id || !preg_match('/^[A-Za-z0-9._-]+$/', $id)) {
    http_response_code(400);
    exit('ID inválido.');
}

$errors = [];
$reason  = $_POST['ReasonForRemoval'] ?? '';
$comment = $_POST['RemovalComment'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF
    if (!isset($_POST['csrf']) || !csrf_validate($_POST['csrf'])) {
        $errors[] = 'Sesión expirada. Intenta de nuevo.';
    }
    // Validar razón (mismas opciones que manejas en UI/BD)
    $allowedReasons = ['Obsoleto', 'Fuera de Calibración', 'No Funciona', 'Scrap'];
    if (!in_array($reason, $allowedReasons, true)) {
        $errors[] = 'Razón inválida.';
    }

    if (!$errors) {
        try {
            $pdo = pdo();

            // Fuerza charset y collation de la conexión para evitar mixes
            $pdo->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
            $pdo->exec("SET collation_connection = utf8mb4_unicode_ci");

            // Llamada al procedimiento almacenado
            // Nota: si tu SP compara cadenas, ahora la conexión y los literales usan unicode_ci
            $stmt = $pdo->prepare("CALL MoveInstrumentOutOfUse(:id, :reason, :comment)");
            $stmt->execute([':id' => $id, ':reason' => $reason, ':comment' => $comment]);

            // Consumir posibles resultsets extra de CALL
            while ($stmt->nextRowset()) {}
            $stmt->closeCursor();

            header('Location: admin.php');
            exit;
        } catch (Throwable $e) {
            $errors[] = 'Error al mover fuera de uso: ' . $e->getMessage();
        }
    }
}
?>
<?php include __DIR__ . '/partials/header.php'; ?>

<div class="row justify-content-center">
  <div class="col-12 col-md-8 col-lg-6">
    <h1 class="h4 my-3">Mover instrumento fuera de uso</h1>

    <?php if ($errors): ?>
      <div class="alert alert-danger">
        <ul class="m-0 ps-3">
          <?php foreach ($errors as $err): ?><li><?= h($err) ?></li><?php endforeach; ?>
        </ul>
      </div>
    <?php endif; ?>

    <div class="card p-3">
      <form method="POST" action="move_out_of_use.php">
        <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
        <input type="hidden" name="id" value="<?= h($id) ?>">

        <div class="mb-3">
          <label class="form-label">ID</label>
          <input type="text" class="form-control" value="<?= h($id) ?>" disabled>
        </div>

        <div class="mb-3">
          <label for="ReasonForRemoval" class="form-label">Razón</label>
          <select class="form-select" id="ReasonForRemoval" name="ReasonForRemoval" required>
            <?php
              $opts = ['Obsoleto','Fuera de Calibración','No Funciona','Scrap'];
              foreach ($opts as $opt):
            ?>
              <option value="<?= h($opt) ?>" <?= ($reason===$opt)?'selected':'' ?>><?= h($opt) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="mb-3">
          <label for="RemovalComment" class="form-label">Comentario <span class="text-muted">(opcional)</span></label>
          <textarea class="form-control" id="RemovalComment" name="RemovalComment" rows="3"
                    placeholder="Describe el motivo o detalle adicional..."><?= h($comment) ?></textarea>
        </div>

        <div class="d-flex gap-2">
          <a href="admin.php" class="btn btn-outline-secondary">Cancelar</a>
          <button type="submit" class="btn btn-primary">Mover fuera de uso</button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php include __DIR__ . '/partials/footer.php'; ?>
