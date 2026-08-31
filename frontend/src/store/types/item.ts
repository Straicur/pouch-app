// Types for itemApi.ts, split out per docs/codestyle/FRONTEND.md's
// "types/ folder" convention (extracted once itemApi.ts alone carried 15
// exported types — apply the same split anywhere else a file's type count
// grows past what's comfortable to scroll past to get to the actual logic).

export type ItemType = "file" | "url" | "photo" | "note";
export type ItemProcessingStatus = "pending" | "completed" | "failed";

// Fields both ItemSummaryResponseDTO and ItemResponseDTO carry — split so
// each response shape only adds what it actually has (Część 13: the two
// diverged further once ItemSummaryResponseDTO got $locked and
// ItemResponseDTO got $hasAccessKey, neither shared by the other).
interface ItemBase {
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

// Minimal shape useItemThumbnailUrl() (ItemCard.tsx) needs — satisfied by
// both ItemSummary and ItemDetail, so the hook works for the compact card
// and the details modal alike without depending on either concrete type.
export interface ItemBaseLike {
  id: number;
  hasThumbnail: boolean;
}

// GET /api/items' per-item shape (no $extractedText — OCR/scraped-page text,
// never rendered anywhere in the UI — and no $hasAccessKey, deliberately: the
// list only needs to know whether *this request* is locked out, not whether
// a key exists at all — see backend's ItemSummaryResponseDTO).
export interface ItemSummary extends ItemBase {
  // Część 13 — an item locked by its own key still appears in GET
  // /api/items (see ItemMapper::toLockedSummaryResponseDTO() on the
  // backend), just with every other content-revealing field redacted to
  // null/false/[]. LockedItemCard renders these by name only, with an
  // inline unlock.
  locked: boolean;
}

// GET /api/items/{id} and every create/update mutation's response — the full
// ItemResponseDTO, fetched behind a deliberate click (ItemDetailsModal), not
// alongside the list.
export interface ItemDetail extends ItemBase {
  extractedText: string | null;
  // Część 13 — whether this item has its own access key set at all, as
  // opposed to ItemSummary's $locked (whether *this request* is unlocked for
  // one that does). AccessKeyPanel uses it to show "Ustaw klucz" vs
  // "Zmień/Usuń klucz" instead of always offering every action.
  hasAccessKey: boolean;
}

export interface ItemListParams {
  // Zgodne z backendowym GET /api/items?categoryIds=1,2,3 ("matches any") —
  // ItemController::list()/ItemListFilter przyjmują dziś listę, nie pojedyncze id.
  categoryIds?: number[];
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
  items: ItemSummary[];
  total: number;
  page: number;
  pageSize: number;
}

export interface SignedLink {
  url: string;
  expiresAt: string;
}

// Mirrors backend's TtlPreset enum (see App\Enum\TtlPreset) — the choices the
// "ttlPreset" field of ItemLifecycleOptions actually accepts.
export type TtlPreset = "1h" | "1d" | "7d" | "30d";

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
  tags?: string[];
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
  // Część 13 — optional free-text description, stored the same way a NOTE
  // item's body is (see backend's ItemServiceInterface::createFile()).
  content?: string;
  tags?: string[];
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
