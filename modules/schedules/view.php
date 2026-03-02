<?php
require __DIR__ . '/../../core/auth.php';
auth_require();
require __DIR__ . '/../../core/db.php';
require __DIR__ . '/../../core/utils.php';

$dayId = (int) ($_GET['day'] ?? 0);

// Buscar informações do dia
$day = $pdo->prepare("
  SELECT sd.*, 
         COALESCE(p.name, '—') AS post
  FROM schedule_days sd
  LEFT JOIN posts p ON p.id = sd.post_id
  WHERE sd.id = ?
");
$day->execute([$dayId]);
$d = $day->fetch();

if (!$d) {
    http_response_code(404);
    exit("Dia não encontrado");
}

// Buscar designações dos servidores
$st = $pdo->prepare("
  SELECT 
      e.name AS emp,
      s.code AS sh,
      s.code AS shift_label,
      s.start_time,
      s.end_time
  FROM shift_assignments sa
  JOIN employees e ON e.id = sa.employee_id
  JOIN shifts s ON s.id = sa.shift_id
  WHERE sa.schedule_day_id = ?
  ORDER BY s.start_time, e.name
");
$st->execute([$dayId]);
$rows = $st->fetchAll();

// Agrupar servidores por turno
$turnos = [];
foreach ($rows as $r) {
    $turnos[$r['sh']][] = $r;
}

require __DIR__ . '/../../inc/header.php';
?>

<style>
.turn-card {
    background: #101014;
    border: 1px solid #1f1f1f;
    border-radius: 12px;
    padding: 16px;
    margin-bottom: 14px;
}
.turn-title {
    font-size: 1rem;
    font-weight: 600;
    color: #f97316;
    display: flex;
    justify-content: space-between;
}
.time-block {
    font-size: 0.85rem;
    color: #bbb;
}
.agent-row {
    padding: 10px 6px;
    border-bottom: 1px solid #222;
}
.agent-row:last-child {
    border-bottom: none;
}
.empty-msg {
    text-align: center;
    padding: 20px;
    color: #888;
    font-style: italic;
}
</style>

<div class="flex items-center justify-between mb-6">
    <div>
        <h1 class="text-2xl font-bold flex items-center gap-3">
            <i data-lucide="calendar" class="w-6 h-6 text-orange-400"></i>
            Escala • <?= htmlspecialchars($d['ref_date']) ?> • <?= htmlspecialchars($d['post']) ?>
        </h1>

        <div class="text-gray-400 text-sm mt-1">
            Status: 
            <?php if ($d['published']): ?>
                <span class="text-green-400 font-semibold">Publicado</span>
            <?php else: ?>
                <span class="text-orange-400 font-semibold">Rascunho</span>
            <?php endif; ?>
        </div>
    </div>

    <div class="flex gap-3">
        <a href="index.php" class="btn btn-muted">
            <i data-lucide="arrow-left" class="h-4 w-4"></i> Voltar
        </a>
        <a href="print.php?day=<?= $dayId ?>" class="btn">
            <i data-lucide="printer" class="h-4 w-4"></i> Imprimir
        </a>
        <a href="builder.php?day=<?= $dayId ?>" class="btn">
            <i data-lucide="edit" class="h-4 w-4"></i> Editar
        </a>
    </div>
</div>

<div class="card p-4">

    <?php if (!$rows): ?>
        <div class="empty-msg">
            Nenhum agente designado nesta escala.
        </div>
    <?php endif; ?>

    <?php foreach ($turnos as $codigo => $lista): ?>
        <?php 
        $shiftName = $codigo; // usado como label
        $inicio = substr($lista[0]['start_time'], 0, 5);
        $fim    = substr($lista[0]['end_time'], 0, 5);
        ?>
        
        <div class="turn-card">
            <div class="turn-title">
                <span><?= htmlspecialchars($shiftName) ?></span>
                <span class="time-block"><?= $inicio ?> — <?= $fim ?></span>
            </div>

            <?php foreach ($lista as $ag): ?>
                <div class="agent-row">
                    <span class="font-medium"><?= htmlspecialchars($ag['emp']) ?></span>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endforeach; ?>

</div>

<?php require __DIR__ . '/../../inc/footer.php'; ?>
