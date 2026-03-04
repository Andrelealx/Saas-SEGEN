# Backlog V1 Priorizado

## EPIC 1 - Identidade e acesso

1. Como admin, quero cadastrar usuários para montar o time no sistema.
Critério: criação de usuário com perfil e tenant.
2. Como usuário, quero autenticar com segurança para acessar meus módulos.
Critério: login JWT com expiração.

## EPIC 2 - CRM

1. Como comercial, quero cadastrar leads para iniciar funil.
Critério: lead com origem, segmento e status inicial.
2. Como comercial, quero mover etapas do funil para visualizar progresso.
Critério: pipeline com etapas configuráveis e histórico.
3. Como gestor, quero ver taxa de conversão por etapa.
Critério: dashboard com taxa por período.

## EPIC 3 - Projetos

1. Como gerente, quero criar projeto a partir de proposta aprovada.
Critério: vínculo proposta-projeto.
2. Como time técnico, quero organizar tarefas por fase.
Critério: kanban com status e responsável.
3. Como gestor, quero alertas de atraso.
Critério: projeto com semáforo por prazo.

## EPIC 4 - Comercial

1. Como comercial, quero gerar proposta em PDF para padronizar vendas.
Critério: template com escopo, preço e validade.
2. Como comercial, quero registrar status da proposta.
Critério: enviado, visualizado, aprovado, recusado.
3. Como operação, quero armazenar contrato assinado.
Critério: upload e vínculo com cliente/projeto.

## EPIC 5 - Financeiro

1. Como financeiro, quero criar contas a receber por projeto.
Critério: parcelas, vencimento e status.
2. Como gestor, quero visão mensal de receita prevista x realizada.
Critério: dashboard com filtro por mês.

## EPIC 6 - Catálogo White-Label

1. Como comercial, quero cadastrar produtos prontos para acelerar proposta.
Critério: ficha de produto com preço base e prazo.
2. Como operação, quero definir tema do cliente.
Critério: logo, cor primária/secundária e domínio.

## EPIC 7 - Suporte

1. Como cliente, quero abrir chamado para solicitar suporte.
Critério: ticket com prioridade e anexo.
2. Como atendimento, quero tratar tickets por SLA.
Critério: fila com timers de SLA.

## Ordem de implementação (MVP)

1. Auth
2. CRM
3. Projetos
4. Financeiro
5. Comercial
6. Catálogo WL
7. Suporte
8. Dashboard
