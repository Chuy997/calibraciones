<?php
// /var/www/html/calibraciones/ingenieria_audit.php
declare(strict_types=1);

// CRITICAL: Increase upload limits for mobile camera photos
ini_set('upload_max_filesize', '10M');
ini_set('post_max_size', '12M');
ini_set('max_file_uploads', '20');

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
        $stmt = $pdo->prepare("INSERT INTO ingenieria_audits (Auditor, Status, AuditDate) VALUES (?, 'Open', NOW())");
        $stmt->execute([$currentUser]);
        $newId = $pdo->lastInsertId();
        header("Location: ingenieria_audit.php?action=edit&id=$newId");
        exit;
    } catch (Throwable $e) {
        die("Error creating audit: " . $e->getMessage());
    }
}

// 2. SAVE AUDIT
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_audit') {
    $auditId = (int)($_POST['audit_id'] ?? 0);
    $status  = $_POST['status_target'] ?? 'Open';
    $comments= $_POST['audit_comments'] ?? '';
    $items   = $_POST['items'] ?? [];

    if ($auditId <= 0) die("ID Inválido");

    // DEBUG: Comprehensive logging
    $debugLog = "=== SAVE AUDIT DEBUG ===\n";
    $debugLog .= "Timestamp: " . date('Y-m-d H:i:s') . "\n";
    $debugLog .= "AuditID: $auditId\n\n";
    
    $debugLog .= "POST items keys: " . implode(', ', array_keys($items)) . "\n\n";
    
   $debugLog .= "FILES Structure:\n";
    if (isset($_FILES['items'])) {
        $debugLog .= "  - items exists\n";
        if (isset($_FILES['items']['name'])) {
            $debugLog .= "  - name keys: " . implode(', ', array_keys($_FILES['items']['name'])) . "\n";
            foreach ($_FILES['items']['name'] as $gid => $fileData) {
                if (is_array($fileData)) {
                    $debugLog .= "    - Item $gid:\n";
                    foreach ($fileData as $key => $fileName) {
                        $error = $_FILES['items']['error'][$gid][$key] ?? 'N/A';
                        $size = $_FILES['items']['size'][$gid][$key] ?? 0;
                        $debugLog .= "      [$key] name=$fileName, error=$error, size=$size\n";
                    }
                }
            }
        }
    } else {
        $debugLog .= "  - NO FILES['items'] FOUND!\n";
    }
    
    file_put_contents(__DIR__ . '/uploads/audit_save_debug.log', $debugLog . "\n\n", FILE_APPEND);

    // DEBUG: Log POST items
    file_put_contents(__DIR__ . '/uploads/audit_debug_post.log', print_r($_POST['items'] ?? [], true));
    file_put_contents(__DIR__ . '/uploads/audit_debug_files.log', print_r($_FILES, true));


    try {
        $pdo->beginTransaction();

        $stats = ['Total' => 0, 'Missing' => 0, 'Damaged' => 0];

        // Wipe old details for clean update
        $pdo->prepare("DELETE FROM ingenieria_audit_items WHERE AuditID = ?")->execute([$auditId]);
        $stmtDetail = $pdo->prepare("INSERT INTO ingenieria_audit_items (AuditID, IngenieriaID, PhysicalCheck, ConditionCheck, Notes) VALUES (?, ?, ?, ?, ?)");
        
        // Update Inventory Tables
        $updLoc = $pdo->prepare("UPDATE ingenieria_items SET Location = ? WHERE ID = ?");
        $updPic = $pdo->prepare("UPDATE ingenieria_items SET Picture = ? WHERE ID = ?");

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
                    
                    $destDir = __DIR__ . '/uploads/ingenieria/' . $gid . '/';
                    if (!is_dir($destDir)) {
                        if (!mkdir($destDir, 0755, true)) $dbgMsg .= "  FAIL: mkdir $destDir\n";
                    }
                    
                    // Use standard naming convention
                    $finalName = 'audit_upd_' . time() . '.' . $ext;
                    if (move_uploaded_file($fTmp, $destDir . $finalName)) {
                        $relPath = '/calibraciones/uploads/ingenieria/' . $gid . '/' . $finalName;
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
        $sqlHead = "UPDATE ingenieria_audits SET TotalItems=?, TotalMissing=?, TotalDamaged=?, Comments=?, Status=? WHERE AuditID=?";
        $pdo->prepare($sqlHead)->execute([$stats['Total'], $stats['Missing'], $stats['Damaged'], $comments, $status, $auditId]);

        // STRICT FINALIZATION CHECK
        if ($status === 'Closed') {
            // Check Database State + Current Uploads
            // We need to re-fetch the *just updated* data to be sure, or check the loop logic again.
            // Simpler: Check if any item marked Good/Damage lacks a valid picture.
            
            // 1. Fetch Audit Date
            $auditDate = $pdo->query("SELECT AuditDate FROM ingenieria_audits WHERE AuditID=$auditId")->fetchColumn();
            
            // 2. Scan all active items
            $checkStmt = $pdo->query("SELECT i.ID, i.Picture, ai.ConditionCheck 
                                      FROM ingenieria_items i 
                                      LEFT JOIN ingenieria_audit_items ai ON i.ID = ai.IngenieriaID AND ai.AuditID = $auditId
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
                header("Location: ingenieria_audit.php?action=edit&id=$auditId&msg=error_validation&details=".urlencode($errorStr));
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
        header("Location: ingenieria_audit.php?action=edit&id=$auditId&msg=$msg");
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
        <?= htmlspecialchars($_GET['details'] ?? 'Faltan fotos nuevas.', ENT_QUOTES, 'UTF-8') ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<!-- ================= LIST VIEW ================= -->
<?php if ($action === 'list'): 
    try {
        $audits = $pdo->query("SELECT * FROM ingenieria_audits ORDER BY Status DESC, AuditDate DESC LIMIT 50")->fetchAll();
    } catch (Throwable $e) { $audits = []; echo "<div class='alert alert-danger'>Error DB: ".$e->getMessage()."</div>"; }
?>
    <div class="d-flex justify-content-between align-items-center mb-4 mt-3">
        <div>
            <h1 class="h3 fw-bold text-dark">Auditorías Ingenieria</h1>
            <p class="text-secondary mb-0">Historial de revisiones de inventario.</p>
        </div>
        <a href="ingenieria_audit.php?action=create_draft" class="btn btn-primary shadow-sm px-4">
            <i class="fa fa-plus me-2"></i>Nueva Auditoría
        </a>
    </div>

    <div class="row g-4">
        <?php if(!$audits): ?>
            <div class="col-12 text-center py-5 text-muted">
                <i class="fa fa-folder-open fa-3x mb-3 text-light"></i>
                <p>No hay auditorías registradas.</p>
            </div>
        <?php else: foreach($audits as $row): 
            $isOpen = $row['Status'] === 'Open';
            $date = $row['AuditDate'] ? date('d/M H:i', strtotime($row['AuditDate'])) : '-';
        ?>
            <div class="col-12 col-md-6 col-lg-4 col-xl-3">
                <div class="card h-100 shadow-sm border-0 <?= $isOpen ? 'border-primary border-2 border-start' : 'border-start border-4 border-secondary' ?>" style="transition: transform 0.2s;">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5 class="card-title fw-bold mb-0 text-dark">#<?= $row['AuditID'] ?></h5>
                            <span class="badge rounded-pill <?= $isOpen ? 'bg-primary' : 'bg-secondary' ?>">
                                <?= $isOpen ? 'En Progreso' : 'Cerrada' ?>
                            </span>
                        </div>
                        
                        <div class="mb-3">
                            <div class="d-flex align-items-center text-muted small mb-1">
                                <i class="fa fa-calendar me-2" style="width:16px"></i>
                                <span><?= $date ?></span>
                            </div>
                            <div class="d-flex align-items-center text-muted small">
                                <i class="fa fa-user me-2" style="width:16px"></i>
                                <span><?= h($row['Auditor']) ?></span>
                            </div>
                        </div>

                        <div class="d-flex justify-content-between align-items-center bg-secondary bg-opacity-10 rounded p-2 mb-3">
                            <div class="text-center px-2">
                                <small class="d-block text-muted text-uppercase" style="font-size: 0.7rem;">Items</small>
                                <span class="fw-bold"><?= $row['TotalItems'] ?></span>
                            </div>
                            <div class="vr"></div>
                            <div class="text-center px-2">
                                <small class="d-block text-muted text-uppercase" style="font-size: 0.7rem;">Minuta</small>
                                <?php if($row['TotalMissing'] > 0): ?>
                                    <span class="text-danger fw-bold"><?= $row['TotalMissing'] ?> Falt.</span>
                                <?php else: ?>
                                    <span class="text-success fw-bold"><i class="fa fa-check"></i> Clean</span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="d-grid">
                            <?php if ($isOpen): ?>
                                <a href="ingenieria_audit.php?action=edit&id=<?= $row['AuditID'] ?>" class="btn btn-primary fw-bold">
                                    <i class="fa fa-arrow-right me-1"></i> Continuar
                                </a>
                            <?php else: ?>
                                <a href="ingenieria_report_print.php?id=<?= $row['AuditID'] ?>" target="_blank" class="btn btn-outline-dark btn-sm">
                                    <i class="fa fa-file-pdf me-1"></i> Ver Reporte
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; endif; ?>
    </div>

<?php elseif ($action === 'edit'): 
    $id = (int)($_GET['id'] ?? 0);
    try {
        // Fetch Audit Header
        $auditRow = $pdo->query("SELECT * FROM ingenieria_audits WHERE AuditID=$id")->fetch();
        if (!$auditRow) die("Referencia vacía.");

        // Fetch Inventory & Saved State
        $inv = $pdo->query("SELECT ID, Description, Brand, Model, SerialNumber, Location, Picture FROM ingenieria_items WHERE Status='Activo' ORDER BY Location ASC, ID ASC")->fetchAll();
        $savedStmt = $pdo->prepare("SELECT * FROM ingenieria_audit_items WHERE AuditID = ?");
        $savedStmt->execute([$id]);
        $saved = [];
        foreach($savedStmt as $s) $saved[$s['IngenieriaID']] = $s;

        // Locations Datalist
        $locs = $pdo->query("SELECT DISTINCT Location FROM ingenieria_items ORDER BY Location")->fetchAll(PDO::FETCH_COLUMN);

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
                    <a href="ingenieria_audit.php" class="btn btn-outline-light">Salir</a>
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

             <!-- Filter Toolbar -->
             <div class="col-12">
                 <div class="card p-2 border-0 shadow-sm bg-secondary bg-opacity-10">
                     <div class="d-flex flex-wrap gap-2 align-items-center justify-content-between">
                         <div class="flex-grow-1">
                             <div class="input-group">
                                 <span class="input-group-text bg-transparent border-end-0"><i class="fa fa-search text-muted"></i></span>
                                 <input type="text" id="filterInput" class="form-control border-start-0" placeholder="Buscar por Nombre, Modelo, Marca o SN...">
                             </div>
                         </div>
                         <div class="form-check form-switch ms-2">
                             <input class="form-check-input" type="checkbox" id="hideCompletedToggle">
                             <label class="form-check-label fw-bold small text-muted" for="hideCompletedToggle">Ocultar Completados (Foto Nueva)</label>
                         </div>
                     </div>
                 </div>
             </div>

             <!-- ITEMS LOOP -->
              <div class="col-12">
                  <!-- ================= UNIFIED RESPONSIVE GRID (Cards for All) ================= -->
                  <div class="row g-4" id="itemsGrid">
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
                            $borderClass = '';
                            if(!$canCheck) $borderClass = 'border-danger';
                            elseif($isNewPic) $borderClass = 'border-success';

                            // Search Data
                            $searchStr = strtolower($item['Description'] . ' ' . $item['Brand'] . ' ' . $item['Model'] . ' ' . $item['SerialNumber'] . ' ' . $gid);
                      ?>
                      <div class="col-12 col-md-6 col-lg-4 col-xl-3 d-flex align-items-stretch item-card-col" data-search="<?= h($searchStr) ?>" data-complete="<?= $isNewPic ? 'true' : 'false' ?>">
                          <div class="card w-100 shadow-sm <?= $borderClass ?>" style="transition: transform 0.2s;">
                              <div class="position-relative bg-light text-center" style="min-height: 200px;">
                                  <!-- Image Area - Always img element for preview to work -->
                                  <img src="<?= $pic ?: 'data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%22100%22 height=%22100%22%3E%3Crect fill=%22%23f8f9fa%22 width=%22100%22 height=%22100%22/%3E%3Ctext x=%2250%%22 y=%2250%%22 font-size=%2240%22 text-anchor=%22middle%22 fill=%22%23adb5bd%22%3E📷%3C/text%3E%3C/svg%3E' ?>" 
                                       class="card-img-top item-thumb <?= $pic ? '' : 'd-none' ?>" 
                                       id="thumb-m-<?= $gid ?>" 
                                       style="height: 200px; object-fit: cover; width: 100%;">
                                  
                                  <?php if(!$pic): ?>
                                  <div class="d-flex align-items-center justify-content-center text-secondary position-absolute top-0 start-0 w-100 h-100" style="pointer-events: none;">
                                      <i class="fa fa-camera fa-2x opacity-50"></i>
                                  </div>
                                  <?php endif; ?>
                                  
                                  <!-- Camera Floater -->
                                  <label class="btn btn-primary btn-sm position-absolute rounded-circle shadow btn-camera-mobile" style="bottom: -15px; right: 15px; width: 40px; height: 40px; padding: 0; display:flex; align-items:center; justify-content:center;">
                                      <i class="fa fa-camera text-white"></i>
                                      <!-- Keeping name 'picture_mobile' as unified input name -->
                                      <input type="file" class="d-none start-cam" data-gid="<?= $gid ?>" accept="image/*" name="items[<?= $gid ?>][picture_mobile]">
                                  </label>

                                  <!-- New Pic Badge -->
                                  <?php if($isNewPic): ?>
                                      <span class="position-absolute top-0 end-0 m-2 badge rounded-pill bg-success shadow-sm">
                                          <i class="fa fa-check-circle me-1"></i> Nueva
                                      </span>
                                  <?php endif; ?>
                              </div>

                              <div class="card-body mt-2 pt-3">
                                  <div class="d-flex justify-content-between align-items-start mb-2">
                                      <div style="max-width: 80%;">
                                          <h6 class="card-title mb-0 fw-bold text-truncate" title="<?= h($item['Description']) ?>"><?= h($item['Description']) ?></h6>
                                          <div class="small text-muted text-truncate"><?= h($item['Brand']) ?> <?= h($item['Model']) ?></div>
                                      </div>
                                      <span class="badge bg-light text-dark border"><?= $gid ?></span>
                                  </div>

                                  <!-- Serial -->
                                  <div class="mb-3 small font-monospace text-muted bg-secondary bg-opacity-10 p-1 rounded text-center text-truncate">
                                      SN: <?= h($item['SerialNumber']) ?>
                                  </div>

                                  <!-- Alerts -->
                                  <?php if(!$canCheck): ?>
                                      <div class="alert alert-danger py-1 px-2 small mb-3 no-pic-alert-<?= $gid ?>">
                                          <i class="fa fa-exclamation-circle"></i> Foto requerida para verificar
                                      </div>
                                  <?php endif; ?>

                                  <!-- Controls -->
                                  <div class="d-flex align-items-center justify-content-between mb-3 bg-secondary bg-opacity-10 p-2 rounded">
                                      <label class="small fw-bold mb-0">Físico:</label>
                                      <div class="form-check form-switch m-0">
                                          <input class="form-check-input phys-chk-<?= $gid ?>" type="checkbox" role="switch" name="items[<?= $gid ?>][physical]" value="1" <?= $checked?'checked':'' ?> <?= !$canCheck?'disabled':'' ?> style="width: 2.5em; height: 1.5em;">
                                      </div>
                                  </div>

                                  <div class="mb-2">
                                      <label class="small text-muted">Ubicación</label>
                                      <input type="text" class="form-control form-control-sm" name="items[<?= $gid ?>][location]" value="<?= h($loc) ?>" list="locsList">
                                  </div>

                                  <div class="row g-2">
                                      <div class="col-6">
                                          <label class="small text-muted">Estado</label>
                                          <select class="form-select form-select-sm cond-sel" name="items[<?= $gid ?>][condition]">
                                              <option value="Good" <?= $cond==='Good'?'selected':'' ?>>Buena</option>
                                              <option value="Damage" <?= $cond==='Damage'?'selected':'' ?>>Dañada</option>
                                              <option value="Missing" <?= $cond==='Missing'?'selected':'' ?> class="text-danger fw-bold">Faltante</option>
                                              <option value="Scrap" <?= $cond==='Scrap'?'selected':'' ?>>Scrap</option>
                                          </select>
                                      </div>
                                      <div class="col-6">
                                          <label class="small text-muted">Notas</label>
                                          <!-- Using note_mobile as primary -->
                                          <input type="text" class="form-control form-control-sm" name="items[<?= $gid ?>][note_mobile]" value="<?= h($note) ?>" placeholder="...">
                                      </div>
                                  </div>
                              </div>
                          </div>
                      </div>
                      <?php endforeach; ?>
                  </div>

                  <!-- DESKTOP VIEW REPLACED BY RESPONSIVE GRID ABOVE -->
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
            console.log('📸 Camera input change event fired');
            
            if (e.target.files && e.target.files.length > 0) {
                const file = e.target.files[0];
                const gid = e.target.getAttribute('data-gid');
                
                console.log(`✅ File selected for ${gid}:`, file.name, `${(file.size/1024/1024).toFixed(2)}MB`);

                // AUTO-ENABLE & CHECK PHYSICAL
                const physCheckbox = document.querySelector(`.phys-chk-${gid}`);
                if(physCheckbox) {
                    physCheckbox.disabled = false;
                    physCheckbox.checked = true;
                    console.log(`✓ Auto-checked physical verification for ${gid}`);
                    
                    // Also hide the "Photo Required" alert if present
                    const alertBox = document.querySelector(`.no-pic-alert-${gid}`);
                    if(alertBox) alertBox.style.display = 'none';
                }

                // Find button (it's the parent label)
                const btnCam = e.target.closest('label.btn-camera-mobile');
                if(btnCam) {
                    btnCam.classList.remove('btn-outline-primary');
                    btnCam.classList.add('btn-success');
                    const icon = btnCam.querySelector('i');
                    if(icon) icon.className = 'fa fa-check text-white';
                    console.log('✓ Button updated to green checkmark');
                }
                
                // Show preview - find img directly by ID
                console.log(`🖼️ Starting preview for ${gid}...`);
                const thumb = document.getElementById(`thumb-m-${gid}`);
                
                if (!thumb) {
                    console.error(`❌ Could not find thumbnail element #thumb-m-${gid}`);
                } else {
                    console.log('Found thumbnail element:', thumb);
                    
                    const reader = new FileReader();
                    reader.onload = (evt) => {
                       console.log(`📷 FileReader loaded, updating preview`);
                       thumb.src = evt.target.result; 
                       thumb.classList.remove('d-none');
                       console.log('✓ Preview updated successfully');
                    };
                    reader.onerror = (err) => {
                        console.error('❌ FileReader error:', err);
                    };
                    reader.readAsDataURL(file);
                }

                // COMPRESS to ensure upload succeeds (server limit is 2MB)
                console.log(`🗜️ Starting compression for ${gid}...`);
                compressImage(file, 0.7, 1200).then(blob => {
                    console.log(`✓ Compression complete: ${(file.size/1024).toFixed(0)}KB → ${(blob.size/1024).toFixed(0)}KB`);
                    
                    // Replace file input with compressed version
                    const dt = new DataTransfer();
                    const newFile = new File([blob], 'photo_' + Date.now() + '.jpg', { type: 'image/jpeg' });
                    dt.items.add(newFile);
                    e.target.files = dt.files;
                    console.log(`✓ File input replaced with compressed version for ${gid}`);
                }).catch(err => {
                    console.error(`❌ Compression error for ${gid}:`, err);
                    alert("⚠️ Error al comprimir la foto. Intenta de nuevo.");
                });
            } else {
                console.log('⚠️ No files selected');
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
    const savedScroll = sessionStorage.getItem('ingenieria_audit_scroll');
    if (savedScroll) {
        setTimeout(() => {
            window.scrollTo(0, parseInt(savedScroll));
            sessionStorage.removeItem('ingenieria_audit_scroll'); // Clear
        }, 100); // Small delay to ensure layout is ready
    }

    // NEW: Client-Side Filters
    const filterInput = document.getElementById('filterInput');
    const toggleCompleted = document.getElementById('hideCompletedToggle');
    const items = document.querySelectorAll('.item-card-col');

    function applyFilters() {
        const txt = filterInput ? filterInput.value.toLowerCase() : '';
        const hideDone = toggleCompleted ? toggleCompleted.checked : false;

        items.forEach(el => {
            const dataSearch = el.getAttribute('data-search') || '';
            const isComplete = el.getAttribute('data-complete') === 'true';
            
            let show = true;
            if (txt && !dataSearch.includes(txt)) show = false;
            if (hideDone && isComplete) show = false;

            if(show) {
                el.classList.remove('d-none');
                el.classList.add('d-flex'); // Restore flex
            } else {
                el.classList.add('d-none');
                el.classList.remove('d-flex');
            }
        });
    }

    if(filterInput && toggleCompleted) {
        filterInput.addEventListener('keyup', applyFilters);
        toggleCompleted.addEventListener('change', applyFilters);
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

    // 3. Set Status if needed
    if (setClosed) {
        document.getElementById('statusTarget').value = 'Closed';
    }

    // NEW: Save Scroll Position
    sessionStorage.setItem('ingenieria_audit_scroll', window.scrollY);

    // 4. Submit
    form.submit();
}
</script>
