<?php
require __DIR__.'/../../core/auth.php';
auth_require();
require __DIR__.'/../../core/db.php';

header('Content-Type: application/json');

$id = (int)($_POST['id'] ?? 0);

if (!$id) {
    echo json_encode(['ok'=>false, 'msg'=>'ID inválido']);
    exit;
}

try {
    // pegar estado atual
    $st = $pdo->prepare("SELECT published FROM schedule_days WHERE id=?");
    $st->execute([$id]);
    $row = $st->fetch();

    if (!$row) {
        echo json_encode(['ok'=>false, 'msg'=>'Escala não encontrada']);
        exit;
    }

    $new = $row['published'] ? 0 : 1;

    $up = $pdo->prepare("UPDATE schedule_days SET published=? WHERE id=?");
    $up->execute([$new, $id]);

    echo json_encode(['ok'=>true, 'new'=>$new]);

} catch (Throwable $e) {
    echo json_encode(['ok'=>false, 'msg'=>$e->getMessage()]);
    exit;
}
