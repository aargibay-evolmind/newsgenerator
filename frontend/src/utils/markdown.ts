import { marked } from 'marked';
import DOMPurify from 'dompurify';

// Track all blob URLs created by renderMarkdown so they can be revoked on cleanup.
const activeBlobs = new Set<string>();

/**
 * Revoke all blob URLs previously created by renderMarkdown.
 * Call this in onUnmounted to prevent memory leaks.
 */
export function revokeMarkdownBlobs() {
  activeBlobs.forEach(url => URL.revokeObjectURL(url));
  activeBlobs.clear();
}

/**
 * Move all Markdown image reference definitions that contain data: URIs
 * to the very end of the document. This keeps the editor clean.
 */
export function reorganizeImageRefs(markdown: string | undefined | null): string {
  if (!markdown) return '';
  
  // Matches lines like:  [some-id]: data:image/jpeg;base64,...
  const refPattern = /^(\[[^\]]+\]:\s*data:image\/[^\s][^\n]*)$/gm;
  const found: string[] = [];

  const cleaned = markdown.replace(refPattern, (match) => {
    found.push(match);
    return ''; // Remove from current position
  });

  if (found.length === 0) return markdown;

  // Trim trailing whitespace left behind by the removals, then append refs
  return cleaned.replace(/(\n\s*)+$/, '') + '\n\n' + found.join('\n') + '\n';
}

export function renderMarkdown(markdown: string | undefined | null, portable: boolean = false): string {
  if (!markdown) return '';

  // ── Step 1: Pre-process image references ──────────────────────────────────
  // Protect data: URIs from DOMPurify stripping by swapping them with safe placeholders.
  const imageRefMap = new Map<string, string>();
  const refPattern = /^\[([^\]]+)\]:\s*(data:image\/[^\s][^\n]*)$/gm;
  
  const processedMarkdown = markdown.replace(refPattern, (_match, id, dataUri) => {
    const placeholder = `/__img_ref_${encodeURIComponent(id)}__`;
    imageRefMap.set(placeholder, dataUri as string);
    return `[${id}]: ${placeholder}`;
  });

  // ── Step 2: Parse Markdown to HTML ──────────────────────────────────────────
  const rawHtml = marked.parse(processedMarkdown) as string;

  // ── Step 3: Sanitize and Parse ──────────────────────────────────────────────
  // Use DOMPurify's RETURN_DOM feature to get a clean DOM node directly.
  const cleanDom = DOMPurify.sanitize(rawHtml, {
      ADD_ATTR: ['src'],
      ALLOWED_URI_REGEXP: /^(?:(?:(?:f|ht)tps?|mailto|tel|callto|cid|xmpp|blob):|[^a-z]|[a-z+.\-]+(?:[^a-z+.\-:]|$))/i,
      RETURN_DOM: true
  }) as unknown as HTMLElement;

  // ── Step 4: Post-process DOM ────────────────────────────────────────────────
  
  // 4a. Convert placeholder src → blob: URL
  const images = cleanDom.querySelectorAll('img');
  images.forEach(img => {
    const src = img.getAttribute('src');
    if (!src) return;

    const dataUri = imageRefMap.get(src);
    if (dataUri) {
      try {
        const commaIdx = dataUri.indexOf(',');
        const header = dataUri.slice(0, commaIdx);
        const b64 = dataUri.slice(commaIdx + 1);
        const mimeMatch = header.match(/data:(image\/[\w+]+)/);
        if (mimeMatch && b64) {
          const mime = mimeMatch[1];
          const bytes = atob(b64);
          const arr = new Uint8Array(bytes.length);
          for (let i = 0; i < bytes.length; i++) arr[i] = bytes.charCodeAt(i);
          const blob = new Blob([arr], { type: mime });
          const blobUrl = URL.createObjectURL(blob);
          activeBlobs.add(blobUrl);
          img.setAttribute('src', blobUrl);
        }
      } catch {
        img.removeAttribute('src'); // Clean up broken placeholders
      }
    }
  });

  // 4b. Fix relative links and targets
  const links = cleanDom.querySelectorAll('a');
  links.forEach(link => {
    const href = link.getAttribute('href');
    if (href && !href.match(/^(https?:\/\/|mailto:|\/|#)/)) {
      link.setAttribute('href', `https://${href}`);
    }
    if (href && (href.startsWith('http') || (!href.startsWith('/') && !href.startsWith('#')))) {
      link.setAttribute('target', '_blank');
      link.setAttribute('rel', 'noopener noreferrer');
    }
  });

  // 4c. Ensure details are open if needed (though DOMPurify should preserve them)
  const details = cleanDom.querySelectorAll('details');
  details.forEach(d => {
    if (!d.hasAttribute('open')) d.setAttribute('open', '');
  });

  const bodyHtml = cleanDom.innerHTML;

  if (!portable || imageRefMap.size === 0) {
    return bodyHtml;
  }

  // ── Step 5: Portable Hydration Script ─────────────────────────────────────
  // Encapsulate data in a script tag and provide the hydration logic.
  const dataObject: Record<string, string> = {};
  imageRefMap.forEach((dataUri, placeholder) => {
      // Find the ID from the placeholder: /__img_ref_${encodeURIComponent(id)}__
      const match = placeholder.match(/\/__img_ref_(.+)__/);
      if (match && match[1]) {
          const id = decodeURIComponent(match[1]);
          dataObject[id] = dataUri;
      }
  });

  const hydrationScript = `
<script id="img-hydration-data" type="application/json">
${JSON.stringify(dataObject)}
</script>
<script>
(function() {
  try {
    const data = JSON.parse(document.getElementById('img-hydration-data').textContent);
    Object.keys(data).forEach(id => {
      const b64WithHeader = data[id];
      const commaIdx = b64WithHeader.indexOf(',');
      const mimeMatch = b64WithHeader.match(/data:(image\\/[\\w+]+)/);
      if (mimeMatch && commaIdx !== -1) {
          const mime = mimeMatch[1];
          const b64 = b64WithHeader.slice(commaIdx + 1);
          const bytes = atob(b64);
          const arr = new Uint8Array(bytes.length);
          for (let i = 0; i < bytes.length; i++) arr[i] = bytes.charCodeAt(i);
          const blob = new Blob([arr], { type: mime });
          const blobUrl = URL.createObjectURL(blob);
          
          // Hydrate all potential places where this ID is used
          const selector = 'img[src="/__img_ref_' + encodeURIComponent(id) + '__"], img[alt*="' + id + '"]';
          document.querySelectorAll(selector).forEach(img => {
              img.src = blobUrl;
          });
      }
    });
  } catch (e) {
    console.error('Hydration failed', e);
  }
})();
</script>`;

  return bodyHtml + hydrationScript;
}

export function cleanMarkdown(text: string | undefined | null): string {
  if (!text) return '';
  return text.replace(/^```(?:markdown|md)?\n?/i, '').replace(/\n?```$/i, '').trim();
}
