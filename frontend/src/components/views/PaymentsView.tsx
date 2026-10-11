'use client';

import { FormEvent, useState } from 'react';
import styles from '@/app/page.module.css';
import { api } from '@/services/api';
import { confirmAction, successAlert, textPrompt } from '@/services/alerts';
import { money } from '@/services/formatters';
import type { HotelSettings, Paginated, Payment, PendingServicePayment, Reservation } from '@/types';
import { Empty, ErrorNotice, Panel, Pagination } from '@/components/ui';

type Props = {
  payments: Payment[];
  reservations: Reservation[];
  canPay: boolean;
  canRefund: boolean;
  canVoid: boolean;
  canManageSettings: boolean;
  settings: HotelSettings;
  pendingService?: PendingServicePayment | null;
  onSaved: () => void;
  onGoToCheckIn?: () => void;
  onPaymentComplete?: () => void;
  currency?: string;
  pagination?: Paginated<Payment>;
  onPageChange?: (page: number) => void;
};

export default function PaymentsView({ payments, reservations, canPay, canRefund, canVoid, canManageSettings, settings, pendingService, onSaved, onGoToCheckIn, onPaymentComplete, currency = settings.currency, pagination, onPageChange }: Props) {
  const [reservationId, setReservationId] = useState('');
  const [method, setMethod] = useState('Cash');
  const [amount, setAmount] = useState('');
  const [notes, setNotes] = useState('');
  const [rateForm, setRateForm] = useState({ tax_rate: String(settings.tax_rate), service_charge_rate: String(settings.service_charge_rate) });
  const [error, setError] = useState('');
  const [saving, setSaving] = useState(false);

  const paidTotal = (item: Reservation) => item.payments?.filter((payment) => ['Paid', 'Refunded'].includes(payment.payment_status)).reduce((total, payment) => total + (payment.transaction_type === 'Refund' ? -Number(payment.amount) : Number(payment.amount)), 0) ?? 0;
  const currentOutstanding = (item: Reservation) => Math.max(Number(item.grand_total) - paidTotal(item), 0);
  const effectiveReservationId = pendingService ? String(pendingService.reservationId) : reservationId;
  const selectedReservation = reservations.find((item) => String(item.id) === effectiveReservationId);
  const isPendingServicePayment = Boolean(pendingService && String(pendingService.reservationId) === effectiveReservationId);
  const pendingServiceNames = pendingService?.services.map((service) => service.serviceName).join(', ') ?? '';
  const pendingServiceTotal = isPendingServicePayment && pendingService
    ? pendingService.services.reduce((total, service) => total + Number(service.servicePrice), 0) * pendingService.quantity
    : 0;
  function projectedBreakdown(item: Reservation, additionalServices = 0) {
    const subtotal = Number(item.subtotal) + additionalServices;
    const memberDiscount = Math.round(subtotal * (Number(item.member_discount_rate ?? 0) / 100) * 100) / 100;
    const manualDiscount = Number(item.manual_discount ?? 0);
    const discount = Math.min(subtotal, Math.round((manualDiscount + memberDiscount) * 100) / 100);
    const discountedSubtotal = Math.max(subtotal - discount, 0);
    const serviceCharge = Math.round(discountedSubtotal * (Number(item.service_charge_rate ?? 0) / 100) * 100) / 100;
    const tax = Math.round((discountedSubtotal + serviceCharge) * (Number(item.tax_rate ?? 0) / 100) * 100) / 100;

    return {
      subtotal,
      discount,
      serviceCharge,
      tax,
      total: Math.max(Math.round((discountedSubtotal + serviceCharge + tax) * 100) / 100, 0),
    };
  }

  const selectedBreakdown = selectedReservation ? projectedBreakdown(selectedReservation, pendingServiceTotal) : null;
  const selectedTotal = selectedBreakdown?.total ?? 0;
  const selectedBalance = selectedReservation ? Math.max(selectedTotal - paidTotal(selectedReservation), 0) : 0;

  const displayAmount = amount === '' && selectedReservation ? String(selectedBalance) : amount;

  async function save(event: FormEvent) {
    event.preventDefault();
    if (!canPay || !selectedReservation) return;
    const isAddingService = isPendingServicePayment && pendingService;
    const isFullSettlement = Number(displayAmount) >= selectedBalance;
    if (!await confirmAction(isAddingService ? 'Bayar dan tambahkan service?' : 'Catat pembayaran?', isAddingService ? `${pendingServiceNames} akan ditambahkan setelah pembayaran berhasil.` : `Pembayaran ${money(displayAmount, currency)} dengan metode ${method} akan dicatat.`)) return;

    setError('');
    setSaving(true);
    try {
      await api(`/reservations/${effectiveReservationId}/payments`, {
        method: 'POST',
        body: JSON.stringify({
          payment_method: method,
          paid_amount: Number(displayAmount),
          notes: notes || undefined,
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
      setNotes('');
      await successAlert(isAddingService ? 'Service dan pembayaran berhasil' : 'Pembayaran berhasil dicatat', isAddingService ? 'Service sudah masuk ke reservasi dan total tagihan diperbarui.' : 'Pembayaran tersimpan di sistem.');
      onSaved();
      if (isAddingService) onPaymentComplete?.();
      else if (isFullSettlement) onGoToCheckIn?.();
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Pembayaran gagal disimpan.');
    } finally {
      setSaving(false);
    }
  }

  async function reverse(payment: Payment, type: 'refund' | 'void') {
    const reason = await textPrompt(type === 'refund' ? 'Refund pembayaran' : 'Void pembayaran', 'Alasan wajib dicatat untuk audit.');
    if (reason === null) return;
    setError('');
    setSaving(true);
    try {
      await api(`/payments/${payment.id}/${type}`, { method: 'POST', body: JSON.stringify({ reason }) });
      await successAlert(type === 'refund' ? 'Refund berhasil' : 'Pembayaran berhasil di-void', 'Saldo reservasi dan laporan keuangan sudah diperbarui.');
      onSaved();
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Transaksi gagal diproses.');
    } finally {
      setSaving(false);
    }
  }

  async function saveSettings(event: FormEvent) {
    event.preventDefault();
    setError('');
    setSaving(true);
    try {
      await api('/settings', { method: 'PATCH', body: JSON.stringify({ tax_rate: Number(rateForm.tax_rate), service_charge_rate: Number(rateForm.service_charge_rate), currency: settings.currency }) });
      await successAlert('Setting keuangan disimpan', 'Tarif baru berlaku untuk reservasi yang dibuat setelah perubahan ini.');
      onSaved();
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Setting gagal disimpan.');
    } finally {
      setSaving(false);
    }
  }

  return <Panel className={styles.paymentsPanel} title="Payments">
    {error && <ErrorNotice message={error} />}
    {canManageSettings && <form className={styles.inlineForm} onSubmit={saveSettings}>
      <label>Service charge (%)<input required min="0" max="100" step="0.01" type="number" value={rateForm.service_charge_rate} onChange={(event) => setRateForm({ ...rateForm, service_charge_rate: event.target.value })} /></label>
      <label>Pajak (%)<input required min="0" max="100" step="0.01" type="number" value={rateForm.tax_rate} onChange={(event) => setRateForm({ ...rateForm, tax_rate: event.target.value })} /></label>
      <button className={styles.secondaryButton} disabled={saving}>Simpan tarif</button>
    </form>}
    {pendingService && <div className={styles.paymentIntent}><span>Service yang akan ditambahkan</span><strong>{pendingServiceNames} × {pendingService.quantity} orang</strong><b>{money(pendingServiceTotal, currency)}</b></div>}
    {canPay && <form className={styles.inlineForm} onSubmit={save}>
      <select required value={effectiveReservationId} disabled={Boolean(pendingService)} onChange={(event) => { setReservationId(event.target.value); const item = reservations.find((row) => String(row.id) === event.target.value); setAmount(item ? String(currentOutstanding(item)) : ''); }}><option value="">Pilih reservasi belum lunas</option>{reservations.filter((item) => ['Pending', 'Confirmed', 'Checked In'].includes(item.status) && (currentOutstanding(item) > 0 || pendingService?.reservationId === item.id)).map((item) => <option key={item.id} value={item.id}>{item.booking_number} - {item.user?.name ?? item.guest?.full_name ?? '-'} (Sisa {money(projectedBreakdown(item, pendingService?.reservationId === item.id ? pendingServiceTotal : 0).total - paidTotal(item), currency)})</option>)}</select>
      <select value={method} onChange={(event) => setMethod(event.target.value)}><option>Cash</option><option>Debit Card</option><option>Credit Card</option><option>Bank Transfer</option><option>QRIS</option></select>
      <input required min="1" type="number" placeholder="Nominal dibayar (boleh parsial)" value={displayAmount} onChange={(event) => setAmount(event.target.value)} />
      <input placeholder="Catatan pembayaran (opsional)" value={notes} onChange={(event) => setNotes(event.target.value)} />
      <button className={styles.primaryButton} disabled={saving}>{saving ? 'Memproses...' : pendingService ? 'Bayar & tambah service' : 'Catat pembayaran'}</button>
    </form>}
    {canPay && selectedReservation && <div className={styles.paymentSummary}>
      <div><span>Harga kamar ({selectedReservation.number_of_nights} malam)</span><strong>{money(Number(selectedReservation.room_price) * selectedReservation.number_of_nights, currency)}</strong></div>
      <div><span>Service yang sudah tercatat</span><strong>{money(selectedReservation.services?.reduce((total, service) => total + Number(service.subtotal), 0) ?? 0, currency)}</strong></div>
      {pendingServiceTotal > 0 && <div><span>Service baru: {pendingServiceNames}</span><strong>{money(pendingServiceTotal, currency)}</strong></div>}
       {Number(selectedReservation.member_discount_amount ?? 0) > 0 && <div><span>Member discount</span><strong>-{money(Number(selectedReservation.member_discount_amount), currency)}</strong></div>}
      <div><span>Service charge ({Number(selectedReservation.service_charge_rate ?? 0)}%)</span><strong>{money(selectedBreakdown?.serviceCharge ?? 0, currency)}</strong></div>
      <div><span>Pajak ({Number(selectedReservation.tax_rate ?? 0)}%)</span><strong>{money(selectedBreakdown?.tax ?? 0, currency)}</strong></div>
      <div><span>Total tagihan</span><strong>{money(selectedTotal, currency)}</strong></div>
      <div><span>Sudah dibayar</span><strong>{money(paidTotal(selectedReservation), currency)}</strong></div>
      <div className={styles.paymentBalance}><span>Sisa pembayaran</span><strong>{money(selectedBalance, currency)}</strong></div>
    </div>}
    <div className={styles.table}><div className={styles.tableHead}><span>TRANSACTION</span><span>BOOKING</span><span>METHOD</span><span>AMOUNT</span><span>STATUS</span><span>ACTION</span></div>{payments.map((payment) => <div className={styles.tableRow} key={payment.id}><strong>{payment.payment_number}<small>{payment.transaction_type ?? 'Payment'}</small></strong><span>{payment.reservation?.booking_number ?? '-'}</span><span>{payment.payment_method}</span><span>{payment.transaction_type === 'Refund' ? '-' : ''}{money(payment.amount, currency)}</span><span>{payment.payment_status}</span><span>{payment.transaction_type !== 'Refund' && payment.payment_status === 'Paid' ? <>{canRefund && <button disabled={saving} className={styles.rowActionButton} onClick={() => void reverse(payment, 'refund')}>Refund</button>}{canVoid && <button disabled={saving} className={styles.rowActionButton} onClick={() => void reverse(payment, 'void')}>Void</button>}</> : '-'}</span></div>)}</div>
    {!payments.length && <Empty text="Belum ada pembayaran." />}
    {pagination && onPageChange && <Pagination currentPage={pagination.current_page} lastPage={pagination.last_page} total={pagination.total} onPageChange={onPageChange} />}
  </Panel>;
}
