"use strict";
Object.defineProperty(exports, "__esModule", { value: true });
exports.financeRouter = void 0;
const express_1 = require("express");
const uuid_1 = require("uuid");
const zod_1 = require("zod");
const store_1 = require("../services/store");
const financeRouter = (0, express_1.Router)();
exports.financeRouter = financeRouter;
const createInvoiceSchema = zod_1.z.object({
    clientName: zod_1.z.string().min(2),
    description: zod_1.z.string().min(2),
    amount: zod_1.z.number().positive(),
    dueDate: zod_1.z.string().min(4),
    status: zod_1.z.enum(["pendente", "paga", "atrasada"]).default("pendente")
});
const updateInvoiceSchema = zod_1.z.object({
    status: zod_1.z.enum(["pendente", "paga", "atrasada"]).optional(),
    dueDate: zod_1.z.string().optional()
});
financeRouter.get("/finance/invoices", (req, res) => {
    const tenant = (0, store_1.getTenantData)(req.tenantId);
    return res.json({ data: tenant.invoices });
});
financeRouter.post("/finance/invoices", (req, res) => {
    const parsed = createInvoiceSchema.safeParse(req.body);
    if (!parsed.success) {
        return res.status(400).json({
            error: "Dados invalidos",
            details: parsed.error.flatten()
        });
    }
    const invoice = (0, store_1.updateTenantData)(req.tenantId, (tenant) => {
        const now = new Date().toISOString();
        const item = {
            id: (0, uuid_1.v4)(),
            clientName: parsed.data.clientName,
            description: parsed.data.description,
            amount: parsed.data.amount,
            dueDate: parsed.data.dueDate,
            status: parsed.data.status,
            paidAt: parsed.data.status === "paga" ? now : undefined,
            createdAt: now,
            updatedAt: now
        };
        tenant.invoices.unshift(item);
        return item;
    });
    return res.status(201).json({ data: invoice });
});
financeRouter.patch("/finance/invoices/:id", (req, res) => {
    const parsed = updateInvoiceSchema.safeParse(req.body);
    if (!parsed.success) {
        return res.status(400).json({
            error: "Dados invalidos",
            details: parsed.error.flatten()
        });
    }
    const invoice = (0, store_1.updateTenantData)(req.tenantId, (tenant) => {
        const item = tenant.invoices.find((invoiceItem) => invoiceItem.id === req.params.id);
        if (!item) {
            return null;
        }
        if (parsed.data.status) {
            item.status = parsed.data.status;
            if (parsed.data.status === "paga") {
                item.paidAt = new Date().toISOString();
            }
        }
        if (typeof parsed.data.dueDate !== "undefined") {
            item.dueDate = parsed.data.dueDate;
        }
        item.updatedAt = new Date().toISOString();
        return item;
    });
    if (!invoice) {
        return res.status(404).json({ error: "Fatura nao encontrada" });
    }
    return res.json({ data: invoice });
});
