<?php
// /var/www/html/calibraciones/assets_hw_packages.php
declare(strict_types=1);

require_once __DIR__.'/config.php';
require_auth(['admin','ingenieria']);

$pdo = pdo();
$search = $_GET['search'] ?? '';

// Query packages with item count
$sql = "
    SELECT 
        p.PackageID, 
        p.Name, 
        p.Description, 
        p.Location, 
        p.Status, 
        p.CreatedAt,
        COUNT(i.ID) as ItemCount
    FROM assets_hw_packages p
    LEFT JOIN assets_hw_items i ON i.PackageID = p.PackageID
    WHERE p.Status != 'Archived'
";

$params = [];
if ($search) {
    $sql .= " AND (p.Name LIKE :s OR p.Description LIKE :s OR p.PackageID LIKE :s)";
    $params[':s'] = "%$search%";
}

$sql .= " GROUP BY p.PackageID ORDER BY p.CreatedAt DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$packages = $stmt->fetchAll();

function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
?>
<?php include __DIR__.'/partials/header.php'; ?>

<div class="d-flex justify-content-between align-items-center my-3">
    <h1 class="h3">Paquetes de Activos (Hardware)</h1>
    <a href="assets_hw_package_create.php" class="btn btn-primary">
        <i class="fa fa-plus me-1"></i> Nuevo Paquete
    </a>
</div>

<div class="card p-3 mb-4">
    <form method="GET" class="row g-2 align-items-center">
        <div class="col-auto flex-grow-1">
            <input type="text" name="search" class="form-control" placeholder="Buscar paquetes..." value="<?= h($search) ?>">
        </div>
        <div class="col-auto">
            <button type="submit" class="btn btn-secondary"><i class="fa fa-search"></i></button>
        </div>
    </form>
</div>

<div class="table-responsive">
    <table class="table table-hover align-middle">
        <thead class="table-dark">
            <tr>
                <th>ID Paquete</th>
                <th>Nombre</th>
                <th>Descripción</th>
                <th>Ubicación</th>
                <th>Items</th>
                <th>Fecha Creación</th>
                <th class="text-end">Acciones</th>
            </tr>
        </thead>
        <tbody>
            <?php if (!$packages): ?>
                <tr>
                    <td colspan="7" class="text-center py-4 text-muted">No hay paquetes registrados.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($packages as $pkg): ?>
                    <tr>
                        <td>
                            <code class="text-primary fw-bold"><?= h($pkg['PackageID']) ?></code>
                        </td>
                        <td class="fw-semibold"><?= h($pkg['Name']) ?></td>
                        <td class="text-muted small"><?= h(mb_strimwidth($pkg['Description'], 0, 50, '...')) ?></td>
                        <td><?= h($pkg['Location']) ?></td>
                        <td><span class="badge bg-info text-dark"><?= $pkg['ItemCount'] ?></span></td>
                        <td><?= date('d/m/Y', strtotime($pkg['CreatedAt'])) ?></td>
                        <td class="text-end">
                            <a href="assets_hw_package_view.php?id=<?= h($pkg['PackageID']) ?>" class="btn btn-sm btn-outline-primary">Ver</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php include __DIR__.'/partials/footer.php'; ?>
