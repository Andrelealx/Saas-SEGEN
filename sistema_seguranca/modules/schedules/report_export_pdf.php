<?php
date_default_timezone_set('America/Sao_Paulo');

ini_set('display_errors',1);
ini_set('display_startup_errors',1);
error_reporting(E_ALL);

require __DIR__ . '/../../core/auth.php';
auth_require();
require __DIR__ . '/../../core/db.php';
require __DIR__ . '/../../core/utils.php';

/* ==== Localizar DOMPDF ==== */
$paths = [
    __DIR__ . '/../../vendor/autoload.php',
    __DIR__ . '/../../vendor/dompdf/autoload.inc.php',
    __DIR__ . '/../../dompdf/autoload.inc.php'
];
$loaded = false;
foreach ($paths as $p) {
    if (file_exists($p)) {
        require $p;
        $loaded = true;
        break;
    }
}
if (!$loaded) {
    die("ERRO: DOMPDF não encontrado.");
}

use Dompdf\Dompdf;
use Dompdf\Options;

/* ======= LÓGICA DO RELATÓRIO ======= */
$month = $_GET['month'] ?? date('Y-m');
$firstDay = $month . "-01";
$lastDay  = date("Y-m-t", strtotime($firstDay));

$sql = "
  SELECT e.name, COUNT(DISTINCT sd.ref_date) AS dias
  FROM shift_assignments a
  JOIN schedule_days sd ON sd.id = a.schedule_day_id
  JOIN employees e ON e.id = a.employee_id
  WHERE sd.ref_date BETWEEN ? AND ?
  GROUP BY e.id, e.name
  ORDER BY dias DESC, e.name
";
$st = $pdo->prepare($sql);
$st->execute([$firstDay, $lastDay]);
$rows = $st->fetchAll();

$total = array_sum(array_column($rows, "dias"));
$servidores = count($rows);
$media = $servidores ? round($total / $servidores, 2) : 0;

/* ==== Logo ==== */
$logoPath = __DIR__ . "/../../assets/img/logo_guapi.png";

$logoBase64 = file_exists($logoPath)
    ? base64_encode(file_get_contents($logoPath))
    : "";

/* ======== HTML PROFISSIONAL ======== */
$html = '
<style>
body {
    font-family: Helvetica, Arial, sans-serif;
    font-size: 12px;
    color: #1a1a1a;
    margin: 0;
    padding: 0;
}

.header {
    background: #0a1a2f;
    color: white;
    padding: 18px 25px;
    display:flex;
    align-items:center;
    gap:20px;
}

.header img {
    height: 58px;
}

.header-text {
    display:flex;
    flex-direction:column;
}

.header-title {
    font-size: 20px;
    font-weight: bold;
    text-transform: uppercase;
    letter-spacing: .04em;
}

.header-sub {
    font-size: 12px;
    color: #d1d5db;
}

.section-title {
    font-size: 16px;
    margin: 25px 0 8px 0;
    font-weight: bold;
    text-transform: uppercase;
    border-left: 4px solid #0a1a2f;
    padding-left: 10px;
}

.metrics {
    width: 100%;
    display: flex;
    gap: 10px;
    margin-bottom: 18px;
}

.metric-box {
    flex: 1;
    background: #eef2f7;
    border: 1px solid #c8d0da;
    border-radius: 6px;
    padding: 10px;
    text-align: center;
}

.metric-label {
    font-size: 11px;
    color: #4b5563;
    text-transform: uppercase;
}

.metric-value {
    font-size: 22px;
    font-weight: bold;
    color: #0a1a2f;
}

.table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 10px;
}

.table th {
    background: #0a1a2f;
    color: white;
    padding: 8px;
    font-size: 12px;
    text-transform: uppercase;
    letter-spacing: .05em;
}

.table td {
    padding: 8px;
    border-bottom: 1px solid #d5d5d5;
}

.table tr:nth-child(even) td {
    background: #f6f7f9;
}

.footer {
    margin-top: 30px;
    padding-top: 10px;
    border-top: 1px solid #aaa;
    font-size: 10px;
    text-align: center;
    color: #444;
}
</style>

<!-- HEADER -->
<div class="header">
    <img src="data:image/png;base64,' . $logoBase64 . '">
    <div class="header-text">
        <div class="header-title">Secretaria Municipal de Segurança Pública</div>
        <div class="header-sub">Relatório Mensal de Escalas • Guapimirim - RJ</div>
        <div class="header-sub">Período: ' . date("m/Y", strtotime($month)) . '</div>
    </div>
</div>

<!-- MÉTRICAS -->
<div class="metrics">
    <div class="metric-box">
        <div class="metric-label">Servidores</div>
        <div class="metric-value">'.$servidores.'</div>
    </div>
    <div class="metric-box">
        <div class="metric-label">Dias Escalados</div>
        <div class="metric-value">'.$total.'</div>
    </div>
    <div class="metric-box">
        <div class="metric-label">Média por Servidor</div>
        <div class="metric-value">'.$media.'</div>
    </div>
</div>

<!-- LISTAGEM -->
<div class="section-title">Distribuição de Escalas</div>

<table class="table">
<thead>
<tr>
    <th>Servidor</th>
    <th width="140">Dias Escalados</th>
</tr>
</thead>
<tbody>';

foreach ($rows as $r) {
    $html .= "
    <tr>
        <td>".htmlspecialchars($r['name'])."</td>
        <td>".$r['dias']."</td>
    </tr>";
}

$html .= '
</tbody>
</table>

<div class="footer">
Documento gerado automaticamente pelo Sistema de Escalas • '.date("d/m/Y H:i").'
</div>
';

/* ==== Render PDF ==== */
$options = new Options();
$options->set('isRemoteEnabled', true);
$options->set('defaultFont', 'Helvetica');

$pdf = new Dompdf($options);
$pdf->loadHtml($html);
$pdf->setPaper("A4", "portrait");
$pdf->render();

$pdf->stream("Relatorio_Escalas_".date("m_Y", strtotime($month)).".pdf", ["Attachment"=>true]);
exit;
?>
