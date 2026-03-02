<?php
// /core/rbac.php
// Depende de: $pdo (db.php) e session ativa
if (session_status() !== PHP_SESSION_ACTIVE) session_start();

function rbac_load_session(PDO $pdo, int $userId): void {
  // Roles do usuário
  $st = $pdo->prepare("
    SELECT r.id, r.name
    FROM user_roles ur
    JOIN roles r ON r.id = ur.role_id
    WHERE ur.user_id=?
    ORDER BY r.name
  ");
  $st->execute([$userId]);
  $roles = $st->fetchAll(PDO::FETCH_ASSOC);
  
  

  // Permissões (por roles)
  $st2 = $pdo->prepare("
    SELECT DISTINCT p.code
    FROM user_roles ur
    JOIN role_permissions rp ON rp.role_id = ur.role_id
    JOIN permissions p ON p.id = rp.permission_id
    WHERE ur.user_id=?
  ");
  $st2->execute([$userId]);
  $perms = $st2->fetchAll(PDO::FETCH_COLUMN);

  $_SESSION['rbac_roles'] = array_map(fn($r)=>$r['name'], $roles);
  $_SESSION['rbac_perms'] = $perms;
}

function rbac_roles(): array {
  return $_SESSION['rbac_roles'] ?? [];
}

function rbac_perms(): array {
  return $_SESSION['rbac_perms'] ?? [];
}

function rbac_has_role(string $role): bool {
  return in_array($role, rbac_roles(), true);
}

function rbac_can(string $perm): bool {
  // SUPERADMIN sempre pode
  if (rbac_has_role('SUPERADMIN')) return true;
  return in_array($perm, rbac_perms(), true);
}

function rbac_require(string $perm): void {
  if (!rbac_can($perm)) {
    http_response_code(403);
    echo "<div style='padding:16px;font-family:Arial'>Acesso negado (403). Permissão necessária: <b>".htmlspecialchars($perm)."</b></div>";
    exit;
  }
}
