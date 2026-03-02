<?php
// /inc/header.php
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../core/auth.php';
auth_require();

/* ===== RBAC (se existir, usa; se não existir, não quebra) ===== */
$rbacFile = __DIR__ . '/../core/rbac.php';
if (is_file($rbacFile)) {
  require_once $rbacFile;
}

/**
 * can($perm):
 * - perm null/vazio => liberado
 * - se existir rbac_can => usa
 * - se não existir rbac_can => libera (compatibilidade até você terminar o RBAC)
 */
function can(?string $perm): bool {
  if (!$perm) return true;
  if (function_exists('rbac_can')) return rbac_can($perm);
  return true;
}

if (!function_exists('str_starts_with')) {
  function str_starts_with($haystack, $needle){
    return $needle === '' || strpos($haystack, $needle) === 0;
  }
}

$uname  = htmlspecialchars($_SESSION['uname'] ?? 'Usuário', ENT_QUOTES, 'UTF-8');
$active = $_SERVER['REQUEST_URI'] ?? '';

/* ===== Menus ===== */
$navMain = [
  ["href"=>"/index.php","icon"=>"layout-dashboard","label"=>"Dashboard","perm"=>null],

  ["href"=>"/modules/employees/","icon"=>"users","label"=>"Funcionários","perm"=>"employees.view"],
  ["href"=>"/modules/schedules/","icon"=>"calendar","label"=>"Escalas","perm"=>"schedules.view"],
  ["href"=>"/modules/overtime/","icon"=>"alarm-clock","label"=>"SDR","perm"=>"overtime.view"],
  ["href"=>"/modules/occurrences/","icon"=>"book-open","label"=>"Ocorrências","perm"=>"occurrences.view"],
  ["href"=>"/modules/groups/","icon"=>"layers","label"=>"Agrupamentos","perm"=>"groups.view"],
  ["href"=>"/modules/reports/","icon"=>"file-chart-column","label"=>"Relatórios","perm"=>"reports.view"],
];

$navAdmin = [
  ["href"=>"/admin/access/users.php","icon"=>"user-cog","label"=>"Usuários","perm"=>"access.users.manage"],
  ["href"=>"/admin/access/roles.php","icon"=>"badge-check","label"=>"Perfis (Roles)","perm"=>"access.manage"],
  ["href"=>"/admin/access/roles_permissions.php","icon"=>"link-2","label"=>"Permissões da Role","perm"=>"rbac.manage"],
];

function renderNavItems(array $items, string $active): void {
  foreach ($items as $item) {
    if (!can($item['perm'] ?? null)) continue;

    $href  = (string)($item['href'] ?? '#');
    $icon  = (string)($item['icon'] ?? 'circle');
    $label = (string)($item['label'] ?? 'Item');

    $is = str_starts_with($active, $href);
    ?>
    <a href="<?= htmlspecialchars($href, ENT_QUOTES, 'UTF-8') ?>"
       class="group flex items-center gap-3 px-3 py-2 rounded-xl mb-1
              <?= $is ? 'bg-orange-500/15 text-orange-300' : 'hover:bg-white/5 text-gray-300' ?>">
      <i data-lucide="<?= htmlspecialchars($icon, ENT_QUOTES, 'UTF-8') ?>" class="h-5 w-5 opacity-80"></i>
      <span class="text-sm"><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></span>
      <?php if($is): ?><span class="ml-auto h-2 w-2 rounded-full bg-orange-400"></span><?php endif; ?>
    </a>
    <?php
  }
}

// existe ao menos 1 item admin visível?
$hasAdmin = false;
foreach ($navAdmin as $it) {
  if (can($it['perm'] ?? null)) { $hasAdmin = true; break; }
}
?>
<!doctype html>
<html lang="pt-BR" class="h-full">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>RH Segurança</title>

<!-- Tailwind -->
<script src="https://cdn.tailwindcss.com"></script>
<script>
  tailwind.config = {
    theme: {
      extend: {
        colors: {
          base: { 900:"#0b0f1a", 800:"#111827", 700:"#1f2937", 600:"#243042" },
          accent: { DEFAULT:"#f97316", 600:"#ea580c", 700:"#c2410c" }
        },
        boxShadow: { soft: "0 10px 30px rgba(0,0,0,.35)" },
        borderRadius: { '2xl':'1rem', '3xl':'1.25rem' }
      }
    }
  }
</script>

<link rel="stylesheet" href="/assets/css/app.css">
<script defer src="/assets/js/ui.js"></script>
</head>

<body class="bg-base-900 text-gray-100 min-h-screen">
<div class="flex min-h-screen">

  <!-- Sidebar -->
  <aside id="sidebar" class="w-64 shrink-0 bg-base-800/90 backdrop-blur border-r border-gray-800 hidden lg:block">
    <div class="px-4 py-4 flex items-center gap-3 border-b border-gray-800">

      <!-- LOGO -->
      <img src="/assets/img/logo_guapi.png"
           alt="Logo Guapimirim"
           class="h-12 w-auto object-contain drop-shadow-[0_0_6px_rgba(0,0,0,0.6)] select-none">

      <!-- TEXTOS -->
      <div class="leading-tight">
        <div class="text-xs text-gray-400 uppercase tracking-wide">
          Secretaria Municipal de
        </div>
        <div class="font-semibold text-gray-100 tracking-wide">
          Segurança Pública
        </div>
      </div>

    </div>

    <nav class="p-3">
      <?php renderNavItems($navMain, $active); ?>

      <?php if ($hasAdmin): ?>
        <div class="mt-4 mb-2 px-3 text-xs uppercase tracking-widest text-gray-500">
          Administração
        </div>
        <?php renderNavItems($navAdmin, $active); ?>
      <?php endif; ?>
    </nav>
  </aside>

  <!-- Conteúdo -->
  <div class="flex-1 flex flex-col">

    <!-- Topbar -->
    <header class="sticky top-0 z-40 bg-base-800/80 backdrop-blur border-b border-gray-800">
      <div class="px-4 lg:px-6 py-3 flex items-center gap-3">
        <button class="lg:hidden p-2 rounded-lg hover:bg-white/5" id="btnSidebar" type="button">
          <i data-lucide="menu" class="h-5 w-5"></i>
        </button>

        <div class="flex-1">
          <div class="text-xs uppercase tracking-widest text-gray-400">Sistema</div>
          <div class="font-semibold">Secretaria de Segurança</div>
        </div>

        <div class="hidden md:flex items-center gap-2">
          <button id="themeToggle" type="button"
                  class="px-3 py-2 text-sm rounded-xl border border-gray-700 hover:bg-white/5">
            Tema
          </button>

          <div class="px-3 py-2 rounded-xl bg-white/5 border border-gray-700 text-sm">
            <?= $uname ?>
          </div>

          <a href="/logout.php"
             class="px-3 py-2 rounded-xl bg-red-500/15 text-red-300 border border-red-800 hover:bg-red-500/25">
            Sair
          </a>
        </div>
      </div>
    </header>

    <!-- Main wrapper (fecha no footer.php) -->
    <main class="p-4 lg:p-6">
