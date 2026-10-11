import type { ReactNode } from 'react';
import styles from '@/app/page.module.css';

export type IconName = 'dashboard' | 'calendar' | 'room' | 'check' | 'payment' | 'report' | 'service' | 'facility' | 'user' | 'logout' | 'search' | 'arrow' | 'sparkle' | 'occupancy' | 'revenue';

export function UiIcon({ name, size = 16 }: { name: IconName; size?: number }) {
  const paths: Record<IconName, string> = {
    dashboard: 'M3 3h7v7H3zM14 3h7v7h-7zM3 14h7v7H3zM14 14h7v7h-7z',
    calendar: 'M5 4v3M19 4v3M4 9h16M5 5h14a1 1 0 0 1 1 1v13H4V6a1 1 0 0 1 1-1Z',
    room: 'M4 20V5l8-2 8 2v15M4 9h16M8 13h2M14 13h2M8 17h2M14 17h2',
    check: 'm5 12 4 4L19 6',
    payment: 'M3 6h18v12H3zM3 10h18M7 15h3',
    report: 'M5 20V10M12 20V4M19 20v-7',
    service: 'M12 3 14.2 8.2 20 9l-4.2 4 1 5.8-5-2.8-5 2.8 1-5.8L3.6 9l5.8-.8Z',
    facility: 'M4 20h16M6 20V8l6-5 6 5v12M9 20v-5h6v5',
    user: 'M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8ZM4 21a8 8 0 0 1 16 0',
    logout: 'M10 17l5-5-5-5M15 12H3M21 3v18',
    search: 'm21 21-4.3-4.3M10.8 18a7.2 7.2 0 1 1 0-14.4 7.2 7.2 0 0 1 0 14.4Z',
    arrow: 'M5 12h14m-6-6 6 6-6 6',
    sparkle: 'm12 3 1.7 5.3L19 10l-5.3 1.7L12 17l-1.7-5.3L5 10l5.3-1.7Z',
    occupancy: 'M12 3a9 9 0 1 0 9 9',
    revenue: 'M4 19 10 13l4 4 6-8',
  };
  return <svg width={size} height={size} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true"><path d={paths[name]} /></svg>;
}

export function Metric({ label, value, icon, tone }: { label: string; value: string | number; icon: IconName; tone: 'blue' | 'green' | 'purple' | 'orange' }) {
  return <article className={`${styles.metric} ${styles[tone]}`}><div className={styles.metricTop}><span><UiIcon name={icon} /></span></div><p>{label}</p><strong>{value}</strong></article>;
}

export function Panel({ title, action, children, className }: { title: string; action?: ReactNode; children: ReactNode; className?: string }) {
  const titleClass = title === 'Rooms' ? styles.roomsPanel : '';
  return <section className={`${styles.panel} ${titleClass} ${className ?? ''}`}><div className={styles.panelHeader}><h2>{title}</h2>{action}</div>{children}</section>;
}

export function Empty({ text }: { text: string }) { return <div className={styles.empty}>{text}</div>; }
export function ErrorNotice({ message }: { message: string }) { return message ? <p className={styles.errorNotice}>{message}</p> : null; }
export function Status({ value, label = value }: { value: string; label?: string }) { return <em className={`${styles.status} ${styles[value.toLowerCase().replaceAll(' ', '')] ?? ''}`}>{label}</em>; }

export function Pagination({ currentPage, lastPage, total, onPageChange }: { currentPage: number; lastPage: number; total: number; onPageChange: (page: number) => void }) {
  if (lastPage <= 1) return null;
  return <div className={styles.pagination}><span>{total} data</span><div><button type="button" disabled={currentPage <= 1} onClick={() => onPageChange(currentPage - 1)}>Sebelumnya</button><strong>Halaman {currentPage} / {lastPage}</strong><button type="button" disabled={currentPage >= lastPage} onClick={() => onPageChange(currentPage + 1)}>Berikutnya</button></div></div>;
}
