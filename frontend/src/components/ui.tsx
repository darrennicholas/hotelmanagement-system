import type { ReactNode } from 'react';
import styles from '@/app/page.module.css';

export function Metric({ label, value, icon, tone }: { label: string; value: string | number; icon: string; tone: 'blue' | 'green' | 'purple' | 'orange' }) {
  return <article className={`${styles.metric} ${styles[tone]}`}><div className={styles.metricTop}><span>{icon}</span><small>Live</small></div><p>{label}</p><strong>{value}</strong></article>;
}

export function Panel({ title, subtitle, action, children, className }: { title: string; subtitle?: string; action?: ReactNode; children: ReactNode; className?: string }) {
  const titleClass = title === 'Rooms' ? styles.roomsPanel : '';
  return <section className={`${styles.panel} ${titleClass} ${className ?? ''}`}><div className={styles.panelHeader}><div><h2>{title}</h2>{subtitle && <p className={styles.muted}>{subtitle}</p>}</div>{action}</div>{children}</section>;
}

export function Empty({ text }: { text: string }) { return <div className={styles.empty}>{text}</div>; }
export function ErrorNotice({ message }: { message: string }) { return message ? <p className={styles.errorNotice}>{message}</p> : null; }
export function Status({ value }: { value: string }) { return <em className={`${styles.status} ${styles[value.toLowerCase().replaceAll(' ', '')] ?? ''}`}>{value}</em>; }
