<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\Provider;
use App\Models\ProviderSchedule;
use App\Models\TimeSlot;
use App\Services\AuditLoggerService;
use App\Services\BookingService;
use App\Services\SlotGeneratorService;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use DomainException;
use Illuminate\Http\Request;

class ScheduleController extends Controller
{
    public function __construct(
        protected SlotGeneratorService $slotGenerator,
        protected BookingService $bookingService
    ) {}

    public function index(Request $request)
    {
        $providers = Provider::with('specialty')
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $selectedProviderId = $request->input('provider_id') ?? $providers->first()?->id;
        $selectedProvider =$selectedProviderId
            ? Provider::with(['specialty', 'schedules'])->find($selectedProviderId)
            : null;

        $daysMap = [
            0 => 'الأحد',
            1 => 'الاثنين',
            2 => 'الثلاثاء',
            3 => 'الأربعاء',
            4 => 'الخميس',
            5 => 'الجمعة',
            6 => 'السبت',
        ];

        $weeklySchedules = collect();$exceptions = collect();
        $weekGrid = [];$upcomingSlotsCount = 0;

        if ($selectedProvider) {
            $weeklySchedules =$selectedProvider->schedules
                ->where('is_exception', false)
                ->sortBy(['day_of_week', 'start_time']);

            $exceptions =$selectedProvider->schedules
                ->where('is_exception', true)
                ->filter(fn ($s) => $s->exception_date &&$s->exception_date->gte(Carbon::today()))
                ->sortBy('exception_date');

            $startDate = Carbon::today();$endDate = Carbon::today()->addDays(6); // 7 أيام شاملة اليوم

            // جلب جميع الفترات للأيام السبعة القادمة
            $slots = TimeSlot::where('provider_id',$selectedProvider->id)
                ->whereBetween('date', [$startDate->toDateString(),$endDate->toDateString()])
                ->orderBy('start_time')
                ->get()
                ->groupBy(fn ($s) =>$s->date->toDateString());

            // جلب المواعيد المرتبطة بهذه الفترات (المحجوزة أو الملغاة) لعرض التفاصيل والألوان
            $appointmentsBySlot = Appointment::with('patient')
                ->where('provider_id', $selectedProvider->id)
                ->whereBetween('appointment_date', [$startDate->toDateString(),$endDate->toDateString()])
                ->get()
                ->groupBy('time_slot_id');

            $upcomingSlotsCount = TimeSlot::where('provider_id',$selectedProvider->id)
                ->whereBetween('date', [$startDate->toDateString(),$endDate->toDateString()])
                ->where('status', 'available')
                ->count();

            // بناء مصفوفة الـ 7 أعمدة (الأيام)
            foreach (CarbonPeriod::create($startDate, $endDate) as$day) {
                $dateStr =$day->toDateString();

                $dayException =$exceptions->first(
                    fn ($ex) =>$ex->exception_date && $ex->exception_date->toDateString() ===$dateStr
                );
                $isDayOff =$dayException && $dayException->start_time ===$dayException->end_time;

                $daySlots = ($slots->get($dateStr) ?? collect())->map(function ($slot) use ($appointmentsBySlot) {$slotAppointments = $appointmentsBySlot->get($slot->id) ?? collect();
                    $activeApt =$slotAppointments->firstWhere('status', '!=', 'cancelled');
                    $cancelledApt =$slotAppointments->firstWhere('status', 'cancelled');

                    // تحديد اللون والحالة البصرية للخانة
                    $visualState = 'available'; // أزرق: متاح للحجز
                    if ($slot->status === 'booked' || $activeApt) {$visualState = 'booked'; // أخضر: محجوز
                    } elseif ($slot->status === 'cooldown' || $cancelledApt) {$visualState = 'cancelled'; // أحمر: ملغي / فترة انتظار
                    }

                    return [
                        'id'            => $slot->id,
                        'date'          => $slot->date->toDateString(),
                        'start_time'    => Carbon::parse($slot->start_time)->format('H:i'),
                        'end_time'      => Carbon::parse($slot->end_time)->format('H:i'),
                        'status'        => $slot->status,
                        'visual_state'  => $visualState,
                        'available_at'  => $slot->available_at?->format('H:i'),
                        'appointment'   => $activeApt ? [
                            'id'           => $activeApt->id,
                            'patient_name' => $activeApt->patient->last_registered_name ?? 'مريض',
                            'patient_phone'=> $activeApt->patient->phone_number ?? '',
                            'status'       => $activeApt->status,
                        ] : null,
                        'cancelled_by'  => $cancelledApt?->patient?->last_registered_name,
                    ];
                })->values();

                $weekGrid[] = [
                    'date'         => $dateStr,
                    'day_name'     => $daysMap[$day->dayOfWeek] ?? '',
                    'is_today'     => $day->isToday(),
                    'is_day_off'   => $isDayOff,
                    'has_exception'=> (bool) $dayException,
                    'slots'        => $daySlots,
                ];
            }
        }

        return view('schedules.index', compact(
            'providers',
            'selectedProvider',
            'weeklySchedules',
            'exceptions',
            'weekGrid',
            'upcomingSlotsCount',
            'daysMap'
        ));
    }

    /**
     * إضافة فترة دوام أسبوعي متكرر
     */
    /**
     * إضافة فترة دوام أسبوعي متكرر + توليد فتراتها تلقائياً للأسبوع القادم
     */
    public function storeWeekly(Request $request, Provider $provider)
    {
        $data = $request->validate([
            'day_of_week'           => ['required', 'integer', 'between:0,6'],
            'start_time'            => ['required', 'date_format:H:i'],
            'end_time'              => ['required', 'date_format:H:i', 'after:start_time'],
            'slot_duration_minutes' => ['required', 'integer', 'in:10,15,20,30,45,60'],
        ], [
            'end_time.after' => 'وقت نهاية الدوام يجب أن يكون بعد وقت البداية.',
        ]);

        $schedule = ProviderSchedule::create([
            'provider_id'           => $provider->id,
            'day_of_week'           => $data['day_of_week'],
            'start_time'            => $data['start_time'] . ':00',
            'end_time'              => $data['end_time'] . ':00',
            'slot_duration_minutes' => $data['slot_duration_minutes'],
            'is_exception'          => false,
            'exception_date'        => null,
        ]);

        if (!$provider->is_recurring) {
            $provider->update(['is_recurring' => true]);
        }

        // توليد الفترات تلقائياً للأسبوع القادم فور إضافة الدوام
        $generatedCount = $this->slotGenerator->generateForProvider(
            $provider,
            Carbon::today(),
            Carbon::today()->addDays(6)
        );

        AuditLoggerService::log(
            'weekly_schedule_added',
            $schedule,
            null,
            array_merge($schedule->toArray(), ['auto_generated_slots' => $generatedCount]),
            auth()->id()
        );

        return redirect()
            ->route('schedules.index', ['provider_id' => $provider->id])
            ->with('success', "تمت إضافة الدوام الأسبوعي وتوليد ({$generatedCount}) فترة زمنية تلقائياً.");
    }

    /**
     * إضافة إجازة أو دوام استثنائي لتاريخ محدد
     */
    public function storeException(Request $request, Provider$provider)
    {
        $data =$request->validate([
            'exception_date'        => ['required', 'date', 'after_or_equal:today'],
            'exception_type'        => ['required', 'in:day_off,custom_shift'],
            'start_time'            => ['required_if:exception_type,custom_shift', 'nullable', 'date_format:H:i'],
            'end_time'              => ['required_if:exception_type,custom_shift', 'nullable', 'date_format:H:i', 'after:start_time'],
            'slot_duration_minutes' => ['required_if:exception_type,custom_shift', 'nullable', 'integer', 'in:10,15,20,30,45,60'],
        ], [
            'exception_date.after_or_equal' => 'لا يمكن إضافة استثناء لتاريخ في الماضي.',
            'end_time.after'                => 'وقت النهاية يجب أن يكون بعد وقت البداية.',
        ]);

        $isDayOff =$data['exception_type'] === 'day_off';

        $schedule = ProviderSchedule::create([
            'provider_id'           => $provider->id,
            'day_of_week'           => Carbon::parse($data['exception_date'])->dayOfWeek,
            'start_time'            => $isDayOff ? '00:00:00' : $data['start_time'] . ':00',
            'end_time'              => $isDayOff ? '00:00:00' : $data['end_time'] . ':00',
            'slot_duration_minutes' => $isDayOff ? 30 : $data['slot_duration_minutes'],
            'is_exception'          => true,
            'exception_date'        => $data['exception_date'],
        ]);

        $deletedAvailable = TimeSlot::where('provider_id',$provider->id)
            ->whereDate('date', $data['exception_date'])
            ->where('status', 'available')
            ->delete();

        if (!$isDayOff) {
            $date = Carbon::parse($data['exception_date']);
            $this->slotGenerator->generateForProvider($provider, $date,$date);
        }

        AuditLoggerService::log(
            'schedule_exception_added',
            $schedule,
            null,
            array_merge($schedule->toArray(), ['cleaned_available_slots' =>$deletedAvailable]),
            auth()->id()
        );

        return redirect()
            ->route('schedules.index', ['provider_id' => $provider->id])
            ->with('success', $isDayOff ? 'تم تسجيل يوم الإجازة وإغلاق الفترات غير المحجوزة.' : 'تم تسجيل الدوام الاستثنائي وتوليد فتراته.');
    }

    /**
     * تعديل خانة موعد واحدة عند النقر عليها في الشبكة (إغلاق / إعادة إتاحة / إلغاء حجز)
     */
    public function updateSlot(Request $request, TimeSlot$slot)
    {
        $data =$request->validate([
            'action' => ['required', 'in:close_slot,make_available,cancel_booking'],
        ]);

        if (Carbon::parse($slot->date)->lt(Carbon::today())) {
            return response()->json(['message' => 'لا يمكن تعديل فترة في الماضي.'], 403);
        }

        try {
            if ($data['action'] === 'close_slot') {
                if ($slot->status === 'booked') {
                    return response()->json(['message' => 'هذه الفترة محجوزة لمريض، يرجى إلغاء الحجز أولاً.'], 422);
                }

                $oldValues = $slot->toArray();$slot->delete(); // SoftDelete لإغلاق هذه الفترة ومنع حجزها

                AuditLoggerService::log('time_slot_closed', $slot,$oldValues, null, auth()->id());

                return response()->json(['message' => 'تم إغلاق الفترة وتحويلها إلى غير متاح.']);
            }

            if ($data['action'] === 'make_available') {$oldValues = $slot->toArray();$slot->update([
                    'status'       => 'available',
                    'available_at' => null,
                ]);

                AuditLoggerService::log('time_slot_reopened', $slot, $oldValues,$slot->fresh()->toArray(), auth()->id());

                return response()->json(['message' => 'تمت إعادة إتاحة الفترة للحجز فوراً.']);
            }

            if ($data['action'] === 'cancel_booking') {
                $appointment = Appointment::where('time_slot_id',$slot->id)
                    ->where('status', 'scheduled')
                    ->firstOrFail();

                $this->bookingService->cancelAppointment($appointment->id,
                    'إلغاء من شبكة مواعيد الطبيب',
                    auth()->id()
                );

                return response()->json(['message' => 'تم إلغاء الحجز ووضع الفترة في الانتظار المؤقت.']);
            }
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['message' => 'إجراء غير مدعوم.'], 400);
    }

    /**
     * حذف قاعدة دوام أو استثناء
     */
    /**
     * حذف قاعدة دوام أو استثناء + تنظيف الفترات غير المحجوزة المرتبطة بها
     */
    public function destroy(ProviderSchedule $schedule)
    {
        $provider = $schedule->provider;
        $providerId = $schedule->provider_id;
        $oldValues = $schedule->toArray();

        $startTime = $schedule->start_time;
        $endTime = $schedule->end_time;
        $isException = $schedule->is_exception;
        $exceptionDate = $schedule->exception_date;
        $dayOfWeek = $schedule->day_of_week;

        $schedule->delete();

        if ($isException && $exceptionDate) {
            // إذا حُذف استثناء (مثل عطلة)، نعيد توليد فترات ذلك اليوم حسب الجدول الأسبوعي إن وجد
            if ($exceptionDate->gte(Carbon::today()) && $provider) {
                $this->slotGenerator->generateForProvider($provider, $exceptionDate, $exceptionDate);
            }
        } else {
            // إذا حُذف دوام أسبوعي، نحذف الفترات المتاحة (غير المحجوزة) القادمة في نفس اليوم ونفس الوقت
            $upcomingSlots = TimeSlot::where('provider_id', $providerId)
                ->whereDate('date', '>=', Carbon::today())
                ->where('status', 'available')
                ->where('start_time', '>=', $startTime)
                ->where('end_time', '<=', $endTime)
                ->get();

            foreach ($upcomingSlots as $slot) {
                if ($slot->date->dayOfWeek === (int) $dayOfWeek) {
                    $slot->delete();
                }
            }
        }

        AuditLoggerService::log('schedule_deleted', $schedule, $oldValues, null, auth()->id());

        return redirect()
            ->route('schedules.index', ['provider_id' => $providerId])
            ->with('success', 'تم حذف السجل وتحديث الشبكة تلقائياً.');
    }

    /**
     * توليد الفترات الزمنية للأسبوع القادم (7 أيام فقط)
     */
    public function generateSlots(Provider $provider)
    {
        $startDate = Carbon::today();$endDate = Carbon::today()->addDays(6); // 7 أيام شاملة اليوم

        $count = $this->slotGenerator->generateForProvider($provider, $startDate,$endDate);

        AuditLoggerService::log(
            'time_slots_generated',
            $provider,
            null,
            [
                'days'            => 7,
                'start_date'      => $startDate->toDateString(),
                'end_date'        => $endDate->toDateString(),
                'generated_count' => $count,
            ],
            auth()->id()
        );

        return redirect()
            ->route('schedules.index', ['provider_id' => $provider->id])
            ->with('success', "تم توليد ({$count}) فترة زمنية جديدة للأسبوع القادم.");
    }
}