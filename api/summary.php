<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=UTF-8');

try {
  require_once __DIR__ . '/../config.php';
  $pdo = pdo();

  // --- OPCIONAL: habilita token fijando un valor no vacío ---
  $DASH_TOKEN = ''; // p.ej. 'mi-token-seguro'; y llama con ?token=mi-token-seguro
  if ($DASH_TOKEN !== '') {
    $tok = $_GET['token'] ?? '';
    if (!hash_equals($DASH_TOKEN, $tok)) {
      http_response_code(403);
      echo json_encode(['status'=>'error','message'=>'Forbidden']); exit;
    }
  }

  // --- Pie: instrumentos por estado (misma lógica que report.php) ---
  $pie_labels = []; $pie_data = [];
  $sql1 = "
    SELECT status_calculado, COUNT(*) AS cnt FROM (
      SELECT CASE
        WHEN CURRENT_DATE() > DueDate THEN 'Vencido'
        WHEN DueDate BETWEEN CURRENT_DATE() AND DATE_ADD(CURRENT_DATE(), INTERVAL 30 DAY) THEN 'Próxima calibración'
        ELSE 'Calibrado'
      END AS status_calculado
      FROM instruments
    ) sub
    GROUP BY status_calculado
  ";
  foreach ($pdo->query($sql1) as $r) {
    $pie_labels[] = (string)$r['status_calculado'];
    $pie_data[]   = (int)$r['cnt'];
  }

  // --- Barras: pendientes por mes (12 meses hacia adelante) ---
  $pending_labels = [];
  for ($i=0; $i<12; $i++) { $pending_labels[] = date('Y-m', strtotime("+{$i} months")); }
  $counts = [];
  $sql2 = "
    SELECT DATE_FORMAT(DueDate, '%Y-%m') AS mes, COUNT(*) AS cnt
    FROM instruments
    WHERE DueDate >= CURRENT_DATE()
      AND DueDate < DATE_ADD(CURRENT_DATE(), INTERVAL 12 MONTH)
    GROUP BY mes ORDER BY mes
  ";
  foreach ($pdo->query($sql2) as $r) { $counts[(string)$r['mes']] = (int)$r['cnt']; }
  $pending_data = array_map(fn($m)=>$counts[$m]??0, $pending_labels);

  echo json_encode([
    'status' => 'success',
    'pie'    => ['labels'=>$pie_labels, 'data'=>$pie_data],
    'pending'=> ['labels'=>$pending_labels, 'data'=>$pending_data],
    'updated_at' => date('c'),
  ]);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['status'=>'error','message'=>$e->getMessage()]);
}
