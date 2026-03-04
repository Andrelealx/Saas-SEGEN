<?php
require __DIR__.'/_oc_bootstrap.php';

$id = (int)($_GET['id'] ?? 0);
if(!$id){ http_response_code(400); echo "ID inválido"; require __DIR__.'/../../inc/footer.php'; exit; }

$st = $pdo->prepare("SELECT * FROM occurrences WHERE id=?");
$st->execute([$id]);
$occ = $st->fetch(PDO::FETCH_ASSOC);
if(!$occ){ http_response_code(404); echo "Ocorrência não encontrada"; require __DIR__.'/../../inc/footer.php'; exit; }

$can_edit = occ_is_manager() || ((int)$occ['created_by'] === $uid && !in_array($occ['status'], ['closed','canceled'], true));
if(!$can_edit){ http_response_code(403); echo "Sem permissão para editar"; require __DIR__.'/../../inc/footer.php'; exit; }

$err = [];

if($_SERVER['REQUEST_METHOD']==='POST'){
  occ_csrf_check();

  $sector = trim($_POST['sector'] ?? $occ['sector']);
  $occurred_at = trim($_POST['occurred_at'] ?? '');
  $location = trim($_POST['location'] ?? '');
  $reference_point = trim($_POST['reference_point'] ?? '');
  $nature = trim($_POST['nature'] ?? '');
  $involved = trim($_POST['involved'] ?? '');
  $description = trim($_POST['description'] ?? '');
  $actions_taken = trim($_POST['actions_taken'] ?? '');
  $observations = trim($_POST['observations'] ?? '');
  $ag = $_POST['agencies'] ?? [];

  $vehicle_prefix = trim($_POST['vehicle_prefix'] ?? '');
  $vehicle_plate = trim($_POST['vehicle_plate'] ?? '');
  $km_start = trim($_POST['km_start'] ?? '');
  $km_end = trim($_POST['km_end'] ?? '');

  if($sector==='') $err[] = "Informe o setor.";
  if($occurred_at==='') $err[] = "Informe a data/hora.";
  if($location==='') $err[] = "Informe o local/endereço.";
  if($nature==='') $err[] = "Informe a natureza.";
  if($description==='') $err[] = "Informe a descrição.";

  if(!$err){
    $occurred_db = str_replace('T',' ', $occurred_at);
    if(strlen($occurred_db) === 16) $occurred_db .= ":00";

    $ag_json = oc_json_from_agencies(is_array($ag)?$ag:[]);

    $pdo->prepare("UPDATE occurrences SET
        sector=?, occurred_at=?, location=?, reference_point=?, nature=?, involved=?, agencies=?, description=?, actions_taken=?, observations=?,
        vehicle_prefix=?, vehicle_plate=?, km_start=?, km_end=?
      WHERE id=?")
      ->execute([
        strtoupper($sector), $occurred_db,
        $location,
        ($reference_point!==''?$reference_point:null),
        $nature,
        ($involved!==''?$involved:null),
        $ag_json,
        $description,
        ($actions_taken!==''?$actions_taken:null),
        ($observations!==''?$observations:null),
        ($vehicle_prefix!==''?$vehicle_prefix:null),
        ($vehicle_plate!==''?$vehicle_plate:null),
        ($km_start!==''?(int)$km_start:null),
        ($km_end!==''?(int)$km_end:null),
        $id
      ]);

    oc_audit($pdo, $id, 'updated', ['fields'=>'form'], $uid);
    header("Location: view.php?id={$id}");
    exit;
  }
}

// valores para o form
$sector = $_POST['sector'] ?? $occ['sector'];
$occurred_at = $_POST['occurred_at'] ?? date('Y-m-d\TH:i', strtotime($occ['occurred_at']));
$location = $_POST['location'] ?? $occ['location'];
$reference_point = $_POST['reference_point'] ?? ($occ['reference_point'] ?? '');
$nature = $_POST['nature'] ?? $occ['nature'];
$involved = $_POST['involved'] ?? ($occ['involved'] ?? '');
$description = $_POST['description'] ?? $occ['description'];
$actions_taken = $_POST['actions_taken'] ?? ($occ['actions_taken'] ?? '');
$observations = $_POST['observations'] ?? ($occ['observations'] ?? '');
$vehicle_prefix = $_POST['vehicle_prefix'] ?? ($occ['vehicle_prefix'] ?? '');
$vehicle_plate = $_POST['vehicle_plate'] ?? ($occ['vehicle_plate'] ?? '');
$km_start = $_POST['km_start'] ?? ($occ['km_start'] ?? '');
$km_end = $_POST['km_end'] ?? ($occ['km_end'] ?? '');

$ag = $_POST['agencies'] ?? [];
if(!$ag && !empty($occ['agencies'])){
  $tmp = json_decode($occ['agencies'], true);
  if(is_array($tmp)) $ag = $tmp;
}
?>

<div class="page-wrap">
  <div class="flex items-center justify-between gap-3 flex-wrap mb-4">
    <div>
      <div class="text-xs uppercase tracking-widest text-gray-400">Editar</div>
      <h1 class="text-xl font-semibold flex items-center gap-2">
        <span class="font-mono text-orange-300"><?=h($occ['protocol'])?></span>
        <?=occ_badge($occ['status'])?>
      </h1>
    </div>
    <div class="flex gap-2">
      <a class="btn" href="view.php?id=<?=$id?>">Voltar</a>
    </div>
  </div>

  <?php if($err): ?>
    <div class="card card-muted border border-red-800/60 text-red-200 mb-4"><?=h(implode(' ', $err))?></div>
  <?php endif; ?>

  <form method="post" class="card card-muted p-5 grid grid-cols-1 md:grid-cols-2 gap-4">
    <input type="hidden" name="_csrf" value="<?=h(occ_csrf_token())?>">

    <div>
      <label class="text-sm text-gray-300">Setor</label>
      <input name="sector" value="<?=h($sector)?>" class="input" required>
    </div>
    <div>
      <label class="text-sm text-gray-300">Data/Hora do fato</label>
      <input type="datetime-local" name="occurred_at" value="<?=h($occurred_at)?>" class="input" required>
    </div>

    <div class="md:col-span-2">
      <label class="text-sm text-gray-300">Local / Endereço</label>
      <input name="location" value="<?=h($location)?>" class="input" required>
    </div>

    <div class="md:col-span-2">
      <label class="text-sm text-gray-300">Ponto de referência</label>
      <input name="reference_point" value="<?=h($reference_point)?>" class="input">
    </div>

    <div>
      <label class="text-sm text-gray-300">Natureza</label>
      <input name="nature" value="<?=h($nature)?>" class="input" required>
    </div>

    <div>
      <label class="text-sm text-gray-300">Órgãos acionados</label>
      <div class="flex flex-wrap gap-3 text-sm mt-2">
        <?php foreach(['PM','Bombeiros','SAMU','PC','Defesa Civil','Outro'] as $opt): ?>
          <label class="inline-flex items-center gap-2">
            <input type="checkbox" name="agencies[]" value="<?=$opt?>" <?=in_array($opt,(array)$ag,true)?'checked':''?>>
            <span><?=$opt?></span>
          </label>
        <?php endforeach; ?>
      </div>
    </div>

    <div class="md:col-span-2">
      <label class="text-sm text-gray-300">Envolvidos (opcional)</label>
      <textarea name="involved" class="input" rows="2"><?=h($involved)?></textarea>
    </div>

    <div class="md:col-span-2">
      <label class="text-sm text-gray-300">Descrição</label>
      <textarea name="description" class="input" rows="6" required><?=h($description)?></textarea>
    </div>

    <div class="md:col-span-2">
      <label class="text-sm text-gray-300">Providências tomadas (opcional)</label>
      <textarea name="actions_taken" class="input" rows="3"><?=h($actions_taken)?></textarea>
    </div>

    <div class="md:col-span-2">
      <label class="text-sm text-gray-300">Observações (opcional)</label>
      <textarea name="observations" class="input" rows="2"><?=h($observations)?></textarea>
    </div>

    <div class="md:col-span-2 grid grid-cols-1 md:grid-cols-4 gap-3">
      <div>
        <label class="text-sm text-gray-300">VTR/Moto (prefixo)</label>
        <input name="vehicle_prefix" value="<?=h($vehicle_prefix)?>" class="input">
      </div>
      <div>
        <label class="text-sm text-gray-300">Placa</label>
        <input name="vehicle_plate" value="<?=h($vehicle_plate)?>" class="input">
      </div>
      <div>
        <label class="text-sm text-gray-300">KM início</label>
        <input name="km_start" value="<?=h($km_start)?>" class="input" inputmode="numeric">
      </div>
      <div>
        <label class="text-sm text-gray-300">KM fim</label>
        <input name="km_end" value="<?=h($km_end)?>" class="input" inputmode="numeric">
      </div>
    </div>

    <div class="md:col-span-2 flex justify-end gap-2">
      <button class="btn" type="submit">Salvar alterações</button>
    </div>
  </form>
</div>

<?php require __DIR__.'/../../inc/footer.php'; ?>
