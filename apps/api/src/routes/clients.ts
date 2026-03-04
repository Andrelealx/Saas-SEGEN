import { Router } from "express";
import { v4 as uuidv4 } from "uuid";
import { z } from "zod";
import { getTenantData, pushActivity, updateTenantData } from "../services/store";

const clientsRouter = Router();

const createClientSchema = z.object({
  name: z.string().min(2),
  segment: z.string().optional(),
  contactName: z.string().optional(),
  email: z.string().email().optional(),
  phone: z.string().optional(),
  status: z.enum(["onboarding", "ativo", "inativo"]).default("onboarding"),
  plan: z.enum(["start", "pro", "custom"]).default("start"),
  mrr: z.number().nonnegative().default(0)
});

const updateClientSchema = z.object({
  segment: z.string().optional(),
  contactName: z.string().optional(),
  email: z.string().email().optional(),
  phone: z.string().optional(),
  status: z.enum(["onboarding", "ativo", "inativo"]).optional(),
  plan: z.enum(["start", "pro", "custom"]).optional(),
  mrr: z.number().nonnegative().optional()
});

clientsRouter.get("/clients", (req, res) => {
  const tenant = getTenantData(req.tenantId);
  return res.json({ data: tenant.clients });
});

clientsRouter.post("/clients", (req, res) => {
  const parsed = createClientSchema.safeParse(req.body);

  if (!parsed.success) {
    return res.status(400).json({ error: "Dados invalidos", details: parsed.error.flatten() });
  }

  const client = updateTenantData(req.tenantId, (tenant) => {
    const now = new Date().toISOString();

    const item = {
      id: uuidv4(),
      name: parsed.data.name,
      segment: parsed.data.segment,
      contactName: parsed.data.contactName,
      email: parsed.data.email,
      phone: parsed.data.phone,
      status: parsed.data.status,
      plan: parsed.data.plan,
      mrr: parsed.data.mrr,
      createdAt: now,
      updatedAt: now
    };

    tenant.clients.unshift(item);
    pushActivity(tenant, "cliente", `Cliente ${item.name} cadastrado no plano ${item.plan}.`);
    return item;
  });

  return res.status(201).json({ data: client });
});

clientsRouter.patch("/clients/:id", (req, res) => {
  const parsed = updateClientSchema.safeParse(req.body);

  if (!parsed.success) {
    return res.status(400).json({ error: "Dados invalidos", details: parsed.error.flatten() });
  }

  const client = updateTenantData(req.tenantId, (tenant) => {
    const item = tenant.clients.find((clientItem) => clientItem.id === req.params.id);

    if (!item) {
      return null;
    }

    if (typeof parsed.data.segment !== "undefined") {
      item.segment = parsed.data.segment;
    }

    if (typeof parsed.data.contactName !== "undefined") {
      item.contactName = parsed.data.contactName;
    }

    if (typeof parsed.data.email !== "undefined") {
      item.email = parsed.data.email;
    }

    if (typeof parsed.data.phone !== "undefined") {
      item.phone = parsed.data.phone;
    }

    if (typeof parsed.data.status !== "undefined") {
      item.status = parsed.data.status;
    }

    if (typeof parsed.data.plan !== "undefined") {
      item.plan = parsed.data.plan;
    }

    if (typeof parsed.data.mrr !== "undefined") {
      item.mrr = parsed.data.mrr;
    }

    item.updatedAt = new Date().toISOString();
    pushActivity(tenant, "cliente", `Cliente ${item.name} atualizado para status ${item.status}.`);
    return item;
  });

  if (!client) {
    return res.status(404).json({ error: "Cliente nao encontrado" });
  }

  return res.json({ data: client });
});

export { clientsRouter };
