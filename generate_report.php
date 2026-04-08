<?php
// Mostrar todos los errores
ini_set('display_errors', 1);
error_reporting(E_ALL);

require 'config.php';

    function generateMonthlyReport($month, $year) {
    $pdo = pdo();

    $firstDayOfMonth = sprintf('%04d-%02d-01', $year, $month);
    $lastDayOfMonth  = date("Y-m-t", strtotime($firstDayOfMonth));

    // Preparamos y ejecutamos la consulta
    $sql  = "SELECT ID, Description, Brand, Model, SerialNumber, HW_ZL, CalDate, DueDate, DaysCounter, Comments FROM instruments WHERE DueDate BETWEEN ? AND ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$firstDayOfMonth, $lastDayOfMonth]);

    // Nombre y ruta del CSV en tmp
    $filename = sprintf('reporte_calibraciones_%04d_%02d.csv', $year, $month);
    $tmpDir   = sys_get_temp_dir();
    $filePath = $tmpDir . DIRECTORY_SEPARATOR . $filename;

    // Abrimos para escritura en tmp
    if (!$file = fopen($filePath, 'w')) {
        die("No se pudo abrir el archivo para escritura: $filePath");
    }

    // Cabeceras
    $headers = ['ID','Description','Brand','Model','Serial Number','HWID','CalDate','DueDate','DaysCounter','Comments'];
    fputcsv($file, $headers);

    // Filas
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $csvRow = [
            $row['ID'] ?? '',
            $row['Description'] ?? '',
            $row['Brand'] ?? '',
            $row['Model'] ?? '',
            $row['SerialNumber'] ?? '',
            $row['HW_ZL'] ?? '',
            $row['CalDate'] ?? '',
            $row['DueDate'] ?? '',
            $row['DaysCounter'] ?? '',
            $row['Comments'] ?? ''
        ];
        fputcsv($file, $csvRow);
    }

    fclose($file);

    return $filePath;
}

if ($_SERVER['REQUEST_METHOD'] == 'GET' && isset($_GET['month']) && isset($_GET['year'])) {
    $month = $_GET['month'];
    $year = $_GET['year'];
    $filePath = generateMonthlyReport($month, $year);
    $downloadName = basename($filePath);

    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="'.$downloadName.'";');
    readfile($filePath);
    unlink($filePath);
    exit();
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Generar Reporte</title>
    <link rel="stylesheet" type="text/css" href="styles.css">
</head>
<body>
    <?php include 'menu.php'; ?>
    <div class="container">
        <h1>Generar Reporte Mensual de Calibraciones</h1>
        <form action="generate_report.php" method="get">
            <label for="month">Mes:</label>
            <select name="month" id="month" required>
                <?php
                for ($m = 1; $m <= 12; $m++) {
                    $monthName = date('F', mktime(0, 0, 0, $m, 1));
                    echo "<option value='$m'>$monthName</option>";
                }
                ?>
            </select>

            <label for="year">Año:</label>
            <select name="year" id="year" required>
                <?php
                $currentYear = date('Y');
                for ($y = $currentYear; $y >= $currentYear - 10; $y--) {
                    echo "<option value='$y'>$y</option>";
                }
                ?>
            </select>

            <input type="submit" value="Generar Reporte">
        </form>
    </div>
</body>
</html>
