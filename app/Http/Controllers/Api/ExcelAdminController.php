<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\Patient;
use App\Models\Provider;
use App\Models\ProviderSchedule;
use App\Models\Specialty;
use Carbon\Carbon;
use Illuminate\Http\Request;

class ExcelAdminController extends Controller
{
    /**
     * إحصائيات لوحة المدير (Dashboard)
     */
    public function dashboardStats()
    {
        $today = Carbon::today();
        $monthStart = Carbon::now()->startOfMonth();

        return response()->json([
            'status' => 'success',
            'data'   => [
                [
                    'metric' => 'مواعيد اليوم',
                    'value'  => Appointment::whereDate('appointment_date', $today)->count(),
                ],
                [
                    'metric' => 'الحضور اليوم',
                    'value'  => Appointment::whereDate('appointment_date', $today)->where('status', 'attended')->count(),
                ],
                [
                    'metric' => 'عدم الحضور (No-Shows) هذا الشهر',
                    'value'  => Appointment::whereDate('appointment_date', '>=', $monthStart)->where('status', 'no_show')->count(),
                ],
                [
                    'metric' => 'إيرادات اليوم',
                    'value'  => (float) Appointment::whereDate('appointment_date', $today)->sum('payment_amount'),
                ],
                [
                    'metric' => 'إيرادات الشهر الحالي',
                    'value'  => (float) Appointment::whereDate('appointment_date', '>=', $monthStart)->sum('payment_amount'),
                ],
                [
                    'metric' => 'المرضى المحظورون',
                    'value'  => Patient::where('is_banned', true)->count(),
                ],
            ],
        ]);
    }

    /**
     * عرض التخصصات
     */
    public function getSpecialties()
    {
        return response()->json([
            'status' => 'success',
            'data'   => Specialty::orderBy('id')->get()->map(fn($s) => [
                'id'                 => $s->id,
                'name'               => $s->name,
                'is_booking_enabled' => (bool) $s->is_booking_enabled,
            ]),
        ]);
    }

    /**
     * إضافة تخصص جديد
     */
    public function storeSpecialty(Request $request)
    {
        $data = $request->validate([
            'name'               => 'required|string|max:100|unique:specialties,name',
            'is_booking_enabled' => 'boolean',
        ]);

        $specialty = Specialty::create([
            'name'               => $data['name'],
            'is_booking_enabled' => $data['is_booking_enabled'] ?? true,
        ]);

        return response()->json([
            'status' => 'success',
            'data'   => $specialty,
        ], 201);
    }

    /**
     * تعديل تخصص
     */
    public function updateSpecialty(Request $request, $id)
    {
        $specialty = Specialty::findOrFail($id);

        $data = $request->validate([
            'name'               => 'sometimes|string|max:100|unique:specialties,name,' . $id,
            'is_booking_enabled' => 'sometimes|boolean',
        ]);

        $specialty->update($data);

        return response()->json([
            'status' => 'success',
            'data'   => $specialty,
        ]);
    }

    /**
     * عرض الأطباء / الوحدات مع خيار التكرار الأسبوعي
     */
    public function getProviders()
    {
        $providers = Provider::with('specialty:id,name')
            ->orderBy('id')
            ->get()
            ->map(fn($p) => [
                'id'           => $p->id,
                'name'         => $p->name,
                'type'         => $p->type,
                'specialty_id' => $p->specialty_id,
                'specialty'    => $p->specialty->name ?? '-',
                'is_recurring' => (bool) $p->is_recurring,
                'is_active'    => (bool) $p->is_active,
            ]);

        return response()->json([
            'status' => 'success',
            'data'   => $providers,
        ]);
    }

    /**
     * إضافة طبيب / وحدة جديدة
     */
    public function storeProvider(Request $request)
    {
        $data = $request->validate([
            'name'         => 'required|string|max:120',
            'type'         => 'sometimes|in:doctor,radiology,lab',
            'specialty_id' => 'required|exists:specialties,id',
            'is_recurring' => 'boolean',
            'is_active'    => 'boolean',
        ]);

        $provider = Provider::create([
            'name'         => $data['name'],
            'type'         => $data['type'] ?? 'doctor',
            'specialty_id' => $data['specialty_id'],
            'is_recurring' => $data['is_recurring'] ?? true,
            'is_active'    => $data['is_active'] ?? true,
        ]);

        return response()->json([
            'status' => 'success',
            'data'   => $provider,
        ], 201);
    }

    /**
     * تعديل طبيب / وحدة أو تفعيل/إيقاف التكرار الأسبوعي
     */
    public function updateProvider(Request $request, $id)
    {
        $provider = Provider::findOrFail($id);

        $data = $request->validate([
            'name'         => 'sometimes|string|max:120',
            'type'         => 'sometimes|in:doctor,radiology,lab',
            'specialty_id' => 'sometimes|exists:specialties,id',
            'is_recurring' => 'sometimes|boolean',
            'is_active'    => 'sometimes|boolean',
        ]);

        $provider->update($data);

        return response()->json([
            'status' => 'success',
            'data'   => $provider,
        ]);
    }

    /**
     * عرض جداول أوقات الأطباء
     */
    public function getSchedules()
    {
        $schedules = ProviderSchedule::with('provider:id,name')
            ->orderBy('provider_id')
            ->orderBy('day_of_week')
            ->get()
            ->map(fn($s) => [
                'id'                    => $s->id,
                'provider_id'           => $s->provider_id,
                'provider'              => $s->provider->name ?? '-',
                'day_of_week'           => $s->day_of_week,
                'start_time'            => substr((string) $s->start_time, 0, 5),
                'end_time'              => substr((string) $s->end_time, 0, 5),
                'slot_duration_minutes' => (int) $s->slot_duration_minutes,
                'is_exception'          => (bool) $s->is_exception,
                'exception_date'        => $s->exception_date?->toDateString(),
            ]);

        return response()->json([
            'status' => 'success',
            'data'   => $schedules,
        ]);
    }

    /**
     * إضافة فترة دوام لطبيب
     */
    public function storeSchedule(Request $request)
    {
        $data = $request->validate([
            'provider_id'           => 'required|exists:providers,id',
            'day_of_week'           => 'nullable|integer|min:0|max:6',
            'start_time'            => 'required|date_format:H:i',
            'end_time'              => 'required|date_format:H:i|after:start_time',
            'slot_duration_minutes' => 'sometimes|integer|in:10,15,20,30,45,60',
            'is_exception'          => 'sometimes|boolean',
            'exception_date'        => 'nullable|date',
        ]);

        $schedule = ProviderSchedule::create([
            'provider_id'           => $data['provider_id'],
            'day_of_week'           => $data['day_of_week'] ?? ($data['exception_date'] ? Carbon::parse($data['exception_date'])->dayOfWeek : null),
            'start_time'            => $data['start_time'] . ':00',
            'end_time'              => $data['end_time'] . ':00',
            'slot_duration_minutes' => $data['slot_duration_minutes'] ?? 30,
            'is_exception'          => $data['is_exception'] ?? false,
            'exception_date'        => $data['exception_date'] ?? null,
        ]);

        return response()->json([
            'status' => 'success',
            'data'   => $schedule,
        ], 201);
    }

    /**
     * حذف فترة دوام
     */
    public function deleteSchedule($id)
    {
        ProviderSchedule::findOrFail($id)->delete();

        return response()->json([
            'status'  => 'success',
            'message' => 'Deleted',
        ]);
    }
}