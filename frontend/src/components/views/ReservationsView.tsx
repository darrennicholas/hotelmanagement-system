'use client';

import {
    CSSProperties,
    FormEvent,
    useState,
} from 'react';

import styles from '@/app/page.module.css';

import { api } from '@/services/api';

import {
    confirmAction,
    successAlert,
} from '@/services/alerts';

import {
    dateLabel,
    money,
    today,
} from '@/services/formatters';

import type {
    Reservation,
    Room,
    User,
} from '@/types';

import {
    Empty,
    ErrorNotice,
    Panel,
    Status,
} from '@/components/ui';


type Props = {
    reservations: Reservation[];
    users: User[];
    canChooseUser: boolean;
    canCreateReservation: boolean;
    rooms: Room[];

    selectedDate?: string;

    onSaved: () => void;

    onGoToPayments?: () => void;

    checkOnly?: boolean;
};


export default function ReservationsView({
    reservations,
    users,
    canChooseUser,
    canCreateReservation,
    rooms,
    selectedDate,
    onSaved,
    onGoToPayments,
    checkOnly = false,
}: Props) {

    const [showForm, setShowForm] =
        useState(false);

    const [error, setError] =
        useState('');

    const [search, setSearch] =
        useState('');

    const [saving, setSaving] =
        useState(false);

    const [form, setForm] =
        useState({
            user_id: '',
            room_id: '',
            check_in_date: today(),
            check_out_date: '',
            number_of_guests: '1',
            notes: '',
            tax_rate: '0',
            discount: '0',
        });


    /*
    |--------------------------------------------------------------------------
    | DATE HELPERS
    |--------------------------------------------------------------------------
    */

    const currentDate = today();


    /*
     * Backend Laravel kadang mengirim:
     *
     * 2026-10-03
     *
     * atau:
     *
     * 2026-10-03T00:00:00.000000Z
     *
     * Maka kita ambil YYYY-MM-DD saja.
     */
    function dateOnly(
        value?: string | null
    ) {
        if (!value) return '';

        return String(value).slice(
            0,
            10
        );
    }


    function isCheckInToday(
        item: Reservation
    ) {
        return (
            dateOnly(
                item.check_in_date
            ) === currentDate
        );
    }


    function isCheckOutToday(
        item: Reservation
    ) {
        return (
            dateOnly(
                item.check_out_date
            ) === currentDate
        );
    }


    /*
     * Kalau user membuka date picker
     * ke tanggal lain, dia hanya sedang
     * melihat jadwal.
     *
     * Tombol action tidak boleh aktif
     * untuk memanipulasi tanggal.
     */
    const isViewingActualToday =
        !checkOnly ||
        !selectedDate ||
        selectedDate === currentDate;


    /*
    |--------------------------------------------------------------------------
    | PAYMENT CHECK
    |--------------------------------------------------------------------------
    */

    function isPaid(
        item: Reservation
    ) {

        const paidAmount =
            item.payments
                ?.filter(
                    (payment) =>
                        payment
                            .payment_status ===
                        'Paid'
                )
                .reduce(
                    (
                        total,
                        payment
                    ) =>
                        total +
                        Number(
                            payment.amount
                        ),
                    0
                ) ?? 0;

        return (
            paidAmount >=
            Number(
                item.grand_total
            )
        );
    }


    /*
    |--------------------------------------------------------------------------
    | FILTER
    |--------------------------------------------------------------------------
    */

    const filtered =
        reservations
            .filter(
                (item) => {

                    const keyword =
                        `${item.booking_number} ` +
                        `${item.user?.name ?? ''} ` +
                        `${item.room?.room_number ?? ''}`;

                    return keyword
                        .toLowerCase()
                        .includes(
                            search.toLowerCase()
                        );
                }
            )
            .filter(
                (item) =>
                    !checkOnly ||
                    [
                        'Confirmed',
                        'Checked In',
                    ].includes(
                        item.status
                    )
            );


    /*
    |--------------------------------------------------------------------------
    | CREATE RESERVATION
    |--------------------------------------------------------------------------
    */

    async function create(
        event: FormEvent
    ) {

        event.preventDefault();

        if (!canCreateReservation) {
            return;
        }


        const confirmed =
            await confirmAction(
                'Simpan reservasi?',
                'Reservasi baru akan ditambahkan ke sistem.'
            );


        if (!confirmed) {
            return;
        }


        setError('');

        setSaving(true);


        try {

            await api(
                '/reservations',
                {
                    method: 'POST',

                    body: JSON.stringify({
                        ...form,

                        user_id:
                            canChooseUser
                                ? Number(form.user_id)
                                : undefined,

                        room_id:
                            Number(
                                form.room_id
                            ),

                        number_of_guests:
                            Number(
                                form
                                    .number_of_guests
                            ),

                        tax_rate:
                            Number(
                                form.tax_rate
                            ),

                        discount:
                            Number(
                                form.discount
                            ),
                    }),
                }
            );


            setShowForm(false);


            setForm({
                user_id: '',
                room_id: '',
                check_in_date:
                    today(),
                check_out_date: '',
                number_of_guests:
                    '1',
                notes: '',
                tax_rate: '0',
                discount: '0',
            });


            await successAlert(
                'Reservasi berhasil dibuat',
                'Lanjutkan ke menu Payments untuk menyelesaikan pembayaran.'
            );


            onSaved();

            onGoToPayments?.();

        } catch (err) {

            setError(
                err instanceof Error
                    ? err.message
                    : 'Reservasi gagal disimpan.'
            );

        } finally {

            setSaving(false);
        }
    }


    /*
    |--------------------------------------------------------------------------
    | RESERVATION ACTION
    |--------------------------------------------------------------------------
    */

    async function action(
        id: number,
        type:
            | 'confirm'
            | 'check-in'
            | 'check-out'
            | 'cancel'
    ) {

        const labels = {

            confirm:
                'konfirmasi reservasi',

            'check-in':
                'check-in tamu',

            'check-out':
                'check-out tamu',

            cancel:
                'pembatalan reservasi',
        };


        const confirmed =
            await confirmAction(

                `Lanjutkan ${labels[type]}?`,

                type === 'cancel'
                    ? 'Reservasi akan dibatalkan dan tidak bisa dipulihkan dari dashboard.'
                    : 'Status reservasi akan diperbarui.'
            );


        if (!confirmed) {
            return;
        }


        setError('');

        setSaving(true);


        try {

            await api(
                `/reservations/${id}/${type}`,
                {
                    method: 'POST',

                    body:
                        type === 'cancel'
                            ? JSON.stringify({
                                  reason:
                                      'Dibatalkan dari dashboard',
                              })
                            : undefined,
                }
            );


            let message =
                'Status reservasi berhasil diperbarui.';


            if (type === 'confirm') {

                message =
                    'Reservasi berhasil dikonfirmasi.';
            }


            if (type === 'check-in') {

                message =
                    'Tamu berhasil melakukan check-in.';
            }


            if (type === 'check-out') {

                message =
                    'Tamu berhasil melakukan check-out dan kamar kembali tersedia.';
            }


            if (type === 'cancel') {

                message =
                    'Reservasi berhasil dibatalkan.';
            }


            await successAlert(
                'Aksi berhasil',
                message
            );


            onSaved();

        } catch (err) {

            setError(
                err instanceof Error
                    ? err.message
                    : 'Aksi gagal.'
            );

        } finally {

            setSaving(false);
        }
    }


    /*
    |--------------------------------------------------------------------------
    | INLINE UI STYLE
    |--------------------------------------------------------------------------
    |
    | Tidak perlu mengubah page.module.css.
    |
    */

    const tableGridStyle: CSSProperties = {

        gridTemplateColumns:
            '1.15fr 1fr 0.65fr 1.15fr 1.35fr 0.9fr 0.8fr 1.8fr',

        minWidth: '1210px',

        columnGap: '14px',
    };


    const actionContainerStyle:
        CSSProperties = {

        display: 'flex',

        alignItems: 'center',

        gap: '8px',

        flexWrap: 'wrap',
    };


    const baseButtonStyle:
        CSSProperties = {

        minWidth: '105px',

        minHeight: '38px',

        padding:
            '0 14px',

        display: 'inline-flex',

        alignItems: 'center',

        justifyContent: 'center',

        border:
            '1px solid transparent',

        borderRadius: '8px',

        fontSize: '10px',

        fontWeight: 700,

        cursor: 'pointer',

        whiteSpace: 'nowrap',
    };


    const checkInStyle:
        CSSProperties = {

        ...baseButtonStyle,

        background:
            '#edf2ff',

        borderColor:
            '#d7e0ff',

        color:
            '#465dcc',
    };


    const checkOutStyle:
        CSSProperties = {

        ...baseButtonStyle,

        background:
            '#eaf0ff',

        borderColor:
            '#d4deff',

        color:
            '#4058c4',
    };


    const paymentStyle:
        CSSProperties = {

        ...baseButtonStyle,

        minWidth: '125px',

        background:
            '#f2efff',

        borderColor:
            '#e1dcff',

        color:
            '#6b5bc5',
    };


    const confirmStyle:
        CSSProperties = {

        ...baseButtonStyle,

        background:
            '#edf1ff',

        borderColor:
            '#dce3ff',

        color:
            '#465ace',
    };


    const cancelStyle:
        CSSProperties = {

        ...baseButtonStyle,

        minWidth: '78px',

        background:
            '#fff1f1',

        borderColor:
            '#ffdddd',

        color:
            '#c85d5d',
    };


    const disabledDateStyle:
        CSSProperties = {

        ...baseButtonStyle,

        minWidth: '145px',

        background:
            '#f5f6f9',

        borderColor:
            '#e6e8ed',

        color:
            '#929aaa',

        cursor:
            'not-allowed',

        opacity: 1,
    };


    /*
    |--------------------------------------------------------------------------
    | UI
    |--------------------------------------------------------------------------
    */

    return (
        <>
            <Panel

                className={
                    checkOnly
                        ? styles.checkInPanel
                        : styles.reservationsPanel
                }

                title={
                    checkOnly
                        ? 'Check-in / check-out'
                        : 'Reservations'
                }

                subtitle={
                    checkOnly
                        ? 'Proses kedatangan dan keberangkatan tamu'
                        : 'Semua reservasi tersimpan di tabel reservations'
                }

                action={
                    !checkOnly && canCreateReservation ? (

                        <button

                            type="button"

                            className={
                                styles.secondaryButton
                            }

                            onClick={() =>
                                setShowForm(
                                    !showForm
                                )
                            }
                        >

                            {showForm
                                ? 'Tutup form'
                                : '+ New reservation'}

                        </button>

                    ) : !checkOnly ? <span className={styles.readOnlyBadge}>View only</span> : undefined
                }
            >

                {/* =====================================================
                    SELECTED OPERATION DATE
                ===================================================== */}

                {checkOnly &&
                    selectedDate && (

                    <div
                        style={{
                            display:
                                'flex',

                            alignItems:
                                'center',

                            flexWrap:
                                'wrap',

                            gap:
                                '8px',

                            padding:
                                '10px 12px',

                            marginBottom:
                                '15px',

                            background:
                                '#f8f9fc',

                            border:
                                '1px solid #e7eaf1',

                            borderRadius:
                                '8px',

                            fontSize:
                                '10px',

                            color:
                                '#7d8798',
                        }}
                    >

                        <span>
                            Tanggal operasional:
                        </span>

                        <strong
                            style={{
                                color:
                                    '#38445a',
                            }}
                        >
                            {dateLabel(
                                selectedDate
                            )}
                        </strong>


                        {selectedDate !==
                            currentDate && (

                            <span
                                style={{
                                    color:
                                        '#a0a8b5',
                                }}
                            >
                                — mode melihat jadwal
                            </span>
                        )}

                    </div>
                )}


                {/* =====================================================
                    SEARCH
                ===================================================== */}

                <div
                    className={
                        styles.toolbar
                    }
                >

                    <input

                        placeholder="Cari booking, tamu, kamar..."

                        value={
                            search
                        }

                        onChange={(
                            event
                        ) =>
                            setSearch(
                                event
                                    .target
                                    .value
                            )
                        }
                    />


                    <span>
                        {filtered.length}{' '}
                        data tampil
                    </span>

                </div>


                {error && (

                    <ErrorNotice
                        message={
                            error
                        }
                    />
                )}


                {/* =====================================================
                    CREATE FORM
                ===================================================== */}

                {showForm && canCreateReservation && (

                    <form
                        className={
                            styles.inlineForm
                        }

                        onSubmit={
                            create
                        }
                    >

                        {canChooseUser && <select

                            required

                            value={
                                form.user_id
                            }

                            onChange={(
                                event
                            ) =>
                                setForm({
                                    ...form,

                                    user_id:
                                        event
                                            .target
                                            .value,
                                })
                            }
                        >

                            <option value="">
                                Pilih user
                            </option>


                            {users.map(
                                (user) => (

                                    <option

                                        key={
                                            user.id
                                        }

                                        value={
                                            user.id
                                        }
                                    >

                                        {
                                            user.name
                                        }

                                    </option>
                                )
                            )}

                        </select>}


                        <select

                            required

                            value={
                                form.room_id
                            }

                            onChange={(
                                event
                            ) =>
                                setForm({
                                    ...form,

                                    room_id:
                                        event
                                            .target
                                            .value,
                                })
                            }
                        >

                            <option value="">
                                Pilih kamar
                            </option>


                            {rooms
                                .filter(
                                    (room) =>
                                        room.status ===
                                            'Available' &&
                                        (room.available_rooms ??
                                            1) >
                                            0
                                )
                                .map(
                                    (room) => (

                                        <option

                                            key={
                                                room.id
                                            }

                                            value={
                                                room.id
                                            }
                                        >

                                            #
                                            {
                                                room.room_number
                                            }
                                            {' - '}
                                            {
                                                room
                                                    .room_type
                                                    ?.name
                                            }

                                        </option>
                                    )
                                )}

                        </select>


                        <input

                            required

                            type="date"

                            value={
                                form.check_in_date
                            }

                            onChange={(
                                event
                            ) =>
                                setForm({
                                    ...form,

                                    check_in_date:
                                        event
                                            .target
                                            .value,
                                })
                            }
                        />


                        <input

                            required

                            type="date"

                            min={
                                form.check_in_date ||
                                undefined
                            }

                            value={
                                form.check_out_date
                            }

                            onChange={(
                                event
                            ) =>
                                setForm({
                                    ...form,

                                    check_out_date:
                                        event
                                            .target
                                            .value,
                                })
                            }
                        />


                        <input

                            required

                            min="1"

                            type="number"

                            placeholder="Jumlah tamu"

                            value={
                                form.number_of_guests
                            }

                            onChange={(
                                event
                            ) =>
                                setForm({
                                    ...form,

                                    number_of_guests:
                                        event
                                            .target
                                            .value,
                                })
                            }
                        />


                        <button

                            className={
                                styles.primaryButton
                            }

                            type="submit"

                            disabled={
                                saving
                            }
                        >

                            {saving
                                ? 'Menyimpan...'
                                : 'Simpan'}

                        </button>

                    </form>
                )}


                {/* =====================================================
                    TABLE
                ===================================================== */}

                <div
                    className={
                        styles.table
                    }
                >

                    <div

                        className={
                            styles.tableHead
                        }

                        style={
                            tableGridStyle
                        }
                    >

                        <span>
                            BOOKING
                        </span>

                        <span>
                            USER
                        </span>

                        <span>
                            ROOM
                        </span>

                        <span>
                            FACILITIES
                        </span>

                        <span>
                            DATES
                        </span>

                        <span>
                            TOTAL
                        </span>

                        <span>
                            STATUS
                        </span>

                        <span>
                            ACTION
                        </span>

                    </div>


                    {filtered.map(
                        (item) => {

                            const paid =
                                isPaid(
                                    item
                                );


                            const canCheckIn =

                                item.status ===
                                    'Confirmed' &&

                                paid &&

                                isCheckInToday(
                                    item
                                ) &&

                                isViewingActualToday;


                            const canCheckOut =

                                item.status ===
                                    'Checked In' &&

                                paid &&

                                isCheckOutToday(
                                    item
                                ) &&

                                isViewingActualToday;


                            return (

                                <div

                                    className={
                                        styles.tableRow
                                    }

                                    style={
                                        tableGridStyle
                                    }

                                    key={
                                        item.id
                                    }
                                >

                                    <strong>
                                        {
                                            item.booking_number
                                        }
                                    </strong>


                                    <span>
                                        {item
                                            .user
                                            ?.name ??
                                            '-'}
                                    </span>


                                    <span>
                                        #
                                        {item
                                            .room
                                            ?.room_number ??
                                            '-'}
                                    </span>

                                    <span className={styles.reservationFacilities} title={item.room?.room_type?.facilities?.map((facility) => facility.name).join(', ')}>
                                        {item.room?.room_type?.facilities?.length
                                            ? item.room.room_type.facilities.map((facility) => facility.name).join(', ')
                                            : '-'}
                                    </span>


                                    <span
                                        style={{
                                            whiteSpace:
                                                'nowrap',
                                        }}
                                    >

                                        {dateLabel(
                                            item.check_in_date
                                        )}

                                        {' → '}

                                        {dateLabel(
                                            item.check_out_date
                                        )}

                                    </span>


                                    <span>

                                        {money(
                                            item.grand_total
                                        )}

                                    </span>


                                    <span>

                                        <Status
                                            value={
                                                item.status
                                            }
                                        />

                                    </span>


                                    {/* ===================================
                                        ACTION
                                    =================================== */}

                                    <div
                                        style={
                                            actionContainerStyle
                                        }
                                    >

                                        {/* PENDING */}

                                        {item.status ===
                                            'Pending' && (

                                            <button

                                                type="button"

                                                disabled={
                                                    saving
                                                }

                                                style={
                                                    confirmStyle
                                                }

                                                onClick={() =>
                                                    action(
                                                        item.id,
                                                        'confirm'
                                                    )
                                                }
                                            >
                                                Confirm
                                            </button>
                                        )}


                                        {/* ==============================
                                            CONFIRMED + BELUM BAYAR
                                        ============================== */}

                                        {item.status ===
                                            'Confirmed' &&
                                            !paid && (

                                            <button

                                                type="button"

                                                disabled={
                                                    saving
                                                }

                                                style={
                                                    paymentStyle
                                                }

                                                onClick={() =>
                                                    onGoToPayments?.()
                                                }
                                            >
                                                Bayar di Payments
                                            </button>
                                        )}


                                        {/* ==============================
                                            CHECK-IN ACTIVE
                                        ============================== */}

                                        {item.status ===
                                            'Confirmed' &&
                                            paid &&
                                            canCheckIn && (

                                            <button

                                                type="button"

                                                disabled={
                                                    saving
                                                }

                                                style={
                                                    checkInStyle
                                                }

                                                onClick={() =>
                                                    action(
                                                        item.id,
                                                        'check-in'
                                                    )
                                                }
                                            >
                                                Check-in
                                            </button>
                                        )}


                                        {/* ==============================
                                            CHECK-IN BELUM TANGGALNYA
                                        ============================== */}

                                        {item.status ===
                                            'Confirmed' &&
                                            paid &&
                                            !canCheckIn && (

                                            <button

                                                type="button"

                                                disabled

                                                style={
                                                    disabledDateStyle
                                                }
                                            >
                                                Check-in{' '}
                                                {dateLabel(
                                                    item.check_in_date
                                                )}
                                            </button>
                                        )}


                                        {/* ==============================
                                            CHECKED IN + BELUM BAYAR
                                        ============================== */}

                                        {item.status ===
                                            'Checked In' &&
                                            !paid && (

                                            <button

                                                type="button"

                                                disabled={
                                                    saving
                                                }

                                                style={
                                                    paymentStyle
                                                }

                                                onClick={() =>
                                                    onGoToPayments?.()
                                                }
                                            >
                                                Bayar di Payments
                                            </button>
                                        )}


                                        {/* ==============================
                                            CHECK-OUT ACTIVE
                                        ============================== */}

                                        {item.status ===
                                            'Checked In' &&
                                            paid &&
                                            canCheckOut && (

                                            <button

                                                type="button"

                                                disabled={
                                                    saving
                                                }

                                                style={
                                                    checkOutStyle
                                                }

                                                onClick={() =>
                                                    action(
                                                        item.id,
                                                        'check-out'
                                                    )
                                                }
                                            >
                                                Check-out
                                            </button>
                                        )}


                                        {/* ==============================
                                            CHECK-OUT BELUM TANGGALNYA
                                        ============================== */}

                                        {item.status ===
                                            'Checked In' &&
                                            paid &&
                                            !canCheckOut && (

                                            <button

                                                type="button"

                                                disabled

                                                style={
                                                    disabledDateStyle
                                                }
                                            >
                                                Check-out{' '}
                                                {dateLabel(
                                                    item.check_out_date
                                                )}
                                            </button>
                                        )}


                                        {/* ==============================
                                            CANCEL
                                        ============================== */}

                                        {![
                                            'Cancelled',
                                            'Checked Out',
                                            'Completed',
                                        ].includes(
                                            item.status
                                        ) && (

                                            <button

                                                type="button"

                                                disabled={
                                                    saving
                                                }

                                                style={
                                                    cancelStyle
                                                }

                                                onClick={() =>
                                                    action(
                                                        item.id,
                                                        'cancel'
                                                    )
                                                }
                                            >
                                                Cancel
                                            </button>
                                        )}

                                    </div>

                                </div>
                            );
                        }
                    )}

                </div>


                {!filtered.length && (

                    <Empty
                        text={
                            checkOnly
                                ? 'Tidak ada reservasi aktif pada tanggal yang dipilih.'
                                : 'Belum ada data reservasi sesuai filter.'
                        }
                    />
                )}

            </Panel>
        </>
    );
}
