# railway-mysql-lab

Projeto de teste para aprender deploy no Railway com Node.js + MySQL.

## O que este app faz

- Sobe uma API Express simples.
- Conecta no MySQL via `DATABASE_URL` (ou variaveis `MYSQL*`).
- Cria automaticamente a tabela `tasks` no boot.
- Expoe endpoints para criar e listar tarefas.

## Endpoints

- `GET /` status da aplicacao e do banco
- `GET /health` health check
- `GET /api/tasks` lista tarefas
- `POST /api/tasks` cria tarefa
  - body JSON: `{ "title": "Minha tarefa" }`

## Rodar localmente

1. Instale as dependencias:

```bash
npm install
```

2. Suba um MySQL local (opcional, mas recomendado para teste rapido):

```bash
docker run --name railway-lab-mysql \
  -e MYSQL_ROOT_PASSWORD=root \
  -e MYSQL_DATABASE=railway_lab \
  -p 3306:3306 \
  -d mysql:8.0
```

3. Crie `.env`:

```bash
cp .env.example .env
```

4. Inicie o app:

```bash
npm start
```

## Deploy no Railway (com MySQL)

1. Crie um repositório com esta pasta e envie para GitHub.
2. No Railway, crie um projeto e adicione um serviço da sua aplicação (Deploy from GitHub).
3. No mesmo projeto, adicione um serviço `MySQL`.
4. No serviço da aplicação, em `Variables`, crie:
   - `DATABASE_URL=${{MySQL.MYSQL_URL}}`
   - Se o nome do serviço de banco não for `MySQL`, ajuste o prefixo.
5. Faça deploy da aplicação.
6. Em `Networking` do serviço da aplicação, clique em gerar domínio público.
7. Acesse o domínio gerado (`*.up.railway.app`) para validar o preview.

## Teste rapido apos deploy

```bash
curl https://SEU_DOMINIO.up.railway.app/
curl https://SEU_DOMINIO.up.railway.app/api/tasks
curl -X POST https://SEU_DOMINIO.up.railway.app/api/tasks \
  -H "Content-Type: application/json" \
  -d '{"title":"Primeiro teste no Railway"}'
```

## Observacoes

- O Railway injeta a variavel `PORT` automaticamente no runtime.
- O app ja usa `process.env.PORT`, entao nao precisa configurar porta manualmente.
