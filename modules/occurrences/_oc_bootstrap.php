<?php
require __DIR__.'/../../core/auth.php';
auth_require();
require __DIR__.'/../../core/db.php';
require __DIR__.'/_oc_helpers.php';
require __DIR__.'/../../inc/header.php';

date_default_timezone_set('America/Sao_Paulo');

$uid = occ_uid();
$role_id = occ_role_id();

function occ_badge(string $status): string {
  $map = [
    'draft' => ['Rascunho','orange'],
    'registered' => ['Registrada','orange'],
    'closed' => ['Encerrada','green'],
    'canceled' => ['Cancelada','red'],
  ];
  $s = $map[$status] ?? [$status,''];
  $label = h($s[0]);
  $class = $s[1] ? 'badge '.$s[1] : 'badge';
  return '<span class="'.$class.'">'.$label.'</span>';
}
