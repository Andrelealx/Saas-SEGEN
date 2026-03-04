import fs from "node:fs";
import path from "node:path";
import { v4 as uuidv4 } from "uuid";
import { Activity, Goal, StoreSchema, TaskPriority, TaskStatus, TenantData, UserRole } from "../domain/types";

const dataDir = path.resolve(__dirname, "../../data");
const storePath = path.join(dataDir, "store.json");

const defaultMajTheme = {
  brandName: "MAJ Software",
  primaryColor: "#1D4ED8",
  secondaryColor: "#64748B",
  logoUrl: "/app/assets/maj-logo.svg"
};

let cache: StoreSchema | null = null;

function nowIso(): string {
  return new Date().toISOString();
}

function createDefaultUser(role: UserRole): {
  id: string;
  name: string;
  email: string;
  password: string;
  role: UserRole;
  active: boolean;
  createdAt: string;
} {
  const usersByRole: Record<UserRole, { name: string; email: string }> = {
    admin: { name: "Administrador", email: "admin@softhouse.com" },
    comercial: { name: "Comercial", email: "comercial@softhouse.com" },
    gestao: { name: "Gestao", email: "gestao@softhouse.com" },
    operacao: { name: "Operacao", email: "operacao@softhouse.com" }
  };

  return {
    id: uuidv4(),
    name: usersByRole[role].name,
    email: usersByRole[role].email,
    password: "admin123",
    role,
    active: true,
    createdAt: nowIso()
  };
}

function createDefaultGoal(title: string, target: number, unit: "count" | "currency", period: "mensal" | "trimestral", current = 0): Goal {
  return {
    id: uuidv4(),
    title,
    target,
    current,
    unit,
    period,
    createdAt: nowIso(),
    updatedAt: nowIso()
  };
}

function createDefaultTask(title: string, priority: TaskPriority, status: TaskStatus): TenantData["tasks"][number] {
  return {
    id: uuidv4(),
    title,
    priority,
    status,
    createdAt: nowIso(),
    updatedAt: nowIso()
  };
}

function createDefaultActivity(message: string, type: Activity["type"]): Activity {
  return {
    id: uuidv4(),
    message,
    type,
    createdAt: nowIso()
  };
}

function createDefaultTenantData(tenantId: string): TenantData {
  return {
    theme: {
      brandName: tenantId === "dev-tenant" ? defaultMajTheme.brandName : `Tenant ${tenantId}`,
      primaryColor: defaultMajTheme.primaryColor,
      secondaryColor: defaultMajTheme.secondaryColor,
      logoUrl: defaultMajTheme.logoUrl
    },
    users: [
      createDefaultUser("admin"),
      createDefaultUser("comercial"),
      createDefaultUser("gestao"),
      createDefaultUser("operacao")
    ],
    leads: [],
    projects: [],
    proposals: [],
    invoices: [],
    tickets: [],
    products: [
      {
        id: uuidv4(),
        code: "AGENDA-PRO",
        name: "AgendaPro",
        segment: "Saude e beleza",
        description: "Agendamento e atendimento para clinicas e saloes.",
        basePrice: 1890,
        setupDays: 7,
        active: true,
        createdAt: nowIso(),
        updatedAt: nowIso()
      },
      {
        id: uuidv4(),
        code: "MENU-GO",
        name: "MenuGo",
        segment: "Alimentacao",
        description: "Cardapio digital com pedidos e status.",
        basePrice: 2490,
        setupDays: 10,
        active: true,
        createdAt: nowIso(),
        updatedAt: nowIso()
      }
    ],
    clients: [
      {
        id: uuidv4(),
        name: "Cliente Demo MAJ",
        segment: "Saude",
        contactName: "Patricia",
        email: "patricia@clientedemo.com",
        status: "onboarding",
        plan: "pro",
        mrr: 890,
        createdAt: nowIso(),
        updatedAt: nowIso()
      }
    ],
    tasks: [
      createDefaultTask("Configurar onboarding comercial", "media", "todo"),
      createDefaultTask("Padronizar proposta WL", "alta", "doing")
    ],
    goals: [
      createDefaultGoal("Novos clientes no mes", 8, "count", "mensal", 2),
      createDefaultGoal("Receita mensal", 35000, "currency", "mensal", 9800),
      createDefaultGoal("Tickets no SLA", 90, "count", "mensal", 84)
    ],
    activities: [
      createDefaultActivity("Tenant inicial MAJ criado", "meta")
    ]
  };
}

function createInitialStore(): StoreSchema {
  return {
    tenants: {
      "dev-tenant": createDefaultTenantData("dev-tenant")
    }
  };
}

function ensureStoreFile(): void {
  if (!fs.existsSync(dataDir)) {
    fs.mkdirSync(dataDir, { recursive: true });
  }

  if (!fs.existsSync(storePath)) {
    const initial = createInitialStore();
    fs.writeFileSync(storePath, JSON.stringify(initial, null, 2), "utf-8");
  }
}

function loadStore(): StoreSchema {
  if (cache) {
    return cache;
  }

  ensureStoreFile();

  const raw = fs.readFileSync(storePath, "utf-8");
  const parsed = JSON.parse(raw) as StoreSchema;

  if (!parsed.tenants || typeof parsed.tenants !== "object") {
    cache = createInitialStore();
    persistStore(cache);
    return cache;
  }

  let changed = false;

  Object.values(parsed.tenants).forEach((tenant) => {
    changed = ensureTenantShape(tenant) || changed;
  });

  cache = parsed;
  if (changed) {
    persistStore(cache);
  }

  return cache;
}

function persistStore(store: StoreSchema): void {
  cache = store;
  fs.writeFileSync(storePath, JSON.stringify(store, null, 2), "utf-8");
}

function ensureTenant(store: StoreSchema, tenantId: string): TenantData {
  if (!store.tenants[tenantId]) {
    store.tenants[tenantId] = createDefaultTenantData(tenantId);
  }

  ensureTenantShape(store.tenants[tenantId]);
  return store.tenants[tenantId];
}

function ensureTenantShape(tenant: TenantData): boolean {
  let changed = false;

  if (!tenant.theme) {
    tenant.theme = { ...defaultMajTheme };
    changed = true;
  }

  if (!tenant.theme.brandName || tenant.theme.brandName === "Softhouse Central") {
    tenant.theme.brandName = defaultMajTheme.brandName;
    changed = true;
  }

  if (!tenant.theme.primaryColor || tenant.theme.primaryColor === "#0B5FFF") {
    tenant.theme.primaryColor = defaultMajTheme.primaryColor;
    changed = true;
  }

  if (!tenant.theme.secondaryColor || tenant.theme.secondaryColor === "#14B8A6") {
    tenant.theme.secondaryColor = defaultMajTheme.secondaryColor;
    changed = true;
  }

  if (!tenant.theme.logoUrl) {
    tenant.theme.logoUrl = defaultMajTheme.logoUrl;
    changed = true;
  }

  if (!Array.isArray(tenant.clients)) {
    tenant.clients = [];
    changed = true;
  }

  if (!Array.isArray(tenant.tasks)) {
    tenant.tasks = [];
    changed = true;
  }

  if (!Array.isArray(tenant.goals)) {
    tenant.goals = [];
    changed = true;
  }

  if (!Array.isArray(tenant.activities)) {
    tenant.activities = [];
    changed = true;
  }

  return changed;
}

export function pushActivity(tenant: TenantData, type: Activity["type"], message: string): void {
  tenant.activities.unshift({
    id: uuidv4(),
    type,
    message,
    createdAt: nowIso()
  });

  tenant.activities = tenant.activities.slice(0, 200);
}

export function getTenantData(tenantId: string): TenantData {
  const store = loadStore();
  const tenant = ensureTenant(store, tenantId);
  persistStore(store);
  return tenant;
}

export function updateTenantData<T>(tenantId: string, updater: (tenant: TenantData) => T): T {
  const store = loadStore();
  const tenant = ensureTenant(store, tenantId);
  const result = updater(tenant);
  persistStore(store);
  return result;
}
