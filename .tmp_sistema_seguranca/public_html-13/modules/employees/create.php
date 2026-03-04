<?php
require __DIR__.'/../../core/auth.php';
auth_require();

require __DIR__.'/../../core/db.php';
require __DIR__.'/../../core/utils.php';

/* ======= RBAC (corrige erro 500) =======
   Se você tiver core/rbac.php, ele será carregado.
   Se não tiver, não quebra a página.
*/
$rbacFile = __DIR__ . '/../../core/rbac.php';
if (is_file($rbacFile)) {
  require_once $rbacFile;
}

if (function_exists('rbac_require')) {
  rbac_require('employees.create');
}
// Se não existir RBAC ainda, deixa acessar (evita 500).
// Depois, quando seu RBAC estiver pronto, isso volta a ser obrigatório.

/* --- DEBUG TEMPORÁRIO (use só se ainda der tela branca/500)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
*/

// CSRF simples
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
if (empty($_SESSION['csrf_token'])) {
  $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$errors = [];

// carrega selects
try {
  $deps = $pdo->query("SELECT id,name FROM departments ORDER BY name")->fetchAll();
  $pos  = $pdo->query("SELECT id,name FROM positions ORDER BY name")->fetchAll();
} catch (Throwable $e) {
  $errors[] = "Erro ao carregar departamentos/cargos: " . $e->getMessage();
  $deps = $pos = [];
}

// submit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

  // valida CSRF
  $csrf = (string)($_POST['csrf_token'] ?? '');
  if (!hash_equals($_SESSION['csrf_token'], $csrf)) {
    $errors[] = "Sessão expirada. Atualize a página e tente novamente.";
  }

  // coleta campos
  $registration  = trim($_POST['registration'] ?? '');
  $name          = trim($_POST['name'] ?? '');
  $cpf           = trim($_POST['cpf'] ?? '');
  $birth_date    = $_POST['birth_date'] ?? null;
  $email         = trim($_POST['email'] ?? '');
  $phone         = trim($_POST['phone'] ?? '');
  $hire_date     = $_POST['hire_date'] ?? null;
  $department_id = ($_POST['department_id'] ?? '') !== '' ? (int)$_POST['department_id'] : null;
  $position_id   = ($_POST['position_id'] ?? '') !== '' ? (int)$_POST['position_id'] : null;
  $base_shift    = $_POST['base_shift'] ?? 'Adm';
  $status        = $_POST['status'] ?? 'ATIVO';
  $notes         = trim($_POST['notes'] ?? '');

  // validação simples
  if ($registration === '') $errors[] = "Matrícula é obrigatória.";
  if ($name === '')         $errors[] = "Nome é obrigatório.";

  // normaliza vazios para NULL
  $cpf        = $cpf        !== '' ? $cpf : null;
  $birth_date = $birth_date !== '' ? $birth_date : null;
  $email      = $email      !== '' ? $email : null;
  $phone      = $phone      !== '' ? $phone : null;
  $hire_date  = $hire_date  !== '' ? $hire_date : null;
  $notes      = $notes      !== '' ? $notes : null;

  if (!$errors) {
    try {
      $sql = "INSERT INTO employees
              (registration,name,cpf,birth_date,email,phone,hire_date,department_id,position_id,base_shift,status,notes)
              VALUES (?,?,?,?,?,?,?,?,?,?,?,?)";
      $stmt = $pdo->prepare($sql);
      $stmt->execute([
        $registration, $name, $cpf, $birth_date, $email, $phone, $hire_date,
        $department_id, $position_id, $base_shift, $status, $notes
      ]);

      set_flash("Funcionário cadastrado com sucesso!");
      header("Location: /modules/employees/");
      exit;

    } catch (PDOException $e) {
      $msg = $e->getMessage();

      // duplicidade
      if (stripos($msg, 'Duplicate entry') !== false) {
        // tenta detectar pelo nome do índice/coluna
        if (stripos($msg, 'registration') !== false) $errors[] = "Já existe um funcionário com essa matrícula.";
        if (stripos($msg, 'cpf') !== false)         $errors[] = "Já existe um funcionário com esse CPF.";
        if (!$errors) $errors[] = "Registro duplicado.";
      } else {
        $errors[] = "Erro ao salvar no banco: " . $msg;
      }
    } catch (Throwable $e) {
      $errors[] = "Erro inesperado: " . $e->getMessage();
    }
  }
}

require __DIR__.'/../../inc/header.php';
?>

<div class="flex items-center justify-between mb-4">
  <h1 class="text-xl font-semibold">Novo Funcionário</h1>
  <a href="/modules/employees/" class="btn"><i data-lucide="list" class="h-4 w-4"></i> Voltar</a>
</div>

<?php if ($errors): ?>
  <div class="card border border-red-800 bg-red-500/10 text-red-200 mb-4">
    <div class="font-semibold mb-1">Não foi possível salvar:</div>
    <ul class="list-disc ml-5 text-sm">
      <?php foreach ($errors as $e): ?><li><?=h($e)?></li><?php endforeach; ?>
    </ul>
  </div>
<?php endif; ?>

<div class="card">
  <form method="post" class="grid gap-3 md:grid-cols-2">
    <input type="hidden" name="csrf_token" value="<?=h($_SESSION['csrf_token'])?>">

    <div>
      <label class="text-sm">Matrícula</label>
      <input name="registration" required class="input" value="<?=h($_POST['registration'] ?? '')?>">
    </div>

    <div>
      <label class="text-sm">Nome</label>
      <input name="name" required class="input" value="<?=h($_POST['name'] ?? '')?>">
    </div>

    <div>
      <label class="text-sm">CPF</label>
      <input name="cpf" class="input" value="<?=h($_POST['cpf'] ?? '')?>">
    </div>

    <div>
      <label class="text-sm">Nascimento</label>
      <input type="date" name="birth_date" class="input צור" value="<?=h($_POST['birth_date'] ?? '')?>">
    </div>

    <div>
      <label class="text-sm">E-mail</label>
      <input type="email" name="email" class="input" value="<?=h($_POST['email'] ?? '')?>">
    </div>

    <div>
      <label class="text-sm">Telefone</label>
      <input name="phone" class="input" value="<?=h($_POST['phone'] ?? '')?>">
    </div>

    <div>
      <label class="text-sm">Admissão</label>
      <input type="date" name="hire_date" class="input" value="<?=h($_POST['hire_date'] ?? '')?>">
    </div>

    <div>
      <label class="text-sm">Departamento</label>
      <select name="department_id" class="select">
        <option value="">—</option>
        <?php foreach ($deps as $d): ?>
          <option value="<?=$d['id']?>" <?= (($_POST['department_id'] ?? '') == $d['id']) ? 'selected' : '' ?>>
            <?=h($d['name'])?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>

    <div>
      <label class="text-sm">Cargo</label>
      <select name="position_id" class="select">
        <option value="">—</option>
        <?php foreach ($pos as $p): ?>
          <option value="<?=$p['id']?>" <?= (($_POST['position_id'] ?? '') == $p['id']) ? 'selected' : '' ?>>
            <?=h($p['name'])?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>

    <div>
      <label class="text-sm">Turno base</label>
      <select name="base_shift" class="select">
        <?php
          $bs = $_POST['base_shift'] ?? 'Adm';
          foreach (['Adm','12x36','24x72','Outro'] as $opt) {
            $sel = $bs === $opt ? 'selected' : '';
            echo "<option $sel>".h($opt)."</option>";
          }
        ?>
      </select>
    </div>

    <div>
      <label class="text-sm">Status</label>
      <select name="status" class="select">
        <?php
          $st = $_POST['status'] ?? 'ATIVO';
          foreach (['ATIVO','AFASTADO','LICENCA','FERIAS','INATIVO'] as $opt) {
            $sel = $st === $opt ? 'selected' : '';
            echo "<option $sel>".h($opt)."</option>";
          }
        ?>
      </select>
    </div>

    <div class="md:col-span-2">
      <label class="text-sm">Observações</label>
      <textarea name="notes" class="input" rows="3"><?=h($_POST['notes'] ?? '')?></textarea>
    </div>

    <div class="md:col-span-2">
      <button class="btn"><i data-lucide="save" class="h-4 w-4"></i> Salvar</button>
    </div>
  </form>
</div>

<?php require __DIR__.'/../../inc/footer.php'; ?>
