<?php
require __DIR__.'/../../core/auth.php';
auth_require();
require __DIR__.'/../../core/db.php';
require __DIR__.'/../../core/utils.php';

date_default_timezone_set('America/Sao_Paulo');

$errors   = [];
$dayId    = (int)($_GET['day'] ?? 0);
$getDate  = $_GET['date'] ?? null;

/* ---------------- Helpers ---------------- */
if (!function_exists('h')) {
  function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
}
function hasColumn(PDO $pdo, string $table, string $col): bool {
  try{
    $st = $pdo->prepare("SHOW COLUMNS FROM `$table` LIKE ?");
    $st->execute([$col]);
    return (bool)$st->fetch(PDO::FETCH_ASSOC);
  } catch(Throwable $e){
    return false;
  }
}

$hasTplCol        = hasColumn($pdo, 'schedule_days', 'doc_template_id');
$hasDocNotesCol   = hasColumn($pdo, 'schedule_days', 'doc_notes');
$hasPostsDefault  = hasColumn($pdo, 'posts', 'default_doc_template_id');

/* ---------------- Templates (doc_templates) ---------------- */
$templates = [];
$templateIndex = []; // [id => row]
try {
  $templates = $pdo->query("
    SELECT id, code, title, scope, post_id, is_active
      FROM doc_templates
     WHERE is_active=1
     ORDER BY title ASC
  ")->fetchAll();
  foreach($templates as $t){
    $templateIndex[(int)$t['id']] = $t;
  }
} catch (Throwable $e) {
  // se ainda não existir a tabela ou algo der errado, só ignora
  $templates = [];
  $templateIndex = [];
}

// ---------------------------------------------------------
// POST – salvar escala
// ---------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $dayId     = (int)($_POST['day_id'] ?? 0);
    $ref_date  = $_POST['ref_date'] ?? '';
    $post_id   = ($_POST['post_id'] ?? '') !== '' ? (int)$_POST['post_id'] : null;
    $notes     = trim($_POST['notes'] ?? '');
    $published = isset($_POST['published']) ? 1 : 0;
    $assign    = $_POST['assign'] ?? [];

    // novos campos
    $doc_template_id = null;
    $doc_notes = null;

    if ($hasTplCol) {
      $doc_template_id = (int)($_POST['doc_template_id'] ?? 0);
      if ($doc_template_id <= 0) $doc_template_id = null;
    }
    if ($hasDocNotesCol) {
      $doc_notes = trim($_POST['doc_notes'] ?? '');
      if ($doc_notes === '') $doc_notes = null;
    }

    if (!$ref_date)       $errors[] = "Data é obrigatória.";
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $ref_date)) $errors[] = "Data inválida.";
    if (!empty($assign) && !is_array($assign)) $assign = [];

    // validação simples do template (se scope=POST, post_id precisa bater)
    if ($hasTplCol && $doc_template_id) {
      $t = $templateIndex[$doc_template_id] ?? null;
      if (!$t) {
        $errors[] = "Modelo de documento inválido.";
      } else {
        if (($t['scope'] ?? '') === 'POST') {
          $tplPost = (int)($t['post_id'] ?? 0);
          if (!$post_id || $tplPost !== (int)$post_id) {
            $errors[] = "O modelo selecionado não pertence ao posto escolhido.";
          }
        }
      }
    }

    if (!$errors) {
        try {
            $pdo->beginTransaction();

            // se não escolheu template e houver default por posto
            if ($hasTplCol && !$doc_template_id && $hasPostsDefault && $post_id) {
              try{
                $stDef = $pdo->prepare("SELECT default_doc_template_id FROM posts WHERE id=?");
                $stDef->execute([$post_id]);
                $defId = (int)$stDef->fetchColumn();
                if ($defId > 0) $doc_template_id = $defId;
              } catch(Throwable $e) {
                // ignora
              }
            }

            // Se já existe (edição)
            if ($dayId > 0) {

                $set = ["ref_date = ?", "post_id = ?", "notes = ?", "published = ?"];
                $vals = [$ref_date, $post_id, ($notes !== '' ? $notes : null), $published];

                if ($hasTplCol)      { $set[] = "doc_template_id = ?"; $vals[] = $doc_template_id; }
                if ($hasDocNotesCol) { $set[] = "doc_notes = ?";       $vals[] = $doc_notes; }

                $vals[] = $dayId;

                $sql = "UPDATE schedule_days SET ".implode(", ", $set)." WHERE id = ?";
                $st = $pdo->prepare($sql);
                $st->execute($vals);

            } else {
                // Novo dia
                $cols = ["ref_date", "post_id", "notes", "published"];
                $qs   = ["?", "?", "?", "?"];
                $vals = [$ref_date, $post_id, ($notes !== '' ? $notes : null), $published];

                if ($hasTplCol)      { $cols[]="doc_template_id"; $qs[]="?"; $vals[]=$doc_template_id; }
                if ($hasDocNotesCol) { $cols[]="doc_notes";       $qs[]="?"; $vals[]=$doc_notes; }

                $st = $pdo->prepare("
                    INSERT INTO schedule_days (".implode(",", $cols).")
                    VALUES (".implode(",", $qs).")
                ");
                $st->execute($vals);
                $dayId = (int)$pdo->lastInsertId();

                // fallback se por acaso não veio o lastInsertId (chave única, etc)
                if (!$dayId) {
                    if ($post_id) {
                        $q = $pdo->prepare("SELECT id FROM schedule_days WHERE ref_date=? AND post_id=? LIMIT 1");
                        $q->execute([$ref_date, $post_id]);
                    } else {
                        $q = $pdo->prepare("SELECT id FROM schedule_days WHERE ref_date=? AND post_id IS NULL LIMIT 1");
                        $q->execute([$ref_date]);
                    }
                    $dayId = (int)$q->fetchColumn();
                }
            }

            // Limpa designações antigas e grava de novo
            $pdo->prepare("DELETE FROM shift_assignments WHERE schedule_day_id=?")->execute([$dayId]);

            if (!empty($assign)) {
                $ins = $pdo->prepare("
                    INSERT INTO shift_assignments
                           (schedule_day_id, employee_id, shift_id, origin, created_by)
                    VALUES (?, ?, ?, 'ESCALA', ?)
                ");
                $uid = (int)($_SESSION['uid'] ?? 0);

                foreach ($assign as $employee_id => $shift_id) {
                    $shift_id = (int)$shift_id;
                    $employee_id = (int)$employee_id;
                    if ($employee_id && $shift_id) {
                        $ins->execute([$dayId, $employee_id, $shift_id, $uid]);
                    }
                }
            }

            $pdo->commit();
            set_flash("Escala salva para ".date('d/m/Y', strtotime($ref_date))."!");
            header("Location: /modules/schedules/index.php");
            exit;

        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $errors[] = "Erro ao salvar escala: ".$e->getMessage();
        }
    }
}

// ---------------------------------------------------------
// Carrega dados da escala (edição) ou valores padrão
// ---------------------------------------------------------
$current = [
    'ref_date'  => date('Y-m-d'),
    'post_id'   => null,
    'notes'     => '',
    'published' => 0,
    'doc_template_id' => null,
    'doc_notes' => '',
];

$currentAssign = []; // [employee_id => shift_id]

if ($dayId > 0) {
    $st = $pdo->prepare("SELECT * FROM schedule_days WHERE id=?");
    $st->execute([$dayId]);
    $row = $st->fetch();

    if ($row) {
        $current['ref_date']  = $row['ref_date'];
        $current['post_id']   = $row['post_id'];
        $current['notes']     = $row['notes'] ?? '';
        $current['published'] = (int)$row['published'];

        if ($hasTplCol && array_key_exists('doc_template_id', $row)) {
          $current['doc_template_id'] = $row['doc_template_id'] ? (int)$row['doc_template_id'] : null;
        }
        if ($hasDocNotesCol && array_key_exists('doc_notes', $row)) {
          $current['doc_notes'] = $row['doc_notes'] ?? '';
        }

        $st2 = $pdo->prepare("
            SELECT employee_id, shift_id
              FROM shift_assignments
             WHERE schedule_day_id = ?
        ");
        $st2->execute([$dayId]);
        foreach ($st2->fetchAll() as $a) {
            $currentAssign[(int)$a['employee_id']] = (int)$a['shift_id'];
        }
    }
} elseif ($getDate && preg_match('/^\d{4}-\d{2}-\d{2}$/', $getDate)) {
    $current['ref_date'] = $getDate;
}

// ---------------------------------------------------------
// Referenciais (postos, turnos, servidores)
// ---------------------------------------------------------
try {
  if ($hasPostsDefault) {
    $posts = $pdo->query("SELECT id, name, default_doc_template_id FROM posts ORDER BY name")->fetchAll();
  } else {
    $posts = $pdo->query("SELECT id, name FROM posts ORDER BY name")->fetchAll();
  }
} catch(Throwable $e){
  $posts = [];
}

$shifts = $pdo->query("
  SELECT id, code, start_time, end_time, hours
  FROM shifts
  WHERE hours IN (12.00, 24.00)
  ORDER BY hours ASC, start_time ASC
")->fetchAll();

$emps  = $pdo->query("SELECT id, name FROM employees WHERE status='ATIVO' ORDER BY name")->fetchAll();

require __DIR__.'/../../inc/header.php';
?>

<style>
  .builder-wrapper { max-width: 1280px; margin: 0 auto; }
  .emp-card {
    background: rgba(15,23,42,0.85);
    border-radius: 0.75rem;
    border: 1px solid rgba(30,64,175,0.35);
    padding: 0.75rem 0.9rem;
    display: flex;
    flex-direction: column;
    gap: 0.35rem;
    transition: background 0.15s, border-color 0.15s, transform 0.08s;
  }
  .emp-card:hover {
    background: rgba(15,23,42,1);
    border-color: rgba(249,115,22,0.8);
    transform: translateY(-1px);
  }
  .emp-name {
    font-size: 0.9rem;
    font-weight: 500;
    color: #e5e7eb;
    display: flex;
    align-items: center;
    gap: 0.4rem;
  }
  .emp-name span.icon {
    width: 20px; height: 20px;
    border-radius: 9999px;
    background: radial-gradient(circle at 30% 30%, #1f2937, #020617);
    border: 1px solid rgba(148,163,184,0.4);
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 10px;
    color: #9ca3af;
  }
  .emp-select { font-size: 0.78rem; }
  .chip-status {
    font-size: 0.7rem;
    padding: 0.15rem 0.55rem;
    border-radius: 9999px;
    border-width: 1px;
    border-style: solid;
    letter-spacing: .12em;
    text-transform: uppercase;
  }
  .chip-rascunho {
    background: rgba(234,179,8,0.12);
    border-color: rgba(250,204,21,0.5);
    color: #facc15;
  }
  .chip-publicado {
    background: rgba(22,163,74,0.15);
    border-color: rgba(34,197,94,0.7);
    color: #4ade80;
  }
  .hint-box {
    border: 1px solid rgba(148,163,184,.2);
    background: rgba(2,6,23,.55);
    border-radius: 0.75rem;
    padding: 0.75rem 0.9rem;
    color: #cbd5e1;
    font-size: 0.82rem;
  }
</style>

<div class="builder-wrapper">

  <!-- Cabeçalho -->
  <div class="flex items-start justify-between mb-6 gap-4">
    <div>
      <div class="text-xs uppercase tracking-[0.28em] text-slate-500 mb-1">
        Gerar nova escala
      </div>
      <h1 class="text-2xl md:text-3xl font-bold text-slate-50 flex items-center gap-2">
        <i data-lucide="calendar-plus" class="w-7 h-7 text-orange-400"></i>
        <?= $dayId ? 'Edição de Escala' : 'Modo criação de nova escala' ?>
      </h1>
      <p class="text-xs text-slate-500 mt-1 max-w-xl">
        Defina a data, o posto e os turnos de cada servidor de forma rápida, organizada e visual.
      </p>
    </div>

    <div class="flex flex-col items-end gap-2">
      <div>
        <span class="chip-status <?= $current['published'] ? 'chip-publicado' : 'chip-rascunho' ?>">
          <?= $current['published'] ? 'Publicado' : 'Rascunho' ?>
        </span>
      </div>
      <a href="index.php" class="inline-flex items-center gap-2 text-sm text-slate-200 bg-slate-800/80 hover:bg-slate-700 px-3 py-2 rounded-lg border border-slate-700">
        <i data-lucide="list" class="w-4 h-4"></i>
        Listar escalas
      </a>
    </div>
  </div>

  <?php if ($errors): ?>
    <div class="card border border-red-700 bg-red-900/20 text-red-200 mb-4 p-4 text-sm">
      <div class="font-semibold mb-1">Não foi possível salvar a escala:</div>
      <ul class="list-disc ml-5 space-y-1">
        <?php foreach($errors as $e): ?><li><?=h($e)?></li><?php endforeach; ?>
      </ul>
    </div>
  <?php endif; ?>

  <?php flash(); ?>

  <!-- FORM PRINCIPAL -->
  <div class="bg-slate-950/70 border border-slate-800 rounded-2xl p-5 md:p-6 shadow-[0_20px_60px_rgba(15,23,42,0.9)] mb-10">

    <form method="post" class="grid gap-6" id="builderForm">
      <input type="hidden" name="day_id" value="<?=$dayId?>">

      <!-- Bloco: Dados gerais -->
      <section class="grid md:grid-cols-2 gap-5">
        <div class="space-y-3">
          <label class="block text-sm font-medium text-slate-200">Data</label>
          <input type="date"
                 name="ref_date"
                 class="input bg-slate-900/80 border-slate-700 text-sm"
                 required
                 value="<?=h($current['ref_date'])?>">
        </div>

        <div class="space-y-3">
          <label class="block text-sm font-medium text-slate-200">Posto</label>
          <select name="post_id" id="postSelect"
                  class="select bg-slate-900/80 border-slate-700 text-sm">
            <option value="">—</option>
            <?php foreach ($posts as $p): ?>
              <option value="<?=$p['id']?>"
                data-defaulttpl="<?= $hasPostsDefault ? (int)($p['default_doc_template_id'] ?? 0) : 0 ?>"
                <?=$current['post_id']==$p['id']?'selected':''?>>
                <?=h($p['name'])?>
              </option>
            <?php endforeach; ?>
          </select>

          <label class="inline-flex items-center gap-2 text-xs text-slate-300 mt-1">
            <input type="checkbox" name="published" value="1"
                   <?=$current['published'] ? 'checked' : ''?>>
            Escala publicada (visível no modo TV)
          </label>
        </div>
      </section>

      <?php if (!$hasTplCol || !$hasDocNotesCol): ?>
        <div class="hint-box">
          <div class="font-semibold text-slate-100 mb-1">Ativar Rotina / Modelos no dia</div>
          <div class="text-slate-300">
            Para o módulo salvar o <b>modelo da rotina</b> e as <b>notas da rotina</b>, rode o SQL do passo 1 (colunas <code>doc_template_id</code> e <code>doc_notes</code>) no banco.
          </div>
        </div>
      <?php endif; ?>

      <?php if ($hasTplCol): ?>
      <!-- Modelo do documento -->
      <section class="grid md:grid-cols-2 gap-5">
        <div class="space-y-2">
          <label class="block text-sm font-medium text-slate-200">
            Modelo do documento (Rotina / Ordem de Missão)
          </label>
          <select name="doc_template_id" id="tplSelect"
                  class="select bg-slate-900/80 border-slate-700 text-sm">
            <option value="">— (automático / nenhum) —</option>
            <?php foreach($templates as $t):
              $tid = (int)$t['id'];
              $scope = $t['scope'] ?? 'GLOBAL';
              $tpost = (int)($t['post_id'] ?? 0);
            ?>
              <option value="<?=$tid?>"
                data-scope="<?=h($scope)?>"
                data-post="<?=$tpost?>"
                <?=$current['doc_template_id']===$tid?'selected':''?>>
                <?=h($t['title'])?><?= $scope==='POST' ? ' • (por posto)' : ' • (global)' ?>
              </option>
            <?php endforeach; ?>
          </select>
          <p class="text-[11px] text-slate-500">
            Dica: se você cadastrar um “modelo padrão” por posto (coluna opcional em <code>posts</code>), ele preenche sozinho.
          </p>
        </div>

        <div class="space-y-2">
          <label class="block text-sm font-medium text-slate-200">Observações gerais (escala)</label>
          <textarea name="notes"
                    rows="3"
                    class="input bg-slate-900/80 border-slate-700 text-sm"
                    placeholder="Ex: plantão reforçado, operação especial, observações de comando..."><?=h($current['notes'])?></textarea>
        </div>
      </section>
      <?php else: ?>
      <!-- Observações (se ainda não tiver doc_template_id no banco) -->
      <section>
        <label class="block text-sm font-medium text-slate-200 mb-1">
          Observações gerais
        </label>
        <textarea name="notes"
                  rows="3"
                  class="input bg-slate-900/80 border-slate-700 text-sm"
                  placeholder="Ex: plantão reforçado, operação especial, observações de comando..."><?=h($current['notes'])?></textarea>
      </section>
      <?php endif; ?>

      <?php if ($hasDocNotesCol): ?>
      <!-- Notas para o documento -->
      <section>
        <label class="block text-sm font-medium text-slate-200 mb-1">
          Observações para a Rotina (vai aparecer no documento)
        </label>
        <textarea name="doc_notes"
                  rows="3"
                  class="input bg-slate-900/80 border-slate-700 text-sm"
                  placeholder="Ex: orientações específicas, checklists, determinação do comandante, ocorrências relevantes..."><?=h($current['doc_notes'])?></textarea>
      </section>
      <?php endif; ?>

      <!-- Barra de ferramentas de designação -->
      <section class="bg-slate-900/60 border border-slate-800 rounded-xl p-4 space-y-3">

        <div class="flex flex-wrap items-center justify-between gap-3">
          <div>
            <div class="flex items-center gap-2 text-sm font-medium text-slate-200">
              <i data-lucide="users" class="w-4 h-4 text-orange-400"></i>
              Designações de servidores
            </div>
            <p class="text-[11px] text-slate-500">
              Use o filtro para localizar rapidamente um servidor ou aplique um mesmo turno em massa.
            </p>
          </div>

          <div class="flex flex-wrap items-center gap-3 text-xs text-slate-400">
            <div class="flex items-center gap-1">
              <span>Total:</span>
              <span id="cntTotal" class="font-semibold text-slate-100"><?=count($emps)?></span>
            </div>
            <div class="flex items-center gap-1">
              <span>Escalados:</span>
              <span id="cntEscalados" class="font-semibold text-emerald-400">0</span>
            </div>
            <div class="flex items-center gap-1">
              <span>Sem turno:</span>
              <span id="cntSemTurno" class="font-semibold text-amber-400">0</span>
            </div>
          </div>
        </div>

        <div class="flex flex-col lg:flex-row gap-3 items-start lg:items-end">

          <!-- Filtro por nome -->
          <div class="flex-1 w-full">
            <label class="block text-xs font-medium text-slate-300 mb-1">
              Filtrar servidores
            </label>
            <div class="relative">
              <input type="text"
                     id="filterInput"
                     class="input bg-slate-950/80 border-slate-700 pl-8 text-sm w-full"
                     placeholder="Digite parte do nome para filtrar a lista..."
                     oninput="filterEmployees()">
              <i data-lucide="search" class="w-4 h-4 text-slate-500 absolute left-2.5 top-2.5"></i>
            </div>
          </div>

          <!-- Ações em massa -->
          <div class="flex flex-col sm:flex-row gap-2 items-stretch sm:items-end">
            <div>
              <label class="block text-xs font-medium text-slate-300 mb-1">
                Aplicar turno...
              </label>
              <select id="bulkShift"
                      class="select bg-slate-950/80 border-slate-700 text-xs min-w-[160px]">
                <option value="">(selecionar)</option>
                <?php foreach ($shifts as $s): ?>
                  <option value="<?=$s['id']?>">
                    <?=h($s['code'])?> • <?=substr($s['start_time'],0,5)?>–<?=substr($s['end_time'],0,5)?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="flex flex-col gap-1 text-xs text-slate-400">
              <label class="inline-flex items-center gap-1 mt-4 sm:mt-0">
                <input type="checkbox" id="bulkVisibleOnly" checked>
                Aplicar apenas nos visíveis
              </label>
              <div class="flex gap-2 mt-1">
                <button type="button"
                        onclick="applyBulkShift()"
                        class="btn px-3 py-1.5 text-xs bg-orange-500 hover:bg-orange-600 border-0">
                  Aplicar
                </button>
                <button type="button"
                        onclick="clearVisibleShifts()"
                        class="btn btn-muted px-3 py-1.5 text-xs">
                  Limpar turnos visíveis
                </button>
              </div>
            </div>
          </div>
        </div>

      </section>

      <!-- Grade de servidores -->
      <section>
        <div class="grid sm:grid-cols-2 xl:grid-cols-3 gap-3" id="empGrid">
          <?php foreach ($emps as $e):
              $eid   = (int)$e['id'];
              $selId = $currentAssign[$eid] ?? 0;
          ?>
            <div class="emp-card" data-name="<?=mb_strtolower($e['name'],'UTF-8')?>">
              <div class="emp-name">
                <span class="icon">
                  <i data-lucide="user" class="w-3 h-3"></i>
                </span>
                <?=h($e['name'])?>
              </div>
              <select name="assign[<?=$eid?>]"
                      class="select emp-select bg-slate-950/80 border-slate-700"
                      onchange="updateCounters()">
                <option value="">(sem)</option>
                <?php foreach ($shifts as $s): ?>
                  <option value="<?=$s['id']?>" <?=$selId==$s['id']?'selected':''?>>
                    <?=h($s['code'])?> • <?=substr($s['start_time'],0,5)?>–<?=substr($s['end_time'],0,5)?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
          <?php endforeach; ?>
        </div>
      </section>

      <!-- Botão salvar -->
      <section class="flex justify-end">
        <button class="btn bg-orange-500 hover:bg-orange-600 border-0 px-6 py-2.5 text-sm font-semibold flex items-center gap-2">
          <i data-lucide="save" class="w-4 h-4"></i>
          Salvar escala
        </button>
      </section>

    </form>
  </div>
</div>

<script>
function filterEmployees(){
  const term = document.getElementById('filterInput').value.toLowerCase();
  const cards = document.querySelectorAll('#empGrid .emp-card');
  cards.forEach(card => {
    const name = card.dataset.name || '';
    const visible = !term || name.includes(term);
    card.style.display = visible ? '' : 'none';
  });
}

function updateCounters(){
  const selects = document.querySelectorAll('#empGrid select');
  let total = 0, escalados = 0;
  selects.forEach(sel => {
    total++;
    if (sel.value) escalados++;
  });
  const sem = total - escalados;
  document.getElementById('cntTotal').textContent     = total;
  document.getElementById('cntEscalados').textContent = escalados;
  document.getElementById('cntSemTurno').textContent  = sem;
}

function applyBulkShift(){
  const shift = document.getElementById('bulkShift').value;
  if (!shift) { alert('Selecione um turno para aplicar.'); return; }
  const onlyVisible = document.getElementById('bulkVisibleOnly').checked;
  const cards = document.querySelectorAll('#empGrid .emp-card');
  cards.forEach(card => {
    if (onlyVisible && card.style.display === 'none') return;
    const sel = card.querySelector('select');
    if (sel) sel.value = shift;
  });
  updateCounters();
}

function clearVisibleShifts(){
  const onlyVisible = document.getElementById('bulkVisibleOnly').checked;
  const cards = document.querySelectorAll('#empGrid .emp-card');
  cards.forEach(card => {
    if (onlyVisible && card.style.display === 'none') return;
    const sel = card.querySelector('select');
    if (sel) sel.value = '';
  });
  updateCounters();
}

/* --------- Templates: filtra por posto + tenta preencher default --------- */
function refreshTemplateOptions(){
  const postSel = document.getElementById('postSelect');
  const tplSel  = document.getElementById('tplSelect');
  if (!postSel || !tplSel) return;

  const postId = parseInt(postSel.value || '0', 10) || 0;

  // filtrar opções
  Array.from(tplSel.options).forEach(opt => {
    if (!opt.value) return; // opção vazia sempre visível
    const scope = (opt.dataset.scope || 'GLOBAL');
    const tPost = parseInt(opt.dataset.post || '0', 10) || 0;

    const ok = (scope === 'GLOBAL') || (scope === 'POST' && postId && tPost === postId);
    opt.hidden = !ok;
  });

  // se a opção atual ficou inválida, limpa
  const cur = tplSel.value;
  if (cur) {
    const curOpt = tplSel.querySelector('option[value="'+cur+'"]');
    if (curOpt && curOpt.hidden) tplSel.value = '';
  }

  // tenta aplicar default do posto se existir (quando não há escolha manual)
  const selectedPostOpt = postSel.options[postSel.selectedIndex];
  const def = parseInt((selectedPostOpt && selectedPostOpt.dataset.defaulttpl) ? selectedPostOpt.dataset.defaulttpl : '0', 10) || 0;
  if (!tplSel.value && def) {
    const defOpt = tplSel.querySelector('option[value="'+def+'"]');
    if (defOpt && !defOpt.hidden) tplSel.value = String(def);
  }
}

document.addEventListener('DOMContentLoaded', () => {
  updateCounters();
  refreshTemplateOptions();
  const postSel = document.getElementById('postSelect');
  if (postSel) postSel.addEventListener('change', refreshTemplateOptions);
});
</script>

<?php require __DIR__.'/../../inc/footer.php'; ?>
