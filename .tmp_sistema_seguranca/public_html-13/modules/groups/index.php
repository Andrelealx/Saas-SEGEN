<?php
require __DIR__.'/../../core/auth.php'; auth_require();
require __DIR__.'/../../core/db.php';
require __DIR__.'/../../core/utils.php';

/* DEBUG (desativar em produção)
ini_set('display_errors',1); ini_set('display_startup_errors',1); error_reporting(E_ALL);
*/

$groups = [];
$err = null;

try {
  $groups = $pdo->query("SELECT id, name, description FROM groups ORDER BY name")->fetchAll();
} catch (Throwable $e) {
  $err = "Erro ao carregar agrupamentos: ".$e->getMessage();
}

require __DIR__.'/../../inc/header.php';
?>

<div class="flex items-center justify-between mb-6">
  <div>
      <h1 class="text-2xl font-bold">Agrupamentos</h1>
      <p class="text-gray-400 text-sm">Gerencie conjuntos de setores, equipes ou categorias internas.</p>
  </div>
  <a href="manage.php" class="btn"><i data-lucide="plus" class="w-4 h-4"></i> Novo</a>
</div>

<!-- 🔍 Barra de busca -->
<div class="mb-6">
    <input 
        id="searchInput"
        type="text"
        placeholder="Buscar agrupamento..."
        class="input w-full"
        onkeyup="filterCards()"
    >
</div>

<!-- 📊 Indicador rápido -->
<div class="card p-4 mb-6 text-center border border-white/5">
    <span class="text-3xl font-bold"><?=count($groups)?></span>
    <p class="text-sm text-gray-400">Agrupamentos cadastrados</p>
</div>

<?php if ($err): ?>
    <div class="card border border-red-800 bg-red-500/10 text-red-200 mb-4"><?=h($err)?></div>
<?php endif; ?>

<?php if (!$groups): ?>
  <div class="card text-gray-400 p-4">
      Nenhum agrupamento cadastrado. 
      <a href="manage.php" class="text-orange-300 hover:underline">Criar agora →</a>
  </div>
<?php else: ?>
  <div id="groupList" class="grid md:grid-cols-2 lg:grid-cols-3 gap-4">

    <?php foreach ($groups as $g): ?>
    <div class="group-card card p-4 transition hover:scale-[1.02] hover:border-orange-400/30 border border-white/5">
        <div class="flex items-center justify-between mb-2">
            <h2 class="font-semibold text-lg"><?=h($g['name'])?></h2>
            <a href="manage.php?id=<?=$g['id']?>" class="text-orange-300 hover:underline text-sm">Editar</a>
        </div>
        <p class="text-gray-400 text-sm mb-2"><?=h($g['description'])?></p>
        
        <a href="manage.php?id=<?=$g['id']?>" 
           class="btn btn-sm block text-center mt-2">
           Gerenciar agrupamento
        </a>
    </div>
    <?php endforeach; ?>

  </div>
<?php endif; ?>

<script>
// 🔍 Filtro com busca em tempo real
function filterCards(){
  let input = document.getElementById("searchInput").value.toLowerCase();
  let cards = document.querySelectorAll(".group-card");

  cards.forEach(card => {
    let text = card.innerText.toLowerCase();
    card.style.display = text.includes(input) ? "block" : "none";
  });
}
</script>

<?php require __DIR__.'/../../inc/footer.php'; ?>
