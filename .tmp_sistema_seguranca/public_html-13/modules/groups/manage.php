<?php
require __DIR__.'/../../core/auth.php'; 
auth_require();
require __DIR__.'/../../core/db.php';
require __DIR__.'/../../core/utils.php';

/* DEBUG
ini_set('display_errors',1); ini_set('display_startup_errors',1); error_reporting(E_ALL);
*/

$id   = (int)($_GET['id'] ?? 0);
$err  = [];
$ok   = null;

$group = ['name'=>'', 'description'=>''];
$selected = [];

try {
    if ($id) {
        $st = $pdo->prepare("SELECT id, name, description FROM groups WHERE id=?");
        $st->execute([$id]);
        $g  = $st->fetch();
        if (!$g) { http_response_code(404); exit('Agrupamento não encontrado'); }
        $group = $g;

        $st2 = $pdo->prepare("SELECT employee_id FROM group_members WHERE group_id=?");
        $st2->execute([$id]);
        $selected = array_column($st2->fetchAll(), 'employee_id');
    }
} catch (Throwable $e) { 
    $err[] = "Erro ao carregar agrupamento: ".$e->getMessage(); 
}

try {
    $emps = $pdo->query("SELECT id, name FROM employees WHERE status='ATIVO' ORDER BY name")->fetchAll();
} catch (Throwable $e) { 
    $err[] = "Erro ao carregar funcionários: ".$e->getMessage(); 
}

// SALVAR
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $members = isset($_POST['members']) ? array_map('intval', $_POST['members']) : [];

    if ($name === '') $err[] = "Nome é obrigatório.";

    if (!$err) {
        try {
            $pdo->beginTransaction();

            if ($id) {
                $upd = $pdo->prepare("UPDATE groups SET name=?, description=? WHERE id=?");
                $upd->execute([$name, $description ?: null, $id]);
            } else {
                $ins = $pdo->prepare("INSERT INTO groups (name, description) VALUES (?, ?)");
                $ins->execute([$name, $description ?: null]);
                $id = (int)$pdo->lastInsertId();
            }

            $pdo->prepare("DELETE FROM group_members WHERE group_id=?")
                ->execute([$id]);

            if ($members) {
                $insm = $pdo->prepare("INSERT INTO group_members (group_id, employee_id) VALUES (?,?)");
                foreach ($members as $empId) $insm->execute([$id, $empId]);
            }

            $pdo->commit();
            set_flash("Agrupamento salvo com sucesso!");
            header("Location: /modules/groups/");
            exit;

        } catch(Throwable $e){
            if($pdo->inTransaction()) $pdo->rollBack();
            $err[] = "Erro ao salvar: ".$e->getMessage();
            $group=['name'=>$name,'description'=>$description]; 
            $selected=$members;
        }
    } else {
        $group=['name'=>$name,'description'=>$description]; 
        $selected=$members;
    }
}

require __DIR__.'/../../inc/header.php';
?>

<style>
/* Destaque visual na busca */
.highlight { background: #ff99003b; }

/* Melhor experiência no mobile */
@media (max-width: 640px) {
    #memberList { height: 260px !important; }
}
</style>

<div class="flex justify-between items-center mb-6">
    <div>
        <h1 class="text-2xl font-bold flex items-center gap-2">
            <?= $id ? 'Editar Agrupamento' : 'Novo Agrupamento' ?>
        </h1>
        <p class="text-gray-400 text-sm">
            Crie grupos para organizar servidores por equipes, setores ou divisões internas.
        </p>
    </div>
    <a href="/modules/groups/" class="btn flex items-center gap-2">
        <i data-lucide="arrow-left" class="w-4 h-4"></i> Voltar
    </a>
</div>

<?php if ($err): ?>
<div class="card border border-red-700 bg-red-900/20 text-red-300 p-4 mb-4">
    <div class="font-semibold mb-2">⚠ Não foi possível salvar:</div>
    <ul class="list-disc ml-5 text-sm space-y-1">
        <?php foreach($err as $e): ?><li><?=h($e)?></li><?php endforeach; ?>
    </ul>
</div>
<?php endif; ?>

<div class="card p-6 max-w-5xl mx-auto">

<form method="post" id="groupForm" class="grid md:grid-cols-2 gap-6">

    <!-- DADOS DO GRUPO -->
    <section class="md:col-span-2">
        <h2 class="text-lg font-semibold mb-2">Informações do agrupamento</h2>
        <hr class="border-gray-700 mb-4">

        <div class="grid md:grid-cols-2 gap-4">
            <div>
                <label class="text-sm font-medium">Nome do agrupamento</label>
                <input name="name" required class="input" value="<?=h($group['name'])?>">
            </div>

            <div>
                <label class="text-sm font-medium">Descrição (opcional)</label>
                <input name="description" class="input" value="<?=h($group['description'])?>">
            </div>
        </div>
    </section>

    <!-- MEMBROS -->
    <section class="md:col-span-2 mt-2">
        <h2 class="text-lg font-semibold mb-2">Membros do agrupamento</h2>
        <hr class="border-gray-700 mb-3">

        <!-- Busca -->
        <input 
            type="text" 
            id="searchEmployee" 
            class="input mb-3"
            placeholder="Digite para buscar um servidor..."
            oninput="filterEmployees()"
        >

        <div class="flex gap-4 flex-col md:flex-row">

            <!-- Lista de funcionários -->
            <select 
                name="members[]" 
                size="14" 
                multiple 
                id="memberList"
                class="select w-full md:w-1/2 min-h-[300px]"
            >
                <?php foreach ($emps as $e): ?>
                <option value="<?=$e['id']?>" <?=in_array($e['id'], $selected)?'selected':''?>>
                    <?=h($e['name'])?>
                </option>
                <?php endforeach; ?>
            </select>

            <!-- Painel lateral -->
            <div class="w-full md:w-1/2 text-sm text-gray-400 space-y-2">

                <div class="p-3 rounded bg-gray-800/50">
                    <p class="font-semibold text-gray-300 mb-1">Como selecionar:</p>
                    <ul class="list-disc ml-5 space-y-1">
                        <li>Clique para selecionar um servidor.</li>
                        <li>Use CTRL para múltiplos.</li>
                        <li>Use SHIFT para seleções contínuas.</li>
                    </ul>
                </div>

                <div class="p-3 rounded bg-gray-800/50">
                    <p class="font-semibold text-gray-300">
                        Selecionados: <span id="countSel"><?=count($selected)?></span>
                    </p>
                </div>
            </div>

        </div>
    </section>

</form>

</div>

<!-- BARRA DE AÇÕES -->
<div class="fixed bottom-0 left-0 w-full bg-black/70 backdrop-blur-md p-3 border-t border-white/10 flex justify-center gap-3 z-50">
    <button form="groupForm" class="btn px-6 flex items-center gap-2">
        <i data-lucide="save" class="w-4 h-4"></i> Salvar agrupamento
    </button>

    <a href="/modules/groups/" class="btn btn-muted px-6">Cancelar</a>
</div>

<script>
function filterEmployees() {
    let s = document.getElementById("searchEmployee").value.toLowerCase();

    document.querySelectorAll("#memberList option").forEach(o => {
        let match = o.innerText.toLowerCase().includes(s);
        o.style.display = match ? 'block' : 'none';

        // destaque visual na busca
        if (s && match) {
            o.classList.add("highlight");
        } else {
            o.classList.remove("highlight");
        }
    });
}

document.getElementById("memberList").addEventListener("change", () => {
    document.getElementById("countSel").textContent =
        [...document.querySelectorAll("#memberList option:checked")].length;
});
</script>

<?php require __DIR__.'/../../inc/footer.php'; ?>
