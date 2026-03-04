import { Router } from "express";
import { v4 as uuidv4 } from "uuid";
import { z } from "zod";
import { getTenantData, pushActivity, updateTenantData } from "../services/store";

const teamRouter = Router();

const createTaskSchema = z.object({
  title: z.string().min(2),
  assigneeId: z.string().optional(),
  priority: z.enum(["baixa", "media", "alta"]).default("media"),
  status: z.enum(["todo", "doing", "done"]).default("todo"),
  dueDate: z.string().optional()
});

const updateTaskSchema = z.object({
  title: z.string().min(2).optional(),
  assigneeId: z.string().optional(),
  priority: z.enum(["baixa", "media", "alta"]).optional(),
  status: z.enum(["todo", "doing", "done"]).optional(),
  dueDate: z.string().optional()
});

teamRouter.get("/team/users", (req, res) => {
  const tenant = getTenantData(req.tenantId);

  const users = tenant.users.map((item) => ({
    id: item.id,
    name: item.name,
    email: item.email,
    role: item.role,
    active: item.active,
    createdAt: item.createdAt
  }));

  return res.json({ data: users });
});

teamRouter.get("/team/tasks", (req, res) => {
  const tenant = getTenantData(req.tenantId);
  return res.json({ data: tenant.tasks });
});

teamRouter.get("/team/workload", (req, res) => {
  const tenant = getTenantData(req.tenantId);

  const data = tenant.users.map((user) => {
    const related = tenant.tasks.filter((task) => task.assigneeId === user.id);

    return {
      userId: user.id,
      name: user.name,
      role: user.role,
      todo: related.filter((task) => task.status === "todo").length,
      doing: related.filter((task) => task.status === "doing").length,
      done: related.filter((task) => task.status === "done").length,
      total: related.length
    };
  });

  return res.json({ data });
});

teamRouter.post("/team/tasks", (req, res) => {
  const parsed = createTaskSchema.safeParse(req.body);

  if (!parsed.success) {
    return res.status(400).json({ error: "Dados invalidos", details: parsed.error.flatten() });
  }

  const task = updateTenantData(req.tenantId, (tenant) => {
    if (parsed.data.assigneeId) {
      const hasUser = tenant.users.some((item) => item.id === parsed.data.assigneeId);
      if (!hasUser) {
        return null;
      }
    }

    const now = new Date().toISOString();
    const item = {
      id: uuidv4(),
      title: parsed.data.title,
      assigneeId: parsed.data.assigneeId,
      priority: parsed.data.priority,
      status: parsed.data.status,
      dueDate: parsed.data.dueDate,
      createdAt: now,
      updatedAt: now
    };

    tenant.tasks.unshift(item);
    pushActivity(tenant, "equipe", `Tarefa interna criada: ${item.title}.`);
    return item;
  });

  if (!task) {
    return res.status(404).json({ error: "Responsavel nao encontrado" });
  }

  return res.status(201).json({ data: task });
});

teamRouter.patch("/team/tasks/:id", (req, res) => {
  const parsed = updateTaskSchema.safeParse(req.body);

  if (!parsed.success) {
    return res.status(400).json({ error: "Dados invalidos", details: parsed.error.flatten() });
  }

  const task = updateTenantData(req.tenantId, (tenant) => {
    const item = tenant.tasks.find((taskItem) => taskItem.id === req.params.id);

    if (!item) {
      return null;
    }

    if (typeof parsed.data.title !== "undefined") {
      item.title = parsed.data.title;
    }

    if (typeof parsed.data.assigneeId !== "undefined") {
      const hasUser = tenant.users.some((user) => user.id === parsed.data.assigneeId);
      if (!hasUser) {
        return null;
      }
      item.assigneeId = parsed.data.assigneeId;
    }

    if (typeof parsed.data.priority !== "undefined") {
      item.priority = parsed.data.priority;
    }

    if (typeof parsed.data.status !== "undefined") {
      item.status = parsed.data.status;
    }

    if (typeof parsed.data.dueDate !== "undefined") {
      item.dueDate = parsed.data.dueDate;
    }

    item.updatedAt = new Date().toISOString();
    pushActivity(tenant, "equipe", `Tarefa interna atualizada: ${item.title} (${item.status}).`);
    return item;
  });

  if (!task) {
    return res.status(404).json({ error: "Tarefa nao encontrada ou responsavel invalido" });
  }

  return res.json({ data: task });
});

export { teamRouter };
