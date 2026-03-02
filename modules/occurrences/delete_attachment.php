<?php
require __DIR__.'/../../core/auth.php'; auth_require();
require __DIR__.'/../../core/db.php';
require __DIR__.'/_oc_helpers.php';

date_default_timezone_set('America/Sao_Paulo');

if($_SERVER['REQUEST_METHOD'] !== 'POST'){ http_response_code(405); exit('Método não permitido'); }
occ_csrf_check();

$uid = occ_uid();
$attachment_id = (int)($_POST['attachment_id'] ?? 0);
$occurrence_id = (int)($_POST['occurrence_id'] ?? 0);

if(!$attachment_id || !$occurrence_id){ http_response_code(400); exit('Dados inválidos'); }

$st = $pdo->prepare("SELECT a.*, o.status AS occ_status, o.created_by AS occ_created_by
                     FROM occurrence_attachments a
                     JOIN occurrences o ON o.id=a.occurrence_id
                     WHERE a.id=? AND a.occurrence_id=?");
$st->execute([$attachment_id, $occurrence_id]);
$a = $st->fetch(PDO::FETCH_ASSOC);
if(!$a){ http_response_code(404); exit('Anexo não encontrado'); }

if(in_array($a['occ_status'], ['closed'], true)){
  http_response_code(403); exit('Ocorrência encerrada: não é possível remover anexos.');
}

$can = occ_is_manager() || (int)$a['created_by'] === $uid || (int)$a['occ_created_by'] === $uid;
if(!$can){ http_response_code(403); exit('Sem permissão'); }

$abs = oc_upload_abs_path($a['stored_path']);
if(is_file($abs)) @unlink($abs);

$pdo->prepare("DELETE FROM occurrence_attachments WHERE id=?")->execute([$attachment_id]);
oc_audit($pdo, $occurrence_id, 'attachment_removed', ['attachment_id'=>$attachment_id,'name'=>$a['original_name']], $uid);

header('Location: view.php?id='.$occurrence_id);
exit;
