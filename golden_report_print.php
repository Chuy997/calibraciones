<?php
// /var/www/html/calibraciones/golden_report_print.php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_auth();

// Helpers
function h(?string $s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

$id = $_GET['id'] ?? '';
if (!$id) die("ID faltante.");

$pdo = pdo();

// 1. Audit Header
$st = $pdo->prepare("SELECT * FROM golden_audits WHERE AuditID = ?");
$st->execute([$id]);
$audit = $st->fetch();

if (!$audit) die("Auditoría no encontrada.");

// 2. Audit Items + Golden Details
$sqlItems = "
    SELECT 
        ai.*, 
        gi.Description, 
        gi.Brand, 
        gi.Model, 
        gi.SerialNumber, 
        gi.Location as MasterLocation,
        gi.Picture as MasterPicture
    FROM golden_audit_items ai
    JOIN golden_items gi ON ai.GoldenID = gi.ID
    WHERE ai.AuditID = ?
    ORDER BY ai.GoldenID ASC
";
$st = $pdo->prepare($sqlItems);
$st->execute([$id]);
$items = $st->fetchAll();

// Stats
$total    = count($items);
$missing  = 0;
$good     = 0;
$damaged  = 0;

foreach ($items as $itm) {
    if ($itm['ConditionCheck'] === 'Missing') $missing++;
    elseif ($itm['ConditionCheck'] === 'Good') $good++;
    elseif ($itm['ConditionCheck'] === 'Damaged') $damaged++;
}

// Format Date
$dateFormatted = date("d/m/Y H:i", strtotime($audit['AuditDate'] ?: $audit['StartDate']));

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reporte Auditoría #<?= h($id) ?></title>
    <style>
        body {
            font-family: sans-serif;
            font-size: 11px; /* Small font for density */
            color: #000;
            background: #fff;
            margin: 0;
            padding: 10px;
        }
        .header {
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            border-bottom: 2px solid #000;
            padding-bottom: 10px;
            margin-bottom: 15px;
        }
        .header h1 {
            margin: 0;
            font-size: 18px;
            text-transform: uppercase;
        }
        .meta p {
            margin: 2px 0;
        }
        .stats {
            display: flex;
            gap: 20px;
            margin-bottom: 15px;
            font-weight: bold;
            font-size: 12px;
            background: #f0f0f0;
            padding: 5px 10px;
            border-radius: 4px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        th, td {
            border: 1px solid #ccc;
            padding: 4px;
            vertical-align: top;
            text-align: left;
        }
        th {
            background: #eee;
            font-weight: bold;
            font-size: 10px;
        }
        .img-cell {
            width: 80px;
            text-align: center;
        }
        .img-thumb {
            max-width: 80px;
            max-height: 80px;
            object-fit: contain;
            display: block;
            margin: auto;
        }
        .status-good { color: green; font-weight: bold; }
        .status-missing { color: red; font-weight: bold; background: #ffeaea; }
        .status-damaged { color: orange; font-weight: bold; }
        
        .no-print {
            background: #333;
            color: #fff;
            padding: 10px;
            text-align: center;
            margin-bottom: 20px;
            border-radius: 4px;
        }
        
        @media print {
            .no-print { display: none !important; }
            body { padding: 0; margin: 0; }
            table { page-break-inside: auto; }
            tr { page-break-inside: avoid; page-break-after: auto; }
            thead { display: table-header-group; }
            tfoot { display: table-footer-group; }
        }
    </style>
</head>
<body>

    <div class="no-print">
        <button onclick="window.print()" style="padding: 10px 20px; font-weight: bold; cursor: pointer;">
            🖨️ IMPRIMIR / GUARDAR PDF
        </button>
        <span style="margin-left: 20px;">Use la opción "Guardar como PDF" de su navegador.</span>
    </div>

    <div class="header">
        <div>
            <h1>Reporte de Auditoría: Material Golden</h1>
            <div class="meta">
                <p><strong>ID:</strong> #<?= h($id) ?></p>
                <p><strong>Fecha:</strong> <?= h($dateFormatted) ?></p>
            </div>
        </div>
        <div class="meta" style="text-align: right;">
            <p><strong>Auditor:</strong> <?= h($audit['Auditor']) ?></p>
            <p><strong>Estado:</strong> <?= h($audit['Status']) ?></p>
        </div>
    </div>

    <div class="stats">
        <span>Total: <?= $total ?></span>
        <span style="color:red">Faltantes: <?= $missing ?></span>
        <span style="color:green">Buenos: <?= $good ?></span>
        <span style="color:orange">Dañados: <?= $damaged ?></span>
    </div>

    <table>
        <thead>
            <tr>
                <th class="img-cell">Foto</th>
                <th>ID</th>
                <th>Descripción</th>
                <th>Ubicación</th>
                <th>Físico</th>
                <th>Condición</th>
                <th>Notas</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($items as $item): ?>
            <tr>
                <td class="img-cell">
                    <?php if ($item['MasterPicture']): ?>
                        <img src="<?= h($item['MasterPicture']) ?>" class="img-thumb">
                        <?php 
                            // Extract timestamp from filename: NAME_TIMESTAMP.ext
                            $picDate = '';
                            if (preg_match('/_([0-9]{10})\./', $item['MasterPicture'], $m)) {
                                $picDate = date('d/m/y H:i', (int)$m[1]);
                            }
                        ?>
                        <?php if ($picDate): ?>
                            <div style="font-size:8px; color:#555; margin-top:2px; white-space:nowrap;">
                                📅 <?= h($picDate) ?>
                            </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <span style="color:#ccc; font-size:9px;">Sin foto</span>
                    <?php endif; ?>
                </td>
                <td><strong><?= h($item['GoldenID']) ?></strong></td>
                <td>
                    <?= h($item['Description']) ?><br>
                    <small style="color:#666;"><?= h($item['Brand']) ?> <?= h($item['Model']) ?></small>
                </td>
                <td>
                    <?= h($item['LocationChecked'] ?: $item['MasterLocation']) ?>
                    <?php if ($item['LocationChecked'] && $item['LocationChecked'] !== $item['MasterLocation']): ?>
                        <br><span style="font-size:9px; color:blue;">(Actualizado)</span>
                    <?php endif; ?>
                </td>
                <td style="text-align: center;">
                    <?= $item['PhysicalCheck'] ? '✅' : '❌' ?>
                </td>
                <td>
                    <?php 
                        $cls = match($item['ConditionCheck']) {
                            'Good' => 'status-good',
                            'Missing' => 'status-missing',
                            'Damaged' => 'status-damaged',
                            default => ''
                        };
                        $lbl = match($item['ConditionCheck']) {
                            'Good' => 'Bueno',
                            'Missing' => 'Faltante',
                            'Damaged' => 'Dañado',
                            default => $item['ConditionCheck']
                        };
                    ?>
                    <span class="<?= $cls ?>"><?= h($lbl) ?></span>
                </td>
                <td><?= h($item['Notes']) ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <div style="font-size: 10px; color: #777; border-top: 1px solid #ccc; padding-top: 5px;">
        <p>Reporte generado el <?= date('d/m/Y H:i:s') ?> por el sistema de Calibraciones.</p>
    </div>

</body>
</html>
