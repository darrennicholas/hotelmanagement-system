'use client';

import styles from '@/app/page.module.css';
import type { Dashboard, Room } from '@/types';
import { money } from '@/services/formatters';
import { Metric, Panel, Empty, Status, UiIcon } from '@/components/ui';

export default function Overview({ dashboard, rooms, currency = 'IDR', onRooms }: { dashboard: Dashboard; rooms: Room[]; currency?: string; onRooms: () => void }) {
  const occupancyRate = Math.min(100, Math.max(0, dashboard.occupancy_rate));

  return <>
    <section className={styles.metrics}>
      <Metric label="Total rooms" value={dashboard.rooms.total} icon="room" tone="blue" />
      <Metric label="Available rooms" value={dashboard.rooms.available} icon="check" tone="green" />
      <Metric label="Occupied rooms" value={dashboard.rooms.occupied} icon="occupancy" tone="purple" />
      <Metric label="Pendapatan harian" value={money(dashboard.financial.today, currency)} icon="revenue" tone="orange" />
    </section>

    <section className={styles.grid}>
      <Panel title="Operasional harian">
        <div className={styles.operations}>
          <div><span className={`${styles.operationIcon} ${styles.greenIcon}`}><UiIcon name="check" /></span><strong>{dashboard.reservations.check_ins}</strong><p>Check-ins</p></div>
          <div><span className={`${styles.operationIcon} ${styles.orangeIcon}`}><UiIcon name="arrow" /></span><strong>{dashboard.reservations.check_outs}</strong><p>Check-outs</p></div>
          <div><span className={`${styles.operationIcon} ${styles.blueIcon}`}><UiIcon name="calendar" /></span><strong>{dashboard.reservations.today}</strong><p>New reservations</p></div>
        </div>
      </Panel>

      <Panel title="Occupancy rate">
        <div className={styles.occupancy}>
          <div className={styles.ring}>
            <svg className={styles.ringSvg} viewBox="0 0 100 100" aria-hidden="true">
              <circle className={styles.ringTrack} cx="50" cy="50" r="42" pathLength="100" />
              <circle className={styles.ringProgress} cx="50" cy="50" r="42" pathLength="100" strokeDasharray="100 100" style={{ strokeDashoffset: 100 - occupancyRate }} />
            </svg>
            <div><strong>{dashboard.occupancy_rate}%</strong><small>Occupied</small></div>
          </div>
          <div className={styles.legend}>
            <p><span className={styles.dotPurple} />Occupied <b>{dashboard.rooms.occupied}</b></p>
            <p><span className={styles.dotBlue} />Reserved <b>{dashboard.rooms.reserved}</b></p>
            <p><span className={styles.dotGrey} />Available <b>{dashboard.rooms.available}</b></p>
          </div>
        </div>
      </Panel>
    </section>

    <Panel title="Room status" action={<button className={styles.secondaryButton} onClick={onRooms}>Kelola rooms <UiIcon name="arrow" /></button>}>
      <div className={styles.table}>
        <div className={styles.tableHead}><span>ROOM</span><span>TYPE</span><span>FLOOR</span><span>RATE / NIGHT</span><span>STATUS</span></div>
        {rooms.slice(0, 5).map((room) => <div className={styles.tableRow} key={room.id}><strong>#{room.room_number}</strong><span>{room.room_type?.name ?? 'Room'}</span><span>Floor {room.floor ?? '-'}</span><span>{money(room.room_type?.base_price, currency)}</span><span><Status value={room.status} /></span></div>)}
      </div>
      {!rooms.length && <Empty text="Belum ada kamar." />}
    </Panel>
  </>;
}
