"use strict";
var __importDefault = (this && this.__importDefault) || function (mod) {
    return (mod && mod.__esModule) ? mod : { "default": mod };
};
Object.defineProperty(exports, "__esModule", { value: true });
exports.getTenantData = getTenantData;
exports.updateTenantData = updateTenantData;
const node_fs_1 = __importDefault(require("node:fs"));
const node_path_1 = __importDefault(require("node:path"));
const uuid_1 = require("uuid");
const dataDir = node_path_1.default.resolve(__dirname, "../../data");
const storePath = node_path_1.default.join(dataDir, "store.json");
const defaultMajTheme = {
    brandName: "MAJ Software",
    primaryColor: "#1D4ED8",
    secondaryColor: "#64748B",
    logoUrl: "/app/assets/maj-logo.svg"
};
let cache = null;
function nowIso() {
    return new Date().toISOString();
}
function createDefaultUser(role) {
    const usersByRole = {
        admin: { name: "Administrador", email: "admin@softhouse.com" },
        comercial: { name: "Comercial", email: "comercial@softhouse.com" },
        gestao: { name: "Gestao", email: "gestao@softhouse.com" },
        operacao: { name: "Operacao", email: "operacao@softhouse.com" }
    };
    return {
        id: (0, uuid_1.v4)(),
        name: usersByRole[role].name,
        email: usersByRole[role].email,
        password: "admin123",
        role,
        active: true,
        createdAt: nowIso()
    };
}
function createDefaultTenantData(tenantId) {
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
                id: (0, uuid_1.v4)(),
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
                id: (0, uuid_1.v4)(),
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
        ]
    };
}
function createInitialStore() {
    return {
        tenants: {
            "dev-tenant": createDefaultTenantData("dev-tenant")
        }
    };
}
function ensureStoreFile() {
    if (!node_fs_1.default.existsSync(dataDir)) {
        node_fs_1.default.mkdirSync(dataDir, { recursive: true });
    }
    if (!node_fs_1.default.existsSync(storePath)) {
        const initial = createInitialStore();
        node_fs_1.default.writeFileSync(storePath, JSON.stringify(initial, null, 2), "utf-8");
    }
}
function loadStore() {
    if (cache) {
        return cache;
    }
    ensureStoreFile();
    const raw = node_fs_1.default.readFileSync(storePath, "utf-8");
    const parsed = JSON.parse(raw);
    if (!parsed.tenants || typeof parsed.tenants !== "object") {
        cache = createInitialStore();
        persistStore(cache);
        return cache;
    }
    const didMigrate = migrateMajBranding(parsed);
    cache = parsed;
    if (didMigrate) {
        persistStore(cache);
    }
    return cache;
}
function persistStore(store) {
    cache = store;
    node_fs_1.default.writeFileSync(storePath, JSON.stringify(store, null, 2), "utf-8");
}
function ensureTenant(store, tenantId) {
    if (!store.tenants[tenantId]) {
        store.tenants[tenantId] = createDefaultTenantData(tenantId);
    }
    return store.tenants[tenantId];
}
function migrateMajBranding(store) {
    const tenant = store.tenants["dev-tenant"];
    if (!tenant) {
        return false;
    }
    let changed = false;
    if (!tenant.theme) {
        tenant.theme = { ...defaultMajTheme };
        return true;
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
    return changed;
}
function getTenantData(tenantId) {
    const store = loadStore();
    const tenant = ensureTenant(store, tenantId);
    persistStore(store);
    return tenant;
}
function updateTenantData(tenantId, updater) {
    const store = loadStore();
    const tenant = ensureTenant(store, tenantId);
    const result = updater(tenant);
    persistStore(store);
    return result;
}
