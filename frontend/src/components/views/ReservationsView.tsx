'use client';

import { FormEvent, useEffect, useMemo, useState } from 'react';

import styles from '@/app/page.module.css';
import { api } from '@/services/api';
import { confirmAction, successAlert, textPrompt } from '@/services/alerts';
import { dateLabel, money, today } from '@/services/formatters';
import type { HotelService, HotelSettings, Paginated, Reservation, Room, User } from '@/types';
import { Empty, ErrorNotice, Panel, Pagination, Status } from '@/components/ui';

type Props = {
    reservations: Reservation[];
    users: User[];
    canChooseUser: boolean;
    canCreateReservation: boolean;
    canManageStay: boolean;
    canUseMemberDiscount: boolean;
    memberDiscountRate?: string | number;
    memberMinimumGuests?: number;
    rooms: Room[];
    services?: HotelService[];
    settings: HotelSettings;
    selectedDate?: string;
    onSaved: () => void;
    onGoToPayments?: () => void;
    checkOnly?: boolean;
    currency?: string;
    pagination?: Paginated<Reservation>;
    onPageChange?: (page: number) => void;
};

type FormState = {
    user_id: string;
    guest_mode: 'registered' | 'walk_in';
    guest_name: string;
    guest_phone: string;
    room_id: string;
    check_in_date: string;
    check_out_date: string;
    number_of_guests: string;
    notes: string;
};

const makeInitialForm = (): FormState => ({
    user_id: '',
    guest_mode: 'registered',
    guest_name: '',
    guest_phone: '',
    room_id: '',
    check_in_date: today(),
    check_out_date: '',
    number_of_guests: '1',
    notes: '',
});

const dateOnly = (value?: string | null) => value ? String(value).slice(0, 10) : '';

const nightsBetween = (checkIn: string, checkOut: string) => {
    if (!checkIn || !checkOut) return 0;
    const start = new Date(`${checkIn}T00:00:00`);
    const end = new Date(`${checkOut}T00:00:00`);
    return Math.max(0, Math.round((end.getTime() - start.getTime()) / 86400000));
};

const statusLabel: Record<string, string> = {
    Pending: 'Menunggu konfirmasi',
    Confirmed: 'Dikonfirmasi',
    'Checked In': 'Sedang menginap',
    'Checked Out': 'Selesai',
    Completed: 'Selesai',
    Cancelled: 'Dibatalkan',
    'No Show': 'Tidak datang',
};

export default function ReservationsView({
    reservations,
    users,
    canChooseUser,
    canCreateReservation,
    canManageStay,
    canUseMemberDiscount,
    memberDiscountRate = 0,
    memberMinimumGuests = 2,
    rooms,
    services = [],
    settings,
    selectedDate,
    onSaved,
    onGoToPayments,
    checkOnly = false,
    currency = 'IDR',
    pagination,
    onPageChange,
}: Props) {
    const isUserExperience = !checkOnly && !canChooseUser;
    const currentDate = today();
    const [showForm, setShowForm] = useState(false);
    const [error, setError] = useState('');
    const [search, setSearch] = useState('');
    const [statusFilter, setStatusFilter] = useState('all');
    const [saving, setSaving] = useState(false);
    const [availabilityLoading, setAvailabilityLoading] = useState(false);
    const [availableRooms, setAvailableRooms] = useState<Room[]>([]);
    const [selectedServiceIds, setSelectedServiceIds] = useState<number[]>([]);
    const [serviceQuantity, setServiceQuantity] = useState('1');
    const [form, setForm] = useState<FormState>(makeInitialForm);

    const setField = <K extends keyof FormState>(field: K, value: FormState[K]) => {
        setForm((current) => ({ ...current, [field]: value }));
    };

    const formNights = nightsBetween(form.check_in_date, form.check_out_date);
    const guestCount = Math.max(1, Number(form.number_of_guests) || 1);
    const hasValidDateRange = Boolean(form.check_in_date && form.check_out_date && formNights > 0);
    const roomOptions = hasValidDateRange ? availableRooms : rooms.filter((room) => room.status !== 'Maintenance');
    const chosenRoom = roomOptions.find((room) => String(room.id) === form.room_id);
    const roomCost = chosenRoom && formNights ? Number(chosenRoom.room_type?.base_price ?? 0) * formNights : 0;
    const selectedServices = services.filter((service) => selectedServiceIds.includes(service.id));
    const servicePeople = Math.max(1, Number(serviceQuantity) || 1);
    const serviceCost = selectedServices.reduce((total, service) => total + Number(service.price), 0) * servicePeople;
    const membershipEligible = canUseMemberDiscount && guestCount >= memberMinimumGuests;
    const estimatedSubtotal = roomCost + serviceCost;
    const estimatedDiscount = membershipEligible ? estimatedSubtotal * (Number(memberDiscountRate) / 100) : 0;
    const estimatedAfterDiscount = Math.max(estimatedSubtotal - estimatedDiscount, 0);
    const estimatedServiceCharge = estimatedAfterDiscount * (Number(settings.service_charge_rate) / 100);
    const estimatedTax = (estimatedAfterDiscount + estimatedServiceCharge) * (Number(settings.tax_rate) / 100);
    const estimatedTotal = estimatedAfterDiscount + estimatedServiceCharge + estimatedTax;

    function toggleService(serviceId: number) {
        setSelectedServiceIds((current) => current.includes(serviceId)
            ? current.filter((id) => id !== serviceId)
            : [...current, serviceId]);
    }

    useEffect(() => {
        if (!showForm) return;

        if (!hasValidDateRange) return;

        const controller = new AbortController();
        const loadingTimer = window.setTimeout(() => setAvailabilityLoading(true), 0);

        api<Room[]>(
            `/rooms/availability?check_in=${encodeURIComponent(form.check_in_date)}&check_out=${encodeURIComponent(form.check_out_date)}&guests=${guestCount}`,
            { signal: controller.signal },
        )
            .then((data) => {
                setAvailableRooms(data);
                setForm((current) => data.some((room) => String(room.id) === current.room_id)
                    ? current
                    : { ...current, room_id: '' });
            })
            .catch((requestError) => {
                if (requestError instanceof DOMException && requestError.name === 'AbortError') return;
                setError(requestError instanceof Error ? requestError.message : 'Ketersediaan kamar gagal dimuat.');
            })
            .finally(() => setAvailabilityLoading(false));

        return () => {
            window.clearTimeout(loadingTimer);
            controller.abort();
        };
    }, [showForm, form.check_in_date, form.check_out_date, guestCount, hasValidDateRange]);

    function isPaid(item: Reservation) {
        const paidAmount = item.payments
            ?.filter((payment) => ['Paid', 'Refunded'].includes(payment.payment_status))
            .reduce((total, payment) => total + (payment.transaction_type === 'Refund' ? -Number(payment.amount) : Number(payment.amount)), 0) ?? 0;
        return paidAmount >= Number(item.grand_total);
    }

    function hasPaidPayment(item: Reservation) {
        return paidAmount(item) > 0;
    }

    function paidAmount(item: Reservation) {
        return item.payments
            ?.filter((payment) => ['Paid', 'Refunded'].includes(payment.payment_status))
            .reduce((total, payment) => total + (payment.transaction_type === 'Refund' ? -Number(payment.amount) : Number(payment.amount)), 0) ?? 0;
    }

    const filtered = useMemo(() => {
        const normalizedSearch = search.trim().toLowerCase();
        return reservations.filter((item) => {
            if (checkOnly && !['Confirmed', 'Checked In'].includes(item.status)) return false;
            if (statusFilter === 'upcoming' && !['Pending', 'Confirmed'].includes(item.status)) return false;
            if (statusFilter === 'payment' && (item.status === 'Cancelled' || isPaid(item))) return false;
            if (statusFilter === 'completed' && !['Checked Out', 'Completed'].includes(item.status)) return false;
            if (!normalizedSearch) return true;
            const haystack = [item.booking_number, item.user?.name, item.guest?.full_name, item.guest?.phone, item.room?.room_number, item.room?.room_type?.name]
                .filter(Boolean).join(' ').toLowerCase();
            return haystack.includes(normalizedSearch);
        });
    }, [checkOnly, reservations, search, statusFilter]);

    const counts = useMemo(() => ({
        all: reservations.length,
        upcoming: reservations.filter((item) => ['Pending', 'Confirmed'].includes(item.status)).length,
        payment: reservations.filter((item) => item.status !== 'Cancelled' && !isPaid(item)).length,
        completed: reservations.filter((item) => ['Checked Out', 'Completed'].includes(item.status)).length,
    }), [reservations]);

    async function create(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        if (!canCreateReservation || !form.room_id) {
            setError('Pilih kamar yang tersedia terlebih dahulu.');
            return;
        }

        if (canChooseUser && form.guest_mode === 'registered' && !form.user_id) {
            setError('Pilih user terdaftar atau pilih tamu walk-in.');
            return;
        }

        if (canChooseUser && form.guest_mode === 'walk_in' && !form.guest_name.trim()) {
            setError('Nama tamu walk-in wajib diisi.');
            return;
        }

        const confirmed = await confirmAction('Buat reservasi sekarang?', 'Kamar akan dikunci untuk rentang tanggal yang dipilih.');
        if (!confirmed) return;

        setError('');
        setSaving(true);
        try {
            await api('/reservations', {
                method: 'POST',
                body: JSON.stringify({
                    ...(canChooseUser && form.guest_mode === 'registered' ? { user_id: Number(form.user_id) } : {}),
                    ...(canChooseUser && form.guest_mode === 'walk_in' ? {
                        guest: {
                            full_name: form.guest_name,
                            phone: form.guest_phone || undefined,
                        },
                    } : {}),
                    room_id: Number(form.room_id),
                    check_in_date: form.check_in_date,
                    check_out_date: form.check_out_date,
                    number_of_guests: guestCount,
                    notes: form.notes || undefined,
                    ...(selectedServices.length ? {
                        services: selectedServices.map((service) => ({
                            hotel_service_id: service.id,
                            quantity: servicePeople,
                        })),
                    } : {}),
                }),
            });
            setShowForm(false);
            setForm(makeInitialForm());
            setSelectedServiceIds([]);
            setServiceQuantity('1');
            await successAlert('Reservasi berhasil dibuat', 'Detail booking sudah tersimpan. Anda dapat melanjutkan pembayaran dari kartu reservasi.');
            onSaved();
        } catch (requestError) {
            setError(requestError instanceof Error ? requestError.message : 'Reservasi gagal disimpan.');
        } finally {
            setSaving(false);
        }
    }

    async function action(item: Reservation, type: 'confirm' | 'check-in' | 'check-out' | 'cancel' | 'no-show') {
        const labels = { confirm: 'konfirmasi reservasi', 'check-in': 'check-in tamu', 'check-out': 'check-out tamu', cancel: 'pembatalan reservasi', 'no-show': 'status no-show' };
        let reason: string | null = null;
        const outsideSchedule = type === 'check-in'
            ? !(dateOnly(item.check_in_date) <= currentDate && currentDate < dateOnly(item.check_out_date))
            : type === 'check-out' && dateOnly(item.check_out_date) !== currentDate;

        if (type === 'cancel' || type === 'no-show' || outsideSchedule) {
            reason = await textPrompt(`Alasan ${labels[type]}`, outsideSchedule ? 'Aksi dilakukan di luar jadwal reservasi.' : 'Alasan akan disimpan pada audit reservasi.');
            if (reason === null) return;
        }

        const confirmed = await confirmAction(
            `Lanjutkan ${labels[type]}?`,
            type === 'cancel' ? 'Reservasi akan dibatalkan dan kamar dilepas.' : 'Status reservasi akan diperbarui.',
        );
        if (!confirmed) return;

        setError('');
        setSaving(true);
        try {
            await api(`/reservations/${item.id}/${type}`, {
                method: 'POST',
                body: reason ? JSON.stringify(type === 'cancel' || type === 'no-show' ? { reason } : { override_reason: reason }) : undefined,
            });
            await successAlert('Aksi berhasil', 'Status reservasi berhasil diperbarui.');
            onSaved();
        } catch (requestError) {
            setError(requestError instanceof Error ? requestError.message : 'Aksi gagal.');
        } finally {
            setSaving(false);
        }
    }

    const renderAction = (item: Reservation) => {
        const paid = isPaid(item);
        const hasPayment = hasPaidPayment(item);
        const actualDate = !checkOnly || !selectedDate || selectedDate === currentDate;

        if (item.status === 'Pending' && canManageStay) return <><button className={styles.reservationActionPrimary} disabled={saving} onClick={() => action(item, 'confirm')}>Konfirmasi</button><button className={styles.reservationActionDanger} disabled={saving || dateOnly(item.check_in_date) > currentDate} onClick={() => action(item, 'no-show')}>No-show</button><button className={styles.reservationActionDanger} disabled={saving} onClick={() => action(item, 'cancel')}>Batalkan</button></>;
        if (item.status === 'Pending') return <><button className={styles.reservationActionPayment} disabled={saving} onClick={onGoToPayments}>Bayar deposit</button>{!hasPayment && <button className={styles.reservationActionDanger} disabled={saving} onClick={() => action(item, 'cancel')}>Batalkan</button>}</>;
        if (item.status === 'Confirmed' && !checkOnly) return <><button className={styles.reservationActionPayment} disabled={saving || paid} onClick={onGoToPayments}>{paid ? 'Lunas' : hasPayment ? 'Lanjut bayar' : 'Bayar sekarang'}</button>{(canManageStay || !hasPayment) && <button className={styles.reservationActionDanger} disabled={saving} onClick={() => action(item, 'cancel')}>Batalkan</button>}</>;
        if (item.status === 'Confirmed' && checkOnly && canManageStay) return <><button className={styles.reservationActionPrimary} disabled={saving || !actualDate} onClick={() => action(item, 'check-in')}>Check-in</button><button className={styles.reservationActionDanger} disabled={saving || dateOnly(item.check_in_date) > currentDate} onClick={() => action(item, 'no-show')}>No-show</button></>;
        if (item.status === 'Checked In' && checkOnly && canManageStay) return <><button className={styles.reservationActionPrimary} disabled={saving || !paid || !actualDate} onClick={() => action(item, 'check-out')}>Check-out</button>{!paid && <button className={styles.reservationActionPayment} disabled={saving} onClick={onGoToPayments}>Lunasi tagihan</button>}</>;
        return null;
    };

    return (
        <Panel
            className={checkOnly ? styles.checkInPanel : styles.reservationsPanel}
            title={checkOnly ? 'Check-in / check-out' : isUserExperience ? 'Reservasi saya' : 'Reservations'}
            action={!checkOnly && canCreateReservation ? <button type="button" className={styles.reservationNewButton} onClick={() => { setError(''); setShowForm((current) => !current); }}>{showForm ? 'Tutup form' : '+ Buat reservasi'}</button> : undefined}
        >
            {checkOnly && selectedDate && <div className={styles.operationDateBanner}><span>Tanggal operasional</span><strong>{dateLabel(selectedDate)}</strong></div>}

            {!checkOnly && <>
                {isUserExperience && <div className={styles.reservationStats}><div><span>Total booking</span><strong>{counts.all}</strong></div><div><span>Akan datang</span><strong>{counts.upcoming}</strong></div><div><span>Perlu dibayar</span><strong>{counts.payment}</strong></div><div><span>Selesai</span><strong>{counts.completed}</strong></div></div>}

                <div className={styles.reservationToolbar}>
                    <div className={styles.reservationSearch}><span>⌕</span><input aria-label="Cari reservasi" placeholder="Cari nomor booking, kamar..." value={search} onChange={(event) => setSearch(event.target.value)} /></div>
                    {isUserExperience && <div className={styles.reservationFilters}>{([['all', 'Semua', counts.all], ['upcoming', 'Akan datang', counts.upcoming], ['payment', 'Belum lunas', counts.payment], ['completed', 'Selesai', counts.completed]] as const).map(([value, label, count]) => <button key={value} type="button" className={statusFilter === value ? styles.reservationFilterActive : ''} onClick={() => setStatusFilter(value)}>{label}<b>{count}</b></button>)}</div>}
                    <span className={styles.resultCount}>{filtered.length} booking</span>
                </div>
            </>}

            {error && <ErrorNotice message={error} />}

            {showForm && canCreateReservation && <form className={styles.reservationBookingForm} onSubmit={create}>
                <div className={styles.bookingFormHeader}><h3>Siapkan perjalanan Anda</h3></div>
                <div className={styles.bookingFields}>
                    {canChooseUser && <>
                        <label>Jenis tamu<select value={form.guest_mode === 'walk_in' ? 'walk-in' : form.user_id} onChange={(event) => { if (event.target.value === 'walk-in') { setForm((current) => ({ ...current, guest_mode: 'walk_in', user_id: '' })); } else { setForm((current) => ({ ...current, guest_mode: 'registered', user_id: event.target.value, guest_name: '', guest_phone: '' })); } }}><option value="">Pilih user terdaftar</option><option value="walk-in">Tamu walk-in</option>{users.map((user) => <option key={user.id} value={user.id}>{user.name} · {user.email}</option>)}</select></label>
                        {form.guest_mode === 'walk_in' && <>
                            <label>Nama tamu<input required value={form.guest_name} onChange={(event) => setField('guest_name', event.target.value)} placeholder="Nama lengkap tamu" /></label>
                            <label>No. telepon<input value={form.guest_phone} onChange={(event) => setField('guest_phone', event.target.value)} placeholder="Opsional" /></label>
                        </>}
                    </>}
                    <label>Check-in<input required min={currentDate} type="date" value={form.check_in_date} onChange={(event) => setField('check_in_date', event.target.value)} /></label>
                    <label>Check-out<input required min={form.check_in_date || currentDate} type="date" value={form.check_out_date} onChange={(event) => setField('check_out_date', event.target.value)} /></label>
                    <label>Jumlah tamu<input required min="1" type="number" value={form.number_of_guests} onChange={(event) => setField('number_of_guests', event.target.value)} /></label>
                </div>

                <div className={styles.roomPickerHeader}><div><span>PILIH KAMAR</span><strong>{availabilityLoading ? 'Memeriksa ketersediaan...' : `${roomOptions.length} kamar tersedia`}</strong></div>{formNights > 0 && <small>{formNights} malam · {guestCount} tamu</small>}</div>
                <div className={styles.roomPicker}>
                    {!availabilityLoading && roomOptions.map((room) => <button type="button" key={room.id} className={form.room_id === String(room.id) ? styles.roomOptionSelected : styles.roomOption} onClick={() => setField('room_id', String(room.id))}><span className={styles.roomOptionIcon}>▦</span><span><b>Kamar {room.room_number}</b><small>{room.room_type?.name ?? 'Room'} · kapasitas {room.room_type?.capacity ?? '-'} tamu</small></span><strong>{money(room.room_type?.base_price ?? 0, currency)}<small>/malam</small></strong></button>)}
                    {!availabilityLoading && !roomOptions.length && <div className={styles.roomPickerEmpty}>Belum ada kamar yang tersedia untuk tanggal dan jumlah tamu tersebut. Coba ubah tanggal.</div>}
                </div>

                {isUserExperience && services.length > 0 && <section className={styles.reservationServicesBox}>
                    <div className={styles.reservationServicesHeader}><strong>Services tambahan</strong><label>Jumlah orang<input min="1" type="number" value={serviceQuantity} onChange={(event) => setServiceQuantity(event.target.value)} /></label></div>
                    <div className={styles.reservationServicesGrid}>{services.map((service) => <label className={selectedServiceIds.includes(service.id) ? styles.reservationServiceSelected : styles.reservationServiceOption} key={service.id}><input type="checkbox" checked={selectedServiceIds.includes(service.id)} onChange={() => toggleService(service.id)} /><span>{service.name}</span><b>{money(service.price, currency)}</b></label>)}</div>
                </section>}

                <div className={styles.bookingBottom}>
                    <label className={styles.bookingNotes}>Catatan tambahan<textarea rows={2} placeholder="Contoh: request lantai tinggi atau late arrival" value={form.notes} onChange={(event) => setField('notes', event.target.value)} /></label>
                    <div className={styles.bookingSummary}><span>Estimasi total</span><strong>{money(estimatedTotal, currency)}</strong><small>{chosenRoom ? `${money(roomCost, currency)} kamar${serviceCost ? ` + ${money(serviceCost, currency)} services` : ''} · service ${Number(settings.service_charge_rate)}% · pajak ${Number(settings.tax_rate)}%` : 'Pilih kamar untuk melihat estimasi'}</small></div>
                    {canUseMemberDiscount && <div className={styles.memberDiscountOption}><span><strong>Member discount otomatis {Number(memberDiscountRate)}%</strong><small>{guestCount < memberMinimumGuests ? `Diskon aktif otomatis mulai ${memberMinimumGuests} tamu.` : 'Diskon sudah termasuk dalam estimasi dan akan dihitung ulang oleh server.'}</small></span></div>}
                    <button className={styles.reservationSubmit} type="submit" disabled={saving || availabilityLoading || !form.room_id || formNights <= 0}>{saving ? 'Menyimpan...' : 'Konfirmasi reservasi →'}</button>
                </div>
            </form>}

            <div className={styles.reservationList}>
                {filtered.map((item) => {
                    const paid = paidAmount(item);
                    const total = Number(item.grand_total);
                    const progress = total > 0 ? Math.min(100, Math.round((paid / total) * 100)) : 0;
                    return <article className={styles.reservationCard} key={item.id}>
                        <div className={styles.reservationCardTop}><div><span className={styles.bookingEyebrow}>{item.booking_number}</span><h3>{item.room?.room_type?.name ?? 'Reservasi kamar'}</h3><p>{item.user?.name ?? item.guest?.full_name ?? 'Tamu'} · Kamar {item.room?.room_number ?? '-'}</p></div><Status value={item.status} label={statusLabel[item.status] ?? item.status} /></div>
                        <div className={styles.reservationCardDetails}><div><span>CHECK-IN</span><strong>{dateLabel(item.check_in_date)}</strong></div><div className={styles.stayArrow}>→</div><div><span>CHECK-OUT</span><strong>{dateLabel(item.check_out_date)}</strong></div><div><span>DURASI</span><strong>{item.number_of_nights} malam</strong></div><div><span>TOTAL</span><strong>{money(item.grand_total, currency)}</strong></div></div>
                        <div className={styles.reservationCardFooter}><div className={styles.paymentProgress}><div><span>Pembayaran</span><b>{isPaid(item) ? 'Lunas' : `${money(paid, currency)} / ${money(total, currency)}`}</b></div><div className={styles.paymentTrack}><i style={{ width: `${progress}%` }} /></div></div><div className={styles.reservationActions}>{renderAction(item)}</div></div>
                    </article>;
                })}
            </div>

            {!filtered.length && <Empty text={checkOnly ? 'Tidak ada reservasi aktif pada tanggal yang dipilih.' : search || statusFilter !== 'all' ? 'Tidak ada booking yang cocok dengan filter.' : 'Belum ada reservasi. Buat booking pertama Anda untuk mulai.'} />}
            {pagination && onPageChange && <Pagination currentPage={pagination.current_page} lastPage={pagination.last_page} total={pagination.total} onPageChange={onPageChange} />}
        </Panel>
    );
}
