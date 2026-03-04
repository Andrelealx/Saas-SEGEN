import { Router } from "express";
import { v4 as uuidv4 } from "uuid";
import { z } from "zod";
import { getTenantData, pushActivity, updateTenantData } from "../services/store";

const catalogRouter = Router();

const createProductSchema = z.object({
  code: z.string().min(2),
  name: z.string().min(2),
  segment: z.string().optional(),
  description: z.string().optional(),
  basePrice: z.number().nonnegative(),
  setupDays: z.number().int().positive()
});

const updateProductSchema = z.object({
  name: z.string().min(2).optional(),
  segment: z.string().optional(),
  description: z.string().optional(),
  basePrice: z.number().nonnegative().optional(),
  setupDays: z.number().int().positive().optional(),
  active: z.boolean().optional()
});

catalogRouter.get("/catalog/products", (req, res) => {
  const tenant = getTenantData(req.tenantId);
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

  const product = updateTenantData(req.tenantId, (tenant) => {
    const now = new Date().toISOString();
    const item = {
      id: uuidv4(),
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
    pushActivity(tenant, "catalogo", `Produto ${item.name} cadastrado no catalogo.`);
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

  const product = updateTenantData(req.tenantId, (tenant) => {
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
    pushActivity(tenant, "catalogo", `Produto ${item.name} atualizado.`);
    return item;
  });

  if (!product) {
    return res.status(404).json({ error: "Produto nao encontrado" });
  }

  return res.json({ data: product });
});

export { catalogRouter };
