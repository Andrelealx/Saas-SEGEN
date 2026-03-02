<?php
$requireAuth = true;
require_once __DIR__.'/core/auth.php';
require_once __DIR__.'/core/db.php';
require_once __DIR__.'/inc/header.php';

date_default_timezone_set('America/Sao_Paulo');
$hoje = date('Y-m-d');

/* ===============================
   RELATÓRIO DO DIA
================================*/

// Funcionários ativos
$ativos = (int)$pdo->query("SELECT COUNT(*) FROM employees WHERE status='ATIVO'")->fetchColumn();

// Funcionários ausentes (usei fallback: consulta zerada porque depende de tabela extra)
$ausentes = 0;

// SDR pendentes hoje
$pend_sdr = (int)$pdo->query("
    SELECT COUNT(*) 
    FROM overtime_requests 
    WHERE status='PENDENTE' 
    AND DATE(created_at) = CURDATE()
")->fetchColumn();

// Escalas publicadas hoje
$escalas_hoje = (int)$pdo->query("
    SELECT COUNT(*) 
    FROM schedule_days 
    WHERE ref_date = CURDATE() 
    AND published = 1
")->fetchColumn();

/* ===============================
   ESCALAS DOS PRÓXIMOS DIAS
================================*/

$proximas = $pdo->query("
    SELECT 
        sd.ref_date, 
        COALESCE(p.name,'—') AS post, 
        sd.published
    FROM schedule_days sd
    LEFT JOIN posts p ON p.id = sd.post_id
    WHERE sd.ref_date >= CURDATE()
    ORDER BY sd.ref_date ASC
    LIMIT 5
")->fetchAll();
?>

<div class="grid gap-4 lg:grid-cols-4">

  <!-- Relatório do dia -->
  <div class="card lg:col-span-4">
    <h2 class="font-semibold text-lg">📅 Relatório do dia — <?=date('d/m/Y')?></h2>
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mt-4">
      
      <div class="card p-4 bg-gray-800/60">
        <div class="text-sm text-gray-400">Funcionários ativos</div>
        <div class="text-2xl font-bold"><?=$ativos?></div>
      </div>

      <div class="card p-4 bg-gray-800/60">
        <div class="text-sm text-gray-400">Ausências registradas</div>
        <div class="text-2xl font-bold"><?=$ausentes?></div>
      </div>

      <div class="card p-4 bg-gray-800/60">
        <div class="text-sm text-gray-400">SDR pendentes hoje</div>
        <div class="text-2xl font-bold text-orange-400"><?=$pend_sdr?></div>
      </div>

      <div class="card p-4 bg-gray-800/60">
        <div class="text-sm text-gray-400">Escalas publicadas hoje</div>
        <div class="text-2xl font-bold"><?=$escalas_hoje?></div>
      </div>

    </div>
  </div>

  <!-- Próximas escalas -->
  <div class="card lg:col-span-3">
    <div class="flex justify-between">
      <h2 class="font-semibold">📆 Próximas escalas</h2>
      <a href="/modules/schedules/" class="text-orange-300 text-sm hover:underline">ver todas</a>
    </div>

    <table class="table mt-4">
      <thead>
        <tr>
          <th>Data</th>
          <th>Posto</th>
          <th>Status</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($proximas as $r): ?>
        <tr class="tr">
          <td><?=htmlspecialchars($r['ref_date'])?></td>
          <td><?=htmlspecialchars($r['post'])?></td>
          <td>
            <span class="badge <?=$r['published'] ? 'green' : 'orange'?>">
              <?=$r['published'] ? 'Publicado' : 'Rascunho'?>
            </span>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <!-- Ações rápidas -->
  <div class="card">
    <h2 class="font-semibold">⚡ Ações rápidas</h2>
    <div class="mt-3 grid gap-2">
      <a href="/modules/employees/create.php" class="btn">+ Novo funcionário</a>
      <a href="/modules/schedules/builder.php" class="btn">+ Gerar escala</a>
      <a href="/modules/overtime/request.php" class="btn">+ Registrar SDR</a>
      <a href="/modules/reports/daily.php" class="btn bg-orange-600 hover:bg-orange-700">📄 Relatório diário</a>
    </div>
  </div>

</div>

<?php require __DIR__.'/inc/footer.php'; ?>
