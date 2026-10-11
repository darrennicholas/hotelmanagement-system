export const money = (value: number | string | undefined, currency = 'IDR') => {
  const normalizedCurrency = /^[A-Z]{3}$/i.test(currency) ? currency.toUpperCase() : 'IDR';
  return new Intl.NumberFormat('id-ID', {
    style: 'currency',
    currency: normalizedCurrency,
    maximumFractionDigits: 0,
  }).format(Number(value ?? 0));
};

export const dateLabel = (value: string | null | undefined) => {
  if (!value) return '-';
  const raw = String(value).trim();
  const normalized = /^\d{4}-\d{2}-\d{2}$/.test(raw)
    ? `${raw}T00:00:00`
    : raw.replace(/\.(\d{3})\d+([zZ]|[+-]\d{2}:?\d{2})$/, '.$1$2');
  const date = new Date(normalized);
  return Number.isNaN(date.getTime()) ? '-' : new Intl.DateTimeFormat('id-ID', { dateStyle: 'medium' }).format(date);
};

export const today = () => {
  const now = new Date();
  const pad = (value: number) => String(value).padStart(2, '0');
  return `${now.getFullYear()}-${pad(now.getMonth() + 1)}-${pad(now.getDate())}`;
};
