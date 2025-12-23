<?php
// /var/www/html/calibraciones/golden_audit.php
declare(strict_types=1);

require_once __DIR__.'/config.php';
require_auth('admin');

function h(?string $s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

$pdo = pdo();
$auditor = $_SESSION['username'] ?? 'Unknown';

// ----------------------------------------------------------------------------
// HANDLERS (POST)
// ----------------------------------------------------------------------------

// 1. Create New Draft (Redirects to Edit)
if (isset($_GET['action']) && $_GET['action'] === 'create_draft') {
    $stmt = $pdo->prepare("INSERT INTO golden_audits (Auditor, Status, AuditDate) VALUES (?, 'Open', NOW())");
    $stmt->execute([$auditor]);
    $newId = $pdo->lastInsertId();
    header("Location: golden_audit.php?action=edit&id=" . $newId);
    exit;
}

// 2. Save / Finalize
if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_audit') {
    $auditId = $_POST['audit_id'] ?? null;
    $status  = $_POST['status_target'] ?? 'Open'; // 'Open' or 'Closed'
    $comments = $_POST['audit_comments'] ?? '';
    $items    = $_POST['items'] ?? []; 

    if (!$auditId) die("ID de auditoría inválido.");

    try {
        $pdo->beginTransaction();

        // Calculate summaries based on what was submitted
        $totalSubmitted = count($items);
        $missing = 0;
        $damaged = 0;

        foreach ($items as $itm) {
            // Checkboxes only send value if checked. We handle this in logic below, but here we iterate keys.
            // Wait, standard form submit works differently. If unchecked, it's missing from POST for simple inputs, 
            // but we need to iterate ALL items to correctly update DB.
            // Actually, we'll iterate the $items array which is keyed by GoldenID.
            
            // Check "physical" key presence for checkbox? No, we will explicitly clear old items and re-insert.
            // For the summary:
            if (!isset($itm['physical'])) { 
                 // If using checkbox value='1', unchecked means unset.
                 // We count it as missing IF we decide unchecked = missing.
                 // However, "Missing" is also a condition.
                 // Logic: Physical=0 implies Missing count.
                 $missing++; 
            }
            if (($itm['condition'] ?? 'Good') === 'Damage') $damaged++;
            if (($itm['condition'] ?? 'Good') === 'Missing') {
                // If Condition says missing, but Physical was checked, it's a contradiction, but usually Missing implies Physical=0
            }
        }

        // We need to re-insert details. 
        // Strategy: DELETE existing details for this AuditID, INSERT new ones.
        // This handles updates easily.
        $pdo->prepare("DELETE FROM golden_audit_items WHERE AuditID = ?")->execute([$auditId]);

        $stmtDetail = $pdo->prepare("INSERT INTO golden_audit_items (AuditID, GoldenID, PhysicalCheck, ConditionCheck, Notes) VALUES (?, ?, ?, ?, ?)");

        // We need to iterate over ALL Active Inventory items to effectively record the state of the WHOLE inventory,
        // OR we just record what the user sent. 
        // Better: The form contains all items. If the user submits, we get all items.
        
        $statMissing = 0;
        $statDamaged = 0;
        $statTotal = 0;

        foreach ($items as $gid => $data) {
            $statTotal++;
            $physical = isset($data['physical']) ? 1 : 0;
            $condition = $data['condition'] ?? 'Good';
            $note = $data['note'] ?? '';

            // Consistency
            if ($condition === 'Missing') $physical = 0;
            if ($physical === 0 && $condition !== 'Missing') {
                // If physically not found, force condition? Or leave as was?
                // Let's force condition to missing if strictly not found? 
                // No, maybe they are just marking it absent temporarily. 
                // But for Audit purposes, Physical=0 usually means Missing.
            }
            
            if ($physical === 0) $statMissing++;
            if ($condition === 'Damage') $statDamaged++;

            $stmtDetail->execute([$auditId, $gid, $physical, $condition, $note]);
        }

        // Update Header
        $sqlHeader = "UPDATE golden_audits SET TotalItems=?, TotalMissing=?, TotalDamaged=?, Comments=?, Status=?, AuditDate=NOW() WHERE AuditID=?";
        $pdo->prepare($sqlHeader)->execute([$statTotal, $statMissing, $statDamaged, $comments, $status, $auditId]);

        $pdo->commit();

        if ($status === 'Closed') {
            header("Location: golden_audit.php?msg=finalized");
        } else {
            header("Location: golden_audit.php?action=edit&id=$auditId&msg=saved");
        }
        exit;

    } catch (Exception $e) {
        $pdo->rollBack();
        die("Error: " . $e->getMessage());
    }
}


// ----------------------------------------------------------------------------
// VIEW LOGIC
// ----------------------------------------------------------------------------

$action = $_GET['action'] ?? 'list';

// LIST VIEW
if ($action === 'list') {
    // Separate Open vs Closed? Or just one list with status
    $audits = $pdo->query("SELECT * FROM golden_audits ORDER BY Status DESC, AuditDate DESC LIMIT 50")->fetchAll();
}

// EDIT VIEW
if ($action === 'edit') {
    $id = $_GET['id'] ?? null;
    if (!$id) die("ID requerido");

    $audit = $pdo->prepare("SELECT * FROM golden_audits WHERE AuditID = ?");
    $audit->execute([$id]);
    $auditRow = $audit->fetch();

    if (!$auditRow) die("Auditoría no encontrada");
    if ($auditRow['Status'] === 'Closed') {
        // If closed, redirect to view mode or show error?
        header("Location: golden_audit_view.php?id=$id");
        exit;
    }

    // Load Audit Details (Previous Saves)
    $savedItemsStmt = $pdo->prepare("SELECT * FROM golden_audit_items WHERE AuditID = ?");
    $savedItemsStmt->execute([$id]);
    $savedItems = [];
    foreach ($savedItemsStmt->fetchAll() as $si) {
        $savedItems[$si['GoldenID']] = $si;
    }

    // Load Live Inventory (We check against updated inventory)
    // We want to show ALL active items.
    $inventory = $pdo->query("SELECT ID, Description, Model, SerialNumber, Location, Picture FROM golden_items WHERE Status='Activo' ORDER BY Location ASC, ID ASC")->fetchAll();
}

?>
<?php include __DIR__.'/partials/header.php'; ?>

<div class="container-fluid">

<!-- TOASTS / ALERTS -->
<?php if (isset($_GET['msg'])): ?>
    <div class="alert alert-success alert-dismissible fade show my-3">
        <?php if ($_GET['msg'] === 'finalized'): ?>
            <i class="fa fa-check-circle me-2"></i>Auditoría finalizada y guardada exitosamente.
        <?php elseif ($_GET['msg'] === 'saved'): ?>
            <i class="fa fa-floppy-disk me-2"></i>Progreso guardado. Puedes continuar más tarde.
        <?php endif; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>


<?php if ($action === 'list'): ?>
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0 fw-bold">Auditorías de Inventario</h1>
            <p class="text-secondary mb-0">Gestiona y dale seguimiento a las revisiones físicas.</p>
        </div>
        <a href="golden_audit.php?action=create_draft" class="btn btn-primary btn-lg shadow-sm">
            <i class="fa fa-plus me-2"></i>Iniciar Nueva Auditoría
        </a>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-4">ID</th>
                        <th>Estado</th>
                        <th>Fecha Última Act.</th>
                        <th>Auditor</th>
                        <th class="text-center">Items</th>
                        <th class="text-center">Hallazgos</th>
                        <th class="text-end pe-4">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($audits)): ?>
                    <tr><td colspan="7" class="text-center py-5 text-muted">No hay registros.</td></tr>
                <?php else: ?>
                    <?php foreach ($audits as $row): 
                        $isOpen = $row['Status'] === 'Open';
                    ?>
                    <tr class="<?= $isOpen ? 'bg-primary bg-opacity-10' : '' ?>">
                        <td class="ps-4 fw-bold">#<?= $row['AuditID'] ?></td>
                        <td>
                            <?php if ($isOpen): ?>
                                <span class="badge bg-primary text-white p-2"><i class="fa fa-pencil me-1"></i> En Progreso</span>
                            <?php else: ?>
                                <span class="badge bg-secondary text-white p-2">Finalizada</span>
                            <?php endif; ?>
                        </td>
                        <td><?= date('d/m/Y H:i', strtotime($row['AuditDate'])) ?></td>
                        <td><?= h($row['Auditor']) ?></td>
                        <td class="text-center"><?= h($row['TotalItems']) ?></td>
                        <td class="text-center">
                            <?php if ($row['TotalMissing'] + $row['TotalDamaged'] > 0): ?>
                                <span class="badge bg-danger"><?= $row['TotalMissing'] ?> Falt.</span>
                                <span class="badge bg-warning text-dark"><?= $row['TotalDamaged'] ?> Dañ.</span>
                            <?php else: ?>
                                <i class="fa fa-check text-success"></i>
                            <?php endif; ?>
                        </td>
                        <td class="text-end pe-4">
                            <?php if ($isOpen): ?>
                                <a href="golden_audit.php?action=edit&id=<?= $row['AuditID'] ?>" class="btn btn-sm btn-primary fw-bold shadow-sm">
                                    Continuar <i class="fa fa-arrow-right ms-1"></i>
                                </a>
                            <?php else: ?>
                                <a href="golden_audit_view.php?id=<?= $row['AuditID'] ?>" class="btn btn-sm btn-outline-secondary">
                                    Ver Detalles
                                </a>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>


<?php if ($action === 'edit'): ?>
    <form method="POST" action="golden_audit.php" id="auditForm">
        <input type="hidden" name="action" value="save_audit">
        <input type="hidden" name="audit_id" value="<?= h($auditRow['AuditID']) ?>">
        <!-- Status target will be set by button clicked -->
        <input type="hidden" name="status_target" id="statusTarget" value="Open">

        <div class="d-flex justify-content-between align-items-center mb-4 sticky-top bg-body pb-3 pt-3 border-bottom" style="top:0; z-index: 1020;">
            <div>
                <div class="d-flex align-items-center gap-2">
                    <span class="badge bg-primary">EN PROGRESO</span>
                    <h2 class="h4 mb-0">Auditoría #<?= h($auditRow['AuditID']) ?></h2>
                </div>
                <small class="text-muted">Iniciada: <?= h($auditRow['AuditDate']) ?></small>
            </div>
            <div class="d-flex gap-2">
                <a href="golden_audit.php" class="btn btn-outline-secondary">Salir sin guardar</a>
                
                <button type="submit" onclick="document.getElementById('statusTarget').value='Open'" class="btn btn-info text-white">
                    <i class="fa fa-floppy-disk me-2"></i>Guardar Progreso
                </button>
                
                <button type="button" class="btn btn-success fw-bold px-4" data-bs-toggle="modal" data-bs-target="#confirmFinishModal">
                    <i class="fa fa-check-double me-2"></i>Terminar Auditoría
                </button>
            </div>
        </div>

        <div class="row g-3">
            <div class="col-12 mb-3">
                <div class="card p-3 bg-light border-0">
                    <label class="form-label fw-bold">Notas de la Auditoría</label>
                    <textarea name="audit_comments" class="form-control" rows="2" placeholder="Observaciones generales..."><?= h($auditRow['Comments']) ?></textarea>
                </div>
            </div>

            <div class="col-12">
                <div class="card border-0 shadow-sm">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-3" style="width:50px;">Img</th>
                                    <th>Item</th>
                                    <th>Detalles</th>
                                    <th>Ubicación</th>
                                    <th class="text-center">Físico</th>
                                    <th style="width: 200px;">Condición</th>
                                    <th>Notas Item</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($inventory as $item): 
                                    $gid = $item['ID'];
                                    // Defaults
                                    $checked = true;
                                    $cond = 'Good';
                                    $note = '';

                                    // Override if saved previously
                                    if (isset($savedItems[$gid])) {
                                        $s = $savedItems[$gid];
                                        $checked = (int)$s['PhysicalCheck'] === 1;
                                        $cond = $s['ConditionCheck'];
                                        $note = $s['Notes'];
                                    }
                                ?>
                                <tr>
                                    <td class="ps-3">
                                        <?php if ($item['Picture']): ?>
                                            <img src="<?= h($item['Picture']) ?>" class="rounded" style="width:40px;height:40px;object-fit:cover;cursor:zoom-in;" 
                                                 data-bs-toggle="modal" data-bs-target="#imgModal" data-src="<?= h($item['Picture']) ?>">
                                        <?php else: ?>
                                            <div class="bg-secondary bg-opacity-10 rounded d-flex align-items-center justify-content-center text-secondary" style="width:40px;height:40px;">
                                                <i class="fa fa-image"></i>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="fw-bold"><?= h($item['ID']) ?></div>
                                    </td>
                                    <td>
                                        <div class="small fw-bold"><?= h($item['Description']) ?></div>
                                        <div class="small text-muted"><?= h($item['Brand']) ?? '' ?> <?= h($item['Model']) ?></div>
                                        <div class="small text-muted text-monospace"><?= h($item['SerialNumber']) ?></div>
                                    </td>
                                    <td>
                                        <span class="badge bg-info bg-opacity-10 text-info border border-info border-opacity-25">
                                            <?= h($item['Location']) ?>
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        <div class="form-check form-switch d-flex justify-content-center">
                                            <input class="form-check-input" type="checkbox" role="switch" 
                                                   name="items[<?= $gid ?>][physical]" value="1" 
                                                   <?= $checked ? 'checked' : '' ?>
                                                   style="width: 3em; height: 1.5em; cursor: pointer;">
                                        </div>
                                    </td>
                                    <td>
                                        <select class="form-select form-select-sm condition-select" name="items[<?= $gid ?>][condition]">
                                            <option value="Good" <?= $cond==='Good'?'selected':'' ?> class="text-success">✓ Bueno</option>
                                            <option value="Damage" <?= $cond==='Damage'?'selected':'' ?> class="text-warning">⚠ Dañado</option>
                                            <option value="Missing" <?= $cond==='Missing'?'selected':'' ?> class="text-danger">❌ Faltante</option>
                                            <option value="Scrap" <?= $cond==='Scrap'?'selected':'' ?> class="text-secondary">♻ Scrap</option>
                                        </select>
                                    </td>
                                    <td>
                                        <input type="text" class="form-control form-control-sm" name="items[<?= $gid ?>][note]" value="<?= h($note) ?>" placeholder="...">
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Modal Confirm Finish -->
        <div class="modal fade" id="confirmFinishModal" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Confirmar Finalización</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <p>¿Estás seguro de que deseas terminar esta auditoría?</p>
                        <p class="text-muted small">La auditoría se marcará como <b>CERRADA</b> y no se podrán hacer más cambios. Asegúrate de haber revisado todos los ítems.</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" onclick="document.getElementById('statusTarget').value='Closed'" class="btn btn-success fw-bold">Sí, Terminar</button>
                    </div>
                </div>
            </div>
        </div>

    </form>
    
    <!-- Image Modal -->
    <div class="modal fade" id="imgModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content bg-transparent border-0 shadow-none">
                 <div class="modal-body p-0 text-center">
                    <img id="previewImage" src="" class="img-fluid rounded shadow" style="max-height:85vh;">
                 </div>
            </div>
        </div>
    </div>

    <script>
    // Preview Image Logic
    const imgModal = document.getElementById('imgModal');
    if(imgModal){
        imgModal.addEventListener('show.bs.modal', e => {
            const btn = e.relatedTarget;
            document.getElementById('previewImage').src = btn.dataset.src;
        });
    }

    // Auto-uncheck physical if Missing is selected
    document.querySelectorAll('.condition-select').forEach(sel => {
        sel.addEventListener('change', (e) => {
            const row = e.target.closest('tr');
            const check = row.querySelector('input[type="checkbox"]');
            if(e.target.value === 'Missing') {
                check.checked = false;
            } else {
                // optional: re-check if user changes back to Good?
                // check.checked = true; 
            }
        });
    });
    </script>

<?php endif; ?>

</div>
<?php include __DIR__.'/partials/footer.php'; ?>
