<?php
require __DIR__.'/_oc_bootstrap.php';

$err = [];

// valores (mantém post-back)
$sector = trim($_POST['sector'] ?? '');
$occurred_at = trim($_POST['occurred_at'] ?? date('Y-m-d\TH:i'));
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

if($_SERVER['REQUEST_METHOD']==='POST'){
  occ_csrf_check();

  if($sector==='') $err[]="Informe o setor.";
  if($location==='') $err[]="Informe o local/endereço.";
  if($nature==='') $err[]="Informe a natureza.";
  if($description==='') $err[]="Informe a descrição.";

  if(!$err){
    try{
      $protocol = oc_next_protocol($pdo, $sector, (int)date('Y'));
      $occurred_db = str_replace('T',' ', $occurred_at);
      if(strlen($occurred_db) === 16) $occurred_db .= ":00";

      $ag_json = oc_json_from_agencies(is_array($ag)?$ag:[]);

      $st = $pdo->prepare("INSERT INTO occurrences
        (protocol, sector, occurred_at, status, location, reference_point, nature, involved, agencies, description, actions_taken, observations,
         vehicle_prefix, vehicle_plate, km_start, km_end, created_by)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");

      $st->execute([
        $protocol, strtoupper($sector), $occurred_db, 'registered',
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
        $uid
      ]);

      $id = (int)$pdo->lastInsertId();
      oc_audit($pdo, $id, 'created', ['protocol'=>$protocol], $uid);

      header("Location: view.php?id=".$id);
      exit;
    }catch(Exception $e){
      $err[] = "Erro ao criar ocorrência: ".$e->getMessage();
    }
  }
}
?>
<div class="page-wrap">
  <div class="flex items-center justify-between gap-3 flex-wrap mb-4">
    <div>
      <div class="text-xs uppercase tracking-widest text-gray-400">Novo registro</div>
      <h1 class="text-xl font-semibold">Nova Ocorrência</h1>
    </div>
    <a href="index.php" class="btn">Voltar</a>
  </div>

  <?php if($err): ?>
    <div class="card card-muted border border-red-800/60 text-red-200 mb-4"><?=h(implode(" ", $err))?></div>
  <?php endif; ?>

  <form method="post" class="card card-muted p-5 grid grid-cols-1 md:grid-cols-2 gap-4">
    <input type="hidden" name="_csrf" value="<?=h(occ_csrf_token())?>">

    <div>
      <label class="text-sm text-gray-300">Setor</label>
      <input name="sector" value="<?=h($sector)?>" class="input" placeholder="BRAVO" required>
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
      <input name="reference_point" value="<?=h($reference_point)?>" class="input" placeholder="(opcional)">
    </div>

    <div>
      <label class="text-sm text-gray-300">Natureza</label>
      <input name="nature" value="<?=h($nature)?>" class="input" placeholder="Averiguação / Apoio / Sinistro..." required>
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
      <button class="btn" type="submit">Salvar</button>
    </div>
  </form>
</div>
<?php require __DIR__.'/../../inc/footer.php'; ?>
