# Sistema de Segurança (RH + Escalas + SDR + Ocorrências)

Aplicação PHP para operação interna da Secretaria de Segurança.

## Requisitos
- PHP 7.4+ (recomendado 8.1+)
- MySQL/MariaDB
- Extensões PHP: `pdo_mysql`, `mbstring`, `json`, `fileinfo`

## Setup rápido
1. Copie o projeto para seu servidor web.
2. Importe o banco:
   - `database/dumps/u305836601_SEGEN-4.sql`
3. Configure credenciais locais:
   - copie `core/config.local.example.php` para `core/config.local.php`
   - ajuste host/usuário/senha/banco
4. Garanta permissão de escrita em:
   - `uploads/`
   - `storage/employees/`

## Segurança (fase inicial aplicada)
- `core/config.php` não contém mais segredo fixo.
- `core/config.local.php` fica fora do versionamento.
- CSRF aplicado nos fluxos críticos de alteração.
- Menu admin ajustado para páginas existentes.

## Banco de dados
Dump principal versionado em:
- `database/dumps/u305836601_SEGEN-4.sql`

## Próximas fases
- Fase 2: dashboard executivo e fluxo de ausência/substituição
- Fase 3: motor de regras de escala e aprovação em camadas
