<?php
require __DIR__ . '/../../core/auth.php';
auth_require();

require __DIR__ . '/../../core/db.php';
require __DIR__ . '/../../core/utils.php';

/* DEBUG EMERGÊNCIA (use só pra achar erro de tela branca)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
*/

require __DIR__ . '/../../inc/header.php';
?>

<style>
/* -------------------------------
   TEMA DARK PROFISSIONAL
---------------------------------- */
.page-title {
    font-size: 1.875rem;
    font-weight: 700;
    color: #f3f4f6;
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.card {
    background: rgba(17, 24, 39, 0.4);
    border: 1px solid #1f2937;
    border-radius: 0.75rem;
    padding: 1.25rem;
    box-shadow: 0 0 20px rgba(0,0,0,0.25);
}

.input,
.select,
textarea {
    background: rgba(17, 24, 39, 0.7);
    border: 1px solid #374151;
    border-radius: 0.5rem;
    color: #e5e7eb;
    padding: 0.5rem 0.75rem;
    width: 100%;
    outline: none;
}

.btn-primary {
    background: #ea580c;
    color: white;
    padding: 0.5rem 1rem;
    border-radius: 0.5rem;
    transition: 0.2s;
}
.btn-primary:hover { background: #c2410c; }

.btn-secondary {
    background: #374151;
    color: #e5e7eb;
    padding: 0.5rem 1rem;
    border-radius: 0.5rem;
}
.btn-secondary:hover { background: #4b5563; }

.btn-danger {
    background: #dc2626;
    color: white;
    padding: 0.5rem 1rem;
    border-radius: 0.5rem;
}
.btn-danger:hover { background: #b91c1c; }

.table-card {
    background: rgba(17, 24, 39, 0.4);
    border: 1px solid #1f2937;
    border-radius: 0.75rem;
    box-shadow: 0 0 25px rgba(0,0,0,0.3);
    margin-top: 1rem;
    overflow-x: auto;
}

.avatar {
    width: 2.5rem;
    height: 2.5rem;
    border-radius: 9999px;
    border: 1px solid #374151;
    object-fit: cover;
}

/* BADGES */
.badge {
    padding: 0.25rem 0.5rem;
    border-radius: 9999px;
    font-size: 0.75rem;
    font-weight: 600;
    border: 1px solid;
}
.badge.green {
    background: rgba(22, 163, 74, 0.2);
    color: #22c55e;
    border-color: rgba(22, 163, 74, 0.3);
}
.badge.orange {
    background: rgba(234, 88, 12, 0.2);
    color: #fb923c;
    border-color: rgba(234, 88, 12, 0.3);
}
.badge.red {
    background: rgba(220, 38, 38, 0.2);
    color: #f87171;
    border-color: rgba(220, 38, 38, 0.3);
}

/* -------------------------------
   MODAL POPUP CORRIGIDO
---------------------------------- */
.modal-bg {
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,0.6);
    backdrop-filter: blur(4px);
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 1rem;
    z-index: 99999;
}

.modal-box {
    background: rgba(17, 24, 39, 0.9);
    border: 1px solid #1f2937;
    border-radius: 1rem;
    width: 100%;
    max-width: 900px;
    padding: 1.5rem;
    box-shadow: 0 20px 50px rgba(0,0,0,0.5);
    animation: modalFade 0.25s ease-out;
}

@keyframes modalFade {
    from { opacity: 0; transform: scale(0.95); }
    to { opacity: 1; transform: scale(1); }
}

/* TOAST */
.toast {
    position: fixed;
    bottom: 1.5rem;
    right: 1.5rem;
    background: #1f2937;
    color: white;
    padding: 0.75rem 1.25rem;
    border-radius: 0.5rem;
    box-shadow: 0 0 25px rgba(0,0,0,0.4);
    display: none;
}
</style>

<div class="flex items-center justify-between mb-6">
  <h1 class="page-title">
    <i data-lucide="users" class="w-8 h-8 text-orange-400"></i> Funcionários
  </h1>

  <div class="flex items-center gap-2">
    <label class="btn-primary cursor-pointer">
      Importar CSV
      <input id="import-file" type="file" accept=".csv" hidden>
    </label>

    <button id="btn-export" class="btn-primary">Exportar CSV</button>

    <button id="btn-new" class="btn-primary flex items-center gap-1">
      <i data-lucide="plus" class="h-4 w-4"></i> Novo
    </button>
  </div>
</div>

<!-- FILTROS -->
<div class="card mb-4">
  <div class="grid md:grid-cols-4 gap-3">
    <input id="q" class="input" placeholder="Buscar por nome, matrícula, CPF, e-mail...">

    <select id="status" class="select">
      <option value="">Todos os status</option>
      <option value="ATIVO">Ativos</option>
      <option value="FERIAS">Férias</option>
      <option value="AFASTADO">Afastados</option>
    </select>

    <select id="dept" class="select">
      <option value="">Todos os departamentos</option>
    </select>

    <div class="flex gap-2">
      <button id="btn-search" class="btn-primary w-full">Buscar</button>
      <button id="btn-clear" class="btn-secondary w-full">Limpar</button>
    </div>
  </div>
</div>

<!-- LISTA -->
<div class="card table-card">
  <table class="table w-full">
    <thead>
      <tr>
        <th class="px-4"><input id="chk-all" type="checkbox"></th>
        <th class="px-4">Foto</th>
        <th class="px-4">Matrícula</th>
        <th class="px-4">Nome</th>
        <th class="px-4">E-mail / Telefone</th>
        <th class="px-4">Departamento</th>
        <th class="px-4">Cargo</th>
        <th class="px-4">Status</th>
        <th class="px-4 text-right">Ações</th>
      </tr>
    </thead>
    <tbody id="list-body">
      <tr><td colspan="9" class="p-6 text-center text-gray-400">Carregando...</td></tr>
    </tbody>
  </table>
</div>

<div id="pager" class="mt-4"></div>

<!-- AÇÕES EM MASSA -->
<div class="flex gap-2 mt-4">
  <button id="bulk-activate" class="btn-secondary">Ativar selecionados</button>
  <button id="bulk-deactivate" class="btn-secondary">Inativar selecionados</button>
  <button id="bulk-delete" class="btn-danger">Excluir selecionados</button>
</div>

<!-- MODAL ROOT -->
<div id="modal-root"></div>

<!-- TOAST -->
<div id="toast" class="toast"></div>

<script>
  window.CSRF_TOKEN = <?= json_encode(csrf_token('_csrf')) ?>;
</script>
<script src="/assets/js/employees.js?v=20260108"></script>

<?php require __DIR__ . '/../../inc/footer.php'; ?>
