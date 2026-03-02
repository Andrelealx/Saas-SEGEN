<?php
require __DIR__.'/../../core/auth.php'; 
auth_require();
require __DIR__.'/../../core/db.php';

date_default_timezone_set('America/Sao_Paulo');
$today       = date('Y-m-d');
$todayLabel  = date('d/m/Y');
?>
<!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<title>Painel de Escalas • Secretaria de Segurança</title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<script src="https://cdn.tailwindcss.com"></script>

<style>
   body {
        background: #020617 url('/assets/img/bg_hex.png') no-repeat center center fixed;
        background-size: cover;
        color: #e5e7eb;
        font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
        overflow: hidden;
    }

    .card-tv {
        background: rgba(15,23,42,0.95);
        border-radius: 18px;
        border: 1px solid rgba(148,163,184,0.4);
        padding: 16px 18px;
        box-shadow: 0 18px 45px rgba(15,23,42,0.9);
        min-height: 120px;
    }
    .status-pill {
        padding: 4px 10px;
        border-radius: 9999px;
        font-size: 11px;
        font-weight: 600;
        letter-spacing: .05em;
        text-transform: uppercase;
    }
    .status-published {
        background: rgba(22,163,74,.2);
        color: #4ade80;
        border: 1px solid #22c55e;
    }
    .status-draft {
        background: rgba(234,179,8,.18);
        color: #facc15;
        border: 1px solid #eab308;
    }
    .post-title {
        font-size: 1.2rem;
        font-weight: 600;
        letter-spacing: .03em;
        text-transform: uppercase;
    }
    .live-dot {
        width: 11px;
        height: 11px;
        border-radius: 9999px;
        background: #22c55e;
        box-shadow: 0 0 15px rgba(34,197,94,.9);
        animation: pulse 1.4s infinite;
    }
    @keyframes pulse {
        0% { transform: scale(.9); opacity:.7; }
        50%{ transform: scale(1.3); opacity:1; }
        100%{ transform: scale(.9); opacity:.7; }
    }
    .agent-line {
        font-size: .95rem;
    }
    .agent-shift {
        font-size: .8rem;
        color: #9ca3af;
    }
</style>
</head>
<body>

<div class="h-screen w-screen flex flex-col">

    <!-- HEADER SUPERIOR -->
    <header class="flex items-center justify-between px-10 pt-6 pb-4 border-b border-slate-800/80">
        <div class="flex items-center gap-6">
            <!-- Ajuste o caminho da logo conforme onde você salvar -->
            <img src="/assets/img/logo_guapi.png" alt="Cidade de Guapimirim" class="h-14 object-contain">

            <div class="leading-tight">
                <div class="text-xs tracking-[0.35em] text-slate-400 uppercase">
                    Secretaria Municipal de
                </div>
                <div class="text-xl font-semibold tracking-[0.18em] text-slate-50 uppercase">
                    Segurança Pública
                </div>
                <div class="text-[11px] text-slate-500 mt-1">
                    Painel operacional de escalas • Uso interno
                </div>
            </div>
        </div>

        <div class="flex items-end gap-8">
            <div class="text-right">
                <div class="text-xs text-slate-400 uppercase tracking-[0.25em] mb-1">
                    Escala de Hoje
                </div>
                <div class="text-2xl font-semibold text-slate-50">
                    <?=$todayLabel?>
                </div>
            </div>

            <div class="flex flex-col items-end gap-1">
                <div class="flex items-center gap-2 text-xs text-emerald-400 uppercase tracking-[0.20em]">
                    <span class="live-dot"></span>
                    <span>ao vivo</span>
                </div>
                 <div class="text-[11px] text-slate-500">
                    Última atualização: <span id="lastUpdate">--:--:--</span>
                </div>
            </div>
        </div>
    </header>

    <!-- CONTEÚDO PRINCIPAL -->
    <main class="flex-1 px-10 pb-6 pt-3 overflow-hidden">
        <div class="h-full w-full rounded-3xl bg-slate-950/70 border border-slate-800/70 p-6 shadow-[0_24px_80px_rgba(15,23,42,1)] flex flex-col">

            <!-- Linha de títulos -->
            <div class="flex items-center justify-between mb-4">
                <div class="text-sm text-slate-400 uppercase tracking-[0.25em]">
                    Postos e equipes escaladas
                </div>
               <!-- <div class="text-[11px] text-slate-500">
                   - Telão otimizado para exibição contínua. Recomenda-se usar em tela cheia (F11).
                </div>-->
            </div>

            <!-- Grid de cards -->
            <div id="content" class="grid gap-4 grid-cols-1 md:grid-cols-2 xl:grid-cols-3 auto-rows-min overflow-y-auto pr-1">
                <!-- preenchido via AJAX -->
            </div>

        </div>
    </main>
</div>

<script>
function formatTimeBR(date){
    return date.toLocaleTimeString('pt-BR', {
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit'
    });
}

function loadTV(){
    fetch('tv_fetch.php', { cache: 'no-store' })
        .then(r => r.text())
        .then(html => {
            const container = document.getElementById('content');
            container.innerHTML = html;
            document.getElementById('lastUpdate').textContent = formatTimeBR(new Date());
        })
        .catch(() => {
            // se der erro, não quebra o painel, só mostra aviso discreto
            document.getElementById('lastUpdate').textContent = 'erro de conexão';
        });
}

// Atualiza a cada 10 segundos
loadTV();
setInterval(loadTV, 10000);
</script>

</body>
</html>
