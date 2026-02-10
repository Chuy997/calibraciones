<?php
// /var/www/html/calibraciones/ingenieria_package_create.php
declare(strict_types=1);

require_once __DIR__.'/config.php';
require_auth(['admin','ingenieria']);

$pdo = pdo();
$errors = [];

// --- POST: Create Package ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf']) || !csrf_validate($_POST['csrf'])) {
        $errors[] = 'Error de sesión. Intente nuevamente.';
    } else {
        $name = trim($_POST['name'] ?? '');
        $desc = trim($_POST['description'] ?? '');
        $loc  = trim($_POST['location'] ?? '');
        $selectedItems = $_POST['items'] ?? [];

        if ($name === '') $errors[] = 'El nombre del paquete es obligatorio.';
        if (empty($selectedItems)) $errors[] = 'Debe seleccionar al menos un ítem.';

        if (!$errors) {
            try {
                $pdo->beginTransaction();

                // Generate PackageID (IPKG for Ingenieria Package)
                $pkgId = 'IPKG-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));
                
                // Insert Package
                $stmt = $pdo->prepare("
                    INSERT INTO ingenieria_packages (PackageID, Name, Description, Location, CreatedBy)
                    VALUES (:id, :name, :desc, :loc, :user)
                ");
                $stmt->execute([
                    ':id' => $pkgId,
                    ':name' => $name,
                    ':desc' => $desc,
                    ':loc' => $loc,
                    ':user' => $_SESSION['username']
                ]);

                // Update Items
                $placeholders = implode(',', array_fill(0, count($selectedItems), '?'));
                $upd = $pdo->prepare("UPDATE ingenieria_items SET PackageID = ?, Location = ? WHERE ID IN ($placeholders)");
                $upd->execute(array_merge([$pkgId, $loc], $selectedItems));

                // Log History
                $hist = $pdo->prepare("
                    INSERT INTO ingenieria_history (IngenieriaID, Action, Description, Status, Comments, CreatedAt)
                    SELECT ID, 'package_add', Description, Status, CONCAT('Added to Package ', :pkgId), NOW()
                    FROM ingenieria_items WHERE PackageID = :pkgId2
                ");
                $hist->execute([':pkgId' => $pkgId, ':pkgId2' => $pkgId]);

                $pdo->commit();
                header("Location: ingenieria_packages.php");
                exit;

            } catch (Throwable $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                $errors[] = 'Error al crear paquete: ' . $e->getMessage();
            }
        }
    }
}

// --- GET: Fetch available items ---
// Only items not in a package and Active
$items = $pdo->query("
    SELECT ID, Description, Brand, Model, SerialNumber, Location 
    FROM ingenieria_items 
    WHERE PackageID IS NULL AND Status = 'Activo'
    ORDER BY ID ASC
")->fetchAll();

?>
<?php include __DIR__.'/partials/header.php'; ?>

<div class="row justify-content-center">
    <div class="col-12 col-xl-10">
        <h1 class="h4 my-3">Crear Paquete de Ingeniería</h1>

        <?php if ($errors): ?>
            <div class="alert alert-danger">
                <ul class="mb-0">
                    <?php foreach ($errors as $e) echo "<li>".htmlspecialchars($e)."</li>"; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form method="POST" class="needs-validation" novalidate onsubmit="return validateForm()">
            <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
            
            <div class="card mb-4">
                <div class="card-header bg-dark text-white">Información del Paquete</div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Nombre del Paquete <span class="text-danger">*</span></label>
                            <input type="text" name="name" class="form-control" required placeholder="Ej. Kit de Herramientas B">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Ubicación Inicial</label>
                            <input type="text" name="location" class="form-control" placeholder="Ej. Gabinete 3">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Descripción</label>
                            <textarea name="description" class="form-control" rows="2"></textarea>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
                    <span>Selección de Materiales</span>
                    <input type="text" id="itemSearch" class="form-control form-control-sm w-auto" placeholder="Filtrar items...">
                </div>
                <div class="card-body p-0" style="max-height: 500px; overflow-y: auto;">
                    <table class="table table-hover table-striped mb-0" id="itemsTable">
                        <thead class="table-light sticky-top">
                            <tr>
                                <th style="width: 40px;"><input type="checkbox" id="selectAll" class="form-check-input"></th>
                                <th>ID</th>
                                <th>Descripción</th>
                                <th>Marca / Modelo</th>
                                <th>Serie</th>
                                <th>Ubicación Actual</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!$items): ?>
                                <tr><td colspan="6" class="text-center py-3">No hay materiales disponibles.</td></tr>
                            <?php else: foreach ($items as $item): ?>
                                <tr>
                                    <td>
                                        <input type="checkbox" name="items[]" value="<?= htmlspecialchars($item['ID']) ?>" class="form-check-input item-check">
                                    </td>
                                    <td><code><?= htmlspecialchars($item['ID']) ?></code></td>
                                    <td><?= htmlspecialchars($item['Description']) ?></td>
                                    <td>
                                        <small><?= htmlspecialchars($item['Brand']) ?> <?= htmlspecialchars($item['Model']) ?></small>
                                    </td>
                                    <td><small><?= htmlspecialchars($item['SerialNumber']) ?></small></td>
                                    <td><small><?= htmlspecialchars($item['Location']) ?></small></td>
                                </tr>
                            <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
                <div class="card-footer text-muted small">
                    <span id="selectedCount">0</span> items seleccionados
                </div>
            </div>

            <div class="d-flex gap-2 justify-content-end">
                <a href="ingenieria_packages.php" class="btn btn-outline-secondary">Cancelar</a>
                <button type="submit" class="btn btn-primary">Crear Paquete</button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const table = document.getElementById('itemsTable');
    const search = document.getElementById('itemSearch');
    const selectAll = document.getElementById('selectAll');
    const checkboxes = document.querySelectorAll('.item-check');
    const countSpan = document.getElementById('selectedCount');

    // Filter
    search.addEventListener('input', (e) => {
        const term = e.target.value.toLowerCase();
        Array.from(table.tBodies[0].rows).forEach(row => {
            const text = row.innerText.toLowerCase();
            row.style.display = text.includes(term) ? '' : 'none';
        });
    });

    // Select All
    selectAll.addEventListener('change', (e) => {
        const visibleRows = Array.from(table.tBodies[0].rows).filter(r => r.style.display !== 'none');
        visibleRows.forEach(row => {
            const chk = row.querySelector('.item-check');
            if (chk) chk.checked = e.target.checked;
        });
        updateCount();
    });

    // Update Count
    checkboxes.forEach(chk => chk.addEventListener('change', updateCount));

    function updateCount() {
        const checked = document.querySelectorAll('.item-check:checked').length;
        countSpan.textContent = checked;
    }
});

function validateForm() {
    const checked = document.querySelectorAll('.item-check:checked').length;
    if (checked === 0) {
        alert('Por favor selecciona al menos un ítem para el paquete.');
        return false;
    }
    return true;
}
</script>

<?php include __DIR__.'/partials/footer.php'; ?>
