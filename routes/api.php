<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ExcelAdminController;
use App\Http\Controllers\Api\ExcelAppointmentController;
use App\Http\Controllers\Api\ExcelFinanceController;
use App\Http\Controllers\Api\WhatsAppWebhookController;
use Illuminate\Support\Facades\Route;

// Meta WhatsApp Cloud API Webhook
Route::get('/whatsapp/webhook', [WhatsAppWebhookController::class, 'verify'])->name('whatsapp.webhook.verify');
Route::post('/whatsapp/webhook', [WhatsAppWebhookController::class, 'handle'])->name('whatsapp.webhook.handle');

// Sanctum API Authentication
Route::post('/login', [AuthController::class, 'login'])->name('api.login');
Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum')->name('api.logout');

Route::middleware(['auth:sanctum'])->prefix('excel')->group(function () {

    // 1. مواعيد اليومية والأرشيف (للسكرتارية والمدير)
    Route::middleware(['role:admin,secretary'])->group(function () {
        Route::get('/appointments/daily', [ExcelAppointmentController::class, 'daily']);
        Route::get('/appointments/archive', [ExcelAppointmentController::class, 'archive']);
        Route::patch('/appointments/{id}/status', [ExcelAppointmentController::class, 'updateStatus']);
        Route::post('/appointments/{id}/payment', [ExcelAppointmentController::class, 'addPayment']);
        Route::post('/patients/{id}/toggle-ban', [ExcelAppointmentController::class, 'toggleBan']);
    });

    // 2. نقطة المالية المُعمّاة (للمحاسب والمدير فقط)
    Route::middleware(['role:admin,accountant'])->group(function () {
        Route::get('/finance', [ExcelFinanceController::class, 'index']);
    });

    // 3. الإدارة، الإحصائيات، وجداول الأطباء (للمدير فقط)
    Route::middleware(['role:admin'])->group(function () {
        Route::get('/dashboard/stats', [ExcelAdminController::class, 'dashboardStats']);

        // التخصصات
        Route::get('/specialties', [ExcelAdminController::class, 'getSpecialties']);
        Route::post('/specialties', [ExcelAdminController::class, 'storeSpecialty']);
        Route::put('/specialties/{id}', [ExcelAdminController::class, 'updateSpecialty']);

        // الموظفون / الأطباء (مع خيار التكرار الأسبوعي is_recurring)
        Route::get('/providers', [ExcelAdminController::class, 'getProviders']);
        Route::post('/providers', [ExcelAdminController::class, 'storeProvider']);
        Route::put('/providers/{id}', [ExcelAdminController::class, 'updateProvider']);

        // جداول الأوقات
        Route::get('/schedules', [ExcelAdminController::class, 'getSchedules']);
        Route::post('/schedules', [ExcelAdminController::class, 'storeSchedule']);
        Route::delete('/schedules/{id}', [ExcelAdminController::class, 'deleteSchedule']);
    });
});