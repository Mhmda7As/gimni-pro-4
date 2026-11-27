<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\Patient;
use App\Services\AuditLoggerService;
use App\Services\BookingService;
use Carbon\Carbon;
use DomainException;
use Illuminate\Http\Request;

class ExcelAppointmentController extends Controller
{
    public function __construct(
        protected BookingService $bookingService
    ) {}

    /**
     * مواعيد اليوم غير المؤرشفة (لورقة Daily)
     */
    public function daily(Request $request)
    {
        $date = $request->input('date', Carbon::today()->toDateString());

        $appointments = Appointment::with(['patient', 'provider.specialty'])
            ->whereDate('appointment_date', $date)
            ->where('is_archived', false)
            ->orderBy('start_time')
            ->get()
            ->map(fn($app) => $this->formatRow($app));

        return response()->json([
            'status' => 'success',
            'data'   => $appointments,
        ]);
    }

    /**
     * المواعيد المؤرشفة أو السابقة (لورقة Archive)
     */
    public function archive(Request $request)
    {
        $table = (new Appointment)->getTable();
        $hasIsArchived = \Illuminate\Support\Facades\Schema::hasColumn($table, 'is_archived');
        $hasArchived = \Illuminate\Support\Facades\Schema::hasColumn($table, 'archived');

        $query = Appointment::with(['patient', 'provider.specialty'])
            ->where(function ($q) use ($hasIsArchived, $hasArchived) {
                $q->whereDate('appointment_date', '<', Carbon::today());
                if ($hasIsArchived) {
                    $q->orWhere('is_archived', true);
                } elseif ($hasArchived) {
                    $q->orWhere('archived', true);
                }
            });

        $appointments = $query->orderByDesc('appointment_date')
            ->limit(1000)
            ->get()
            ->map(fn($app) => $this->formatRow($app));

        return response()->json([
            'status' => 'success',
            'data'   => $appointments,
        ]);
    }

    /**
     * تحديث حالة الموعد (attended / cancelled / no_show / scheduled)
     */
    public function updateStatus(Request $request, $id)
    {
        $data = $request->validate([
            'status' => 'required|in:scheduled,attended,cancelled,no_show',
        ]);

        $appointment = Appointment::with('patient')->findOrFail($id);

        if (Carbon::parse($appointment->appointment_date)->lt(Carbon::today())) {
            return response()->json(['message' => 'Cannot modify a past-date appointment.'], 403);
        }

        try {
            $actorId = $request->user()?->id;

            if ($data['status'] === 'cancelled') {
                $this->bookingService->cancelAppointment($appointment->id, 'Cancelled via API', $actorId);
            } elseif ($data['status'] === 'no_show') {
                $this->bookingService->markAsNoShow($appointment->id, $actorId);
            } else {
                $oldValues = $appointment->toArray();
                $appointment->update(['status' => $data['status']]);
                AuditLoggerService::log(
                    'appointment_' . $data['status'],
                    $appointment,
                    $oldValues,
                    $appointment->fresh()->toArray(),
                    $actorId
                );
            }

            return response()->json([
                'status'  => 'success',
                'message' => 'Status updated',
                'data'    => $this->formatRow($appointment->fresh(['patient', 'provider.specialty'])),
            ]);
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * إضافة أو تعديل الدفعة المالية والملاحظات للموعد
     */
    public function addPayment(Request $request, $id)
    {
        $data = $request->validate([
            'amount' => 'required|numeric|min:0|max:999999',
            'notes'  => 'nullable|string|max:500',
        ]);

        $appointment = Appointment::findOrFail($id);

        if (Carbon::parse($appointment->appointment_date)->lt(Carbon::today())) {
            return response()->json(['message' => 'Cannot modify a past-date appointment.'], 403);
        }

        $oldValues = $appointment->toArray();
        $appointment->payment_amount = $data['amount'];

        if (array_key_exists('notes', $data)) {
            $appointment->notes = $data['notes'];
        }

        $appointment->save();

        AuditLoggerService::log(
            'appointment_payment_updated',
            $appointment,
            $oldValues,
            $appointment->fresh()->toArray(),
            $request->user()?->id
        );

        return response()->json([
            'status'  => 'success',
            'message' => 'Payment saved',
            'data'    => $this->formatRow($appointment->fresh(['patient', 'provider.specialty'])),
        ]);
    }

    /**
     * حظر أو فك حظر المريض
     */
    public function toggleBan(Request $request, $id)
    {
        $patient = Patient::findOrFail($id);
        $oldValues = $patient->toArray();
        $newBanned = !$patient->is_banned;

        $patient->update([
            'is_banned'     => $newBanned,
            'banned_until'  => $newBanned ? now()->addDays(30) : null,
            'no_show_count' => $newBanned ? $patient->no_show_count : 0,
        ]);

        AuditLoggerService::log(
            $newBanned ? 'patient_banned_manually' : 'patient_unbanned_manually',
            $patient,
            $oldValues,
            $patient->fresh()->toArray(),
            $request->user()?->id
        );

        return response()->json([
            'status'    => 'success',
            'is_banned' => (bool) $patient->is_banned,
            'message'   => $patient->is_banned ? 'Patient banned' : 'Patient unbanned',
        ]);
    }

    /**
     * توحيد شكل الصف مطابقاً لأعمدة الإكسل المعتمدة
     */
    private function formatRow(Appointment $app): array
    {
        return [
            'id'           => $app->id,
            'patient_id'   => $app->patient_id,
            'date'         => $app->appointment_date instanceof Carbon
                ? $app->appointment_date->format('Y-m-d')
                : (string) $app->appointment_date,
            'time'         => substr((string) $app->start_time, 0, 5),
            'status'       => $app->status,
            'patient_name' => $app->patient->last_registered_name ?? 'Unknown',
            'phone'        => $app->patient->phone_number ?? '',
            'no_shows'     => (int) ($app->patient->no_show_count ?? 0),
            'banned'       => (bool) ($app->patient->isCurrentlyBanned() ?? false),
            'doctor_unit'  => $app->provider->name ?? '-',
            'specialty'    => $app->provider->specialty->name ?? '-',
            'payment'      => (float) ($app->payment_amount ?? 0),
            'archived'     => (bool) ($app->is_archived ?? false),
            'notes'        => $app->notes ?? '',
        ];
    }
}