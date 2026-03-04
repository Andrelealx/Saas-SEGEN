"use strict";
Object.defineProperty(exports, "__esModule", { value: true });
exports.healthRouter = void 0;
const express_1 = require("express");
const healthRouter = (0, express_1.Router)();
exports.healthRouter = healthRouter;
healthRouter.get("/health", (_req, res) => {
    res.json({
        status: "ok",
        service: "softhouse-central-api",
        timestamp: new Date().toISOString()
    });
});
