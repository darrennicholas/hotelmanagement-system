'use client';

import { FormEvent, useState } from 'react';
import styles from '@/app/page.module.css';
import { api } from '@/services/api';
import { confirmAction, successAlert } from '@/services/alerts';
import { money } from '@/services/formatters';
import type { Paginated, Room, RoomType } from '@/types';
import { Empty, ErrorNotice, Panel, Pagination, Status, UiIcon } from '@/components/ui';

type Props = {
  rooms: Room[];
  roomTypes: RoomType[];
  onSaved: () => void;
  canManage: boolean;
  canUpdateStatus: boolean;
  pagination?: Paginated<Room>;
  onPageChange?: (page: number) => void;
};

export default function RoomsView({ rooms, roomTypes, onSaved, canManage, canUpdateStatus, currency = 'IDR', pagination, onPageChange }: Props & { currency?: string }) {
  const [showForm, setShowForm] = useState(false);
  const [error, setError] = useState('');
  const [saving, setSaving] = useState(false);
  const [form, setForm] = useState({ room_number: '', room_type_id: '', floor: '', status: 'Available', description: '' });
  const availableCount = rooms.filter((room) => room.status === 'Available').length;
  const occupiedCount = rooms.filter((room) => room.status === 'Occupied').length;
  const attentionCount = rooms.filter((room) => !['Available', 'Occupied'].includes(room.status)).length;

  async function save(event: FormEvent) {
    event.preventDefault();
    if (!canManage || !await confirmAction('Tambah kamar?', `Kamar nomor ${form.room_number || 'baru'} akan ditambahkan.`)) return;
    setError('');
    setSaving(true);
    try {
      await api('/rooms', { method: 'POST', body: JSON.stringify({ ...form, room_type_id: Number(form.room_type_id), floor: Number(form.floor) }) });
      setShowForm(false);
      setForm({ room_number: '', room_type_id: '', floor: '', status: 'Available', description: '' });
      await successAlert('Kamar berhasil ditambahkan', 'Data kamar sudah tersimpan.');
      onSaved();
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Kamar gagal disimpan.');
    } finally {
      setSaving(false);
    }
  }

  async function updateStatus(room: Room, status: string) {
    if (status === room.status || !await confirmAction('Ubah status kamar?', `Kamar ${room.room_number} akan menjadi ${status}.`)) return;
    setError('');
    setSaving(true);
    try {
      await api(`/rooms/${room.id}/status`, { method: 'PATCH', body: JSON.stringify({ status }) });
      await successAlert('Status kamar diperbarui', `Kamar ${room.room_number} sekarang ${status}.`);
      onSaved();
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Status kamar gagal diperbarui.');
    } finally {
      setSaving(false);
    }
  }

  return <Panel title="Rooms" action={canManage ? <button className={styles.secondaryButton} onClick={() => setShowForm(!showForm)}>{showForm ? 'Tutup form' : '+ Add room'}</button> : undefined}>
    <div className={styles.roomsContent}>
      {error && <ErrorNotice message={error} />}
      {canManage && showForm && <form className={styles.inlineForm} onSubmit={save}>
        <input required placeholder="Nomor kamar" value={form.room_number} onChange={(event) => setForm({ ...form, room_number: event.target.value })} />
        <select required value={form.room_type_id} onChange={(event) => setForm({ ...form, room_type_id: event.target.value })}><option value="">Tipe kamar</option>{roomTypes.map((type) => <option key={type.id} value={type.id}>{type.name} - {money(type.base_price, currency)}</option>)}</select>
        <input required type="number" min="0" placeholder="Floor" value={form.floor} onChange={(event) => setForm({ ...form, floor: event.target.value })} />
        <button className={styles.primaryButton} disabled={saving}>{saving ? 'Menyimpan...' : 'Simpan'}</button>
      </form>}
      <div className={styles.roomsSummary}><div className={styles.roomsSummaryLead}><span className={styles.roomsSummaryIcon}><UiIcon name="room" /></span><div><strong>{rooms.length} kamar terdaftar</strong></div></div><div className={styles.roomStat}><span className={`${styles.roomStatDot} ${styles.roomStatAvailable}`}></span><div><small>Available</small><strong>{availableCount}</strong></div></div><div className={styles.roomStat}><span className={`${styles.roomStatDot} ${styles.roomStatOccupied}`}></span><div><small>Occupied</small><strong>{occupiedCount}</strong></div></div><div className={styles.roomStat}><span className={`${styles.roomStatDot} ${styles.roomStatAttention}`}></span><div><small>Perlu perhatian</small><strong>{attentionCount}</strong></div></div></div>
      <div className={styles.table}><div className={styles.tableHead}><span>ROOM</span><span>TYPE</span><span>FLOOR</span><span>RATE / NIGHT</span><span>AVAILABLE</span><span>STATUS</span></div>{rooms.map((room) => <div className={styles.tableRow} key={room.id}><strong>#{room.room_number}</strong><span>{room.room_type?.name ?? '-'}</span><span>{room.floor ?? '-'}</span><span>{money(room.room_type?.base_price, currency)}</span><span className={styles.availableCount}>{room.available_rooms ?? (room.status === 'Available' ? 1 : 0)}</span><span>{canUpdateStatus && ['Available', 'Cleaning', 'Maintenance', 'Out of Order'].includes(room.status) ? <select value={room.status} onChange={(event) => void updateStatus(room, event.target.value)} disabled={saving}><option>Available</option><option>Cleaning</option><option>Maintenance</option><option>Out of Order</option></select> : <Status value={room.status} />}</span></div>)}</div>
      {pagination && onPageChange && <Pagination currentPage={pagination.current_page} lastPage={pagination.last_page} total={pagination.total} onPageChange={onPageChange} />}
      {!rooms.length && <Empty text="Belum ada kamar." />}
    </div>
  </Panel>;
}
