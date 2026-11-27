<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Provider;
use App\Models\Specialty;
use App\Services\AuditLoggerService;
use Illuminate\Http\Request;

class ClinicSetupController extends Controller
{
    public function index()
    {
        $specialties = Specialty::withCount('providers')
            ->orderBy('name')
            ->get();

        $providers = Provider::with('specialty')
            ->orderBy('name')
            ->get();

        return view('setup.index', compact('specialties', 'providers'));
    }

    /**
     * إضافة تخصص جديد
     */
    public function storeSpecialty(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150', 'unique:specialties,name'],
        ], [
            'name.unique' => 'هذا التخصص مسجل مسبقاً.',
        ]);

        $specialty = Specialty::create([
            'name'               => $data['name'],
            'is_booking_enabled' => true,
        ]);

        AuditLoggerService::log(
            'specialty_created',
            $specialty,
            null,
            $specialty->toArray(),
            auth()->id()
        );

        return back()->with('success', 'تم إضافة التخصص بنجاح.');
    }

    /**
     * تشغيل أو إيقاف الحجز لتخصص معين
     */
    public function toggleSpecialtyBooking(Specialty $specialty)
    {
        $oldValues = $specialty->toArray();

        $specialty->update([
            'is_booking_enabled' => !$specialty->is_booking_enabled,
        ]);

        AuditLoggerService::log(
            'specialty_booking_toggled',
            $specialty,
            $oldValues,
            $specialty->fresh()->toArray(),
            auth()->id()
        );

        $statusText = $specialty->is_booking_enabled ? 'تفعيل' : 'إيقاف';
        return back()->with('success', "تم {$statusText} الحجز لتخصص ({$specialty->name}).");
    }

    /**
     * إضافة موظف (طبيب / أشعة / مخبر)
     */
    public function storeProvider(Request $request)
    {
        $data = $request->validate([
            'name'         => ['required', 'string', 'max:150'],
            'type'         => ['required', 'in:doctor,radiology,lab'],
            'specialty_id' => ['required', 'exists:specialties,id'],
            'is_recurring' => ['nullable', 'boolean'],
        ]);

        $provider = Provider::create([
            'name'         => $data['name'],
            'type'         => $data['type'],
            'specialty_id' => $data['specialty_id'],
            'is_recurring' => $request->boolean('is_recurring'),
            'is_active'    => true,
        ]);

        AuditLoggerService::log(
            'provider_created',
            $provider,
            null,
            $provider->toArray(),
            auth()->id()
        );

        return back()->with('success', 'تم إضافة الموظف وربطه بالتخصص بنجاح.');
    }

    /**
     * تبديل حالة التكرار الأسبوعي للموظف (is_recurring)
     */
    public function toggleProviderRecurring(Provider $provider)
    {
        $oldValues = $provider->toArray();

        $provider->update([
            'is_recurring' => !$provider->is_recurring,
        ]);

        AuditLoggerService::log(
            'provider_recurring_toggled',
            $provider,
            $oldValues,
            $provider->fresh()->toArray(),
            auth()->id()
        );

        return back()->with('success', "تم تحديث إعداد التكرار الأسبوعي لـ ({$provider->name}).");
    }

    /**
     * تفعيل أو تعطيل الموظف (is_active)
     */
    public function toggleProviderActive(Provider $provider)
    {
        $oldValues = $provider->toArray();

        $provider->update([
            'is_active' => !$provider->is_active,
        ]);

        AuditLoggerService::log(
            'provider_status_toggled',
            $provider,
            $oldValues,
            $provider->fresh()->toArray(),
            auth()->id()
        );

        $statusText = $provider->is_active ? 'تفعيل' : 'تعطيل';
        return back()->with('success', "تم {$statusText} حساب ({$provider->name}).");
    }
}