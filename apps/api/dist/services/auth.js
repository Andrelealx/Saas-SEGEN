"use strict";
Object.defineProperty(exports, "__esModule", { value: true });
exports.createToken = createToken;
exports.parseToken = parseToken;
const SEPARATOR = "::";
function createToken(tenantId, user) {
    return Buffer.from(`${tenantId}${SEPARATOR}${user.id}`, "utf-8").toString("base64url");
}
function parseToken(token) {
    try {
        const decoded = Buffer.from(token, "base64url").toString("utf-8");
        const [tenantId, userId] = decoded.split(SEPARATOR);
        if (!tenantId || !userId) {
            return null;
        }
        return { tenantId, userId };
    }
    catch {
        return null;
    }
}
