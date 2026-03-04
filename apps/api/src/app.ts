import path from "node:path";
import express, { Request, Response } from "express";
import { healthRouter } from "./routes/health";
import { authRouter } from "./routes/auth";
import { dashboardRouter } from "./routes/dashboard";
import { crmRouter } from "./routes/crm";
import { projectsRouter } from "./routes/projects";
import { proposalsRouter } from "./routes/proposals";
import { financeRouter } from "./routes/finance";
import { supportRouter } from "./routes/support";
import { catalogRouter } from "./routes/catalog";
import { clientsRouter } from "./routes/clients";
import { teamRouter } from "./routes/team";
import { goalsRouter } from "./routes/goals";
import { activitiesRouter } from "./routes/activities";
import { tenantsRouter } from "./routes/tenants";
import { attachTenant, requireAuth } from "./middlewares/requestContext";

const app = express();
const port = Number(process.env.PORT || 3333);
const webPath = path.resolve(__dirname, "../../web");

app.use(express.json());
app.use(healthRouter);
app.use("/app", express.static(webPath));

app.get("/", (_req, res) => {
  res.json({
    service: "softhouse-central-api",
    app: "/app",
    message:
      "Sistema central ativo. Em desenvolvimento local, use tenant padrao dev-tenant se nao enviar x-tenant-id."
  });
});

app.use(attachTenant);
app.use(requireAuth);
app.use(authRouter);
app.use(dashboardRouter);
app.use(crmRouter);
app.use(projectsRouter);
app.use(proposalsRouter);
app.use(financeRouter);
app.use(supportRouter);
app.use(catalogRouter);
app.use(clientsRouter);
app.use(teamRouter);
app.use(goalsRouter);
app.use(activitiesRouter);
app.use(tenantsRouter);

app.use((req: Request, res: Response) => {
  res.status(404).json({ error: `Rota não encontrada: ${req.path}` });
});

app.listen(port, () => {
  console.log(`API rodando em http://localhost:${port}`);
});
