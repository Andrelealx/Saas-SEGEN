"use strict";
Object.defineProperty(exports, "__esModule", { value: true });
exports.catalogRouter = void 0;
const express_1 = require("express");
const uuid_1 = require("uuid");
const zod_1 = require("zod");
const store_1 = require("../services/store");
const catalogRouter = (0, express_1.Router)();
exports.catalogRouter = catalogRouter;
const createProductSchema = zod_1.z.object({
    code: zod_1.z.string().min(2),
    name: zod_1.z.string().min(2),
    segment: zod_1.z.string().optional(),
    description: zod_1.z.string().optional(),
    basePrice: zod_1.z.number().nonnegative(),
    setupDays: zod_1.z.number().int().positive()
});
const updateProductSchema = zod_1.z.object({
    name: zod_1.z.string().min(2).optional(),
    segment: zod_1.z.string().optional(),
    description: zod_1.z.string().optional(),
    basePrice: zod_1.z.number().nonnegative().optional(),
    setupDays: zod_1.z.number().int().positive().optional(),
    active: zod_1.z.boolean().optional()
});
catalogRouter.get("/catalog/products", (req, res) => {
    const tenant = (0, store_1.getTenantData)(req.tenantId);
    return res.json({ data: tenant.products });
});
catalogRouter.post("/catalog/products", (req, res) => {
    const parsed = createProductSchema.safeParse(req.body);
    if (!parsed.success) {
        return res.status(400).json({
            error: "Dados invalidos",
            details: parsed.error.flatten()
        });
    }
    const product = (0, store_1.updateTenantData)(req.tenantId, (tenant) => {
        const now = new Date().toISOString();
        const item = {
            id: (0, uuid_1.v4)(),
            code: parsed.data.code,
            name: parsed.data.name,
            segment: parsed.data.segment,
            description: parsed.data.description,
            basePrice: parsed.data.basePrice,
            setupDays: parsed.data.setupDays,
            active: true,
            createdAt: now,
            updatedAt: now
        };
        tenant.products.unshift(item);
        return item;
    });
    return res.status(201).json({ data: product });
});
catalogRouter.patch("/catalog/products/:id", (req, res) => {
    const parsed = updateProductSchema.safeParse(req.body);
    if (!parsed.success) {
        return res.status(400).json({
            error: "Dados invalidos",
            details: parsed.error.flatten()
        });
    }
    const product = (0, store_1.updateTenantData)(req.tenantId, (tenant) => {
        const item = tenant.products.find((productItem) => productItem.id === req.params.id);
        if (!item) {
            return null;
        }
        if (parsed.data.name) {
            item.name = parsed.data.name;
        }
        if (typeof parsed.data.segment !== "undefined") {
            item.segment = parsed.data.segment;
        }
        if (typeof parsed.data.description !== "undefined") {
            item.description = parsed.data.description;
        }
        if (typeof parsed.data.basePrice !== "undefined") {
            item.basePrice = parsed.data.basePrice;
        }
        if (typeof parsed.data.setupDays !== "undefined") {
            item.setupDays = parsed.data.setupDays;
        }
        if (typeof parsed.data.active !== "undefined") {
            item.active = parsed.data.active;
        }
        item.updatedAt = new Date().toISOString();
        return item;
    });
    if (!product) {
        return res.status(404).json({ error: "Produto nao encontrado" });
    }
    return res.json({ data: product });
});
