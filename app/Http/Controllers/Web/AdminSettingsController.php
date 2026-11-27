<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\Setting;
use App\Models\User;
use App\Services\AuditLoggerService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AdminSettingsController extends Controller
{
    public function index()
    {
        // 1. إحصائيات عامة للعيادة
        $totalVisits = Appointment::count();
        $attendedCount = Appointment::where('status', 'attended')->count();
        $noShowCount = Appointment::where('status', 'no_show')->count();
        $cancelledCount = Appointment::where('status', 'cancelled')->count();

        $completedBase = $attendedCount + $noShowCount;
        $attendanceRate = $completedBase > 0 ? round(($attendedCount / $completedBase) * 100, 1) : 0;
        $noShowRate = $completedBase > 0 ? round(($noShowCount / $completedBase) * 100, 1) : 0;

        $totalRevenue = Appointment::where('status', '!=', 'cancelled')->sum('payment_amount');

        // 2. قراءة الإعدادات الحالية
        $settings = [
            'clinic_name'                   => Setting::getValue('clinic_name', 'عيادات الشفاء التخصصية'),
            'clinic_phone'                  => Setting::getValue('clinic_phone', ''),
            'global_booking_enabled'        => Setting::getValue('global_booking_enabled', '1'),
            'auto_ban_no_show_limit'        => Setting::getValue('auto_ban_no_show_limit', '3'),
            'auto_ban_days'                 => Setting::getValue('auto_ban_days', '30'),
            'cancellation_cooldown_minutes' => Setting::getValue('cancellation_cooldown_minutes', '10'),
        ];

        // 3. قائمة حسابات موظفي النظام (مدير، سكرتير، محاسب)
        $staffUsers = User::orderBy('role')->orderBy('name')->get();

        return view('admin.index', compact(
            'totalVisits',
            'attendedCount',
            'noShowCount',
            'cancelledCount',
            'attendanceRate',
            'noShowRate',
            'totalRevenue',
            'settings',
            'staffUsers'
        ));
    }

    /**
     * تحديث إعدادات العيادة وزر الإيقاف العام (Kill Switch)
     */
    public function update(Request $request)
    {
        $data = $request->validate([
            'clinic_name'                   => ['required', 'string', 'max:150'],
            'clinic_phone'                  => ['nullable', 'string', 'max:50'],
            'global_booking_enabled'        => ['nullable', 'boolean'],
            'auto_ban_no_show_limit'        => ['required', 'integer', 'min:1', 'max:20'],
            'auto_ban_days'                 => ['required', 'integer', 'min:1', 'max:365'],
            'cancellation_cooldown_minutes' => ['required', 'integer', 'min:0', 'max:120'],
        ]);

        $newSettings = [
            'clinic_name'                   => $data['clinic_name'],
            'clinic_phone'                  => $data['clinic_phone'] ?? '',
            'global_booking_enabled'        => $request->boolean('global_booking_enabled') ? '1' : '0',
            'auto_ban_no_show_limit'        => (string) $data['auto_ban_no_show_limit'],
            'auto_ban_days'                 => (string) $data['auto_ban_days'],
            'cancellation_cooldown_minutes' => (string) $data['cancellation_cooldown_minutes'],
        ];

        $lastSettingModel = null;
        foreach ($newSettings as $key => $value) {
            $lastSettingModel = Setting::updateOrCreate(
                ['key' => $key],
                ['value' => $value]
            );
        }

        if ($lastSettingModel) {
            AuditLoggerService::log(
                'clinic_settings_updated',
                $lastSettingModel,
                null,
                $newSettings,
                auth()->id()
            );
        }

        return back()->with('success', 'تم حفظ إعدادات النظام وتحديث قواعد الحجز بنجاح.');
    }

    /**
     * إضافة موظف جديد (سكرتير / مالية / مدير) وتوليد كلمة مرور له تلقائياً
     */
    public function storeStaff(Request $request)
    {
        $data = $request->validate([
            'name'  => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:150', 'unique:users,email'],
            'role'  => ['required', 'in:secretary,accountant,admin'],
        ], [
            'email.unique' => 'هذا البريد الإلكتروني مستخدم لموظف آخر.',
        ]);

        // توليد كلمة مرور سهلة القراءة مكونة من 8 خانات
        $plainPassword = Str::upper(Str::random(4)) . random_int(1000, 9999);

        $user = User::create([
            'name'     => $data['name'],
            'email'    => $data['email'],
            'role'     => $data['role'],
            'password' => Hash::make($plainPassword),
        ]);

        AuditLoggerService::log(
            'staff_account_created',
            $user,
            null,
            ['name' => $user->name, 'email' => $user->email, 'role' => $user->role],
            auth()->id()
        );

        return back()->with('generated_credentials', [
            'title'    => 'تم إنشاء حساب الموظف بنجاح',
            'name'     => $user->name,
            'email'    => $user->email,
            'role'     => $user->role,
            'password' => $plainPassword,
        ]);
    }

    /**
     * إعادة توليد كلمة مرور جديدة لموظف حالي
     */
    public function regeneratePassword(User $user)
    {
        $plainPassword = Str::upper(Str::random(4)) . random_int(1000, 9999);

        $user->update([
            'password' => Hash::make($plainPassword),
        ]);

        AuditLoggerService::log(
            'staff_password_regenerated',
            $user,
            null,
            ['target_user_id' => $user->id, 'target_email' => $user->email],
            auth()->id()
        );

        return back()->with('generated_credentials', [
            'title'    => 'تم توليد كلمة مرور جديدة',
            'name'     => $user->name,
            'email'    => $user->email,
            'role'     => $user->role,
            'password' => $plainPassword,
        ]);
    }
}