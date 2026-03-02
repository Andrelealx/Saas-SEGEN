<?php
require __DIR__.'/../../core/auth.php'; auth_require();
require __DIR__.'/../../core/db.php';
require __DIR__.'/../../core/utils.php';

$today = date('Y-m-d');

// schedule_days + posts
$st = $pdo->prepare("
  SELECT sd.id, sd.ref_date, sd.published, COALESCE(p.name,'—') post
  FROM schedule_days sd
  LEFT JOIN posts p ON p.id = sd.post_id
  WHERE sd.ref_date = ?
  ORDER BY p.name
");
$st->execute([$today]);
$days = $st->fetchAll();

// carrega designações (se existir tabela)
$assigns = [];
try {
  $sa = $pdo->prepare("
    SELECT a.schedule_day_id, e.name emp, s.name shift_name
    FROM shift_assignments a
    LEFT JOIN employees e ON e.id = a.employee_id
    LEFT JOIN shifts s ON s.id = a.shift_id
    WHERE a.schedule_day_id IN (
      SELECT id FROM schedule_days WHERE ref_date = ?
    )
    ORDER BY e.name
  ");
  $sa->execute([$today]);
  foreach($sa->fetchAll() as $row){
    $assigns[$row['schedule_day_id']][] = $row;
  }
} catch(Throwable $e) {
  // se nao existir tabela, ignora
}

require __DIR__.'/../../inc/header.php';
?>

<div class="flex items-center justify-between mb-6">
  <h1 class="text-2xl font-bold flex items-center gap-2">
    <i data-lucide="radar" class="h-6 w-6 text-orange-400"></i>
    Plantão de hoje (<?=h($today)?>)
  </h1>
  <a href="index.php" class="btn">
    <i data-lucide="arrow-left" class="h-4 w-4"></i> Voltar
  </a>
</div>

<?php if(!$days): ?>
  <div class="card p-6 text-gray-300">
    Nenhuma escala encontrada para hoje.
  </div>
<?php else: ?>

  <div class="grid md:grid-cols-2 gap-4">
    <?php foreach($days as $d): ?>
      <div class="card p-4">
        <div class="flex justify-between items-center mb-2">
          <div>
            <div class="text-sm text-gray-400">Posto</div>
            <div class="font-semibold text-lg"><?=h($d['post'])?></div>
          </div>
          <div>
            <?php
              $cls = $d['published'] ? 'green' : 'orange';
              $lbl = $d['published'] ? 'Publicado' : 'Rascunho';
            ?>
            <span class="badge <?=$cls?>"><?=$lbl?></span>
          </div>
        </div>
        <div class="text-xs text-gray-400 mb-2">
          Data: <?=h($d['ref_date'])?>
        </div>

        <?php if (!empty($assigns[$d['id']])): ?>
          <ul class="text-sm text-gray-200 space-y-1">
            <?php foreach($assigns[$d['id']] as $a): ?>
              <li>
                • <?=h($a['emp'])?>
                <?php if($a['shift_name']): ?>
                  <span class="text-gray-400"> (<?=h($a['shift_name'])?>)</span>
                <?php endif; ?>
              </li>
            <?php endforeach; ?>
          </ul>
        <?php else: ?>
          <div class="text-sm text-gray-500 italic">
            Nenhum servidor designado.
          </div>
        <?php endif; ?>

        <div class="mt-3 flex gap-2 justify-end">
          <a href="view.php?day=<?=$d['id']?>" class="text-orange-300 hover:underline text-xs">
            Ver escala completa
          </a>
        </div>
      </div>
    <?php endforeach; ?>
  </div>

<?php endif; ?>

<?php require __DIR__.'/../../inc/footer.php'; ?>
