'use client';

import { FormEvent, useState } from 'react';
import styles from '@/app/page.module.css';
import { api } from '@/services/api';
import { confirmAction, successAlert } from '@/services/alerts';
import { money } from '@/services/formatters';
import type { Room, RoomType } from '@/types';
import { Empty, ErrorNotice, Panel, Status } from '@/components/ui';

export default function RoomsView({ rooms, roomTypes, onSaved, canManage }: { rooms: Room[]; roomTypes: RoomType[]; onSaved: () => void; canManage: boolean }) {
  const [showForm, setShowForm] = useState(false); const [error, setError] = useState(''); const [saving, setSaving] = useState(false); const [form, setForm] = useState({ room_number: '', room_type_id: '', floor: '', status: 'Available', description: '' });
  const availableCount = rooms.filter((room) => room.status === 'Available').length;
  const occupiedCount = rooms.filter((room) => room.status === 'Occupied').length;
  const attentionCount = rooms.filter((room) => !['Available', 'Occupied'].includes(room.status)).length;
  async function save(event: FormEvent) {
    event.preventDefault(); if (!canManage || !await confirmAction('Tambah kamar?', `Kamar nomor ${form.room_number || 'baru'} akan ditambahkan.`)) return;
    setError(''); setSaving(true);
    try { await api('/rooms', { method: 'POST', body: JSON.stringify({ ...form, room_type_id: Number(form.room_type_id), floor: Number(form.floor) }) }); setShowForm(false); setForm({ room_number: '', room_type_id: '', floor: '', status: 'Available', description: '' }); await successAlert('Kamar berhasil ditambahkan', 'Data kamar sudah tersimpan.'); onSaved(); }
    catch (err) { setError(err instanceof Error ? err.message : 'Kamar gagal disimpan.'); } finally { setSaving(false); }
  }
  return <Panel title="Rooms" subtitle="Status dikunci dan diperbarui otomatis oleh backend" action={canManage ? <button className={styles.secondaryButton} onClick={() => setShowForm(!showForm)}>{showForm ? 'Tutup form' : '+ Add room'}</button> : undefined}><div className={styles.roomsContent}>{error && <ErrorNotice message={error} />}{canManage && showForm && <form className={styles.inlineForm} onSubmit={save}><input required placeholder="Nomor kamar" value={form.room_number} onChange={(event) => setForm({ ...form, room_number: event.target.value })} /><select required value={form.room_type_id} onChange={(event) => setForm({ ...form, room_type_id: event.target.value })}><option value="">Tipe kamar</option>{roomTypes.map((type) => <option key={type.id} value={type.id}>{type.name} - {money(type.base_price)}</option>)}</select><input required type="number" min="0" placeholder="Floor" value={form.floor} onChange={(event) => setForm({ ...form, floor: event.target.value })} /><button className={styles.primaryButton} disabled={saving}>{saving ? 'Menyimpan...' : 'Simpan'}</button></form>}<div className={styles.roomsSummary}><div className={styles.roomsSummaryLead}><span className={styles.roomsSummaryIcon}>▦</span><div><strong>{rooms.length} kamar terdaftar</strong><span>{canManage ? 'Kelola inventaris kamar dari satu tempat.' : 'Informasi kamar siap digunakan untuk reservasi.'}</span></div></div><div className={styles.roomStat}><span className={`${styles.roomStatDot} ${styles.roomStatAvailable}`}></span><div><small>Available</small><strong>{availableCount}</strong></div></div><div className={styles.roomStat}><span className={`${styles.roomStatDot} ${styles.roomStatOccupied}`}></span><div><small>Occupied</small><strong>{occupiedCount}</strong></div></div><div className={styles.roomStat}><span className={`${styles.roomStatDot} ${styles.roomStatAttention}`}></span><div><small>Perlu perhatian</small><strong>{attentionCount}</strong></div></div></div><div className={styles.table}><div className={styles.tableHead}><span>ROOM</span><span>TYPE</span><span>FLOOR</span><span>RATE / NIGHT</span><span>AVAILABLE</span><span>STATUS</span></div>{rooms.map((room) => <div className={styles.tableRow} key={room.id}><strong>#{room.room_number}</strong><span>{room.room_type?.name ?? '-'}</span><span>{room.floor ?? '-'}</span><span>{money(room.room_type?.base_price)}</span><span className={styles.availableCount}>{room.available_rooms ?? (room.status === 'Available' ? 1 : 0)}</span><span><Status value={room.status} /></span></div>)}</div>{!rooms.length && <Empty text="Belum ada kamar di database." />}</div></Panel>;
}
