import { marked } from 'marked';
import DOMPurify from 'dompurify';

export function renderMarkdown(markdown: string | undefined | null): string {
  if (!markdown) return '';

  // Parse markdown to HTML
  const rawHtml = marked.parse(markdown) as string;

  // Sanitize HTML
  const sanitized = DOMPurify.sanitize(rawHtml, {
    ADD_ATTR: ['src'],
    ALLOWED_URI_REGEXP: /^(?:(?:(?:f|ht)tps?|mailto|tel|callto|cid|xmpp|data):|[^a-z]|[a-z+.\-]+(?:[^a-z+.\-:]|$))/i
  }).replace(/<details>/g, '<details open>');

  // Post-process HTML to fix relative links and add target="_blank"
  const parser = new DOMParser();
  const doc = parser.parseFromString(sanitized, 'text/html');
  const links = doc.querySelectorAll('a');

  links.forEach(link => {
    const href = link.getAttribute('href');
    if (href && !href.match(/^(https?:\/\/|mailto:|\/|#)/)) {
      link.setAttribute('href', `https://${href}`);
    }
    
    // Always open external/absolute links in new tab
    if (href && (href.startsWith('http') || (!href.startsWith('/') && !href.startsWith('#')))) {
      link.setAttribute('target', '_blank');
      link.setAttribute('rel', 'noopener noreferrer');
    }
  });

  return doc.body.innerHTML;
}

export function cleanMarkdown(text: string | undefined | null): string {
  if (!text) return '';
  return text.replace(/^```(?:markdown|md)?\n?/i, '').replace(/\n?```$/i, '').trim();
}
