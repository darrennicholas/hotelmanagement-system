'use client';

import { FormEvent, useState } from 'react';
import styles from '@/app/page.module.css';
import { api } from '@/services/api';
import { confirmAction, successAlert } from '@/services/alerts';
import type { Facility, RoomType } from '@/types';
import { Empty, ErrorNotice, Panel } from '@/components/ui';

type Props = { facilities: Facility[]; roomTypes: RoomType[]; canManage: boolean; onSaved: () => void };

export default function FacilitiesView({ facilities, roomTypes, canManage, onSaved }: Props) {
  const [showForm, setShowForm] = useState(false);
  const [form, setForm] = useState({ name: '', description: '' });
  const [roomTypeId, setRoomTypeId] = useState('');
  const [selectedFacilityIds, setSelectedFacilityIds] = useState<number[]>([]);
  const [error, setError] = useState('');
  const [saving, setSaving] = useState(false);

  function chooseRoomType(value: string) {
    setRoomTypeId(value);
    const roomType = roomTypes.find((item) => String(item.id) === value);
    setSelectedFacilityIds(roomType?.facilities?.map((facility) => facility.id) ?? []);
  }

  function toggleFacility(id: number) {
    setSelectedFacilityIds((current) => current.includes(id) ? current.filter((item) => item !== id) : [...current, id]);
  }

  async function save(event: FormEvent) {
    event.preventDefault();
    if (!await confirmAction('Tambah fasilitas?', `${form.name || 'Fasilitas baru'} akan ditambahkan ke daftar fasilitas.`)) return;
    setSaving(true);
    setError('');
    try {
      await api('/facilities', { method: 'POST', body: JSON.stringify(form) });
      setForm({ name: '', description: '' });
      setShowForm(false);
      await successAlert('Fasilitas berhasil ditambahkan', 'Fasilitas siap dihubungkan ke tipe kamar.');
      onSaved();
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Fasilitas gagal disimpan.');
    } finally {
      setSaving(false);
    }
  }

  async function saveRoomTypeFacilities(event: FormEvent) {
    event.preventDefault();
    const roomType = roomTypes.find((item) => String(item.id) === roomTypeId);
    if (!roomType) return;
    if (!await confirmAction('Simpan fasilitas kamar?', `${roomType.name} akan menggunakan fasilitas yang dipilih.`)) return;
    setSaving(true);
    setError('');
    try {
      await api(`/room-types/${roomType.id}`, { method: 'PUT', body: JSON.stringify({ facility_ids: selectedFacilityIds }) });
      await successAlert('Fasilitas kamar diperbarui', `Fasilitas untuk ${roomType.name} sudah tersimpan.`);
      onSaved();
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Fasilitas kamar gagal diperbarui.');
    } finally {
      setSaving(false);
    }
  }

  return <div className={styles.servicesStack}>
    <Panel className={styles.facilitiesPanel} title="Facilities" subtitle="Fasilitas mengikuti tipe kamar yang dipesan" action={canManage ? <button className={styles.secondaryButton} onClick={() => setShowForm(!showForm)}>{showForm ? 'Tutup form' : '+ Add facility'}</button> : <span className={styles.liveLabel}>{facilities.length} fasilitas</span>}>
      {error && <ErrorNotice message={error} />}
      {showForm && <form className={styles.inlineForm} onSubmit={save}><input required placeholder="Nama fasilitas" value={form.name} onChange={(event) => setForm({ ...form, name: event.target.value })} /><input placeholder="Deskripsi" value={form.description} onChange={(event) => setForm({ ...form, description: event.target.value })} /><button className={styles.primaryButton} disabled={saving}>{saving ? 'Menyimpan...' : 'Simpan'}</button></form>}
      <div className={styles.facilityList}>{facilities.map((facility) => <article className={styles.facilityItem} key={facility.id}><div className={styles.facilityIcon}>+</div><div className={styles.facilityInfo}><strong>{facility.name}</strong><span>{facility.description || 'Fasilitas standar untuk tamu hotel.'}</span></div><div className={styles.facilityRooms}><small>TERSEDIA DI</small><b>{facility.room_types?.length ? facility.room_types.map((roomType) => roomType.name).join(', ') : 'Belum dihubungkan ke tipe kamar'}</b></div></article>)}</div>
      {!facilities.length && <Empty text="Belum ada fasilitas di database." />}
    </Panel>

    {canManage && <Panel className={styles.facilitySettingsPanel} title="Atur fasilitas per tipe kamar" subtitle="Pengaturan ini akan ikut tampil pada kolom Facilities di Reservations">
      <form className={styles.facilityAssignment} onSubmit={saveRoomTypeFacilities}>
        <select required value={roomTypeId} onChange={(event) => chooseRoomType(event.target.value)}><option value="">Pilih tipe kamar</option>{roomTypes.map((roomType) => <option key={roomType.id} value={roomType.id}>{roomType.name}</option>)}</select>
        {roomTypeId ? <div className={styles.facilityChecks}>{facilities.map((facility) => <label key={facility.id}><input type="checkbox" checked={selectedFacilityIds.includes(facility.id)} onChange={() => toggleFacility(facility.id)} /> <span>{facility.name}</span></label>)}</div> : <p className={styles.serviceNote}>Pilih tipe kamar untuk mengatur fasilitasnya.</p>}
        <button className={styles.primaryButton} type="submit" disabled={saving || !roomTypeId}>{saving ? 'Menyimpan...' : 'Simpan fasilitas kamar'}</button>
      </form>
    </Panel>}
  </div>;
}
