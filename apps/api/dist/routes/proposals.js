"use strict";
Object.defineProperty(exports, "__esModule", { value: true });
exports.proposalsRouter = void 0;
const express_1 = require("express");
const uuid_1 = require("uuid");
const zod_1 = require("zod");
const store_1 = require("../services/store");
const proposalsRouter = (0, express_1.Router)();
exports.proposalsRouter = proposalsRouter;
const createProposalSchema = zod_1.z.object({
    clientName: zod_1.z.string().min(2),
    title: zod_1.z.string().min(2),
    scopeSummary: zod_1.z.string().min(2),
    amount: zod_1.z.number().nonnegative(),
    status: zod_1.z.enum(["rascunho", "enviada", "aprovada", "recusada"]).default("rascunho"),
    validUntil: zod_1.z.string().optional()
});
const updateProposalSchema = zod_1.z.object({
    status: zod_1.z.enum(["rascunho", "enviada", "aprovada", "recusada"]).optional(),
    validUntil: zod_1.z.string().optional()
});
proposalsRouter.get("/proposals", (req, res) => {
    const tenant = (0, store_1.getTenantData)(req.tenantId);
    return res.json({ data: tenant.proposals });
});
proposalsRouter.post("/proposals", (req, res) => {
    const parsed = createProposalSchema.safeParse(req.body);
    if (!parsed.success) {
        return res.status(400).json({
            error: "Dados invalidos",
            details: parsed.error.flatten()
        });
    }
    const proposal = (0, store_1.updateTenantData)(req.tenantId, (tenant) => {
        const now = new Date().toISOString();
        const item = {
            id: (0, uuid_1.v4)(),
            clientName: parsed.data.clientName,
            title: parsed.data.title,
            scopeSummary: parsed.data.scopeSummary,
            amount: parsed.data.amount,
            status: parsed.data.status,
            validUntil: parsed.data.validUntil,
            createdAt: now,
            updatedAt: now
        };
        tenant.proposals.unshift(item);
        return item;
    });
    return res.status(201).json({ data: proposal });
});
proposalsRouter.patch("/proposals/:id", (req, res) => {
    const parsed = updateProposalSchema.safeParse(req.body);
    if (!parsed.success) {
        return res.status(400).json({
            error: "Dados invalidos",
            details: parsed.error.flatten()
        });
    }
    const proposal = (0, store_1.updateTenantData)(req.tenantId, (tenant) => {
        const item = tenant.proposals.find((proposalItem) => proposalItem.id === req.params.id);
        if (!item) {
            return null;
        }
        if (parsed.data.status) {
            item.status = parsed.data.status;
        }
        if (typeof parsed.data.validUntil !== "undefined") {
            item.validUntil = parsed.data.validUntil;
        }
        item.updatedAt = new Date().toISOString();
        return item;
    });
    if (!proposal) {
        return res.status(404).json({ error: "Proposta nao encontrada" });
    }
    return res.json({ data: proposal });
});
