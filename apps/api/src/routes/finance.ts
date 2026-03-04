import { Router } from "express";
import { v4 as uuidv4 } from "uuid";
import { z } from "zod";
import { getTenantData, pushActivity, updateTenantData } from "../services/store";

const financeRouter = Router();

const createInvoiceSchema = z.object({
  clientName: z.string().min(2),
  description: z.string().min(2),
  amount: z.number().positive(),
  dueDate: z.string().min(4),
  status: z.enum(["pendente", "paga", "atrasada"]).default("pendente")
});

const updateInvoiceSchema = z.object({
  status: z.enum(["pendente", "paga", "atrasada"]).optional(),
  dueDate: z.string().optional()
});

financeRouter.get("/finance/invoices", (req, res) => {
  const tenant = getTenantData(req.tenantId);
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

  const invoice = updateTenantData(req.tenantId, (tenant) => {
    const now = new Date().toISOString();
    const item = {
      id: uuidv4(),
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
    pushActivity(tenant, "financeiro", `Fatura ${item.description} criada para ${item.clientName}.`);
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

  const invoice = updateTenantData(req.tenantId, (tenant) => {
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
    pushActivity(tenant, "financeiro", `Fatura ${item.description} atualizada para ${item.status}.`);
    return item;
  });

  if (!invoice) {
    return res.status(404).json({ error: "Fatura nao encontrada" });
  }

  return res.json({ data: invoice });
});

export { financeRouter };
