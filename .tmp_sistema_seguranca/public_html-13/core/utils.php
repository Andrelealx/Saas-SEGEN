<?php
function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function flash(){ if(!empty($_SESSION['flash'])){ echo "<div class='mb-3 text-green-400'>".h($_SESSION['flash'])."</div>"; unset($_SESSION['flash']); } }
function set_flash($t){ $_SESSION['flash']=$t; }
function audit($pdo,$userId,$action,$entity,$entityId,$payload=[]){
  $q=$pdo->prepare("INSERT INTO audit_logs (user_id,action,entity,entity_id,payload) VALUES (?,?,?,?,JSON_OBJECT())");
  $q->execute([$userId,$action,$entity,$entityId]);
}
