<?php
require __DIR__.'/../../core/auth.php';
auth_require();
require __DIR__.'/../../core/db.php';
require __DIR__.'/../../core/utils.php';

if (session_status() !== PHP_SESSION_ACTIVE) session_start();

/* ===== RBAC seguro (não quebra se ainda não existir) ===== */
$rbacFile = __DIR__ . '/../../core/rbac.php';
if (is_file($rbacFile)) require_once $rbacFile;
if (function_exists('rbac_require')) {
  rbac_require('overtime.create'); // ou overtime.duplicate, se você criar essa permissão
}

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

/* ===== Helper: calcula end_time pelo turno 12/24 ===== */
function compute_end_time(string $refDate, string $startTime, int $hours): string {
  $tz = new DateTimeZone('America/Sao_Paulo');
  $start = DateTimeImmutable::createFromFormat('Y-m-d H:i', $refDate.' '.$startTime, $tz);
  if (!$start) return '';
  $hours = ($hours === 24) ? 24 : 12; // trava
  $end = $start->add(new DateInterval('PT'.$hours.'H'));
  return $end->format('H:i');
}

$id = (int)($_GET['id'] ?? ($_POST['id'] ?? 0));
if (!$id) {
  http_response_code(400);
  exit('ID inválido');
}

/* =============================
   Carregar SDR original
   ============================= */
try {
  $st = $pdo->prepare("
    SELECT o.*, e.name emp
    FROM overtime_requests o
    LEFT JOIN employees e ON e.id = o.employee_id
    WHERE o.id=?
    LIMIT 1
  ");
  $st->execute([$id]);
  $r = $st->fetch(PDO::FETCH_ASSOC);

  if (!$r) {
    http_response_code(404);
    exit('SDR não encontrada.');
  }
} catch (Throwable $e) {
  http_response_code(500);
  exit("Erro ao carregar SDR: ".$e->getMessage());
}

/* garante regra nova (12/24) */
$hours = (int)($r['hours'] ?? 12);
if (!in_array($hours, [12,24], true)) $hours = 12;

/* =============================
   POST: duplicar
   ============================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

  csrf_check();

  $newDate = $_POST['new_date'] ?? '';
  if (!$newDate) $newDate = (string)($r['ref_date'] ?? date('Y-m-d'));

  // valida data básica YYYY-MM-DD
  if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $newDate)) {
    set_flash("Data inválida.", "error");
    header("Location: duplicate.php?id=".$id);
    exit;
  }

  $start_time = substr((string)($r['start_time'] ?? ''), 0, 5);
  if (!$start_time) {
    set_flash("SDR original sem horário de início válido.", "error");
    header("Location: /modules/overtime/");
    exit;
  }

  $end_time = compute_end_time($newDate, $start_time, $hours);
  if ($end_time === '') {
    set_flash("Não foi possível calcular o horário de fim da SDR duplicada.", "error");
    header("Location: duplicate.php?id=".$id);
    exit;
  }

  try {
    $sql = "
      INSERT INTO overtime_requests
        (employee_id, ref_date, start_time, end_time, hours, reason, requested_by, status)
      VALUES
        (?, ?, ?, ?, ?, ?, ?, ?)
    ";
    $pdo->prepare($sql)->execute([
      (int)$r['employee_id'],
      $newDate,
      $start_time,
      $end_time,
      $hours,
      $r['reason'] ?? null,
      (int)($_SESSION['uid'] ?? 0),
      'PENDENTE'
    ]);

    $newId = (int)$pdo->lastInsertId();

    set_flash("SDR duplicada com sucesso! (#$newId) Você pode editar para ajustar o início se quiser.");
    header("Location: /modules/overtime/");
    exit;

  } catch (Throwable $e) {
    set_flash("Erro ao duplicar: ".$e->getMessage(), "error");
    header("Location: /modules/overtime/");
    exit;
  }
}

/* =============================
   GET: tela de confirmação
   ============================= */
require __DIR__.'/../../inc/header.php';

$defaultDate = date('Y-m-d'); // melhor UX: duplicar para hoje por padrão
?>

<div class="flex items-center justify-between mb-6">
  <h1 class="text-2xl font-semibold">Duplicar SDR</h1>
  <a href="/modules/overtime/" class="btn flex items-center gap-2">
    <i data-lucide="arrow-left" class="h-4 w-4"></i> Voltar
  </a>
</div>

<div class="card p-6">

  <div class="text-sm text-gray-300 mb-4">
    Você vai duplicar a SDR abaixo. O turno será mantido em <b><?=h($hours)?>h</b> e o horário de fim será recalculado automaticamente.
  </div>

  <div class="grid md:grid-cols-2 gap-3 text-sm">
    <div><span class="text-gray-400">Servidor:</span> <b><?=h($r['emp'] ?? '—')?></b></div>
    <div><span class="text-gray-400">Data original:</span> <b><?=h($r['ref_date'] ?? '—')?></b></div>
    <div><span class="text-gray-400">Início:</span> <b><?=h(substr((string)($r['start_time'] ?? ''),0,5))?></b></div>
    <div><span class="text-gray-400">Fim:</span> <b><?=h(substr((string)($r['end_time'] ?? ''),0,5))?></b></div>
    <div class="md:col-span-2"><span class="text-gray-400">Motivo:</span> <b><?=h($r['reason'] ?? '—')?></b></div>
  </div>

  <form method="post" class="mt-6 grid md:grid-cols-3 gap-3 items-end">
    <input type="hidden" name="_csrf" value="<?=h($_SESSION['_csrf'])?>">
    <input type="hidden" name="id" value="<?=$id?>">

    <div>
      <label class="text-sm">Duplicar para a data</label>
      <input type="date" name="new_date" class="input" value="<?=h($defaultDate)?>" required>
      <div class="text-xs text-gray-400 mt-1">Dica: escolha “hoje” ou o próximo dia de serviço.</div>
    </div>

    <div class="md:col-span-2 flex gap-3">
      <a href="/modules/overtime/" class="btn btn-muted">Cancelar</a>
      <button class="btn px-5"
              onclick="return confirm('Confirmar duplicação?');">
        Duplicar SDR
      </button>
    </div>
  </form>

</div>

<?php require __DIR__.'/../../inc/footer.php'; ?>
