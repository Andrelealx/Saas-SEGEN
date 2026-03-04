<?php
require __DIR__ . '/../../core/auth.php';
auth_require();
require __DIR__ . '/../../core/db.php';
require __DIR__ . '/../../core/utils.php';

if (session_status() !== PHP_SESSION_ACTIVE) session_start();

/* ===== RBAC seguro (não quebra se ainda não existir) ===== */
$rbacFile = __DIR__ . '/../../core/rbac.php';
if (is_file($rbacFile)) require_once $rbacFile;

if (function_exists('rbac_require')) {
  rbac_require('access.users.manage');
}

/* ===== DEBUG (ligue se der tela branca) =====
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
*/

function dbname(PDO $pdo): string {
  try { return (string)$pdo->query("SELECT DATABASE()")->fetchColumn(); }
  catch(Throwable $e){ return ''; }
}
function table_exists(PDO $pdo, string $table): bool {
  $db = dbname($pdo);
  if ($db === '') return false;
  $st = $pdo->prepare("
    SELECT COUNT(*)
    FROM information_schema.TABLES
    WHERE TABLE_SCHEMA=? AND TABLE_NAME=?
  ");
  $st->execute([$db, $table]);
  return (int)$st->fetchColumn() > 0;
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

/* ===== Detecta tabela de usuários (users/usuarios) ===== */
$userTable = null;
foreach (['users','usuarios'] as $t) {
  if (table_exists($pdo, $t)) { $userTable = $t; break; }
}
if (!$userTable) {
  http_response_code(500);
  exit("Tabela de usuários não encontrada (esperado: users ou usuarios).");
}

/* ===== Detecta colunas ===== */
$colUsername = column_exists($pdo, $userTable, 'username') ? 'username' : null;
$colName     = column_exists($pdo, $userTable, 'name') ? 'name' : (column_exists($pdo, $userTable, 'nome') ? 'nome' : null);
$colEmail    = column_exists($pdo, $userTable, 'email') ? 'email' : null;
$colActive   = column_exists($pdo, $userTable, 'active') ? 'active' : (column_exists($pdo, $userTable, 'ativo') ? 'ativo' : null);
$colPassHash = column_exists($pdo, $userTable, 'password_hash') ? 'password_hash' : null;
$colPass     = column_exists($pdo, $userTable, 'password') ? 'password' : null;

if (!$colName) {
  http_response_code(500);
  exit("Coluna de nome não encontrada na tabela $userTable (esperado: name ou nome).");
}
if (!$colEmail) {
  http_response_code(500);
  exit("Coluna email não encontrada na tabela $userTable.");
}
if (!$colPassHash && !$colPass) {
  http_response_code(500);
  exit("Nenhuma coluna de senha encontrada em $userTable (esperado: password_hash ou password).");
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

/* ===== Helpers ===== */
function pick(array $row, ?string $col, $default = ''){
  if (!$col) return $default;
  return $row[$col] ?? $default;
}
function boolval_db($v): int {
  return (int)!!$v;
}

/* ===== Carrega roles ===== */
$roles = [];
try {
  if (table_exists($pdo,'roles')) {
    $roles = $pdo->query("SELECT id,name FROM roles ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
  }
} catch(Throwable $e){}

/* ===== Ações ===== */
$errors = [];
$action = $_POST['action'] ?? '';
$editId = (int)($_GET['id'] ?? ($_POST['id'] ?? 0));
$selfId = (int)($_SESSION['uid'] ?? 0);

try {

  // Salvar usuário (create/update)
  if ($action === 'save_user') {
    csrf_check();

    $id       = (int)($_POST['id'] ?? 0);
    $name     = trim((string)($_POST['name'] ?? ''));
    $email    = trim((string)($_POST['email'] ?? ''));
    $username = trim((string)($_POST['username'] ?? ''));
    $active   = isset($_POST['active']) ? 1 : 0;

    if ($name === '')  throw new Exception("Nome é obrigatório.");
    if ($email === '') throw new Exception("E-mail é obrigatório.");

    // trava: não deixar desativar a si mesmo
    if ($id > 0 && $id === $selfId && $active === 0) {
      throw new Exception("Você não pode desativar o próprio usuário.");
    }

    // valida e-mail simples
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
      throw new Exception("E-mail inválido.");
    }

    // username opcional, mas se existir coluna, vale validar
    if ($colUsername && $username !== '' && !preg_match('/^[a-zA-Z0-9._-]{3,40}$/', $username)) {
      throw new Exception("Username inválido. Use letras/números e . _ - (3 a 40 chars).");
    }

    // checa duplicidade email/username
    if ($id > 0) {
      $st = $pdo->prepare("SELECT id FROM {$userTable} WHERE {$colEmail}=? AND id<>? LIMIT 1");
      $st->execute([$email, $id]);
      if ($st->fetchColumn()) throw new Exception("Já existe outro usuário com esse e-mail.");

      if ($colUsername && $username !== '') {
        $st = $pdo->prepare("SELECT id FROM {$userTable} WHERE {$colUsername}=? AND id<>? LIMIT 1");
        $st->execute([$username, $id]);
        if ($st->fetchColumn()) throw new Exception("Já existe outro usuário com esse username.");
      }
    } else {
      $st = $pdo->prepare("SELECT id FROM {$userTable} WHERE {$colEmail}=? LIMIT 1");
      $st->execute([$email]);
      if ($st->fetchColumn()) throw new Exception("Já existe usuário com esse e-mail.");

      if ($colUsername && $username !== '') {
        $st = $pdo->prepare("SELECT id FROM {$userTable} WHERE {$colUsername}=? LIMIT 1");
        $st->execute([$username]);
        if ($st->fetchColumn()) throw new Exception("Já existe usuário com esse username.");
      }
    }

    // UPDATE
    if ($id > 0) {
      $set = [];
      $params = [];

      $set[] = "{$colName}=?";
      $params[] = $name;

      $set[] = "{$colEmail}=?";
      $params[] = $email;

      if ($colUsername) {
        $set[] = "{$colUsername}=?";
        $params[] = ($username !== '' ? $username : null);
      }

      if ($colActive) {
        $set[] = "{$colActive}=?";
        $params[] = $active;
      }

      $params[] = $id;

      $sql = "UPDATE {$userTable} SET ".implode(',', $set)." WHERE id=?";
      $pdo->prepare($sql)->execute($params);

      set_flash("Usuário atualizado!");
      header("Location: users.php?id=".$id);
      exit;
    }

    // CREATE
    $cols = [$colName, $colEmail];
    $vals = [$name, $email];
    $ph   = ['?','?'];

    if ($colUsername) { $cols[] = $colUsername; $vals[] = ($username !== '' ? $username : null); $ph[]='?'; }
    if ($colActive)   { $cols[] = $colActive;   $vals[] = 1; $ph[]='?'; }

    // senha inicial obrigatória ao criar
    $pass1 = (string)($_POST['password'] ?? '');
    if ($pass1 === '' || strlen($pass1) < 4) throw new Exception("Defina uma senha inicial (mínimo 4 caracteres).");

    $hash = password_hash($pass1, PASSWORD_DEFAULT);

    if ($colPassHash) { $cols[] = $colPassHash; $vals[] = $hash; $ph[]='?'; }
    else              { $cols[] = $colPass;     $vals[] = $hash; $ph[]='?'; }

    $sql = "INSERT INTO {$userTable} (".implode(',',$cols).") VALUES (".implode(',',$ph).")";
    $pdo->prepare($sql)->execute($vals);

    $newId = (int)$pdo->lastInsertId();

    set_flash("Usuário criado!");
    header("Location: users.php?id=".$newId);
    exit;
  }

  // Resetar senha
  if ($action === 'set_password') {
    csrf_check();

    $id = (int)($_POST['id'] ?? 0);
    $p1 = (string)($_POST['newpass'] ?? '');

    if ($id <= 0) throw new Exception("Usuário inválido.");
    if ($p1 === '' || strlen($p1) < 4) throw new Exception("Senha muito curta (mín. 4).");

    $hash = password_hash($p1, PASSWORD_DEFAULT);

    $col = $colPassHash ?: $colPass;
    $pdo->prepare("UPDATE {$userTable} SET {$col}=? WHERE id=?")->execute([$hash, $id]);

    set_flash("Senha atualizada!");
    header("Location: users.php?id=".$id);
    exit;
  }

  // Salvar roles do usuário
  if ($action === 'save_roles') {
    csrf_check();

    $id = (int)($_POST['id'] ?? 0);
    if ($id <= 0) throw new Exception("Usuário inválido.");

    if (!table_exists($pdo,'user_roles') || !table_exists($pdo,'roles')) {
      throw new Exception("Tabelas RBAC não encontradas (user_roles/roles).");
    }

    $roleIds = $_POST['role_id'] ?? [];
    if (!is_array($roleIds)) $roleIds = [];
    $roleIds = array_values(array_filter(array_map('intval', $roleIds), fn($v)=>$v>0));

    $pdo->beginTransaction();
    $pdo->prepare("DELETE FROM user_roles WHERE user_id=?")->execute([$id]);

    if ($roleIds) {
      $ins = $pdo->prepare("INSERT INTO user_roles (user_id, role_id) VALUES (?,?)");
      foreach ($roleIds as $rid) $ins->execute([$id, $rid]);
    }
    $pdo->commit();

    // (Opcional) Se o usuário editado for o próprio logado, recarrega RBAC na sessão
    if ($id === $selfId && function_exists('rbac_load_session')) {
      rbac_load_session($pdo, $id);
    }

    set_flash("Roles atualizadas!");
    header("Location: users.php?id=".$id);
    exit;
  }

} catch (Throwable $e) {
  if ($pdo->inTransaction()) $pdo->rollBack();
  $errors[] = $e->getMessage();
}

/* ===== Listagem + filtros ===== */
$q = trim((string)($_GET['q'] ?? ''));

$where = "1=1";
$params = [];
if ($q !== '') {
  $where .= " AND ({$colName} LIKE ? OR {$colEmail} LIKE ?".($colUsername ? " OR {$colUsername} LIKE ?" : "").")";
  $p = "%{$q}%";
  $params[] = $p;
  $params[] = $p;
  if ($colUsername) $params[] = $p;
}

$sql = "SELECT * FROM {$userTable} WHERE {$where} ORDER BY id DESC LIMIT 200";
$st = $pdo->prepare($sql);
$st->execute($params);
$users = $st->fetchAll(PDO::FETCH_ASSOC);

/* ===== Usuário selecionado ===== */
$selected = null;
$userRoleIds = [];
if ($editId > 0) {
  $st = $pdo->prepare("SELECT * FROM {$userTable} WHERE id=?");
  $st->execute([$editId]);
  $selected = $st->fetch(PDO::FETCH_ASSOC);

  if ($selected && table_exists($pdo,'user_roles')) {
    $st = $pdo->prepare("SELECT role_id FROM user_roles WHERE user_id=?");
    $st->execute([$editId]);
    $userRoleIds = array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN));
  }
}

require __DIR__ . '/../../inc/header.php';
?>

<div class="flex items-center justify-between mb-6">
  <h1 class="text-2xl font-semibold">Controle de Acesso • Usuários</h1>
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

  <!-- LISTA -->
  <div class="card p-4">
    <div class="flex items-center justify-between mb-3">
      <div class="font-semibold">Usuários</div>
      <a class="btn btn-muted" href="users.php">Novo</a>
    </div>

    <form method="get" class="flex gap-2 mb-3">
      <input class="input" name="q" placeholder="Buscar..." value="<?=h($q)?>">
      <button class="btn">Buscar</button>
    </form>

    <?php if (!$users): ?>
      <div class="text-gray-400">Nenhum usuário encontrado.</div>
    <?php else: ?>
      <div class="space-y-2 max-h-[560px] overflow-auto pr-1">
        <?php foreach($users as $u): ?>
          <?php
            $uid = (int)$u['id'];
            $nm  = (string)pick($u,$colName,'');
            $em  = (string)pick($u,$colEmail,'');
            $un  = $colUsername ? (string)pick($u,$colUsername,'') : '';
            $ac  = $colActive ? boolval_db(pick($u,$colActive,1)) : 1;
          ?>
          <a class="block px-3 py-2 rounded-xl border border-gray-800 hover:bg-gray-800/40 <?=($editId===$uid)?'bg-gray-800/50':''?>"
             href="users.php?id=<?=$uid?>">
            <div class="flex items-center justify-between gap-2">
              <div class="font-medium"><?=h($nm)?> <span class="text-xs text-gray-500">#<?=$uid?></span></div>
              <span class="text-xs px-2 py-1 rounded-full border <?= $ac ? 'border-green-700 text-green-300 bg-green-500/10' : 'border-red-700 text-red-300 bg-red-500/10' ?>">
                <?= $ac ? 'ATIVO' : 'INATIVO' ?>
              </span>
            </div>
            <div class="text-xs text-gray-400"><?=h($em)?><?= $un ? ' • '.h($un) : '' ?></div>
          </a>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>

  <!-- FORM USER -->
  <div class="card p-4">
    <div class="font-semibold mb-3"><?= $selected ? 'Editar Usuário' : 'Criar Usuário' ?></div>

    <form method="post" class="grid gap-3">
      <input type="hidden" name="_csrf" value="<?=h($_SESSION['_csrf'])?>">
      <input type="hidden" name="action" value="save_user">
      <input type="hidden" name="id" value="<?=h($selected['id'] ?? '')?>">

      <div>
        <label class="text-sm">Nome</label>
        <input name="name" class="input" required value="<?=h($selected ? pick($selected,$colName,'') : '')?>">
      </div>

      <div>
        <label class="text-sm">E-mail</label>
        <input name="email" class="input" required type="email" value="<?=h($selected ? pick($selected,$colEmail,'') : '')?>">
      </div>

      <?php if ($colUsername): ?>
      <div>
        <label class="text-sm">Username (opcional)</label>
        <input name="username" class="input" value="<?=h($selected ? pick($selected,$colUsername,'') : '')?>" placeholder="ex: lealx">
        <div class="text-xs text-gray-500 mt-1">Permite login por usuário. Use letras/números e . _ -</div>
      </div>
      <?php endif; ?>

      <?php if ($colActive): ?>
      <label class="flex items-center gap-2">
        <?php
          $isActive = $selected ? (bool)boolval_db(pick($selected,$colActive,1)) : true;
        ?>
        <input type="checkbox" name="active" <?= $isActive ? 'checked' : '' ?>>
        <span class="text-sm">Ativo</span>
      </label>
      <?php endif; ?>

      <?php if (!$selected): ?>
      <div>
        <label class="text-sm">Senha inicial</label>
        <input name="password" class="input" type="password" required minlength="4">
      </div>
      <?php endif; ?>

      <button class="btn"><i data-lucide="save" class="h-4 w-4"></i> Salvar</button>
    </form>
  </div>

  <!-- SENHA + ROLES -->
  <div class="card p-4">
    <div class="font-semibold mb-3">Segurança & Roles</div>

    <?php if (!$selected): ?>
      <div class="text-gray-400">Crie o usuário primeiro para configurar senha/roles.</div>
    <?php else: ?>

      <!-- Reset senha -->
      <form method="post" class="grid gap-2 mb-4" onsubmit="return confirm('Atualizar a senha desse usuário?');">
        <input type="hidden" name="_csrf" value="<?=h($_SESSION['_csrf'])?>">
        <input type="hidden" name="action" value="set_password">
        <input type="hidden" name="id" value="<?=h($selected['id'])?>">

        <label class="text-sm">Nova senha</label>
        <input name="newpass" class="input" type="password" minlength="4" required>

        <button class="btn btn-muted"><i data-lucide="key" class="h-4 w-4"></i> Atualizar senha</button>
      </form>

      <!-- Roles -->
      <form method="post" class="grid gap-2">
        <input type="hidden" name="_csrf" value="<?=h($_SESSION['_csrf'])?>">
        <input type="hidden" name="action" value="save_roles">
        <input type="hidden" name="id" value="<?=h($selected['id'])?>">

        <div class="text-sm text-gray-400">
          Selecione as roles do usuário:
        </div>

        <?php if (!$roles): ?>
          <div class="text-gray-400">Nenhuma role cadastrada (vá em Roles e crie).</div>
        <?php else: ?>
          <div class="max-h-[420px] overflow-auto border border-gray-800 rounded-xl p-3 space-y-2">
            <?php foreach($roles as $r): ?>
              <?php $rid=(int)$r['id']; $checked=in_array($rid,$userRoleIds,true); ?>
              <label class="flex items-center gap-3 p-2 rounded-xl hover:bg-gray-800/40">
                <input type="checkbox" name="role_id[]" value="<?=$rid?>" <?=$checked?'checked':''?>>
                <span class="font-medium"><?=h($r['name'])?></span>
              </label>
            <?php endforeach; ?>
          </div>
          <button class="btn"><i data-lucide="shield" class="h-4 w-4"></i> Salvar roles</button>
        <?php endif; ?>
      </form>

    <?php endif; ?>
  </div>

</div>

<?php require __DIR__ . '/../../inc/footer.php'; ?>
