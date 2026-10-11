'use client';

import styles from '@/app/page.module.css';
import { money } from '@/services/formatters';
import type { Paginated, Room } from '@/types';
import { Empty, Panel, Pagination, Status, UiIcon } from '@/components/ui';

const roomImages = [
  'https://images.unsplash.com/photo-1611892440504-42a792e24d32?auto=format&fit=crop&w=900&q=85',
  'https://images.unsplash.com/photo-1582719478250-c89cae4dc85b?auto=format&fit=crop&w=900&q=85',
  'https://images.unsplash.com/photo-1566665797739-1674de7a421a?auto=format&fit=crop&w=900&q=85',
  'https://images.unsplash.com/photo-1578683010236-d716f9a3f461?auto=format&fit=crop&w=900&q=85',
];

function roomDescription(room: Room) {
  const type = room.room_type?.name ?? 'Kamar nyaman';
  const floor = room.floor ? `Berada di lantai ${room.floor}` : 'Siap menyambut waktu istirahat Anda';
  const capacity = room.room_type?.capacity ? ` untuk hingga ${room.room_type.capacity} tamu` : '';
  const beds = room.room_type?.number_of_beds ? ` dengan ${room.room_type.number_of_beds} tempat tidur` : '';
  return `${type}${capacity}${beds}. ${floor}.`;
}

export default function HotelSurroundingsView({ rooms, currency = 'IDR', pagination, onPageChange }: { rooms: Room[]; currency?: string; pagination?: Paginated<Room>; onPageChange?: (page: number) => void }) {
  return (
    <Panel className={styles.surroundingsPanel} title="Hotel Surroundings">
      <div className={styles.surroundingsIntro}>
        <div className={styles.surroundingsIntroIcon}><UiIcon name="sparkle" /></div>
        <div>
          <strong>Ruang nyaman untuk setiap perjalanan</strong>
          <p>Jelajahi kamar berdasarkan tipe, lantai, dan tarif yang tersedia.</p>
        </div>
        <span className={styles.surroundingsCount}>{rooms.length} kamar</span>
      </div>

      {rooms.length ? (
        <div className={styles.surroundingsGrid}>
          {rooms.map((room, index) => {
            const image = roomImages[index % roomImages.length];
            return (
              <article className={styles.surroundingsCard} key={room.id}>
                <div
                  className={styles.surroundingsImage}
                  role="img"
                  aria-label={`Foto ${room.room_type?.name ?? 'kamar'} nomor ${room.room_number}`}
                  style={{ backgroundImage: `url(${image})` }}
                >
                  <span className={styles.surroundingsRoomNumber}>Room #{room.room_number}</span>
                  <Status value={room.status} />
                </div>
                <div className={styles.surroundingsCardBody}>
                  <div className={styles.surroundingsCardHeading}>
                    <div>
                      <span className={styles.surroundingsEyebrow}>HAVEN HOTEL</span>
                      <h3>{room.room_type?.name ?? 'Kamar pilihan'}</h3>
                    </div>
                    <strong>{money(room.room_type?.base_price, currency)}</strong>
                  </div>
                  <p>{roomDescription(room)}</p>
                  <div className={styles.surroundingsMeta}>
                    <span>Floor {room.floor ?? '-'}</span>
                    <span>{room.available_rooms ?? (room.status === 'Available' ? 1 : 0)} available</span>
                  </div>
                </div>
              </article>
            );
          })}
        </div>
      ) : (
        <Empty text="Belum ada kamar yang dapat ditampilkan." />
      )}
      {pagination && onPageChange && <Pagination currentPage={pagination.current_page} lastPage={pagination.last_page} total={pagination.total} onPageChange={onPageChange} />}
    </Panel>
  );
}
