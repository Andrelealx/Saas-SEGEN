import { NextFunction, Request, Response } from "express";
import { parseToken } from "../services/auth";
import { getTenantData } from "../services/store";

declare global {
  namespace Express {
    interface Request {
      tenantId: string;
      userId?: string;
      userRole?: string;
    }
  }
}

const PUBLIC_PATHS = new Set(["/", "/health", "/auth/login"]);

export function attachTenant(req: Request, res: Response, next: NextFunction): Response | void {
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

export function requireAuth(req: Request, res: Response, next: NextFunction): Response | void {
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

  const parsed = parseToken(token);
  if (!parsed) {
    return res.status(401).json({ error: "Token invalido" });
  }

  if (parsed.tenantId !== req.tenantId) {
    return res.status(401).json({ error: "Token nao pertence ao tenant informado" });
  }

  const tenant = getTenantData(req.tenantId);
  const user = tenant.users.find((item) => item.id === parsed.userId && item.active);

  if (!user) {
    return res.status(401).json({ error: "Usuario nao encontrado ou inativo" });
  }

  req.userId = user.id;
  req.userRole = user.role;
  return next();
}
