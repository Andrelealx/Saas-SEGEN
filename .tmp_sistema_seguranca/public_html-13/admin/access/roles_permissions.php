<?php
require __DIR__.'/../../../core/auth.php'; auth_require();
require __DIR__.'/../../../core/db.php';
require __DIR__.'/../../../core/utils.php';
require __DIR__.'/../../../core/rbac.php';

rbac_require('rbac.manage');

$roleId = (int)($_GET['id'] ?? 0);
if (!$roleId) { http_response_code(400); exit("Role inválida."); }

$role = $pdo->prepare("SELECT * FROM roles WHERE id=?");
$role->execute([$roleId]);
$role = $role->fetch(PDO::FETCH_ASSOC);
if (!$role) { http_response_code(404); exit("Role não encontrada."); }

$perms = $pdo->query("SELECT * FROM permissions ORDER BY code")->fetchAll(PDO::FETCH_ASSOC);

$has = $pdo->prepare("
  SELECT p.code
  FROM role_permissions rp
  JOIN permissions p ON p.id = rp.permission_id
  WHERE rp.role_id=?
");
$has->execute([$roleId]);
$hasCodes = $has->fetchAll(PDO::FETCH_COLUMN);
$hasSet = array_flip($hasCodes);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $selected = $_POST['perms'] ?? [];
  if (!is_array($selected)) $selected = [];

  $pdo->beginTransaction();
  try {
    $pdo->prepare("DELETE FROM role_permissions WHERE role_id=?")->execute([$roleId]);

    if ($selected) {
      $ins = $pdo->prepare("INSERT INTO role_permissions(role_id, permission_id)
                            SELECT ?, id FROM permissions WHERE code=?");
      foreach ($selected as $code) {
        $code = trim((string)$code);
        if ($code === '') continue;
        $ins->execute([$roleId, $code]);
      }
    }

    $pdo->commit();
    set_flash("Permissões atualizadas!");

    // opcional: se você quiser, pode recarregar a sessão do próprio admin
    // rbac_load_session($pdo, (int)$_SESSION['uid']);

    header("Location: role_permissions.php?id=".$roleId);
    exit;
  } catch (Throwable $e) {
    $pdo->rollBack();
    $err = $e->getMessage();
    set_flash("Erro: ".$err);
    header("Location: role_permissions.php?id=".$roleId);
    exit;
  }
}

require __DIR__.'/../../../inc/header.php';
?>

<div class="flex items-center justify-between mb-4">
  <h1 class="text-xl font-semibold">Permissões • <?=h($role['name'])?></h1>
  <a href="roles.php" class="btn">Voltar</a>
</div>

<div class="card p-4">
  <form method="post" class="grid gap-3">
    <div class="text-sm text-gray-400">Marque o que essa role pode acessar.</div>

    <div class="grid md:grid-cols-2 gap-2">
      <?php foreach($perms as $p): 
        $code = (string)$p['code'];
        $checked = isset($hasSet[$code]) ? 'checked' : '';
      ?>
        <label class="flex items-center gap-2 p-3 rounded bg-gray-800/60">
          <input type="checkbox" name="perms[]" value="<?=h($code)?>" <?=$checked?>>
          <div>
            <div class="font-semibold"><?=h($p['name'])?></div>
            <div class="text-xs text-gray-400"><?=h($code)?></div>
          </div>
        </label>
      <?php endforeach; ?>
    </div>

    <button class="btn mt-2">Salvar</button>
  </form>
</div>

<?php require __DIR__.'/../../../inc/footer.php'; ?>
