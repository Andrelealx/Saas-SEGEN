<?php
require __DIR__ . '/../../core/auth.php';
auth_require();
require __DIR__ . '/../../core/db.php';
require __DIR__ . '/../../core/utils.php';

if (session_status() !== PHP_SESSION_ACTIVE) session_start();

/* ===== RBAC seguro ===== */
$rbacFile = __DIR__ . '/../../core/rbac.php';
if (is_file($rbacFile)) require_once $rbacFile;

// Se existir rbac, restringe. Se não existir ainda, só deixa logado entrar.
if (function_exists('rbac_require')) {
  rbac_require('access.manage'); // você vai mapear isso pro SUPERADMIN
}

/* ===== DEBUG (se ficar branco, ative isso) =====
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
*/

function dbname(PDO $pdo): string {
  try { return (string)$pdo->query("SELECT DATABASE()")->fetchColumn(); }
  catch(Throwable $e){ return ''; }
}

function column_exists(PDO $pdo, string $table, string $col): bool {
  $db = dbname($pdo);
  if ($db === '') return false;
  $st = $pdo->prepare("
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA=? AND TABLE_NAME=? AND COLUMN_NAME=?
  ");
  $st->execute([$db, $table, $col]);
  return (int)$st->fetchColumn() > 0;
}

/* ===== CSRF ===== */
if (empty($_SESSION['_csrf'])) $_SESSION['_csrf'] = bin2hex(random_bytes(16));
function csrf_check(): void {
  $t = $_POST['_csrf'] ?? '';
  if (!$t || !hash_equals($_SESSION['_csrf'] ?? '', $t)) {
    http_response_code(403);
    exit('CSRF inválido.');
  }
}

$errors = [];
$hasRoleDesc = column_exists($pdo, 'roles', 'description');
$hasPermDesc = column_exists($pdo, 'permissions', 'description');

/* ===== Actions ===== */
$action = $_POST['action'] ?? '';
$role_id = (int)($_GET['role_id'] ?? ($_POST['role_id'] ?? 0));

try {

  // CREATE / UPDATE ROLE
  if ($action === 'save_role') {
    csrf_check();

    $rid  = (int)($_POST['role_id'] ?? 0);
    $name = strtoupper(trim((string)($_POST['name'] ?? '')));
    $desc = trim((string)($_POST['description'] ?? ''));

    if ($name === '') throw new Exception("Nome da role é obrigatório.");

    if ($rid > 0) {
      if ($hasRoleDesc) {
        $st = $pdo->prepare("UPDATE roles SET name=?, description=? WHERE id=?");
        $st->execute([$name, ($desc !== '' ? $desc : null), $rid]);
      } else {
        $st = $pdo->prepare("UPDATE roles SET name=? WHERE id=?");
        $st->execute([$name, $rid]);
      }
      set_flash("Role atualizada!");
      header("Location: roles.php?role_id=".$rid);
      exit;
    } else {
      if ($hasRoleDesc) {
        $st = $pdo->prepare("INSERT INTO roles (name, description) VALUES (?,?)");
        $st->execute([$name, ($desc !== '' ? $desc : null)]);
      } else {
        $st = $pdo->prepare("INSERT INTO roles (name) VALUES (?)");
        $st->execute([$name]);
      }
      $newId = (int)$pdo->lastInsertId();
      set_flash("Role criada!");
      header("Location: roles.php?role_id=".$newId);
      exit;
    }
  }

  // DELETE ROLE
  if ($action === 'delete_role') {
    csrf_check();
    $rid = (int)($_POST['role_id'] ?? 0);
    if ($rid <= 0) throw new Exception("Role inválida.");

    // Evita apagar SUPERADMIN
    $nm = $pdo->prepare("SELECT name FROM roles WHERE id=?");
    $nm->execute([$rid]);
    $roleName = (string)$nm->fetchColumn();
    if ($roleName === 'SUPERADMIN') throw new Exception("Não é permitido excluir SUPERADMIN.");

    // remove vínculos
    $pdo->prepare("DELETE FROM role_permissions WHERE role_id=?")->execute([$rid]);
    $pdo->prepare("DELETE FROM user_roles WHERE role_id=?")->execute([$rid]);
    $pdo->prepare("DELETE FROM roles WHERE id=?")->execute([$rid]);

    set_flash("Role excluída!");
    header("Location: roles.php");
    exit;
  }

  // SAVE PERMISSIONS
  if ($action === 'save_perms') {
    csrf_check();
    $rid = (int)($_POST['role_id'] ?? 0);
    if ($rid <= 0) throw new Exception("Role inválida.");

    $permIds = $_POST['perm_id'] ?? [];
    if (!is_array($permIds)) $permIds = [];

    $permIds = array_values(array_filter(array_map('intval', $permIds), fn($v)=>$v>0));

    $pdo->beginTransaction();
    $pdo->prepare("DELETE FROM role_permissions WHERE role_id=?")->execute([$rid]);

    if ($permIds) {
      $ins = $pdo->prepare("INSERT INTO role_permissions (role_id, permission_id) VALUES (?,?)");
      foreach ($permIds as $pid) $ins->execute([$rid, $pid]);
    }
    $pdo->commit();

    set_flash("Permissões atualizadas!");
    header("Location: roles.php?role_id=".$rid);
    exit;
  }

} catch (Throwable $e) {
  if ($pdo->inTransaction()) $pdo->rollBack();
  $errors[] = $e->getMessage();
}

/* ===== Load data ===== */
try {
  $roles = $pdo->query("SELECT * FROM roles ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

  $selectedRole = null;
  if ($role_id > 0) {
    $st = $pdo->prepare("SELECT * FROM roles WHERE id=?");
    $st->execute([$role_id]);
    $selectedRole = $st->fetch(PDO::FETCH_ASSOC);
  }

  $perms = $pdo->query("SELECT * FROM permissions ORDER BY code")->fetchAll(PDO::FETCH_ASSOC);

  $rolePermIds = [];
  if ($role_id > 0) {
    $st = $pdo->prepare("SELECT permission_id FROM role_permissions WHERE role_id=?");
    $st->execute([$role_id]);
    $rolePermIds = $st->fetchAll(PDO::FETCH_COLUMN);
    $rolePermIds = array_map('intval', $rolePermIds);
  }
} catch (Throwable $e) {
  $roles = $perms = [];
  $selectedRole = null;
  $rolePermIds = [];
  $errors[] = "Erro carregando dados: ".$e->getMessage();
}

require __DIR__ . '/../../inc/header.php';
?>

<div class="flex items-center justify-between mb-6">
  <h1 class="text-2xl font-semibold">Controle de Acesso • Roles</h1>
  <a href="/index.php" class="btn"><i data-lucide="home" class="h-4 w-4"></i> Início</a>
</div>

<?php flash(); ?>

<?php if ($errors): ?>
  <div class="card border border-red-800 bg-red-500/10 text-red-200 mb-4 p-4">
    <div class="font-semibold mb-2">Erros:</div>
    <ul class="list-disc ml-5 text-sm space-y-1">
      <?php foreach($errors as $er): ?><li><?=h($er)?></li><?php endforeach; ?>
    </ul>
  </div>
<?php endif; ?>

<div class="grid lg:grid-cols-3 gap-4">
  <!-- Lista -->
  <div class="card p-4">
    <div class="flex items-center justify-between mb-3">
      <div class="font-semibold">Roles</div>
      <a class="btn btn-muted" href="roles.php">Nova</a>
    </div>

    <?php if (!$roles): ?>
      <div class="text-gray-400">Nenhuma role cadastrada.</div>
    <?php else: ?>
      <div class="space-y-2">
        <?php foreach($roles as $r): ?>
          <a class="block px-3 py-2 rounded-xl border border-gray-800 hover:bg-gray-800/40 <?=($role_id==(int)$r['id'])?'bg-gray-800/50':''?>"
             href="roles.php?role_id=<?=$r['id']?>">
            <div class="font-medium"><?=h($r['name'])?></div>
            <?php if ($hasRoleDesc && !empty($r['description'])): ?>
              <div class="text-xs text-gray-400"><?=h($r['description'])?></div>
            <?php endif; ?>
          </a>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>

  <!-- Form Role -->
  <div class="card p-4">
    <div class="font-semibold mb-3"><?= $selectedRole ? 'Editar Role' : 'Criar Role' ?></div>

    <form method="post" class="grid gap-3">
      <input type="hidden" name="_csrf" value="<?=h($_SESSION['_csrf'])?>">
      <input type="hidden" name="action" value="save_role">
      <input type="hidden" name="role_id" value="<?=h($selectedRole['id'] ?? '')?>">

      <div>
        <label class="text-sm">Nome</label>
        <input name="name" class="input" required value="<?=h($selectedRole['name'] ?? '')?>" placeholder="EX: SUPERADMIN">
      </div>

      <?php if ($hasRoleDesc): ?>
      <div>
        <label class="text-sm">Descrição (opcional)</label>
        <input name="description" class="input" value="<?=h($selectedRole['description'] ?? '')?>">
      </div>
      <?php else: ?>
      <div class="text-xs text-gray-400">
        Observação: sua tabela <b>roles</b> não tem coluna <b>description</b> (ok, o sistema se adapta).
      </div>
      <?php endif; ?>

      <div class="flex gap-2">
        <button class="btn"><i data-lucide="save" class="h-4 w-4"></i> Salvar</button>

        <?php if ($selectedRole): ?>
          <form method="post" onsubmit="return confirm('Excluir esta role?');">
            <input type="hidden" name="_csrf" value="<?=h($_SESSION['_csrf'])?>">
            <input type="hidden" name="action" value="delete_role">
            <input type="hidden" name="role_id" value="<?=h($selectedRole['id'])?>">
            <button class="btn bg-red-600 hover:bg-red-700 text-white" type="submit">
              <i data-lucide="trash" class="h-4 w-4"></i> Excluir
            </button>
          </form>
        <?php endif; ?>
      </div>
    </form>
  </div>

  <!-- Permissões -->
  <div class="card p-4">
    <div class="font-semibold mb-3">Permissões da Role</div>

    <?php if (!$selectedRole): ?>
      <div class="text-gray-400">Selecione uma role à esquerda para editar permissões.</div>
    <?php else: ?>
      <form method="post" class="grid gap-3">
        <input type="hidden" name="_csrf" value="<?=h($_SESSION['_csrf'])?>">
        <input type="hidden" name="action" value="save_perms">
        <input type="hidden" name="role_id" value="<?=h($selectedRole['id'])?>">

        <div class="text-sm text-gray-400">
          Role atual: <b><?=h($selectedRole['name'])?></b>
        </div>

        <div class="max-h-[520px] overflow-auto border border-gray-800 rounded-xl p-3 space-y-2">
          <?php if (!$perms): ?>
            <div class="text-gray-400">Nenhuma permissão cadastrada.</div>
          <?php else: ?>
            <?php foreach($perms as $p): ?>
              <?php $pid = (int)$p['id']; $checked = in_array($pid, $rolePermIds, true); ?>
              <label class="flex items-start gap-3 p-2 rounded-xl hover:bg-gray-800/40">
                <input type="checkbox" name="perm_id[]" value="<?=$pid?>" <?=$checked?'checked':''?>>
                <div>
                  <div class="font-mono text-sm"><?=h($p['code'] ?? '')?></div>
                  <?php if ($hasPermDesc && !empty($p['description'])): ?>
                    <div class="text-xs text-gray-400"><?=h($p['description'])?></div>
                  <?php endif; ?>
                </div>
              </label>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>

        <button class="btn"><i data-lucide="shield" class="h-4 w-4"></i> Salvar permissões</button>
      </form>
    <?php endif; ?>
  </div>
</div>

<?php require __DIR__ . '/../../inc/footer.php'; ?>
