// Types for adminApi.ts — see store/types/item.ts's header for why this is
// split out (same types/ convention, applied per docs/codestyle/FRONTEND.md).

export interface StorageUsageByType {
  type: string;
  totalBytes: number;
  itemCount: number;
}

export interface StorageLimit {
  type: string;
  maxSizeBytes: number;
}

export interface StorageReport {
  byType: StorageUsageByType[];
  archivedVersionsBytes: number;
  limits: StorageLimit[];
}

export interface GcRunLog {
  id: number;
  trigger: "cron" | "manual";
  expiredCount: number;
  purgedCount: number;
  runAt: string;
  // null dla przebiegu crona (zawsze wszystkie pouche) albo ręcznego bez wybranej pouch.
  pouchName: string | null;
}

export interface AuditLogEntry {
  id: number;
  action: "view" | "download" | "delete" | "key_change" | "purge" | "restore" | "enable" | "disable";
  resourceType: "category" | "item" | "user" | "technical_break";
  resourceId: number;
  userId: number | null;
  userEmail: string | null;
  ip: string | null;
  createdAt: string;
  // null dla akcji bez jednej, wyraźnej "właścicielskiej" pouch.
  pouchName: string | null;
}

export interface AuditLogParams {
  limit?: number;
  resourceType?: AuditLogEntry["resourceType"];
  action?: AuditLogEntry["action"];
  pouchId?: number | null;
}

export interface TechnicalBreakStatus {
  active: boolean;
  message: string | null;
  since: string | null;
}

export interface ExtendExpiryRequest {
  itemIds: number[];
  keepForever: boolean;
  ttlPreset?: string;
  expiresAt?: string;
}
