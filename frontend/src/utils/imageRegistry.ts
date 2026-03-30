/**
 * Markdown Image Utilities (Reference-Style)
 *
 * This utility manages images using standard Markdown reference syntax.
 * Instead of embedding giant base64 strings in the middle of sentences,
 * it inserts a short reference like `![alt][img-id]` and appends the 
 * data definition `[img-id]: data:image/...` to the bottom of the file.
 *
 * This is natively supported by the 'marked' library and keep the 
 * prose clean and readable.
 */

/**
 * Inserts a reference-style image into the markdown.
 * Returns the updated markdown with the reference in-place and the footer entry appended.
 */
export function insertReferenceImage(markdown: string, name: string, base64: string): string {
  const id = `img-${Date.now()}-${Math.random().toString(36).substr(2, 5)}`;
  const refLink = `![${name}][${id}]`;
  const refData = `\n\n[${id}]: ${base64}`;
  
  // Append the reference data to the very end of the markdown
  return (markdown || '') + '\n' + refLink + refData;
}

/**
 * Returns a short token for the image. 
 * (Kept for compatibility if needed, but we prefer direct insertion)
 */
export function getRefLink(name: string, id: string): string {
  return `![${name}][${id}]`;
}

export function getRefData(id: string, base64: string): string {
  return `\n[${id}]: ${base64}`;
}

/**
 * No-op cleaners (kept to avoid breaking imports immediately, 
 * but logic is now handled by standard markdown reference)
 */
export function resolveForHtml(markdown: string): string { return markdown; }
export function resolveForExport(markdown: string): string { return markdown; }
export function tokenize(markdown: string): string { return markdown; }
export function clear(): void {}
export function unregisterImage(_id: string): void {}
export function registerImage(_id: string, _name: string, _base64: string): string { return ''; }
