<?php
function ensure_session(): void {
  if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
  }
}

function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

function flash(): void {
  ensure_session();
  if (empty($_SESSION['flash'])) return;

  $payload = $_SESSION['flash'];
  unset($_SESSION['flash']);

  $message = is_array($payload) ? (string)($payload['message'] ?? '') : (string)$payload;
  $type = is_array($payload) ? (string)($payload['type'] ?? 'success') : 'success';
  if ($message === '') return;

  $classes = [
    'success' => 'text-green-300 bg-green-500/10 border border-green-700',
    'error' => 'text-red-200 bg-red-500/10 border border-red-700',
    'warning' => 'text-amber-200 bg-amber-500/10 border border-amber-700',
    'info' => 'text-blue-200 bg-blue-500/10 border border-blue-700',
  ];
  $class = $classes[$type] ?? $classes['success'];

  echo "<div class='mb-3 px-3 py-2 rounded ".$class."'>".h($message)."</div>";
}

function set_flash($t, string $type = 'success'): void {
  ensure_session();
  $_SESSION['flash'] = ['message' => (string)$t, 'type' => $type];
}

function csrf_token(string $key = '_csrf'): string {
  ensure_session();
  if (empty($_SESSION[$key])) {
    $_SESSION[$key] = bin2hex(random_bytes(16));
  }
  return (string)$_SESSION[$key];
}

function csrf_check(string $key = '_csrf'): void {
  ensure_session();
  $token = (string)($_POST[$key] ?? '');
  $known = (string)($_SESSION[$key] ?? '');
  if ($token === '' || $known === '' || !hash_equals($known, $token)) {
    http_response_code(403);
    exit('CSRF inválido.');
  }
}

function audit($pdo,$userId,$action,$entity,$entityId,$payload=[]){
  $q = $pdo->prepare("INSERT INTO audit_logs (user_id,action,entity,entity_id,payload) VALUES (?,?,?,?,?)");
  $q->execute([$userId,$action,$entity,$entityId, json_encode($payload, JSON_UNESCAPED_UNICODE)]);
}
