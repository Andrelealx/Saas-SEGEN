<?php
// login.php
if (session_status() !== PHP_SESSION_ACTIVE) session_start();

require_once __DIR__ . '/core/auth.php';
require_once __DIR__ . '/core/db.php';     // <-- CORREÇÃO: estava /db.php
require_once __DIR__ . '/core/utils.php';  // se você tiver set_flash(), h(), etc

// (Opcional) RBAC: só carrega se existir
$rbacFile = __DIR__ . '/core/rbac.php';
if (is_file($rbacFile)) {
  require_once $rbacFile;
}

$error = null;

// CSRF token
if (empty($_SESSION['csrf_token'])) {
  $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $userOrEmail = trim($_POST['email'] ?? '');
  $password    = (string)($_POST['password'] ?? '');
  $csrf        = (string)($_POST['csrf_token'] ?? '');

  // valida CSRF
  if (!hash_equals($_SESSION['csrf_token'], $csrf)) {
    $error = "Sessão expirada. Atualize a página e tente novamente.";
  } elseif ($userOrEmail === '' || $password === '') {
    $error = "Preencha todos os campos.";
  } else {
    try {
      if (auth_login($userOrEmail, $password)) {

        // Se você usa uid na sessão, garanta que está setado pelo auth_login.
        // Caso RBAC exista, carregue permissões na sessão:
        if (function_exists('rbac_load_session')) {
          $uid = (int)($_SESSION['uid'] ?? 0);
          if ($uid > 0) rbac_load_session($pdo, $uid);
        }

        header("Location: /index.php");
        exit;
      } else {
        $error = "Usuário ou senha inválidos.";
      }
    } catch (Throwable $e) {
      // Loga o erro, mas não mostra pro usuário
      error_log("LOGIN ERROR: " . $e->getMessage());
      $error = "Erro ao processar login. Tente novamente.";
    }
  }
}
?>
<!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Login • RH Segurança</title>
<script src="https://cdn.tailwindcss.com"></script>
<link rel="stylesheet" href="/assets/css/app.css">
</head>
<body class="min-h-screen flex items-center justify-center bg-gray-900 text-gray-100">

<div class="w-full max-w-md">
  <form method="post" class="card card-muted p-6 shadow-lg shadow-black/30" autocomplete="on">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">

      <div class="mb-4 text-center">
          <h1 class="text-2xl font-bold mb-1">RH Segurança</h1>
          <p class="text-sm text-gray-400">Acesse com suas credenciais</p>
      </div>

      <?php if (!empty($error)): ?>
          <div class="mb-3 text-sm text-red-200 bg-red-950/40 border border-red-800 rounded px-3 py-2">
              <?= htmlspecialchars($error) ?>
          </div>
      <?php endif; ?>

      <label class="block text-sm mb-1">E-mail, Usuário ou Nome</label>
      <input name="email" type="text" required autofocus
             autocomplete="username"
             class="input mb-3 w-full"
             value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">

      <label class="block text-sm mb-1">Senha</label>
      <input name="password" type="password" required
             autocomplete="current-password"
             class="input mb-4 w-full">

      <button class="btn w-full mt-1" type="submit">Entrar</button>

      <p class="text-center text-xs text-gray-500 mt-4">
          © <?=date('Y')?> Secretaria de Segurança
      </p>
  </form>
</div>

</body>
</html>
