import { User } from "../domain/types";

const SEPARATOR = "::";

export function createToken(tenantId: string, user: User): string {
  return Buffer.from(`${tenantId}${SEPARATOR}${user.id}`, "utf-8").toString("base64url");
}

export function parseToken(token: string): { tenantId: string; userId: string } | null {
  try {
    const decoded = Buffer.from(token, "base64url").toString("utf-8");
    const [tenantId, userId] = decoded.split(SEPARATOR);

    if (!tenantId || !userId) {
      return null;
    }

    return { tenantId, userId };
  } catch {
    return null;
  }
}
