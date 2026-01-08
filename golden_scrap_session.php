<?php
// /var/www/html/calibraciones/golden_scrap_session.php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_auth(['admin','ingenieria']);

function h(?string $s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

$pdo = pdo();
$username = $_SESSION['username'] ?? 'manual';

// --- ACTIONS ---
$action = $_REQUEST['action'] ?? 'index';

// 0. Search API (MUST BE BEFORE HTML OUTPUT)
if ($action === 'search_api') {
    $q = $_GET['q'] ?? '';
    $rows = $pdo->prepare("SELECT ID, Description, Brand, Model, SerialNumber FROM golden_items WHERE Status='Activo' AND (ID LIKE ? OR SerialNumber LIKE ? OR Model LIKE ?) LIMIT 5");
    $rows->execute(["%$q%","%$q%","%$q%"]);
    $res = $rows->fetchAll();
    header('Content-Type: application/json');
    echo json_encode($res);
    exit;
}

// 1. Create/Open Session
if ($action === 'create_session') {
    try {
        $st = $pdo->prepare("INSERT INTO golden_scrap_batches (User, Status, Comments) VALUES (?, 'Open', '')");
        $st->execute([$username]);
        header('Location: golden_scrap_session.php');
        exit;
    } catch (Throwable $e) {
        die("Error creando sesión: ".$e->getMessage());
    }
}

// 2. Fetch Open Session
$session = $pdo->query("SELECT * FROM golden_scrap_batches WHERE Status='Open' ORDER BY BatchID DESC LIMIT 1")->fetch();

// 3. Add Item to Session
if ($action === 'add_item' && $session) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $desc = trim($_POST['description'] ?? '');
        $brand = trim($_POST['brand'] ?? '');
        $model = trim($_POST['model'] ?? '');
        $serial = trim($_POST['serial'] ?? '');
        $notes = trim($_POST['notes'] ?? '');
        $goldenId = trim($_POST['golden_id'] ?? ''); // Optional, if linked
        
        // Photo upload
        $picPath = null;
        if (!empty($_FILES['photo']['name']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg','jpeg','png','webp'])) {
                $dir = __DIR__ . '/uploads/scrap_batches/' . $session['BatchID'];
                if (!is_dir($dir)) mkdir($dir, 0777, true);
                $newName = 'scrap_' . uniqid() . '.' . $ext;
                if (move_uploaded_file($_FILES['photo']['tmp_name'], $dir . '/' . $newName)) {
                    $picPath = 'uploads/scrap_batches/' . $session['BatchID'] . '/' . $newName;
                }
            }
        }

        if ($desc || $model || $serial) {
            $stmt = $pdo->prepare("INSERT INTO golden_scrap_batch_items (BatchID, GoldenID, Description, Brand, Model, SerialNumber, Picture, Notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$session['BatchID'], $goldenId ?: null, $desc, $brand, $model, $serial, $picPath, $notes]);
        }
        header('Location: golden_scrap_session.php');
        exit;
    }
}

// 4. Delete Item
if ($action === 'delete_item' && $session) {
    $did = $_GET['detail_id'] ?? 0;
    $pdo->prepare("DELETE FROM golden_scrap_batch_items WHERE DetailID=? AND BatchID=?")->execute([$did, $session['BatchID']]);
    header('Location: golden_scrap_session.php');
    exit;
}

// 5. Close Session & Process
if ($action === 'close_session' && $session) {
    // Logic: Update all linked Golden Items to 'Scrap' status in main inventory
    // Then close batch.
    try {
        $pdo->beginTransaction();
        
        // Fetch all items with GoldenID
        $items = $pdo->query("SELECT * FROM golden_scrap_batch_items WHERE BatchID=".$session['BatchID']." AND GoldenID IS NOT NULL AND GoldenID != ''")->fetchAll();
        
        $updItem = $pdo->prepare("UPDATE golden_items SET Status='Scrap', Comments=CONCAT(COALESCE(Comments,''), '\n[Scrap Batch #".$session['BatchID']."] ', ?) WHERE ID=?");
        $insHist = $pdo->prepare("INSERT INTO golden_history (GoldenID, Action, Status, Comments, CreatedAt) VALUES (?, 'scrap', 'Scrap', ?, NOW())");

        foreach ($items as $itm) {
            // Update Inventory
            $updItem->execute([$itm['Notes'], $itm['GoldenID']]);
            // History
            $insHist->execute([$itm['GoldenID'], "Scrap Batch #".$session['BatchID']]);
        }

        // Close Batch
        $pdo->prepare("UPDATE golden_scrap_batches SET Status='Closed' WHERE BatchID=?")->execute([$session['BatchID']]);

        $pdo->commit();
        header('Location: golden_scrap_report_print.php?id='.$session['BatchID']); // Redirect to report
        exit;

    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        die("Error cerrando sesión: ".$e->getMessage());
    }
}

// VIEW DATA
$items = [];
if ($session) {
    try {
        $items = $pdo->query("SELECT * FROM golden_scrap_batch_items WHERE BatchID=".((int)$session['BatchID'])." ORDER BY DetailID DESC")->fetchAll();
    } catch(Exception $e) { $items = []; }
}

?>
<?php include __DIR__ . '/partials/header.php'; ?>

<div class="row mb-4">
    <div class="col-12">
        <h1 class="h3 fw-bold">Registro de Scrap (Lotes)</h1>
        <p class="text-secondary">Capture múltiple material para dar de baja y generar un reporte consolidado.</p>
    </div>
</div>

<?php if (!$session): ?>
    <div class="text-center py-5">
        <div class="mb-3"><i class="fa fa-boxes-packing fa-3x text-secondary"></i></div>
        <h3>No hay sesión activa</h3>
        <p class="text-muted">Inicie una nueva sesión para comenzar a registrar material de scrap.</p>
        <a href="?action=create_session" class="btn btn-primary btn-lg"><i class="fa fa-plus-circle me-2"></i>Nueva Sesión de Scrap</a>
        
        <hr class="my-5">
        <h5 class="text-start">Historial de Reportes</h5>
        <div class="table-responsive text-start">
            <table class="table table-hover table-sm">
                <thead><tr><th>ID</th><th>Fecha</th><th>Usuario</th><th>Items</th><th>Acción</th></tr></thead>
                <tbody>
                    <?php 
                    $hist = $pdo->query("SELECT b.*, COUNT(i.DetailID) as Cnt FROM golden_scrap_batches b LEFT JOIN golden_scrap_batch_items i ON b.BatchID=i.BatchID WHERE b.Status='Closed' GROUP BY b.BatchID ORDER BY b.BatchID DESC LIMIT 10")->fetchAll();
                    foreach($hist as $h): ?>
                    <tr>
                        <td>#<?= $h['BatchID'] ?></td>
                        <td><?= date('d/m/Y H:i', strtotime($h['BatchDate'])) ?></td>
                        <td><?= h($h['User']) ?></td>
                        <td><?= $h['Cnt'] ?></td>
                        <td><a href="golden_scrap_report_print.php?id=<?= $h['BatchID'] ?>" target="_blank" class="btn btn-sm btn-outline-dark"><i class="fa fa-print"></i></a></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php else: ?>
    <!-- ACTIVE SESSION UI -->
    <div class="card border-secondary mb-4 shadow-sm">
        <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
            <span class="fw-bold"><i class="fa fa-box-open me-2"></i>SESIÓN ACTIVA #<?= $session['BatchID'] ?></span>
            <div>
                <span class="badge bg-warning text-dark me-2">Items: <?= count($items) ?></span>
                <span class="badge bg-secondary border border-light px-2 rounded"><?= date('d/m/Y', strtotime($session['BatchDate'])) ?></span>
            </div>
        </div>
        <div class="card-body bg-secondary text-white">
             <!-- FORM ADD -->
             <form method="POST" action="?action=add_item" enctype="multipart/form-data" class="row g-3">
                <input type="hidden" name="golden_id" id="golden_id_input"> <!-- Populated by search -->
                
                <div class="col-12">
                    <label class="form-label fw-bold">Buscar en Inventario (Opcional)</label>
                    <div class="input-group">
                        <input type="text" class="form-control" id="search_input" placeholder="Escribe Serie, Modelo o ID para autocompletar...">
                        <button class="btn btn-outline-secondary" type="button" onclick="searchInventory()"><i class="fa fa-search"></i></button>
                    </div>
                    <div id="search_results" class="list-group mt-1 position-absolute w-100" style="z-index:999; display:none;"></div>
                </div>

                <div class="col-md-3">
                     <label class="form-label small">Marca</label>
                     <input type="text" name="brand" id="brand" class="form-control form-control-sm">
                </div>
                <div class="col-md-3">
                     <label class="form-label small">Modelo</label>
                     <input type="text" name="model" id="model" class="form-control form-control-sm">
                </div>
                <div class="col-md-3">
                     <label class="form-label small">Serial Number</label>
                     <input type="text" name="serial" id="serial" class="form-control form-control-sm" required>
                </div>
                <div class="col-md-3">
                     <label class="form-label small">Descripción</label>
                     <input type="text" name="description" id="description" class="form-control form-control-sm">
                </div>
                <div class="col-md-6">
                     <label class="form-label small">Notas / Motivo</label>
                     <textarea name="notes" class="form-control form-control-sm" rows="1"></textarea>
                </div>
                <div class="col-md-4">
                     <label class="form-label small">Foto Evidencia</label>
                     <input type="file" name="photo" class="form-control form-control-sm" accept="image/*">
                </div>
                <div class="col-md-2 d-grid">
                     <label class="form-label small">&nbsp;</label>
                     <button type="submit" class="btn btn-success btn-sm fw-bold"><i class="fa fa-plus"></i> AGREGAR</button>
                </div>
             </form>
        </div>
    </div>

    <!-- LIST -->
    <div class="table-responsive mb-4">
        <h5 class="mb-2">Items registradors en esta sesión:</h5>
        <table class="table table-bordered table-hover align-middle bg-white text-dark">
            <thead class="table-light">
                <tr>
                    <th width="50">Foto</th>
                    <th>Info Material</th>
                    <th>S/N</th>
                    <th>Notas</th>
                    <th width="50"></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($items as $itm): ?>
                <tr>
                    <td>
                        <?php if($itm['Picture']): ?>
                            <a href="<?= h($itm['Picture']) ?>" target="_blank"><img src="<?= h($itm['Picture']) ?>" style="height:40px;"></a>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if($itm['GoldenID']): ?><span class="badge bg-warning text-dark"><?= h($itm['GoldenID']) ?></span><br><?php endif; ?>
                        <strong><?= h($itm['Brand']) ?></strong> <?= h($itm['Model']) ?><br>
                        <small class="text-muted"><?= h($itm['Description']) ?></small>
                    </td>
                    <td class="font-monospace text-danger"><?= h($itm['SerialNumber']) ?></td>
                    <td><?= h($itm['Notes']) ?></td>
                    <td>
                        <a href="?action=delete_item&detail_id=<?= $itm['DetailID'] ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('¿Quitar este item?')"><i class="fa fa-trash"></i></a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
        <a href="?action=close_session" class="btn btn-lg btn-danger px-5 fw-bold" onclick="return confirm('¿Finalizar lote? Esto marcará los items vinculados como SCRAP en el inventario.')">
            <i class="fa fa-file-pdf me-2"></i> TERMINAR Y GENERAR REPORTE
        </a>
    </div>

    <script>
    const searchInput = document.getElementById('search_input');
    const resultsDiv = document.getElementById('search_results');

    if(searchInput) {
        searchInput.addEventListener('input', function() {
            const q = this.value;
            if(q.length < 2) { resultsDiv.style.display='none'; return; }
            
            fetch('golden_scrap_session.php?action=search_api&q='+encodeURIComponent(q))
            .then(r=>r.json())
            .then(data => {
                resultsDiv.innerHTML = '';
                if(data.length > 0) {
                    resultsDiv.style.display = 'block';
                    data.forEach(item => {
                        const a = document.createElement('a');
                        a.className = 'list-group-item list-group-item-action list-group-item-dark';
                        a.innerHTML = `<strong>${item.ID}</strong> - ${item.Brand} ${item.Model} (<span class="text-danger">${item.SerialNumber}</span>)`;
                        a.href = '#';
                        a.onclick = (e) => {
                            e.preventDefault();
                            document.getElementById('golden_id_input').value = item.ID;
                            document.getElementById('brand').value = item.Brand;
                            document.getElementById('model').value = item.Model;
                            document.getElementById('serial').value = item.SerialNumber;
                            document.getElementById('description').value = item.Description;
                            resultsDiv.style.display = 'none';
                        };
                        resultsDiv.appendChild(a);
                    });
                } else {
                    resultsDiv.style.display = 'none';
                }
            })
            .catch(err => console.error(err));
        });

        document.addEventListener('click', function(e) {
            if (!searchInput.contains(e.target) && !resultsDiv.contains(e.target)) {
                resultsDiv.style.display = 'none';
            }
        });
    }
    </script>
<?php endif; ?>



<?php include __DIR__ . '/partials/footer.php'; ?>
