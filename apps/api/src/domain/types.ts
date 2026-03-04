export type UserRole = "admin" | "comercial" | "gestao" | "operacao";

export interface User {
  id: string;
  name: string;
  email: string;
  password: string;
  role: UserRole;
  active: boolean;
  createdAt: string;
}

export interface TenantTheme {
  brandName: string;
  primaryColor: string;
  secondaryColor: string;
  logoUrl: string;
}

export type LeadStage =
  | "novo"
  | "qualificacao"
  | "diagnostico"
  | "proposta"
  | "negociacao"
  | "fechado"
  | "perdido";

export interface Lead {
  id: string;
  companyName: string;
  contactName: string;
  email?: string;
  phone?: string;
  source?: string;
  stage: LeadStage;
  notes?: string;
  createdAt: string;
  updatedAt: string;
}

export type ProjectType = "white_label" | "custom";
export type ProjectStatus = "planejado" | "em_andamento" | "homologacao" | "entregue";

export interface Project {
  id: string;
  clientName: string;
  name: string;
  type: ProjectType;
  status: ProjectStatus;
  dueDate?: string;
  createdAt: string;
  updatedAt: string;
}

export type ProposalStatus = "rascunho" | "enviada" | "aprovada" | "recusada";

export interface Proposal {
  id: string;
  clientName: string;
  title: string;
  scopeSummary: string;
  amount: number;
  status: ProposalStatus;
  validUntil?: string;
  createdAt: string;
  updatedAt: string;
}

export type InvoiceStatus = "pendente" | "paga" | "atrasada";

export interface Invoice {
  id: string;
  clientName: string;
  description: string;
  amount: number;
  dueDate: string;
  status: InvoiceStatus;
  paidAt?: string;
  createdAt: string;
  updatedAt: string;
}

export type TicketPriority = "baixa" | "media" | "alta" | "critica";
export type TicketStatus = "aberto" | "em_andamento" | "resolvido";

export interface TicketMessage {
  id: string;
  body: string;
  authorName: string;
  createdAt: string;
}

export interface Ticket {
  id: string;
  clientName: string;
  title: string;
  category?: string;
  priority: TicketPriority;
  status: TicketStatus;
  messages: TicketMessage[];
  createdAt: string;
  updatedAt: string;
}

export interface Product {
  id: string;
  code: string;
  name: string;
  segment?: string;
  description?: string;
  basePrice: number;
  setupDays: number;
  active: boolean;
  createdAt: string;
  updatedAt: string;
}

export type ClientStatus = "onboarding" | "ativo" | "inativo";
export type ClientPlan = "start" | "pro" | "custom";

export interface Client {
  id: string;
  name: string;
  segment?: string;
  contactName?: string;
  email?: string;
  phone?: string;
  status: ClientStatus;
  plan: ClientPlan;
  mrr: number;
  createdAt: string;
  updatedAt: string;
}

export type TaskPriority = "baixa" | "media" | "alta";
export type TaskStatus = "todo" | "doing" | "done";

export interface TeamTask {
  id: string;
  title: string;
  assigneeId?: string;
  priority: TaskPriority;
  status: TaskStatus;
  dueDate?: string;
  createdAt: string;
  updatedAt: string;
}

export type GoalUnit = "count" | "currency";
export type GoalPeriod = "mensal" | "trimestral";

export interface Goal {
  id: string;
  title: string;
  target: number;
  current: number;
  unit: GoalUnit;
  period: GoalPeriod;
  createdAt: string;
  updatedAt: string;
}

export interface Activity {
  id: string;
  type: "crm" | "projeto" | "financeiro" | "suporte" | "catalogo" | "cliente" | "equipe" | "meta";
  message: string;
  createdAt: string;
}

export interface TenantData {
  theme: TenantTheme;
  users: User[];
  leads: Lead[];
  projects: Project[];
  proposals: Proposal[];
  invoices: Invoice[];
  tickets: Ticket[];
  products: Product[];
  clients: Client[];
  tasks: TeamTask[];
  goals: Goal[];
  activities: Activity[];
}

export interface StoreSchema {
  tenants: Record<string, TenantData>;
}
