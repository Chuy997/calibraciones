<?php
// /var/www/html/calibraciones/golden_audit.php
declare(strict_types=1);

// Debugging
ini_set('display_errors', '0'); // Prod mode: Hide errors from output
ini_set('log_errors', '1');
error_reporting(E_ALL);

require_once __DIR__.'/config.php';
// Auth Check with CLI Bypass
if (php_sapi_name() === 'cli') {
    $currentUser = 'admin_cli';
} else {
    require_auth();
    $currentUser = $_SESSION['username'] ?? 'Unknown';
}

// Helpers
function h($s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
$pdo = pdo();

/* -------------------------------------------------------------------------- */
/*                                POST HANDLERS                               */
/* -------------------------------------------------------------------------- */

// 1. CREATE DRAFT
if (isset($_GET['action']) && $_GET['action'] === 'create_draft') {
    try {
        $stmt = $pdo->prepare("INSERT INTO golden_audits (Auditor, Status, AuditDate) VALUES (?, 'Open', NOW())");
        $stmt->execute([$currentUser]);
        $newId = $pdo->lastInsertId();
        header("Location: golden_audit.php?action=edit&id=$newId");
        exit;
    } catch (Throwable $e) {
        die("Error creating audit: " . $e->getMessage());
    }
}

// 2. SAVE AUDIT
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_audit') {
    $auditId = $_POST['audit_id'] ?? null;
    $status  = $_POST['status_target'] ?? 'Open';
    $comments= $_POST['audit_comments'] ?? '';
    $items   = $_POST['items'] ?? [];

    if (!$auditId) die("ID Inválido");

    // DEBUG: Log POST items
    file_put_contents(__DIR__ . '/uploads/audit_debug_post.log', print_r($_POST['items'] ?? [], true));
    file_put_contents(__DIR__ . '/uploads/audit_debug_files.log', print_r($_FILES, true));


    try {
        $pdo->beginTransaction();

        $stats = ['Total' => 0, 'Missing' => 0, 'Damaged' => 0];

        // Wipe old details for clean update
        $pdo->prepare("DELETE FROM golden_audit_items WHERE AuditID = ?")->execute([$auditId]);
        $stmtDetail = $pdo->prepare("INSERT INTO golden_audit_items (AuditID, GoldenID, PhysicalCheck, ConditionCheck, Notes) VALUES (?, ?, ?, ?, ?)");
        
        // Update Inventory Tables
        $updLoc = $pdo->prepare("UPDATE golden_items SET Location = ? WHERE ID = ?");
        $updPic = $pdo->prepare("UPDATE golden_items SET Picture = ? WHERE ID = ?");

        foreach ($items as $gid => $data) {
            $stats['Total']++;
            $phys = isset($data['physical']) ? 1 : 0;
            $cond = $data['condition'] ?? 'Good'; // Default to Good so validation triggers
            // Merge notes from mobile/desktop inputs
            $noteM = $data['note_mobile'] ?? '';
            $noteD = $data['note_desktop'] ?? '';
            $note  = $noteM ?: $noteD; // Prefer mobile if set
            $loc  = $data['location'] ?? '';

            // Stats Logic
            if ($phys === 0) $stats['Missing']++; 
            if ($cond === 'Damage') $stats['Damaged']++;

            // Insert Detail
            $stmtDetail->execute([$auditId, $gid, $phys, $cond, $note]);

            // Update Location
            if ($loc) $updLoc->execute([$loc, $gid]);

            // Update Picture (Check Mobile First, then Desktop)
            // Fix: Inputs were colliding. Now checking specific suffixes.
            // Update Picture (Check Mobile First, then Desktop)
            $uploadKey = null;
            if (isset($_FILES['items']['name'][$gid]['picture_mobile']) && $_FILES['items']['error'][$gid]['picture_mobile'] != UPLOAD_ERR_NO_FILE) {
                $uploadKey = 'picture_mobile';
            } elseif (isset($_FILES['items']['name'][$gid]['picture_desktop']) && $_FILES['items']['error'][$gid]['picture_desktop'] != UPLOAD_ERR_NO_FILE) {
                $uploadKey = 'picture_desktop';
            }

            if ($uploadKey) {
                $upErr = $_FILES['items']['error'][$gid][$uploadKey];
                $dbgMsg = "Item $gid: Upload Key $uploadKey Code $upErr\n";
                
                if ($upErr === UPLOAD_ERR_OK) {
                    $fName = $_FILES['items']['name'][$gid][$uploadKey];
                    $fTmp  = $_FILES['items']['tmp_name'][$gid][$uploadKey];
                    // Clean filename, keep it simple
                    $ext = strtolower(pathinfo($fName, PATHINFO_EXTENSION));
                    if(!$ext) $ext = 'jpg'; // Fallback
                    
                    $destDir = __DIR__ . '/uploads/golden/' . $gid . '/';
                    if (!is_dir($destDir)) {
                        if (!mkdir($destDir, 0755, true)) $dbgMsg .= "  FAIL: mkdir $destDir\n";
                    }
                    
                    // Use standard naming convention
                    $finalName = 'audit_upd_' . time() . '.' . $ext;
                    if (move_uploaded_file($fTmp, $destDir . $finalName)) {
                        $relPath = '/calibraciones/uploads/golden/' . $gid . '/' . $finalName;
                        $updPic->execute([$relPath, $gid]);
                        $dbgMsg .= "  SUCCESS: Moved to $relPath\n";
                    } else {
                        $dbgMsg .= "  FAIL: move_uploaded_file\n";
                    }
                }
                file_put_contents(__DIR__ . '/uploads/audit_debug.log', $dbgMsg, FILE_APPEND);
            }
        } // End Foreach







        // Update Header
        $sqlHead = "UPDATE golden_audits SET TotalItems=?, TotalMissing=?, TotalDamaged=?, Comments=?, Status=?, AuditDate=NOW() WHERE AuditID=?";
        $pdo->prepare($sqlHead)->execute([$stats['Total'], $stats['Missing'], $stats['Damaged'], $comments, $status, $auditId]);

        // STRICT FINALIZATION CHECK
        if ($status === 'Closed') {
            // Check Database State + Current Uploads
            // We need to re-fetch the *just updated* data to be sure, or check the loop logic again.
            // Simpler: Check if any item marked Good/Damage lacks a valid picture.
            
            // 1. Fetch Audit Date
            $auditDate = $pdo->query("SELECT AuditDate FROM golden_audits WHERE AuditID=$auditId")->fetchColumn();
            
            // 2. Scan all active items
            $checkStmt = $pdo->query("SELECT i.ID, i.Picture, ai.ConditionCheck 
                                      FROM golden_items i 
                                      LEFT JOIN golden_audit_items ai ON i.ID = ai.GoldenID AND ai.AuditID = $auditId
                                      WHERE i.Status='Activo'");
                                      
            $errors = [];
            foreach($checkStmt as $row) {
                $cond = $row['ConditionCheck'] ?? 'Missing';
                if ($cond === 'Missing' || $cond === 'Scrap') continue; // Exempt

                // Must have new photo
                $pic = $row['Picture'];
                $isNew = false;
                if ($pic && preg_match('/_([0-9]{10})\./', $pic, $matches)) {
                    if ((int)$matches[1] >= strtotime($auditDate)) $isNew = true;
                }
                
                if (!$isNew) {
                    $errors[] = "Item " . $row['ID'] . " requiere foto nueva.";
                }
            }

            if (!empty($errors)) {
                $pdo->rollBack(); // Revert everything
                $errorStr = implode("<br>", array_slice($errors, 0, 5));
                if(count($errors)>5) $errorStr .= "<br>... y " . (count($errors)-5) . " más.";
                header("Location: golden_audit.php?action=edit&id=$auditId&msg=error_validation&details=".urlencode($errorStr));
                exit;
            }
        }

        $pdo->commit();
        
        // AJAX Response
        if (!empty($_POST['ajax_mode'])) {
            header('Content-Type: application/json');
            echo json_encode(['status' => 'success', 'msg' => 'Guardado correctamente']);
            exit;
        }

        $msg = ($status === 'Closed') ? 'finalized' : 'saved';
        header("Location: golden_audit.php?action=edit&id=$auditId&msg=$msg");
        exit;

    } catch (Throwable $e) {
        $pdo->rollBack();
        file_put_contents(__DIR__ . '/uploads/audit_debug.log', "CRASH: " . $e->getMessage() . "\n", FILE_APPEND);
        error_log("Audit Save Error: " . $e->getMessage());
        die("Error saving: " . $e->getMessage());
    }
}

/* -------------------------------------------------------------------------- */
/*                                 VIEW LOGIC                                 */
/* -------------------------------------------------------------------------- */

$action = $_GET['action'] ?? 'list';
$msg    = $_GET['msg'] ?? null;

include __DIR__.'/partials/header.php'; 
?>
<!-- Cropper.js Dependencies -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.13/cropper.min.css" />
<script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.13/cropper.min.js"></script>

<div class="container-fluid pb-5">
<style>
/* --- DESKTOP TABLE --- */
.table-hover tbody tr { transition: background 0.2s; }

/* --- MOBILE CARDS (Laravel/clean style) --- */
@media (max-width: 767.98px) {
    .d-hide-mobile { display: none !important; }
    
    .mobile-card-row {
        display: block;
        background: #f8f9fa; /* Softer background */
        border: 1px solid #e9ecef;
        border-radius: 12px;
        box-shadow: 0 4px 6px rgba(0,0,0,0.02);
        margin-bottom: 20px;
        padding: 15px;
        position: relative;
    }
    .mobile-card-row.border-danger {
        border-color: #dc3545 !important;
        background: #fff5f5 !important;
    }
    .mobile-card-row.border-success {
        border-color: #198754 !important;
        background: #f0fff4 !important;
    }
    
    /* Layout Grid inside Card */
    .m-card-grid {
        display: grid;
        grid-template-columns: 80px 1fr;
        gap: 15px;
        align-items: start;
    }
    
    /* Image Area */
    .m-card-img-area { grid-column: 1; text-align: center; }
    .m-card-img { width: 70px; height: 70px; object-fit: cover; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
    
    /* Info Area */
    .m-card-info { grid-column: 2; }
    .m-card-title { font-size: 1.1rem; font-weight: 700; color: #333; margin-bottom: 2px; line-height: 1.2; }
    .m-card-subtitle { font-size: 0.85rem; color: #888; font-weight: 500; margin-bottom: 15px; }
    
    /* Controls Area (Stacked) */
    .m-card-controls { grid-column: 1 / -1; margin-top: 5px; }
    
    .m-control-group { background: #f8f9fa; padding: 10px; border-radius: 8px; margin-bottom: 8px; }
    .m-label { font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.5px; color: #6c757d; font-weight: 700; display: block; margin-bottom: 4px; }
    
    /* Inputs */
    .form-control-lg-mobile { height: 44px; font-size: 16px; }
    .btn-camera-mobile { width: 100%; margin-top: 5px; }

    /* Physical Toggle Area */
    .m-phys-toggle { 
        background: #eef2ff; 
        text-align: center; 
        padding: 12px; 
        display: flex; 
        justify-content: space-between; 
        align-items: center;
    }
    .form-switch .form-check-input { width: 3em; height: 1.5em; }
}
</style>

<!-- ALERTS -->
<?php if ($msg === 'saved'): ?>
    <div class="alert alert-success alert-dismissible fade show my-3 shadow-sm">
        <i class="fa fa-floppy-disk me-2"></i><b>Progreso Guardado.</b> Puedes continuar más tarde.
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php elseif ($msg === 'finalized'): ?>
    <div class="alert alert-success alert-dismissible fade show my-3 shadow-sm">
        <i class="fa fa-check-circle me-2"></i><b>Auditoría Finalizada.</b>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php elseif ($msg === 'error_validation'): ?>
    <div class="alert alert-danger alert-dismissible fade show my-3 shadow-sm">
        <i class="fa fa-circle-xmark me-2"></i><b>No se puede finalizar:</b><br>
        <?= $_GET['details'] ?? 'Faltan fotos nuevas.' ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<!-- ================= LIST VIEW ================= -->
<?php if ($action === 'list'): 
    try {
        $audits = $pdo->query("SELECT * FROM golden_audits ORDER BY Status DESC, AuditDate DESC LIMIT 50")->fetchAll();
    } catch (Throwable $e) { $audits = []; echo "<div class='alert alert-danger'>Error DB: ".$e->getMessage()."</div>"; }
?>
    <div class="d-flex justify-content-between align-items-center mb-4 mt-3">
        <div>
            <h1 class="h3 fw-bold text-dark">Auditorías Golden</h1>
            <p class="text-secondary mb-0">Historial de revisiones de inventario.</p>
        </div>
        <a href="golden_audit.php?action=create_draft" class="btn btn-primary shadow-sm px-4">
            <i class="fa fa-plus me-2"></i>Nueva Auditoría
        </a>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-4">ID</th>
                        <th>Estado</th>
                        <th>Fecha</th>
                        <th>Auditor</th>
                        <th class="text-center">Items</th>
                        <th class="text-center">Minuta</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                <?php if(!$audits): ?>
                    <tr><td colspan="7" class="text-center py-5 text-muted">No hay registros.</td></tr>
                <?php else: foreach($audits as $row): 
                    $isOpen = $row['Status'] === 'Open';
                    $date = $row['AuditDate'] ? date('d/M H:i', strtotime($row['AuditDate'])) : '-';
                ?>
                    <tr class="<?= $isOpen ? 'bg-primary bg-opacity-10' : '' ?>">
                        <td class="ps-4 fw-bold">#<?= $row['AuditID'] ?></td>
                        <td>
                            <span class="badge <?= $isOpen ? 'bg-primary' : 'bg-secondary' ?>">
                                <?= $isOpen ? 'En Progreso' : 'Cerrada' ?>
                            </span>
                        </td>
                        <td><?= $date ?></td>
                        <td><?= h($row['Auditor']) ?></td>
                        <td class="text-center"><?= $row['TotalItems'] ?></td>
                        <td class="text-center">
                            <?php if($row['TotalMissing'] > 0): ?>
                                <span class="badge bg-danger"><?= $row['TotalMissing'] ?> Faltantes</span>
                            <?php else: ?>
                                <i class="fa fa-check text-success"></i> Note
                            <?php endif; ?>
                        </td>
                        <td class="text-end pe-4">
                            <?php if ($isOpen): ?>
                                <a href="golden_audit.php?action=edit&id=<?= $row['AuditID'] ?>" class="btn btn-sm btn-primary fw-bold">Continuar</a>
                            <?php else: ?>
                                <a href="golden_report_print.php?id=<?= $row['AuditID'] ?>" target="_blank" class="btn btn-sm btn-outline-dark">Reporte</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>

<!-- ================= EDIT VIEW ================= -->
<?php elseif ($action === 'edit'): 
    $id = $_GET['id'] ?? 0;
    try {
        // Fetch Audit Header
        $auditRow = $pdo->query("SELECT * FROM golden_audits WHERE AuditID=$id")->fetch();
        if (!$auditRow) die("Referencia vacía.");

        // Fetch Inventory & Saved State
        $inv = $pdo->query("SELECT ID, Description, Brand, Model, SerialNumber, Location, Picture FROM golden_items WHERE Status='Activo' ORDER BY Location ASC, ID ASC")->fetchAll();
        $savedStmt = $pdo->prepare("SELECT * FROM golden_audit_items WHERE AuditID = ?");
        $savedStmt->execute([$id]);
        $saved = [];
        foreach($savedStmt as $s) $saved[$s['GoldenID']] = $s;

        // Locations Datalist
        $locs = $pdo->query("SELECT DISTINCT Location FROM golden_items ORDER BY Location")->fetchAll(PDO::FETCH_COLUMN);

    } catch (Throwable $e) { die("Error Crítico de Carga: " . $e->getMessage()); }
?>
    <form method="POST" enctype="multipart/form-data" id="auditForm">
        <input type="hidden" name="action" value="save_audit">
        <input type="hidden" name="audit_id" value="<?= $id ?>">
        <input type="hidden" name="status_target" id="statusTarget" value="Open">
        
        <datalist id="locsList">
            <?php foreach($locs as $l) echo "<option value='".h($l)."'>"; ?>
        </datalist>

        <!-- Sticky Header -->
        <div class="sticky-top bg-dark text-white border-bottom py-3 mb-3 shadow" style="top: 0; z-index: 1000;">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <span class="badge bg-primary mb-1">AUDITORÍA #<?= $id ?></span>
                    <h5 class="mb-0 fw-bold d-none d-md-block">Revisión de Material</h5>
                </div>
                <div class="d-flex gap-2">
                    <a href="golden_audit.php" class="btn btn-outline-light">Salir</a>
                    <button type="button" class="btn btn-info text-white fw-bold" onclick="cleanAndSubmit(false)"><i class="fa fa-save me-1"></i> <span class="d-none d-md-inline">Guardar</span></button>
                    <button type="button" class="btn btn-success fw-bold" data-bs-toggle="modal" data-bs-target="#finishModal"><i class="fa fa-check me-1"></i> <span class="d-none d-md-inline">Terminar</span></button>
                </div>
            </div>
            <!-- Progress Summary -->
             <div class="progress mt-3" style="height: 6px;">
                 <?php 
                    $total = count($inv); $done = count($saved); 
                    $pct = $total > 0 ? ($done/$total)*100 : 0;
                 ?>
                 <div class="progress-bar bg-success" style="width: <?= $pct ?>%"></div>
             </div>
        </div>

        <div class="row g-4">
             <!-- Global Comments -->
              <div class="col-12">
                  <div class="card p-3 shadow-sm border-0" style="background-color: #e2e3e5;"> <!-- Darker grey (bs-gray-200 equiv) -->
                     <label class="fw-bold small text-dark text-uppercase mb-2"><i class="fa fa-comment-dots"></i> Observaciones Generales</label>
                     <textarea name="audit_comments" class="form-control border-0 shadow-sm" rows="2" style="background:#fff;"><?= h($auditRow['Comments']) ?></textarea>
                 </div>
             </div>

             <!-- ITEMS LOOP -->
              <div class="col-12">
                  <!-- ================= MOBILE VIEW LOOP (CARDS) ================= -->
                  <div class="d-md-none">
                      <?php foreach ($inv as $item): 
                            $gid = $item['ID'];
                            $s   = $saved[$gid] ?? null;
                            $checked = $s ? ((int)$s['PhysicalCheck']===1) : false;
                            $cond    = $s ? $s['ConditionCheck'] : 'Good'; // Default 'Good' to enforce photo req
                            $note    = $s ? $s['Notes'] : '';
                            $loc     = $item['Location'];
                            $pic     = $item['Picture'];

                            $auditDate = $auditRow['AuditDate'] ?? date('Y-m-d H:i:s');
                            $isNewPic  = false;
                            if ($pic && preg_match('/_([0-9]{10})\./', $pic, $matches)) {
                                if ((int)$matches[1] >= strtotime($auditDate)) $isNewPic = true;
                            }
                            // Logic: Can check if New Pic OR Exempt Status
                            $isExempt = ($cond === 'Missing' || $cond === 'Scrap');
                            $canCheck = $isNewPic || $isExempt;  
                            
                            // Row Style Logic
                            $rowClass = '';
                            if(!$canCheck) $rowClass = 'border-danger';
                            elseif($isNewPic) $rowClass = 'border-success';
                      ?>
                      <div class="mobile-card-row <?= $rowClass ?>">
                          <div class="m-card-grid">
                              <div class="m-card-img-area">
                                  <?php if($pic): ?>
                                    <img src="<?= h($pic) ?>" class="m-card-img item-thumb" id="thumb-m-<?= $gid ?>" data-src="<?= h($pic) ?>">
                                  <?php else: ?>
                                    <div class="m-card-img bg-light d-flex align-items-center justify-content-center text-secondary"><i class="fa fa-camera fa-lg"></i></div>
                                  <?php endif; ?>
                                  
                                  <label class="btn btn-sm btn-outline-primary btn-camera-mobile mt-2">
                                      <i class="fa fa-camera"></i>
                                      <input type="file" class="d-none start-cam" data-gid="<?= $gid ?>" accept="image/*" name="items[<?= $gid ?>][picture_mobile]">
                                  </label>
                              </div>
                              <div class="m-card-info">
                                  <div class="d-flex justify-content-between">
                                      <div class="m-card-title"><?= h($item['Description']) ?></div>
                                      <span class="badge bg-light text-dark border"><?= $gid ?></span>
                                  </div>
                                  <div class="m-card-subtitle"><?= h($item['Brand']) ?> <?= h($item['Model']) ?> <br> <span class="font-monospace"><?= h($item['SerialNumber']) ?></span></div>
                                  
                                  <?php if(!$canCheck): ?>
                                      <div class="alert alert-danger py-1 px-2 small mb-0 d-inline-block no-pic-alert-<?= $gid ?>">
                                          <i class="fa fa-camera"></i> <b>FOTO NUEVA REQUERIDA</b>
                                      </div>
                                  <?php elseif($isNewPic): ?>
                                      <div class="text-success small fw-bold mt-1">
                                          <i class="fa fa-check-circle"></i> FOTO ACTUALIZADA
                                      </div>
                                  <?php endif; ?>
                              </div>
                              <div class="m-card-controls">
                                  <div class="m-control-group m-phys-toggle">
                                      <span class="fw-bold <?= $checked?'text-success':'text-secondary' ?>">
                                          <?= $checked ? 'VERIFICADO' : 'NO VERIFICADO' ?>
                                      </span>
                                      <div class="form-check form-switch m-0">
                                          <input class="form-check-input phys-chk-<?= $gid ?>" type="checkbox" role="switch" name="items[<?= $gid ?>][physical]" value="1" <?= $checked?'checked':'' ?> <?= !$canCheck?'disabled':'' ?>>
                                      </div>
                                  </div>
                                  <div class="m-control-group">
                                      <label class="m-label">Ubicación</label>
                                      <input type="text" class="form-control form-control-lg-mobile" name="items[<?= $gid ?>][location]" value="<?= h($loc) ?>" list="locsList">
                                  </div>
                                  <div class="m-control-group">
                                      <label class="m-label">Estado</label>
                                      <select class="form-select form-control-lg-mobile cond-sel" name="items[<?= $gid ?>][condition]">
                                          <option value="Good" <?= $cond==='Good'?'selected':'' ?>>Buena</option>
                                          <option value="Damage" <?= $cond==='Damage'?'selected':'' ?>>Dañada</option>
                                          <option value="Missing" <?= $cond==='Missing'?'selected':'' ?> class="text-danger">Faltante</option>
                                          <option value="Scrap" <?= $cond==='Scrap'?'selected':'' ?>>Scrap</option>
                                      </select>
                                  </div>
                                  <div class="m-control-group">
                                      <label class="m-label">Observaciones</label>
                                      <input type="text" class="form-control form-control-lg-mobile" name="items[<?= $gid ?>][note_mobile]" value="<?= h($note) ?>" placeholder="Observaciones">
                                  </div>
                              </div>
                          </div>
                      </div>
                      <?php endforeach; ?>
                  </div>

                  <!-- ================= DESKTOP VIEW LOOP (TABLE) ================= -->
                  <div class="d-none d-md-block">
                    <table class="table table-hover align-middle border bg-white">
                        <thead class="table-light"><tr>
                            <th width="80">Foto</th>
                            <th>Descripción</th>
                            <th>Ubicación</th>
                            <th class="text-center">Físico</th>
                            <th>Estado</th>
                            <th>Notas</th>
                        </tr></thead>
                        <tbody>
                        <?php foreach ($inv as $item): 
                                $gid = $item['ID'];
                                $s   = $saved[$gid] ?? null;
                                $checked = $s ? ((int)$s['PhysicalCheck']===1) : false;
                                $cond    = $s ? $s['ConditionCheck'] : 'Good'; // Default 'Good'
                                $note    = $s ? $s['Notes'] : '';
                                $loc     = $item['Location'];
                                $pic     = $item['Picture'];

                                $auditDate = $auditRow['AuditDate'] ?? date('Y-m-d H:i:s');
                                $isNewPic  = false;
                                if ($pic && preg_match('/_([0-9]{10})\./', $pic, $matches)) {
                                    if ((int)$matches[1] >= strtotime($auditDate)) $isNewPic = true;
                                }
                                $isExempt = ($cond === 'Missing' || $cond === 'Scrap');
                                $canCheck = $isNewPic || $isExempt; 
                        ?>
                          <tr>
                            <td>
                                <div class="position-relative" style="width: 50px;">
                                    <img src="<?= $pic ? h($pic) : '' ?>" class="rounded shadow-sm <?= $pic?'':'d-none' ?>" style="width:50px;height:50px;object-fit:cover;" id="thumb-d-<?= $gid ?>">
                                    <?php if($isNewPic): ?>
                                        <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-success">
                                            <i class="fa fa-check"></i>
                                        </span>
                                    <?php endif; ?>
                                    <label class="btn btn-sm btn-light position-absolute bottom-0 end-0 p-0 border rounded-circle shadow-sm" style="width:24px;height:24px;">
                                        <i class="fa fa-camera small"></i>
                                        <input type="file" class="d-none start-cam" data-gid="<?= $gid ?>" accept="image/*" name="items[<?= $gid ?>][picture_desktop]">
                                    </label>
                                </div>
                            </td>
                            <td>
                                <div class="fw-bold"><?= h($item['Description']) ?></div>
                                <div class="small text-muted"><?= $gid ?> | <?= h($item['Model']) ?></div>
                            </td>
                            <td><input type="text" class="form-control form-control-sm" name="items[<?= $gid ?>][location]" value="<?= h($loc) ?>" list="locsList"></td>
                            <td class="text-center">
                                <div class="form-check form-switch d-flex justify-content-center">
                                    <input class="form-check-input phys-chk-<?= $gid ?>" type="checkbox" name="items[<?= $gid ?>][physical]" value="1" <?= $checked?'checked':'' ?> <?= !$canCheck?'disabled':'' ?> title="<?= !$canCheck?'Foto Nueva Requerida':'' ?>">
                                </div>
                                <?php if(!$canCheck): ?><small class="text-danger no-pic-alert-<?= $gid ?>" style="font-size: 0.65rem;"><b>Foto Nueva Req.</b></small><?php endif; ?>
                            </td>
                            <td>
                                <select class="form-select form-select-sm cond-sel" name="items[<?= $gid ?>][condition]">
                                    <option value="Good" <?= $cond==='Good'?'selected':'' ?>>OK</option>
                                    <option value="Damage" <?= $cond==='Damage'?'selected':'' ?>>Dañado</option>
                                    <option value="Missing" <?= $cond==='Missing'?'selected':'' ?>>Faltante</option>
                                </select>
                            </td>
                             <td><input type="text" class="form-control form-control-sm" name="items[<?= $gid ?>][note_desktop]" value="<?= h($note) ?>"></td>
                          </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                  </div>
        </div>
        
        <!-- Finish Modal -->
        <div class="modal fade" id="finishModal" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header bg-success text-white">
                        <h5 class="modal-title">Finalizar Auditoría</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <p>¿Seguro que deseas cerrar la auditoría?</p>
                        <p class="small text-muted">Se generará el reporte final y no podrás editar más.</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="button" class="btn btn-success" onclick="cleanAndSubmit(true)">Confirmar</button>
                    </div>
                </div>

    </form>

<?php endif; ?>
</div>

<?php include __DIR__.'/partials/footer.php'; ?>

<script>
window.addEventListener('load', () => {
    // 1. Visual Feedback for Camera Input
    document.querySelectorAll('.start-cam').forEach(input => {
        input.addEventListener('change', (e) => {
            const container = e.target.closest('.m-card-img-area') || e.target.closest('td');
            const btnCam = container.querySelector('.btn-camera-mobile') || container.querySelector('.btn-camera-desktop');
            
            if (e.target.files && e.target.files.length > 0) {
                const file = e.target.files[0];

                // Change icon to checkmark
                if(btnCam) {
                    btnCam.classList.remove('btn-outline-primary');
                    btnCam.classList.add('btn-success');
                    const icon = btnCam.querySelector('i');
                    if(icon) icon.className = 'fa fa-check';
                }
                
                // Show preview
                const gid = e.target.getAttribute('data-gid');
                const reader = new FileReader();
                reader.onload = (evt) => {
                   const thumbs = document.querySelectorAll(`#thumb-m-${gid}, #thumb-d-${gid}`);
                   thumbs.forEach(t => { 
                       t.src = evt.target.result; 
                       t.classList.remove('d-none'); 
                   });
                }
                reader.readAsDataURL(file);

                // COMPRESS AND REPLACE (DataTransfer Pattern)
                // Only compress if > 1MB
                if (file.size > 1024 * 1024) {
                    compressImage(file, 0.7, 1200).then(blob => {
                        // Create new File from Blob
                        const dt = new DataTransfer();
                        const newFile = new File([blob], file.name.replace(/\.[^/.]+$/, "") + ".jpg", { type: "image/jpeg" });
                        dt.items.add(newFile);
                        
                        // REPLACE input file
                        e.target.files = dt.files;
                        console.log("Compressed item " + gid + " to " + (blob.size/1024).toFixed(0) + "KB");
                        
                    }).catch(err => {
                        console.error("Compression error:", err);
                        // Fallback: Keep original file (might fail server limit)
                    });
                }
            }
        });
    });

    // 2. Condition Logic (Hides/Shows "Missing Photo" alert)
    document.querySelectorAll('.cond-sel').forEach(sel => {
        sel.addEventListener('change', function() {
            const row = this.closest('tr') || this.closest('.mobile-card-row'); 
            const gid = this.name.match(/\[(\d+)\]/)[1];
            const val = this.value;
            
            const isExempt = (val === 'Missing' || val === 'Scrap');
            const alert = row.querySelector(`.no-pic-alert-${gid}`);
            const check = row.querySelector(`.phys-chk-${gid}`);

            if(isExempt) {
                if(alert) alert.style.display = 'none';
                if(check) { check.disabled = false; check.checked = true; }
            } else {
                const thumb = row.querySelector(`#thumb-m-${gid}`);
                const hasThumb = thumb && !thumb.classList.contains('d-none');
                
                if(!hasThumb) {
                    if(alert) alert.style.display = 'inline-block';
                    if(check) { check.disabled = true; check.checked = false; }
                }
            }
        });
    });
    // 2. Condition Logic (Hides/Shows "Missing Photo" alert)
    document.querySelectorAll('.cond-sel').forEach(sel => {
        // ... (existing logic)
    });
    
    // NEW: Restore Scroll Position if saved
    const savedScroll = sessionStorage.getItem('golden_audit_scroll');
    if (savedScroll) {
        setTimeout(() => {
            window.scrollTo(0, parseInt(savedScroll));
            sessionStorage.removeItem('golden_audit_scroll'); // Clear
        }, 100); // Small delay to ensure layout is ready
    }
});

function compressImage(file, quality, maxWidth) {
    return new Promise((resolve, reject) => {
        const reader = new FileReader();
        reader.readAsDataURL(file);
        reader.onload = event => {
            const img = new Image();
            img.src = event.target.result;
            img.onload = () => {
                let width = img.width;
                let height = img.height;
                
                if (width > maxWidth) {
                    height = Math.round(height * (maxWidth / width));
                    width = maxWidth;
                }
                
                const canvas = document.createElement('canvas');
                canvas.width = width;
                canvas.height = height;
                const ctx = canvas.getContext('2d');
                ctx.drawImage(img, 0, 0, width, height);
                
                // Return BLOB (not DataURL)
                canvas.toBlob(blob => {
                    resolve(blob);
                }, 'image/jpeg', quality);
            };
            img.onerror = error => reject(error);
        };
        reader.onerror = error => reject(error);
    });
}

// CRITICAL FIX for max_file_uploads = 20
function cleanAndSubmit(setClosed = false) {
    const form = document.getElementById('auditForm');
    
    // 1. Disable empty file inputs so they aren't sent
    const fileInputs = form.querySelectorAll('input[type="file"]');
    let count = 0;
    
    fileInputs.forEach(input => {
        if (input.files.length > 0) count++;
        else input.removeAttribute('name');
    });
    
    // 2. Warn if > 20 files
    if (count > 20) {
        alert("¡Atención! Estás intentando subir más de 20 fotos a la vez. El servidor solo procesará las primeras 20. Por favor guarda más seguido.");
    }
    
    // DEBUG: Verify count (Removed for Production)
    // alert("Sistema: Detectadas " + count + " fotos para subir.");

    // 3. Set Status if needed
    if (setClosed) {
        document.getElementById('statusTarget').value = 'Closed';
    }

    // NEW: Save Scroll Position
    sessionStorage.setItem('golden_audit_scroll', window.scrollY);

    // 4. Submit
    form.submit();
}
</script>
