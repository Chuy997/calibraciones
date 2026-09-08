<?php
// /var/www/html/calibraciones/aio_scrap_mail.php
// Helper para envío de correo de scrap de Ingeniería via PHPMailer
declare(strict_types=1);

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/vendor/autoload.php';

function aio_send_scrap_email(array $items): bool
{
    $mail = new PHPMailer(true);

    try {
        // ── Configuración SMTP ──────────────────────────────────────
        $mail->isSMTP();
        $mail->Host       = 'smtphz.qiye.163.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'alertservice@xinya-la.com';
        $mail->Password   = 'M4ru4t4.2025!';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        $mail->Port       = 465;
        $mail->CharSet    = 'UTF-8';

        // ── Remitente / Destinatario ────────────────────────────────
        $mail->setFrom('alertservice@xinya-la.com', 'Xinya Asset Inventory System');
        $mail->addReplyTo('alertservice@xinya-la.com', 'Xinya Asset Inventory System');
        $mail->addAddress('cesar.gutierrez@xinya-la.com', 'César Gutiérrez');
        $mail->addCC('jesus.muro@xinya-la.com');
        $mail->addCC('sergio.gomez@xinya-la.com');

        // ── Asunto ──────────────────────────────────────────────────
        $total = count($items);
        $fecha = date('d/m/Y H:i');
        $mail->Subject = "Authorization Request for Material Destruction – {$total} item(s) – {$fecha}";

        // ── Cuerpo HTML ─────────────────────────────────────────────
        $rows = '';
        $n    = 1;
        foreach ($items as $r) {
            $bg = ($n % 2 === 0) ? '#1e293b' : '#0f172a';
            $rows .= "
            <tr style=\"background:{$bg};\">
              <td style=\"padding:8px 12px;border-bottom:1px solid #334155;\">{$n}</td>
              <td style=\"padding:8px 12px;border-bottom:1px solid #334155;\">" . htmlspecialchars((string)($r['ID'] ?? ''), ENT_QUOTES) . "</td>
              <td style=\"padding:8px 12px;border-bottom:1px solid #334155;\">" . htmlspecialchars((string)($r['Description'] ?? ''), ENT_QUOTES) . "</td>
              <td style=\"padding:8px 12px;border-bottom:1px solid #334155;\">" . htmlspecialchars((string)($r['Brand'] ?? ''), ENT_QUOTES) . "</td>
              <td style=\"padding:8px 12px;border-bottom:1px solid #334155;\">" . htmlspecialchars((string)($r['Model'] ?? ''), ENT_QUOTES) . "</td>
              <td style=\"padding:8px 12px;border-bottom:1px solid #334155;\">" . htmlspecialchars((string)($r['SerialNumber'] ?? ''), ENT_QUOTES) . "</td>
              <td style=\"padding:8px 12px;border-bottom:1px solid #334155;text-align:center;\">" . htmlspecialchars((string)($r['Qty'] ?? '1'), ENT_QUOTES) . "</td>
              <td style=\"padding:8px 12px;border-bottom:1px solid #334155;\">" . htmlspecialchars((string)($r['Department'] ?? ''), ENT_QUOTES) . "</td>
              <td style=\"padding:8px 12px;border-bottom:1px solid #334155;\">" . htmlspecialchars((string)($r['Owner'] ?? ''), ENT_QUOTES) . "</td>
              <td style=\"padding:8px 12px;border-bottom:1px solid #334155;font-size:12px;color:#94a3b8;\">" . htmlspecialchars((string)($r['UpdatedAt'] ?? ''), ENT_QUOTES) . "</td>
            </tr>";
            $n++;
        }

        $mail->isHTML(true);
        $mail->Body = "
<!DOCTYPE html>
<html lang='en'>
<head><meta charset='UTF-8'></head>
<body style=\"margin:0;padding:0;background:#020617;font-family:'Segoe UI',Arial,sans-serif;color:#e2e8f0;\">
  <div style=\"max-width:900px;margin:30px auto;background:#0f172a;border-radius:12px;overflow:hidden;box-shadow:0 8px 32px rgba(0,0,0,0.5);\">

    <!-- Header -->
    <div style=\"background:linear-gradient(135deg,#f59e0b,#d97706);padding:28px 32px;\">
      <h1 style=\"margin:0;font-size:22px;color:#020617;font-weight:700;\">
        ⚠️ Authorization Request – Scrap Material Destruction
      </h1>
      <p style=\"margin:8px 0 0;font-size:14px;color:#1c1917;\">
        Xinya Asset Inventory System &nbsp;|&nbsp; {$fecha}
      </p>
    </div>

    <!-- Body -->
    <div style=\"padding:28px 32px;\">
      <p style=\"margin:0 0 16px;font-size:15px;line-height:1.6;\">
        Dear <strong>César</strong>,<br><br>
        The following list of <strong>{$total} material(s)</strong> has been generated. These items are currently
        in <span style=\"color:#f59e0b;font-weight:600;\">Scrap</span> status and are
        pending authorization for their <strong>final destruction</strong>.
      </p>
      <p style=\"margin:0 0 24px;font-size:14px;color:#94a3b8;\">
        Please review the list and reply to this email indicating which items
        you approve for destruction.
      </p>

      <!-- Tabla -->
      <div style=\"overflow-x:auto;border-radius:8px;border:1px solid #334155;\">
        <table style=\"width:100%;border-collapse:collapse;font-size:13px;\">
          <thead>
            <tr style=\"background:#1e40af;\">
              <th style=\"padding:10px 12px;text-align:left;color:#bfdbfe;font-weight:600;\">#</th>
              <th style=\"padding:10px 12px;text-align:left;color:#bfdbfe;font-weight:600;\">ID</th>
              <th style=\"padding:10px 12px;text-align:left;color:#bfdbfe;font-weight:600;\">Description</th>
              <th style=\"padding:10px 12px;text-align:left;color:#bfdbfe;font-weight:600;\">Brand</th>
              <th style=\"padding:10px 12px;text-align:left;color:#bfdbfe;font-weight:600;\">Model</th>
              <th style=\"padding:10px 12px;text-align:left;color:#bfdbfe;font-weight:600;\">Serial</th>
              <th style=\"padding:10px 12px;text-align:center;color:#bfdbfe;font-weight:600;\">Qty</th>
              <th style=\"padding:10px 12px;text-align:left;color:#bfdbfe;font-weight:600;\">Dept</th>
              <th style=\"padding:10px 12px;text-align:left;color:#bfdbfe;font-weight:600;\">Owner</th>
              <th style=\"padding:10px 12px;text-align:left;color:#bfdbfe;font-weight:600;\">Scrap Date</th>
            </tr>
          </thead>
          <tbody>
            {$rows}
          </tbody>
        </table>
      </div>

      <!-- Nota -->
      <div style=\"margin-top:24px;padding:16px;background:#1e293b;border-left:4px solid #f59e0b;border-radius:0 8px 8px 0;\">
        <p style=\"margin:0;font-size:13px;color:#cbd5e1;\">
          <strong style=\"color:#f59e0b;\">Note:</strong>
          If not all items are approved for destruction, please specify the exact IDs
          that can be destroyed. The rest will remain in <em>Scrap</em> status
          until further instruction.
        </p>
      </div>
    </div>

    <!-- Footer -->
    <div style=\"padding:16px 32px;background:#020617;border-top:1px solid #1e293b;\">
      <p style=\"margin:0;font-size:12px;color:#475569;text-align:center;\">
        This is an automated message from the Xinya Asset Inventory System.
        Please do not reply directly to this automated email; reply to the sender with your authorization.
      </p>
    </div>
  </div>
</body>
</html>";

        // Plain text fallback
        $plain = "Scrap Material Destruction Request – Engineering\n";
        $plain .= "Date: {$fecha}\n";
        $plain .= "Total items: {$total}\n\n";
        foreach ($items as $i => $r) {
            $plain .= ($i + 1) . ". [{$r['ID']}] {$r['Description']} | {$r['Brand']} {$r['Model']} | S/N: {$r['SerialNumber']} | Qty: {$r['Qty']}\n";
        }
        $mail->AltBody = $plain;

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log('[aio_scrap_mail] Error: ' . $mail->ErrorInfo);
        return false;
    }
}
