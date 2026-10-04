'use client';

import { FormEvent, useState } from 'react';
import styles from '@/app/page.module.css';
import { api } from '@/services/api';
import { confirmAction, successAlert } from '@/services/alerts';
import { money } from '@/services/formatters';
import type { Payment, PendingServicePayment, Reservation } from '@/types';
import { Empty, ErrorNotice, Panel } from '@/components/ui';

type Props = {
  payments: Payment[];
  reservations: Reservation[];
  canPay: boolean;
  pendingService?: PendingServicePayment | null;
  onSaved: () => void;
  onGoToCheckIn?: () => void;
  onPaymentComplete?: () => void;
};

export default function PaymentsView({ payments, reservations, canPay, pendingService, onSaved, onGoToCheckIn, onPaymentComplete }: Props) {
  const [reservationId, setReservationId] = useState('');
  const [method, setMethod] = useState('Cash');
  const [amount, setAmount] = useState('');
  const [error, setError] = useState('');
  const [saving, setSaving] = useState(false);

  const paidTotal = (item: Reservation) => item.payments?.filter((payment) => payment.payment_status === 'Paid').reduce((total, payment) => total + Number(payment.amount), 0) ?? 0;
  const currentOutstanding = (item: Reservation) => Math.max(Number(item.grand_total) - paidTotal(item), 0);
  const effectiveReservationId = pendingService ? String(pendingService.reservationId) : reservationId;
  const selectedReservation = reservations.find((item) => String(item.id) === effectiveReservationId);
  const isPendingServicePayment = Boolean(pendingService && String(pendingService.reservationId) === effectiveReservationId);
  const pendingServiceNames = pendingService?.services.map((service) => service.serviceName).join(', ') ?? '';
  const pendingServiceTotal = isPendingServicePayment && pendingService
    ? pendingService.services.reduce((total, service) => total + Number(service.servicePrice), 0) * pendingService.quantity
    : 0;
  const selectedTotal = selectedReservation ? Number(selectedReservation.grand_total) + pendingServiceTotal : 0;
  const selectedBalance = selectedReservation ? Math.max(selectedTotal - paidTotal(selectedReservation), 0) : 0;

  const displayAmount = amount === '' && selectedReservation ? String(selectedBalance) : amount;

  async function save(event: FormEvent) {
    event.preventDefault();
    if (!canPay || !selectedReservation) return;
    const isAddingService = isPendingServicePayment && pendingService;
    if (!await confirmAction(isAddingService ? 'Bayar dan tambahkan service?' : 'Catat pembayaran?', isAddingService ? `${pendingServiceNames} akan ditambahkan setelah pembayaran berhasil.` : `Pembayaran ${money(amount)} dengan metode ${method} akan dicatat.`)) return;

    setError('');
    setSaving(true);
    try {
      await api(`/reservations/${effectiveReservationId}/payments`, {
        method: 'POST',
        body: JSON.stringify({
          payment_method: method,
          paid_amount: Number(displayAmount),
          ...(isAddingService ? {
            services: pendingService.services.map((service) => ({
              hotel_service_id: service.serviceId,
              quantity: pendingService.quantity,
            })),
          } : {}),
        }),
      });
      setReservationId('');
      setAmount('');
      await successAlert(isAddingService ? 'Service dan pembayaran berhasil' : 'Pembayaran berhasil dicatat', isAddingService ? 'Service sudah masuk ke reservasi dan total tagihan diperbarui.' : 'Pembayaran tersimpan di sistem.');
      onSaved();
      if (isAddingService) onPaymentComplete?.();
      else onGoToCheckIn?.();
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Pembayaran gagal disimpan.');
    } finally {
      setSaving(false);
    }
  }

  return <Panel className={styles.paymentsPanel} title="Payments" subtitle="Harga kamar, fasilitas layanan, dan service tambahan dirangkum di sini" action={canPay ? <span className={styles.liveLabel}>{payments.length} transaksi</span> : <span className={styles.readOnlyBadge}>View only · {payments.length} transaksi</span>}>
    {error && <ErrorNotice message={error} />}
    {!canPay && <div className={styles.paymentReadOnlyNote}><strong>Mode hanya lihat</strong><span>Role Admin, Receptionist, dan Manager dapat memantau transaksi, tetapi tidak dapat mencatat pembayaran.</span></div>}
    {pendingService && <div className={styles.paymentIntent}><span>Service yang akan ditambahkan</span><strong>{pendingServiceNames} × {pendingService.quantity} orang</strong><b>{money(pendingServiceTotal)}</b></div>}
    {canPay && <form className={styles.inlineForm} onSubmit={save}>
      <select required value={effectiveReservationId} disabled={Boolean(pendingService)} onChange={(event) => { setReservationId(event.target.value); const item = reservations.find((row) => String(row.id) === event.target.value); setAmount(item ? String(currentOutstanding(item)) : ''); }}><option value="">Pilih reservasi belum lunas</option>{reservations.filter((item) => ['Pending', 'Confirmed', 'Checked In'].includes(item.status) && (currentOutstanding(item) > 0 || pendingService?.reservationId === item.id)).map((item) => <option key={item.id} value={item.id}>{item.booking_number} - {item.user?.name ?? '-'} (Sisa {money(currentOutstanding(item) + (pendingService?.reservationId === item.id ? pendingServiceTotal : 0))})</option>)}</select>
      <select value={method} onChange={(event) => setMethod(event.target.value)}><option>Cash</option><option>Debit Card</option><option>Credit Card</option><option>Bank Transfer</option><option>QRIS</option></select>
      <input required min={selectedBalance} type="number" placeholder="Nominal dibayar" value={displayAmount} onChange={(event) => setAmount(event.target.value)} />
      <button className={styles.primaryButton} disabled={saving}>{saving ? 'Memproses...' : pendingService ? 'Bayar & tambah service' : 'Catat pembayaran'}</button>
    </form>}
    {canPay && selectedReservation && <div className={styles.paymentSummary}>
      <div><span>Harga kamar ({selectedReservation.number_of_nights} malam)</span><strong>{money(Number(selectedReservation.room_price) * selectedReservation.number_of_nights)}</strong></div>
      <div><span>Service yang sudah tercatat</span><strong>{money(selectedReservation.services?.reduce((total, service) => total + Number(service.subtotal), 0) ?? 0)}</strong></div>
      {pendingServiceTotal > 0 && <div><span>Service baru: {pendingServiceNames}</span><strong>{money(pendingServiceTotal)}</strong></div>}
      <div><span>Total tagihan</span><strong>{money(selectedTotal)}</strong></div>
      <div><span>Sudah dibayar</span><strong>{money(paidTotal(selectedReservation))}</strong></div>
      <div className={styles.paymentBalance}><span>Sisa pembayaran</span><strong>{money(selectedBalance)}</strong></div>
    </div>}
    <div className={styles.table}><div className={styles.tableHead}><span>PAYMENT NO.</span><span>BOOKING</span><span>USER</span><span>METHOD</span><span>AMOUNT</span><span>DATE</span></div>{payments.map((payment) => <div className={styles.tableRow} key={payment.id}><strong>{payment.payment_number}</strong><span>{payment.reservation?.booking_number ?? '-'}</span><span>{payment.reservation?.user?.name ?? '-'}</span><span>{payment.payment_method}</span><span>{money(payment.amount)}</span><span>{payment.paid_at ? new Date(payment.paid_at).toLocaleString('id-ID') : '-'}</span></div>)}</div>
    {!payments.length && <Empty text="Belum ada pembayaran." />}
  </Panel>;
}
