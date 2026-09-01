// Matches ItemRepository::SNIPPET_HIGHLIGHT_START/END on the backend — see
// that constant's own doc comment for why a search snippet arrives wrapped
// in these Private Use Area codepoints instead of HTML (`<b>...</b>`):
// ts_headline() returns the item's own text verbatim around the match, so
// treating it as markup would let planted item content (a note, OCR output,
// a scraped page) inject HTML into another account's search results.
const HIGHLIGHT_START = "\u{E000}";
const HIGHLIGHT_END = "\u{E001}";

export interface SnippetSegment {
  text: string;
  highlighted: boolean;
}

// Splits a snippet into plain/highlighted text segments — render each as a
// plain text node (a highlighted one wrapped in <mark>, say), never via
// dangerouslySetInnerHTML.
export function highlightSnippetSegments(snippet: string): SnippetSegment[] {
  const segments: SnippetSegment[] = [];
  let cursor = 0;

  while (cursor < snippet.length) {
    const start = snippet.indexOf(HIGHLIGHT_START, cursor);
    if (-1 === start) {
      segments.push({ text: snippet.slice(cursor), highlighted: false });
      break;
    }

    if (start > cursor) {
      segments.push({ text: snippet.slice(cursor, start), highlighted: false });
    }

    const end = snippet.indexOf(HIGHLIGHT_END, start + HIGHLIGHT_START.length);
    if (-1 === end) {
      // Unterminated sentinel — treat the rest as plain text rather than
      // dropping it silently.
      segments.push({ text: snippet.slice(start + HIGHLIGHT_START.length), highlighted: false });
      break;
    }

    segments.push({ text: snippet.slice(start + HIGHLIGHT_START.length, end), highlighted: true });
    cursor = end + HIGHLIGHT_END.length;
  }

  return segments;
}
