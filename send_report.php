<?php
require 'config.php';
require 'vendor/autoload.php'; // Assuming you are using Composer for PHPMailer

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

function generateMonthlyReport() {
    $conn = getConnection('admin');

    $firstDayOfMonth = date('Y-m-01');
    $lastDayOfMonth = date('Y-m-t');

    $sql = "SELECT * FROM Instruments WHERE DueDate BETWEEN ? AND ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $firstDayOfMonth, $lastDayOfMonth);
    $stmt->execute();
    $result = $stmt->get_result();

    $filename = 'reporte_calibraciones_' . date('Y_m') . '.csv';
    $file = fopen($filename, 'w');

    $headers = array('ID', 'Description', 'Brand', 'Model', 'Serial Number', 'Cal Date', 'Due Date', 'Status', 'Certificate No.', 'Comments');
    fputcsv($file, $headers);

    while ($row = $result->fetch_assoc()) {
        fputcsv($file, $row);
    }

    fclose($file);
    $stmt->close();
    $conn->close();

    return $filename;
}

function sendReportByEmail($filename) {
    $mail = new PHPMailer(true);
    
    try {
        //Server settings
        $mail->SMTPDebug = 0;
        $mail->isSMTP();
        $mail->Host = 'smtp.example.com'; // Specify main and backup SMTP servers
        $mail->SMTPAuth = true;
        $mail->Username = 'your_email@example.com';
        $mail->Password = 'your_email_password';
        $mail->SMTPSecure = 'tls';
        $mail->Port = 587;

        //Recipients
        $mail->setFrom('your_email@example.com', 'Calibrations System');
        $mail->addAddress('jesus.muro@zhongli-la.com');

        //Attachments
        $mail->addAttachment($filename);

        //Content
        $mail->isHTML(true);
        $mail->Subject = 'Reporte Mensual de Calibraciones';
        $mail->Body    = 'Adjunto encontrarás el reporte mensual de calibraciones.';

        $mail->send();
        echo 'El reporte ha sido enviado exitosamente.';
    } catch (Exception $e) {
        echo 'El reporte no pudo ser enviado. Mailer Error: ', $mail->ErrorInfo;
    }
}

// Generate report and send it by email
$filename = generateMonthlyReport();
sendReportByEmail($filename);
unlink($filename); // Delete the file after sending
?>
