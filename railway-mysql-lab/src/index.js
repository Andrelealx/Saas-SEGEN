require("dotenv").config();

const express = require("express");
const {
  initSchema,
  pingDatabase,
  listTasks,
  createTask,
  closePool,
} = require("./db");

const app = express();
app.use(express.json());

app.get("/", async (_req, res) => {
  try {
    const databaseOk = await pingDatabase();
    res.json({
      project: "railway-mysql-lab",
      status: "online",
      database: databaseOk ? "connected" : "disconnected",
      now: new Date().toISOString(),
    });
  } catch (error) {
    res.status(500).json({
      project: "railway-mysql-lab",
      status: "error",
      database: "unreachable",
      message: error.message,
    });
  }
});

app.get("/health", (_req, res) => {
  res.json({ ok: true, timestamp: new Date().toISOString() });
});

app.get("/api/tasks", async (_req, res) => {
  try {
    const tasks = await listTasks();
    res.json({ total: tasks.length, tasks });
  } catch (error) {
    res.status(500).json({ message: error.message });
  }
});

app.post("/api/tasks", async (req, res) => {
  const title = String(req.body?.title || "").trim();

  if (!title) {
    return res.status(400).json({ message: "Campo 'title' e obrigatorio." });
  }

  try {
    const task = await createTask(title);
    return res.status(201).json(task);
  } catch (error) {
    return res.status(500).json({ message: error.message });
  }
});

const port = Number(process.env.PORT || 3000);

async function bootstrap() {
  await initSchema();

  const server = app.listen(port, () => {
    // railway define PORT automaticamente em runtime
    console.log(`API online na porta ${port}`);
  });

  async function gracefulShutdown(signal) {
    console.log(`${signal} recebido, finalizando...`);
    server.close(async () => {
      await closePool();
      process.exit(0);
    });
  }

  process.on("SIGINT", () => gracefulShutdown("SIGINT"));
  process.on("SIGTERM", () => gracefulShutdown("SIGTERM"));
}

bootstrap().catch((error) => {
  console.error("Falha ao iniciar aplicacao:", error);
  process.exit(1);
});
