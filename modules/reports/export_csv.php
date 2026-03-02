<?php
require __DIR__.'/../../core/auth.php'; auth_require();
require __DIR__.'/../../core/db.php';

/* --- DEBUG TEMPORÁRIO (remova depois) ---
ini_set('display_errors',1); ini_set('display_startup_errors',1); error_reporting(E_ALL);
// ---------------------------------------- */

// Sanitiza inputs
$ini = $_GET['ini'] ?? '';
$fim = $_GET['fim'] ?? '';
$tipo= $_GET['tipo'] ?? 'sdr';
$post_id = isset($_GET['post_id']) && $_GET['post_id']!=='' ? (int)$_GET['post_id'] : null;
$employee_id = isset($_GET['employee_id']) && $_GET['employee_id']!=='' ? (int)$_GET['employee_id'] : null;

// Validação básica de data (YYYY-MM-DD)
$validDate = function($d){ return (bool)preg_match('/^\d{4}-\d{2}-\d{2}$/', $d); };
if (!$validDate($ini) || !$validDate($fim)) {
  http_response_code(400); exit('Datas inválidas.');
}

// Evita espaço em branco no output antes dos headers
ob_clean();

// Define nome do arquivo
$filename = "relatorio_{$tipo}_{$ini}_{$fim}.csv";

// Cabeçalhos CSV
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="'.$filename.'"');

// Abre saída
$out = fopen('php://output', 'w');

// Gera CSV conforme o tipo
try {
  if ($tipo === 'sdr') {
    // Cabeçalho
    fputcsv($out, ['Servidor','Data','Início','Fim','Horas','Status','Motivo']);

    // Monta SQL com filtros opcionais
    $sql = "SELECT e.name AS servidor, o.ref_date, o.start_time, o.end_time, o.hours, o.status, o.reason
            FROM overtime_requests o
            LEFT JOIN employees e ON e.id = o.employee_id
            WHERE o.ref_date BETWEEN ? AND ?";

    $params = [$ini, $fim];

    if ($employee_id) {
      $sql .= " AND o.employee_id = ?";
      $params[] = $employee_id;
    }

    $sql .= " ORDER BY o.ref_date ASC, e.name ASC";

    $q = $pdo->prepare($sql);
    $q->execute($params);

    while ($r = $q->fetch()) {
      fputcsv($out, [
        $r['servidor'],
        $r['ref_date'],
        substr($r['start_time'],0,5),
        substr($r['end_time'],0,5),
        $r['hours'],
        $r['status'],
        $r['reason']
      ]);
    }

  } else { // escala
    fputcsv($out, ['Data','Posto','Agente','Turno','Início','Fim']);

    $sql = "SELECT sd.ref_date,
                   COALESCE(p.name,'—') AS post,
                   e.name AS emp,
                   s.code AS sh,
                   s.start_time,
                   s.end_time
            FROM shift_assignments sa
            JOIN schedule_days sd ON sd.id = sa.schedule_day_id
            LEFT JOIN posts p      ON p.id = sd.post_id
            LEFT JOIN employees e  ON e.id = sa.employee_id
            LEFT JOIN shifts s     ON s.id = sa.shift_id
            WHERE sd.ref_date BETWEEN ? AND ?";

    $params = [$ini, $fim];

    if ($post_id) {
      $sql .= " AND sd.post_id = ?";
      $params[] = $post_id;
    }
    if ($employee_id) {
      $sql .= " AND sa.employee_id = ?";
      $params[] = $employee_id;
    }

    $sql .= " ORDER BY sd.ref_date ASC, p.name ASC, s.start_time ASC";

    $q = $pdo->prepare($sql);
    $q->execute($params);

    while ($r = $q->fetch()) {
      fputcsv($out, [
        $r['ref_date'],
        $r['post'],
        $r['emp'],
        $r['sh'],
        substr($r['start_time'],0,5),
        substr($r['end_time'],0,5)
      ]);
    }
  }
} catch (Throwable $e) {
  // Se der erro aqui, tentamos retornar uma linha de aviso no CSV
  fputcsv($out, ['ERRO:', $e->getMessage()]);
}

// Fecha CSV
fclose($out);
exit;
