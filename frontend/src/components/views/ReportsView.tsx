import styles from '@/app/page.module.css';
import type { OccupancyReport, ReportReservation, RevenueRow } from '@/types';
import { dateLabel, money } from '@/services/formatters';
import { Metric, Panel, Empty, Status } from '@/components/ui';

export default function ReportsView({ occupancy, revenue, reservations }: { occupancy: OccupancyReport; revenue: RevenueRow[]; reservations: ReportReservation[] }) {
  const totalRevenue = revenue.reduce((sum, item) => sum + Number(item.total), 0);
  return <><section className={styles.metrics}><Metric label="Occupancy" value={`${occupancy.occupancy_rate}%`} icon="◉" tone="purple" /><Metric label="Occupied rooms" value={occupancy.occupied_rooms} icon="⌂" tone="blue" /><Metric label="Available rooms" value={occupancy.available_rooms} icon="✓" tone="green" /><Metric label="Revenue period" value={money(totalRevenue)} icon="↗" tone="orange" /></section><section className={styles.grid}><Panel title="Revenue by day" subtitle="Bersumber dari payments berstatus Paid"><div className={styles.reportList}>{revenue.map((item) => <div key={item.date}><span>{dateLabel(item.date)}</span><strong>{money(item.total)}</strong></div>)}{!revenue.length && <Empty text="Belum ada revenue pada periode ini." />}</div></Panel><Panel title="Reservation report" subtitle={`${reservations.length} reservasi pada periode aktif`}><div className={styles.reportList}>{reservations.slice(0, 8).map((item) => <div key={item.id}><span>{item.booking_number} · {item.user?.name ?? '-'}</span><Status value={item.status} /></div>)}{!reservations.length && <Empty text="Belum ada reservasi." />}</div></Panel></section></>;
}
