"use strict";
Object.defineProperty(exports, "__esModule", { value: true });
exports.db = void 0;
const leads = [];
const projects = [];
exports.db = {
    addLead(lead) {
        leads.push(lead);
        return lead;
    },
    listLeads(tenantId) {
        return leads.filter((item) => item.tenantId === tenantId);
    },
    addProject(project) {
        projects.push(project);
        return project;
    },
    listProjects(tenantId) {
        return projects.filter((item) => item.tenantId === tenantId);
    }
};
