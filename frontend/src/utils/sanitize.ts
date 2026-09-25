import DOMPurify from 'dompurify';

export function sanitizeHtml(value: string): string {
  return DOMPurify.sanitize(value);
}