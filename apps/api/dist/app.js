"use strict";
var __importDefault = (this && this.__importDefault) || function (mod) {
    return (mod && mod.__esModule) ? mod : { "default": mod };
};
Object.defineProperty(exports, "__esModule", { value: true });
const node_path_1 = __importDefault(require("node:path"));
const express_1 = __importDefault(require("express"));
const health_1 = require("./routes/health");
const auth_1 = require("./routes/auth");
const dashboard_1 = require("./routes/dashboard");
const crm_1 = require("./routes/crm");
const projects_1 = require("./routes/projects");
const proposals_1 = require("./routes/proposals");
const finance_1 = require("./routes/finance");
const support_1 = require("./routes/support");
const catalog_1 = require("./routes/catalog");
const tenants_1 = require("./routes/tenants");
const requestContext_1 = require("./middlewares/requestContext");
const app = (0, express_1.default)();
const port = Number(process.env.PORT || 3333);
const webPath = node_path_1.default.resolve(__dirname, "../../web");
app.use(express_1.default.json());
app.use(health_1.healthRouter);
app.use("/app", express_1.default.static(webPath));
app.get("/", (_req, res) => {
    res.json({
        service: "softhouse-central-api",
        app: "/app",
        message: "Sistema central ativo. Em desenvolvimento local, use tenant padrao dev-tenant se nao enviar x-tenant-id."
    });
});
app.use(requestContext_1.attachTenant);
app.use(requestContext_1.requireAuth);
app.use(auth_1.authRouter);
app.use(dashboard_1.dashboardRouter);
app.use(crm_1.crmRouter);
app.use(projects_1.projectsRouter);
app.use(proposals_1.proposalsRouter);
app.use(finance_1.financeRouter);
app.use(support_1.supportRouter);
app.use(catalog_1.catalogRouter);
app.use(tenants_1.tenantsRouter);
app.use((req, res) => {
    res.status(404).json({ error: `Rota não encontrada: ${req.path}` });
});
app.listen(port, () => {
    console.log(`API rodando em http://localhost:${port}`);
});
