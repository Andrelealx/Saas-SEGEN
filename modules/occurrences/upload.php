<?php
require __DIR__.'/../../core/auth.php'; auth_require();
require __DIR__.'/../../core/db.php';
require __DIR__.'/_oc_helpers.php';

date_default_timezone_set('America/Sao_Paulo');

if($_SERVER['REQUEST_METHOD'] !== 'POST'){ http_response_code(405); exit('Método não permitido'); }
occ_csrf_check();

$uid = occ_uid();
$occurrence_id = (int)($_POST['occurrence_id'] ?? 0);
$note = trim($_POST['note'] ?? '');

if(!$occurrence_id){ http_response_code(400); exit('Ocorrência inválida'); }

// carrega ocorrência para validar status
$st = $pdo->prepare("SELECT id,status,created_by FROM occurrences WHERE id=?");
$st->execute([$occurrence_id]);
$occ = $st->fetch(PDO::FETCH_ASSOC);
if(!$occ){ http_response_code(404); exit('Ocorrência não encontrada'); }

if(in_array($occ['status'], ['closed'], true)){
  http_response_code(403); exit('Ocorrência encerrada: anexos bloqueados.');
}

if(empty($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK){
  http_response_code(400); exit('Arquivo não enviado');
}

$f = $_FILES['file'];
$max = 20 * 1024 * 1024; // 20MB
if((int)$f['size'] > $max){ http_response_code(400); exit('Arquivo acima de 20MB'); }

$orig = oc_safe_filename($f['name']);
$ext = strtolower(pathinfo($orig, PATHINFO_EXTENSION));

$allowed_ext = ['pdf','jpg','jpeg','png','doc','docx','xls','xlsx','txt'];
if(!in_array($ext, $allowed_ext, true)){
  http_response_code(400); exit('Extensão não permitida');
}

$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime = $finfo->file($f['tmp_name']) ?: 'application/octet-stream';

// diretório: YYYY/MM
$year = date('Y');
$month = date('m');
$rel_dir = $year.'/'.$month;
$base = oc_upload_base_dir();
$target_dir = $base.'/'.$rel_dir;

if(!is_dir($target_dir)){
  if(!mkdir($target_dir, 0755, true)){
    http_response_code(500); exit('Falha ao criar diretório de upload');
  }
}

$rand = bin2hex(random_bytes(16));
$stored_name = $rand.'_'.$orig;
$stored_path = $rel_dir.'/'.$stored_name;
$abs = oc_upload_abs_path($stored_path);

if(!move_uploaded_file($f['tmp_name'], $abs)){
  http_response_code(500); exit('Falha ao salvar arquivo');
}

$ins = $pdo->prepare("INSERT INTO occurrence_attachments
  (occurrence_id, original_name, stored_path, mime, size_bytes, created_by)
  VALUES (?,?,?,?,?,?)");
$ins->execute([$occurrence_id, $orig, $stored_path, $mime, (int)$f['size'], $uid]);

$att_id = (int)$pdo->lastInsertId();
oc_audit($pdo, $occurrence_id, 'attachment_added', ['attachment_id'=>$att_id,'name'=>$orig,'note'=>$note], $uid);

header('Location: view.php?id='.$occurrence_id);
exit;
