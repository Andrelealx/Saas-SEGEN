# Arquitetura Técnica

## Stack recomendada

1. Frontend: React + TypeScript.
2. Backend: Node.js + Express + TypeScript.
3. Banco: PostgreSQL.
4. Cache e filas: Redis.
5. Infra: Docker Compose (dev), VPS/Cloud (produção).

## Estrutura de solução

1. `apps/api`: API principal do sistema central.
2. `apps/web`: portal interno/cliente (planejado na fase seguinte).
3. `database`: schema SQL e migrações.
4. `infra`: serviços de infraestrutura local.

## Modelo multi-tenant

1. Estratégia inicial: `tenant_id` em tabelas compartilhadas.
2. Resolução de tenant por subdomínio ou header.
3. Regras de isolamento em toda query.
4. Evolução futura para schema por tenant quando escalar.

## Domínios funcionais da API

1. Auth e usuários.
2. CRM (leads e contas).
3. Projetos (tarefas, marcos e apontamento).
4. Financeiro (recebíveis e status).
5. Comercial (propostas e contratos).
6. Suporte (tickets e SLA).
7. Catálogo white-label.

## Segurança e conformidade

1. JWT com rotação de chave e expiração.
2. Controle de acesso por RBAC.
3. Senhas com hash forte.
4. Logs de auditoria para eventos críticos.
5. Política de backup diário.
6. Boas práticas de LGPD (consentimento, retenção e exclusão).

## Qualidade de software

1. Testes unitários para regras de negócio.
2. Testes de integração para rotas críticas.
3. Lint e formatação no CI.
4. Versionamento semântico de releases.
