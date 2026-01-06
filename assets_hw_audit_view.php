<?php
// /var/www/html/calibraciones/assets_hw_audit_view.php
declare(strict_types=1);

require_once __DIR__.'/config.php';
require_auth(['admin','ingenieria']);

function h(?string $s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

$id = $_GET['id'] ?? null;
if (!$id) {
    header('Location: assets_hw_audit.php');
    exit;
}

$pdo = pdo();

// Get Audit Header
$stmt = $pdo->prepare("SELECT * FROM assets_hw_audits WHERE AuditID = ?");
$stmt->execute([$id]);
$audit = $stmt->fetch();

if (!$audit) {
    die("Auditoría no encontrada.");
}

// Get Details
$stmtDetails = $pdo->prepare("
    SELECT 
        d.*, 
        g.Description, 
        g.Model, 
        g.SerialNumber, 
        g.Location, 
        g.Picture 
    FROM assets_hw_audit_items d
    LEFT JOIN assets_hw_items g ON d.AssetsHWID = g.ID
    WHERE d.AuditID = ?
    ORDER BY g.Location ASC, g.ID ASC
");
$stmtDetails->execute([$id]);
$items = $stmtDetails->fetchAll();

?>
<?php include __DIR__.'/partials/header.php'; ?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0 fw-bold">Detalle de Auditoría #<?= h($audit['AuditID']) ?></h1>
            <p class="text-secondary mb-0">
                <i class="fa fa-calendar me-1"></i><?= date('d/m/Y H:i', strtotime($audit['AuditDate'])) ?> 
                <span class="mx-2">•</span> 
                <i class="fa fa-user me-1"></i><?= h($audit['Auditor']) ?>
            </p>
        </div>
        <a href="assets_hw_audit.php" class="btn btn-outline-secondary">
            <i class="fa fa-arrow-left me-1"></i> Volver al listado
        </a>
    </div>

    <!-- Status Badge & Meta -->
    <div class="mb-4 d-flex align-items-center gap-3">
        <?php if ($audit['Status'] === 'Open'): ?>
            <span class="badge bg-primary fs-6 px-3 py-2">En Progreso</span>
        <?php else: ?>
            <span class="badge bg-success fs-6 px-3 py-2">Finalizada</span>
        <?php endif; ?>
    </div>

    <!-- Summary Cards -->
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card border-0 shadow-sm text-center py-3">
                <h6 class="text-muted text-uppercase small ls-1">Total Ítems</h6>
                <div class="display-6 fw-bold text-dark"><?= h($audit['TotalItems']) ?></div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm text-center py-3">
                <h6 class="text-muted text-uppercase small ls-1">Faltantes</h6>
                <div class="display-6 fw-bold <?= $audit['TotalMissing'] > 0 ? 'text-danger' : 'text-success' ?>">
                    <?= h($audit['TotalMissing']) ?>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm text-center py-3">
                <h6 class="text-muted text-uppercase small ls-1">Dañados</h6>
                <div class="display-6 fw-bold <?= $audit['TotalDamaged'] > 0 ? 'text-warning' : 'text-success' ?>">
                    <?= h($audit['TotalDamaged']) ?>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm p-3 h-100">
                <h6 class="text-muted text-uppercase small ls-1 mb-2">Comentarios</h6>
                <p class="mb-0 small text-secondary fst-italic">
                    <?= !empty($audit['Comments']) ? nl2br(h($audit['Comments'])) : 'Sin comentarios.' ?>
                </p>
            </div>
        </div>
    </div>

    <!-- Items Table -->
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white py-3">
            <h5 class="mb-0 card-title">Resultados Detallados</h5>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-4">Item</th>
                        <th>Ubicación</th>
                        <th class="text-center">Físico</th>
                        <th class="text-center">Condición</th>
                        <th>Notas</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $item): ?>
                    <tr>
                        <td class="ps-4">
                            <div class="d-flex align-items-center gap-3">
                                <?php if ($item['Picture']): ?>
                                    <img src="<?= h($item['Picture']) ?>" class="rounded" style="width:40px;height:40px;object-fit:cover;" data-bs-toggle="modal" data-bs-target="#imgModal" data-src="<?= h($item['Picture']) ?>">
                                <?php else: ?>
                                    <div class="bg-light rounded d-flex align-items-center justify-content-center text-muted" style="width:40px;height:40px;">
                                        <i class="fa fa-image"></i>
                                    </div>
                                <?php endif; ?>
                                <div>
                                    <div class="fw-bold"><?= h($item['AssetsHWID']) ?></div>
                                    <div class="small text-muted"><?= h($item['Description']) ?></div>
                                </div>
                            </div>
                        </td>
                        <td>
                            <span class="badge bg-secondary bg-opacity-10 text-secondary">
                                <?= h($item['Location']) ?>
                            </span>
                        </td>
                        <td class="text-center">
                            <?php if ($item['PhysicalCheck']): ?>
                                <i class="fa fa-check-circle text-success fs-5"></i>
                            <?php else: ?>
                                <i class="fa fa-times-circle text-danger fs-5"></i>
                            <?php endif; ?>
                        </td>
                        <td class="text-center">
                            <?php 
                            $cond = $item['ConditionCheck'];
                            $cls = match($cond) {
                                'Good' => 'bg-success text-white',
                                'Damage' => 'bg-warning text-dark',
                                'Scrap' => 'bg-dark text-white',
                                'Missing' => 'bg-danger text-white',
                                default => 'bg-secondary text-white'
                            };
                            $label = match($cond) {
                                'Good' => 'Buen Estado',
                                'Damage' => 'Dañado',
                                'Scrap' => 'Scrap',
                                'Missing' => 'Faltante',
                                default => $cond
                            };
                            ?>
                            <span class="badge <?= $cls ?>"><?= h($label) ?></span>
                        </td>
                        <td class="text-secondary small">
                            <?= h($item['Notes']) ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal Image -->
<div class="modal fade" id="imgModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content bg-dark">
      <div class="modal-body p-0 text-center">
        <img id="previewImage" src="" class="img-fluid" style="max-height:80vh;">
      </div>
    </div>
  </div>
</div>
<script>
    const imgModal = document.getElementById('imgModal');
    if (imgModal) {
        imgModal.addEventListener('show.bs.modal', function(event) {
            const button = event.relatedTarget;
            const src = button.getAttribute('data-src');
            const img = this.querySelector('#previewImage');
            img.src = src;
        });
    }
</script>

<?php include __DIR__.'/partials/footer.php'; ?>
