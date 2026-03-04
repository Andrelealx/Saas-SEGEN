<?php
require __DIR__.'/../../core/auth.php';
auth_require();
require __DIR__.'/../../core/db.php';
require __DIR__.'/../../core/utils.php';

date_default_timezone_set('America/Sao_Paulo');

/* deixa o PDO mais “falante” (tira se quiser) */
if (isset($pdo) && $pdo instanceof PDO) {
  $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
}

$today   = date('Y-m-d');
$month   = $_GET['month'] ?? date('Y-m');
$post_id = $_GET['post_id'] ?? '';

if (!preg_match('/^\d{4}\-\d{2}$/', $month)) $month = date('Y-m');

$firstDay = $month . '-01';
$lastDay  = date('Y-m-t', strtotime($firstDay));

$prevMonth = date('Y-m', strtotime($firstDay.' -1 month'));
$nextMonth = date('Y-m', strtotime($firstDay.' +1 month'));

/* -----------------------
   Escalas do mês (schedule_days)
------------------------ */
$where  = "sd.ref_date BETWEEN ? AND ?";
$params = [$firstDay, $lastDay];

if ($post_id !== '') {
  $where .= " AND sd.post_id = ?";
  $params[] = $post_id;
}

$st = $pdo->prepare("
  SELECT sd.id, sd.ref_date, sd.published, sd.post_id,
         COALESCE(p.name,'—') AS post
  FROM schedule_days sd
  LEFT JOIN posts p ON p.id = sd.post_id
  WHERE $where
  ORDER BY sd.ref_date ASC, p.name ASC
");
$st->execute($params);
$days = $st->fetchAll();

/* lista de postos (filtro) */
$posts = $pdo->query("SELECT id, name FROM posts ORDER BY name")->fetchAll();

/* lookup por data (pode ter mais de 1 escala por dia) */
$lookup = [];   // YYYY-MM-DD => [rows...]
$dayIds = [];
foreach ($days as $d) {
  $lookup[$d['ref_date']][] = $d;
  $dayIds[] = (int)$d['id'];
}

/* -----------------------
   Alocações (shift_assignments) por schedule_day
------------------------ */
$assignByDayId = [];
$totalAssignmentsMonth = 0;

if ($dayIds) {
  $in = implode(',', array_fill(0, count($dayIds), '?'));
  $stA = $pdo->prepare("
    SELECT schedule_day_id, COUNT(*) AS cnt
    FROM shift_assignments
    WHERE schedule_day_id IN ($in)
    GROUP BY schedule_day_id
  ");
  $stA->execute($dayIds);
  foreach($stA->fetchAll() as $r){
    $assignByDayId[(int)$r['schedule_day_id']] = (int)$r['cnt'];
    $totalAssignmentsMonth += (int)$r['cnt'];
  }
}

/* heatmap por posto */
$postCount = [];
foreach ($days as $d) {
  $postCount[$d['post']] = ($postCount[$d['post']] ?? 0) + 1;
}

/* -----------------------
   SDR do mês (overtime_requests)
------------------------ */
$sdrMonthTotal = 0;
$sdrByDate   = []; // YYYY-MM-DD => count
$sdrByStatus = []; // status => count

try {
  $stS = $pdo->prepare("
    SELECT ref_date, status, COUNT(*) AS cnt
    FROM overtime_requests
    WHERE ref_date BETWEEN ? AND ?
    GROUP BY ref_date, status
  ");
  $stS->execute([$firstDay, $lastDay]);
  foreach ($stS->fetchAll() as $r) {
    $d   = (string)$r['ref_date'];
    $stt = (string)$r['status'];
    $cnt = (int)$r['cnt'];

    $sdrByDate[$d] = ($sdrByDate[$d] ?? 0) + $cnt;
    $sdrByStatus[$stt] = ($sdrByStatus[$stt] ?? 0) + $cnt;
    $sdrMonthTotal += $cnt;
  }
} catch (Throwable $e) {
  // silencioso
}

/* -----------------------
   Plantão de hoje (resumo)
------------------------ */
$todaySummary = [];
try {
  $stToday = $pdo->prepare("
    SELECT sd.id,
           COALESCE(p.name,'—') AS post,
           sd.published,
           COUNT(sa.id) AS total_agents
    FROM schedule_days sd
    LEFT JOIN posts p ON p.id = sd.post_id
    LEFT JOIN shift_assignments sa ON sa.schedule_day_id = sd.id
    WHERE sd.ref_date = ?
    GROUP BY sd.id, sd.published, p.name
    ORDER BY p.name
  ");
  $stToday->execute([$today]);
  $todaySummary = $stToday->fetchAll();
} catch (Throwable $e) {
  $todaySummary = [];
}

/* -----------------------
   Resumo do mês
------------------------ */
$totalRows = count($days);
$published = count(array_filter($days, fn($d) => (int)$d['published'] === 1));
$late      = count(array_filter($days, fn($d) => (int)$d['published'] === 0 && $d['ref_date'] < $today));
$drafts    = max(0, $totalRows - $published - $late);

/* -----------------------
   Agrupamentos (groups + group_fixed_rules + group_members)
------------------------ */
$groupsInfo = [];
$groupMembersCount = [];
$groupPlannedDays  = [];

try {
  $groupsInfo = $pdo->query("SELECT id, name FROM groups ORDER BY name")->fetchAll();

  $stGM = $pdo->query("SELECT group_id, COUNT(*) AS cnt FROM group_members GROUP BY group_id");
  foreach ($stGM->fetchAll() as $r) {
    $groupMembersCount[(int)$r['group_id']] = (int)$r['cnt'];
  }

  $rules = $pdo->query("
    SELECT group_id, weekday
    FROM group_fixed_rules
    WHERE is_active=1
  ")->fetchAll(PDO::FETCH_ASSOC);

  $weekdaysByGroup = [];
  foreach ($rules as $r) {
    $gid = (int)$r['group_id'];
    $wd  = (int)$r['weekday'];
    $weekdaysByGroup[$gid][$wd] = true;
  }

  $start = new DateTime($firstDay);
  $end   = new DateTime($lastDay);
  $end->setTime(0,0,0);

  foreach ($weekdaysByGroup as $gid => $wds) {
    $cnt = 0;
    $cur = clone $start;
    while ($cur <= $end) {
      $wd = (int)$cur->format('w');
      if (isset($wds[$wd])) $cnt++;
      $cur->modify('+1 day');
    }
    $groupPlannedDays[$gid] = $cnt;
  }
} catch (Throwable $e) {
  $groupsInfo = [];
}

/* -----------------------
   Calendário
------------------------ */
$firstCalendarDay = (int)date('w', strtotime($firstDay));
$daysInMonth      = (int)date('t', strtotime($firstDay));

require __DIR__.'/../../inc/header.php';
?>


<style>
/* ===== Calendário (legível / institucional) ===== */
.calendar-container{
  background: rgba(17, 24, 39, .35);
  border: 1px solid rgba(31, 41, 55, .9);
  border-radius: 14px;
  padding: 18px;
  box-shadow: 0 20px 40px rgba(0,0,0,.25);
}

.calendar-weekdays{
  display:grid;
  grid-template-columns:repeat(7, minmax(0,1fr));
  gap:10px;
  margin-bottom:10px;
}

.weekday{
  font-size: 12px;
  letter-spacing: .14em;
  text-transform: uppercase;
  color: rgba(148,163,184,.9);
  text-align:center;
  padding: 4px 0;
}

.calendar-grid{
  display:grid;
  grid-template-columns:repeat(7, minmax(0,1fr));
  gap:10px;
}

.calday{
  position:relative;
  border-radius: 12px;
  padding: 10px;
  min-height: 88px;
  border: 1px solid rgba(51,65,85,.65);
  background: rgba(15,23,42,.55);
  cursor:pointer;
  text-align:left;
  transition: transform .08s ease, background-color .15s ease, border-color .15s ease;
}

.calday:hover{
  transform: translateY(-1px);
  border-color: rgba(148,163,184,.45);
  background: rgba(15,23,42,.70);
}

.calday:active{ transform: translateY(0); }

.day-num{
  font-weight: 800;
  font-size: 15px;
  color: rgba(226,232,240,.95);
  line-height: 1;
}

.day-count{
  position:absolute;
  top:8px;
  right:8px;
  font-size: 11px;
  padding: 2px 7px;
  border-radius: 999px;
  background: rgba(2,6,23,.45);
  border: 1px solid rgba(51,65,85,.55);
  color: rgba(226,232,240,.85);
}

.day-metrics{
  position:absolute;
  left:8px;
  right:8px;
  bottom:8px;
  display:flex;
  gap:8px;
}

.metric{
  flex:1;
  display:flex;
  align-items:center;
  justify-content:center;
  gap:6px;
  padding: 6px 8px;
  border-radius: 10px;
  background: rgba(2,6,23,.35);
  border: 1px solid rgba(51,65,85,.55);
  color: rgba(226,232,240,.9);
  font-size: 11px;
  line-height: 1;
  white-space: nowrap; /* <-- evita “quebra vertical” */
}

.metric svg{ width:14px; height:14px; opacity:.9; }

/* Status (cores discretas, sem poluir) */
.calday.status-none{
  background: rgba(15,23,42,.45);
  border-color: rgba(51,65,85,.55);
  color: rgba(148,163,184,.9);
}
.calday.status-draft{
  background: rgba(249,115,22,.12);
  border-color: rgba(249,115,22,.25);
}
.calday.status-published{
  background: rgba(16,185,129,.12);
  border-color: rgba(16,185,129,.25);
}
.calday.status-late{
  background: rgba(239,68,68,.12);
  border-color: rgba(239,68,68,.25);
}
</style></-->

<div class="flex items-center justify-between mb-6">
  <h1 class="page-title">
    <i data-lucide="calendar" class="w-7 h-7 text-orange-400"></i>
    Escalas
  </h1>

  <div class="flex flex-wrap gap-2 justify-end">
    <a href="builder.php" class="action-button">
      <i data-lucide="calendar-plus" class="h-5 w-5"></i> Gerar / Editar Escalas
    </a>

    <button id="btnGenGroups" class="btn btn-muted flex items-center gap-1">
      <i data-lucide="wand-2" class="h-4 w-4"></i> Gerar mês por Agrupamentos
    </button>

    <a href="daily_overview.php" class="btn btn-muted flex items-center gap-1">
      <i data-lucide="radar" class="h-4 w-4"></i> Plantão de hoje
    </a>

    <a href="report.php?month=<?=h($month)?>" class="btn btn-muted flex items-center gap-1">
      <i data-lucide="file-text" class="h-4 w-4"></i> Relatório
    </a>

    <a href="copy_month.php?month=<?=h($month)?>" class="btn btn-muted flex items-center gap-1"
       onclick="return confirm('Copiar escalas do mês anterior para este mês?');">
      <i data-lucide="copy" class="h-4 w-4"></i> Copiar mês anterior
    </a>

    <a href="tv.php" target="_blank" class="btn btn-muted flex items-center gap-1">
      <i data-lucide="tv-2" class="h-4 w-4"></i> Modo TV
    </a>
  </div>
</div>

<?php flash(); ?>

<!-- Navegação do mês -->
<div class="card p-4 mb-6 flex flex-wrap items-center justify-between gap-3">
  <div class="flex items-center gap-2">
    <a class="btn btn-muted"
       href="index.php?month=<?=h($prevMonth)?><?= $post_id!=='' ? '&post_id='.h($post_id) : '' ?>">
      <i data-lucide="chevron-left" class="h-4 w-4"></i> <?=h($prevMonth)?>
    </a>

    <div class="text-sm text-gray-300">
      Período:
      <span class="font-semibold text-gray-100"><?=date('d/m/Y', strtotime($firstDay))?></span> até
      <span class="font-semibold text-gray-100"><?=date('d/m/Y', strtotime($lastDay))?></span>
    </div>

    <a class="btn btn-muted"
       href="index.php?month=<?=h($nextMonth)?><?= $post_id!=='' ? '&post_id='.h($post_id) : '' ?>">
      <?=h($nextMonth)?> <i data-lucide="chevron-right" class="h-4 w-4"></i>
    </a>
  </div>

  <div class="flex flex-wrap items-center gap-2 text-xs">
    <span class="badge blue">Alocações (mês): <?= (int)$totalAssignmentsMonth ?></span>
    <span class="badge orange">SDR (mês): <?= (int)$sdrMonthTotal ?></span>
  </div>
</div>

<!-- Indicadores -->
<div class="grid md:grid-cols-5 gap-4 mb-6">
  <div class="card-resume">
    <div class="text-xs text-gray-400 uppercase tracking-[0.2em] mb-1">Escalas</div>
    <div class="text-3xl font-bold"><?= (int)$totalRows ?></div>
    <div class="text-sm text-gray-400">Registros no mês</div>
  </div>

  <div class="card-resume">
    <div class="text-xs text-gray-400 uppercase tracking-[0.2em] mb-1">Publicadas</div>
    <div class="text-3xl font-bold text-emerald-300"><?= (int)$published ?></div>
    <div class="text-sm text-gray-400">Visíveis no modo TV</div>
  </div>

  <div class="card-resume">
    <div class="text-xs text-gray-400 uppercase tracking-[0.2em] mb-1">Rascunhos</div>
    <div class="text-3xl font-bold text-orange-300"><?= (int)$drafts ?></div>
    <div class="text-sm text-gray-400">Em edição</div>
  </div>

  <div class="card-resume">
    <div class="text-xs text-gray-400 uppercase tracking-[0.2em] mb-1">Atrasadas</div>
    <div class="text-3xl font-bold text-red-300"><?= (int)$late ?></div>
    <div class="text-sm text-gray-400">Dias passados sem publicar</div>
  </div>

  <div class="card-resume">
    <div class="text-xs text-gray-400 uppercase tracking-[0.2em] mb-1">SDR</div>
    <div class="text-3xl font-bold text-orange-300"><?= (int)$sdrMonthTotal ?></div>
    <div class="text-sm text-gray-400">Solicitações no mês</div>
  </div>
</div>

<!-- Filtros -->
<div class="card p-5 mb-6">
  <form method="get" class="grid md:grid-cols-3 gap-4 items-end">
    <div>
      <label class="text-sm">Mês</label>
      <input name="month" type="month" class="input" value="<?= h($month) ?>">
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

    <div class="flex gap-3">
      <button class="btn px-6">Filtrar</button>
      <a href="index.php" class="btn btn-muted px-6">Limpar</a>
    </div>
  </form>
</div>

<!-- Calendário + Painéis -->
<div class="grid xl:grid-cols-3 gap-6 mb-6">

  <!-- Calendário -->
  <div class="calendar-container xl:col-span-2">
    <div class="flex items-center justify-between mb-3">
      <h2 class="text-lg font-semibold flex items-center gap-2">
        <i data-lucide="calendar-days" class="h-5 w-5 text-orange-300"></i>
        Calendário do mês
      </h2>
      <div class="text-xs text-gray-400">Clique no dia para listar/editar as escalas.</div>
    </div>

    <div class="calendar-grid">
      <?php for ($i = 0; $i < $firstCalendarDay; $i++): ?>
        <div class="calendar-day empty"></div>
      <?php endfor; ?>

      <?php for ($day = 1; $day <= $daysInMonth; $day++):
        $date = $month . '-' . str_pad($day, 2, '0', STR_PAD_LEFT);
        $rows = $lookup[$date] ?? [];

        if (!$rows) $status = 'none';
        else {
          $anyLate=false; $anyDraft=false;
          foreach($rows as $r){
            if((int)$r['published']===0 && $r['ref_date'] < $today) $anyLate=true;
            if((int)$r['published']===0 && $r['ref_date'] >= $today) $anyDraft=true;
          }
          if($anyLate) $status='late';
          else if($anyDraft) $status='draft';
          else $status='published';
        }

        $isToday = ($date === $today);
        $extra   = $isToday ? ' ring-2 ring-orange-500 ring-offset-2 ring-offset-slate-900 font-semibold' : '';

        $assignDay = 0;
        foreach($rows as $r){
          $assignDay += $assignByDayId[(int)$r['id']] ?? 0;
        }
        $sdrDay = $sdrByDate[$date] ?? 0;

        $postsLabel = '';
        if($rows){
          $names = array_map(fn($x)=> (string)$x['post'], $rows);
          $names = array_values(array_unique($names));
          $postsLabel = implode(', ', array_slice($names, 0, 2));
          if(count($names) > 2) $postsLabel .= '…';
        }
      ?>
        <div class="calendar-day calday status-<?=$status?><?=$extra?>"
             data-date="<?=$date?>"
             data-posts="<?=h($postsLabel ?: 'Sem posto')?>"
             onclick="openDayPicker(this)">
          <div class="text-base leading-none"><?=$day?></div>

          <div class="day-micro">
            <span class="day-chip" title="Alocações do dia">
              <i data-lucide="users" class="w-3.5 h-3.5"></i>
              <span><?= (int)$assignDay ?></span>
            </span>
            <span class="day-chip" title="SDR do dia">
              <i data-lucide="alarm-clock" class="w-3.5 h-3.5"></i>
              <span><?= (int)$sdrDay ?></span>
            </span>
          </div>
        </div>
      <?php endfor; ?>
    </div>

    <div class="mt-3 text-xs text-gray-400 flex flex-wrap gap-3">
      <span><span class="inline-block w-3 h-3 rounded bg-emerald-600/60 mr-1"></span>Publicado</span>
      <span><span class="inline-block w-3 h-3 rounded bg-orange-600/60 mr-1"></span>Rascunho</span>
      <span><span class="inline-block w-3 h-3 rounded bg-red-600/60 mr-1"></span>Atrasado</span>
      <span><span class="inline-block w-3 h-3 rounded bg-gray-700 mr-1"></span>Sem escala</span>
    </div>
  </div>

  <!-- Painel lateral -->
  <div class="flex flex-col gap-4">

    <!-- Plantão de hoje -->
    <div class="card p-5">
      <div class="flex items-center justify-between mb-2">
        <h2 class="text-lg font-semibold flex items-center gap-2">
          <i data-lucide="shield" class="h-5 w-5 text-emerald-400"></i>
          Plantão de hoje
        </h2>
        <span class="text-xs text-gray-400"><?=date('d/m/Y', strtotime($today));?></span>
      </div>

      <?php if(!$todaySummary): ?>
        <p class="text-sm text-gray-500">Nenhuma escala registrada para o dia de hoje.</p>
      <?php else: ?>
        <ul class="space-y-2 text-sm">
          <?php foreach($todaySummary as $t): ?>
          <li class="flex items-center justify-between border-b border-slate-800/70 pb-1 last:border-0 last:pb-0">
            <div>
              <div class="font-medium text-gray-100"><?=h($t['post'])?></div>
              <div class="text-[11px] text-gray-500"><?= $t['published'] ? 'Publicado' : 'Rascunho' ?></div>
            </div>
            <div class="text-xs text-gray-300"><?= (int)$t['total_agents'] ?> agente(s)</div>
          </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </div>

    <!-- SDR do mês -->
    <div class="card p-5">
      <h2 class="text-lg font-semibold mb-3 flex items-center gap-2">
        <i data-lucide="alarm-clock" class="h-5 w-5 text-orange-300"></i>
        SDR (mês)
      </h2>

      <div class="flex items-center justify-between">
        <div class="text-sm text-gray-300">Total</div>
        <div class="text-xl font-bold text-orange-300"><?= (int)$sdrMonthTotal ?></div>
      </div>

      <?php if($sdrByStatus): ?>
        <div class="mt-3 space-y-2 text-sm">
          <?php foreach($sdrByStatus as $stt => $cnt): ?>
            <div class="flex items-center justify-between border-b border-slate-800/70 pb-1 last:border-0 last:pb-0">
              <div class="text-gray-300"><?=h($stt)?></div>
              <div class="text-gray-100 font-semibold"><?= (int)$cnt ?></div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php else: ?>
        <div class="mt-2 text-xs text-gray-500">Nenhum SDR no período.</div>
      <?php endif; ?>

      <div class="mt-3">
        <a class="text-orange-300 hover:underline text-sm" href="/modules/overtime/?month=<?=h($month)?>">
          Abrir módulo SDR
        </a>
      </div>
    </div>

    <!-- Frequência de postos -->
    <div class="card p-5">
      <h2 class="text-lg font-semibold mb-3 flex items-center gap-2">
        <i data-lucide="bar-chart-3" class="h-5 w-5 text-orange-300"></i>
        Frequência de Postos (mês)
      </h2>

      <?php if(!$postCount): ?>
        <div class="text-gray-500 text-sm">Nenhuma escala registrada neste mês.</div>
      <?php else: ?>
        <?php $max = max($postCount); ?>
        <?php foreach($postCount as $postName => $count): ?>
          <div class="mb-3">
            <div class="flex justify-between text-xs text-gray-300 mb-1">
              <span><?=h($postName)?></span>
              <span><?=$count?> escala(s)</span>
            </div>
            <div class="w-full bg-gray-800 h-3 rounded-md overflow-hidden">
              <div class="heatmap-bar" style="width: <?= max(8, ($count / $max) * 100) ?>%;"></div>
            </div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>

    <!-- Agrupamentos -->
    <div class="card p-5">
      <h2 class="text-lg font-semibold mb-3 flex items-center gap-2">
        <i data-lucide="layers" class="h-5 w-5 text-blue-300"></i>
        Agrupamentos (mês)
      </h2>

      <?php if(!$groupsInfo): ?>
        <div class="text-gray-500 text-sm">Nenhum agrupamento encontrado.</div>
      <?php else: ?>
        <div class="space-y-2 text-sm">
          <?php foreach($groupsInfo as $g): $gid=(int)$g['id']; ?>
            <div class="flex items-center justify-between border-b border-slate-800/70 pb-1 last:border-0 last:pb-0">
              <div>
                <div class="font-medium text-gray-100"><?=h($g['name'])?></div>
                <div class="text-[11px] text-gray-500">
                  <?= (int)($groupMembersCount[$gid] ?? 0) ?> membro(s) •
                  <?= (int)($groupPlannedDays[$gid] ?? 0) ?> dia(s) previstos no mês
                </div>
              </div>
              <a class="text-xs text-blue-300 hover:underline" href="/modules/groups/">abrir</a>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- Listagem detalhada -->
<?php if (!$days): ?>
  <div class="empty-box">
    <div class="text-xl font-semibold text-gray-200 mb-1">Nenhuma escala registrada</div>
    <p>Nenhuma escala foi encontrada para esse mês.</p>
    <a href="builder.php" class="text-orange-300 hover:underline font-medium">Gerar escala agora</a>
  </div>
<?php else: ?>
  <div class="table-card mb-6">
    <table class="table w-full">
      <thead>
        <tr>
          <th class="py-3 px-4">Data</th>
          <th class="py-3 px-4">Posto</th>
          <th class="py-3 px-4">Alocações</th>
          <th class="py-3 px-4">SDR (dia)</th>
          <th class="py-3 px-4">Status</th>
          <th class="py-3 px-4 text-right">Ações</th>
        </tr>
      </thead>

      <tbody>
        <?php foreach ($days as $d):
          $badgeClass = (int)$d['published'] ? 'green' : 'orange';
          if ((int)$d['published'] === 0 && $d['ref_date'] < $today) $badgeClass = 'red';

          $cntAssign = $assignByDayId[(int)$d['id']] ?? 0;
          $cntSdrDay = $sdrByDate[$d['ref_date']] ?? 0;
        ?>
        <tr data-dayid="<?=$d['id']?>" data-date="<?=h($d['ref_date'])?>" data-published="<?= (int)$d['published'] ?>">
          <td class="py-3 px-4"><?=h($d['ref_date'])?></td>
          <td class="py-3 px-4"><?=h($d['post'])?></td>
          <td class="py-3 px-4"><?= (int)$cntAssign ?></td>
          <td class="py-3 px-4"><?= (int)$cntSdrDay ?></td>
          <td class="py-3 px-4">
            <span class="badge <?=$badgeClass?>">
              <?= (int)$d['published'] ? 'Publicado' : ($badgeClass==='red'?'Atrasado':'Rascunho') ?>
            </span>
          </td>
          <td class="py-3 px-4">
            <div class="flex justify-end gap-3">

              <a href="view.php?day=<?=$d['id']?>"
                 class="flex items-center gap-1 text-blue-300 hover:text-blue-400 text-sm transition">
                <i data-lucide="eye" class="w-4 h-4"></i> Ver
              </a>

              <a href="builder.php?day=<?=$d['id']?>"
                 class="flex items-center gap-1 text-gray-300 hover:text-white text-sm transition">
                <i data-lucide="pencil" class="w-4 h-4"></i> Editar
              </a>

              <a href="copy_day.php?id=<?=$d['id']?>"
                 class="flex items-center gap-1 text-orange-300 hover:text-orange-400 text-sm transition">
                <i data-lucide="copy" class="w-4 h-4"></i> Duplicar
              </a>

              <?php if ((int)$d['published']): ?>
                <button onclick="publishDay(this, <?=$d['id']?>)"
                  class="flex items-center gap-1 text-emerald-300 hover:text-emerald-200 text-sm transition">
                  <i data-lucide="toggle-left" class="w-4 h-4"></i> Ocultar
                </button>
              <?php else: ?>
                <button onclick="publishDay(this, <?=$d['id']?>)"
                  class="flex items-center gap-1 text-yellow-300 hover:text-yellow-200 text-sm transition">
                  <i data-lucide="toggle-right" class="w-4 h-4"></i> Publicar
                </button>
              <?php endif; ?>

              <a href="delete.php?id=<?=$d['id']?>"
                 class="flex items-center gap-1 text-red-300 hover:text-red-200 text-sm transition"
                 onclick="return confirm('Deseja realmente excluir esta escala? Esta ação é irreversível.');">
                <i data-lucide="trash-2" class="w-4 h-4"></i> Excluir
              </a>

            </div>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php endif; ?>

<!-- Modal: lista escalas do dia -->
<div id="dayPicker" class="fixed inset-0 bg-black/60 hidden items-center justify-center z-50">
  <div class="bg-gray-900 border border-gray-700 rounded-2xl p-5 w-full max-w-lg shadow-2xl">
    <div class="flex justify-between items-center mb-3">
      <h2 class="text-lg font-semibold">Dia <span id="dp_date"></span></h2>
      <button onclick="closeDayPicker()" class="text-gray-400 hover:text-white">&times;</button>
    </div>

    <div class="text-sm text-gray-300 mb-3">
      Postos: <span id="dp_posts" class="font-medium text-orange-200"></span>
    </div>

    <div id="dp_list" class="space-y-2"></div>

    <div class="mt-4 grid grid-cols-2 gap-2">
      <button class="btn btn-muted" onclick="openBuilderForDate()">Criar/Editar no construtor</button>
      <button class="btn" onclick="closeDayPicker()">Fechar</button>
    </div>

    <div class="mt-3 text-[11px] text-gray-500 flex items-center gap-2">
      <i data-lucide="alarm-clock" class="w-3.5 h-3.5"></i>
      <span>SDR do dia:</span>
      <a class="text-orange-300 hover:underline" id="dp_sdr" href="#">abrir</a>
    </div>
  </div>
</div>

<script>
/* Deixa o lookup acessível pra atualizações dinâmicas */
window.monthLookup = <?= json_encode($lookup, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) ?>;

let currentDate = null;

function escapeHtml(s){
  return String(s ?? '')
    .replaceAll('&','&amp;')
    .replaceAll('<','&lt;')
    .replaceAll('>','&gt;')
    .replaceAll('"','&quot;')
    .replaceAll("'","&#039;");
}

function openDayPicker(el){
  const date = el.dataset.date;
  currentDate = date;

  document.getElementById('dp_date').textContent  = date;
  document.getElementById('dp_posts').textContent = el.dataset.posts || '—';

  // Ajuste se seu módulo SDR usa outro parâmetro
  document.getElementById('dp_sdr').href = "/modules/overtime/?date=" + encodeURIComponent(date);

  const list = document.getElementById('dp_list');
  list.innerHTML = '';

  const rows = (window.monthLookup && window.monthLookup[date]) ? window.monthLookup[date] : [];
  if (!rows.length) {
    list.innerHTML = `
      <div class="p-4 rounded-xl border border-gray-800 bg-gray-900/40 text-gray-300">
        Nenhuma escala registrada para este dia.
        <div class="mt-2">
          <a class="text-orange-300 hover:underline" href="builder.php?date=${encodeURIComponent(date)}">
            Criar agora
          </a>
        </div>
      </div>
    `;
  } else {
    rows.forEach(r => {
      const status = (parseInt(r.published,10)===1) ? 'Publicado' : 'Rascunho';
      const badge  = (parseInt(r.published,10)===1) ? 'badge green' : 'badge orange';

      list.insertAdjacentHTML('beforeend', `
        <div class="p-4 rounded-xl border border-gray-800 bg-gray-900/40 flex items-center justify-between gap-3">
          <div>
            <div class="font-semibold text-gray-100">${escapeHtml(r.post || '—')}</div>
            <div class="mt-1"><span class="${badge}">${status}</span></div>
          </div>
          <div class="flex items-center gap-2">
            <a class="btn btn-muted" href="view.php?day=${encodeURIComponent(r.id)}">Ver</a>
            <a class="btn" href="builder.php?day=${encodeURIComponent(r.id)}">Editar</a>
          </div>
        </div>
      `);
    });
  }

  document.getElementById('dayPicker').classList.remove('hidden');
  document.getElementById('dayPicker').classList.add('flex');

  if (window.lucide && typeof window.lucide.createIcons === 'function') {
    window.lucide.createIcons();
  }
}

function closeDayPicker(){
  document.getElementById('dayPicker').classList.add('hidden');
  document.getElementById('dayPicker').classList.remove('flex');
  currentDate = null;
}

function openBuilderForDate(){
  if(!currentDate) return;
  window.location = "builder.php?date=" + encodeURIComponent(currentDate);
}

/* Toast profissional (sem alert) */
function toast(msg, type='ok'){
  let box = document.getElementById('toastBox');
  if(!box){
    box = document.createElement('div');
    box.id = 'toastBox';
    box.className = 'fixed top-4 right-4 z-[9999] space-y-2';
    document.body.appendChild(box);
  }
  const el = document.createElement('div');
  el.className =
    'px-4 py-3 rounded-xl border shadow-xl text-sm backdrop-blur ' +
    (type==='ok'
      ? 'bg-emerald-900/40 border-emerald-700/40 text-emerald-100'
      : type==='warn'
      ? 'bg-orange-900/40 border-orange-700/40 text-orange-100'
      : 'bg-red-900/40 border-red-700/40 text-red-100'
    );
  el.textContent = msg;
  box.appendChild(el);
  setTimeout(()=>{ el.style.opacity='0'; el.style.transition='opacity .2s'; }, 2200);
  setTimeout(()=> el.remove(), 2600);
}

/* Recalcula classe do dia no calendário (sem reload) */
function recalcCalendarCell(date){
  const rows = (window.monthLookup && window.monthLookup[date]) ? window.monthLookup[date] : [];
  let status = 'none';
  if(rows.length){
    let anyLate=false, anyDraft=false;
    const today = <?= json_encode($today) ?>;
    for(const r of rows){
      const pub = parseInt(r.published,10)===1;
      if(!pub && r.ref_date < today) anyLate = true;
      else if(!pub) anyDraft = true;
    }
    status = anyLate ? 'late' : (anyDraft ? 'draft' : 'published');
  }

  const cell = document.querySelector(`.calday[data-date="${CSS.escape(date)}"]`);
  if(!cell) return;

  cell.classList.remove('status-none','status-draft','status-published','status-late');
  cell.classList.add('status-'+status);

  if (window.lucide && typeof window.lucide.createIcons === 'function') {
    window.lucide.createIcons();
  }
}

/* Publicar/Ocultar sem reload */
async function publishDay(btn, id){
  if (!confirm("Confirmar alteração de publicação?")) return;

  const tr = btn.closest('tr');
  const date = tr?.dataset?.date || null;
  const currentPublished = tr ? (parseInt(tr.dataset.published,10)===1) : null;

  btn.disabled = true;
  const oldHtml = btn.innerHTML;
  btn.innerHTML = '<span class="opacity-80">Processando…</span>';

  try{
    const r = await fetch('toggle_publish.php', {
      method: 'POST',
      headers: {'Content-Type': 'application/x-www-form-urlencoded'},
      body: "id=" + encodeURIComponent(id)
    });
    const js = await r.json();

    if(!js.ok){
      toast(js.msg || "Erro ao atualizar.", 'err');
      btn.innerHTML = oldHtml;
      btn.disabled = false;
      return;
    }

    const newPublished =
      (typeof js.published !== 'undefined') ? (parseInt(js.published,10)===1) : !currentPublished;

    if(tr) tr.dataset.published = newPublished ? '1' : '0';

    // badge
    const badge = tr?.querySelector('.badge');
    if(badge){
      badge.classList.remove('green','orange','red');
      if(newPublished){
        badge.classList.add('green');
        badge.textContent = 'Publicado';
      } else {
        const today = <?= json_encode($today) ?>;
        const isPast = date && date < today;
        badge.classList.add(isPast ? 'red' : 'orange');
        badge.textContent = isPast ? 'Atrasado' : 'Rascunho';
      }
    }

    // botão
    btn.disabled = false;
    btn.innerHTML = newPublished
      ? '<i data-lucide="toggle-left" class="w-4 h-4"></i> Ocultar'
      : '<i data-lucide="toggle-right" class="w-4 h-4"></i> Publicar';

    // atualiza monthLookup
    if(date && window.monthLookup && window.monthLookup[date]){
      const row = window.monthLookup[date].find(x => String(x.id) === String(id));
      if(row) row.published = newPublished ? 1 : 0;
      recalcCalendarCell(date);
    }

    toast(newPublished ? "Escala publicada." : "Escala ocultada.", 'ok');

    if (window.lucide && typeof window.lucide.createIcons === 'function') {
      window.lucide.createIcons();
    }

  }catch(e){
    toast("Erro de comunicação com o servidor.", 'err');
    btn.innerHTML = oldHtml;
    btn.disabled = false;
  }
}

/* gerar por agrupamentos */
document.getElementById('btnGenGroups')?.addEventListener('click', async () => {
  const month = <?= json_encode($month) ?>;
  if(!confirm("Gerar escalas do mês pelos Agrupamentos (regras fixas)?\n\nIsso cria o que estiver faltando e não apaga o que já existe.")) return;

  try{
    const r = await fetch('generate_from_groups.php', {
      method: 'POST',
      headers: {'Content-Type': 'application/x-www-form-urlencoded'},
      body: 'month=' + encodeURIComponent(month)
    });
    const js = await r.json();

    if(js.ok){
      toast(js.msg || "Mês gerado com sucesso.", 'ok');
      setTimeout(()=> location.reload(), 700);
    } else {
      toast(js.msg || "Falha ao gerar.", 'err');
    }
  }catch(e){
    toast("Erro de comunicação ao gerar mês.", 'err');
  }
});

document.addEventListener('keydown', e => {
  if (e.key === 'Escape') closeDayPicker();
});

if (window.lucide && typeof window.lucide.createIcons === 'function') {
  window.lucide.createIcons();
}
</script>

<?php require __DIR__.'/../../inc/footer.php'; ?>
