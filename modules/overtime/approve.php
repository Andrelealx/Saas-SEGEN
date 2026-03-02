<?php
require __DIR__.'/../../core/auth.php';
auth_require();

require __DIR__.'/../../core/db.php';
require __DIR__.'/../../core/utils.php';

/* ===== RBAC seguro (não quebra se ainda não existir) ===== */
$rbacFile = __DIR__ . '/../../core/rbac.php';
if (is_file($rbacFile)) require_once $rbacFile;
if (function_exists('rbac_require')) {
  rbac_require('overtime.approve'); // ajuste o nome da permissão como preferir
}

/* DEBUG
ini_set('display_errors',1); ini_set('display_startup_errors',1); error_reporting(E_ALL);
*/

if (session_status() !== PHP_SESSION_ACTIVE) session_start();

/* ===== CSRF simples ===== */
if (empty($_SESSION['_csrf'])) {
  $_SESSION['_csrf'] = bin2hex(random_bytes(16));
}
function csrf_check(): void {
  $t = $_POST['_csrf'] ?? '';
  if (!$t || !hash_equals($_SESSION['_csrf'] ?? '', $t)) {
    http_response_code(403);
    exit('CSRF inválido.');
  }
}

$id = (int)($_GET['id'] ?? 0);
if (!$id) { http_response_code(400); exit('ID inválido'); }

/* Mapa de status permitido */
$allowedStatus = ['APROVADO','NEGADO','LANÇADO','PENDENTE'];

/* Classes CSS seguras */
$statusClass = [
  'APROVADO' => 'status-APROVADO',
  'PENDENTE' => 'status-PENDENTE',
  'NEGADO'   => 'status-NEGADO',
  'LANÇADO'  => 'status-LANÇADO',
];

/* =============================
   Carrega SDR
   ============================= */
try {
  $st = $pdo->prepare("
    SELECT o.*, e.name emp
    FROM overtime_requests o
    LEFT JOIN employees e ON e.id=o.employee_id
    WHERE o.id=?
    LIMIT 1
  ");
  $st->execute([$id]);
  $r = $st->fetch(PDO::FETCH_ASSOC);

  if (!$r) {
    http_response_code(404);
    exit('SDR não encontrada');
  }
} catch (Throwable $e) {
  http_response_code(500);
  exit('Erro ao carregar SDR: '.$e->getMessage());
}

/* =============================
   Tenta buscar nome do aprovador (se existir tabela users)
   ============================= */
$approvedByName = null;
if (!empty($r['approved_by'])) {
  try {
    $u = $pdo->prepare("SELECT name FROM users WHERE id=? LIMIT 1");
    $u->execute([(int)$r['approved_by']]);
    $approvedByName = $u->fetchColumn() ?: null;
  } catch (Throwable $e) {
    // se a tabela/coluna não existir, não quebra
    $approvedByName = null;
  }
}

/* =============================
   Histórico do servidor
   ============================= */
$last = [];
try {
  $history = $pdo->prepare("
    SELECT ref_date, hours, status
    FROM overtime_requests
    WHERE employee_id=? AND id<>?
    ORDER BY ref_date DESC
    LIMIT 5
  ");
  $history->execute([(int)$r['employee_id'], $id]);
  $last = $history->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
  $last = [];
}

/* =============================
   PROCESSAR FORMULÁRIO
   ============================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

  csrf_check();

  $status = $_POST['status'] ?? 'PENDENTE';
  $obs    = trim((string)($_POST['obs'] ?? ''));

  if (!in_array($status, $allowedStatus, true)) {
    $status = 'PENDENTE';
  }

  // Justificativa obrigatória ao negar
  if ($status === 'NEGADO' && $obs === '') {
    set_flash("Para negar uma SDR é necessário informar uma justificativa.","error");
    header("Location: approve.php?id=".$id);
    exit;
  }

  try {
    // Se voltar para PENDENTE, opcionalmente limpa aprovação (pra não confundir histórico)
    if ($status === 'PENDENTE') {
      $pdo->prepare("
        UPDATE overtime_requests
        SET status=?, approved_by=NULL, approved_at=NULL, approval_note=?
        WHERE id=?
      ")->execute([$status, $obs ?: null, $id]);
    } else {
      $pdo->prepare("
        UPDATE overtime_requests
        SET status=?, approved_by=?, approved_at=NOW(), approval_note=?
        WHERE id=?
      ")->execute([
        $status,
        (int)($_SESSION['uid'] ?? 0),
        $obs ?: null,
        $id
      ]);
    }

    set_flash("SDR marcada como $status.");
    header("Location: /modules/overtime/");
    exit;

  } catch (Throwable $e) {
    set_flash("Erro ao atualizar: ".$e->getMessage(), "error");
    header("Location: approve.php?id=".$id);
    exit;
  }
}

require __DIR__.'/../../inc/header.php';

$stNow = (string)($r['status'] ?? 'PENDENTE');
$stCls = $statusClass[$stNow] ?? 'status-PENDENTE';
?>

<style>
.badge-status {
  padding: 4px 10px;
  border-radius: 8px;
  font-weight: 600;
  display:inline-block;
}
.status-APROVADO { background:#16a34a33; color:#4ade80; border:1px solid #16a34a; }
.status-PENDENTE { background:#f59e0b33; color:#fbbf24; border:1px solid #f59e0b; }
.status-NEGADO   { background:#dc262633; color:#f87171; border:1px solid #dc2626; }
.status-LANÇADO  { background:#0284c733; color:#7dd3fc; border:1px solid #0ea5e9; }
</style>

<div class="flex items-center justify-between mb-6">
  <h1 class="text-2xl font-bold">Análise de SDR</h1>
  <a href="index.php" class="btn flex items-center gap-2">
    <i data-lucide="arrow-left" class="h-4 w-4"></i> Voltar
  </a>
</div>

<?php flash(); ?>

<div class="card p-6">

  <div class="grid md:grid-cols-2 gap-4">

    <div>
      <span class="text-gray-400 text-sm">Servidor</span>
      <div class="text-lg font-semibold"><?=h($r['emp'] ?? '—')?></div>
    </div>

    <div>
      <span class="text-gray-400 text-sm">Status Atual</span>
      <div class="badge-status <?=$stCls?>"><?=h($stNow)?></div>
    </div>

    <div>
      <span class="text-gray-400 text-sm">Data</span>
      <div><?=h($r['ref_date'] ?? '—')?></div>
    </div>

    <div>
      <span class="text-gray-400 text-sm">Período</span>
      <div><?=h(substr((string)($r['start_time'] ?? ''),0,5))?> – <?=h(substr((string)($r['end_time'] ?? ''),0,5))?></div>
    </div>

    <div>
      <span class="text-gray-400 text-sm">Total de Horas</span>
      <div class="font-semibold text-lg"><?=h($r['hours'] ?? '—')?>h</div>
    </div>

    <div>
      <span class="text-gray-400 text-sm">Motivo</span>
      <div><?= h($r['reason'] ?? '') ?: '<i class="text-gray-600">Não informado</i>' ?></div>
    </div>

    <?php if (!empty($r['approved_by'])): ?>
      <div class="md:col-span-2 mt-3 p-3 bg-gray-800/40 rounded">
        <span class="text-gray-400 text-sm">Última aprovação</span>
        <div class="text-sm">
          <b>Por:</b> <?=h($approvedByName ?: ('ID '.$r['approved_by']))?> •
          <b>Em:</b> <?=h($r['approved_at'] ?? '—')?>
        </div>
        <?php if (!empty($r['approval_note'])): ?>
          <div class="text-xs text-gray-300 mt-2"><b>Obs:</b> <?=h($r['approval_note'])?></div>
        <?php endif; ?>
      </div>
    <?php endif; ?>

  </div>

  <?php if ($last): ?>
    <div class="mt-6">
      <h2 class="text-lg font-semibold mb-2">Últimas SDR do servidor</h2>
      <div class="p-3 bg-gray-800/40 rounded text-sm">
        <?php foreach($last as $hrow): 
          $hs = (string)($hrow['status'] ?? 'PENDENTE');
          $hcls = $statusClass[$hs] ?? 'status-PENDENTE';
        ?>
          <div class="border-b border-gray-700 py-1 flex justify-between">
            <div><?=h($hrow['ref_date'] ?? '—')?></div>
            <div><?=h($hrow['hours'] ?? '—')?>h</div>
            <div class="badge-status <?=$hcls?>"><?=h($hs)?></div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  <?php endif; ?>

  <form method="post" class="mt-6">
    <input type="hidden" name="_csrf" value="<?=h($_SESSION['_csrf'])?>">

    <label class="text-sm font-medium">Observação / Justificativa (opcional exceto em caso de negação)</label>
    <textarea name="obs" class="input min-h-[80px]" placeholder="Registrar comentário interno do RH..."></textarea>

    <div class="flex flex-wrap gap-3 mt-4">

      <button name="status" value="APROVADO"
        onclick="return confirm('Confirmar APROVAÇÃO da SDR?');"
        class="btn flex items-center gap-2">
        <i data-lucide="check-circle" class="h-4 w-4"></i> Aprovar
      </button>

      <button name="status" value="NEGADO"
        onclick="return confirm('Tem certeza que deseja NEGAR esta SDR?');"
        class="px-4 py-2 rounded-xl bg-red-600 hover:bg-red-700 text-white">
        Negar
      </button>

      <button name="status" value="LANÇADO"
        onclick="return confirm('Marcar SDR como lançada?');"
        class="px-4 py-2 rounded-xl bg-blue-600 hover:bg-blue-700 text-white">
        Marcar como lançado
      </button>

      <!-- Mantive seu delete via GET por compatibilidade com seu delete.php atual -->
      <a href="delete.php?id=<?=$id?>"
        onclick="return confirm('Tem certeza que deseja excluir esta SDR?');"
        class="px-4 py-2 rounded-xl bg-red-700 hover:bg-red-800 text-white">
        Excluir SDR
      </a>

    </div>
  </form>

</div>

<?php require __DIR__.'/../../inc/footer.php'; ?>
