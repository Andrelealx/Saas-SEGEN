const state = {
  token: localStorage.getItem("sc_token") || "",
  tenantId: localStorage.getItem("sc_tenant") || "dev-tenant",
  user: JSON.parse(localStorage.getItem("sc_user") || "null"),
  page: "dashboard"
};

const DEFAULT_THEME = {
  brandName: "MAJ Software",
  primaryColor: "#1D4ED8",
  secondaryColor: "#64748B",
  logoUrl: "/app/assets/maj-logo.svg"
};

const pageTitles = {
  dashboard: "Dashboard",
  crm: "CRM",
  projects: "Projetos",
  proposals: "Propostas",
  finance: "Financeiro",
  support: "Suporte",
  catalog: "Catalogo White-Label",
  branding: "Branding"
};

const loginScreen = document.getElementById("login-screen");
const appScreen = document.getElementById("app-screen");
const loginForm = document.getElementById("login-form");
const loginError = document.getElementById("login-error");
const tenantInput = document.getElementById("tenant-input");
const emailInput = document.getElementById("email-input");
const passwordInput = document.getElementById("password-input");
const nav = document.getElementById("nav");
const pageTitle = document.getElementById("page-title");
const pageContent = document.getElementById("page-content");
const userPill = document.getElementById("user-pill");
const brandName = document.getElementById("brand-name");
const brandTenant = document.getElementById("brand-tenant");
const brandLogo = document.getElementById("brand-logo");
const brandBadge = document.getElementById("brand-badge");
const refreshButton = document.getElementById("refresh-page");
const logoutButton = document.getElementById("logout-btn");

function saveSession() {
  localStorage.setItem("sc_token", state.token);
  localStorage.setItem("sc_tenant", state.tenantId);
  localStorage.setItem("sc_user", JSON.stringify(state.user));
}

function clearSession() {
  state.token = "";
  state.tenantId = "dev-tenant";
  state.user = null;
  localStorage.removeItem("sc_token");
  localStorage.removeItem("sc_tenant");
  localStorage.removeItem("sc_user");
}

function escapeHtml(value) {
  return String(value || "")
    .replaceAll("&", "&amp;")
    .replaceAll("<", "&lt;")
    .replaceAll(">", "&gt;")
    .replaceAll('"', "&quot;")
    .replaceAll("'", "&#039;");
}

function formatCurrency(value) {
  return new Intl.NumberFormat("pt-BR", {
    style: "currency",
    currency: "BRL"
  }).format(Number(value || 0));
}

function formatDate(value) {
  if (!value) {
    return "-";
  }

  const date = new Date(value);
  if (Number.isNaN(date.getTime())) {
    return value;
  }

  return date.toLocaleDateString("pt-BR");
}

function getErrorMessage(error, fallback = "Falha na requisicao") {
  if (!error) {
    return fallback;
  }

  if (typeof error === "string") {
    return error;
  }

  if (error.message) {
    return error.message;
  }

  return fallback;
}

async function request(path, options = {}) {
  const {
    method = "GET",
    body,
    tenantId = state.tenantId,
    useAuth = true
  } = options;

  const headers = {
    "Content-Type": "application/json",
    "x-tenant-id": tenantId || "dev-tenant"
  };

  if (useAuth && state.token) {
    headers.Authorization = `Bearer ${state.token}`;
  }

  const response = await fetch(path, {
    method,
    headers,
    body: body ? JSON.stringify(body) : undefined
  });

  const payload = await response.json().catch(() => ({}));

  if (!response.ok) {
    throw new Error(payload.error || `Erro ${response.status}`);
  }

  return payload;
}

function setTheme(theme) {
  if (!theme) {
    return;
  }

  const resolvedTheme = {
    brandName: theme.brandName || DEFAULT_THEME.brandName,
    primaryColor: theme.primaryColor || DEFAULT_THEME.primaryColor,
    secondaryColor: theme.secondaryColor || DEFAULT_THEME.secondaryColor,
    logoUrl: theme.logoUrl || DEFAULT_THEME.logoUrl
  };

  document.documentElement.style.setProperty("--primary", resolvedTheme.primaryColor);
  document.documentElement.style.setProperty("--secondary", resolvedTheme.secondaryColor);
  brandName.textContent = resolvedTheme.brandName;
  brandTenant.textContent = state.tenantId;
  brandBadge.textContent = (resolvedTheme.brandName || "MAJ")
    .split(" ")
    .slice(0, 2)
    .map((item) => item[0]?.toUpperCase() || "")
    .join("")
    .slice(0, 2);

  if (brandLogo) {
    brandLogo.src = resolvedTheme.logoUrl;
    brandLogo.alt = `Logo ${resolvedTheme.brandName}`;
    brandLogo.classList.remove("hidden");

    brandLogo.onerror = () => {
      brandLogo.classList.add("hidden");
      brandBadge.classList.remove("hidden");
    };

    brandLogo.onload = () => {
      brandLogo.classList.remove("hidden");
      brandBadge.classList.add("hidden");
    };
  }
}

function showLoginScreen() {
  loginScreen.classList.remove("hidden");
  appScreen.classList.add("hidden");
}

function showAppScreen() {
  loginScreen.classList.add("hidden");
  appScreen.classList.remove("hidden");
}

function setActiveNav(page) {
  state.page = page;
  pageTitle.textContent = pageTitles[page] || "Sistema";

  document.querySelectorAll(".nav-item").forEach((node) => {
    node.classList.toggle("active", node.dataset.page === page);
  });
}

function showInlineError(message) {
  pageContent.innerHTML = `<div class="empty-state">${escapeHtml(message)}</div>`;
}

async function renderDashboard() {
  const response = await request("/dashboard/summary");
  const summary = response.data;

  pageContent.innerHTML = `
    <div class="grid three">
      <article class="card">
        <p class="small">Leads totais</p>
        <p class="metric">${summary.leads.total}</p>
        <span class="badge">Fechados: ${summary.leads.fechados}</span>
      </article>
      <article class="card">
        <p class="small">Projetos em andamento</p>
        <p class="metric">${summary.projects.emAndamento}</p>
        <span class="badge">Atrasados: ${summary.projects.overdue}</span>
      </article>
      <article class="card">
        <p class="small">Tickets abertos</p>
        <p class="metric">${summary.support.openTickets}</p>
        <span class="badge">Total: ${summary.support.totalTickets}</span>
      </article>
    </div>
    <div class="grid two" style="margin-top: 16px;">
      <article class="card">
        <h3>Financeiro</h3>
        <p class="small">Receita planejada</p>
        <p class="metric">${formatCurrency(summary.financial.revenuePlanned)}</p>
        <p class="small">Receita recebida: ${formatCurrency(summary.financial.revenuePaid)}</p>
      </article>
      <article class="card">
        <h3>Operacao</h3>
        <p class="small">Leads novos</p>
        <p class="metric">${summary.leads.novos}</p>
        <p class="small">Projetos ativos: ${summary.projects.total}</p>
      </article>
    </div>
  `;
}

async function renderCrm() {
  const response = await request("/crm/leads");
  const leads = response.data;

  const rows = leads
    .map(
      (lead) => `
        <tr>
          <td>${escapeHtml(lead.companyName)}</td>
          <td>${escapeHtml(lead.contactName)}</td>
          <td>${escapeHtml(lead.email || "-")}</td>
          <td>
            <select data-action="lead-stage" data-id="${lead.id}">
              ${[
                "novo",
                "qualificacao",
                "diagnostico",
                "proposta",
                "negociacao",
                "fechado",
                "perdido"
              ]
                .map(
                  (stage) =>
                    `<option value="${stage}" ${lead.stage === stage ? "selected" : ""}>${stage}</option>`
                )
                .join("")}
            </select>
          </td>
          <td>${formatDate(lead.createdAt)}</td>
        </tr>
      `
    )
    .join("");

  pageContent.innerHTML = `
    <div class="page-head">
      <h3>Leads</h3>
      <span class="badge">${leads.length} registros</span>
    </div>
    <form id="crm-form" class="card">
      <div class="form-grid">
        <label>Empresa<input name="companyName" required /></label>
        <label>Contato<input name="contactName" required /></label>
        <label>Email<input name="email" type="email" /></label>
        <label>Telefone<input name="phone" /></label>
        <label>Origem<input name="source" placeholder="Instagram, Indicacao..." /></label>
        <label>Etapa
          <select name="stage">
            <option value="novo">novo</option>
            <option value="qualificacao">qualificacao</option>
            <option value="diagnostico">diagnostico</option>
            <option value="proposta">proposta</option>
            <option value="negociacao">negociacao</option>
            <option value="fechado">fechado</option>
            <option value="perdido">perdido</option>
          </select>
        </label>
        <label class="full">Observacoes<textarea name="notes"></textarea></label>
      </div>
      <button class="btn btn-primary" type="submit">Salvar lead</button>
    </form>

    <div class="table-wrap" style="margin-top: 12px;">
      <table>
        <thead>
          <tr>
            <th>Empresa</th>
            <th>Contato</th>
            <th>Email</th>
            <th>Etapa</th>
            <th>Criado</th>
          </tr>
        </thead>
        <tbody>${rows || ""}</tbody>
      </table>
    </div>
  `;

  if (!rows) {
    pageContent.querySelector("tbody").innerHTML = `<tr><td colspan="5"><div class="small">Nenhum lead ainda.</div></td></tr>`;
  }

  const form = document.getElementById("crm-form");
  form.addEventListener("submit", async (event) => {
    event.preventDefault();
    const formData = new FormData(form);

    try {
      await request("/crm/leads", {
        method: "POST",
        body: {
          companyName: formData.get("companyName"),
          contactName: formData.get("contactName"),
          email: formData.get("email") || undefined,
          phone: formData.get("phone") || undefined,
          source: formData.get("source") || undefined,
          stage: formData.get("stage"),
          notes: formData.get("notes") || undefined
        }
      });
      await renderCurrentPage();
    } catch (error) {
      showInlineError(getErrorMessage(error));
    }
  });

  pageContent.querySelectorAll('[data-action="lead-stage"]').forEach((node) => {
    node.addEventListener("change", async (event) => {
      const select = event.target;
      try {
        await request(`/crm/leads/${select.dataset.id}`, {
          method: "PATCH",
          body: { stage: select.value }
        });
      } catch (error) {
        alert(getErrorMessage(error));
      }
    });
  });
}

async function renderProjects() {
  const response = await request("/projects");
  const projects = response.data;

  const rows = projects
    .map(
      (project) => `
        <tr>
          <td>${escapeHtml(project.name)}</td>
          <td>${escapeHtml(project.clientName)}</td>
          <td>${escapeHtml(project.type)}</td>
          <td>
            <select data-action="project-status" data-id="${project.id}">
              ${["planejado", "em_andamento", "homologacao", "entregue"]
                .map(
                  (status) =>
                    `<option value="${status}" ${project.status === status ? "selected" : ""}>${status}</option>`
                )
                .join("")}
            </select>
          </td>
          <td>${formatDate(project.dueDate)}</td>
        </tr>
      `
    )
    .join("");

  pageContent.innerHTML = `
    <div class="page-head">
      <h3>Projetos</h3>
      <span class="badge">${projects.length} projetos</span>
    </div>
    <form id="projects-form" class="card">
      <div class="form-grid">
        <label>Nome do projeto<input name="name" required /></label>
        <label>Cliente<input name="clientName" required /></label>
        <label>Tipo
          <select name="type">
            <option value="white_label">white_label</option>
            <option value="custom">custom</option>
          </select>
        </label>
        <label>Prazo<input name="dueDate" type="date" /></label>
      </div>
      <button class="btn btn-primary" type="submit">Criar projeto</button>
    </form>
    <div class="table-wrap" style="margin-top: 12px;">
      <table>
        <thead>
          <tr>
            <th>Projeto</th>
            <th>Cliente</th>
            <th>Tipo</th>
            <th>Status</th>
            <th>Prazo</th>
          </tr>
        </thead>
        <tbody>${rows || ""}</tbody>
      </table>
    </div>
  `;

  if (!rows) {
    pageContent.querySelector("tbody").innerHTML = `<tr><td colspan="5"><div class="small">Nenhum projeto ainda.</div></td></tr>`;
  }

  document.getElementById("projects-form").addEventListener("submit", async (event) => {
    event.preventDefault();
    const formData = new FormData(event.target);

    try {
      await request("/projects", {
        method: "POST",
        body: {
          name: formData.get("name"),
          clientName: formData.get("clientName"),
          type: formData.get("type"),
          dueDate: formData.get("dueDate") || undefined
        }
      });
      await renderCurrentPage();
    } catch (error) {
      showInlineError(getErrorMessage(error));
    }
  });

  pageContent.querySelectorAll('[data-action="project-status"]').forEach((node) => {
    node.addEventListener("change", async (event) => {
      const select = event.target;
      try {
        await request(`/projects/${select.dataset.id}`, {
          method: "PATCH",
          body: { status: select.value }
        });
      } catch (error) {
        alert(getErrorMessage(error));
      }
    });
  });
}

async function renderProposals() {
  const response = await request("/proposals");
  const proposals = response.data;

  const rows = proposals
    .map(
      (item) => `
        <tr>
          <td>${escapeHtml(item.title)}</td>
          <td>${escapeHtml(item.clientName)}</td>
          <td>${formatCurrency(item.amount)}</td>
          <td>
            <select data-action="proposal-status" data-id="${item.id}">
              ${["rascunho", "enviada", "aprovada", "recusada"]
                .map(
                  (status) =>
                    `<option value="${status}" ${item.status === status ? "selected" : ""}>${status}</option>`
                )
                .join("")}
            </select>
          </td>
          <td>${formatDate(item.validUntil)}</td>
        </tr>
      `
    )
    .join("");

  pageContent.innerHTML = `
    <div class="page-head">
      <h3>Propostas</h3>
      <span class="badge">${proposals.length} propostas</span>
    </div>
    <form id="proposal-form" class="card">
      <div class="form-grid">
        <label>Titulo<input name="title" required /></label>
        <label>Cliente<input name="clientName" required /></label>
        <label>Valor (R$)<input name="amount" type="number" step="0.01" required /></label>
        <label>Valida ate<input name="validUntil" type="date" /></label>
        <label class="full">Resumo do escopo<textarea name="scopeSummary" required></textarea></label>
      </div>
      <button class="btn btn-primary" type="submit">Salvar proposta</button>
    </form>
    <div class="table-wrap" style="margin-top: 12px;">
      <table>
        <thead>
          <tr>
            <th>Titulo</th>
            <th>Cliente</th>
            <th>Valor</th>
            <th>Status</th>
            <th>Validade</th>
          </tr>
        </thead>
        <tbody>${rows || ""}</tbody>
      </table>
    </div>
  `;

  if (!rows) {
    pageContent.querySelector("tbody").innerHTML = `<tr><td colspan="5"><div class="small">Nenhuma proposta ainda.</div></td></tr>`;
  }

  document.getElementById("proposal-form").addEventListener("submit", async (event) => {
    event.preventDefault();
    const formData = new FormData(event.target);

    try {
      await request("/proposals", {
        method: "POST",
        body: {
          title: formData.get("title"),
          clientName: formData.get("clientName"),
          amount: Number(formData.get("amount")),
          validUntil: formData.get("validUntil") || undefined,
          scopeSummary: formData.get("scopeSummary"),
          status: "rascunho"
        }
      });
      await renderCurrentPage();
    } catch (error) {
      showInlineError(getErrorMessage(error));
    }
  });

  pageContent.querySelectorAll('[data-action="proposal-status"]').forEach((node) => {
    node.addEventListener("change", async (event) => {
      const select = event.target;
      try {
        await request(`/proposals/${select.dataset.id}`, {
          method: "PATCH",
          body: { status: select.value }
        });
      } catch (error) {
        alert(getErrorMessage(error));
      }
    });
  });
}

async function renderFinance() {
  const response = await request("/finance/invoices");
  const invoices = response.data;

  const rows = invoices
    .map(
      (item) => `
        <tr>
          <td>${escapeHtml(item.description)}</td>
          <td>${escapeHtml(item.clientName)}</td>
          <td>${formatCurrency(item.amount)}</td>
          <td>${formatDate(item.dueDate)}</td>
          <td>
            <select data-action="invoice-status" data-id="${item.id}">
              ${["pendente", "paga", "atrasada"]
                .map(
                  (status) =>
                    `<option value="${status}" ${item.status === status ? "selected" : ""}>${status}</option>`
                )
                .join("")}
            </select>
          </td>
        </tr>
      `
    )
    .join("");

  pageContent.innerHTML = `
    <div class="page-head">
      <h3>Contas a receber</h3>
      <span class="badge">${invoices.length} faturas</span>
    </div>
    <form id="finance-form" class="card">
      <div class="form-grid">
        <label>Descricao<input name="description" required /></label>
        <label>Cliente<input name="clientName" required /></label>
        <label>Valor<input name="amount" type="number" step="0.01" required /></label>
        <label>Vencimento<input name="dueDate" type="date" required /></label>
      </div>
      <button class="btn btn-primary" type="submit">Lancar fatura</button>
    </form>
    <div class="table-wrap" style="margin-top: 12px;">
      <table>
        <thead>
          <tr>
            <th>Descricao</th>
            <th>Cliente</th>
            <th>Valor</th>
            <th>Vencimento</th>
            <th>Status</th>
          </tr>
        </thead>
        <tbody>${rows || ""}</tbody>
      </table>
    </div>
  `;

  if (!rows) {
    pageContent.querySelector("tbody").innerHTML = `<tr><td colspan="5"><div class="small">Nenhuma fatura ainda.</div></td></tr>`;
  }

  document.getElementById("finance-form").addEventListener("submit", async (event) => {
    event.preventDefault();
    const formData = new FormData(event.target);

    try {
      await request("/finance/invoices", {
        method: "POST",
        body: {
          description: formData.get("description"),
          clientName: formData.get("clientName"),
          amount: Number(formData.get("amount")),
          dueDate: formData.get("dueDate"),
          status: "pendente"
        }
      });
      await renderCurrentPage();
    } catch (error) {
      showInlineError(getErrorMessage(error));
    }
  });

  pageContent.querySelectorAll('[data-action="invoice-status"]').forEach((node) => {
    node.addEventListener("change", async (event) => {
      const select = event.target;
      try {
        await request(`/finance/invoices/${select.dataset.id}`, {
          method: "PATCH",
          body: { status: select.value }
        });
      } catch (error) {
        alert(getErrorMessage(error));
      }
    });
  });
}

async function renderSupport() {
  const response = await request("/support/tickets");
  const tickets = response.data;

  const rows = tickets
    .map(
      (ticket) => `
        <tr>
          <td>${escapeHtml(ticket.title)}</td>
          <td>${escapeHtml(ticket.clientName)}</td>
          <td>${escapeHtml(ticket.priority)}</td>
          <td>
            <select data-action="ticket-status" data-id="${ticket.id}">
              ${["aberto", "em_andamento", "resolvido"]
                .map(
                  (status) =>
                    `<option value="${status}" ${ticket.status === status ? "selected" : ""}>${status}</option>`
                )
                .join("")}
            </select>
          </td>
          <td>${ticket.messages.length}</td>
          <td><button class="btn btn-secondary" data-action="ticket-msg" data-id="${ticket.id}">Mensagem</button></td>
        </tr>
      `
    )
    .join("");

  pageContent.innerHTML = `
    <div class="page-head">
      <h3>Tickets</h3>
      <span class="badge">${tickets.length} chamados</span>
    </div>
    <form id="support-form" class="card">
      <div class="form-grid">
        <label>Titulo<input name="title" required /></label>
        <label>Cliente<input name="clientName" required /></label>
        <label>Categoria<input name="category" /></label>
        <label>Prioridade
          <select name="priority">
            <option value="baixa">baixa</option>
            <option value="media" selected>media</option>
            <option value="alta">alta</option>
            <option value="critica">critica</option>
          </select>
        </label>
      </div>
      <button class="btn btn-primary" type="submit">Abrir ticket</button>
    </form>
    <div class="table-wrap" style="margin-top: 12px;">
      <table>
        <thead>
          <tr>
            <th>Titulo</th>
            <th>Cliente</th>
            <th>Prioridade</th>
            <th>Status</th>
            <th>Mensagens</th>
            <th>Acoes</th>
          </tr>
        </thead>
        <tbody>${rows || ""}</tbody>
      </table>
    </div>
  `;

  if (!rows) {
    pageContent.querySelector("tbody").innerHTML = `<tr><td colspan="6"><div class="small">Nenhum ticket ainda.</div></td></tr>`;
  }

  document.getElementById("support-form").addEventListener("submit", async (event) => {
    event.preventDefault();
    const formData = new FormData(event.target);

    try {
      await request("/support/tickets", {
        method: "POST",
        body: {
          title: formData.get("title"),
          clientName: formData.get("clientName"),
          category: formData.get("category") || undefined,
          priority: formData.get("priority"),
          status: "aberto"
        }
      });
      await renderCurrentPage();
    } catch (error) {
      showInlineError(getErrorMessage(error));
    }
  });

  pageContent.querySelectorAll('[data-action="ticket-status"]').forEach((node) => {
    node.addEventListener("change", async (event) => {
      const select = event.target;
      try {
        await request(`/support/tickets/${select.dataset.id}`, {
          method: "PATCH",
          body: { status: select.value }
        });
      } catch (error) {
        alert(getErrorMessage(error));
      }
    });
  });

  pageContent.querySelectorAll('[data-action="ticket-msg"]').forEach((node) => {
    node.addEventListener("click", async (event) => {
      const button = event.target;
      const body = window.prompt("Digite a mensagem para este ticket:");
      if (!body) {
        return;
      }

      try {
        await request(`/support/tickets/${button.dataset.id}/messages`, {
          method: "POST",
          body: {
            body,
            authorName: state.user?.name || "Equipe"
          }
        });
        await renderCurrentPage();
      } catch (error) {
        alert(getErrorMessage(error));
      }
    });
  });
}

async function renderCatalog() {
  const response = await request("/catalog/products");
  const products = response.data;

  const rows = products
    .map(
      (product) => `
        <tr>
          <td>${escapeHtml(product.code)}</td>
          <td>${escapeHtml(product.name)}</td>
          <td>${escapeHtml(product.segment || "-")}</td>
          <td>${formatCurrency(product.basePrice)}</td>
          <td>${product.setupDays} dias</td>
          <td>
            <select data-action="product-active" data-id="${product.id}">
              <option value="true" ${product.active ? "selected" : ""}>ativo</option>
              <option value="false" ${!product.active ? "selected" : ""}>inativo</option>
            </select>
          </td>
        </tr>
      `
    )
    .join("");

  pageContent.innerHTML = `
    <div class="page-head">
      <h3>Produtos White-Label</h3>
      <span class="badge">${products.length} produtos</span>
    </div>
    <form id="catalog-form" class="card">
      <div class="form-grid">
        <label>Codigo<input name="code" required /></label>
        <label>Nome<input name="name" required /></label>
        <label>Segmento<input name="segment" /></label>
        <label>Preco base<input name="basePrice" type="number" step="0.01" required /></label>
        <label>Prazo setup (dias)<input name="setupDays" type="number" required /></label>
        <label class="full">Descricao<textarea name="description"></textarea></label>
      </div>
      <button class="btn btn-primary" type="submit">Cadastrar produto</button>
    </form>
    <div class="table-wrap" style="margin-top: 12px;">
      <table>
        <thead>
          <tr>
            <th>Codigo</th>
            <th>Nome</th>
            <th>Segmento</th>
            <th>Preco base</th>
            <th>Setup</th>
            <th>Status</th>
          </tr>
        </thead>
        <tbody>${rows || ""}</tbody>
      </table>
    </div>
  `;

  if (!rows) {
    pageContent.querySelector("tbody").innerHTML = `<tr><td colspan="6"><div class="small">Nenhum produto ainda.</div></td></tr>`;
  }

  document.getElementById("catalog-form").addEventListener("submit", async (event) => {
    event.preventDefault();
    const formData = new FormData(event.target);

    try {
      await request("/catalog/products", {
        method: "POST",
        body: {
          code: formData.get("code"),
          name: formData.get("name"),
          segment: formData.get("segment") || undefined,
          description: formData.get("description") || undefined,
          basePrice: Number(formData.get("basePrice")),
          setupDays: Number(formData.get("setupDays"))
        }
      });
      await renderCurrentPage();
    } catch (error) {
      showInlineError(getErrorMessage(error));
    }
  });

  pageContent.querySelectorAll('[data-action="product-active"]').forEach((node) => {
    node.addEventListener("change", async (event) => {
      const select = event.target;
      try {
        await request(`/catalog/products/${select.dataset.id}`, {
          method: "PATCH",
          body: { active: select.value === "true" }
        });
      } catch (error) {
        alert(getErrorMessage(error));
      }
    });
  });
}

async function renderBranding() {
  const response = await request("/tenants/theme");
  const theme = response.data;

  pageContent.innerHTML = `
    <div class="grid two">
      <form id="branding-form" class="card">
        <h3>Identidade do tenant</h3>
        <div class="form-grid">
          <label>Nome da marca<input name="brandName" value="${escapeHtml(theme.brandName)}" required /></label>
          <label>Logo URL<input name="logoUrl" value="${escapeHtml(theme.logoUrl || "")}" /></label>
          <label>Cor primaria<input name="primaryColor" value="${escapeHtml(theme.primaryColor)}" required /></label>
          <label>Cor secundaria<input name="secondaryColor" value="${escapeHtml(theme.secondaryColor)}" required /></label>
        </div>
        <button class="btn btn-primary" type="submit">Salvar branding</button>
      </form>
      <article class="card">
        <p class="eyebrow">Preview</p>
        <h3 id="preview-brand-name">${escapeHtml(theme.brandName)}</h3>
        <p class="small">Visual aplicado na interface apos salvar.</p>
        <img
          id="preview-brand-logo"
          src="${escapeHtml(theme.logoUrl || DEFAULT_THEME.logoUrl)}"
          alt="Logo da marca"
          style="max-width: 220px; margin-top: 14px;"
        />
        <div style="display:flex; gap:10px; margin-top:16px;">
          <span class="badge" style="background:${escapeHtml(theme.primaryColor)}; color:#fff;">Primaria</span>
          <span class="badge" style="background:${escapeHtml(theme.secondaryColor)}; color:#fff;">Secundaria</span>
        </div>
      </article>
    </div>
  `;

  document.getElementById("branding-form").addEventListener("submit", async (event) => {
    event.preventDefault();
    const formData = new FormData(event.target);

    try {
      const payload = {
        brandName: formData.get("brandName"),
        logoUrl: formData.get("logoUrl") || "",
        primaryColor: formData.get("primaryColor"),
        secondaryColor: formData.get("secondaryColor")
      };

      const update = await request("/tenants/theme", {
        method: "PUT",
        body: payload
      });

      setTheme(update.data);
      document.getElementById("preview-brand-name").textContent = update.data.brandName;
      document.getElementById("preview-brand-logo").src = update.data.logoUrl || DEFAULT_THEME.logoUrl;
      alert("Branding atualizado com sucesso.");
    } catch (error) {
      showInlineError(getErrorMessage(error));
    }
  });
}

const renderByPage = {
  dashboard: renderDashboard,
  crm: renderCrm,
  projects: renderProjects,
  proposals: renderProposals,
  finance: renderFinance,
  support: renderSupport,
  catalog: renderCatalog,
  branding: renderBranding
};

async function renderCurrentPage() {
  pageContent.innerHTML = `<div class="small">Carregando...</div>`;

  try {
    await renderByPage[state.page]();
  } catch (error) {
    showInlineError(getErrorMessage(error, "Nao foi possivel renderizar esta pagina"));
  }
}

async function loadProfileAndTheme() {
  const [meResponse, themeResponse] = await Promise.all([
    request("/auth/me"),
    request("/tenants/theme")
  ]);

  state.user = meResponse.data;
  userPill.textContent = `${state.user.name} (${state.user.role})`; 
  setTheme(themeResponse.data);
  brandTenant.textContent = state.tenantId;
}

async function handleLogin(event) {
  event.preventDefault();
  loginError.classList.add("hidden");

  const tenantId = tenantInput.value.trim() || "dev-tenant";

  try {
    const loginResponse = await request("/auth/login", {
      method: "POST",
      tenantId,
      useAuth: false,
      body: {
        email: emailInput.value.trim(),
        password: passwordInput.value
      }
    });

    state.token = loginResponse.data.token;
    state.tenantId = loginResponse.data.tenantId;
    state.user = loginResponse.data.user;
    saveSession();

    await loadProfileAndTheme();
    setActiveNav("dashboard");
    showAppScreen();
    await renderCurrentPage();
  } catch (error) {
    loginError.textContent = getErrorMessage(error, "Nao foi possivel autenticar");
    loginError.classList.remove("hidden");
  }
}

async function bootstrap() {
  loginForm.addEventListener("submit", handleLogin);

  nav.addEventListener("click", async (event) => {
    const button = event.target.closest("button[data-page]");
    if (!button) {
      return;
    }

    setActiveNav(button.dataset.page);
    await renderCurrentPage();
  });

  refreshButton.addEventListener("click", async () => {
    await renderCurrentPage();
  });

  logoutButton.addEventListener("click", () => {
    clearSession();
    showLoginScreen();
  });

  if (!state.token) {
    showLoginScreen();
    tenantInput.value = state.tenantId;
    return;
  }

  try {
    await loadProfileAndTheme();
    setActiveNav("dashboard");
    showAppScreen();
    await renderCurrentPage();
  } catch {
    clearSession();
    showLoginScreen();
  }
}

void bootstrap();
