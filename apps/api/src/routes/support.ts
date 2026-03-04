import { Router } from "express";
import { v4 as uuidv4 } from "uuid";
import { z } from "zod";
import { getTenantData, pushActivity, updateTenantData } from "../services/store";

const supportRouter = Router();

const createTicketSchema = z.object({
  clientName: z.string().min(2),
  title: z.string().min(2),
  category: z.string().optional(),
  priority: z.enum(["baixa", "media", "alta", "critica"]).default("media"),
  status: z.enum(["aberto", "em_andamento", "resolvido"]).default("aberto")
});

const updateTicketSchema = z.object({
  status: z.enum(["aberto", "em_andamento", "resolvido"]).optional(),
  priority: z.enum(["baixa", "media", "alta", "critica"]).optional()
});

const addMessageSchema = z.object({
  body: z.string().min(1),
  authorName: z.string().min(1).default("Equipe")
});

supportRouter.get("/support/tickets", (req, res) => {
  const tenant = getTenantData(req.tenantId);
  return res.json({ data: tenant.tickets });
});

supportRouter.post("/support/tickets", (req, res) => {
  const parsed = createTicketSchema.safeParse(req.body);

  if (!parsed.success) {
    return res.status(400).json({
      error: "Dados invalidos",
      details: parsed.error.flatten()
    });
  }

  const ticket = updateTenantData(req.tenantId, (tenant) => {
    const now = new Date().toISOString();
    const item = {
      id: uuidv4(),
      clientName: parsed.data.clientName,
      title: parsed.data.title,
      category: parsed.data.category,
      priority: parsed.data.priority,
      status: parsed.data.status,
      messages: [],
      createdAt: now,
      updatedAt: now
    };

    tenant.tickets.unshift(item);
    pushActivity(tenant, "suporte", `Ticket ${item.title} aberto para ${item.clientName}.`);
    return item;
  });

  return res.status(201).json({ data: ticket });
});

supportRouter.patch("/support/tickets/:id", (req, res) => {
  const parsed = updateTicketSchema.safeParse(req.body);

  if (!parsed.success) {
    return res.status(400).json({
      error: "Dados invalidos",
      details: parsed.error.flatten()
    });
  }

  const ticket = updateTenantData(req.tenantId, (tenant) => {
    const item = tenant.tickets.find((ticketItem) => ticketItem.id === req.params.id);

    if (!item) {
      return null;
    }

    if (parsed.data.status) {
      item.status = parsed.data.status;
    }

    if (parsed.data.priority) {
      item.priority = parsed.data.priority;
    }

    item.updatedAt = new Date().toISOString();
    pushActivity(tenant, "suporte", `Ticket ${item.title} alterado para ${item.status}.`);
    return item;
  });

  if (!ticket) {
    return res.status(404).json({ error: "Ticket nao encontrado" });
  }

  return res.json({ data: ticket });
});

supportRouter.post("/support/tickets/:id/messages", (req, res) => {
  const parsed = addMessageSchema.safeParse(req.body);

  if (!parsed.success) {
    return res.status(400).json({
      error: "Dados invalidos",
      details: parsed.error.flatten()
    });
  }

  const message = updateTenantData(req.tenantId, (tenant) => {
    const item = tenant.tickets.find((ticketItem) => ticketItem.id === req.params.id);

    if (!item) {
      return null;
    }

    const newMessage = {
      id: uuidv4(),
      body: parsed.data.body,
      authorName: parsed.data.authorName,
      createdAt: new Date().toISOString()
    };

    item.messages.push(newMessage);
    item.updatedAt = new Date().toISOString();
    pushActivity(tenant, "suporte", `Nova mensagem no ticket ${item.title}.`);
    return newMessage;
  });

  if (!message) {
    return res.status(404).json({ error: "Ticket nao encontrado" });
  }

  return res.status(201).json({ data: message });
});

export { supportRouter };
