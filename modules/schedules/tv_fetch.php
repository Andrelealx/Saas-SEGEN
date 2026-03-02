<?php
require __DIR__.'/../../core/db.php';
date_default_timezone_set('America/Sao_Paulo');

$today = date('Y-m-d');

// BUSCA APENAS ESCALAS PUBLICADAS
$sql = "
    SELECT 
        sd.id,
        sd.ref_date,
        sd.published,
        COALESCE(p.name,'—') AS post_name
    FROM schedule_days sd
    LEFT JOIN posts p ON p.id = sd.post_id
    WHERE sd.ref_date = ? 
      AND sd.published = 1
    ORDER BY p.name ASC
";

$st = $pdo->prepare($sql);
$st->execute([$today]);
$days = $st->fetchAll();

// MONTA HTML
$html = "";

if (!$days) {
    $html .= "
        <div class='card-tv text-slate-400 text-sm'>
            Nenhuma escala publicada para hoje.
        </div>
    ";
    echo $html;
    exit;
}

foreach ($days as $d) {

    // BUSCA AGENTES DA ESCALA
    $st2 = $pdo->prepare("
        SELECT e.name AS emp, s.code AS sh, s.start_time, s.end_time
        FROM shift_assignments sa
        JOIN employees e ON e.id = sa.employee_id
        JOIN shifts s    ON s.id = sa.shift_id
        WHERE sa.schedule_day_id = ?
        ORDER BY s.start_time ASC
    ");
    $st2->execute([$d['id']]);
    $agents = $st2->fetchAll();

    // Se não tiver agentes designados, não exibe no modo TV
    if (!$agents) continue;

    // STATUS
    $statusHTML = "<span class='status-pill status-published'>Publicado</span>";

    // BLOCO DO POSTO
    $html .= "
        <div class='card-tv'>
            <div class='flex justify-between items-center mb-2'>
                <div class='post-title'>".htmlspecialchars($d['post_name'])."</div>
                $statusHTML
            </div>

            <div class='text-xs text-slate-400 mb-2'>
                Data: ".date('d/m/Y', strtotime($d['ref_date']))."
            </div>

            <div class='flex flex-col gap-1'>
    ";

    // AGENTES
    foreach ($agents as $a) {
        $html .= "
            <div class='agent-line'>
                <span class='text-slate-200 font-medium'>{$a['emp']}</span><br>
                <span class='agent-shift'>{$a['sh']} • ".substr($a['start_time'],0,5)."–".substr($a['end_time'],0,5)."</span>
            </div>
        ";
    }

    $html .= "</div></div>";
}

echo $html;
