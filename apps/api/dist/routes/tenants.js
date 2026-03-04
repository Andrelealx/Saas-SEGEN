"use strict";
Object.defineProperty(exports, "__esModule", { value: true });
exports.tenantsRouter = void 0;
const express_1 = require("express");
const zod_1 = require("zod");
const store_1 = require("../services/store");
const tenantsRouter = (0, express_1.Router)();
exports.tenantsRouter = tenantsRouter;
const themeSchema = zod_1.z.object({
    brandName: zod_1.z.string().min(2),
    primaryColor: zod_1.z.string().min(4),
    secondaryColor: zod_1.z.string().min(4),
    logoUrl: zod_1.z.string().optional().default("")
});
tenantsRouter.get("/tenants/theme", (req, res) => {
    const tenant = (0, store_1.getTenantData)(req.tenantId);
    return res.json({
        data: {
            tenantId: req.tenantId,
            ...tenant.theme
        }
    });
});
tenantsRouter.put("/tenants/theme", (req, res) => {
    const parsed = themeSchema.safeParse(req.body);
    if (!parsed.success) {
        return res.status(400).json({
            error: "Dados invalidos",
            details: parsed.error.flatten()
        });
    }
    const theme = (0, store_1.updateTenantData)(req.tenantId, (tenant) => {
        tenant.theme = {
            brandName: parsed.data.brandName,
            primaryColor: parsed.data.primaryColor,
            secondaryColor: parsed.data.secondaryColor,
            logoUrl: parsed.data.logoUrl || ""
        };
        return tenant.theme;
    });
    return res.json({ data: theme });
});
