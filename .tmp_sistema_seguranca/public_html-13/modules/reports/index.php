<?php
require __DIR__.'/../../core/auth.php'; 
auth_require();
require __DIR__.'/../../core/db.php';
require __DIR__.'/../../core/utils.php';

$ini_sug = date('Y-m-01');
$fim_sug = date('Y-m-t');

require __DIR__.'/../../inc/header.php';
?>

<div class="flex items-center justify-between mb-6">
  <div>
    <h1 class="text-xl font-semibold">Relatórios do Sistema</h1>
    <p class="text-sm text-gray-400">Gere relatórios completos por período, posto, servidor ou tipo de operação.</p>
  </div>
  
  <a href="/modules/reports/daily.php" class="btn bg-orange-600 hover:bg-orange-700">
    📄 Relatório Diário
  </a>
</div>

<?php flash(); ?>

<div class="grid md:grid-cols-3 gap-4 mb-6">
  
  <!-- Card SDR -->
  <div class="card p-4 bg-gray-900/60">
    <h2 class="font-semibold text-lg mb-2">📌 Relatórios de SDR</h2>
    <p class="text-sm text-gray-400 mb-4">
      Acompanhe solicitações por período, por servidor e status.
    </p>
    <ul class="text-sm text-gray-300 space-y-1 mb-4">
      <li>• SDR por período</li>
      <li>• SDR por servidor</li>
      <li>• SDR pendentes</li>
    </ul>
    <div>
      <span class="text-xs text-gray-500">Utilize o formulário abaixo para exportar.</span>
    </div>
  </div>

  <!-- Card Escalas -->
  <div class="card p-4 bg-gray-900/60">
    <h2 class="font-semibold text-lg mb-2">📆 Relatórios de Escalas</h2>
    <p class="text-sm text-gray-400 mb-4">
      Obtenha designações, postos, escalas publicadas e distribuição por servidor.
    </p>
    <ul class="text-sm text-gray-300 space-y-1 mb-4">
      <li>• Designações por período</li>
      <li>• Escalas publicadas</li>
      <li>• Escalas por posto</li>
    </ul>
    <span class="text-xs text-gray-500">Filtre por posto ou servidor no formulário abaixo.</span>
  </div>

  <!-- Card Servidores -->
  <div class="card p-4 bg-gray-900/60">
    <h2 class="font-semibold text-lg mb-2">👮 Servidores</h2>
    <p class="text-sm text-gray-400 mb-4">
      Gere relatórios completos de servidores, status, lotação e atividade.
    </p>
    <ul class="text-sm text-gray-300 space-y-1 mb-4">
      <li>• Servidores ativos</li>
      <li>• Férias / afastamentos</li>
      <li>• Lotação e setores</li>
    </ul>
    <span class="text-xs text-gray-500">Relatórios específicos serão adicionados futuramente.</span>
  </div>

</div>

<!-- FORMULÁRIO DE EXPORTAÇÃO -->
<div class="card max-w-4xl p-6">
  <h2 class="text-lg font-semibold mb-3">📤 Gerar Relatório CSV</h2>

  <p class="text-sm text-gray-400 mb-4">
    Escolha o período, tipo de relatório e filtros opcionais para exportar os dados.
  </p>

  <form class="grid gap-4" action="export_csv.php" method="get">

    <div class="grid md:grid-cols-3 gap-3">
      <div>
        <label class="text-sm">De</label>
        <input class="input" type="date" name="ini" required 
               value="<?=htmlspecialchars($_GET['ini'] ?? $ini_sug)?>">
      </div>

      <div>
        <label class="text-sm">Até</label>
        <input class="input" type="date" name="fim" required 
               value="<?=htmlspecialchars($_GET['fim'] ?? $fim_sug)?>">
      </div>

      <div>
        <label class="text-sm">Tipo de Relatório</label>
        <select class="select" name="tipo">
          <option value="sdr"   <?= (($_GET['tipo'] ?? '')==='sdr')?'selected':'' ?>>SDR por período</option>
          <option value="escala"<?= (($_GET['tipo'] ?? '')==='escala')?'selected':'' ?>>Escalas / designações</option>
        </select>
      </div>
    </div>

    <div class="grid md:grid-cols-2 gap-3">

      <div>
        <label class="text-sm">Posto (opcional)</label>
        <select class="select" name="post_id">
          <option value="">Todos os postos</option>
          <?php
          try {
            $posts = $pdo->query("SELECT id,name FROM posts ORDER BY name")->fetchAll();
            foreach($posts as $p){
              $sel = (($_GET['post_id'] ?? '') == $p['id']) ? 'selected' : '';
              echo '<option value="'.$p['id'].'" '.$sel.'>'.htmlspecialchars($p['name']).'</option>';
            }
          } catch(Throwable $e) {}
          ?>
        </select>
      </div>

      <div>
        <label class="text-sm">Servidor (opcional)</label>
        <select class="select" name="employee_id">
          <option value="">Todos os servidores</option>
          <?php
          try {
            $emps = $pdo->query("SELECT id,name FROM employees ORDER BY name")->fetchAll();
            foreach($emps as $e){
              $sel = (($_GET['employee_id'] ?? '') == $e['id']) ? 'selected' : '';
              echo '<option value="'.$e['id'].'" '.$sel.'>'.htmlspecialchars($e['name']).'</option>';
            }
          } catch(Throwable $e) {}
          ?>
        </select>
      </div>
    </div>

    <div class="mt-4">
      <button class="btn w-full">
        <i data-lucide="download" class="h-4 w-4 mr-2"></i>
        Exportar CSV
      </button>
    </div>

  </form>
</div>

<!-- DICAS -->
<div class="card max-w-4xl mt-4 p-4 text-sm text-gray-300">
  <h3 class="font-semibold mb-2">💡 Dicas de uso</h3>
  <ul class="list-disc ml-5 space-y-1">
    <li>O relatório CSV é gerado instantaneamente e baixado no navegador.</li>
    <li>Prefira selecionar períodos menores para acelerar a exportação.</li>
    <li>Filtros de Posto e Servidor deixam o relatório mais preciso.</li>
    <li>Caso apareça erro 500, verifique se as tabelas necessárias existem no banco:</li>
  </ul>

  <code class="block bg-gray-800 text-gray-200 p-2 rounded mt-2 text-xs">
    overtime_requests, schedule_days, shift_assignments, shifts, employees, posts
  </code>
</div>

<?php require __DIR__.'/../../inc/footer.php'; ?>
