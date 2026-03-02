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
  rbac_require('overtime.delete'); // ajuste o nome da permissão como preferir
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

$id = (int)($_GET['id'] ?? ($_POST['id'] ?? 0));
if (!$id) {
  http_response_code(400);
  exit("ID inválido.");
}

/* ===== Carrega SDR (pra confirmar e evitar apagar algo inexistente) ===== */
try {
  $st = $pdo->prepare("
    SELECT o.id, o.ref_date, o.hours, o.status, e.name AS emp
    FROM overtime_requests o
    LEFT JOIN employees e ON e.id=o.employee_id
    WHERE o.id=?
    LIMIT 1
  ");
  $st->execute([$id]);
  $row = $st->fetch(PDO::FETCH_ASSOC);

  if (!$row) {
    http_response_code(404);
    exit("SDR não encontrada.");
  }
} catch (Throwable $e) {
  http_response_code(500);
  exit("Erro ao verificar SDR: ".$e->getMessage());
}

/* ==========================================================
   1) Se for POST: executa exclusão (com CSRF)
   ========================================================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

  csrf_check();

  try {
    $del = $pdo->prepare("DELETE FROM overtime_requests WHERE id=?");
    $del->execute([$id]);

    set_flash("SDR excluída com sucesso!");
    header("Location: /modules/overtime/");
    exit;

  } catch (Throwable $e) {
    set_flash("Erro ao excluir: ".$e->getMessage(), "error");
    header("Location: /modules/overtime/");
    exit;
  }
}

/* ==========================================================
   2) Se for GET: mostra tela de confirmação segura
   ========================================================== */
require __DIR__.'/../../inc/header.php';
?>

<div class="flex items-center justify-between mb-6">
  <h1 class="text-2xl font-semibold">Excluir SDR</h1>
  <a href="/modules/overtime/" class="btn"><i data-lucide="arrow-left" class="h-4 w-4"></i> Voltar</a>
</div>

<div class="card p-6">
  <div class="text-sm text-gray-300 mb-4">
    Você está prestes a excluir a SDR abaixo. Esta ação <b class="text-red-300">não pode ser desfeita</b>.
  </div>

  <div class="grid md:grid-cols-2 gap-3 text-sm">
    <div><span class="text-gray-400">Servidor:</span> <b><?=h($row['emp'] ?? '—')?></b></div>
    <div><span class="text-gray-400">Data:</span> <b><?=h($row['ref_date'] ?? '—')?></b></div>
    <div><span class="text-gray-400">Horas:</span> <b><?=h($row['hours'] ?? '—')?>h</b></div>
    <div><span class="text-gray-400">Status:</span> <b><?=h($row['status'] ?? '—')?></b></div>
  </div>

  <form method="post" class="mt-6 flex gap-3">
    <input type="hidden" name="_csrf" value="<?=h($_SESSION['_csrf'])?>">
    <input type="hidden" name="id" value="<?=$id?>">

    <a href="/modules/overtime/" class="btn btn-muted">Cancelar</a>

    <button class="px-4 py-2 rounded-xl bg-red-700 hover:bg-red-800 text-white"
            onclick="return confirm('Confirmar exclusão definitiva?');">
      Excluir definitivamente
    </button>
  </form>
</div>

<?php require __DIR__.'/../../inc/footer.php'; ?>
