import { Router } from "express";
import { v4 as uuidv4 } from "uuid";
import { z } from "zod";
import { LeadStage } from "../domain/types";
import { getTenantData, pushActivity, updateTenantData } from "../services/store";

const crmRouter = Router();

const createLeadSchema = z.object({
  companyName: z.string().min(2),
  contactName: z.string().min(2),
  email: z.string().email().optional(),
  phone: z.string().optional(),
  source: z.string().optional(),
  stage: z
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
  notes: z.string().optional()
});

const updateLeadSchema = z.object({
  stage: z
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
  notes: z.string().optional()
});

crmRouter.get("/crm/leads", (req, res) => {
  const tenant = getTenantData(req.tenantId);
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

  const lead = updateTenantData(req.tenantId, (tenant) => {
    const now = new Date().toISOString();
    const item = {
      id: uuidv4(),
      companyName: parsed.data.companyName,
      contactName: parsed.data.contactName,
      email: parsed.data.email,
      phone: parsed.data.phone,
      source: parsed.data.source,
      stage: parsed.data.stage as LeadStage,
      notes: parsed.data.notes,
      createdAt: now,
      updatedAt: now
    };

    tenant.leads.unshift(item);
    pushActivity(tenant, "crm", `Lead ${item.companyName} cadastrado na etapa ${item.stage}.`);
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

  const result = updateTenantData(req.tenantId, (tenant) => {
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
    pushActivity(tenant, "crm", `Lead ${lead.companyName} movido para ${lead.stage}.`);
    return lead;
  });

  if (!result) {
    return res.status(404).json({ error: "Lead nao encontrado" });
  }

  return res.json({ data: result });
});

export { crmRouter };
