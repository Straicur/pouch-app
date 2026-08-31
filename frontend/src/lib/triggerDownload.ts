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
// X-Pouch-Access-Grants header httpClient's interceptor normally attaches.
// An earlier version of this function appended the grants themselves as a
// "?grants=" query parameter to work around that — real content then sitting
// in browser history and any proxy/nginx access log, unbounded in size. The
// fix moved into the caller instead: a category export first exchanges its
// current grants for a short-lived, fixed-size, opaque token (POST .../
// export-token — see accessKeyApi.ts's useGetCategoryExportTokenMutation)
// and only *that* token goes on the URL this function is handed. This
// function itself stays a plain, generic "go to this URL" — it has no
// opinion on what, if anything, the caller put in the query string.
export const triggerDownload = (url: string): void => {
  window.location.assign(url);
};
