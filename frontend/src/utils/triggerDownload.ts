// Category export / admin backup stream the ZIP straight from an
// authenticated endpoint. A plain navigation lets the browser stream the
// download itself, instead of fetching the whole response as a Blob first
// and only then handing it over (which would fully buffer a large
// backup/export in page memory before any bytes reached disk, defeating the
// point of the backend's own streaming — CategoryController::export() /
// AdminController's backup endpoint are both StreamedResponse). Auth here is
// the same httpOnly cookie a normal page request already carries (see
// httpClient's baseURL — same origin), and both endpoints set
// Content-Disposition: attachment, so the browser downloads the file without
// leaving the current page or reloading the SPA.
//
// A plain navigation can't carry the X-Pouch-Access-Grants header
// httpClient's interceptor normally attaches, so putting raw grants on the
// URL isn't an option either (real content sitting in browser history and
// any proxy/nginx access log, unbounded in size). The caller handles that
// instead: a category export first exchanges its current grants for a
// short-lived, fixed-size, opaque token (POST .../export-token — see
// accessKeyApi.ts's useGetCategoryExportTokenMutation) and only *that* token
// goes on the URL this function is handed. This function itself stays a
// plain, generic "go to this URL" — it has no opinion on what, if anything,
// the caller put in the query string.
export const triggerDownload = (url: string): void => {
  window.location.assign(url);
};
