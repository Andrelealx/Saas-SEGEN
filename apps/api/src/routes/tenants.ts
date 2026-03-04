import { Router } from "express";
import { z } from "zod";
import { getTenantData, pushActivity, updateTenantData } from "../services/store";

const tenantsRouter = Router();

const themeSchema = z.object({
  brandName: z.string().min(2),
  primaryColor: z.string().min(4),
  secondaryColor: z.string().min(4),
  logoUrl: z.string().optional().default("")
});

tenantsRouter.get("/tenants/theme", (req, res) => {
  const tenant = getTenantData(req.tenantId);

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

  const theme = updateTenantData(req.tenantId, (tenant) => {
    tenant.theme = {
      brandName: parsed.data.brandName,
      primaryColor: parsed.data.primaryColor,
      secondaryColor: parsed.data.secondaryColor,
      logoUrl: parsed.data.logoUrl || ""
    };

    pushActivity(tenant, "meta", `Branding atualizado para ${tenant.theme.brandName}.`);
    return tenant.theme;
  });

  return res.json({ data: theme });
});

export { tenantsRouter };
