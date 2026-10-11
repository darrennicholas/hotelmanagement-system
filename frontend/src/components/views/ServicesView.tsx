'use client';

import { FormEvent, useState } from 'react';
import styles from '@/app/page.module.css';
import { api } from '@/services/api';
import { confirmAction, successAlert } from '@/services/alerts';
import { money } from '@/services/formatters';
import type { HotelService, PendingServicePayment, Reservation } from '@/types';
import { Empty, ErrorNotice, Panel, Status } from '@/components/ui';

type Props = {
  services: HotelService[];
  reservations: Reservation[];
  canManage: boolean;
  isUser: boolean;
  onSaved: () => void;
  onGoToPayments: (request: PendingServicePayment) => void;
  currency?: string;
};

export default function ServicesView({ services, reservations, canManage, isUser, onSaved, onGoToPayments, currency = 'IDR' }: Props) {
  const [reservationId, setReservationId] = useState('');
  const [selectedServiceIds, setSelectedServiceIds] = useState<string[]>([]);
  const [quantity, setQuantity] = useState('1');
  const [showForm, setShowForm] = useState(false);
  const [error, setError] = useState('');
  const [saving, setSaving] = useState(false);
  const [form, setForm] = useState({ name: '', price: '', description: '' });

  const activeReservations = reservations.filter((reservation) => ['Pending', 'Confirmed', 'Checked In'].includes(reservation.status));
  const availableServices = isUser ? services : services.filter((service) => service.status);
  const selectedServices = availableServices.filter((service) => selectedServiceIds.includes(String(service.id)));
  const serviceQuantity = Number(quantity);
  const selectedTotal = selectedServices.reduce((total, service) => total + Number(service.price), 0) * (Number.isInteger(serviceQuantity) && serviceQuantity > 0 ? serviceQuantity : 0);

  function toggleService(serviceId: string) {
    setSelectedServiceIds((current) => current.includes(serviceId) ? current.filter((id) => id !== serviceId) : [...current, serviceId]);
  }

  function adjustQuantity(change: number) {
    setQuantity((current) => String(Math.max(1, (Number(current) || 1) + change)));
  }

  function payService(event: FormEvent) {
    event.preventDefault();
    const reservation = activeReservations.find((item) => String(item.id) === reservationId);
    if (!reservation || !selectedServices.length || !Number.isInteger(serviceQuantity) || serviceQuantity < 1) return;

    onGoToPayments({
      reservationId: reservation.id,
      quantity: serviceQuantity,
      services: selectedServices.map((service) => ({ serviceId: service.id, serviceName: service.name, servicePrice: service.price })),
    });
  }

  async function save(event: FormEvent) {
    event.preventDefault();
    if (!await confirmAction('Tambah service?', `${form.name || 'Service baru'} akan ditambahkan ke katalog layanan.`)) return;
    setError('');
    setSaving(true);
    try {
      await api('/services', { method: 'POST', body: JSON.stringify({ ...form, price: Number(form.price) }) });
      setShowForm(false);
      setForm({ name: '', price: '', description: '' });
      await successAlert('Service berhasil ditambahkan', 'Data layanan sudah tersimpan.');
      onSaved();
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Service gagal disimpan.');
    } finally {
      setSaving(false);
    }
  }

  async function toggle(service: HotelService) {
    if (!await confirmAction(`${service.status ? 'Nonaktifkan' : 'Aktifkan'} service?`, `Status ${service.name} akan diubah.`)) return;
    setError('');
    setSaving(true);
    try {
      await api(`/services/${service.id}`, { method: 'PUT', body: JSON.stringify({ status: !service.status }) });
      await successAlert('Status service diperbarui', `${service.name} sekarang ${service.status ? 'nonaktif' : 'aktif'}.`);
      onSaved();
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Status service gagal diubah.');
    } finally {
      setSaving(false);
    }
  }

  return <div className={styles.servicesStack}>
    <Panel title="Tambah layanan ke reservasi">
      {error && <ErrorNotice message={error} />}
      <form className={styles.serviceBookingForm} onSubmit={payService}>
        <div className={styles.serviceReservationField}>
          <div><span className={styles.serviceFieldLabel}>Reservasi</span></div>
          <select required value={reservationId} onChange={(event) => setReservationId(event.target.value)}><option value="">Pilih reservasi</option>{activeReservations.map((reservation) => <option key={reservation.id} value={reservation.id}>{reservation.booking_number} · #{reservation.room?.room_number ?? '-'} · {reservation.user?.name ?? reservation.guest?.full_name ?? '-'}</option>)}</select>
        </div>
        <div className={styles.serviceSelectionArea}>
          <div className={styles.serviceSelectionHeader}><div><span className={styles.serviceFieldLabel}>Layanan tersedia</span></div><strong>{availableServices.length} tersedia</strong></div>
          <div className={styles.serviceChecklist}>
            {availableServices.map((service) => <label key={service.id} className={selectedServiceIds.includes(String(service.id)) ? styles.serviceChecked : ''}><input type="checkbox" checked={selectedServiceIds.includes(String(service.id))} onChange={() => toggleService(String(service.id))} /><span><strong>{service.name}</strong></span><b>{money(service.price, currency)}</b></label>)}
            {!availableServices.length && <Empty text="Belum ada service aktif." />}
          </div>
        </div>
        <div className={styles.serviceBookingFooter}>
          <label className={styles.quantityControl}><span>Jumlah orang</span><div className={styles.quantityStepper}><button type="button" aria-label="Kurangi jumlah orang" onClick={() => adjustQuantity(-1)}>−</button><input required min="1" type="number" aria-label="Jumlah orang" value={quantity} onChange={(event) => setQuantity(event.target.value)} /><button type="button" aria-label="Tambah jumlah orang" onClick={() => adjustQuantity(1)}>+</button><em>orang</em></div></label>
          <div className={styles.serviceTotal}><span>Perkiraan total</span><strong>{money(selectedTotal, currency)}</strong></div>
          <button className={styles.primaryButton} type="submit" disabled={!selectedServices.length || !reservationId}>Bayar layanan <span aria-hidden="true">→</span></button>
        </div>
      </form>
    </Panel>

    <Panel title="Services" action={canManage ? <button className={styles.secondaryButton} onClick={() => setShowForm(!showForm)}>{showForm ? 'Tutup form' : '+ Add service'}</button> : undefined}>
      {showForm && canManage && <form className={styles.inlineForm} onSubmit={save}><input required placeholder="Nama service" value={form.name} onChange={(event) => setForm({ ...form, name: event.target.value })} /><input required min="0" type="number" placeholder="Harga" value={form.price} onChange={(event) => setForm({ ...form, price: event.target.value })} /><input placeholder="Deskripsi" value={form.description} onChange={(event) => setForm({ ...form, description: event.target.value })} /><button className={styles.primaryButton} disabled={saving}>{saving ? 'Menyimpan...' : 'Simpan'}</button></form>}
      <div className={styles.table}><div className={styles.tableHead}><span>SERVICE</span><span>DESCRIPTION</span><span>PRICE</span><span>STATUS</span><span>ACTION</span></div>{services.map((service) => <div className={styles.tableRow} key={service.id}><strong>{service.name}</strong><span>{service.description || '-'}</span><span>{money(service.price, currency)}</span><span><Status value={isUser || service.status ? 'Active' : 'Inactive'} /></span><span>{canManage ? <button disabled={saving} className={styles.rowActionButton} onClick={() => toggle(service)}>{service.status ? 'Nonaktifkan' : 'Aktifkan'}</button> : '-'}</span></div>)}</div>
      {!services.length && <Empty text="Belum ada service." />}
    </Panel>
  </div>;
}
