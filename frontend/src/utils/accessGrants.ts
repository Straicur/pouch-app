import { logger } from "../libs/logger";

// Part 7: what AccessKeyGuard (backend) expects on the X-Pouch-Access-Grants
// header — a signed, time-limited proof this browser submitted the right key
// for one resource. The resource string itself (category-key:{id}:v{version}:
// u{userId} / item-key:{id}:v{version}:u{userId}) is opaque to the frontend —
// it's built server-side by AccessKeyResource and echoed back verbatim in the
// unlock response, so a key reset or a different logged-in user automatically
// invalidates it without the frontend needing to know why. sessionStorage,
// not localStorage: a grant is a per-tab "I unlocked this just now" fact, not
// something that should silently outlive the tab it was earned in — and it's
// explicitly cleared on login/logout too (see authApi.ts), since a grant
// earned by one account has no business surviving into another's session.
export interface AccessGrant {
  resource: string;
  expires: number;
  signature: string;
}

const STORAGE_KEY = "pouchAccessGrants";
export const GRANTS_HEADER = "X-Pouch-Access-Grants";

const nowSeconds = (): number => Math.floor(Date.now() / 1000);

const readAll = (): AccessGrant[] => {
  try {
    const raw = sessionStorage.getItem(STORAGE_KEY);
    if (null === raw) {
      return [];
    }

    const parsed: unknown = JSON.parse(raw);

    return Array.isArray(parsed) ? (parsed as AccessGrant[]) : [];
  } catch (error) {
    logger.error("Failed to read stored access grants:", error);

    return [];
  }
};

const writeAll = (grants: AccessGrant[]) => {
  try {
    sessionStorage.setItem(STORAGE_KEY, JSON.stringify(grants));
  } catch (error) {
    logger.error("Failed to persist access grants:", error);
  }
};

export const accessGrants = {
  // Expired grants are dropped on every read/write — a grant a few seconds
  // past expiry is worthless anyway (the server rejects it), no reason to
  // keep sending it or let the list grow forever.
  getValid(): AccessGrant[] {
    const valid = readAll().filter((grant) => grant.expires > nowSeconds());
    writeAll(valid);

    return valid;
  },

  add(grant: AccessGrant) {
    const others = accessGrants.getValid().filter((existing) => existing.resource !== grant.resource);
    writeAll([...others, grant]);
  },

  // Called from authApi's login/logout onQueryStarted handlers. Without
  // this, a grant earned by one account keeps being sent after that account
  // logs out and a different one logs in on the same tab.
  clear() {
    try {
      sessionStorage.removeItem(STORAGE_KEY);
    } catch (error) {
      logger.error("Failed to clear stored access grants:", error);
    }
  },

  // The exact header value AccessKeyGuard::parseGrants() expects — undefined
  // when there's nothing to send, so callers can skip setting the header at
  // all rather than sending "[]" on every request.
  toHeaderValue(): string | undefined {
    const grants = accessGrants.getValid();

    return grants.length > 0 ? JSON.stringify(grants) : undefined;
  },
};
