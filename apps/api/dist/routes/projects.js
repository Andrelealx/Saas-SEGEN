"use strict";
Object.defineProperty(exports, "__esModule", { value: true });
exports.projectsRouter = void 0;
const express_1 = require("express");
const uuid_1 = require("uuid");
const zod_1 = require("zod");
const store_1 = require("../services/store");
const projectsRouter = (0, express_1.Router)();
exports.projectsRouter = projectsRouter;
const createProjectSchema = zod_1.z.object({
    clientName: zod_1.z.string().min(2),
    name: zod_1.z.string().min(2),
    type: zod_1.z.enum(["white_label", "custom"]),
    status: zod_1.z
        .enum(["planejado", "em_andamento", "homologacao", "entregue"])
        .default("planejado"),
    dueDate: zod_1.z.string().optional()
});
const updateProjectSchema = zod_1.z.object({
    status: zod_1.z.enum(["planejado", "em_andamento", "homologacao", "entregue"]).optional(),
    dueDate: zod_1.z.string().optional()
});
projectsRouter.get("/projects", (req, res) => {
    const tenant = (0, store_1.getTenantData)(req.tenantId);
    return res.json({ data: tenant.projects });
});
projectsRouter.post("/projects", (req, res) => {
    const parsed = createProjectSchema.safeParse(req.body);
    if (!parsed.success) {
        return res.status(400).json({
            error: "Dados invalidos",
            details: parsed.error.flatten()
        });
    }
    const project = (0, store_1.updateTenantData)(req.tenantId, (tenant) => {
        const now = new Date().toISOString();
        const item = {
            id: (0, uuid_1.v4)(),
            clientName: parsed.data.clientName,
            name: parsed.data.name,
            type: parsed.data.type,
            status: parsed.data.status,
            dueDate: parsed.data.dueDate,
            createdAt: now,
            updatedAt: now
        };
        tenant.projects.unshift(item);
        return item;
    });
    return res.status(201).json({ data: project });
});
projectsRouter.patch("/projects/:id", (req, res) => {
    const parsed = updateProjectSchema.safeParse(req.body);
    if (!parsed.success) {
        return res.status(400).json({
            error: "Dados invalidos",
            details: parsed.error.flatten()
        });
    }
    const project = (0, store_1.updateTenantData)(req.tenantId, (tenant) => {
        const item = tenant.projects.find((projectItem) => projectItem.id === req.params.id);
        if (!item) {
            return null;
        }
        if (parsed.data.status) {
            item.status = parsed.data.status;
        }
        if (typeof parsed.data.dueDate !== "undefined") {
            item.dueDate = parsed.data.dueDate;
        }
        item.updatedAt = new Date().toISOString();
        return item;
    });
    if (!project) {
        return res.status(404).json({ error: "Projeto nao encontrado" });
    }
    return res.json({ data: project });
});
