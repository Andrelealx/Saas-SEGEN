import { Router } from "express";
import { getTenantData } from "../services/store";

const dashboardRouter = Router();

dashboardRouter.get("/dashboard/summary", (req, res) => {
  const tenant = getTenantData(req.tenantId);
  const today = new Date();
  const todayTime = today.getTime();

  const overdueProjects = tenant.projects.filter((item) => {
    if (!item.dueDate || item.status === "entregue") {
      return false;
    }

    return new Date(item.dueDate).getTime() < todayTime;
  });

  const revenuePlanned = tenant.invoices.reduce((sum, item) => sum + item.amount, 0);
  const revenuePaid = tenant.invoices
    .filter((item) => item.status === "paga")
    .reduce((sum, item) => sum + item.amount, 0);

  const pipelineValue = tenant.proposals
    .filter((item) => item.status === "enviada" || item.status === "aprovada")
    .reduce((sum, item) => sum + item.amount, 0);

  const pendingInvoices = tenant.invoices.filter((item) => item.status !== "paga");
  const openTickets = tenant.tickets.filter((item) => item.status !== "resolvido");
  const activeClients = tenant.clients.filter((item) => item.status === "ativo");
  const onboardingClients = tenant.clients.filter((item) => item.status === "onboarding");
  const taskTodo = tenant.tasks.filter((item) => item.status === "todo").length;
  const taskDoing = tenant.tasks.filter((item) => item.status === "doing").length;

  const goalsSummary = tenant.goals.map((goal) => {
    const percent = goal.target > 0 ? Math.min(100, Math.round((goal.current / goal.target) * 100)) : 0;

    return {
      id: goal.id,
      title: goal.title,
      unit: goal.unit,
      target: goal.target,
      current: goal.current,
      progress: percent
    };
  });

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
        overdue: overdueProjects.length,
        overdueList: overdueProjects.slice(0, 5)
      },
      financial: {
        revenuePlanned,
        revenuePaid,
        receivableOpen: pendingInvoices.reduce((sum, item) => sum + item.amount, 0),
        pendingInvoices: pendingInvoices.length,
        mrr: activeClients.reduce((sum, item) => sum + item.mrr, 0),
        pipelineValue
      },
      support: {
        openTickets: openTickets.length,
        highPriorityOpen: openTickets.filter((item) => item.priority === "alta" || item.priority === "critica")
          .length,
        totalTickets: tenant.tickets.length
      },
      clients: {
        total: tenant.clients.length,
        active: activeClients.length,
        onboarding: onboardingClients.length,
        inactive: tenant.clients.filter((item) => item.status === "inativo").length
      },
      team: {
        users: tenant.users.length,
        tasksTodo: taskTodo,
        tasksDoing: taskDoing,
        tasksDone: tenant.tasks.filter((item) => item.status === "done").length
      },
      goals: goalsSummary,
      activities: tenant.activities.slice(0, 8),
      alerts: {
        overdueProjects: overdueProjects.length,
        overdueInvoices: pendingInvoices.filter((item) => new Date(item.dueDate).getTime() < todayTime).length,
        criticalTickets: openTickets.filter((item) => item.priority === "critica").length
      }
    }
  });
});

export { dashboardRouter };
