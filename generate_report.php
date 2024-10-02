<?php
require 'config.php';

function generateMonthlyReport($month, $year) {
    $conn = getConnection('admin');

    $firstDayOfMonth = "$year-$month-01";
    $lastDayOfMonth = date("Y-m-t", strtotime($firstDayOfMonth));

    $sql = "SELECT * FROM Instruments WHERE DueDate BETWEEN ? AND ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $firstDayOfMonth, $lastDayOfMonth);
    $stmt->execute();
    $result = $stmt->get_result();

    $filename = 'reporte_calibraciones_' . $year . '_' . $month . '.csv';
    $file = fopen($filename, 'w');

    $headers = array('ID', 'Description', 'Brand', 'Model', 'Serial Number', 'HWID', 'Cal Date', 'Due Date', 'Days counter.', 'Comments');
    fputcsv($file, $headers);

    while ($row = $result->fetch_assoc()) {
        fputcsv($file, $row);
    }

    fclose($file);
    $stmt->close();
    $conn->close();

    return $filename;
}

if ($_SERVER['REQUEST_METHOD'] == 'GET' && isset($_GET['month']) && isset($_GET['year'])) {
    $month = $_GET['month'];
    $year = $_GET['year'];
    $filename = generateMonthlyReport($month, $year);
    header('Content-Type: application/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '";');
    readfile($filename);
    unlink($filename); // Delete the file after download
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
