// Types for itemApi.ts, split out per docs/codestyle/FRONTEND.md's
// "types/ folder" convention (extracted once itemApi.ts alone carried 15
// exported types — apply the same split anywhere else a file's type count
// grows past what's comfortable to scroll past to get to the actual logic).

export type ItemType = "file" | "url" | "photo" | "note";
export type ItemProcessingStatus = "pending" | "completed" | "failed";

// Post-review fix: GET /api/items returns this shape (no $extractedText —
// OCR/scraped-page text, never rendered anywhere in the UI, dropped from the
// paginated list response to keep it off every page's payload; see backend's
// ItemSummaryResponseDTO). $noteContent stays: ItemCard renders a note's full
// body inline in the list itself, not behind a separate detail fetch.
export interface Item {
  id: number;
  categoryId: number;
  type: ItemType;
  name: string;
  processingStatus: ItemProcessingStatus;
  processingError: string | null;
  originalFilename: string | null;
  mimeType: string | null;
  size: number | null;
  hasThumbnail: boolean;
  url: string | null;
  pageTitle: string | null;
  pageDescription: string | null;
  noteContent: string | null;
  favorite: boolean;
  tags: string[];
  keepForever: boolean;
  expiresAt: string | null;
  trashedAt: string | null;
  createdAt: string;
}

export interface ItemListParams {
  categoryId?: number;
  favorite?: boolean;
  tags?: string[];
  q?: string;
  page?: number;
  pageSize?: number;
}

// The envelope GET /api/items responds with — see backend's
// ItemListResponseDTO. $total is pre-lock-filtering (see that DTO's own
// comment), so a page can come back with fewer than $pageSize items without
// $total being wrong about it.
export interface ItemListResult {
  items: Item[];
  total: number;
  page: number;
  pageSize: number;
}

export interface SignedLink {
  url: string;
  expiresAt: string;
}

// Mirrors backend's TtlPreset enum (see App\Enum\TtlPreset) — the three
// choices the "ttlPreset" field of ItemLifecycleOptions actually accepts.
export type TtlPreset = "1h" | "7d" | "30d";

// Shared by every create-item form (post-review fix — see LifecycleFields):
// keepForever wins over expiresAt, which wins over ttlPreset, which — if all
// three are omitted — falls back to the backend's own 1-day default
// (ItemService::resolveExpiresAt()).
export interface ItemLifecycleFields {
  keepForever?: boolean;
  ttlPreset?: TtlPreset;
  expiresAt?: string;
}

export interface CreateNoteRequest extends ItemLifecycleFields {
  categoryId: number;
  content: string;
  name?: string;
}

export interface UpdateNoteRequest {
  id: number;
  content: string;
}

export interface UpdateTagsRequest {
  id: number;
  tags: string[];
}

export interface CreateFileRequest extends ItemLifecycleFields {
  categoryId: number;
  file: File;
  name?: string;
}

export interface OverwriteFileRequest {
  id: number;
  file: File;
}

export interface ItemVersion {
  version: number;
  originalFilename: string;
  mimeType: string;
  size: number;
  createdAt: string;
}

export interface PublicLink {
  viewUrl: string;
  downloadUrl: string | null;
  thumbnailUrl: string | null;
  expiresAt: string;
}
