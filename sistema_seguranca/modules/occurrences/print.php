<?php
require __DIR__.'/../../core/auth.php'; auth_require();
require __DIR__.'/../../core/db.php';
require __DIR__.'/_oc_helpers.php';

date_default_timezone_set('America/Sao_Paulo');

$id = (int)($_GET['id'] ?? 0);
if(!$id){ http_response_code(400); exit('ID inválido'); }

$st = $pdo->prepare("SELECT o.*, u.name AS created_name, u2.name AS closed_name
                     FROM occurrences o
                     LEFT JOIN users u ON u.id=o.created_by
                     LEFT JOIN users u2 ON u2.id=o.closed_by
                     WHERE o.id=?");
$st->execute([$id]);
$occ = $st->fetch(PDO::FETCH_ASSOC);
if(!$occ){ http_response_code(404); exit('Ocorrência não encontrada'); }

$agencies = [];
if(!empty($occ['agencies'])){
  $t = json_decode($occ['agencies'], true);
  if(is_array($t)) $agencies = $t;
}

function fdt($dt){ return $dt ? date('d/m/Y H:i', strtotime($dt)) : '-'; }

?><!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Ocorrência <?=h($occ['protocol'])?></title>
<link rel="stylesheet" href="/assets/css/app.css">
<style>
  body{ background:#0b0f1a; color:#e5e7eb; }
  .wrap{ max-width: 900px; margin: 20px auto; padding: 16px; }
  .hdr{ display:flex; justify-content:space-between; gap:12px; align-items:flex-start; flex-wrap:wrap; }
  .mono{ font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace; }
  .grid2{ display:grid; grid-template-columns: 1fr 1fr; gap:12px; }
  .k{ color:#9ca3af; font-size:.78rem; text-transform:uppercase; letter-spacing:.06em; }
  .v{ white-space:pre-wrap; }
  @media print{
    body{ background:#fff; color:#111; }
    .card{ box-shadow:none !important; border:1px solid #ddd !important; background:#fff !important; }
    .no-print{ display:none !important; }
  }
</style>
</head>
<body>
  <div class="wrap">
    <div class="hdr no-print" style="margin-bottom:12px;">
      <a class="btn" href="view.php?id=<?=$id?>">Voltar</a>
      <button class="btn" onclick="window.print()">Imprimir / Salvar PDF</button>
    </div>

    <div class="card">
      <div class="k">Livro de Ocorrências</div>
      <h1 class="text-xl" style="margin:6px 0 10px 0;">
        <span class="mono" style="color:#fdba74; font-weight:700;"><?=h($occ['protocol'])?></span>
      </h1>

      <div class="grid2">
        <div>
          <div class="k">Setor</div>
          <div class="v"><?=h($occ['sector'])?></div>
        </div>
        <div>
          <div class="k">Data/Hora do fato</div>
          <div class="v"><?=h(fdt($occ['occurred_at']))?></div>
        </div>
        <div>
          <div class="k">Status</div>
          <div class="v"><?=h($occ['status'])?></div>
        </div>
        <div>
          <div class="k">Criada por</div>
          <div class="v"><?=h($occ['created_name'] ?? ('#'.$occ['created_by']))?> • <?=h(fdt($occ['created_at']))?></div>
        </div>
      </div>

      <hr style="border:0;border-top:1px solid #1f2937;margin:14px 0;">

      <div class="grid2">
        <div>
          <div class="k">Local / Endereço</div>
          <div class="v"><?=h($occ['location'])?></div>
        </div>
        <div>
          <div class="k">Ponto de referência</div>
          <div class="v"><?=h($occ['reference_point'] ?? '-')?></div>
        </div>
        <div>
          <div class="k">Natureza</div>
          <div class="v"><?=h($occ['nature'])?></div>
        </div>
        <div>
          <div class="k">Órgãos acionados</div>
          <div class="v"><?= $agencies ? h(implode(', ', $agencies)) : '-' ?></div>
        </div>
        <div>
          <div class="k">VTR/Moto</div>
          <div class="v"><?=h(($occ['vehicle_prefix'] ?? '-') . (!empty($occ['vehicle_plate']) ? ' • '.$occ['vehicle_plate'] : ''))?></div>
        </div>
        <div>
          <div class="k">KM</div>
          <div class="v"><?=h(($occ['km_start'] ?? '-') . ' → ' . ($occ['km_end'] ?? '-'))?></div>
        </div>
      </div>

      <hr style="border:0;border-top:1px solid #1f2937;margin:14px 0;">

      <div>
        <div class="k">Envolvidos</div>
        <div class="v"><?=h($occ['involved'] ?? '-')?></div>
      </div>

      <div style="margin-top:12px;">
        <div class="k">Descrição</div>
        <div class="v"><?=h($occ['description'])?></div>
      </div>

      <div style="margin-top:12px;">
        <div class="k">Providências tomadas</div>
        <div class="v"><?=h($occ['actions_taken'] ?? '-')?></div>
      </div>

      <div style="margin-top:12px;">
        <div class="k">Observações</div>
        <div class="v"><?=h($occ['observations'] ?? '-')?></div>
      </div>

      <?php if(!empty($occ['closed_at'])): ?>
        <hr style="border:0;border-top:1px solid #1f2937;margin:14px 0;">
        <div>
          <div class="k">Encerramento</div>
          <div class="v"><?=h(fdt($occ['closed_at']))?> • <?=h($occ['closed_name'] ?? ('#'.$occ['closed_by']))?></div>
        </div>
      <?php endif; ?>

      <div style="margin-top:18px; display:grid; grid-template-columns: 1fr 1fr; gap:16px;">
        <div>
          <div class="k">Assinatura (operador)</div>
          <div style="height:48px;border-bottom:1px solid #9ca3af;"></div>
        </div>
        <div>
          <div class="k">Assinatura (supervisão)</div>
          <div style="height:48px;border-bottom:1px solid #9ca3af;"></div>
        </div>
      </div>
    </div>
  </div>
</body>
</html>
