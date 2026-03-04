<?php
require __DIR__.'/_oc_bootstrap.php';

$id = (int)($_GET['id'] ?? 0);
if(!$id){ http_response_code(400); echo "ID inválido"; require __DIR__.'/../../inc/footer.php'; exit; }

// Carrega ocorrência + nomes
$st = $pdo->prepare("SELECT o.*, u.name AS created_name, u2.name AS closed_name
                     FROM occurrences o
                     LEFT JOIN users u ON u.id = o.created_by
                     LEFT JOIN users u2 ON u2.id = o.closed_by
                     WHERE o.id=?");
$st->execute([$id]);
$occ = $st->fetch(PDO::FETCH_ASSOC);
if(!$occ){ http_response_code(404); echo "Ocorrência não encontrada"; require __DIR__.'/../../inc/footer.php'; exit; }

$can_edit = occ_is_manager() || ((int)$occ['created_by'] === $uid && !in_array($occ['status'], ['closed','canceled'], true));
$can_close = occ_is_manager() && !in_array($occ['status'], ['closed','canceled'], true);
$can_reopen = occ_is_manager() && in_array($occ['status'], ['closed','canceled'], true);

$err = null;
$ok = null;

// Ações de status
if($_SERVER['REQUEST_METHOD'] === 'POST'){
  occ_csrf_check();
  $action = $_POST['action'] ?? '';

  try{
    if($action === 'close'){
      if(!$can_close) throw new Exception('Sem permissão para encerrar.');
      $pdo->prepare("UPDATE occurrences SET status='closed', closed_by=?, closed_at=NOW() WHERE id=?")
          ->execute([$uid, $id]);
      oc_audit($pdo, $id, 'status_changed', ['to'=>'closed'], $uid);
      header("Location: view.php?id={$id}"); exit;
    }

    if($action === 'cancel'){
      // permitir cancelar para gestor/supervisor, e opcionalmente para o criador
      if(!(occ_is_manager() || (int)$occ['created_by'] === $uid)) throw new Exception('Sem permissão para cancelar.');
      if(in_array($occ['status'], ['closed'], true)) throw new Exception('Ocorrência encerrada não pode ser cancelada.');
      $reason = trim($_POST['reason'] ?? '');
      $pdo->prepare("UPDATE occurrences SET status='canceled' WHERE id=?")
          ->execute([$id]);
      oc_audit($pdo, $id, 'status_changed', ['to'=>'canceled','reason'=>$reason], $uid);
      header("Location: view.php?id={$id}"); exit;
    }

    if($action === 'reopen'){
      if(!$can_reopen) throw new Exception('Sem permissão para reabrir.');
      $pdo->prepare("UPDATE occurrences SET status='registered', closed_by=NULL, closed_at=NULL WHERE id=?")
          ->execute([$id]);
      oc_audit($pdo, $id, 'status_changed', ['to'=>'registered'], $uid);
      header("Location: view.php?id={$id}"); exit;
    }
  } catch(Exception $e){
    $err = $e->getMessage();
  }
}

// Anexos
$stA = $pdo->prepare("SELECT a.*, u.name AS who
                      FROM occurrence_attachments a
                      LEFT JOIN users u ON u.id=a.created_by
                      WHERE a.occurrence_id=?
                      ORDER BY a.id DESC");
$stA->execute([$id]);
$attachments = $stA->fetchAll(PDO::FETCH_ASSOC);

// Auditoria
$stH = $pdo->prepare("SELECT x.*, u.name AS who
                      FROM occurrence_audit x
                      LEFT JOIN users u ON u.id=x.created_by
                      WHERE x.occurrence_id=?
                      ORDER BY x.id DESC
                      LIMIT 200");
$stH->execute([$id]);
$history = $stH->fetchAll(PDO::FETCH_ASSOC);

$agencies = [];
if(!empty($occ['agencies'])){
  $tmp = json_decode($occ['agencies'], true);
  if(is_array($tmp)) $agencies = $tmp;
}
?>

<div class="page-wrap">
  <div class="flex items-start justify-between gap-3 flex-wrap mb-4">
    <div>
      <div class="text-xs uppercase tracking-widest text-gray-400">Ocorrência</div>
      <h1 class="text-xl font-semibold flex items-center gap-2">
        <span class="font-mono text-orange-300"><?=h($occ['protocol'])?></span>
        <?=occ_badge($occ['status'])?>
      </h1>
      <div class="text-sm text-gray-400 mt-1">
        <?=h($occ['sector'])?> • <?=h(date('d/m/Y H:i', strtotime($occ['occurred_at'])))?>
        • Criada por: <?=h($occ['created_name'] ?? ('#'.$occ['created_by']))?>
        <?php if(!empty($occ['closed_at'])): ?>
          • Encerrada em: <?=h(date('d/m/Y H:i', strtotime($occ['closed_at'])))?>
          <?php if(!empty($occ['closed_name'])): ?> (<?=h($occ['closed_name'])?>)<?php endif; ?>
        <?php endif; ?>
      </div>
    </div>

    <div class="flex gap-2 flex-wrap">
      <a class="btn" href="index.php">Voltar</a>
      <a class="btn" href="print.php?id=<?=$id?>" target="_blank">Imprimir</a>
      <?php if($can_edit): ?>
        <a class="btn" href="edit.php?id=<?=$id?>">Editar</a>
      <?php endif; ?>

      <?php if($can_close): ?>
        <form method="post" class="inline">
          <input type="hidden" name="_csrf" value="<?=h(occ_csrf_token())?>">
          <input type="hidden" name="action" value="close">
          <button class="btn" type="submit">Encerrar</button>
        </form>
      <?php endif; ?>

      <?php if($can_reopen): ?>
        <form method="post" class="inline">
          <input type="hidden" name="_csrf" value="<?=h(occ_csrf_token())?>">
          <input type="hidden" name="action" value="reopen">
          <button class="btn" type="submit">Reabrir</button>
        </form>
      <?php endif; ?>

      <?php if(!in_array($occ['status'], ['canceled','closed'], true) && (occ_is_manager() || (int)$occ['created_by']===$uid)): ?>
        <button class="btn" onclick="document.getElementById('cancelBox').classList.toggle('hidden')">Cancelar</button>
      <?php endif; ?>
    </div>
  </div>

  <?php if($err): ?>
    <div class="card card-muted border border-red-800/60 text-red-200 mb-4"><?=h($err)?></div>
  <?php endif; ?>

  <div id="cancelBox" class="card card-muted border border-red-800/40 mb-4 hidden">
    <form method="post" class="grid gap-3">
      <input type="hidden" name="_csrf" value="<?=h(occ_csrf_token())?>">
      <input type="hidden" name="action" value="cancel">
      <div class="text-sm text-red-200">Cancelar ocorrência (registre o motivo):</div>
      <input name="reason" class="input" placeholder="Motivo do cancelamento (opcional)">
      <div class="flex gap-2 justify-end">
        <button type="button" class="btn" onclick="document.getElementById('cancelBox').classList.add('hidden')">Voltar</button>
        <button type="submit" class="btn">Confirmar cancelamento</button>
      </div>
    </form>
  </div>

  <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
    <section class="lg:col-span-2">
      <div class="card card-muted">
        <div class="flex items-center justify-between gap-2 mb-2">
          <h2 class="font-semibold">Detalhes</h2>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-3 text-sm">
          <div>
            <div class="text-gray-400 text-xs">Local / Endereço</div>
            <div><?=h($occ['location'])?></div>
          </div>
          <div>
            <div class="text-gray-400 text-xs">Ponto de referência</div>
            <div><?=h($occ['reference_point'] ?? '-')?></div>
          </div>
          <div>
            <div class="text-gray-400 text-xs">Natureza</div>
            <div><?=h($occ['nature'])?></div>
          </div>
          <div>
            <div class="text-gray-400 text-xs">Órgãos acionados</div>
            <div><?= $agencies ? h(implode(', ', $agencies)) : '-' ?></div>
          </div>

          <div>
            <div class="text-gray-400 text-xs">VTR/Moto</div>
            <div><?=h(($occ['vehicle_prefix'] ?? '-') . (!empty($occ['vehicle_plate']) ? ' • '.$occ['vehicle_plate'] : ''))?></div>
          </div>
          <div>
            <div class="text-gray-400 text-xs">KM</div>
            <div><?=h(($occ['km_start'] ?? '-') . ' → ' . ($occ['km_end'] ?? '-'))?></div>
          </div>
        </div>

        <div class="mt-4">
          <div class="text-gray-400 text-xs mb-1">Envolvidos</div>
          <div class="whitespace-pre-wrap"><?=h($occ['involved'] ?? '-')?></div>
        </div>

        <div class="mt-4">
          <div class="text-gray-400 text-xs mb-1">Descrição</div>
          <div class="whitespace-pre-wrap"><?=h($occ['description'])?></div>
        </div>

        <div class="mt-4">
          <div class="text-gray-400 text-xs mb-1">Providências tomadas</div>
          <div class="whitespace-pre-wrap"><?=h($occ['actions_taken'] ?? '-')?></div>
        </div>

        <div class="mt-4">
          <div class="text-gray-400 text-xs mb-1">Observações</div>
          <div class="whitespace-pre-wrap"><?=h($occ['observations'] ?? '-')?></div>
        </div>
      </div>

      <div class="card card-muted mt-4">
        <div class="flex items-center justify-between gap-2 mb-3">
          <h2 class="font-semibold">Anexos</h2>
        </div>

        <form method="post" action="upload.php" enctype="multipart/form-data" class="grid grid-cols-1 md:grid-cols-3 gap-3 mb-4">
          <input type="hidden" name="_csrf" value="<?=h(occ_csrf_token())?>">
          <input type="hidden" name="occurrence_id" value="<?=$id?>">
          <input type="file" name="file" class="input" required>
          <input name="note" class="input" placeholder="Observação do anexo (opcional)">
          <button class="btn" type="submit">Enviar anexo</button>
        </form>

        <div class="overflow-x-auto">
          <table class="table">
            <thead>
              <tr>
                <th>Arquivo</th>
                <th>Enviado por</th>
                <th>Data</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              <?php foreach($attachments as $a): ?>
                <tr class="tr">
                  <td>
                    <div class="font-mono text-sm"><?=h($a['original_name'])?></div>
                    <div class="text-xs text-gray-400"><?=h($a['mime'])?> • <?=h(number_format(((int)$a['size_bytes'])/1024, 1, ',', '.'))?> KB</div>
                  </td>
                  <td><?=h($a['who'] ?? ('#'.$a['created_by']))?></td>
                  <td><?=h(date('d/m/Y H:i', strtotime($a['created_at'])))?></td>
                  <td class="text-right">
                    <a class="btn" href="download.php?id=<?=$a['id']?>">Baixar</a>
                    <?php
                      $can_del = occ_is_manager() || (int)$a['created_by'] === $uid || (int)$occ['created_by'] === $uid;
                      $locked = in_array($occ['status'], ['closed'], true);
                    ?>
                    <?php if($can_del && !$locked): ?>
                      <form method="post" action="delete_attachment.php" class="inline" onsubmit="return confirm('Remover anexo?');">
                        <input type="hidden" name="_csrf" value="<?=h(occ_csrf_token())?>">
                        <input type="hidden" name="attachment_id" value="<?=$a['id']?>">
                        <input type="hidden" name="occurrence_id" value="<?=$id?>">
                        <button class="btn" type="submit">Remover</button>
                      </form>
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; ?>
              <?php if(!$attachments): ?>
                <tr><td colspan="4" class="text-gray-400">Nenhum anexo enviado.</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </section>

    <aside>
      <div class="card card-muted">
        <h2 class="font-semibold mb-3">Histórico</h2>
        <div class="space-y-3 text-sm">
          <?php foreach($history as $hrow): ?>
            <?php $meta = []; if(!empty($hrow['meta'])){ $m = json_decode($hrow['meta'], true); if(is_array($m)) $meta = $m; } ?>
            <div class="p-3 rounded-xl border border-gray-800 bg-white/5">
              <div class="text-xs text-gray-400"><?=h(date('d/m/Y H:i', strtotime($hrow['created_at'])))?> • <?=h($hrow['who'] ?? ('#'.$hrow['created_by']))?></div>
              <div class="font-semibold"><?=h($hrow['action'])?></div>
              <?php if($meta): ?>
                <div class="text-xs text-gray-300 mt-1 whitespace-pre-wrap"><?=h(json_encode($meta, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT))?></div>
              <?php endif; ?>
            </div>
          <?php endforeach; ?>
          <?php if(!$history): ?>
            <div class="text-gray-400">Sem histórico.</div>
          <?php endif; ?>
        </div>
      </div>
    </aside>
  </div>
</div>

<?php require __DIR__.'/../../inc/footer.php'; ?>
