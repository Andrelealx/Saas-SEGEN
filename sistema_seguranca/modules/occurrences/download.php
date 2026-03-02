<?php
require __DIR__.'/../../core/auth.php'; auth_require();
require __DIR__.'/../../core/db.php';
require __DIR__.'/_oc_helpers.php';

date_default_timezone_set('America/Sao_Paulo');

$id = (int)($_GET['id'] ?? 0);
if(!$id){ http_response_code(400); exit('ID inválido'); }

$st = $pdo->prepare("SELECT a.*, o.status AS occ_status
                     FROM occurrence_attachments a
                     JOIN occurrences o ON o.id=a.occurrence_id
                     WHERE a.id=?");
$st->execute([$id]);
$a = $st->fetch(PDO::FETCH_ASSOC);
if(!$a){ http_response_code(404); exit('Anexo não encontrado'); }

$abs = oc_upload_abs_path($a['stored_path']);
if(!is_file($abs)){ http_response_code(404); exit('Arquivo não encontrado no servidor'); }

$filename = $a['original_name'];
$mime = $a['mime'] ?: 'application/octet-stream';
$size = (int)$a['size_bytes'];

header('Content-Type: '.$mime);
header('Content-Length: '.$size);
header('X-Content-Type-Options: nosniff');
header('Content-Disposition: attachment; filename="'.str_replace('"','', $filename).'"');

readfile($abs);
exit;
