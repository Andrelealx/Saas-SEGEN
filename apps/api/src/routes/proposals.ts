import { Router } from "express";
import { v4 as uuidv4 } from "uuid";
import { z } from "zod";
import { getTenantData, pushActivity, updateTenantData } from "../services/store";

const proposalsRouter = Router();

const createProposalSchema = z.object({
  clientName: z.string().min(2),
  title: z.string().min(2),
  scopeSummary: z.string().min(2),
  amount: z.number().nonnegative(),
  status: z.enum(["rascunho", "enviada", "aprovada", "recusada"]).default("rascunho"),
  validUntil: z.string().optional()
});

const updateProposalSchema = z.object({
  status: z.enum(["rascunho", "enviada", "aprovada", "recusada"]).optional(),
  validUntil: z.string().optional()
});

proposalsRouter.get("/proposals", (req, res) => {
  const tenant = getTenantData(req.tenantId);
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

  const proposal = updateTenantData(req.tenantId, (tenant) => {
    const now = new Date().toISOString();
    const item = {
      id: uuidv4(),
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
    pushActivity(tenant, "crm", `Proposta ${item.title} criada para ${item.clientName}.`);
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

  const proposal = updateTenantData(req.tenantId, (tenant) => {
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
    pushActivity(tenant, "crm", `Proposta ${item.title} alterada para ${item.status}.`);
    return item;
  });

  if (!proposal) {
    return res.status(404).json({ error: "Proposta nao encontrada" });
  }

  return res.json({ data: proposal });
});

export { proposalsRouter };
