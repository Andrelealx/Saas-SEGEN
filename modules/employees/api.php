<?php
require __DIR__ . '/../../core/auth.php';
auth_require();

require __DIR__ . '/../../core/db.php';
require __DIR__ . '/../../core/utils.php';

if (session_status() !== PHP_SESSION_ACTIVE) session_start();

$action = $_REQUEST['action'] ?? 'list';

function out($d, int $code = 200){
  http_response_code($code);
  header('Content-Type: application/json; charset=utf-8');
  echo json_encode($d, JSON_UNESCAPED_UNICODE);
  exit;
}

function hasEmployeePhotoColumn(PDO $pdo): bool {
  static $cached = null;
  if ($cached !== null) return $cached;

  try {
    $db = $pdo->query("SELECT DATABASE()")->fetchColumn();
    if (!$db) return $cached = false;

    $st = $pdo->prepare("
      SELECT COUNT(*)
      FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA = ?
        AND TABLE_NAME = 'employees'
        AND COLUMN_NAME = 'photo'
    ");
    $st->execute([$db]);
    $cached = ((int)$st->fetchColumn() > 0);
    return $cached;
  } catch (Throwable $e) {
    return $cached = false;
  }
}

try {
  $writeActions = ['save', 'delete', 'bulk', 'import'];
  if (in_array($action, $writeActions, true)) {
    $known = (string)($_SESSION['_csrf'] ?? '');
    $sent = (string)($_POST['_csrf'] ?? '');
    if ($known === '' || $sent === '' || !hash_equals($known, $sent)) {
      out(['ok'=>false,'error'=>'CSRF inválido'], 403);
    }
  }

  /* =========================
     OPTIONS (deps/positions)
     ========================= */
  if ($action === 'options') {
    $deps  = $pdo->query("SELECT id,name FROM departments ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
    $poses = $pdo->query("SELECT id,name FROM positions ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
    out(['ok'=>true,'departments'=>$deps,'positions'=>$poses]);
  }

  /* =========================
     LIST (pagination + filters)
     ========================= */
  if ($action === 'list') {
    $q = trim($_GET['q'] ?? '');
    $status = $_GET['status'] ?? '';
    $dept = $_GET['department'] ?? '';
    $page = max(1,(int)($_GET['page'] ?? 1));
    $perPage = 20;
    $offset = ($page-1)*$perPage;

    $where = "1=1";
    $params = [];

    if ($q !== '') {
      $where .= " AND (e.name LIKE ? OR e.registration LIKE ? OR e.cpf LIKE ? OR e.email LIKE ?)";
      $p = "%$q%";
      $params = array_merge($params, [$p,$p,$p,$p]);
    }
    if ($status !== '') { $where .= " AND e.status=?"; $params[] = $status; }
    if ($dept !== '') { $where .= " AND e.department_id=?"; $params[] = (int)$dept; }

    $count = $pdo->prepare("SELECT COUNT(*) FROM employees e WHERE $where");
    $count->execute($params);
    $total = (int)$count->fetchColumn();

    // Se não tiver coluna photo, ainda assim retorna photo = null
    $hasPhoto = hasEmployeePhotoColumn($pdo);
    $photoSelect = $hasPhoto ? "e.photo" : "NULL AS photo";

    $sql = "SELECT e.id, e.registration, e.name, e.cpf, e.status, e.email, e.phone, e.notes,
                   e.department_id, e.position_id,
                   $photoSelect,
                   d.name AS dept, p.name AS pos
            FROM employees e
            LEFT JOIN departments d ON d.id = e.department_id
            LEFT JOIN positions p ON p.id = e.position_id
            WHERE $where
            ORDER BY e.name ASC
            LIMIT ? OFFSET ?";

    $stmt = $pdo->prepare($sql);
    $i = 1;
    foreach ($params as $v) { $stmt->bindValue($i++, $v); }
    $stmt->bindValue($i++, (int)$perPage, PDO::PARAM_INT);
    $stmt->bindValue($i++, (int)$offset, PDO::PARAM_INT);
    $stmt->execute();

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    out(['ok'=>true,'data'=>$rows,'total'=>$total,'page'=>$page,'perPage'=>$perPage]);
  }

  /* =========================
     GET single
     ========================= */
  if ($action === 'get') {
    $id = (int)($_GET['id'] ?? 0);
    if (!$id) out(['ok'=>false,'error'=>'ID inválido'], 400);

    $hasPhoto = hasEmployeePhotoColumn($pdo);
    $photoSelect = $hasPhoto ? "photo" : "NULL AS photo";

    $st = $pdo->prepare("SELECT *, $photoSelect FROM employees WHERE id=?");
    $st->execute([$id]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    if (!$row) out(['ok'=>false,'error'=>'Registro não encontrado'], 404);

    out(['ok'=>true,'data'=>$row]);
  }

  /* =========================
     SAVE (create/update) + upload photo
     ========================= */
  if ($action === 'save') {
    $id = !empty($_POST['id']) ? (int)$_POST['id'] : null;

    $name = trim($_POST['name'] ?? '');
    if ($name === '') out(['ok'=>false,'error'=>'Nome obrigatório'], 400);

    $registration  = isset($_POST['registration']) ? trim((string)$_POST['registration']) : null;
    $cpf           = isset($_POST['cpf']) ? trim((string)$_POST['cpf']) : null;
    $email         = isset($_POST['email']) ? trim((string)$_POST['email']) : null;
    $phone         = isset($_POST['phone']) ? trim((string)$_POST['phone']) : null;
    $notes         = isset($_POST['notes']) ? trim((string)$_POST['notes']) : null;
    $department_id = !empty($_POST['department_id']) ? (int)$_POST['department_id'] : null;
    $position_id   = !empty($_POST['position_id']) ? (int)$_POST['position_id'] : null;

    $statusIn = $_POST['status'] ?? 'ATIVO';
    $allowedStatus = ['ATIVO','FERIAS','AFASTADO','INATIVO','LICENCA'];
    $status = in_array($statusIn, $allowedStatus, true) ? $statusIn : 'ATIVO';

    if ($id) {
      $st = $pdo->prepare("UPDATE employees
        SET name=?, registration=?, cpf=?, status=?, email=?, phone=?, notes=?, department_id=?, position_id=?
        WHERE id=?");
      $st->execute([$name,$registration,$cpf,$status,$email,$phone,$notes,$department_id,$position_id,$id]);
    } else {
      $st = $pdo->prepare("INSERT INTO employees
        (name,registration,cpf,status,email,phone,notes,department_id,position_id)
        VALUES (?,?,?,?,?,?,?,?,?)");
      $st->execute([$name,$registration,$cpf,$status,$email,$phone,$notes,$department_id,$position_id]);
      $id = (int)$pdo->lastInsertId();
    }

    // Upload de foto (opcional) — só tenta se a coluna existir
    $hasPhoto = hasEmployeePhotoColumn($pdo);

    if ($hasPhoto && !empty($_FILES['photo']) && ($_FILES['photo']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {

      if ((int)$_FILES['photo']['size'] > 2 * 1024 * 1024) {
        out(['ok'=>false,'error'=>'Imagem maior que 2MB'], 400);
      }

      // MIME real (finfo se existir, senão mime_content_type)
      $mime = '';
      if (class_exists('finfo')) {
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = (string)$finfo->file($_FILES['photo']['tmp_name']);
      } else {
        $mime = (string)(mime_content_type($_FILES['photo']['tmp_name']) ?: '');
      }

      $allowedMime = ['image/jpeg','image/png','image/webp'];
      if (!in_array($mime, $allowedMime, true)) {
        out(['ok'=>false,'error'=>'Formato inválido (JPEG/PNG/WebP)'], 400);
      }

      // pasta pública (melhor que /storage privado)
      $dir = __DIR__ . '/../../uploads/employees';
      if (!is_dir($dir)) {
        if (!mkdir($dir, 0755, true)) out(['ok'=>false,'error'=>'Falha ao criar uploads/employees'], 500);
      }

      // sem match() (compatível PHP 7.4+)
      $ext = 'jpg';
      if ($mime === 'image/png')  $ext = 'png';
      if ($mime === 'image/webp') $ext = 'webp';

      $fname = 'employee_' . $id . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
      $dest = $dir . '/' . $fname;
      $dest_rel = '/uploads/employees/' . $fname;

      if (!move_uploaded_file($_FILES['photo']['tmp_name'], $dest)) {
        out(['ok'=>false,'error'=>'Falha ao salvar imagem (permissão/pasta).'], 500);
      }

      $pdo->prepare("UPDATE employees SET photo=? WHERE id=?")->execute([$dest_rel, $id]);
    }

    out(['ok'=>true,'id'=>$id]);
  }

  /* =========================
     DELETE single
     ========================= */
  if ($action === 'delete') {
    $id = (int)($_POST['id'] ?? 0);
    if (!$id) out(['ok'=>false,'error'=>'ID inválido'], 400);

    $pdo->prepare("DELETE FROM employees WHERE id=?")->execute([$id]);
    out(['ok'=>true]);
  }

  /* =========================
     BULK actions
     ========================= */
  if ($action === 'bulk') {
    $op = $_POST['op'] ?? '';
    $ids = $_POST['ids'] ?? [];

    if (!is_array($ids) || empty($ids)) out(['ok'=>false,'error'=>'Nenhum ID informado'], 400);

    $ids = array_values(array_filter(array_map('intval', $ids), function($v){ return $v > 0; }));
    if (!$ids) out(['ok'=>false,'error'=>'IDs inválidos'], 400);

    $placeholders = implode(',', array_fill(0, count($ids), '?'));

    if ($op === 'activate') {
      $pdo->prepare("UPDATE employees SET status='ATIVO' WHERE id IN ($placeholders)")->execute($ids);
      out(['ok'=>true]);
    } elseif ($op === 'deactivate') {
      $pdo->prepare("UPDATE employees SET status='INATIVO' WHERE id IN ($placeholders)")->execute($ids);
      out(['ok'=>true]);
    } elseif ($op === 'delete') {
      $pdo->prepare("DELETE FROM employees WHERE id IN ($placeholders)")->execute($ids);
      out(['ok'=>true]);
    } else {
      out(['ok'=>false,'error'=>'Operação inválida'], 400);
    }
  }

  /* =========================
     EXPORT
     ========================= */
  if ($action === 'export') {
    $q = trim($_GET['q'] ?? '');
    $status = $_GET['status'] ?? '';
    $dept = $_GET['department'] ?? '';

    $where = "1=1"; $params = [];
    if ($q !== '') {
      $where .= " AND (e.name LIKE ? OR e.registration LIKE ? OR e.cpf LIKE ? OR e.email LIKE ?)";
      $p="%$q%"; $params = [$p,$p,$p,$p];
    }
    if ($status !== '') { $where .= " AND e.status=?"; $params[] = $status; }
    if ($dept !== '') { $where .= " AND e.department_id=?"; $params[] = (int)$dept; }

    $st = $pdo->prepare("
      SELECT e.id, e.registration, e.name, e.cpf, e.email, e.phone,
             d.name as dept, p.name as position, e.status
      FROM employees e
      LEFT JOIN departments d ON d.id = e.department_id
      LEFT JOIN positions p ON p.id = e.position_id
      WHERE $where
      ORDER BY e.name
    ");
    $st->execute($params);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC);

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="employees_export_'.date('Ymd_His').'.csv"');

    $outf = fopen('php://output','w');
    fputcsv($outf, array_keys($rows[0] ?? ['id','registration','name','cpf','email','phone','dept','position','status']));
    foreach ($rows as $r) fputcsv($outf, $r);
    fclose($outf);
    exit;
  }

  /* =========================
     IMPORT CSV
     ========================= */
  if ($action === 'import') {
    if (empty($_FILES['file']) || ($_FILES['file']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
      out(['ok'=>false,'error'=>'Arquivo não enviado'], 400);
    }

    $tmp = $_FILES['file']['tmp_name'];
    $fh = fopen($tmp,'r');
    if (!$fh) out(['ok'=>false,'error'=>'Erro ao abrir arquivo'], 500);

    $header = fgetcsv($fh);
    if (!$header) { fclose($fh); out(['ok'=>false,'error'=>'CSV vazio'], 400); }

    $mapped = array_map(function($s){ return strtolower(trim((string)$s)); }, $header);
    $count = 0;

    while (($row = fgetcsv($fh)) !== false) {
      if (count($row) < count($mapped)) continue; // evita array_combine quebrar
      $data = @array_combine($mapped, $row);
      if (!$data) continue;

      $name = trim((string)($data['name'] ?? $data['nome'] ?? ''));
      if ($name === '') continue;

      $cpf = trim((string)($data['cpf'] ?? ''));
      $cpf = $cpf !== '' ? $cpf : null;

      $reg = trim((string)($data['registration'] ?? $data['matricula'] ?? ''));
      $reg = $reg !== '' ? $reg : null;

      $email = trim((string)($data['email'] ?? ''));
      $email = $email !== '' ? $email : null;

      $phone = trim((string)($data['phone'] ?? $data['telefone'] ?? ''));
      $phone = $phone !== '' ? $phone : null;

      $statusIn = trim((string)($data['status'] ?? 'ATIVO'));
      $allowedStatus = ['ATIVO','FERIAS','AFASTADO','INATIVO','LICENCA'];
      $status = in_array($statusIn, $allowedStatus, true) ? $statusIn : 'ATIVO';

      $eid = null;
      if ($cpf) {
        $existing = $pdo->prepare("SELECT id FROM employees WHERE cpf=? LIMIT 1");
        $existing->execute([$cpf]);
        $eid = $existing->fetchColumn();
      }

      if ($eid) {
        $pdo->prepare("UPDATE employees SET name=?, registration=?, email=?, phone=?, status=? WHERE id=?")
            ->execute([$name,$reg,$email,$phone,$status,$eid]);
      } else {
        $pdo->prepare("INSERT INTO employees (name,registration,cpf,email,phone,status) VALUES (?,?,?,?,?,?)")
            ->execute([$name,$reg,$cpf,$email,$phone,$status]);
      }

      $count++;
    }

    fclose($fh);
    out(['ok'=>true,'imported'=>$count]);
  }

  out(['ok'=>false,'error'=>'Ação inválida'], 400);

} catch (Throwable $e) {
  out(['ok'=>false,'error'=>'Erro no servidor: '.$e->getMessage()], 500);
}
