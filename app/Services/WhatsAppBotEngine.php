<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\Patient;
use App\Models\Provider;
use App\Models\Setting;
use App\Models\Specialty;
use App\Models\TimeSlot;
use App\Models\WhatsappSession;
use Carbon\Carbon;
use DomainException;

class WhatsAppBotEngine
{
    public function __construct(
        private readonly WhatsAppCloudService $whatsapp,
        private readonly BookingService $bookingService
    ) {}

    /**
     * Entry point for every incoming WhatsApp message or interactive reply.
     */
    public function handleIncoming(string $phoneNumber, string $inputId, string $inputText): void
    {
        $cleanPhone = preg_replace('/\D+/', '', $phoneNumber);
        $clinicName = Setting::getValue('clinic_name', 'العيادة الطبية');

        // 1. Check Global Booking Switch
        if (Setting::getValue('global_booking_enabled', '1') !== '1') {
            $this->whatsapp->sendText(
                $cleanPhone,
                "مرحباً بك في {$clinicName}.\nنظام الحجز التلقائي متوقف مؤقتاً في الوقت الحالي. يرجى التواصل مع الاستقبال."
            );
            return;
        }

        // 2. Find or initialize Patient & Check Ban Status
        $patient = Patient::firstOrCreate(
            ['phone_number' => $cleanPhone],
            ['kvkk_accepted' => false]
        );

        if ($patient->isCurrentlyBanned()) {
            $untilMsg = $patient->banned_until
                ? 'حتى تاريخ ' . $patient->banned_until->format('Y-m-d H:i')
                : 'بشكل دائم';
            $this->whatsapp->sendText(
                $cleanPhone,
                "عذراً، تم إيقاف إمكانية الحجز لهذا الرقم ({$untilMsg}) بسبب تكرار عدم الحضور للمواعيد المحجوزة. يرجى مراجعة إدارة العيادة."
            );
            return;
        }

        // 3. Load or initialize Session
        $session = WhatsappSession::firstOrCreate(
            ['phone_number' => $cleanPhone],
            ['current_step' => 'MAIN_MENU', 'payload' => []]
        );

        // Global reset keywords
        $normalizedText = mb_strtolower(trim($inputText));
        if (in_array($normalizedText, ['0', 'menu', 'القائمة', 'الغاء', 'إلغاء', 'بداية'], true) || $inputId === 'GO_MAIN_MENU') {
            $this->sendMainMenu($cleanPhone, $clinicName, $session);
            return;
        }

        // 4. Route based on current state machine step
        match ($session->current_step) {
            'MAIN_MENU' => $this->handleMainMenu($cleanPhone, $inputId, $patient, $session, $clinicName),
            'KVKK_CONSENT' => $this->handleKvkkConsent($cleanPhone, $inputId, $patient, $session),
            'SELECT_SPECIALTY' => $this->handleSpecialtySelection($cleanPhone, $inputId, $session),
            'SELECT_PROVIDER' => $this->handleProviderSelection($cleanPhone, $inputId, $session),
            'SELECT_DATE' => $this->handleDateSelection($cleanPhone, $inputId, $session),
            'SELECT_SLOT' => $this->handleSlotSelection($cleanPhone, $inputId, $patient, $session),
            'CONFIRM_EXISTING_NAME' => $this->handleExistingNameChoice($cleanPhone, $inputId, $patient, $session),
            'ENTER_NEW_NAME' => $this->handleNewNameInput($cleanPhone, $inputText, $patient, $session),
            'CONFIRM_BOOKING' => $this->handleBookingConfirmation($cleanPhone, $inputId, $patient, $session, $clinicName),
            'MY_APPOINTMENTS' => $this->handleCancelAppointmentSelection($cleanPhone, $inputId, $patient, $session, $clinicName),
            'CONFIRM_CANCEL' => $this->handleCancelConfirmation($cleanPhone, $inputId, $patient, $session, $clinicName),
            default => $this->sendMainMenu($cleanPhone, $clinicName, $session),
        };
    }

    private function sendMainMenu(string $phone, string $clinicName, WhatsappSession $session): void
    {
        $session->update([
            'current_step' => 'MAIN_MENU',
            'payload' => [],
        ]);

        $this->whatsapp->sendButtons(
            $phone,
            "أهلاً بك في *{$clinicName}*.\nيرجى اختيار الخدمة المطلوبة:\n(يمكنك إرسال 0 في أي وقت للعودة للقائمة الرئيسية)",
            [
                ['id' => 'ACT_BOOK', 'title' => 'حجز موعد جديد'],
                ['id' => 'ACT_MY_APTS', 'title' => 'مواعيدي / إلغاء'],
            ],
            $clinicName
        );
    }

    private function handleMainMenu(string $phone, string $inputId, Patient $patient, WhatsappSession $session, string $clinicName): void
    {
        if ($inputId === 'ACT_BOOK') {
            if (!$patient->kvkk_accepted) {
                $session->update(['current_step' => 'KVKK_CONSENT']);
                $this->whatsapp->sendButtons(
                    $phone,
                    "قبل المتابعة، يرجى الموافقة على سياسة حماية البيانات الشخصية (KVKK) الخاصة بالعيادة لاستخدام بياناتك في تنظيم المواعيد الطبية.",
                    [
                        ['id' => 'KVKK_YES', 'title' => 'أوافق وأتابع'],
                        ['id' => 'GO_MAIN_MENU', 'title' => 'لا أوافق'],
                    ],
                    'موافقة KVKK'
                );
                return;
            }

            $this->promptSpecialties($phone, $session);
            return;
        }

        if ($inputId === 'ACT_MY_APTS') {
            $this->promptMyAppointments($phone, $patient, $session);
            return;
        }

        $this->sendMainMenu($phone, $clinicName, $session);
    }

    private function handleKvkkConsent(string $phone, string $inputId, Patient $patient, WhatsappSession $session): void
    {
        if ($inputId === 'KVKK_YES') {
            $patient->update(['kvkk_accepted' => true]);
            $this->promptSpecialties($phone, $session);
            return;
        }

        $this->whatsapp->sendText($phone, "عذراً، لا يمكن إتمام الحجز الإلكتروني دون الموافقة على سياسة حماية البيانات (KVKK).");
        $session->update(['current_step' => 'MAIN_MENU']);
    }

    private function promptSpecialties(string $phone, WhatsappSession $session): void
    {
        $specialties = Specialty::where('is_booking_enabled', true)->get();

        if ($specialties->isEmpty()) {
            $this->whatsapp->sendText($phone, "عذراً، لا توجد تخصصات متاحة للحجز حالياً.");
            $session->update(['current_step' => 'MAIN_MENU']);
            return;
        }

        $rows = $specialties->map(fn ($s) => [
            'id' => 'SPEC_' . $s->id,
            'title' => $s->name,
        ])->toArray();

        $session->update(['current_step' => 'SELECT_SPECIALTY']);

        $this->whatsapp->sendList(
            $phone,
            "يرجى اختيار القسم أو التخصص المطلوب:",
            "عرض التخصصات",
            "التخصصات المتاحة",
            $rows
        );
    }

    private function handleSpecialtySelection(string $phone, string $inputId, WhatsappSession $session): void
    {
        if (!str_starts_with($inputId, 'SPEC_')) {
            $this->promptSpecialties($phone, $session);
            return;
        }

        $specialtyId = (int) str_replace('SPEC_', '', $inputId);
        $specialty = Specialty::where('id', $specialtyId)
            ->where('is_booking_enabled', true)
            ->first();

        if (!$specialty) {
            $this->whatsapp->sendText($phone, "عذراً، الحجز لهذا التخصص غير متاح حالياً.");
            $this->promptSpecialties($phone, $session);
            return;
        }

        $providers = Provider::where('specialty_id', $specialtyId)
            ->where('is_active', true)
            ->get();

        if ($providers->isEmpty()) {
            $this->whatsapp->sendText($phone, "عذراً، لا يوجد أطباء متاحون في هذا التخصص حالياً.");
            $this->promptSpecialties($phone, $session);
            return;
        }

        $rows = $providers->map(fn ($p) => [
            'id' => 'PROV_' . $p->id,
            'title' => trim(($p->title ? $p->title . ' ' : '') . $p->name),
            'description' => match ($p->type) {
                'radiology' => 'قسم الأشعة',
                'lab' => 'المخبر والتحاليل',
                default => 'عيادة طبية',
            },
        ])->toArray();

        $session->update([
            'current_step' => 'SELECT_PROVIDER',
            'payload' => ['specialty_id' => $specialtyId],
        ]);

        $this->whatsapp->sendList(
            $phone,
            "يرجى اختيار الطبيب أو الوحدة:",
            "عرض القائمة",
            "الأطباء والوحدات",
            $rows
        );
    }

    private function handleProviderSelection(string $phone, string $inputId, WhatsappSession $session): void
    {
        if (!str_starts_with($inputId, 'PROV_')) {
            $this->promptSpecialties($phone, $session);
            return;
        }

        $providerId = (int) str_replace('PROV_', '', $inputId);
        $today = now()->toDateString();
        $endDate = now()->addDays(6)->toDateString();

        // Fetch next 7 days that actually have future bookable slots
        $availableDates = TimeSlot::where('provider_id', $providerId)
            ->whereDate('date', '>=', $today)
            ->whereDate('date', '<=', $endDate)
            ->bookable()
            ->where(function ($q) use ($today) {
                $q->whereDate('date', '>', $today)
                  ->orWhere(function ($sub) use ($today) {
                      $sub->whereDate('date', '=', $today)
                          ->where('start_time', '>', now()->format('H:i:s'));
                  });
            })
            ->select('date')
            ->distinct()
            ->orderBy('date')
            ->limit(7)
            ->pluck('date');

        if ($availableDates->isEmpty()) {
            $this->whatsapp->sendText($phone, "عذراً، لا توجد مواعيد شاغرة لهذا الطبيب في الأيام القادمة.");
            $this->promptSpecialties($phone, $session);
            return;
        }

        $rows = $availableDates->map(function ($date) {
            $carbonDate = Carbon::parse($date);
            $dayName = $carbonDate->locale('ar')->translatedFormat('l');
            return [
                'id' => 'DATE_' . $carbonDate->toDateString(),
                'title' => $carbonDate->toDateString(),
                'description' => "يوم {$dayName}",
            ];
        })->toArray();

        $payload = $session->payload ?? [];
        $payload['provider_id'] = $providerId;

        $session->update([
            'current_step' => 'SELECT_DATE',
            'payload' => $payload,
        ]);

        $this->whatsapp->sendList(
            $phone,
            "يرجى اختيار التاريخ المناسب:",
            "عرض التواريخ",
            "الأيام المتاحة",
            $rows
        );
    }

    private function handleDateSelection(string $phone, string $inputId, WhatsappSession $session): void
    {
        $payload = $session->payload ?? [];
        $providerId = $payload['provider_id'] ?? null;

        if (!str_starts_with($inputId, 'DATE_') || !$providerId) {
            $this->promptSpecialties($phone, $session);
            return;
        }

        $selectedDate = str_replace('DATE_', '', $inputId);

        $slotsQuery = TimeSlot::where('provider_id', $providerId)
            ->whereDate('date', $selectedDate)
            ->bookable()
            ->orderBy('start_time')
            ->limit(10);

        // Exclude slots in the past if selected date is today
        if ($selectedDate === now()->toDateString()) {
            $slotsQuery->where('start_time', '>', now()->format('H:i:s'));
        }

        $slots = $slotsQuery->get();

        if ($slots->isEmpty()) {
            $this->whatsapp->sendText($phone, "عذراً، نفدت المواعيد في هذا التاريخ. يرجى اختيار تاريخ آخر.");
            $this->handleProviderSelection($phone, 'PROV_' . $providerId, $session);
            return;
        }

        $rows = $slots->map(fn ($slot) => [
            'id' => 'SLOT_' . $slot->id,
            'title' => 'الساعة ' . substr($slot->start_time, 0, 5),
            'description' => 'حتى ' . substr($slot->end_time, 0, 5),
        ])->toArray();

        $payload['selected_date'] = $selectedDate;
        $session->update([
            'current_step' => 'SELECT_SLOT',
            'payload' => $payload,
        ]);

        $this->whatsapp->sendList(
            $phone,
            "اختر الساعة المناسبة ليوم {$selectedDate}:",
            "عرض الأوقات",
            "الساعات المتاحة",
            $rows
        );
    }

    private function handleSlotSelection(string $phone, string $inputId, Patient $patient, WhatsappSession $session): void
    {
        if (!str_starts_with($inputId, 'SLOT_')) {
            $this->promptSpecialties($phone, $session);
            return;
        }

        $slotId = (int) str_replace('SLOT_', '', $inputId);
        $payload = $session->payload ?? [];
        $payload['time_slot_id'] = $slotId;

        if (!empty($patient->last_registered_name)) {
            $session->update([
                'current_step' => 'CONFIRM_EXISTING_NAME',
                'payload' => $payload,
            ]);

            $this->whatsapp->sendButtons(
                $phone,
                "هل الحجز باسم المريض المسجل لدينا:\n*{$patient->last_registered_name}*؟",
                [
                    ['id' => 'NAME_KEEP', 'title' => 'نعم، نفس الاسم'],
                    ['id' => 'NAME_NEW', 'title' => 'تعديل / اسم آخر'],
                ]
            );
            return;
        }

        $session->update([
            'current_step' => 'ENTER_NEW_NAME',
            'payload' => $payload,
        ]);

        $this->whatsapp->sendText($phone, "يرجى كتابة الاسم الكامل للمريض (الاسم والكنية):");
    }

    private function handleExistingNameChoice(string $phone, string $inputId, Patient $patient, WhatsappSession $session): void
    {
        $payload = $session->payload ?? [];

        if ($inputId === 'NAME_KEEP') {
            $payload['patient_name'] = $patient->last_registered_name;
            $this->promptBookingSummary($phone, $payload, $session);
            return;
        }

        $session->update([
            'current_step' => 'ENTER_NEW_NAME',
            'payload' => $payload,
        ]);

        $this->whatsapp->sendText($phone, "يرجى كتابة الاسم الكامل للمريض:");
    }

    private function handleNewNameInput(string $phone, string $inputText, Patient $patient, WhatsappSession $session): void
    {
        $cleanName = trim($inputText);
        if (mb_strlen($cleanName) < 3) {
            $this->whatsapp->sendText($phone, "يرجى إدخال اسم صحيح وكامل (3 أحرف على الأقل):");
            return;
        }

        $patient->update(['last_registered_name' => $cleanName]);

        $payload = $session->payload ?? [];
        $payload['patient_name'] = $cleanName;

        $this->promptBookingSummary($phone, $payload, $session);
    }

    private function promptBookingSummary(string $phone, array $payload, WhatsappSession $session): void
    {
        $slot = TimeSlot::with('provider.specialty')->find($payload['time_slot_id'] ?? 0);

        if (!$slot) {
            $this->whatsapp->sendText($phone, "حدث خطأ في استرجاع بيانات الموعد. يرجى المحاولة من جديد.");
            $this->promptSpecialties($phone, $session);
            return;
        }

        $providerName = trim(($slot->provider->title ? $slot->provider->title . ' ' : '') . $slot->provider->name);
        $specialtyName = $slot->provider->specialty->name ?? '';
        $dateStr = $slot->date->toDateString();
        $timeStr = substr($slot->start_time, 0, 5);
        $patientName = $payload['patient_name'] ?? '';

        $session->update([
            'current_step' => 'CONFIRM_BOOKING',
            'payload' => $payload,
        ]);

        $summary = "*ملخص تفاصيل الحجز:*\n"
            . "• المريض: {$patientName}\n"
            . "• التخصص: {$specialtyName}\n"
            . "• الطبيب: {$providerName}\n"
            . "• التاريخ: {$dateStr}\n"
            . "• الساعة: {$timeStr}\n\n"
            . "هل تؤكد حجز هذا الموعد؟";

        $this->whatsapp->sendButtons(
            $phone,
            $summary,
            [
                ['id' => 'CONFIRM_YES', 'title' => 'تأكيد الحجز'],
                ['id' => 'GO_MAIN_MENU', 'title' => 'إلغاء'],
            ],
            'تأكيد الموعد'
        );
    }

    private function handleBookingConfirmation(string $phone, string $inputId, Patient $patient, WhatsappSession $session, string $clinicName): void
    {
        if ($inputId !== 'CONFIRM_YES') {
            $this->sendMainMenu($phone, $clinicName, $session);
            return;
        }

        $payload = $session->payload ?? [];
        $slotId = (int) ($payload['time_slot_id'] ?? 0);

        try {
            $appointment = $this->bookingService->bookSlot($patient->id, $slotId, 'Booked via WhatsApp Bot');

            $session->update([
                'current_step' => 'MAIN_MENU',
                'payload' => [],
            ]);

            $this->whatsapp->sendText(
                $phone,
                "✅ *تم تأكيد حجزك بنجاح!*\n"
                . "رقم الموعد: #{$appointment->id}\n"
                . "التاريخ: {$appointment->appointment_date->toDateString()}\n"
                . "الساعة: " . substr($appointment->start_time, 0, 5) . "\n\n"
                . "يرجى الحضور قبل الموعد بـ 10 دقائق. أرسل 0 للعودة للقائمة الرئيسية."
            );
        } catch (DomainException $e) {
            $this->whatsapp->sendText(
                $phone,
                "⚠️ تعذر إتمام الحجز: {$e->getMessage()}\nيرجى اختيار موعد آخر."
            );
            $this->promptSpecialties($phone, $session);
        }
    }

    private function promptMyAppointments(string $phone, Patient $patient, WhatsappSession $session): void
    {
        $appointments = Appointment::with('provider')
            ->where('patient_id', $patient->id)
            ->where('status', 'scheduled')
            ->whereDate('appointment_date', '>=', now()->toDateString())
            ->orderBy('appointment_date')
            ->orderBy('start_time')
            ->limit(10)
            ->get();

        if ($appointments->isEmpty()) {
            $this->whatsapp->sendText($phone, "لا توجد لديك مواعيد قادمة مسجلة حالياً.\nأرسل 0 للعودة للقائمة الرئيسية.");
            $session->update(['current_step' => 'MAIN_MENU']);
            return;
        }

        $rows = $appointments->map(function ($apt) {
            $docName = $apt->provider?->name ?? 'طبيب';
            return [
                'id' => 'CANCEL_APT_' . $apt->id,
                'title' => $apt->appointment_date->toDateString() . ' | ' . substr($apt->start_time, 0, 5),
                'description' => "إلغاء موعد د. {$docName}",
            ];
        })->toArray();

        $session->update(['current_step' => 'MY_APPOINTMENTS']);

        $this->whatsapp->sendList(
            $phone,
            "مواعيدك القادمة:\nاختر الموعد من القائمة أدناه إذا كنت ترغب في *إلغائه*، أو أرسل 0 للعودة.",
            "مواعيدي القادمة",
            "اضغط لإلغاء الموعد",
            $rows
        );
    }

    private function handleCancelAppointmentSelection(string $phone, string $inputId, Patient $patient, WhatsappSession $session, string $clinicName): void
    {
        if (!str_starts_with($inputId, 'CANCEL_APT_')) {
            $this->sendMainMenu($phone, $clinicName, $session);
            return;
        }

        $appointmentId = (int) str_replace('CANCEL_APT_', '', $inputId);
        $appointment = Appointment::with('provider')
            ->where('id', $appointmentId)
            ->where('patient_id', $patient->id)
            ->where('status', 'scheduled')
            ->first();

        if (!$appointment) {
            $this->whatsapp->sendText($phone, "لم يتم العثور على الموعد المطلوب.");
            $this->sendMainMenu($phone, $clinicName, $session);
            return;
        }

        $session->update([
            'current_step' => 'CONFIRM_CANCEL',
            'payload' => ['cancel_appointment_id' => $appointment->id],
        ]);

        $providerName = $appointment->provider?->name ?? 'الطبيب';
        $dateStr = $appointment->appointment_date->toDateString();
        $timeStr = substr($appointment->start_time, 0, 5);

        $this->whatsapp->sendButtons(
            $phone,
            "هل أنت متأكد من رغبتك في إلغاء الموعد رقم #{$appointment->id}؟\n• الطبيب: {$providerName}\n• التاريخ: {$dateStr}\n• الساعة: {$timeStr}",
            [
                ['id' => 'CANCEL_YES', 'title' => 'نعم، تأكيد الإلغاء'],
                ['id' => 'GO_MAIN_MENU', 'title' => 'تراجع'],
            ],
            'تأكيد إلغاء الموعد'
        );
    }

    private function handleCancelConfirmation(string $phone, string $inputId, Patient $patient, WhatsappSession $session, string $clinicName): void
    {
        if ($inputId !== 'CANCEL_YES') {
            $this->sendMainMenu($phone, $clinicName, $session);
            return;
        }

        $payload = $session->payload ?? [];
        $appointmentId = (int) ($payload['cancel_appointment_id'] ?? 0);

        $appointment = Appointment::where('id', $appointmentId)
            ->where('patient_id', $patient->id)
            ->first();

        if (!$appointment) {
            $this->whatsapp->sendText($phone, "لم يتم العثور على الموعد المطلوب.");
            $this->sendMainMenu($phone, $clinicName, $session);
            return;
        }

        try {
            $this->bookingService->cancelAppointment($appointment->id, 'Cancelled by patient via WhatsApp');
            $this->whatsapp->sendText(
                $phone,
                "🗑️ تم إلغاء الموعد رقم #{$appointment->id} بنجاح.\nأرسل 0 للعودة للقائمة الرئيسية."
            );
        } catch (DomainException $e) {
            $this->whatsapp->sendText($phone, "تعذر إلغاء الموعد: {$e->getMessage()}");
        }

        $session->update([
            'current_step' => 'MAIN_MENU',
            'payload' => [],
        ]);
    }
}