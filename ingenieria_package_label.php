<?php
// /var/www/html/calibraciones/ingenieria_package_label.php
declare(strict_types=1);

require_once __DIR__.'/config.php';
require_auth(['admin','ingenieria']);

$pdo = pdo();
$id = $_GET['id'] ?? '';

$stmt = $pdo->prepare("SELECT * FROM ingenieria_packages WHERE PackageID = :id");
$stmt->execute([':id' => $id]);
$pkg = $stmt->fetch();

if (!$pkg) die("Paquete no encontrado.");

$items = $pdo->prepare("SELECT ID, SerialNumber, Description, Brand, Model FROM ingenieria_items WHERE PackageID = :id");
$items->execute([':id' => $id]);
$itemList = $items->fetchAll();

// Prepare QR Content: JSON with ID and List of Serials
$qrData = json_encode([
    'id' => $pkg['PackageID'],
    'items' => array_column($itemList, 'SerialNumber')
]);

function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Etiqueta <?= h($pkg['PackageID']) ?></title>
    <!-- QR Code Library -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
    <style>
        @page {
            size: 150mm 100mm;
            margin: 0;
        }
        body {
            width: 148mm;
            height: 98mm;
            margin: 1mm;
            font-family: sans-serif;
            border: 1px dashed #ccc; /* Visual aid */
            box-sizing: border-box;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }
        .header {
            border-bottom: 2px solid black;
            padding: 5px;
            text-align: center;
            background: #eee;
        }
        .header h1 { margin: 0; font-size: 18px; }
        .header .id { font-family: monospace; font-size: 14px; font-weight: bold; }
        
        .content {
            flex: 1;
            display: flex;
            padding: 10px;
        }
        .info {
            flex: 2;
            padding-right: 10px;
            font-size: 12px;
        }
        .info h2 { margin: 0 0 5px 0; font-size: 14px; }
        .item-list {
            margin-top: 5px;
            font-size: 10px;
            max-height: 55mm;
            overflow: hidden; /* Crop if too many */
        }
        .qr-container {
            flex: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
        }
        .footer {
            border-top: 1px solid #000;
            padding: 3px;
            text-align: center;
            font-size: 10px;
        }
        @media print {
            body { border: none; margin: 0; }
            .no-print { display: none; }
        }
    </style>
</head>
<body onload="generateQR()">
    <div class="no-print" style="position: absolute; top:0; right:0; background: yellow; padding: 5px; font-size: 10px;">
        Tamaño: 150mm x 100mm <button onclick="window.print()">Imprimir</button>
    </div>

    <div class="header">
        <h1>PAQUETE DE INGENIERÍA</h1>
        <div class="id"><?= h($pkg['PackageID']) ?></div>
    </div>

    <div class="content">
        <div class="info">
            <h2><?= h($pkg['Name']) ?></h2>
            <p><strong>Ubicación:</strong> <?= h($pkg['Location']) ?></p>
            <p><strong>Responsable:</strong> <?= h($pkg['CreatedBy']) ?></p>
            <p><strong>Fecha:</strong> <?= date('d/m/Y', strtotime($pkg['CreatedAt'])) ?></p>
            
            <div class="item-list">
                <strong>Contenido (<?= count($itemList) ?> items):</strong><br>
                <?php foreach(array_slice($itemList, 0, 10) as $it): ?>
                    - <?= h($it['Brand']) ?> <?= h($it['Model']) ?> (SN: <?= h($it['SerialNumber']) ?>)<br>
                <?php endforeach; ?>
                <?php if(count($itemList) > 10): ?>
                    <em>... y <?= count($itemList)-10 ?> más.</em>
                <?php endif; ?>
            </div>
        </div>
        <div class="qr-container">
            <div id="qrcode"></div>
        </div>
    </div>

    <div class="footer">
        Sistema de Inventario (Ingeniería)
    </div>

    <script>
        function generateQR() {
            var txt = <?= json_encode($qrData) ?>;
            new QRCode(document.getElementById("qrcode"), {
                text: txt,
                width: 128,
                height: 128
            });
        }
    </script>
</body>
</html>
