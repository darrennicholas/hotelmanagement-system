export const money = (value: number | string | undefined) => new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(Number(value ?? 0));

export const dateLabel = (value: string | null | undefined) => {
  if (!value) return '-';
  const raw = String(value).trim();
  const normalized = /^\d{4}-\d{2}-\d{2}$/.test(raw)
    ? `${raw}T00:00:00`
    : raw.replace(/\.(\d{3})\d+([zZ]|[+-]\d{2}:?\d{2})$/, '.$1$2');
  const date = new Date(normalized);
  return Number.isNaN(date.getTime()) ? '-' : new Intl.DateTimeFormat('id-ID', { dateStyle: 'medium' }).format(date);
};

export const today = () => new Date().toISOString().slice(0, 10);
