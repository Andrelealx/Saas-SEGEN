"use strict";
Object.defineProperty(exports, "__esModule", { value: true });
exports.authRouter = void 0;
const express_1 = require("express");
const zod_1 = require("zod");
const auth_1 = require("../services/auth");
const store_1 = require("../services/store");
const authRouter = (0, express_1.Router)();
exports.authRouter = authRouter;
const loginSchema = zod_1.z.object({
    email: zod_1.z.string().email(),
    password: zod_1.z.string().min(4)
});
authRouter.post("/auth/login", (req, res) => {
    const parsed = loginSchema.safeParse(req.body);
    if (!parsed.success) {
        return res.status(400).json({
            error: "Dados de login invalidos",
            details: parsed.error.flatten()
        });
    }
    const tenant = (0, store_1.getTenantData)(req.tenantId);
    const user = tenant.users.find((item) => item.email === parsed.data.email && item.active);
    if (!user || user.password !== parsed.data.password) {
        return res.status(401).json({ error: "Email ou senha invalidos" });
    }
    const token = (0, auth_1.createToken)(req.tenantId, user);
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
    const tenant = (0, store_1.getTenantData)(req.tenantId);
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
