<?php
// core/auth.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/db.php';

/**
 * Faz login por email, username ou name.
 */
function auth_login(string $userInput, string $password): bool {
    global $pdo;

    $sql = "SELECT id, name, email, username, password_hash, role_id, is_active
            FROM users
            WHERE email = :v OR username = :v OR name = :v
            LIMIT 1";

    $q = $pdo->prepare($sql);
    $q->execute([':v' => $userInput]);
    $u = $q->fetch(PDO::FETCH_ASSOC);

    // Usuário não encontrado ou inativo
    if (!$u || !$u['is_active']) {
        return false;
    }

    // Senha inválida
    if (!password_verify($password, $u['password_hash'])) {
        return false;
    }

    // Login ok → reforça sessão
    session_regenerate_id(true);

    $_SESSION['uid']       = (int)$u['id'];
    $_SESSION['uname']     = $u['name'];
    $_SESSION['uemail']    = $u['email'];
    $_SESSION['urole_id']  = (int)$u['role_id'];
    $_SESSION['ulogged_at'] = time();

    return true;
}

/**
 * Exige login para acessar a página.
 */
function auth_require(): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    if (empty($_SESSION['uid'])) {
        header('Location: /login.php');
        exit;
    }
}

/**
 * Verifica se o usuário tem um dos papéis permitidos.
 * Ex: auth_can([1,2])  // roles 1 ou 2.
 */
function auth_can(array $rolesAllowed): bool {
    if (!isset($_SESSION['urole_id'])) {
        return false;
    }
    return in_array((int)$_SESSION['urole_id'], $rolesAllowed, true);
}

/**
 * Faz logout completo.
 */
function auth_logout(): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $_SESSION = [];
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    session_destroy();
    header('Location: /login.php');
    exit;
}
