<?php

use App\Http\Controllers\Web\AdminSettingsController;
use App\Http\Controllers\Web\ArchiveController;
use App\Http\Controllers\Web\AuthController;
use App\Http\Controllers\Web\ClinicSetupController;
use App\Http\Controllers\Web\DailyDeskController;
use App\Http\Controllers\Web\FinanceController;
use App\Http\Controllers\Web\ScheduleController;
use Illuminate\Support\Facades\Route;
// مسارات المدير فقط
use App\Http\Controllers\Web\AuditLogController;

// التوجيه الجذري
Route::get('/', fn() => redirect()->route('login'));

// مسارات المصادقة
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->name('login.attempt');
});

Route::post('/logout', [AuthController::class, 'logout'])
    ->middleware('auth')
    ->name('logout');

// مسارات السكرتير والمدير
Route::middleware(['auth', 'role:admin,secretary'])->group(function () {
    // 1. اليومية (Daily Desk)
    Route::get('/daily-desk', [DailyDeskController::class, 'index'])->name('desk.index');
    Route::post('/daily-desk/quick-book', [DailyDeskController::class, 'quickStore'])->name('desk.quick-book');
    Route::patch('/daily-desk/appointments/{appointment}/status', [DailyDeskController::class, 'updateStatus'])->name('desk.status');
    Route::patch('/daily-desk/appointments/{appointment}/payment', [DailyDeskController::class, 'updatePayment'])->name('desk.payment');
    Route::patch('/daily-desk/patients/{patient}/ban', [DailyDeskController::class, 'toggleBan'])->name('desk.ban');

    // 2. الأرشيف (Archive)
    Route::get('/archive', [ArchiveController::class, 'index'])->name('archive.index');
    Route::get('/archive/export', [ArchiveController::class, 'export'])->name('archive.export');

    // 3. التخصصات والموظفين (Clinic Setup)
    Route::get('/clinic-setup', [ClinicSetupController::class, 'index'])->name('setup.index');
    Route::post('/clinic-setup/specialties', [ClinicSetupController::class, 'storeSpecialty'])->name('setup.specialties.store');
    Route::patch('/clinic-setup/specialties/{specialty}/toggle', [ClinicSetupController::class, 'toggleSpecialtyBooking'])->name('setup.specialties.toggle');
    Route::post('/clinic-setup/providers', [ClinicSetupController::class, 'storeProvider'])->name('setup.providers.store');
    Route::patch('/clinic-setup/providers/{provider}/recurring', [ClinicSetupController::class, 'toggleProviderRecurring'])->name('setup.providers.recurring');
    Route::patch('/clinic-setup/providers/{provider}/active', [ClinicSetupController::class, 'toggleProviderActive'])->name('setup.providers.active');

    // 4. جداول مواعيد الأطباء (Schedules)
Route::get('/schedules', [ScheduleController::class, 'index'])->name('schedules.index');
Route::post('/schedules/{provider}/weekly', [ScheduleController::class, 'storeWeekly'])->name('schedules.weekly.store');
Route::post('/schedules/{provider}/exception', [ScheduleController::class, 'storeException'])->name('schedules.exception.store');
Route::delete('/schedules/{schedule}', [ScheduleController::class, 'destroy'])->name('schedules.destroy');
Route::post('/schedules/{provider}/generate', [ScheduleController::class, 'generateSlots'])->name('schedules.generate');
Route::patch('/schedules/slots/{slot}', [ScheduleController::class, 'updateSlot'])->name('schedules.slots.update');
});

// مسارات المحاسب والمدير فقط
Route::middleware(['auth', 'role:admin,accountant'])->group(function () {
    Route::get('/finance', [FinanceController::class, 'index'])->name('finance.index');
    Route::get('/finance/export', [FinanceController::class, 'export'])->name('finance.export');
});

// مسارات المدير فقط
Route::middleware(['auth', 'role:admin'])->group(function () {
    Route::get('/admin/settings', [AdminSettingsController::class, 'index'])->name('admin.index');
    Route::put('/admin/settings', [AdminSettingsController::class, 'update'])->name('admin.settings.update');
    Route::post('/admin/staff', [AdminSettingsController::class, 'storeStaff'])->name('admin.staff.store');
    Route::patch('/admin/staff/{user}/regenerate-password', [AdminSettingsController::class, 'regeneratePassword'])->name('admin.staff.regenerate');
    Route::get('/admin/audit-logs', [AuditLogController::class, 'index'])->name('admin.audit-logs');
});