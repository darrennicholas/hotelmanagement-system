<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FacilityController;
use App\Http\Controllers\HotelServiceController;
use App\Http\Controllers\HotelSettingController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\ReservationController;
use App\Http\Controllers\RoomController;
use App\Http\Controllers\RoomTypeController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| PUBLIC ROUTES
|--------------------------------------------------------------------------
*/

Route::post(
    '/login',
    [AuthController::class, 'login']
)->middleware('throttle:login');

Route::post(
    '/register',
    [AuthController::class, 'register']
)->middleware('throttle:register');

/*
|--------------------------------------------------------------------------
| AUTHENTICATED ROUTES
|--------------------------------------------------------------------------
*/

Route::middleware(['token.cookie', 'auth:sanctum'])->group(function () {

    /*
    |--------------------------------------------------------------------------
    | SEMUA ROLE YANG SUDAH LOGIN
    |--------------------------------------------------------------------------
    */

    Route::post(
        '/logout',
        [AuthController::class, 'logout']
    );

    Route::get(
        '/user',
        [AuthController::class, 'user']
    );

    /*
    |--------------------------------------------------------------------------
    | DASHBOARD
    |--------------------------------------------------------------------------
    |
    | Hanya Admin dan Receptionist.
    |
    */

    Route::middleware(
        'role:Admin,Receptionist,Manager'
    )->get(
        '/dashboard',
        DashboardController::class
    );

    /*
    |--------------------------------------------------------------------------
    | ROOMS
    |--------------------------------------------------------------------------
    */

    Route::get(
        '/rooms/availability',
        [RoomController::class, 'availability']
    );

    Route::apiResource(
        'rooms',
        RoomController::class
    )->only([
        'index',
        'show',
    ]);

    Route::apiResource(
        'room-types',
        RoomTypeController::class
    )->only([
        'index',
        'show',
    ]);

    /*
    |--------------------------------------------------------------------------
    | USERS LIST
    |--------------------------------------------------------------------------
    |
    | Tetap dipertahankan karena Reservations / Check-in dapat membutuhkan
    | daftar user.
    |
    */

    Route::get(
        '/users',
        [UserController::class, 'index']
    );

    /*
    |--------------------------------------------------------------------------
    | RESERVATIONS
    |--------------------------------------------------------------------------
    |
    | Semua role yang login tetap dapat menggunakan Reservations.
    |
    */

    Route::get(
        '/reservations',
        [ReservationController::class, 'index']
    );

    Route::post(
        '/reservations',
        [ReservationController::class, 'store']
    )->middleware('role:Admin,Receptionist,Manager,User');

    Route::get(
        '/reservations/{reservation}',
        [ReservationController::class, 'show']
    );

    Route::put(
        '/reservations/{reservation}',
        [ReservationController::class, 'update']
    );

    Route::post(
        '/reservations/{reservation}/confirm',
        [ReservationController::class, 'confirm']
    )->middleware('role:Admin,Receptionist,Manager');

    Route::post(
        '/reservations/{reservation}/check-in',
        [ReservationController::class, 'checkIn']
    )->middleware('role:Admin,Receptionist,Manager');

    Route::post(
        '/reservations/{reservation}/check-out',
        [ReservationController::class, 'checkOut']
    )->middleware('role:Admin,Receptionist,Manager');

    Route::post(
        '/reservations/{reservation}/no-show',
        [ReservationController::class, 'noShow']
    )->middleware('role:Admin,Receptionist,Manager');

    Route::post(
        '/reservations/{reservation}/cancel',
        [ReservationController::class, 'cancel']
    );

    Route::post(
        '/reservations/{reservation}/services',
        [ReservationController::class, 'addService']
    )->middleware('role:Admin,Receptionist,Manager,User');

    /*
    |--------------------------------------------------------------------------
    | FACILITIES
    |--------------------------------------------------------------------------
    */

    Route::get(
        '/facilities',
        [FacilityController::class, 'index']
    );

    /*
    |--------------------------------------------------------------------------
    | PAYMENTS
    |--------------------------------------------------------------------------
    */

    Route::get(
        '/payments',
        [PaymentController::class, 'index']
    );

    Route::get(
        '/reservations/{reservation}/folio',
        [PaymentController::class, 'folio']
    );

    Route::post(
        '/reservations/{reservation}/payments',
        [PaymentController::class, 'store']
    )->middleware('role:Admin,Receptionist,Manager,User');

    Route::post(
        '/payments/{payment}/refund',
        [PaymentController::class, 'refund']
    )->middleware('role:Admin,Manager');

    Route::post(
        '/payments/{payment}/void',
        [PaymentController::class, 'void']
    )->middleware('role:Admin');

    Route::get(
        '/settings',
        [HotelSettingController::class, 'show']
    );

    /*
    |--------------------------------------------------------------------------
    | ADMIN + RECEPTIONIST
    |--------------------------------------------------------------------------
    |
    | Role User TIDAK boleh mengakses:
    |
    | - Dashboard
    | - Reports
    | Services tersedia untuk semua role login; hanya Admin yang dapat mengelola katalog.
    |
    |--------------------------------------------------------------------------
    */

    Route::middleware(
        'role:Admin,Receptionist,Manager,User'
    )->get(
        '/services',
        [HotelServiceController::class, 'index']
    );

    Route::middleware(
        'role:Admin,Receptionist,Manager'
    )->group(function () {

        Route::patch(
            '/rooms/{room}/status',
            [RoomController::class, 'updateStatus']
        );

        /*
        |--------------------------------------------------------------------------
        | REPORTS
        |--------------------------------------------------------------------------
        */

        Route::get(
            '/reports/reservations',
            [ReportController::class, 'reservations']
        );

        Route::get(
            '/reports/revenue',
            [ReportController::class, 'revenue']
        );

        Route::get(
            '/reports/occupancy',
            [ReportController::class, 'occupancy']
        );

        Route::get(
            '/reports/payments',
            [ReportController::class, 'payments']
        );

    });

    /*
    |--------------------------------------------------------------------------
    | ADMIN ONLY
    |--------------------------------------------------------------------------
    */

    Route::middleware(
        'role:Admin'
    )->group(function () {

        /*
        |--------------------------------------------------------------------------
        | ROOMS
        |--------------------------------------------------------------------------
        */

        Route::apiResource(
            'rooms',
            RoomController::class
        )->only([
            'store',
            'update',
            'destroy',
        ]);

        /*
        |--------------------------------------------------------------------------
        | ROOM TYPES
        |--------------------------------------------------------------------------
        */

        Route::apiResource(
            'room-types',
            RoomTypeController::class
        )->only([
            'store',
            'update',
            'destroy',
        ]);

        /*
        |--------------------------------------------------------------------------
        | FACILITIES
        |--------------------------------------------------------------------------
        */

        Route::apiResource(
            'facilities',
            FacilityController::class
        )->only([
            'store',
            'update',
            'destroy',
        ]);

        /*
        |--------------------------------------------------------------------------
        | USERS
        |--------------------------------------------------------------------------
        */

        Route::apiResource(
            'users',
            UserController::class
        )->only([
            'store',
            'update',
        ]);

        Route::get(
            '/roles',
            [UserController::class, 'roles']
        );

        /*
        |--------------------------------------------------------------------------
        | SERVICES
        |--------------------------------------------------------------------------
        */

        Route::apiResource(
            'services',
            HotelServiceController::class
        )->only([
            'store',
            'update',
        ]);

        Route::patch(
            '/settings',
            [HotelSettingController::class, 'update']
        );
    });
});
