<?php
require __DIR__.'/../../core/auth.php'; auth_require();
require __DIR__.'/../../core/db.php';
require __DIR__.'/../../core/utils.php';

$month = $_GET['month'] ?? date('Y-m');
$curFirst = $month . '-01';

$prevFirst = date('Y-m-01', strtotime('-1 month', strtotime($curFirst)));
$prevLast  = date('Y-m-t', strtotime($prevFirst));

try {
  // pega dias do mês anterior
  $st = $pdo->prepare("SELECT * FROM schedule_days WHERE ref_date BETWEEN ? AND ?");
  $st->execute([$prevFirst,$prevLast]);
  $prevDays = $st->fetchAll();

  if (!$prevDays) {
    set_flash("Nenhuma escala encontrada no mês anterior para copiar.","error");
    header("Location: index.php?month=$month"); exit;
  }

  $pdo->beginTransaction();

  foreach($prevDays as $d){
    $oldDate = $d['ref_date'];
    // diferença entre oldDate e prevFirst
    $diffDays = (strtotime($oldDate) - strtotime($prevFirst)) / 86400;
    $newDate = date('Y-m-d', strtotime("+$diffDays day", strtotime($curFirst)));

    // evita sobrescrever se já existir
    $check = $pdo->prepare("SELECT id FROM schedule_days WHERE ref_date=? AND post_id=?");
    $check->execute([$newDate, $d['post_id']]);
    if ($check->fetch()) continue;

    $ins = $pdo->prepare("
      INSERT INTO schedule_days (ref_date, post_id, published)
      VALUES (?,?,0)
    ");
    $ins->execute([$newDate, $d['post_id']]);

    $newDayId = (int)$pdo->lastInsertId();

    // copia designações se tabela existir
    try {
      $sa = $pdo->prepare("SELECT * FROM shift_assignments WHERE schedule_day_id=?");
      $sa->execute([$d['id']]);
      $assigns = $sa->fetchAll();

      foreach($assigns as $a){
        $insA = $pdo->prepare("
          INSERT INTO shift_assignments (schedule_day_id, employee_id, shift_id)
          VALUES (?,?,?)
        ");
        $insA->execute([$newDayId, $a['employee_id'], $a['shift_id']]);
      }
    } catch(Throwable $e){
      // se não existir a tabela, ignora
    }
  }

  $pdo->commit();
  set_flash("Escalas copiadas do mês anterior (como rascunho).");
} catch(Throwable $e){
  if ($pdo->inTransaction()) $pdo->rollBack();
  set_flash("Erro ao copiar escalas: ".$e->getMessage(),"error");
}

header("Location: index.php?month=$month");
exit;
