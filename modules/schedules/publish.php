<?php
require __DIR__.'/../../core/auth.php';
auth_require();
require __DIR__.'/../../core/db.php';
require __DIR__.'/../../core/utils.php';

$day = (int)($_GET['day'] ?? 0);
$action = $_GET['a'] ?? '';

if (!$day) {
    set_flash("ID inválido", "error");
    header("Location: index.php");
    exit;
}

if (!in_array($action, ['publish','unpublish'])) {
    set_flash("Ação inválida", "error");
    header("Location: index.php");
    exit;
}

try {
    $published = ($action === 'publish') ? 1 : 0;

    $st = $pdo->prepare("UPDATE schedule_days SET published=? WHERE id=?");
    $st->execute([$published, $day]);

    if ($published) {
        set_flash("Escala publicada com sucesso!");
    } else {
        set_flash("Escala marcada como rascunho.");
    }

} catch (Throwable $e) {
    set_flash("Erro: ".$e->getMessage(), "error");
}

header("Location: index.php");
exit;
