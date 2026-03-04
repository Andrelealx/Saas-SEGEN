"use strict";
Object.defineProperty(exports, "__esModule", { value: true });
exports.dashboardRouter = void 0;
const express_1 = require("express");
const store_1 = require("../services/store");
const dashboardRouter = (0, express_1.Router)();
exports.dashboardRouter = dashboardRouter;
dashboardRouter.get("/dashboard/summary", (req, res) => {
    const tenant = (0, store_1.getTenantData)(req.tenantId);
    const today = new Date();
    const overdueProjects = tenant.projects.filter((item) => {
        if (!item.dueDate || item.status === "entregue") {
            return false;
        }
        return new Date(item.dueDate) < today;
    }).length;
    const revenuePlanned = tenant.invoices.reduce((sum, item) => sum + item.amount, 0);
    const revenuePaid = tenant.invoices
        .filter((item) => item.status === "paga")
        .reduce((sum, item) => sum + item.amount, 0);
    const openTickets = tenant.tickets.filter((item) => item.status !== "resolvido").length;
    return res.json({
        data: {
            leads: {
                total: tenant.leads.length,
                novos: tenant.leads.filter((item) => item.stage === "novo").length,
                fechados: tenant.leads.filter((item) => item.stage === "fechado").length
            },
            projects: {
                total: tenant.projects.length,
                emAndamento: tenant.projects.filter((item) => item.status === "em_andamento").length,
                overdue: overdueProjects
            },
            financial: {
                revenuePlanned,
                revenuePaid,
                pendingInvoices: tenant.invoices.filter((item) => item.status !== "paga").length
            },
            support: {
                openTickets,
                totalTickets: tenant.tickets.length
            }
        }
    });
});
