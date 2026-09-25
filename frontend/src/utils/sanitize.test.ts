import { describe, expect, it } from 'vitest';
import { sanitizeHtml } from './sanitize';

describe('sanitizeHtml', () => {
  it('keeps safe formatting tags', () => {
    expect(sanitizeHtml('<p>Привет <strong>мир</strong>!</p>')).toBe('<p>Привет <strong>мир</strong>!</p>');
    expect(sanitizeHtml('<b>bold</b>')).toBe('<b>bold</b>');
  });

  it('strips script tags', () => {
    expect(sanitizeHtml('<p>x</p><script>alert(1)</script>')).toBe('<p>x</p>');
  });

  it('strips event handler attributes', () => {
    expect(sanitizeHtml('<img src="x" onerror="alert(1)">')).not.toContain('onerror');
    expect(sanitizeHtml('<a href="#" onclick="alert(1)">x</a>')).not.toContain('onclick');
  });

  it('neutralizes javascript: URLs', () => {
    expect(sanitizeHtml('<a href="javascript:alert(1)">x</a>')).not.toContain('javascript:');
  });

  it('removes iframes', () => {
    expect(sanitizeHtml('<iframe src="https://evil.example"></iframe>p')).not.toContain('iframe');
  });

  it('returns empty string for empty input', () => {
    expect(sanitizeHtml('')).toBe('');
  });
});