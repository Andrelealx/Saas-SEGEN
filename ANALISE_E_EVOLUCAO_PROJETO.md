# Análise e Evolução da Ideia do Projeto

## 1) O que o projeto já é hoje (ponto de partida)
Você já tem um **sistema operacional para Segurança Pública municipal** com foco em rotina interna:

- Gestão de funcionários (`employees`)
- Escalas e designações (`schedules` + `shift_assignments`)
- SDR/Horas extras (`overtime_requests`)
- Livro de ocorrências com anexos e auditoria (`occurrences`)
- Agrupamentos de equipes (`groups`)
- Relatórios CSV e painel diário
- Controle de acesso por roles/permissões (RBAC parcial)
- Painel de TV para exibição operacional em tempo real

Resumo: a ideia atual já é boa e útil, mas ainda está como "sistema administrativo". O potencial real é virar **plataforma de comando operacional**.

---

## 2) Problemas que limitam crescimento hoje

### 2.1 Produto (negócio)
- Falta uma narrativa única do produto: RH, escalas, SDR e ocorrências ainda parecem módulos soltos.
- Métricas de gestão ainda são básicas; faltam indicadores de decisão (cobertura operacional, custo de hora extra, gargalos por posto/setor).
- Não há fluxo explícito de "efetivo planejado x efetivo executado".

### 2.2 Técnico (estrutura)
- Monólito PHP com páginas mistas (regra de negócio + HTML + SQL no mesmo arquivo).
- Ausência de migrações/versionamento completo do banco (apenas `modules/occurrences/INSTALL.sql`).
- Duplicidade de fluxos em funcionários (`index` com API moderna e telas antigas `create/edit`).
- Padronização incompleta de segurança (CSRF e RBAC aplicados de forma desigual).

### 2.3 Segurança e governança
- Credenciais de banco em texto puro no repositório (`core/config.php`).
- Links de administração apontando para páginas inexistentes, gerando risco operacional e confusão de permissões.
- Algumas telas de alteração sem proteção CSRF.

---

## 3) Ideia melhorada (versão produto)

### Nome/proposta
**Plataforma de Gestão Operacional da Segurança Municipal (PGOSM)**

### Tese
Não ser só "sistema de cadastro", e sim o sistema que responde:
- Quem está escalado agora?
- Onde está o déficit de efetivo?
- Qual custo e pressão de SDR por posto/setor?
- Quais ocorrências impactam a escala e o planejamento do próximo turno?

### Pilares do produto
1. **Planejamento operacional**
- Escala mensal/semanal por posto e grupo
- Regras de cobertura mínima por posto
- Geração automática de escala por regras

2. **Execução em tempo real**
- Painel de plantão (TV + web)
- Situação do dia: escalado, pendente, ausência, substituição
- Alertas operacionais (buraco de escala, excesso de hora extra)

3. **Comando e evidência**
- Livro de ocorrências integrado à escala
- Anexos com rastreabilidade
- Trilha de auditoria confiável por ação

4. **Governança e conformidade**
- RBAC por perfil/função
- Logs de auditoria unificados
- Relatórios gerenciais e jurídicos (CSV/PDF)

---

## 4) Funcionalidades de alto impacto (prioridade)

### Prioridade A (impacto imediato)
- Dashboard executivo com KPIs operacionais:
  - Cobertura de postos (%)
  - SDR pendente/aprovada/negada
  - Custo estimado de SDR por período
  - Ocorrências por setor e tipo
- Fluxo de "ausência e substituição" para fechar escala do dia.
- Vincular ocorrência a posto/equipe/turno para visão contextual.

### Prioridade B (ganho de eficiência)
- Motor de regras de escala:
  - limite de horas por servidor
  - descanso mínimo entre turnos
  - distribuição equilibrada de carga
- Workflow de aprovação em camadas (chefia imediata -> comando -> RH).
- Relatórios analíticos com filtros salvos.

### Prioridade C (maturidade)
- App mobile interno (consulta de escala, confirmação de ciência, abertura rápida de ocorrência).
- Notificações (e-mail/WhatsApp corporativo) para mudanças de escala/status de SDR.
- Mapa operacional (postos/áreas + status).

---

## 5) Redesenho de arquitetura recomendado

### Curto prazo (sem reescrever tudo)
- Manter PHP, mas criar separação mínima:
  - `controllers/` (entrada HTTP)
  - `services/` (regras de negócio)
  - `repositories/` (SQL)
  - `views/` (renderização)
- Padronizar resposta JSON da API.
- Centralizar validação e autorização.

### Médio prazo
- API interna versionada (`/api/v1`) para desacoplar front.
- Migrations de banco (Phinx ou Laravel Migrations standalone).
- Testes de integração para fluxos críticos (login, aprovar SDR, publicar escala, criar ocorrência).

### Segurança (obrigatório)
- Tirar segredos do código e migrar para `.env`.
- CSRF obrigatório em todo POST.
- RBAC obrigatório em todos os endpoints mutáveis.
- Política de upload com antivírus/verificação MIME/extensão e armazenamento fora de pasta pública quando possível.

---

## 6) Roadmap sugerido (90 dias)

### Fase 1 (0-30 dias) - Estabilização
- Corrigir riscos críticos (credenciais, CSRF, links quebrados, permissões).
- Definir versão mínima de schema e migrations.
- Unificar fluxo de funcionários (remover/arquivar telas legadas).

### Fase 2 (31-60 dias) - Inteligência operacional
- Implementar dashboard executivo e KPIs.
- Criar fluxo de ausência/substituição.
- Relacionar ocorrências com escala/turno/posto.

### Fase 3 (61-90 dias) - Escala inteligente
- Motor de regras de escala com validação de conflito.
- Aprovação em etapas de SDR.
- Relatórios de custo e performance por unidade/setor.

---

## 7) Decisão estratégica (mais importante)
A melhor evolução da ideia é posicionar o sistema como:

**"Centro digital de planejamento, execução e governança da operação de segurança municipal"**

e não apenas como "sistema de RH com escalas".

Essa mudança de posicionamento aumenta valor político, operacional e institucional do projeto, além de justificar investimento contínuo.

---

## 8) Evidências usadas na análise (arquivos)
- Núcleo: `core/auth.php`, `core/rbac.php`, `core/db.php`, `core/config.php`
- Dashboard/menu: `index.php`, `inc/header.php`
- Módulos: `modules/schedules`, `modules/overtime`, `modules/occurrences`, `modules/groups`, `modules/employees`, `modules/reports`
- Administração: `admin/access/users.php`, `admin/access/roles.php`, `admin/access/roles_permissions.php`
