<?php
require __DIR__.'/../../core/auth.php'; auth_require();
require __DIR__.'/../../core/db.php';
require __DIR__.'/../../core/utils.php';

$month = $_GET['month'] ?? date('Y-m');
$post_id = $_GET['post_id'] ?? '';
$employee_id = $_GET['employee_id'] ?? '';

$firstDay = $month . '-01';
$lastDay  = date('Y-m-t', strtotime($firstDay));

$where = "sd.ref_date BETWEEN ? AND ?";
$params = [$firstDay, $lastDay];

if ($post_id) {
    $where .= " AND sd.post_id = ?";
    $params[] = $post_id;
}
if ($employee_id) {
    $where .= " AND e.id = ?";
    $params[] = $employee_id;
}

// consulta principal
$sql = "
  SELECT e.id, e.name, COUNT(DISTINCT sd.ref_date) AS dias
  FROM shift_assignments a
  JOIN schedule_days sd ON sd.id = a.schedule_day_id
  JOIN employees e ON e.id = a.employee_id
  WHERE $where
  GROUP BY e.id, e.name
  ORDER BY dias DESC, e.name
";
$st = $pdo->prepare($sql);
$st->execute($params);
$rows = $st->fetchAll();

// coleta de dados auxiliares
$posts = $pdo->query("SELECT id,name FROM posts ORDER BY name")->fetchAll();
$employees = $pdo->query("SELECT id,name FROM employees ORDER BY name")->fetchAll();

$totalEscalacoes = array_sum(array_column($rows, 'dias'));
$servidores = count($rows);
$media = $servidores ? round($totalEscalacoes / $servidores, 2) : 0;
$max = $servidores ? max(array_column($rows, 'dias')) : 0;
$min = $servidores ? min(array_column($rows, 'dias')) : 0;

require __DIR__.'/../../inc/header.php';
?>

<style>
.summary-card {
    @apply bg-gray-900/50 border border-gray-800 rounded-xl p-5 text-center shadow-lg;
}
.summary-title {
    @apply text-xs tracking-widest text-gray-400 uppercase;
}
.summary-value {
    @apply text-3xl font-bold text-gray-100;
}
.heat {
    height: 8px;
    border-radius: 4px;
    background: linear-gradient(90deg, #ffa500, #ff4500);
}
</style>

<div class="flex items-center justify-between mb-6">
  <h1 class="text-2xl font-bold flex items-center gap-2">
    <i data-lucide="chart-bar" class="h-6 w-6 text-orange-400"></i>
    Relatório Mensal de Escalas
  </h1>

  <a href="index.php" class="btn">
    <i data-lucide="arrow-left" class="h-4 w-4"></i> Voltar
  </a>
</div>

<!-- Resumo geral -->
<div class="grid md:grid-cols-4 gap-4 mb-6">

  <div class="summary-card">
    <div class="summary-title">Servidores Escalados</div>
    <div class="summary-value"><?= $servidores ?></div>
  </div>

  <div class="summary-card">
    <div class="summary-title">Total de Dias Escalados</div>
    <div class="summary-value"><?= $totalEscalacoes ?></div>
  </div>

  <div class="summary-card">
    <div class="summary-title">Média por Servidor</div>
    <div class="summary-value"><?= $media ?></div>
  </div>

  <div class="summary-card">
    <div class="summary-title">Maior / Menor</div>
    <div class="summary-value"><?= $max ?> / <?= $min ?></div>
  </div>

</div>

<!-- FILTROS -->
<div class="card p-4 mb-4">
  <form method="get" class="grid md:grid-cols-4 gap-3 items-end">

    <div>
      <label class="text-sm">Mês</label>
      <input type="month" name="month" class="input" value="<?=h($month)?>">
    </div>

    <div>
      <label class="text-sm">Posto</label>
      <select name="post_id" class="select">
        <option value="">Todos</option>
        <?php foreach($posts as $p): ?>
        <option value="<?=$p['id']?>" <?=$post_id==$p['id']?'selected':''?>>
          <?=h($p['name'])?>
        </option>
        <?php endforeach; ?>
      </select>
    </div>

    <div>
      <label class="text-sm">Servidor</label>
      <select name="employee_id" class="select">
        <option value="">Todos</option>
        <?php foreach($employees as $e): ?>
        <option value="<?=$e['id']?>" <?=$employee_id==$e['id']?'selected':''?>>
          <?=h($e['name'])?>
        </option>
        <?php endforeach; ?>
      </select>
    </div>

    <button class="btn px-6">Aplicar</button>

  </form>
</div>

<!-- TABELA DE RESULTADOS -->
<?php if(!$rows): ?>
  <div class="card p-6 text-gray-300">
    Nenhuma escala encontrada para esse filtro.
  </div>
<?php else: ?>

<div class="card p-0 overflow-x-auto">
<table class="table w-full">
    <thead>
      <tr>
        <th>Servidor</th>
        <th>Dias escalados</th>
        <th>Distribuição</th>
      </tr>
    </thead>
    <tbody>

      <?php foreach($rows as $r): 
        $perc = $max > 0 ? ($r['dias'] / $max) * 100 : 0;
      ?>
      <tr class="tr">
        <td class="font-medium"><?=h($r['name'])?></td>
        <td><?=h($r['dias'])?></td>
        <td>
          <div class="heat" style="width: <?=$perc?>%"></div>
        </td>
      </tr>
      <?php endforeach; ?>

    </tbody>
</table>
</div>

<!-- Exportações -->
<div class="flex gap-3 mt-4">
  <a href="report_export_csv.php?month=<?=$month?>" class="btn btn-muted">
    <i data-lucide="download" class="w-4 h-4"></i>
    Exportar CSV
  </a>

  <a href="report_export_pdf.php?month=<?=$month?>" class="btn btn-muted">
    <i data-lucide="file" class="w-4 h-4"></i>
    Exportar PDF
  </a>
</div>

<?php endif; ?>

<?php require __DIR__.'/../../inc/footer.php'; ?>
