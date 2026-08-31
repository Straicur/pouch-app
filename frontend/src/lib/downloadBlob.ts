import { httpClient } from "./httpClient";

// Category export / admin backup (Parts 9–10) stream the ZIP straight from
// an authenticated endpoint — unlike item downloads, there's no separate
// signed link to just point the browser at, so the file has to come through
// httpClient (cookie + access-grants headers) as a blob, then get "saved" by
// briefly faking a click on an object-URL <a download>.
export const downloadBlob = async (url: string, filename: string): Promise<void> => {
  const response = await httpClient.get<Blob>(url, { responseType: "blob" });
  const objectUrl = URL.createObjectURL(response.data);

  try {
    const link = document.createElement("a");
    link.href = objectUrl;
    link.download = filename;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
  } finally {
    URL.revokeObjectURL(objectUrl);
  }
};
