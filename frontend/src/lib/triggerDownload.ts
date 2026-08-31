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
export const triggerDownload = (url: string): void => {
  window.location.assign(url);
};
