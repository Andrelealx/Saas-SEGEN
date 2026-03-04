import { Router } from "express";
import { z } from "zod";
import { createToken } from "../services/auth";
import { getTenantData } from "../services/store";

const authRouter = Router();

const loginSchema = z.object({
  email: z.string().email(),
  password: z.string().min(4)
});

authRouter.post("/auth/login", (req, res) => {
  const parsed = loginSchema.safeParse(req.body);
  if (!parsed.success) {
    return res.status(400).json({
      error: "Dados de login invalidos",
      details: parsed.error.flatten()
    });
  }

  const tenant = getTenantData(req.tenantId);
  const user = tenant.users.find((item) => item.email === parsed.data.email && item.active);

  if (!user || user.password !== parsed.data.password) {
    return res.status(401).json({ error: "Email ou senha invalidos" });
  }

  const token = createToken(req.tenantId, user);

  return res.json({
    data: {
      token,
      user: {
        id: user.id,
        name: user.name,
        email: user.email,
        role: user.role
      },
      tenantId: req.tenantId
    }
  });
});

authRouter.get("/auth/me", (req, res) => {
  const tenant = getTenantData(req.tenantId);
  const user = tenant.users.find((item) => item.id === req.userId);

  if (!user) {
    return res.status(404).json({ error: "Usuario nao encontrado" });
  }

  return res.json({
    data: {
      id: user.id,
      name: user.name,
      email: user.email,
      role: user.role,
      tenantId: req.tenantId
    }
  });
});

export { authRouter };
