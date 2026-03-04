"use strict";
Object.defineProperty(exports, "__esModule", { value: true });
exports.crmRouter = void 0;
const express_1 = require("express");
const uuid_1 = require("uuid");
const zod_1 = require("zod");
const store_1 = require("../services/store");
const crmRouter = (0, express_1.Router)();
exports.crmRouter = crmRouter;
const createLeadSchema = zod_1.z.object({
    companyName: zod_1.z.string().min(2),
    contactName: zod_1.z.string().min(2),
    email: zod_1.z.string().email().optional(),
    phone: zod_1.z.string().optional(),
    source: zod_1.z.string().optional(),
    stage: zod_1.z
        .enum([
        "novo",
        "qualificacao",
        "diagnostico",
        "proposta",
        "negociacao",
        "fechado",
        "perdido"
    ])
        .default("novo"),
    notes: zod_1.z.string().optional()
});
const updateLeadSchema = zod_1.z.object({
    stage: zod_1.z
        .enum([
        "novo",
        "qualificacao",
        "diagnostico",
        "proposta",
        "negociacao",
        "fechado",
        "perdido"
    ])
        .optional(),
    notes: zod_1.z.string().optional()
});
crmRouter.get("/crm/leads", (req, res) => {
    const tenant = (0, store_1.getTenantData)(req.tenantId);
    return res.json({ data: tenant.leads });
});
crmRouter.post("/crm/leads", (req, res) => {
    const parsed = createLeadSchema.safeParse(req.body);
    if (!parsed.success) {
        return res.status(400).json({
            error: "Dados invalidos",
            details: parsed.error.flatten()
        });
    }
    const lead = (0, store_1.updateTenantData)(req.tenantId, (tenant) => {
        const now = new Date().toISOString();
        const item = {
            id: (0, uuid_1.v4)(),
            companyName: parsed.data.companyName,
            contactName: parsed.data.contactName,
            email: parsed.data.email,
            phone: parsed.data.phone,
            source: parsed.data.source,
            stage: parsed.data.stage,
            notes: parsed.data.notes,
            createdAt: now,
            updatedAt: now
        };
        tenant.leads.unshift(item);
        return item;
    });
    return res.status(201).json({ data: lead });
});
crmRouter.patch("/crm/leads/:id", (req, res) => {
    const parsed = updateLeadSchema.safeParse(req.body);
    if (!parsed.success) {
        return res.status(400).json({
            error: "Dados invalidos",
            details: parsed.error.flatten()
        });
    }
    const result = (0, store_1.updateTenantData)(req.tenantId, (tenant) => {
        const lead = tenant.leads.find((item) => item.id === req.params.id);
        if (!lead) {
            return null;
        }
        if (parsed.data.stage) {
            lead.stage = parsed.data.stage;
        }
        if (typeof parsed.data.notes !== "undefined") {
            lead.notes = parsed.data.notes;
        }
        lead.updatedAt = new Date().toISOString();
        return lead;
    });
    if (!result) {
        return res.status(404).json({ error: "Lead nao encontrado" });
    }
    return res.json({ data: result });
});
