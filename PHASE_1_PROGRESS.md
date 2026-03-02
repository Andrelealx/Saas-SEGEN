# Fase 1 - Progresso Executado

## Escopo aplicado
- Hardening de configuração (remoção de segredo do código versionado)
- Correção de links/fluxo administrativo quebrado
- CSRF em fluxos críticos de gravação
- Importação do dump de banco para o repositório

## Alterações implementadas
- `core/config.php`
  - Removidos segredos hardcoded
  - Adicionado suporte a variáveis de ambiente
  - Adicionado override opcional por `core/config.local.php`

- `core/utils.php`
  - Helpers de sessão/flash padronizados
  - Suporte de tipo para `set_flash(..., type)`
  - Helpers de CSRF (`csrf_token`, `csrf_check`)

- `inc/header.php`
  - Menu admin ajustado para rotas reais existentes

- `admin/access/roles_permissions.php`
  - Corrigido path de includes
  - Corrigido redirect de retorno
  - Adicionado CSRF no POST

- `modules/groups/manage.php`
  - Adicionado CSRF no POST

- `modules/employees/edit.php`
  - Adicionado CSRF no POST

- `modules/schedules/builder.php`
  - Adicionado CSRF no POST

- `modules/employees/index.php`
  - Exposição segura do token CSRF para frontend

- `assets/js/employees.js`
  - Envio de token CSRF em `save/delete/bulk/import`

- `modules/employees/api.php`
  - Validação CSRF em ações mutáveis

- `modules/schedules/index.php`
  - Envio CSRF em ações AJAX (`toggle_publish`, `generate_from_groups`)

- `modules/schedules/toggle_publish.php`
  - Validação CSRF em endpoint AJAX
  - Ajuste de payload de resposta

- `modules/schedules/generate_from_groups.php`
  - Validação CSRF em endpoint AJAX

- `modules/employees/create.php`
  - Correção de classe inválida em input (`input צור` -> `input`)

## Arquivos de suporte adicionados
- `.gitignore`
- `README.md`
- `core/config.local.example.php`
- `database/dumps/u305836601_SEGEN-4.sql`

## Próximo foco (Fase 2)
- Dashboard executivo com KPIs operacionais
- Fluxo de ausência/substituição para cobertura diária
- Associação de ocorrência à escala/posto/turno
