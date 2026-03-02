<?php
require __DIR__.'/_oc_bootstrap.php';

$q = trim($_GET['q'] ?? '');
$sector = trim($_GET['sector'] ?? '');
$status = trim($_GET['status'] ?? '');
$from = trim($_GET['from'] ?? '');
$to = trim($_GET['to'] ?? '');

$where = [];
$params = [];

if ($sector !== '') { $where[] = "o.sector = ?"; $params[] = strtoupper($sector); }
if ($status !== '') { $where[] = "o.status = ?"; $params[] = $status; }
if ($from !== '') { $where[] = "o.occurred_at >= ?"; $params[] = $from." 00:00:00"; }
if ($to !== '')   { $where[] = "o.occurred_at <= ?"; $params[] = $to." 23:59:59"; }

if ($q !== '') {
  $where[] = "(o.protocol LIKE ? OR o.location LIKE ? OR o.nature LIKE ? OR o.description LIKE ? OR o.involved LIKE ?)";
  $like = "%$q%";
  array_push($params, $like,$like,$like,$like,$like);
}

$sql = "SELECT o.id,o.protocol,o.sector,o.occurred_at,o.status,o.location,o.nature
        FROM occurrences o";
if ($where) $sql .= " WHERE ".implode(" AND ", $where);
$sql .= " ORDER BY o.occurred_at DESC LIMIT 300";

$st = $pdo->prepare($sql);
$st->execute($params);
$rows = $st->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="page-wrap">
  <div class="flex items-center justify-between gap-3 flex-wrap mb-4">
    <div>
      <div class="text-xs uppercase tracking-widest text-gray-400">Módulo</div>
      <h1 class="text-xl font-semibold">Livro de Ocorrências</h1>
      <div class="text-sm text-gray-400">Registros digitais • anexos • auditoria</div>
    </div>
    <a href="create.php" class="btn">+ Nova ocorrência</a>
  </div>

  <form class="card card-muted p-4 mb-4 grid grid-cols-1 md:grid-cols-5 gap-3" method="get">
    <input name="q" value="<?=h($q)?>" class="input" placeholder="Buscar (protocolo, texto, local...)" />
    <input name="sector" value="<?=h($sector)?>" class="input" placeholder="Setor (ALPHA/BRAVO/...)" />
    <select name="status" class="input">
      <option value="">Status (todos)</option>
      <?php foreach(['draft'=>'Rascunho','registered'=>'Registrada','closed'=>'Encerrada','canceled'=>'Cancelada'] as $k=>$v): ?>
        <option value="<?=$k?>" <?=$status===$k?'selected':''?>><?=$v?></option>
      <?php endforeach; ?>
    </select>
    <input type="date" name="from" value="<?=h($from)?>" class="input" />
    <div class="flex gap-2">
      <input type="date" name="to" value="<?=h($to)?>" class="input w-full" />
      <button class="btn" type="submit">Filtrar</button>
    </div>
  </form>

  <div class="card card-muted overflow-x-auto">
    <table class="table w-full">
      <thead>
        <tr>
          <th>Protocolo</th>
          <th>Data/Hora</th>
          <th>Setor</th>
          <th>Natureza</th>
          <th>Local</th>
          <th>Status</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach($rows as $r): ?>
        <tr class="tr">
          <td class="font-mono"><?=h($r['protocol'])?></td>
          <td><?=h(date('d/m/Y H:i', strtotime($r['occurred_at'])))?></td>
          <td><?=h($r['sector'])?></td>
          <td><?=h($r['nature'])?></td>
          <td><?=h($r['location'])?></td>
          <td><?=occ_badge($r['status'])?></td>
          <td class="text-right">
            <a class="btn" href="view.php?id=<?=$r['id']?>">Abrir</a>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if(!$rows): ?>
          <tr><td class="p-6 text-gray-400" colspan="7">Nenhuma ocorrência encontrada.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require __DIR__.'/../../inc/footer.php'; ?>
