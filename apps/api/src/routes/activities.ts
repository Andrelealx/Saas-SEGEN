import { Router } from "express";
import { getTenantData } from "../services/store";

const activitiesRouter = Router();

activitiesRouter.get("/activities", (req, res) => {
  const tenant = getTenantData(req.tenantId);
  const requested = Number(req.query.limit || 30);
  const limit = Number.isNaN(requested) ? 30 : Math.max(1, Math.min(100, requested));

  return res.json({ data: tenant.activities.slice(0, limit) });
});

export { activitiesRouter };
