'use client';

/* eslint-disable react-hooks/preserve-manual-memoization, react-hooks/set-state-in-effect */

import {
    useCallback,
    useEffect,
    useState,
} from 'react';

import styles from './page.module.css';

import { api } from '@/services/api';

import {
    confirmAction,
    successAlert,
} from '@/services/alerts';

import {
    today,
} from '@/services/formatters';

import {
    emptyDashboard,
    menu,
} from '@/config/dashboard';

import type {
    Dashboard,
    Facility,
    HotelService,
    OccupancyReport,
    Paginated,
    Payment,
    PendingServicePayment,
    ReportReservation,
    Reservation,
    RevenueRow,
    Role,
    Room,
    RoomType,
    User,
} from '@/types';

import Login from '@/components/Login';

import Overview from '@/components/views/Overview';

import ReservationsView from '@/components/views/ReservationsView';

import RoomsView from '@/components/views/RoomsView';

import HotelSurroundingsView from '@/components/views/HotelSurroundingsView';

import PaymentsView from '@/components/views/PaymentsView';

import ReportsView from '@/components/views/ReportsView';

import ServicesView from '@/components/views/ServicesView';

import FacilitiesView from '@/components/views/FacilitiesView';

import UsersView from '@/components/views/UsersView';

import {
    Empty,
    ErrorNotice,
    Panel,
} from '@/components/ui';


const pageMeta:
    Record<
        string,
        {
            eyebrow: string;
            title: string;
            description: string;
        }
    > = {

    Overview: {
        eyebrow:
            'TODAY AT A GLANCE',

        title:
            'Ringkasan operasional',

        description:
            'Pantau kamar, reservasi, dan performa hotel dalam satu layar.',
    },

    Reservations: {
        eyebrow:
            'FRONT DESK',

        title:
            'Kelola reservasi',

        description:
            'Atur booking tamu dari dibuat sampai selesai dengan cepat.',
    },

    Rooms: {
        eyebrow:
            'INVENTORY',

        title:
            'Ketersediaan kamar',

        description:
            'Pastikan status kamar dan tarif selalu siap untuk dijual.',
    },

    'Hotel Surroundings': {
        eyebrow:
            'HOTEL EXPERIENCE',

        title:
            'Hotel Surroundings',

        description:
            'Jelajahi pilihan kamar Haven yang nyaman dan siap menemani perjalanan Anda.',
    },

    'Check-in / out': {
        eyebrow:
            'ARRIVALS & DEPARTURES',

        title:
            'Check-in dan check-out',

        description:
            'Buat proses kedatangan dan keberangkatan terasa lebih lancar.',
    },

    Payments: {
        eyebrow:
            'FINANCE',

        title:
            'Pembayaran',

        description:
            'Catat pemasukan dan pastikan setiap transaksi terhubung ke booking.',
    },

    Reports: {
        eyebrow:
            'INSIGHTS',

        title:
            'Laporan hotel',

        description:
            'Baca tren okupansi dan pendapatan untuk keputusan yang lebih baik.',
    },

    Services: {
        eyebrow:
            'GUEST EXPERIENCE',

        title:
            'Layanan tambahan',

        description:
            'Kelola fasilitas ekstra yang membuat pengalaman tamu lebih lengkap.',
    },

    Facilities: {
        eyebrow:
            'ROOM AMENITIES',

        title:
            'Fasilitas kamar',

        description:
            'Lihat fasilitas yang ikut didapatkan sesuai tipe kamar yang dipesan.',
    },

    Users: {
        eyebrow:
            'TEAM ACCESS',

        title:
            'Pengguna dan akses',

        description:
            'Atur akun tim dan peran yang dapat mengakses sistem.',
    },
};


export default function Home() {

    const [user, setUser] =
        useState<User | null>(
            null
        );

    const [active, setActive] =
        useState(
            'Overview'
        );

    /*
     * Tanggal operasional disimpan terpisah per halaman.
     * Mengubah tanggal di Overview tidak boleh mengubah tanggal
     * yang sedang dipakai halaman Check-in / out.
     */

    const [
        overviewDate,
        setOverviewDate,
    ] =
        useState(
            today
        );

    const [
        checkInOutDate,
        setCheckInOutDate,
    ] =
        useState(
            today
        );

    const selectedDate =
        active === 'Overview'
            ? overviewDate
            : checkInOutDate;

    const [
        profileOpen,
        setProfileOpen,
    ] =
        useState(false);

    const [
        loading,
        setLoading,
    ] =
        useState(true);

    const [
        busy,
        setBusy,
    ] =
        useState(false);

    const [
        error,
        setError,
    ] =
        useState('');

    const [
        dashboard,
        setDashboard,
    ] =
        useState<Dashboard>(
            emptyDashboard
        );

    const [
        rooms,
        setRooms,
    ] =
        useState<Room[]>(
            []
        );

    const [
        roomTypes,
        setRoomTypes,
    ] =
        useState<
            RoomType[]
        >([]);

    const [
        reservations,
        setReservations,
    ] =
        useState<
            Reservation[]
        >([]);

    const [
        payments,
        setPayments,
    ] =
        useState<
            Payment[]
        >([]);

    const [
        services,
        setServices,
    ] =
        useState<
            HotelService[]
        >([]);

    const [
        facilities,
        setFacilities,
    ] =
        useState<Facility[]>([]);

    const [
        pendingPayment,
        setPendingPayment,
    ] =
        useState<PendingServicePayment | null>(
            null
        );

    const [
        users,
        setUsers,
    ] =
        useState<
            User[]
        >([]);

    const [
        roles,
        setRoles,
    ] =
        useState<
            Role[]
        >([]);

    const [
        occupancy,
        setOccupancy,
    ] =
        useState<
            OccupancyReport
        >({
            total_rooms: 0,
            occupied_rooms: 0,
            available_rooms: 0,
            occupancy_rate: 0,
        });

    const [
        revenue,
        setRevenue,
    ] =
        useState<
            RevenueRow[]
        >([]);

    const [
        reportReservations,
        setReportReservations,
    ] =
        useState<
            ReportReservation[]
        >([]);


    /*
    |--------------------------------------------------------------------------
    | ROLE-BASED MENU ACCESS
    |--------------------------------------------------------------------------
    |
    | Role User tidak boleh melihat / membuka:
    |
    | - Overview
    | - Reports
    | - Users
    | - Services (User dapat memilih service)
    |
    |--------------------------------------------------------------------------
    */

    const isBasicUser =
        user?.role?.name === 'User';

    const userOnlyMenuItem = {
        label: 'Hotel Surroundings',
        icon: '✦',
        group: 'WORKSPACE',
    };

    const visibleMenu =
        isBasicUser
            ? menu
                .filter(
                    (item) =>
                        item.label !== 'Overview' &&
                        item.label !== 'Reports' &&
                        item.label !== 'Users'
                )
                .flatMap((item) => item.label === 'Rooms' ? [item, userOnlyMenuItem] : [item])
            : menu;


    /*
    |--------------------------------------------------------------------------
    | LOAD
    |--------------------------------------------------------------------------
    */

    const load =
        useCallback(
            async (
                view: string
            ) => {

                /*
                |--------------------------------------------------------------------------
                | ROLE GUARD
                |--------------------------------------------------------------------------
                */

                if (
                    user?.role?.name === 'User' &&
                    [
                        'Overview',
                        'Reports',
                        'Users',
                    ].includes(view)
                ) {
                    setBusy(false);

                    setError('');

                    return;
                }

                setBusy(true);

                setError('');

                try {

                    /*
                    |--------------------------------------------------------------------------
                    | OVERVIEW
                    |--------------------------------------------------------------------------
                    */

                    if (
                        view ===
                        'Overview'
                    ) {
                        const [
                            stats,
                            roomData,
                        ] =
                            await Promise.all(
                                [
                                    api<Dashboard>(
                                        `/dashboard?date=${encodeURIComponent(
                                            selectedDate
                                        )}`
                                    ),

                                    api<
                                        Paginated<Room>
                                    >(
                                        '/rooms?per_page=100'
                                    ),
                                ]
                            );

                        setDashboard(
                            stats
                        );

                        setRooms(
                            roomData.data ??
                            []
                        );
                    }


                    /*
                    |--------------------------------------------------------------------------
                    | ROOMS
                    |--------------------------------------------------------------------------
                    */

                    if (
                        view ===
                        'Rooms'
                    ) {
                        const [
                            roomData,
                            typeData,
                        ] =
                            await Promise.all(
                                [
                                    api<
                                        Paginated<Room>
                                    >(
                                        '/rooms?per_page=100'
                                    ),

                                    api<
                                        Paginated<RoomType>
                                    >(
                                        '/room-types?per_page=100'
                                    ),
                                ]
                            );

                        setRooms(
                            roomData.data ??
                            []
                        );

                        setRoomTypes(
                            typeData.data ??
                            []
                        );
                    }

                    if (
                        view ===
                        'Hotel Surroundings'
                    ) {
                        const roomData = await api<Paginated<Room>>('/rooms?per_page=100');

                        setRooms(
                            roomData.data ??
                            []
                        );
                    }


                    /*
                    |--------------------------------------------------------------------------
                    | RESERVATIONS / CHECK-IN OUT / PAYMENT / SERVICES
                    |--------------------------------------------------------------------------
                    */

                    if (
                        view === 'Reservations' ||
                        view === 'Check-in / out' ||
                        view === 'Payments' ||
                        view === 'Services'
                    ) {
                        let reservationUrl =
                            '/reservations?per_page=100';


                        /*
                         * Khusus Check-in / out,
                         * tanggal dikirim ke backend.
                         */

                        if (
                            view ===
                            'Check-in / out'
                        ) {
                            reservationUrl +=
                                `&date=${encodeURIComponent(
                                    selectedDate
                                )}`;
                        }


                        const [
                            reservationData,
                            userData,
                            roomData,
                        ] =
                            await Promise.all(
                                [
                                    api<
                                        Paginated<Reservation>
                                    >(
                                        reservationUrl
                                    ),

                                    api<
                                        Paginated<User>
                                    >(
                                        '/users?role=User&per_page=100'
                                    ),

                                    api<
                                        Paginated<Room>
                                    >(
                                        '/rooms?per_page=100'
                                    ),
                                ]
                            );

                        setReservations(
                            reservationData.data ??
                            []
                        );

                        setUsers(
                            userData.data ??
                            []
                        );

                        setRooms(
                            roomData.data ??
                            []
                        );


                        if (
                            view ===
                            'Payments'
                        ) {
                            const paymentData =
                                await api<
                                    Paginated<Payment>
                                >(
                                    '/payments?per_page=100'
                                );

                            setPayments(
                                paymentData.data ??
                                []
                            );
                        }
                    }


                    /*
                    |--------------------------------------------------------------------------
                    | USERS
                    |--------------------------------------------------------------------------
                    */

                    if (
                        view ===
                        'Users'
                    ) {
                        const data =
                            await api<
                                Paginated<User>
                            >(
                                '/users?role=User&per_page=100'
                            );

                        setUsers(
                            data.data ??
                            []
                        );
                    }


                    /*
                    |--------------------------------------------------------------------------
                    | REPORTS
                    |--------------------------------------------------------------------------
                    */

                    if (
                        view ===
                        'Reports'
                    ) {
                        const [
                            occupancyData,
                            revenueData,
                            reportData,
                        ] =
                            await Promise.all(
                                [
                                    api<
                                        OccupancyReport
                                    >(
                                        '/reports/occupancy'
                                    ),

                                    api<
                                        RevenueRow[]
                                    >(
                                        '/reports/revenue'
                                    ),

                                    api<
                                        ReportReservation[]
                                    >(
                                        '/reports/reservations'
                                    ),
                                ]
                            );

                        setOccupancy(
                            occupancyData
                        );

                        setRevenue(
                            revenueData
                        );

                        setReportReservations(
                            reportData
                        );
                    }


                    /*
                    |--------------------------------------------------------------------------
                    | SERVICES
                    |--------------------------------------------------------------------------
                    */

                    if (
                        view ===
                        'Services'
                    ) {
                        setServices(
                            await api<
                                HotelService[]
                            >(
                                `/services${
                                    user
                                        ?.role
                                        ?.name ===
                                    'Admin'
                                        ? '?all=1'
                                        : ''
                                }`
                            )
                        );
                    }


                    /*
                    |--------------------------------------------------------------------------
                    | FACILITIES
                    |--------------------------------------------------------------------------
                    */

                    if (
                        view ===
                        'Facilities'
                    ) {
                        const [
                            facilityData,
                            typeData,
                        ] =
                            await Promise.all([
                                api<
                                    Facility[]
                                >(
                                    '/facilities'
                                ),

                                api<
                                    Paginated<RoomType>
                                >(
                                    '/room-types?per_page=100'
                                ),
                            ]);

                        setFacilities(
                            facilityData
                        );

                        setRoomTypes(
                            typeData.data ??
                            []
                        );
                    }


                    /*
                    |--------------------------------------------------------------------------
                    | ADMIN USERS
                    |--------------------------------------------------------------------------
                    */

                    if (
                        view ===
                            'Users' &&
                        user
                            ?.role
                            ?.name ===
                            'Admin'
                    ) {
                        const [
                            userData,
                            roleData,
                        ] =
                            await Promise.all(
                                [
                                    api<
                                        Paginated<User>
                                    >(
                                        '/users?per_page=100'
                                    ),

                                    api<
                                        Role[]
                                    >(
                                        '/roles'
                                    ),
                                ]
                            );

                        setUsers(
                            userData.data ??
                            []
                        );

                        setRoles(
                            roleData
                        );
                    }

                } catch (
                    err
                ) {
                    setError(
                        err instanceof Error
                            ? err.message
                            : 'Data gagal dimuat.'
                    );
                } finally {
                    setBusy(
                        false
                    );
                }
            },
            [
                selectedDate,
                user
                    ?.role
                    ?.name,
            ]
        );


    /*
    |--------------------------------------------------------------------------
    | RESTORE LOGIN
    |--------------------------------------------------------------------------
    */

    useEffect(
        () => {
            const token =
                localStorage.getItem(
                    'hotel_token'
                );

            if (
                !token
            ) {
                setLoading(
                    false
                );

                return;
            }

            api<User>(
                '/user'
            )
                .then(
                    (me) => {
                        setUser(
                            me
                        );

                        if (
                            me.role?.name ===
                            'User'
                        ) {
                            setActive(
                                'Reservations'
                            );
                        }
                    }
                )
                .catch(
                    () =>
                        localStorage.removeItem(
                            'hotel_token'
                        )
                )
                .finally(
                    () =>
                        setLoading(
                            false
                        )
                );
        },
        []
    );


    /*
    |--------------------------------------------------------------------------
    | ROLE ACCESS GUARD
    |--------------------------------------------------------------------------
    |
    | Kalau User masih menunjuk ke halaman terlarang
    | karena state lama, otomatis kembali ke Reservations.
    |
    |--------------------------------------------------------------------------
    */

    useEffect(
        () => {
            if (
                user?.role?.name ===
                    'User' &&
                [
                    'Overview',
                    'Reports',
                    'Users',
                ].includes(
                    active
                )
            ) {
                setActive(
                    'Reservations'
                );
            }
        },
        [
            user?.role?.name,
            active,
        ]
    );


    /*
    |--------------------------------------------------------------------------
    | AUTO LOAD
    |--------------------------------------------------------------------------
    */

    useEffect(
        () => {
            if (
                user
            ) {
                void load(
                    active
                );
            }
        },
        [
            user,
            active,
            load,
        ]
    );


    /*
    |--------------------------------------------------------------------------
    | RELOAD
    |--------------------------------------------------------------------------
    */

    const reload =
        () => {
            void load(
                active
            );

            if (
                active !==
                    'Overview' &&
                !isBasicUser
            ) {
                void load(
                    'Overview'
                );
            }
        };


    /*
    |--------------------------------------------------------------------------
    | LOADING
    |--------------------------------------------------------------------------
    */

    if (
        loading
    ) {
        return (
            <div
                className={
                    styles.loading
                }
            >
                Memuat workspace...
            </div>
        );
    }


    /*
    |--------------------------------------------------------------------------
    | LOGIN
    |--------------------------------------------------------------------------
    */

    if (
        !user
    ) {
        return (
            <Login
                onLogin={(
                    me
                ) => {
                    setUser(
                        me
                    );

                    setActive(
                        me.role?.name ===
                            'User'
                            ? 'Reservations'
                            : 'Overview'
                    );
                }}
            />
        );
    }


    /*
    |--------------------------------------------------------------------------
    | LOGOUT
    |--------------------------------------------------------------------------
    */

    const logout =
        async () => {
            if (
                !await confirmAction(
                    'Keluar dari dashboard?',
                    'Sesi Anda akan diakhiri.'
                )
            ) {
                return;
            }

            try {
                await api(
                    '/logout',
                    {
                        method:
                            'POST',
                    }
                );
            } catch {
                /*
                 * Local logout tetap dilakukan.
                 */
            }

            localStorage.removeItem(
                'hotel_token'
            );

            await successAlert(
                'Berhasil logout',
                'Sampai jumpa kembali.'
            );

            setUser(
                null
            );
        };


    /*
    |--------------------------------------------------------------------------
    | TITLE
    |--------------------------------------------------------------------------
    */

    const currentMeta =
        pageMeta[
            active
        ] ??
        pageMeta
            .Overview;

    const pageTitle =
        active ===
        'Overview' ? (
            <>
                Selamat pagi,{' '}
                {
                    user.name.split(
                        ' '
                    )[0]
                }
            </>
        ) : (
            currentMeta.title
        );


    /*
    |--------------------------------------------------------------------------
    | UI
    |--------------------------------------------------------------------------
    */

    return (
        <main
            className={
                styles.appShell
            }
        >

            {/* =====================================================
                SIDEBAR
            ===================================================== */}

            <aside
                className={
                    styles.sidebar
                }
            >
                <div
                    className={
                        styles.logo
                    }
                >
                    <span>
                        H
                    </span>

                    <div>
                        <strong>
                            Haven
                        </strong>

                        <small>
                            HOTEL SYSTEM
                        </small>
                    </div>
                </div>


                <nav>
                    {visibleMenu.map(
                        (
                            item,
                            index
                        ) => (
                            <div
                                key={
                                    item.label
                                }
                            >
                                {(index ===
                                    0 ||
                                    item.group !==
                                        visibleMenu[
                                            index -
                                                1
                                        ]
                                            .group) && (

                                    <p
                                        className={
                                            styles.navLabel
                                        }
                                    >
                                        {
                                            item.group
                                        }
                                    </p>
                                )}


                                <button
                                    type="button"

                                    aria-current={
                                        active ===
                                        item.label
                                            ? 'page'
                                            : undefined
                                    }

                                    className={
                                        `${active === item.label ? styles.activeNav : ''} ${item.label === 'Hotel Surroundings' ? styles.userMenuHighlight : ''}`
                                    }

                                    onClick={() =>
                                        setActive(
                                            item.label
                                        )
                                    }
                                >
                                    <span>
                                        {
                                            item.icon
                                        }
                                    </span>

                                    {
                                        item.label
                                    }


                                    {item.label ===
                                        'Reservations' &&
                                        dashboard
                                            .reservations
                                            .today >
                                            0 && (

                                        <b>
                                            {
                                                dashboard
                                                    .reservations
                                                    .today
                                            }
                                        </b>
                                    )}
                                </button>
                            </div>
                        )
                    )}
                </nav>
            </aside>


            {/* =====================================================
                CONTENT
            ===================================================== */}

            <section
                className={
                    styles.content
                }

                aria-busy={
                    busy
                }
            >

                {/* =================================================
                    HEADER
                ================================================= */}

                <header
                    className={
                        styles.header
                    }
                >
                    <div>
                        <div
                            className={
                                styles.headerTopline
                            }
                        >
                            <p
                                className={
                                    styles.breadcrumb
                                }
                            >
                                WORKSPACE /{' '}

                                {
                                    active.toUpperCase()
                                }
                            </p>
                        </div>


                        <h1>
                            {
                                pageTitle
                            }
                        </h1>


                        {/* ==========================================
                            OPERATIONAL DATE
                        ========================================== */}

                        {(
                            active ===
                                'Overview' ||
                            active ===
                                'Check-in / out'
                        ) && (

                            <label
                                className={
                                    styles.dateSelector
                                }
                            >
                                <span>
                                    {new Intl
                                        .DateTimeFormat(
                                            'id-ID',
                                            {
                                                dateStyle:
                                                    'full',
                                            }
                                        )
                                        .format(
                                            new Date(
                                                `${selectedDate}T00:00:00`
                                            )
                                        )}
                                </span>


                                <input
                                    type="date"

                                    value={
                                        selectedDate
                                    }

                                    onChange={(
                                        event
                                    ) =>
                                        active ===
                                            'Overview'
                                            ? setOverviewDate(
                                                event.target.value
                                            )
                                            : setCheckInOutDate(
                                                event.target.value
                                            )
                                    }

                                    aria-label="Pilih tanggal operasional hotel"
                                />
                            </label>
                        )}
                    </div>


                    {/* ==============================================
                        PROFILE
                    ============================================== */}

                    <div
                        className={
                            styles.headerActions
                        }
                    >
                        <span
                            className={
                                styles.headerRole
                            }
                        >
                            {user
                                .role
                                ?.name ??
                                'Staff'}
                        </span>


                        <div
                            className={
                                styles.profileMenuWrap
                            }
                        >
                            <button
                                type="button"

                                className={
                                    `${styles.headerAvatar} ${
                                        profileOpen
                                            ? styles.headerAvatarActive
                                            : ''
                                    }`
                                }

                                onClick={() =>
                                    setProfileOpen(
                                        (
                                            open
                                        ) =>
                                            !open
                                    )
                                }

                                aria-label="Buka menu profil"

                                aria-expanded={
                                    profileOpen
                                }
                            >
                                {user.name.charAt(
                                    0
                                )}
                            </button>


                            {profileOpen && (

                                <div
                                    className={
                                        styles.profileMenu
                                    }
                                >
                                    <div
                                        className={
                                            styles.profileMenuHeader
                                        }
                                    >
                                        <div
                                            className={
                                                styles.profileMenuAvatar
                                            }
                                        >
                                            {user.name.charAt(
                                                0
                                            )}
                                        </div>


                                        <div>
                                            <strong>
                                                {
                                                    user.name
                                                }
                                            </strong>

                                            <span>
                                                {user
                                                    .role
                                                    ?.name ??
                                                    'Staff'}
                                            </span>
                                        </div>
                                    </div>


                                    <div
                                        className={
                                            styles.profileMenuDivider
                                        }
                                    />


                                    <button
                                        type="button"

                                        className={
                                            styles.logoutButton
                                        }

                                        onClick={() => {
                                            setProfileOpen(
                                                false
                                            );

                                            void logout();
                                        }}
                                    >
                                        <span>
                                            ↪
                                        </span>

                                        Keluar dari dashboard
                                    </button>
                                </div>
                            )}
                        </div>
                    </div>
                </header>


                {/* =================================================
                    MOBILE NAV
                ================================================= */}

                <div
                    className={
                        styles.mobileNav
                    }

                    aria-label="Navigasi menu"
                >
                    {visibleMenu.map(
                        (
                            item
                        ) => (

                            <button
                                type="button"

                                key={
                                    item.label
                                }

                                aria-current={
                                    active ===
                                    item.label
                                        ? 'page'
                                        : undefined
                                }

                                className={
                                    `${active === item.label ? styles.mobileNavActive : ''} ${item.label === 'Hotel Surroundings' ? styles.userMenuHighlight : ''}`
                                }

                                onClick={() =>
                                    setActive(
                                        item.label
                                    )
                                }
                            >
                                <span>
                                    {
                                        item.icon
                                    }
                                </span>

                                {
                                    item.label
                                }
                            </button>
                        )
                    )}
                </div>


                {error && (

                    <ErrorNotice
                        message={
                            error
                        }
                    />
                )}


                {/* =================================================
                    OVERVIEW
                ================================================= */}

                {active ===
                    'Overview' && (

                    <Overview
                        dashboard={
                            dashboard
                        }

                        rooms={
                            rooms
                        }

                        onRooms={() =>
                            setActive(
                                'Rooms'
                            )
                        }
                    />
                )}


                {/* =================================================
                    RESERVATIONS
                ================================================= */}

                {active ===
                    'Reservations' && (

                    <ReservationsView
                        reservations={
                            reservations
                        }

                        users={
                            users
                        }

                        canChooseUser={
                            !isBasicUser
                        }

                        canCreateReservation={
                            isBasicUser
                        }

                        rooms={
                            rooms
                        }

                        selectedDate={
                            selectedDate
                        }

                        onSaved={
                            reload
                        }

                        onGoToPayments={() =>
                            setActive(
                                'Payments'
                            )
                        }
                    />
                )}


                {/* =================================================
                    ROOMS
                ================================================= */}

                {active ===
                    'Rooms' && (

                    <RoomsView
                        canManage={user.role?.name === 'Admin'}
                        rooms={
                            rooms
                        }

                        roomTypes={
                            roomTypes
                        }

                        onSaved={
                            reload
                        }
                    />
                )}

                {active ===
                    'Hotel Surroundings' && (

                    <HotelSurroundingsView
                        rooms={
                            rooms
                        }
                    />
                )}


                {/* =================================================
                    CHECK-IN / CHECK-OUT
                ================================================= */}

                {active ===
                    'Check-in / out' && (

                    <ReservationsView
                        reservations={
                            reservations
                        }

                        users={
                            users
                        }

                        canChooseUser={
                            !isBasicUser
                        }

                        canCreateReservation={
                            isBasicUser
                        }

                        rooms={
                            rooms
                        }

                        selectedDate={
                            selectedDate
                        }

                        onSaved={
                            reload
                        }

                        onGoToPayments={() =>
                            setActive(
                                'Payments'
                            )
                        }

                        checkOnly
                    />
                )}


                {/* =================================================
                    PAYMENTS
                ================================================= */}

                {active ===
                    'Payments' && (

                    <PaymentsView
                        canPay={
                            isBasicUser
                        }

                        payments={
                            payments
                        }

                        reservations={
                            reservations
                        }

                        pendingService={
                            pendingPayment
                        }

                        onSaved={
                            reload
                        }

                        onGoToCheckIn={() =>
                            setActive(
                                'Check-in / out'
                            )
                        }

                        onPaymentComplete={() =>
                            setPendingPayment(
                                null
                            )
                        }
                    />
                )}


                {/* =================================================
                    REPORTS
                ================================================= */}

                {active ===
                    'Reports' &&
                    !isBasicUser && (

                    <ReportsView
                        occupancy={
                            occupancy
                        }

                        revenue={
                            revenue
                        }

                        reservations={
                            reportReservations
                        }
                    />
                )}


                {/* =================================================
                    SERVICES
                ================================================= */}

                {active ===
                    'Services' && (

                    <ServicesView
                        services={
                            services
                        }

                        canManage={
                            user.role?.name ===
                            'Admin'
                        }

                        reservations={
                            reservations
                        }

                        onSaved={
                            reload
                        }

                        onGoToPayments={(
                            request
                        ) => {
                            setPendingPayment(
                                request
                            );

                            setActive(
                                'Payments'
                            );
                        }}
                    />
                )}


                {/* =================================================
                    FACILITIES
                ================================================= */}

                {active ===
                    'Facilities' && (

                    <FacilitiesView
                        facilities={
                            facilities
                        }

                        roomTypes={
                            roomTypes
                        }

                        canManage={
                            user.role?.name ===
                            'Admin'
                        }

                        onSaved={
                            reload
                        }
                    />
                )}


                {/* =================================================
                    USERS
                ================================================= */}

                {active ===
                    'Users' && (

                    user
                        .role
                        ?.name ===
                    'Admin' ? (

                        <UsersView
                            users={
                                users
                            }

                            roles={
                                roles
                            }

                            onSaved={
                                reload
                            }
                        />

                    ) : (

                        <Panel
                            title="Users"

                            subtitle="Akses dibatasi"
                        >
                            <Empty
                                text="Menu Users hanya dapat diakses oleh Admin."
                            />
                        </Panel>
                    )
                )}
            </section>
        </main>
    );
}
