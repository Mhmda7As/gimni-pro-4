<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\Patient;
use App\Models\Provider;
use App\Models\TimeSlot;
use App\Services\AuditLoggerService;
use App\Services\BookingService;
use Carbon\Carbon;
use DomainException;
use Illuminate\Http\Request;

class DailyDeskController extends Controller
{
    public function __construct(
        protected BookingService $bookingService
    ) {}

    public function index()
    {
        $today = Carbon::today();

        // 1. جلب مواعيد اليوم فقط (النشطة وغير المؤرشفة وغير الملغاة)
        $appointments = Appointment::with(['patient', 'provider.specialty'])
            ->whereDate('appointment_date', $today)
            ->where('is_archived', false)
            ->where('status', '!=', 'cancelled')
            ->orderBy('start_time')
            ->get()
            ->map(function ($apt) {
                return [
                    'id'             => $apt->id,
                    'time'           => Carbon::parse($apt->start_time)->format('H:i'),
                    'patient_id'     => $apt->patient_id,
                    'patient_name'   => $apt->patient->last_registered_name ?? 'غير محدد',
                    'patient_phone'  => $apt->patient->phone_number ?? '',
                    'no_show_count'  => $apt->patient->no_show_count ?? 0,
                    'is_banned'      => (bool) ($apt->patient?->isCurrentlyBanned() ?? false),
                    'banned_until'   => $apt->patient?->banned_until ? Carbon::parse($apt->patient->banned_until)->format('Y-m-d') : null,
                    'provider_id'    => $apt->provider_id,
                    'provider_name'  => $apt->provider->name ?? 'غير محدد',
                    'specialty_name' => $apt->provider->specialty->name ?? '',
                    'status'         => $apt->status,
                    'payment_amount' => (float) ($apt->payment_amount ?? 0),
                ];
            });

        $providers = Provider::with('specialty')->where('is_active', true)->get();

        // 2. جلب الفترات المتاحة اليوم (available أو cooldown انتهى وقته)
        $availableSlots = TimeSlot::with('provider')
            ->whereDate('date', $today)
            ->where(function ($query) {
                $query->where('status', 'available')
                    ->orWhere(function ($q) {
                        $q->where('status', 'cooldown')
                          ->whereNotNull('available_at')
                          ->where('available_at', '<=', now());
                    });
            })
            ->whereTime('start_time', '>=', now()->format('H:i:s'))
            ->orderBy('start_time')
            ->get()
            ->map(fn($slot) => [
                'id'            => $slot->id,
                'provider_id'   => $slot->provider_id,
                'provider_name' => $slot->provider->name ?? '',
                'time'          => Carbon::parse($slot->start_time)->format('H:i'),
            ]);

        return view('desk.index', compact('appointments', 'providers', 'availableSlots'));
    }

    public function updateStatus(Request $request, Appointment $appointment)
    {
        $this->ensureNotPast($appointment);

        $data = $request->validate([
            'status' => ['required', 'in:attended,cancelled,no_show'],
        ]);

        try {
            $actorId = auth()->id();

            if ($data['status'] === 'cancelled') {
                $this->bookingService->cancelAppointment(
                    $appointment->id,
                    'إلغاء من لوحة الاستقبال',
                    $actorId
                );
            } elseif ($data['status'] === 'no_show') {
                $this->bookingService->markAsNoShow($appointment->id, $actorId);
            } elseif ($data['status'] === 'attended') {
                $oldValues = $appointment->toArray();
                $appointment->update(['status' => 'attended']);

                AuditLoggerService::log(
                    'appointment_attended',
                    $appointment,
                    $oldValues,
                    $appointment->fresh()->toArray(),
                    $actorId
                );
            }

            $freshAppointment = $appointment->fresh(['patient']);

            return response()->json([
                'message'       => 'تم تحديث حالة الموعد بنجاح.',
                'status'        => $freshAppointment->status,
                'no_show_count' => $freshAppointment->patient->no_show_count ?? 0,
                'is_banned'     => (bool) ($freshAppointment->patient?->isCurrentlyBanned() ?? false),
                'banned_until'  => $freshAppointment->patient?->banned_until?->format('Y-m-d'),
            ]);
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function updatePayment(Request $request, Appointment $appointment)
    {
        $this->ensureNotPast($appointment);

        $data = $request->validate([
            'payment_amount' => ['required', 'numeric', 'min:0', 'max:999999'],
        ]);

        $oldValues = $appointment->toArray();
        $appointment->update(['payment_amount' => $data['payment_amount']]);

        AuditLoggerService::log(
            'appointment_payment_updated',
            $appointment,
            $oldValues,
            $appointment->fresh()->toArray(),
            auth()->id()
        );

        return response()->json([
            'message'        => 'تم حفظ مبلغ الدفعة.',
            'payment_amount' => (float) $appointment->payment_amount,
        ]);
    }

    public function toggleBan(Request $request, Patient $patient)
    {
        $data = $request->validate([
            'action'   => ['required', 'in:ban,unban'],
            'ban_days' => ['nullable', 'integer', 'min:1', 'max:365'],
        ]);

        $oldValues = $patient->toArray();

        if ($data['action'] === 'ban') {
            $days = (int) ($data['ban_days'] ?? 30);
            $until = now()->addDays($days);

            $patient->update([
                'is_banned'    => true,
                'banned_until' => $until,
            ]);

            AuditLoggerService::log(
                'patient_banned_manually',
                $patient,
                $oldValues,
                $patient->fresh()->toArray(),
                auth()->id()
            );
        } else {
            $patient->update([
                'is_banned'     => false,
                'banned_until'  => null,
                'no_show_count' => 0,
            ]);

            AuditLoggerService::log(
                'patient_unbanned_manually',
                $patient,
                $oldValues,
                $patient->fresh()->toArray(),
                auth()->id()
            );
        }

        $freshPatient = $patient->fresh();

        return response()->json([
            'message'      => $data['action'] === 'ban' ? 'تم حظر المريض.' : 'تم فك الحظر عن المريض.',
            'is_banned'    => (bool) $freshPatient->isCurrentlyBanned(),
            'banned_until' => $freshPatient->banned_until ? Carbon::parse($freshPatient->banned_until)->format('Y-m-d') : null,
        ]);
    }

    public function quickStore(Request $request)
    {
        $data = $request->validate([
            'name'         => ['required', 'string', 'max:150'],
            'phone'        => ['required', 'string', 'max:30'],
            'time_slot_id' => ['required', 'exists:time_slots,id'],
        ]);

        $slot = TimeSlot::findOrFail($data['time_slot_id']);

        if (Carbon::parse($slot->date)->lt(Carbon::today())) {
            return back()->withErrors(['time_slot_id' => 'لا يمكن الحجز في تاريخ سابق.']);
        }

        $cleanPhone = preg_replace('/\D+/', '', $data['phone']) ?: $data['phone'];

        $patient = Patient::firstOrCreate(
            ['phone_number' => $cleanPhone],
            [
                'last_registered_name' => $data['name'],
                'kvkk_accepted'        => true,
            ]
        );

        if (!$patient->kvkk_accepted || empty($patient->last_registered_name)) {
            $patient->update([
                'last_registered_name' => $patient->last_registered_name ?: $data['name'],
                'kvkk_accepted'        => true,
            ]);
        }

        try {
            $this->bookingService->bookSlot(
                $patient->id,
                $slot->id,
                'حجز يدوي من لوحة الاستقبال',
                auth()->id()
            );

            return redirect()->route('desk.index')->with('success', 'تم إضافة الحجز اليدوي بنجاح.');
        } catch (DomainException $e) {
            return back()->withErrors(['booking' => $e->getMessage()]);
        }
    }

    private function ensureNotPast(Appointment $appointment): void
    {
        if (Carbon::parse($appointment->appointment_date)->lt(Carbon::today())) {
            abort(403, 'قاعدة حماية: لا يمكن تعديل أو إلغاء موعد انتهى يومه.');
        }
    }
}