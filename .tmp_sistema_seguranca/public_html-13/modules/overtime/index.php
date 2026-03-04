<?php
require __DIR__.'/../../core/auth.php'; auth_require();
require __DIR__.'/../../core/db.php';
require __DIR__.'/../../core/utils.php';

/* (opcional) RBAC seguro — só ativa se você já tiver rbac.php
$rbacFile = __DIR__ . '/../../core/rbac.php';
if (is_file($rbacFile)) require_once $rbacFile;
if (function_exists('rbac_require')) rbac_require('overtime.view');
*/

/* DEBUG
ini_set('display_errors',1); ini_set('display_startup_errors',1); error_reporting(E_ALL);
*/

// =============================================================
// FILTROS
// =============================================================
$employee_id = $_GET['employee_id'] ?? '';
$status      = $_GET['status'] ?? '';
$ini         = $_GET['ini'] ?? date('Y-m-01');
$fim         = $_GET['fim'] ?? date('Y-m-t');

$where = [];
$params = [];

if ($employee_id !== '') {
    $where[] = "o.employee_id = ?";
    $params[] = (int)$employee_id;
}
if ($status !== '') {
    $where[] = "o.status = ?";
    $params[] = $status;
}
if ($ini !== '') {
    $where[] = "o.ref_date >= ?";
    $params[] = $ini;
}
if ($fim !== '') {
    $where[] = "o.ref_date <= ?";
    $params[] = $fim;
}

$sqlWhere = $where ? "WHERE ".implode(" AND ", $where) : "";

// =============================================================
// CONSULTA DE SDR
// =============================================================
try {
    $sql = "
        SELECT o.*, e.name emp
        FROM overtime_requests o
        LEFT JOIN employees e ON e.id = o.employee_id
        $sqlWhere
        ORDER BY o.ref_date DESC
        LIMIT 300
    ";
    $st = $pdo->prepare($sql);
    $st->execute($params);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $rows = [];
    $_SESSION['flash'] = "Erro ao carregar SDR: " . $e->getMessage();
}

// Para filtros
try {
  $emps = $pdo->query("SELECT id,name FROM employees WHERE status='ATIVO' ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
  $emps = [];
}

// =============================================================
// DADOS PRO CALENDÁRIO (gerado com json_encode: mais seguro)
// =============================================================
$events = [];
foreach ($rows as $r) {
  $hoursLabel = ((string)$r['hours']) . "h";

  $fullText = sprintf(
    "%s<br>Horas: %s<br>Status: %s<br>Início: %s — Fim: %s",
    (string)($r['emp'] ?? '—'),
    $hoursLabel,
    (string)($r['status'] ?? '—'),
    substr((string)($r['start_time'] ?? ''),0,5),
    substr((string)($r['end_time'] ?? ''),0,5)
  );

  $colorMap = [
    'APROVADO' => '#22c55e',
    'PENDENTE' => '#f97316',
    'NEGADO'   => '#ef4444',
    'LANÇADO'  => '#0ea5e9',
  ];

  $events[] = [
    'title'    => (string)($r['emp'] ?? '—') . " ($hoursLabel)",
    'start'    => (string)$r['ref_date'],
    'url'      => "approve.php?id=".(int)$r['id'],
    'fullText' => $fullText,
    'color'    => $colorMap[(string)$r['status']] ?? '#7dd3fc',
  ];
}

require __DIR__.'/../../inc/header.php';
?>

<style>
.fc-event:hover { opacity: 0.9; cursor: pointer; }
#fc-tooltip {
    position: absolute;
    padding: 6px 10px;
    background: rgba(0,0,0,0.85);
    color: #fff;
    font-size: 13px;
    border-radius: 6px;
    pointer-events: none;
    z-index: 99999;
}
</style>

<div class="flex items-center justify-between mb-6">
    <h1 class="text-2xl font-semibold">SDR • Horas Extras</h1>
    <a href="request.php" class="btn"><i data-lucide="plus" class="h-4 w-4"></i> Nova Solicitação</a>
</div>

<?php flash(); ?>

<!-- ================================
     FILTROS AVANÇADOS
================================ -->
<div class="card p-5 mb-6">
    <form method="get" class="grid md:grid-cols-4 gap-4 items-end">

        <div>
            <label class="text-sm">Servidor</label>
            <select name="employee_id" class="select">
                <option value="">Todos</option>
                <?php foreach($emps as $e): ?>
                <option value="<?=$e['id']?>" <?=$employee_id==$e['id']?'selected':''?>>
                    <?=h($e['name'])?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div>
            <label class="text-sm">Status</label>
            <select name="status" class="select">
                <option value="">Todos</option>
                <option value="APROVADO" <?=$status=='APROVADO'?'selected':''?>>Aprovado</option>
                <option value="PENDENTE" <?=$status=='PENDENTE'?'selected':''?>>Pendente</option>
                <option value="NEGADO" <?=$status=='NEGADO'?'selected':''?>>Negado</option>
                <option value="LANÇADO" <?=$status=='LANÇADO'?'selected':''?>>Lançado</option>
            </select>
        </div>

        <div>
            <label class="text-sm">De</label>
            <input type="date" name="ini" class="input" value="<?=h($ini)?>">
        </div>

        <div>
            <label class="text-sm">Até</label>
            <input type="date" name="fim" class="input" value="<?=h($fim)?>">
        </div>

        <div class="md:col-span-4 flex gap-3 mt-2">
            <button class="btn px-6">Filtrar</button>
            <a href="index.php" class="btn btn-muted px-6">Limpar</a>
        </div>
    </form>
</div>

<!-- ===============================
     CARDS RESUMO
================================ -->
<?php
$ap  = count(array_filter($rows, fn($r)=>($r['status'] ?? '')=='APROVADO'));
$pd  = count(array_filter($rows, fn($r)=>($r['status'] ?? '')=='PENDENTE'));
$ng  = count(array_filter($rows, fn($r)=>($r['status'] ?? '')=='NEGADO'));
$lc  = count(array_filter($rows, fn($r)=>($r['status'] ?? '')=='LANÇADO'));
$tot = count($rows);
?>

<div class="grid grid-cols-5 gap-4 mb-6">

    <div class="card p-4 text-center">
        <div class="text-3xl font-bold"><?=$tot?></div>
        <div class="text-sm text-gray-400">Total</div>
    </div>

    <div class="card p-4 text-center">
        <div class="text-3xl font-bold text-green-400"><?=$ap?></div>
        <div class="text-sm text-gray-400">Aprovados</div>
    </div>

    <div class="card p-4 text-center">
        <div class="text-3xl font-bold text-orange-400"><?=$pd?></div>
        <div class="text-sm text-gray-400">Pendentes</div>
    </div>

    <div class="card p-4 text-center">
        <div class="text-3xl font-bold text-red-400"><?=$ng?></div>
        <div class="text-sm text-gray-400">Negados</div>
    </div>

    <div class="card p-4 text-center">
        <div class="text-3xl font-bold text-blue-400"><?=$lc?></div>
        <div class="text-sm text-gray-400">Lançado</div>
    </div>

</div>

<!-- ===============================
     CALENDÁRIO PRO SDR
================================ -->
<link href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/index.global.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/index.global.min.js"></script>

<div id="calendar" class="card p-3 mb-6"></div>

<script>
document.addEventListener("DOMContentLoaded", () => {
  const events = <?= json_encode($events, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) ?>;

  const calEl = document.getElementById("calendar");
  if (!calEl) return;

  const calendar = new FullCalendar.Calendar(calEl, {
    initialView: "dayGridMonth",
    locale: "pt-br",
    height: "auto",
    eventDisplay: "auto",
    eventTextColor: "#fff",
    events,

    eventMouseEnter(info) {
      const old = document.getElementById("fc-tooltip");
      if (old) old.remove();

      const tooltip = document.createElement("div");
      tooltip.id = "fc-tooltip";
      tooltip.innerHTML = (info.event.extendedProps && info.event.extendedProps.fullText) ? info.event.extendedProps.fullText : '';
      document.body.appendChild(tooltip);

      const moveTooltip = (e) => {
        const t = document.getElementById("fc-tooltip");
        if (!t) return;
        t.style.top  = (e.pageY + 14) + "px";
        t.style.left = (e.pageX + 14) + "px";
      };

      tooltip._moveHandler = moveTooltip;
      document.addEventListener("mousemove", moveTooltip);
    },

    eventMouseLeave() {
      const tooltip = document.getElementById("fc-tooltip");
      if (tooltip) {
        if (tooltip._moveHandler) document.removeEventListener("mousemove", tooltip._moveHandler);
        tooltip.remove();
      }
    }
  });

  calendar.render();
});
</script>

<!-- ===============================
     LISTA PROFISSIONAL
================================ -->
<?php if (!$rows): ?>
  <div class="card text-gray-400">Nenhuma SDR encontrada para esses filtros.</div>
<?php else: ?>

<div class="card overflow-x-auto p-3">
    <table class="table">
        <thead>
            <tr>
                <th>Servidor</th>
                <th>Data</th>
                <th>Início</th>
                <th>Fim</th>
                <th>Horas</th>
                <th>Status</th>
                <th class="text-right">Ações</th>
            </tr>
        </thead>
        <tbody>

        <?php foreach($rows as $r): ?>
        <tr class="tr">
            <td><?=h($r['emp'] ?? '—')?></td>
            <td><?=h($r['ref_date'] ?? '—')?></td>
            <td><?=h(substr((string)($r['start_time'] ?? ''),0,5))?></td>
            <td><?=h(substr((string)($r['end_time'] ?? ''),0,5))?></td>
            <td><?=h($r['hours'] ?? '—')?></td>

            <td>
                <?php
                  $color = [
                      'APROVADO'=>'green',
                      'PENDENTE'=>'orange',
                      'NEGADO'=>'red',
                      'LANÇADO'=>'blue'
                  ][(string)($r['status'] ?? '')] ?? 'gray';
                ?>
                <span class="badge <?=$color?>"><?=h($r['status'] ?? '—')?></span>
            </td>

            <td class="text-right flex justify-end gap-3">
              <a class="text-blue-300 hover:underline text-sm" href="request.php?id=<?=(int)$r['id']?>">Editar</a>
              <a class="text-orange-300 hover:underline text-sm" href="approve.php?id=<?=(int)$r['id']?>">Aprovar</a>
              <a class="text-gray-400 hover:text-blue-300 text-sm" href="duplicate.php?id=<?=(int)$r['id']?>">Duplicar</a>
              <a class="text-red-400 hover:text-red-600 text-sm"
                 href="delete.php?id=<?=(int)$r['id']?>"
                 onclick="return confirm('Tem certeza que deseja EXCLUIR esta SDR? Esta ação não pode ser desfeita.');">
                 Excluir
              </a>
            </td>
        </tr>
        <?php endforeach; ?>

        </tbody>
    </table>
</div>

<?php endif; ?>

<?php require __DIR__.'/../../inc/footer.php'; ?>
