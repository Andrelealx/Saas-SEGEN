<?php
// modules/occurrences/_oc_helpers.php

if (!function_exists('h')) {
  function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
}

/**
 * Papéis (role_id) com permissão de gerenciar/encerrar/cancelar.
 * Ajuste se necessário (ex.: [1] apenas admin).
 */
if (!defined('OCC_MANAGER_ROLES')) {
  define('OCC_MANAGER_ROLES', [1,2]);
}

function occ_uid(): int {
  if (session_status() === PHP_SESSION_NONE) session_start();
  return (int)($_SESSION['uid'] ?? 0);
}

function occ_role_id(): int {
  if (session_status() === PHP_SESSION_NONE) session_start();
  return (int)($_SESSION['urole_id'] ?? 0);
}

function occ_is_manager(): bool {
  if (function_exists('auth_can')) {
    return auth_can(OCC_MANAGER_ROLES);
  }
  return occ_role_id() === 1;
}

/* ---------------- CSRF ---------------- */
function occ_csrf_token(): string {
  if (session_status() === PHP_SESSION_NONE) session_start();
  if (empty($_SESSION['_occ_csrf'])) {
    $_SESSION['_occ_csrf'] = bin2hex(random_bytes(16));
  }
  return $_SESSION['_occ_csrf'];
}

function occ_csrf_check(): void {
  if (session_status() === PHP_SESSION_NONE) session_start();
  $t = (string)($_POST['_csrf'] ?? '');
  if (!$t || empty($_SESSION['_occ_csrf']) || !hash_equals($_SESSION['_occ_csrf'], $t)) {
    http_response_code(403);
    exit('CSRF inválido.');
  }
}

/* --------- Protocolo sequencial por setor/ano --------- */
function oc_next_protocol(PDO $pdo, string $sector, ?int $year=null): string {
  $year = $year ?: (int)date('Y');
  $sector = strtoupper(trim($sector));

  $pdo->beginTransaction();
  try {
    $st = $pdo->prepare("SELECT last_number FROM occurrence_sequences WHERE year=? AND sector=? FOR UPDATE");
    $st->execute([$year, $sector]);
    $row = $st->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
      $pdo->prepare("INSERT INTO occurrence_sequences (year, sector, last_number) VALUES (?,?,0)")
          ->execute([$year, $sector]);
      $last = 0;
    } else {
      $last = (int)$row['last_number'];
    }

    $next = $last + 1;
    $pdo->prepare("UPDATE occurrence_sequences SET last_number=? WHERE year=? AND sector=?")
        ->execute([$next, $year, $sector]);

    $pdo->commit();
  } catch (Exception $e) {
    $pdo->rollBack();
    throw $e;
  }

  return sprintf('%s-%d-%06d', $sector, $year, $next);
}

/* ---------------- Auditoria ---------------- */
function oc_audit(PDO $pdo, int $occ_id, string $action, $meta, int $user_id): void {
  $json = $meta === null ? null : json_encode($meta, JSON_UNESCAPED_UNICODE);
  $st = $pdo->prepare("INSERT INTO occurrence_audit (occurrence_id, action, meta, created_by) VALUES (?,?,?,?)");
  $st->execute([$occ_id, $action, $json, $user_id]);
}

/* Compat: usado no create.php */
function oc_json_from_agencies(array $arr): ?string {
  $clean = array_values(array_filter(array_map('trim', $arr), fn($v)=>$v!==''));
  if (!$clean) return null;
  return json_encode($clean, JSON_UNESCAPED_UNICODE);
}

function oc_safe_filename(string $name): string {
  $name = preg_replace('/[^\\pL\\pN\\.\\-_ ]+/u', '', $name);
  $name = trim(preg_replace('/\\s+/', ' ', $name));
  return $name !== '' ? $name : 'arquivo';
}

function oc_root_dir(): string {
  // __DIR__ = /public_html/modules/occurrences
  return dirname(__DIR__, 2);
}

function oc_upload_base_dir(): string {
  return oc_root_dir() . '/uploads/occurrences';
}

function oc_upload_abs_path(string $stored_path): string {
  return oc_upload_base_dir() . '/' . ltrim($stored_path, '/');
}
