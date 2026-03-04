import { Router } from "express";
import { v4 as uuidv4 } from "uuid";
import { z } from "zod";
import { getTenantData, pushActivity, updateTenantData } from "../services/store";

const projectsRouter = Router();

const createProjectSchema = z.object({
  clientName: z.string().min(2),
  name: z.string().min(2),
  type: z.enum(["white_label", "custom"]),
  status: z
    .enum(["planejado", "em_andamento", "homologacao", "entregue"])
    .default("planejado"),
  dueDate: z.string().optional()
});

const updateProjectSchema = z.object({
  status: z.enum(["planejado", "em_andamento", "homologacao", "entregue"]).optional(),
  dueDate: z.string().optional()
});

projectsRouter.get("/projects", (req, res) => {
  const tenant = getTenantData(req.tenantId);
  return res.json({ data: tenant.projects });
});

projectsRouter.post("/projects", (req, res) => {
  const parsed = createProjectSchema.safeParse(req.body);

  if (!parsed.success) {
    return res.status(400).json({
      error: "Dados invalidos",
      details: parsed.error.flatten()
    });
  }

  const project = updateTenantData(req.tenantId, (tenant) => {
    const now = new Date().toISOString();
    const item = {
      id: uuidv4(),
      clientName: parsed.data.clientName,
      name: parsed.data.name,
      type: parsed.data.type,
      status: parsed.data.status,
      dueDate: parsed.data.dueDate,
      createdAt: now,
      updatedAt: now
    };

    tenant.projects.unshift(item);
    pushActivity(tenant, "projeto", `Projeto ${item.name} criado para ${item.clientName}.`);
    return item;
  });

  return res.status(201).json({ data: project });
});

projectsRouter.patch("/projects/:id", (req, res) => {
  const parsed = updateProjectSchema.safeParse(req.body);

  if (!parsed.success) {
    return res.status(400).json({
      error: "Dados invalidos",
      details: parsed.error.flatten()
    });
  }

  const project = updateTenantData(req.tenantId, (tenant) => {
    const item = tenant.projects.find((projectItem) => projectItem.id === req.params.id);

    if (!item) {
      return null;
    }

    if (parsed.data.status) {
      item.status = parsed.data.status;
    }

    if (typeof parsed.data.dueDate !== "undefined") {
      item.dueDate = parsed.data.dueDate;
    }

    item.updatedAt = new Date().toISOString();
    pushActivity(tenant, "projeto", `Projeto ${item.name} atualizado para status ${item.status}.`);
    return item;
  });

  if (!project) {
    return res.status(404).json({ error: "Projeto nao encontrado" });
  }

  return res.json({ data: project });
});

export { projectsRouter };
