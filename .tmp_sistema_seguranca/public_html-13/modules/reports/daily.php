<?php
$requireAuth = true;
require_once __DIR__ . '/../../core/auth.php';
require_once __DIR__ . '/../../core/db.php';
require_once __DIR__ . '/../../inc/header.php';

date_default_timezone_set('America/Sao_Paulo');
$hoje = date('Y-m-d');
$hoje_br = date('d/m/Y');

/* =======================================
   COLETA DE DADOS PARA O RELATÓRIO
======================================== */

// Funcionários ativos
$ativos = (int)$pdo->query("
    SELECT COUNT(*) 
    FROM employees 
    WHERE status='ATIVO'
")->fetchColumn();

// SDR pendentes hoje
$pend_sdr = (int)$pdo->query("
    SELECT COUNT(*) 
    FROM overtime_requests 
    WHERE status='PENDENTE'
    AND DATE(created_at) = CURDATE()
")->fetchColumn();

// Escalas publicadas hoje
$esc_hoje = $pdo->query("
    SELECT 
        sd.*, 
        COALESCE(p.name,'—') AS post
    FROM schedule_days sd
    LEFT JOIN posts p ON p.id = sd.post_id
    WHERE sd.ref_date = CURDATE()
")->fetchAll();

// SDR registradas hoje
$sdr_hoje = $pdo->query("
    SELECT 
        orq.id,
        e.name AS funcionario,
        orq.reason,
        orq.status,
        DATE_FORMAT(orq.created_at, '%H:%i') hora
    FROM overtime_requests orq
    LEFT JOIN employees e ON e.id = orq.employee_id
    WHERE DATE(orq.created_at)=CURDATE()
    ORDER BY orq.created_at DESC
")->fetchAll();
?>

<style>
.print-area { padding: 20px; }
@media print {
    .no-print, header, nav { display: none !important; }
    body { background: #fff !important; color: #000 !important; }
}
</style>

<div class="print-area card p-6">

    <div class="flex justify-between items-center mb-6">
        <div>
            <h1 class="text-xl font-bold">Relatório Diário – RH Segurança</h1>
            <p class="text-sm text-gray-400">Data: <?=$hoje_br?></p>
        </div>

        <button onclick="window.print()" class="btn no-print">
            🖨 Imprimir
        </button>
    </div>

    <!-- KPIs -->
    <h2 class="font-semibold mb-2">Indicadores do Dia</h2>
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">

        <div class="card p-4 bg-gray-800/50">
            <div class="text-sm text-gray-400">Funcionários ativos</div>
            <div class="text-2xl font-bold"><?=$ativos?></div>
        </div>

        <div class="card p-4 bg-gray-800/50">
            <div class="text-sm text-gray-400">SDR pendentes</div>
            <div class="text-2xl font-bold text-orange-400"><?=$pend_sdr?></div>
        </div>

        <div class="card p-4 bg-gray-800/50">
            <div class="text-sm text-gray-400">Escalas publicadas hoje</div>
            <div class="text-2xl font-bold"><?=count($esc_hoje)?></div>
        </div>

        <div class="card p-4 bg-gray-800/50">
            <div class="text-sm text-gray-400">Total de SDR hoje</div>
            <div class="text-2xl font-bold"><?=count($sdr_hoje)?></div>
        </div>

    </div>

    <!-- Escalas do dia -->
    <h2 class="font-semibold mb-2">Escalas Publicadas Hoje</h2>
    <table class="table mb-6">
        <thead>
            <tr>
                <th>Data</th>
                <th>Posto</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($esc_hoje as $esc): ?>
            <tr class="tr">
                <td><?=$esc['ref_date']?></td>
                <td><?=$esc['post']?></td>
                <td>
                    <span class="badge <?=$esc['published']?'green':'orange'?>">
                        <?=$esc['published']?'Publicado':'Rascunho'?>
                    </span>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

    <!-- SDR do dia -->
    <h2 class="font-semibold mb-2">SDR Registradas Hoje</h2>

    <table class="table mb-6">
        <thead>
            <tr>
                <th>Hora</th>
                <th>Funcionário</th>
                <th>Motivo</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
        <?php if (count($sdr_hoje) == 0): ?>
            <tr><td colspan="4" class="text-center text-gray-400">Nenhuma SDR registrada hoje.</td></tr>
        <?php endif; ?>

        <?php foreach ($sdr_hoje as $sdr): ?>
            <tr class="tr">
                <td><?=$sdr['hora']?></td>
                <td><?=$sdr['funcionario']?></td>
                <td><?=$sdr['reason']?></td>
                <td>
                    <span class="badge <?=$sdr['status']=='PENDENTE'?'orange':'green'?>">
                        <?=$sdr['status']?>
                    </span>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

</div>

<?php require_once __DIR__ . '/../../inc/footer.php'; ?>
