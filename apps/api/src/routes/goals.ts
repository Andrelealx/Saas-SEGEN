import { Router } from "express";
import { v4 as uuidv4 } from "uuid";
import { z } from "zod";
import { getTenantData, pushActivity, updateTenantData } from "../services/store";

const goalsRouter = Router();

const createGoalSchema = z.object({
  title: z.string().min(2),
  target: z.number().positive(),
  current: z.number().nonnegative().default(0),
  unit: z.enum(["count", "currency"]).default("count"),
  period: z.enum(["mensal", "trimestral"]).default("mensal")
});

const updateGoalSchema = z.object({
  title: z.string().min(2).optional(),
  target: z.number().positive().optional(),
  current: z.number().nonnegative().optional(),
  unit: z.enum(["count", "currency"]).optional(),
  period: z.enum(["mensal", "trimestral"]).optional()
});

goalsRouter.get("/goals", (req, res) => {
  const tenant = getTenantData(req.tenantId);
  return res.json({ data: tenant.goals });
});

goalsRouter.post("/goals", (req, res) => {
  const parsed = createGoalSchema.safeParse(req.body);

  if (!parsed.success) {
    return res.status(400).json({ error: "Dados invalidos", details: parsed.error.flatten() });
  }

  const goal = updateTenantData(req.tenantId, (tenant) => {
    const now = new Date().toISOString();
    const item = {
      id: uuidv4(),
      title: parsed.data.title,
      target: parsed.data.target,
      current: parsed.data.current,
      unit: parsed.data.unit,
      period: parsed.data.period,
      createdAt: now,
      updatedAt: now
    };

    tenant.goals.unshift(item);
    pushActivity(tenant, "meta", `Nova meta criada: ${item.title}.`);
    return item;
  });

  return res.status(201).json({ data: goal });
});

goalsRouter.patch("/goals/:id", (req, res) => {
  const parsed = updateGoalSchema.safeParse(req.body);

  if (!parsed.success) {
    return res.status(400).json({ error: "Dados invalidos", details: parsed.error.flatten() });
  }

  const goal = updateTenantData(req.tenantId, (tenant) => {
    const item = tenant.goals.find((goalItem) => goalItem.id === req.params.id);

    if (!item) {
      return null;
    }

    if (typeof parsed.data.title !== "undefined") {
      item.title = parsed.data.title;
    }

    if (typeof parsed.data.target !== "undefined") {
      item.target = parsed.data.target;
    }

    if (typeof parsed.data.current !== "undefined") {
      item.current = parsed.data.current;
    }

    if (typeof parsed.data.unit !== "undefined") {
      item.unit = parsed.data.unit;
    }

    if (typeof parsed.data.period !== "undefined") {
      item.period = parsed.data.period;
    }

    item.updatedAt = new Date().toISOString();
    pushActivity(tenant, "meta", `Meta atualizada: ${item.title}.`);
    return item;
  });

  if (!goal) {
    return res.status(404).json({ error: "Meta nao encontrada" });
  }

  return res.json({ data: goal });
});

export { goalsRouter };
