"use strict";
Object.defineProperty(exports, "__esModule", { value: true });
exports.attachTenant = attachTenant;
exports.requireAuth = requireAuth;
const auth_1 = require("../services/auth");
const store_1 = require("../services/store");
const PUBLIC_PATHS = new Set(["/", "/health", "/auth/login"]);
function attachTenant(req, res, next) {
    if (PUBLIC_PATHS.has(req.path)) {
        req.tenantId = String(req.headers["x-tenant-id"] || "dev-tenant");
        return next();
    }
    const incoming = req.headers["x-tenant-id"];
    const isMissingTenant = !incoming || String(incoming).trim() === "";
    if (isMissingTenant && process.env.NODE_ENV !== "production") {
        req.tenantId = "dev-tenant";
        req.headers["x-tenant-id"] = "dev-tenant";
        return next();
    }
    if (isMissingTenant) {
        return res.status(400).json({ error: "Cabeçalho x-tenant-id e obrigatorio" });
    }
    req.tenantId = String(incoming);
    return next();
}
function requireAuth(req, res, next) {
    if (PUBLIC_PATHS.has(req.path)) {
        return next();
    }
    const authorization = req.headers.authorization;
    const token = authorization?.startsWith("Bearer ")
        ? authorization.slice("Bearer ".length).trim()
        : "";
    if (!token) {
        return res.status(401).json({ error: "Token de autenticacao ausente" });
    }
    const parsed = (0, auth_1.parseToken)(token);
    if (!parsed) {
        return res.status(401).json({ error: "Token invalido" });
    }
    if (parsed.tenantId !== req.tenantId) {
        return res.status(401).json({ error: "Token nao pertence ao tenant informado" });
    }
    const tenant = (0, store_1.getTenantData)(req.tenantId);
    const user = tenant.users.find((item) => item.id === parsed.userId && item.active);
    if (!user) {
        return res.status(401).json({ error: "Usuario nao encontrado ou inativo" });
    }
    req.userId = user.id;
    req.userRole = user.role;
    return next();
}
