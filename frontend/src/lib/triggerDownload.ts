import { accessGrants } from "./accessGrants";

// Category export / admin backup (Parts 9–10) stream the ZIP straight from
// an authenticated endpoint.
//
// Post-review fix: this used to fetch the whole response as a Blob first
// (httpClient + responseType: "blob") and only then hand it to the browser —
// meaning a large backup/export was fully buffered in page memory before any
// bytes reached disk, defeating the point of the backend's own streaming
// (CategoryController::export() / AdminController's backup endpoint are both
// StreamedResponse). A plain navigation lets the browser stream the download
// itself instead: auth here is the same httpOnly cookie a normal page
// request already carries (see httpClient's baseURL — same origin), and both
// endpoints set Content-Disposition: attachment, so the browser downloads
// the file without leaving the current page or reloading the SPA.
//
// Post-review fix #2: a plain navigation can't carry the
// X-Pouch-Access-Grants header httpClient's interceptor normally attaches —
// without it, CategoryExportService silently treated every locked category/
// item as if it had never been unlocked. The exact same (already signed,
// already short-lived) grants ride along as a "grants" query parameter
// instead; CategoryController::export() relays it back onto the header
// AccessKeyGuard actually reads. Harmless to always include, even for the
// admin backup endpoint, which ignores it (bypasses locks entirely).
export const triggerDownload = (url: string): void => {
  const grants = accessGrants.toHeaderValue();

  if (undefined === grants) {
    window.location.assign(url);
    return;
  }

  const separator = url.includes("?") ? "&" : "?";
  window.location.assign(`${url}${separator}grants=${encodeURIComponent(grants)}`);
};
