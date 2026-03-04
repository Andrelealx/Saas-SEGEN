-- Schema inicial do Sistema Central da Softhouse

CREATE TABLE IF NOT EXISTS tenants (
  id UUID PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  slug VARCHAR(80) UNIQUE NOT NULL,
  primary_color VARCHAR(20),
  secondary_color VARCHAR(20),
  logo_url TEXT,
  custom_domain VARCHAR(255),
  created_at TIMESTAMP NOT NULL DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS users (
  id UUID PRIMARY KEY,
  tenant_id UUID NOT NULL REFERENCES tenants(id),
  name VARCHAR(120) NOT NULL,
  email VARCHAR(180) NOT NULL,
  role VARCHAR(40) NOT NULL,
  password_hash TEXT NOT NULL,
  active BOOLEAN NOT NULL DEFAULT TRUE,
  created_at TIMESTAMP NOT NULL DEFAULT NOW(),
  UNIQUE (tenant_id, email)
);

CREATE TABLE IF NOT EXISTS leads (
  id UUID PRIMARY KEY,
  tenant_id UUID NOT NULL REFERENCES tenants(id),
  company_name VARCHAR(150) NOT NULL,
  contact_name VARCHAR(120) NOT NULL,
  email VARCHAR(180),
  phone VARCHAR(40),
  source VARCHAR(80),
  funnel_stage VARCHAR(50) NOT NULL,
  notes TEXT,
  created_at TIMESTAMP NOT NULL DEFAULT NOW(),
  updated_at TIMESTAMP NOT NULL DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS clients (
  id UUID PRIMARY KEY,
  tenant_id UUID NOT NULL REFERENCES tenants(id),
  legal_name VARCHAR(180) NOT NULL,
  trade_name VARCHAR(180),
  email VARCHAR(180),
  phone VARCHAR(40),
  status VARCHAR(40) NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS product_catalog (
  id UUID PRIMARY KEY,
  tenant_id UUID NOT NULL REFERENCES tenants(id),
  code VARCHAR(60) NOT NULL,
  name VARCHAR(120) NOT NULL,
  segment VARCHAR(100),
  description TEXT,
  base_price NUMERIC(12,2) NOT NULL DEFAULT 0,
  setup_days INT NOT NULL DEFAULT 7,
  active BOOLEAN NOT NULL DEFAULT TRUE,
  created_at TIMESTAMP NOT NULL DEFAULT NOW(),
  UNIQUE (tenant_id, code)
);

CREATE TABLE IF NOT EXISTS proposals (
  id UUID PRIMARY KEY,
  tenant_id UUID NOT NULL REFERENCES tenants(id),
  lead_id UUID REFERENCES leads(id),
  client_id UUID REFERENCES clients(id),
  title VARCHAR(180) NOT NULL,
  scope_summary TEXT NOT NULL,
  amount NUMERIC(12,2) NOT NULL,
  status VARCHAR(40) NOT NULL,
  valid_until DATE,
  created_at TIMESTAMP NOT NULL DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS contracts (
  id UUID PRIMARY KEY,
  tenant_id UUID NOT NULL REFERENCES tenants(id),
  proposal_id UUID NOT NULL REFERENCES proposals(id),
  file_url TEXT,
  signed_at TIMESTAMP,
  status VARCHAR(30) NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS projects (
  id UUID PRIMARY KEY,
  tenant_id UUID NOT NULL REFERENCES tenants(id),
  client_id UUID NOT NULL REFERENCES clients(id),
  proposal_id UUID REFERENCES proposals(id),
  name VARCHAR(180) NOT NULL,
  type VARCHAR(40) NOT NULL,
  status VARCHAR(40) NOT NULL,
  start_date DATE,
  due_date DATE,
  created_at TIMESTAMP NOT NULL DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS project_tasks (
  id UUID PRIMARY KEY,
  tenant_id UUID NOT NULL REFERENCES tenants(id),
  project_id UUID NOT NULL REFERENCES projects(id),
  title VARCHAR(180) NOT NULL,
  phase VARCHAR(50) NOT NULL,
  status VARCHAR(40) NOT NULL,
  assignee_id UUID REFERENCES users(id),
  due_date DATE,
  created_at TIMESTAMP NOT NULL DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS invoices (
  id UUID PRIMARY KEY,
  tenant_id UUID NOT NULL REFERENCES tenants(id),
  client_id UUID NOT NULL REFERENCES clients(id),
  project_id UUID REFERENCES projects(id),
  description VARCHAR(180) NOT NULL,
  amount NUMERIC(12,2) NOT NULL,
  due_date DATE NOT NULL,
  paid_at TIMESTAMP,
  status VARCHAR(30) NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS tickets (
  id UUID PRIMARY KEY,
  tenant_id UUID NOT NULL REFERENCES tenants(id),
  client_id UUID NOT NULL REFERENCES clients(id),
  title VARCHAR(180) NOT NULL,
  category VARCHAR(80),
  priority VARCHAR(20) NOT NULL,
  status VARCHAR(30) NOT NULL,
  sla_due_at TIMESTAMP,
  created_at TIMESTAMP NOT NULL DEFAULT NOW(),
  resolved_at TIMESTAMP
);

CREATE TABLE IF NOT EXISTS ticket_messages (
  id UUID PRIMARY KEY,
  tenant_id UUID NOT NULL REFERENCES tenants(id),
  ticket_id UUID NOT NULL REFERENCES tickets(id),
  author_user_id UUID REFERENCES users(id),
  author_client_name VARCHAR(120),
  body TEXT NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS audit_logs (
  id UUID PRIMARY KEY,
  tenant_id UUID NOT NULL REFERENCES tenants(id),
  actor_user_id UUID REFERENCES users(id),
  action VARCHAR(120) NOT NULL,
  entity VARCHAR(80) NOT NULL,
  entity_id UUID,
  metadata JSONB,
  created_at TIMESTAMP NOT NULL DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_leads_stage ON leads (tenant_id, funnel_stage);
CREATE INDEX IF NOT EXISTS idx_projects_status ON projects (tenant_id, status);
CREATE INDEX IF NOT EXISTS idx_tickets_status ON tickets (tenant_id, status);
CREATE INDEX IF NOT EXISTS idx_invoices_due_date ON invoices (tenant_id, due_date);
