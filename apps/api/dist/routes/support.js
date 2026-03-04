"use strict";
Object.defineProperty(exports, "__esModule", { value: true });
exports.supportRouter = void 0;
const express_1 = require("express");
const uuid_1 = require("uuid");
const zod_1 = require("zod");
const store_1 = require("../services/store");
const supportRouter = (0, express_1.Router)();
exports.supportRouter = supportRouter;
const createTicketSchema = zod_1.z.object({
    clientName: zod_1.z.string().min(2),
    title: zod_1.z.string().min(2),
    category: zod_1.z.string().optional(),
    priority: zod_1.z.enum(["baixa", "media", "alta", "critica"]).default("media"),
    status: zod_1.z.enum(["aberto", "em_andamento", "resolvido"]).default("aberto")
});
const updateTicketSchema = zod_1.z.object({
    status: zod_1.z.enum(["aberto", "em_andamento", "resolvido"]).optional(),
    priority: zod_1.z.enum(["baixa", "media", "alta", "critica"]).optional()
});
const addMessageSchema = zod_1.z.object({
    body: zod_1.z.string().min(1),
    authorName: zod_1.z.string().min(1).default("Equipe")
});
supportRouter.get("/support/tickets", (req, res) => {
    const tenant = (0, store_1.getTenantData)(req.tenantId);
    return res.json({ data: tenant.tickets });
});
supportRouter.post("/support/tickets", (req, res) => {
    const parsed = createTicketSchema.safeParse(req.body);
    if (!parsed.success) {
        return res.status(400).json({
            error: "Dados invalidos",
            details: parsed.error.flatten()
        });
    }
    const ticket = (0, store_1.updateTenantData)(req.tenantId, (tenant) => {
        const now = new Date().toISOString();
        const item = {
            id: (0, uuid_1.v4)(),
            clientName: parsed.data.clientName,
            title: parsed.data.title,
            category: parsed.data.category,
            priority: parsed.data.priority,
            status: parsed.data.status,
            messages: [],
            createdAt: now,
            updatedAt: now
        };
        tenant.tickets.unshift(item);
        return item;
    });
    return res.status(201).json({ data: ticket });
});
supportRouter.patch("/support/tickets/:id", (req, res) => {
    const parsed = updateTicketSchema.safeParse(req.body);
    if (!parsed.success) {
        return res.status(400).json({
            error: "Dados invalidos",
            details: parsed.error.flatten()
        });
    }
    const ticket = (0, store_1.updateTenantData)(req.tenantId, (tenant) => {
        const item = tenant.tickets.find((ticketItem) => ticketItem.id === req.params.id);
        if (!item) {
            return null;
        }
        if (parsed.data.status) {
            item.status = parsed.data.status;
        }
        if (parsed.data.priority) {
            item.priority = parsed.data.priority;
        }
        item.updatedAt = new Date().toISOString();
        return item;
    });
    if (!ticket) {
        return res.status(404).json({ error: "Ticket nao encontrado" });
    }
    return res.json({ data: ticket });
});
supportRouter.post("/support/tickets/:id/messages", (req, res) => {
    const parsed = addMessageSchema.safeParse(req.body);
    if (!parsed.success) {
        return res.status(400).json({
            error: "Dados invalidos",
            details: parsed.error.flatten()
        });
    }
    const message = (0, store_1.updateTenantData)(req.tenantId, (tenant) => {
        const item = tenant.tickets.find((ticketItem) => ticketItem.id === req.params.id);
        if (!item) {
            return null;
        }
        const newMessage = {
            id: (0, uuid_1.v4)(),
            body: parsed.data.body,
            authorName: parsed.data.authorName,
            createdAt: new Date().toISOString()
        };
        item.messages.push(newMessage);
        item.updatedAt = new Date().toISOString();
        return newMessage;
    });
    if (!message) {
        return res.status(404).json({ error: "Ticket nao encontrado" });
    }
    return res.status(201).json({ data: message });
});
