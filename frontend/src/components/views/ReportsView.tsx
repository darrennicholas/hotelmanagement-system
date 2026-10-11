'use client';

import { FormEvent, useState } from 'react';
import styles from '@/app/page.module.css';
import type { OccupancyReport, ReportReservation, RevenueRow } from '@/types';
import { dateLabel, money } from '@/services/formatters';
import { Metric, Panel, Empty, Status } from '@/components/ui';

type Props = {
  occupancy: OccupancyReport;
  revenue: RevenueRow[];
  reservations: ReportReservation[];
  from: string;
  to: string;
  currency?: string;
  onFilter: (from: string, to: string) => void;
};

export default function ReportsView({ occupancy, revenue, reservations, from, to, currency = 'IDR', onFilter }: Props) {
  const [draftFrom, setDraftFrom] = useState(from);
  const [draftTo, setDraftTo] = useState(to);
  const totalRevenue = revenue.reduce((sum, item) => sum + Number(item.total), 0);

  function submit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    if (draftFrom && draftTo && draftFrom <= draftTo) onFilter(draftFrom, draftTo);
  }

  return <>
    <Panel title="Periode laporan">
      <form className={styles.inlineForm} onSubmit={submit}>
        <label>Dari<input required type="date" value={draftFrom} onChange={(event) => setDraftFrom(event.target.value)} /></label>
        <label>Sampai<input required type="date" min={draftFrom} value={draftTo} onChange={(event) => setDraftTo(event.target.value)} /></label>
        <button className={styles.primaryButton} type="submit">Terapkan</button>
      </form>
      <small>Okupansi dihitung pada {dateLabel(occupancy.date ?? to)}. Revenue bersih memakai tanggal pembayaran dikurangi refund.</small>
    </Panel>
    <section className={styles.metrics}>
      <Metric label="Occupancy" value={`${occupancy.occupancy_rate}%`} icon="occupancy" tone="purple" />
      <Metric label="Occupied rooms" value={occupancy.occupied_rooms} icon="room" tone="blue" />
      <Metric label="Reserved rooms" value={occupancy.reserved_rooms ?? 0} icon="calendar" tone="purple" />
      <Metric label="Net revenue period" value={money(totalRevenue, currency)} icon="revenue" tone="orange" />
    </section>
    <section className={styles.grid}>
      <Panel title="Net revenue by day"><div className={styles.reportList}>{revenue.map((item) => <div key={item.date}><span>{dateLabel(item.date)}</span><strong>{money(item.total, currency)}</strong></div>)}{!revenue.length && <Empty text="Belum ada transaksi pada periode ini." />}</div></Panel>
      <Panel title="Reservation report"><div className={styles.reportList}>{reservations.slice(0, 8).map((item) => <div key={item.id}><span>{item.booking_number} · {item.user?.name ?? item.guest?.full_name ?? '-'}</span><Status value={item.status} /></div>)}{!reservations.length && <Empty text="Belum ada reservasi." />}</div></Panel>
    </section>
  </>;
}
