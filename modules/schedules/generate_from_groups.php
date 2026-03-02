<?php
require __DIR__.'/../../core/auth.php';
auth_require();
require __DIR__.'/../../core/db.php';

date_default_timezone_set('America/Sao_Paulo');
header('Content-Type: application/json; charset=utf-8');

function json_out($ok, $msg, $extra = []) {
  echo json_encode(array_merge(['ok'=>$ok,'msg'=>$msg], $extra));
  exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  json_out(false, 'Método inválido.');
}

$known = (string)($_SESSION['_csrf'] ?? '');
$sent = (string)($_POST['_csrf'] ?? '');
if ($known === '' || $sent === '' || !hash_equals($known, $sent)) {
  json_out(false, 'CSRF inválido.');
}

$month = $_POST['month'] ?? '';
if (!preg_match('/^\d{4}\-\d{2}$/', $month)) {
  json_out(false, 'Mês inválido.');
}

$firstDay = $month.'-01';
$lastDay  = date('Y-m-t', strtotime($firstDay));

/**
 * Shift padrão se a regra não tiver shift_id.
 * No seu dump:
 *  5 = 12H-D, 6 = 12H-N, 7 = 24H
 */
$DEFAULT_SHIFT_ID = 5;

/* tenta pegar user id para created_by */
$createdBy = null;
if (isset($_SESSION['uid'])) $createdBy = (int)$_SESSION['uid'];
elseif (isset($_SESSION['user']['id'])) $createdBy = (int)$_SESSION['user']['id'];
elseif (isset($_SESSION['id'])) $createdBy = (int)$_SESSION['id'];

try{
  $pdo->beginTransaction();

  // regras ativas
  $rules = $pdo->query("
    SELECT id, group_id, weekday, post_id, shift_id, doc_template_id, notes
    FROM group_fixed_rules
    WHERE is_active = 1
    ORDER BY group_id, weekday, id
  ")->fetchAll(PDO::FETCH_ASSOC);

  if(!$rules){
    $pdo->rollBack();
    json_out(false, 'Nenhuma regra ativa em group_fixed_rules. Configure os dias fixos nos agrupamentos.');
  }

  // membros
  $members = $pdo->query("
    SELECT group_id, employee_id
    FROM group_members
    ORDER BY group_id
  ")->fetchAll(PDO::FETCH_ASSOC);

  $membersByGroup = [];
  foreach($members as $m){
    $gid = (int)$m['group_id'];
    $membersByGroup[$gid][] = (int)$m['employee_id'];
  }

  // organiza regras por weekday
  $rulesByWeekday = [];
  foreach($rules as $r){
    $wd = (int)$r['weekday'];
    $rulesByWeekday[$wd][] = $r;
  }

  // prepared statements
  $stFindDay = $pdo->prepare("SELECT id FROM schedule_days WHERE ref_date=? AND post_id=? LIMIT 1");
  $stInsDay  = $pdo->prepare("
    INSERT INTO schedule_days (ref_date, post_id, notes, published, doc_template_id, doc_notes)
    VALUES (?,?,?,?,?,?)
  ");

  $stFindAssign = $pdo->prepare("
    SELECT id FROM shift_assignments
    WHERE schedule_day_id=? AND employee_id=? LIMIT 1
  ");
  $stInsAssign  = $pdo->prepare("
    INSERT INTO shift_assignments (schedule_day_id, employee_id, shift_id, origin, created_by)
    VALUES (?,?,?,'ESCALA',?)
  ");

  $createdDays = 0;
  $createdAssignments = 0;
  $rulesApplied = 0;

  $cur = new DateTime($firstDay);
  $end = new DateTime($lastDay);
  $end->setTime(0,0,0);

  while($cur <= $end){
    $date = $cur->format('Y-m-d');
    $wd   = (int)$cur->format('w');

    $rulesToday = $rulesByWeekday[$wd] ?? [];
    foreach($rulesToday as $rule){
      $gid = (int)$rule['group_id'];
      $postId = $rule['post_id'] !== null ? (int)$rule['post_id'] : 0;

      // regra sem posto: pula (evita duplicar schedule_days com post NULL)
      if ($postId <= 0) continue;

      $shiftId = $rule['shift_id'] !== null ? (int)$rule['shift_id'] : $DEFAULT_SHIFT_ID;
      if ($shiftId <= 0) $shiftId = $DEFAULT_SHIFT_ID;

      // schedule_day
      $stFindDay->execute([$date, $postId]);
      $dayId = (int)($stFindDay->fetchColumn() ?: 0);

      if(!$dayId){
        $notes = $rule['notes'] ? (string)$rule['notes'] : null;
        $docTemplateId = $rule['doc_template_id'] !== null ? (int)$rule['doc_template_id'] : null;

        // aqui você decide onde jogar texto: notes ou doc_notes.
        // vou colocar notes (curto) e deixar doc_notes vazio.
        $stInsDay->execute([$date, $postId, $notes, 0, $docTemplateId, null]);
        $dayId = (int)$pdo->lastInsertId();
        $createdDays++;
      }

      // membros do grupo => shift_assignments
      $agentIds = $membersByGroup[$gid] ?? [];
      foreach($agentIds as $empId){
        $stFindAssign->execute([$dayId, $empId]);
        if($stFindAssign->fetchColumn()) continue;

        $stInsAssign->execute([$dayId, $empId, $shiftId, $createdBy]);
        $createdAssignments++;
      }

      $rulesApplied++;
    }

    $cur->modify('+1 day');
  }

  $pdo->commit();

  json_out(true, 'Escalas do mês geradas pelos Agrupamentos (regras fixas).', [
    'created_days' => $createdDays,
    'created_assignments' => $createdAssignments,
    'rules_applied' => $rulesApplied
  ]);

}catch(Throwable $e){
  if($pdo->inTransaction()) $pdo->rollBack();
  json_out(false, 'Erro ao gerar: '.$e->getMessage());
}
