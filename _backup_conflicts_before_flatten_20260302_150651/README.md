# Sistema Central da MAJ Software

Projeto completo para abrir, organizar e escalar uma softhouse com foco em:

1. Operação interna profissional (CRM, projetos, financeiro, suporte).
2. Produtos white-label personalizados (logo, paleta e domínio do cliente).
3. Entrega híbrida: sistemas prontos + projetos sob encomenda.

## Estrutura do repositório

- `docs/`: plano de negócio, operação, arquitetura, roadmap e backlog.
- `docs/templates/`: templates de briefing, proposta, contrato e ritos.
- `apps/api/`: esqueleto inicial da API do sistema central.
- `database/`: modelo relacional base em SQL.
- `infra/`: docker compose para ambiente local.

## Resultado deste projeto

1. Modelo operacional definido de ponta a ponta.
2. Catálogo de produtos white-label pronto para venda.
3. Backlog técnico priorizado para MVP.
4. Base de API inicial com rotas para evoluir.
5. Processo comercial e de entrega padronizado.

## Fases sugeridas

1. Fase 1 (0-30 dias): CRM + Projetos + Financeiro básico.
2. Fase 2 (31-60 dias): Propostas + Contratos + Catálogo white-label.
3. Fase 3 (61-90 dias): Suporte + Portal do cliente + Dashboards.
4. Fase 4 (90+ dias): Automações, integrações e escala comercial.

## Como iniciar

1. Revisar `docs/00-resumo-executivo.md`.
2. Validar stack em `docs/04-arquitetura-tecnica.md`.
3. Executar MVP com base em `docs/06-backlog-v1.md`.
4. Subir infraestrutura local com `infra/docker-compose.yml`.
5. Evoluir API em `apps/api/` e conectar com banco em `database/schema.sql`.

## Comandos úteis

```bash
npm install
npm run dev
```

## Acesso ao sistema

1. Abra `http://localhost:3333/app`.
2. Login inicial:
   - Tenant: `dev-tenant`
   - Email: `admin@softhouse.com`
   - Senha: `admin123`

## Próximo marco recomendado

Fechar 3 clientes piloto usando 1 produto white-label e 1 projeto sob encomenda para validar operação, precificação e funil.
