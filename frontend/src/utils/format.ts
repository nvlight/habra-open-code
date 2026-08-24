const countFormatter = new Intl.NumberFormat('ru-RU');

export function formatCount(value: number | undefined): string {
  if (value === undefined || value === null) {
    return '0';
  }

  return countFormatter.format(value);
}

export function formatDate(iso: string | null): string {
  if (!iso) {
    return '';
  }

  const date = new Date(iso);
  const now = new Date();
  const diffMinutes = Math.floor((now.getTime() - date.getTime()) / 60000);

  if (diffMinutes < 1) {
    return 'только что';
  }
  if (diffMinutes < 60) {
    const n = diffMinutes % 10;
    if (n === 1 && diffMinutes !== 11) return `${diffMinutes} минуту`;
    if (n >= 2 && n <= 4 && (diffMinutes < 12 || diffMinutes > 14)) return `${diffMinutes} минуты`;
    return `${diffMinutes} минут`;
  }

  const diffHours = Math.floor(diffMinutes / 60);
  if (diffHours < 24) {
    return `${diffHours} ч.`;
  }

  return date.toLocaleDateString('ru-RU', { day: 'numeric', month: 'long', year: 'numeric' });
}
