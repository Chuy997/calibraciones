<?php
// /var/www/html/calibraciones/golden_package_view.php
declare(strict_types=1);

require_once __DIR__.'/config.php';
require_auth(['admin','ingenieria']);

$pdo = pdo();
$id = $_GET['id'] ?? '';

if (!$id) {
    header("Location: golden_packages.php");
    exit;
}

// Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf']) || !csrf_validate($_POST['csrf'])) die("Error de sesión.");
    
    $action = $_POST['action'] ?? '';

    if ($action === 'update_location') {
        $newLoc = trim($_POST['location'] ?? '');
        if ($newLoc) {
            $pdo->beginTransaction();
            // Update Package
            $stmt = $pdo->prepare("UPDATE golden_packages SET Location = :loc WHERE PackageID = :id");
            $stmt->execute([':loc' => $newLoc, ':id' => $id]);
            
            // Update Items
            $stmt2 = $pdo->prepare("UPDATE golden_items SET Location = :loc WHERE PackageID = :id");
            $stmt2->execute([':loc' => $newLoc, ':id' => $id]);

            // Log
            // (Simplified log for brevity, ideally would log per item)
            $pdo->commit();
        }
    }
    elseif ($action === 'disband') {
        // Disband package
        $pdo->beginTransaction();
        // Archive Package
        $stmt = $pdo->prepare("UPDATE golden_packages SET Status = 'Archived' WHERE PackageID = :id");
        $stmt->execute([':id' => $id]);
        
        // Release items
        $stmt2 = $pdo->prepare("UPDATE golden_items SET PackageID = NULL WHERE PackageID = :id");
        $stmt2->execute([':id' => $id]);
        
        $pdo->commit();
        header("Location: golden_packages.php");
        exit;
    }

    // Refresh to show changes
    header("Location: golden_package_view.php?id=$id");
    exit;
}

// Fetch Package
$stmt = $pdo->prepare("SELECT * FROM golden_packages WHERE PackageID = :id");
$stmt->execute([':id' => $id]);
$pkg = $stmt->fetch();

if (!$pkg) die("Paquete no encontrado.");

// Fetch Items
$stmtItems = $pdo->prepare("SELECT * FROM golden_items WHERE PackageID = :id ORDER BY ID ASC");
$stmtItems->execute([':id' => $id]);
$items = $stmtItems->fetchAll();

function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
?>
<?php include __DIR__.'/partials/header.php'; ?>

<div class="row mb-4">
    <div class="col-12 d-flex justify-content-between align-items-center">
        <h1 class="h3">Paquete: <?= h($pkg['Name']) ?></h1>
        
        <div class="btn-group">
            <a href="golden_packages.php" class="btn btn-outline-secondary"><i class="fa fa-arrow-left"></i> Volver</a>
            <a href="golden_package_label.php?id=<?= h($pkg['PackageID']) ?>" target="_blank" class="btn btn-dark">
                <i class="fa fa-print me-1"></i> Imprimir Etiqueta
            </a>
            <?php if($pkg['Status'] === 'Activo'): ?>
            <button class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#disbandModal">
                <i class="fa fa-trash-can me-1"></i> Desagrupar
            </button>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-4 mb-3">
        <div class="card h-100">
            <div class="card-header fw-bold">Detalles</div>
            <div class="card-body">
                <p class="mb-1 text-muted small">ID Paquete</p>
                <p class="font-monospace user-select-all"><?= h($pkg['PackageID']) ?></p>
                
                <p class="mb-1 text-muted small">Descripción</p>
                <p><?= h($pkg['Description'] ?: 'Sin descripción') ?></p>

                <p class="mb-1 text-muted small">Ubicación Actual</p>
                <div class="d-flex justify-content-between align-items-center">
                    <span class="fs-5 fw-bold text-primary"><?= h($pkg['Location']) ?></span>
                    <?php if($pkg['Status'] === 'Activo'): ?>
                    <button class="btn btn-sm btn-link" data-bs-toggle="modal" data-bs-target="#locationModal">Cambiar</button>
                    <?php endif; ?>
                </div>
                
                <p class="mb-1 text-muted small mt-2">Estado</p>
                <p>
                    <span class="badge <?= $pkg['Status'] === 'Activo' ? 'bg-success' : 'bg-secondary' ?>">
                        <?= h($pkg['Status']) ?>
                    </span>
                </p>

                <p class="mb-1 text-muted small mt-2">Creado por</p>
                <p><?= h($pkg['CreatedBy']) ?> <small class="text-muted">(<?= $pkg['CreatedAt'] ?>)</small></p>
            </div>
        </div>
    </div>

    <div class="col-md-8 mb-3">
        <div class="card h-100">
            <div class="card-header fw-bold">Contenido (<?= count($items) ?> items)</div>
            <div class="table-responsive">
                <table class="table table-striped mb-0">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Descripción</th>
                            <th>Serie</th>
                            <th>Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($items as $item): ?>
                        <tr>
                            <td><a href="golden_history.php?id=<?= h($item['ID']) ?>"><?= h($item['ID']) ?></a></td>
                            <td>
                                <?= h($item['Description']) ?><br>
                                <small class="text-muted"><?= h($item['Brand']) ?> <?= h($item['Model']) ?></small>
                            </td>
                            <td><?= h($item['SerialNumber']) ?></td>
                            <td><?= h($item['Status']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Modal Location -->
<div class="modal fade" id="locationModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" class="modal-content">
            <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
            <input type="hidden" name="action" value="update_location">
            <div class="modal-header">
                <h5 class="modal-title">Actualizar Ubicación</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Esta acción actualizará la ubicación de <strong>todos los items</strong> en este paquete.</p>
                <div class="mb-3">
                    <label class="form-label">Nueva Ubicación</label>
                    <input type="text" name="location" class="form-control" required value="<?= h($pkg['Location']) ?>">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-primary">Guardar</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Disband -->
<div class="modal fade" id="disbandModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" class="modal-content">
            <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
            <input type="hidden" name="action" value="disband">
            <div class="modal-header">
                <h5 class="modal-title">Desagrupar Paquete</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="text-danger">¿Estás seguro de que quieres desagrupar este paquete?</p>
                <p>El paquete <strong><?= h($pkg['PackageID']) ?></strong> se archivará y los items volverán a estar disponibles individualmente.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-danger">Confirmar Desagrupación</button>
            </div>
        </form>
    </div>
</div>

<?php include __DIR__.'/partials/footer.php'; ?>
