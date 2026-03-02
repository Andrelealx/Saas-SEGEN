<?php
require __DIR__.'/../../core/auth.php';
auth_require();
require __DIR__.'/../../core/db.php';
require __DIR__.'/../../core/utils.php';
require __DIR__.'/../../inc/header.php';
require __DIR__.'/../../core/auth.php'; auth_require();
require __DIR__.'/../../core/db.php';
require __DIR__.'/../../core/rbac.php';

rbac_require('employees.view');


$id = (int)($_GET['id'] ?? 0);
if(!$id){ http_response_code(400); exit('ID inválido'); }

$st = $pdo->prepare("SELECT e.*, d.name AS dept, p.name AS pos
                     FROM employees e
                     LEFT JOIN departments d ON d.id = e.department_id
                     LEFT JOIN positions p ON p.id = e.position_id
                     WHERE e.id=?");
$st->execute([$id]);
$e = $st->fetch(PDO::FETCH_ASSOC);
if(!$e){ http_response_code(404); exit('Funcionário não encontrado'); }

$photo = $e['photo'] ?: '/assets/img/avatar-placeholder.png';
?>
<div class="page-wrap">
  <div class="head flex items-center justify-between gap-3 flex-wrap mb-4">
    <div>
      <h1 class="text-xl font-semibold">Funcionário</h1>
      <div class="text-sm text-gray-400">Detalhes do cadastro</div>
    </div>
    <div class="flex gap-2 flex-wrap">
      <a class="btn-secondary px-4 py-2 rounded" href="index.php">Voltar</a>
      <a class="btn-primary px-4 py-2 rounded" href="edit.php?id=<?=$id?>">Editar (página)</a>
    </div>
  </div>

  <div class="card card-muted p-5">
    <div class="flex items-start gap-4 flex-wrap">
      <div class="w-24 h-24 rounded-full overflow-hidden border border-gray-700 bg-gray-800">
        <img src="<?=h($photo)?>" class="w-full h-full object-cover" alt="Foto">
      </div>

      <div class="flex-1 min-w-[260px]">
        <div class="text-2xl font-semibold"><?=h($e['name'] ?? '')?></div>
        <div class="text-sm text-gray-400 mt-1">
          Matrícula: <span class="font-mono"><?=h($e['registration'] ?? '—')?></span>
          • CPF: <span class="font-mono"><?=h($e['cpf'] ?? '—')?></span>
        </div>

        <div class="mt-3 grid grid-cols-1 md:grid-cols-2 gap-3">
          <div class="p-3 rounded-xl border border-gray-800 bg-black/20">
            <div class="text-xs text-gray-400">Contato</div>
            <div class="mt-1 text-sm">
              E-mail: <span class="text-gray-200"><?=h($e['email'] ?? '—')?></span><br>
              Telefone: <span class="text-gray-200"><?=h($e['phone'] ?? '—')?></span>
            </div>
          </div>

          <div class="p-3 rounded-xl border border-gray-800 bg-black/20">
            <div class="text-xs text-gray-400">Lotação</div>
            <div class="mt-1 text-sm">
              Departamento: <span class="text-gray-200"><?=h($e['dept'] ?? '—')?></span><br>
              Cargo: <span class="text-gray-200"><?=h($e['pos'] ?? '—')?></span>
            </div>
          </div>

          <div class="p-3 rounded-xl border border-gray-800 bg-black/20">
            <div class="text-xs text-gray-400">Status</div>
            <div class="mt-1 text-sm">
              <span class="badge <?=($e['status'] ?? '')==='ATIVO'?'green':((($e['status'] ?? '')==='FERIAS')?'orange':'red')?>"><?=h($e['status'] ?? '—')?></span>
            </div>
          </div>

          <div class="p-3 rounded-xl border border-gray-800 bg-black/20">
            <div class="text-xs text-gray-400">Última atualização</div>
            <div class="mt-1 text-sm">
              <?=h($e['updated_at'] ?? $e['created_at'] ?? '—')?>
            </div>
          </div>
        </div>

        <?php if(!empty($e['notes'])): ?>
          <div class="mt-4 p-3 rounded-xl border border-gray-800 bg-black/20">
            <div class="text-xs text-gray-400">Observações</div>
            <div class="mt-1 text-sm whitespace-pre-line"><?=h($e['notes'])?></div>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<?php require __DIR__.'/../../inc/footer.php'; ?>
