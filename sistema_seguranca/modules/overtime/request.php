<?php
require __DIR__.'/../../core/auth.php';
auth_require();
require __DIR__.'/../../core/db.php';
require __DIR__.'/../../core/utils.php';

if (session_status() !== PHP_SESSION_ACTIVE) session_start();

/* ===== RBAC seguro (não quebra se ainda não existir) ===== */
$rbacFile = __DIR__ . '/../../core/rbac.php';
if (is_file($rbacFile)) require_once $rbacFile;

/* ===== DEBUG (use só pra testar) =====
ini_set('display_errors',1);
ini_set('display_startup_errors',1);
error_reporting(E_ALL);
*/

/* ===== ID para edição (vem de GET ou POST) ===== */
$id = (int)($_GET['id'] ?? ($_POST['id'] ?? 0));
$isEdit = $id > 0;

/* ===== Permissões diferentes para criar/editar ===== */
if (function_exists('rbac_require')) {
  if ($isEdit) rbac_require('overtime.edit');
  else         rbac_require('overtime.create');
}

/* ===== CSRF ===== */
if (empty($_SESSION['_csrf'])) {
  $_SESSION['_csrf'] = bin2hex(random_bytes(16));
}
function csrf_check(): void {
  $t = $_POST['_csrf'] ?? '';
  if (!$t || !hash_equals($_SESSION['_csrf'] ?? '', $t)) {
    http_response_code(403);
    exit('CSRF inválido.');
  }
}

/* ===== Helper: calcula end_time (12h/24h) ===== */
function compute_end_time(string $refDate, string $startTime, int $hours): string {
  $tz = new DateTimeZone('America/Sao_Paulo');
  $start = DateTimeImmutable::createFromFormat('Y-m-d H:i', $refDate.' '.$startTime, $tz);
  if (!$start) return '';

  $hours = ($hours === 24) ? 24 : 12; // trava
  $end = $start->add(new DateInterval('PT'.$hours.'H'));
  return $end->format('H:i');
}

$errors = [];
$data = [
  'employee_id' => '',
  'ref_date'    => date('Y-m-d'),
  'start_time'  => '',
  'end_time'    => '',
  'hours'       => 12,
  'reason'      => ''
];

/* ===== Carrega lista de servidores ===== */
try {
  $emps = $pdo->query("SELECT id,name FROM employees WHERE status='ATIVO' ORDER BY name")
              ->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
  $errors[] = "Erro ao carregar servidores: ".$e->getMessage();
  $emps = [];
}

/* ===== Carregar SDR para edição ===== */
if ($isEdit && $_SERVER['REQUEST_METHOD'] !== 'POST') {
  try {
    $st = $pdo->prepare("SELECT * FROM overtime_requests WHERE id=?");
    $st->execute([$id]);
    $sdr = $st->fetch(PDO::FETCH_ASSOC);

    if (!$sdr) {
      http_response_code(404);
      exit('SDR não encontrada.');
    }

    $data['employee_id'] = (int)$sdr['employee_id'];
    $data['ref_date']    = (string)$sdr['ref_date'];
    $data['start_time']  = substr((string)$sdr['start_time'], 0, 5);
    $data['hours']       = (int)$sdr['hours'];
    $data['reason']      = (string)($sdr['reason'] ?? '');

    if (!in_array($data['hours'], [12, 24], true)) {
      $data['hours'] = 12;
      $errors[] = "Aviso: esta SDR antiga tinha horas fora do padrão. Ajustado para 12h (você pode trocar para 24h).";
    }

    if ($data['ref_date'] && $data['start_time']) {
      $data['end_time'] = compute_end_time($data['ref_date'], $data['start_time'], (int)$data['hours']);
    }

  } catch (Throwable $e) {
    $errors[] = "Erro ao carregar SDR: ".$e->getMessage();
  }
}

/* ===== SUBMISSÃO ===== */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  csrf_check();

  $data['employee_id'] = (int)($_POST['employee_id'] ?? 0);
  $data['ref_date']    = (string)($_POST['ref_date'] ?? '');
  $data['start_time']  = (string)($_POST['start_time'] ?? '');
  $data['hours']       = (int)($_POST['hours'] ?? 0);
  $data['reason']      = trim((string)($_POST['reason'] ?? ''));

  if (!$data['employee_id']) $errors[] = "Selecione o servidor.";
  if (!$data['ref_date'])    $errors[] = "Informe a data.";
  if (!$data['start_time'])  $errors[] = "Informe o horário de início.";

  if (!in_array($data['hours'], [12, 24], true)) {
    $errors[] = "Turno inválido. Selecione 12h ou 24h.";
  }

  if ($data['ref_date'] && $data['start_time'] && in_array($data['hours'], [12,24], true)) {
    $data['end_time'] = compute_end_time($data['ref_date'], $data['start_time'], (int)$data['hours']);
    if ($data['end_time'] === '') {
      $errors[] = "Não foi possível calcular o horário de término. Verifique data e início.";
    }
  } else {
    $data['end_time'] = '';
  }

  if (!$errors) {
    try {
      if ($isEdit) {
        $sql = "UPDATE overtime_requests
                SET employee_id=?, ref_date=?, start_time=?, end_time=?, hours=?, reason=?
                WHERE id=?";
        $p = $pdo->prepare($sql);
        $p->execute([
          $data['employee_id'],
          $data['ref_date'],
          $data['start_time'],
          $data['end_time'],
          $data['hours'],
          $data['reason'] ?: null,
          $id
        ]);
        set_flash("SDR atualizada com sucesso!");
      } else {
        // ✅ CORRIGIDO: 8 colunas => 8 placeholders
        $sql = "INSERT INTO overtime_requests
                (employee_id,ref_date,start_time,end_time,hours,reason,requested_by,status)
                VALUES (?,?,?,?,?,?,?,?)";
        $p = $pdo->prepare($sql);
        $p->execute([
          $data['employee_id'],
          $data['ref_date'],
          $data['start_time'],
          $data['end_time'],
          $data['hours'],
          $data['reason'] ?: null,
          (int)($_SESSION['uid'] ?? 0),
          'PENDENTE'
        ]);
        set_flash("SDR registrada com sucesso!");
      }

      header("Location: /modules/overtime/");
      exit;

    } catch (Throwable $e) {
      $errors[] = "Erro ao salvar: ".$e->getMessage();
    }
  }
}

require __DIR__.'/../../inc/header.php';
?>

<div class="flex items-center justify-between mb-6">
  <h1 class="text-2xl font-semibold">
    <?= $isEdit ? 'Editar Solicitação de SDR' : 'Nova Solicitação de SDR' ?>
  </h1>
  <a href="index.php" class="btn flex items-center gap-2">
    <i data-lucide="arrow-left" class="h-4 w-4"></i> Voltar
  </a>
</div>

<?php if ($errors): ?>
  <div class="card border border-red-700 bg-red-500/20 text-red-200 p-4 mb-4">
    <div class="font-semibold mb-2">Verifique os seguintes pontos:</div>
    <ul class="list-disc ml-5 space-y-1 text-sm">
      <?php foreach ($errors as $e): ?><li><?=h($e)?></li><?php endforeach; ?>
    </ul>
  </div>
<?php endif; ?>

<div class="card p-6 max-w-3xl mx-auto">
  <form method="post" class="grid gap-6" id="sdrForm">
    <input type="hidden" name="id" value="<?=$id?>">
    <input type="hidden" name="_csrf" value="<?=h($_SESSION['_csrf'])?>">

    <section>
      <h2 class="font-semibold text-lg mb-2">1. Servidor</h2>
      <select name="employee_id" class="select w-full" required>
        <option value="">Selecione o servidor…</option>
        <?php foreach($emps as $e): ?>
          <option value="<?=$e['id']?>" <?=$data['employee_id']==$e['id']?'selected':''?>>
            <?=h($e['name'])?>
          </option>
        <?php endforeach; ?>
      </select>
    </section>

    <section>
      <h2 class="font-semibold text-lg mb-2">2. Data e Início</h2>
      <div class="grid md:grid-cols-3 gap-3">
        <div>
          <label class="text-sm">Data</label>
          <input type="date" name="ref_date" class="input" required id="ref_date" value="<?=h($data['ref_date'])?>">
        </div>

        <div>
          <label class="text-sm">Início</label>
          <input type="time" name="start_time" class="input" required id="start_time" value="<?=h($data['start_time'])?>">
        </div>

        <div>
          <label class="text-sm">Fim (automático)</label>
          <input type="time" name="end_time" class="input opacity-80" id="end_time" value="<?=h($data['end_time'])?>" readonly>
          <div class="text-xs text-gray-400 mt-1">Calculado pelo turno (12h/24h).</div>
        </div>
      </div>
    </section>

    <section>
      <h2 class="font-semibold text-lg mb-2">3. Turno</h2>
      <div class="grid md:grid-cols-2 gap-3 items-end">
        <div>
          <label class="text-sm">Selecione 12h ou 24h</label>
          <select name="hours" id="hours" class="select w-full" required>
            <option value="12" <?=((int)$data['hours']===12)?'selected':''?>>12 horas</option>
            <option value="24" <?=((int)$data['hours']===24)?'selected':''?>>24 horas</option>
          </select>
        </div>

        <div>
          <label class="text-sm text-gray-300">Pré-visualização</label>
          <div class="p-2 rounded bg-gray-800 text-center text-lg font-semibold" id="previewHours">
            <?= number_format((float)$data['hours'],2,'.','').'h' ?>
          </div>
        </div>
      </div>
    </section>

    <section>
      <h2 class="font-semibold text-lg mb-2">4. Motivo</h2>
      <input name="reason" class="input" placeholder="Ex.: Operação noturna, reforço, atendimento…"
             value="<?=h($data['reason'])?>">
    </section>

    <div class="text-right">
      <button class="btn px-6">
        <i data-lucide="save" class="h-4 w-4"></i>
        <?= $isEdit ? 'Atualizar SDR' : 'Registrar SDR' ?>
      </button>
    </div>
  </form>
</div>

<script>
function pad2(n){ return String(n).padStart(2,'0'); }

function recalcByShift(){
  const dateEl  = document.getElementById('ref_date');
  const startEl = document.getElementById('start_time');
  const endEl   = document.getElementById('end_time');
  const hoursEl = document.getElementById('hours');
  const prevEl  = document.getElementById('previewHours');

  const date = dateEl.value;
  const s    = startEl.value;
  const hrs  = parseInt(hoursEl.value || '0', 10);

  if (!date || !s || (hrs !== 12 && hrs !== 24)) return;

  const start = new Date(date + "T" + s + ":00");
  const end   = new Date(start.getTime() + (hrs * 3600000));

  endEl.value = `${pad2(end.getHours())}:${pad2(end.getMinutes())}`;
  prevEl.textContent = hrs.toFixed(2) + "h";
}

document.getElementById("start_time").addEventListener("input", recalcByShift);
document.getElementById("ref_date").addEventListener("change", recalcByShift);
document.getElementById("hours").addEventListener("change", recalcByShift);
document.addEventListener("DOMContentLoaded", recalcByShift);
</script>

<?php require __DIR__.'/../../inc/footer.php'; ?>
