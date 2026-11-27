@extends('layouts.app')

@section('title', 'جداول مواعيد الأطباء')
@section('header', 'جداول الدوام والشبكة الأسبوعية للمواعيد')

@section('content')
<div x-data="scheduleBoard(@js($weekGrid))" class="space-y-6">

    <!-- 1. شريط اختيار الطبيب وزر التوليد الوحيد (الأسبوع القادم 7 أيام) -->
    <div class="bg-white p-5 rounded-xl border border-slate-200 shadow-sm flex flex-col lg:flex-row items-stretch lg:items-center justify-between gap-4">
        <form method="GET" action="{{ route('schedules.index') }}" class="flex items-center gap-3 flex-1">
            <label class="text-sm font-bold text-slate-700 shrink-0">الطبيب / القسم:</label>
            <select name="provider_id" onchange="this.form.submit()"
                    class="w-full max-w-md px-3.5 py-2.5 rounded-lg border border-slate-300 text-sm font-semibold bg-white focus:ring-2 focus:ring-teal-600 outline-none">
                @foreach($providers as $prov)
                    <option value="{{ $prov->id }}" @selected($selectedProvider?->id === $prov->id)>
                        {{ $prov->name }} — ({{ $prov->specialty->name ?? '' }})
                    </option>
                @endforeach
            </select>
        </form>

        @if($selectedProvider)
            <div class="flex flex-wrap items-center gap-3">
                <div class="px-3.5 py-2 rounded-lg bg-blue-50 border border-blue-200 text-xs font-bold text-blue-800">
                    المتاح هذا الأسبوع: <span class="text-sm">{{ $upcomingSlotsCount }}</span> فترة
                </div>

                <form method="POST" action="{{ route('schedules.generate', $selectedProvider) }}">
                    @csrf
                    <button type="submit"
                            class="bg-teal-700 hover:bg-teal-800 text-white font-bold px-5 py-2.5 rounded-lg text-sm flex items-center gap-2 transition cursor-pointer shadow-sm">
                        <i data-lucide="calendar-plus" class="w-4 h-4"></i>
                        <span>توليد مواعيد الأسبوع القادم (7 أيام)</span>
                    </button>
                </form>
            </div>
        @endif
    </div>

    @if(!$selectedProvider)
        <div class="bg-white p-8 rounded-xl border border-slate-200 text-center text-slate-500">
            يرجى إضافة طبيب أو قسم نشط أولاً من شاشة "التخصصات والخدمات الصحية".
        </div>
    @else
        <!-- 2. الشبكة الأسبوعية التفاعلية بالألوان الأربعة -->
        <section class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
            <div class="p-4 border-b border-slate-200 bg-slate-50/70 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                <div>
                    <h3 class="font-bold text-base text-slate-800">جدول الأسبوع القادم (انقر على أي خانة لتعديلها)</h3>
                    <p class="text-xs text-slate-500 mt-0.5">عرض تفصيلي لكل يوم وتقسيمات المواعيد المولدة.</p>
                </div>

                <!-- دليل الألوان (Legend) -->
                <div class="flex flex-wrap items-center gap-3 text-xs font-bold">
                    <span class="flex items-center gap-1.5 text-blue-800">
                        <span class="w-3 h-3 rounded bg-blue-500 inline-block"></span> متاح للحجز
                    </span>
                    <span class="flex items-center gap-1.5 text-emerald-800">
                        <span class="w-3 h-3 rounded bg-emerald-500 inline-block"></span> محجوز
                    </span>
                    <span class="flex items-center gap-1.5 text-red-800">
                        <span class="w-3 h-3 rounded bg-red-500 inline-block"></span> ملغي / انتظار
                    </span>
                    <span class="flex items-center gap-1.5 text-slate-600">
                        <span class="w-3 h-3 rounded bg-slate-400 inline-block"></span> عطلة / مغلق
                    </span>
                </div>
            </div>

            <div class="p-4 overflow-x-auto">
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-7 gap-3 min-w-[900px]">
                    <template x-for="day in grid" :key="day.date">
                        <div class="rounded-xl border flex flex-col overflow-hidden"
                             :class="day.is_today ? 'border-teal-500 ring-1 ring-teal-500' : 'border-slate-200'">

                            <!-- ترويسة العمود (اليوم والتاريخ) -->
                            <div class="p-3 text-center border-b"
                                 :class="day.is_day_off ? 'bg-slate-200 text-slate-700' : (day.is_today ? 'bg-teal-700 text-white' : 'bg-slate-100 text-slate-800')">
                                <div class="font-bold text-sm" x-text="day.day_name"></div>
                                <div class="text-xs font-mono mt-0.5 opacity-90" dir="ltr" x-text="day.date"></div>
                            </div>

                            <!-- محتوى العمود (خانات المواعيد أو العطلة) -->
                            <div class="p-2.5 flex-1 space-y-2 min-h-[260px]"
                                 :class="day.is_day_off ? 'bg-slate-100/80' : 'bg-white'">

                                <!-- حالة العطلة الكاملة أو عدم وجود مواعيد (رمادي) -->
                                <template x-if="day.is_day_off || day.slots.length === 0">
                                    <div class="h-full flex flex-col items-center justify-center text-center p-3 rounded-lg bg-slate-200/70 text-slate-600 border border-dashed border-slate-300">
                                        <i data-lucide="moon" class="w-5 h-5 mb-1 text-slate-500"></i>
                                        <span class="text-xs font-bold" x-text="day.is_day_off ? 'عطلة / إجازة' : 'لا توجد فترات مولّدة'"></span>
                                    </div>
                                </template>

                                <!-- قائمة الفترات الزمنية داخل العمود -->
                                <template x-for="slot in day.slots" :key="slot.id">
                                    <button type="button" @click="openSlotModal(slot)"
                                            class="w-full text-right p-2 rounded-lg border transition cursor-pointer shadow-2xs flex flex-col gap-0.5"
                                            :class="{
                                                'bg-blue-50 border-blue-300 text-blue-900 hover:bg-blue-100': slot.visual_state === 'available',
                                                'bg-emerald-50 border-emerald-400 text-emerald-950 hover:bg-emerald-100': slot.visual_state === 'booked',
                                                'bg-red-50 border-red-300 text-red-900 hover:bg-red-100': slot.visual_state === 'cancelled',
                                                'bg-slate-200/80 border-slate-300 text-slate-600 hover:bg-slate-200': slot.visual_state === 'closed'
                                            }">
                                        <div class="flex items-center justify-between w-full">
                                            <span class="font-mono font-bold text-xs" dir="ltr" x-text="slot.start_time"></span>
                                            <span class="text-[10px] font-bold px-1.5 py-0.2 rounded"
                                                  :class="{
                                                      'bg-blue-200/70 text-blue-900': slot.visual_state === 'available',
                                                      'bg-emerald-200/80 text-emerald-900': slot.visual_state === 'booked',
                                                      'bg-red-200/70 text-red-900': slot.visual_state === 'cancelled',
                                                      'bg-slate-300 text-slate-700': slot.visual_state === 'closed'
                                                  }"
                                                  x-text="stateLabel(slot.visual_state)">
                                            </span>
                                        </div>

                                        <!-- اسم المريض إن كان محجوزاً -->
                                        <template x-if="slot.visual_state === 'booked' && slot.appointment">
                                            <span class="text-[11px] font-semibold truncate w-full text-emerald-800" x-text="slot.appointment.patient_name"></span>
                                        </template>

                                        <!-- وقت انتهاء الانتظار إن كان ملغى -->
                                        <template x-if="slot.visual_state === 'cancelled'">
                                            <span class="text-[10px] truncate w-full text-red-700"
                                                  x-text="slot.available_at ? 'يتاح: ' + slot.available_at : 'ملغي'"></span>
                                        </template>
                                    </button>
                                </template>
                            </div>
                        </div>
                    </template>
                </div>
            </div>
        </section>

        <!-- 3. قسم إعداد القواعد الأسبوعية الثابتة والاستثناءات -->
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">

            <!-- الجدول الأسبوعي الثابت (7 أعمدة) -->
            <div class="lg:col-span-7 space-y-4">
                <div class="bg-white p-5 rounded-xl border border-slate-200 shadow-sm">
                    <h3 class="font-bold text-base text-slate-800 mb-3 flex items-center gap-2">
                        <i data-lucide="repeat" class="w-5 h-5 text-teal-700"></i>
                        <span>إعداد أيام الدوام الأسبوعي الثابتة</span>
                    </h3>

                    <form method="POST" action="{{ route('schedules.weekly.store', $selectedProvider) }}" class="grid grid-cols-1 sm:grid-cols-4 gap-3">
                        @csrf
                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-1">اليوم</label>
                            <select name="day_of_week" required class="w-full px-3 py-2 rounded-lg border border-slate-300 text-sm bg-white">
                                @foreach($daysMap as $num => $dayName)
                                    <option value="{{ $num }}">{{ $dayName }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-1">من الساعة</label>
                            <input type="time" name="start_time" value="09:00" required class="w-full px-3 py-2 rounded-lg border border-slate-300 text-sm">
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-1">إلى الساعة</label>
                            <input type="time" name="end_time" value="17:00" required class="w-full px-3 py-2 rounded-lg border border-slate-300 text-sm">
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-1">مدة الموعد</label>
                            <select name="slot_duration_minutes" required class="w-full px-3 py-2 rounded-lg border border-slate-300 text-sm bg-white">
                                <option value="15">15 دقيقة</option>
                                <option value="20">20 دقيقة</option>
                                <option value="30" selected>30 دقيقة (نصف ساعة)</option>
                                <option value="45">45 دقيقة</option>
                                <option value="60">60 دقيقة (ساعة)</option>
                            </select>
                        </div>

                        <div class="sm:col-span-4 flex justify-end pt-1">
                            <button type="submit" class="bg-teal-700 hover:bg-teal-800 text-white font-bold px-5 py-2 rounded-lg text-sm transition cursor-pointer">
                                إضافة للدوام الثابت
                            </button>
                        </div>
                    </form>
                </div>

                <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
                    <div class="px-5 py-3 bg-slate-50 border-b border-slate-200 font-bold text-sm text-slate-700">
                        أيام الدوام الأسبوعي الثابتة
                    </div>
                    <table class="w-full text-right border-collapse">
                        <thead>
                            <tr class="bg-slate-50/60 border-b border-slate-200 text-xs font-bold text-slate-600">
                                <th class="py-2.5 px-4">اليوم</th>
                                <th class="py-2.5 px-4">الفترة</th>
                                <th class="py-2.5 px-4">المدة</th>
                                <th class="py-2.5 px-4">حذف</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 text-sm">
                            @forelse($weeklySchedules as $sch)
                                <tr>
                                    <td class="py-2.5 px-4 font-bold text-slate-800">{{ $daysMap[$sch->day_of_week] ?? $sch->day_of_week }}</td>
                                    <td class="py-2.5 px-4 font-mono text-xs" dir="ltr">
                                        {{ \Carbon\Carbon::parse($sch->start_time)->format('H:i') }} — {{ \Carbon\Carbon::parse($sch->end_time)->format('H:i') }}
                                    </td>
                                    <td class="py-2.5 px-4 text-xs font-bold text-teal-700">{{ $sch->slot_duration_minutes }} د</td>
                                    <td class="py-2.5 px-4">
                                        <form method="POST" action="{{ route('schedules.destroy', $sch) }}" onsubmit="return confirm('حذف هذا اليوم؟');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="text-red-600 hover:text-red-800 text-xs font-bold cursor-pointer">حذف</button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="py-5 text-center text-slate-500 text-xs">لا توجد أيام ثابتة مسجلة.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- الاستثناءات والإجازات (5 أعمدة) -->
            <div class="lg:col-span-5 space-y-4" x-data="{ exType: 'day_off' }">
                <div class="bg-white p-5 rounded-xl border border-slate-200 shadow-sm">
                    <h3 class="font-bold text-base text-slate-800 mb-3 flex items-center gap-2">
                        <i data-lucide="calendar-off" class="w-5 h-5 text-slate-600"></i>
                        <span>إضافة عطلة أو دوام استثنائي ليوم محدد</span>
                    </h3>

                    <form method="POST" action="{{ route('schedules.exception.store', $selectedProvider) }}" class="space-y-3">
                        @csrf
                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-1">التاريخ</label>
                            <input type="date" name="exception_date" min="{{ now()->format('Y-m-d') }}" required
                                   class="w-full px-3 py-2 rounded-lg border border-slate-300 text-sm">
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-1">نوع الاستثناء</label>
                            <select name="exception_type" x-model="exType" class="w-full px-3 py-2 rounded-lg border border-slate-300 text-sm bg-white">
                                <option value="day_off">عطلة كاملة (رمادي - إغلاق اليوم)</option>
                                <option value="custom_shift">دوام استثنائي بساعات مخصصة</option>
                            </select>
                        </div>

                        <div x-show="exType === 'custom_shift'" x-cloak class="grid grid-cols-3 gap-2 pt-1">
                            <div>
                                <label class="block text-xs font-bold text-slate-700 mb-1">من</label>
                                <input type="time" name="start_time" value="10:00" class="w-full px-2 py-2 rounded-lg border border-slate-300 text-sm">
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-slate-700 mb-1">إلى</label>
                                <input type="time" name="end_time" value="14:00" class="w-full px-2 py-2 rounded-lg border border-slate-300 text-sm">
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-slate-700 mb-1">المدة</label>
                                <select name="slot_duration_minutes" class="w-full px-2 py-2 rounded-lg border border-slate-300 text-sm bg-white">
                                    <option value="15">15 د</option>
                                    <option value="30" selected>30 د</option>
                                    <option value="60">60 د</option>
                                </select>
                            </div>
                        </div>

                        <div class="pt-1 flex justify-end">
                            <button type="submit" class="bg-slate-700 hover:bg-slate-800 text-white font-bold px-4 py-2 rounded-lg text-sm transition cursor-pointer">
                                حفظ الاستثناء
                            </button>
                        </div>
                    </form>
                </div>

                <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
                    <div class="px-5 py-3 bg-slate-50 border-b border-slate-200 font-bold text-sm text-slate-700">
                        الاستثناءات والعطل المسجلة
                    </div>
                    <div class="divide-y divide-slate-100">
                        @forelse($exceptions as $ex)
                            <div class="p-3.5 flex items-center justify-between gap-2">
                                <div>
                                    <div class="font-bold text-slate-800 text-xs">{{ $ex->exception_date->format('Y-m-d') }}</div>
                                    <span class="inline-block mt-1 px-2 py-0.5 rounded text-[11px] font-bold {{ $ex->start_time === $ex->end_time ? 'bg-slate-200 text-slate-700' : 'bg-blue-100 text-blue-700' }}">
                                        {{ $ex->start_time === $ex->end_time ? 'عطلة كاملة' : 'دوام استثنائي' }}
                                    </span>
                                </div>
                                <form method="POST" action="{{ route('schedules.destroy', $ex) }}">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-red-600 hover:text-red-800 text-xs font-bold cursor-pointer">حذف</button>
                                </form>
                            </div>
                        @empty
                            <div class="p-5 text-center text-xs text-slate-500">لا توجد استثناءات مسجلة.</div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    @endif

    <!-- نافذة تعديل الخانة عند النقر عليها في الشبكة -->
    <div x-show="modal.open" x-cloak class="fixed inset-0 bg-slate-900/50 flex items-center justify-center p-4 z-50">
        <div @click.away="modal.open = false" class="bg-white rounded-2xl max-w-sm w-full p-6 shadow-xl space-y-4">
            <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                <div>
                    <h4 class="font-bold text-base text-slate-800">تفاصيل وتعديل الفترة الزمنية</h4>
                    <p class="text-xs text-slate-500 font-mono mt-0.5" dir="ltr"
                       x-text="`${modal.slot?.date} | ${modal.slot?.start_time} - ${modal.slot?.end_time}`"></p>
                </div>
                <button @click="modal.open = false" class="text-slate-400 hover:text-slate-600">✕</button>
            </div>

            <!-- تفاصيل المريض إن كانت الخانة محجوزة (أخضر) -->
            <template x-if="modal.slot?.visual_state === 'booked' && modal.slot?.appointment">
                <div class="p-3.5 rounded-xl bg-emerald-50 border border-emerald-200 space-y-1 text-sm">
                    <div class="text-xs text-emerald-700 font-bold">محجوز للمريض:</div>
                    <div class="font-bold text-slate-800" x-text="modal.slot.appointment.patient_name"></div>
                    <div class="text-xs text-slate-600 font-mono" dir="ltr" x-text="modal.slot.appointment.patient_phone"></div>
                </div>
            </template>

            <!-- أزرار التحكم حسب حالة الخانة -->
            <div class="space-y-2 pt-1">
                <!-- إذا كانت متاحة (أزرق) -> خيار إغلاقها (تحويل لعطلة) -->
                <template x-if="modal.slot?.visual_state === 'available'">
                    <button @click="executeSlotAction('close_slot')"
                            class="w-full py-2.5 px-4 rounded-lg bg-slate-700 hover:bg-slate-800 text-white font-bold text-sm transition cursor-pointer">
                        إغلاق هذه الفترة (تحويل لعطلة / مغلق)
                    </button>
                </template>

                <!-- إذا كانت محجوزة (أخضر) -> خيار إلغاء الحجز -->
                <template x-if="modal.slot?.visual_state === 'booked'">
                    <button @click="executeSlotAction('cancel_booking')"
                            class="w-full py-2.5 px-4 rounded-lg bg-red-600 hover:bg-red-700 text-white font-bold text-sm transition cursor-pointer">
                        إلغاء هذا الحجز
                    </button>
                </template>

                <!-- إذا كانت ملغاة / انتظار (أحمر) -> خيار إعادة إتاحتها فوراً أو إغلاقها -->
                <template x-if="modal.slot?.visual_state === 'cancelled'">
                    <div class="space-y-2">
                        <button @click="executeSlotAction('make_available')"
                                class="w-full py-2.5 px-4 rounded-lg bg-blue-600 hover:bg-blue-700 text-white font-bold text-sm transition cursor-pointer">
                            إعادة إتاحة الفترة للحجز فوراً
                        </button>
                        <button @click="executeSlotAction('close_slot')"
                                class="w-full py-2.5 px-4 rounded-lg bg-slate-200 hover:bg-slate-300 text-slate-800 font-bold text-sm transition cursor-pointer">
                            إغلاق الفترة نهائياً
                        </button>
                    </div>
                </template>

                <!-- إذا كانت مغلقة (رمادي) -> خيار إعادة فتحها -->
                <template x-if="modal.slot?.visual_state === 'closed'">
                    <button @click="executeSlotAction('make_available')"
                            class="w-full py-2.5 px-4 rounded-lg bg-blue-600 hover:bg-blue-700 text-white font-bold text-sm transition cursor-pointer">
                        إعادة فتح الفترة وجعلها متاحة للحجز
                    </button>
                </template>
            </div>
        </div>
    </div>

</div>
@endsection

@push('scripts')
<script>
function scheduleBoard(initialGrid) {
    return {
        grid: initialGrid,
        modal: { open: false, slot: null },

        stateLabel(state) {
            return {
                available: 'متاح',
                booked: 'محجوز',
                cancelled: 'ملغي',
                closed: 'مغلق'
            }[state] || state;
        },

        openSlotModal(slot) {
            this.modal = { open: true, slot: slot };
        },

        async executeSlotAction(action) {
            if (!this.modal.slot) return;
            try {
                const baseUrl = "{{ url('/schedules/slots') }}";
                const res = await fetch(`${baseUrl}/${this.modal.slot.id}`, {
                    method: 'PATCH',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({ action: action })
                });

                const text = await res.text();
                let data;
                try {
                    data = JSON.parse(text);
                } catch {
                    throw new Error('استجابة غير متوقعة من الخادم (كود ' + res.status + ')');
                }

                if (!res.ok) throw new Error(data.message || 'حدث خطأ أثناء التحديث');

                window.location.reload();
            } catch (e) {
                alert(e.message);
            }
        }
    }
}
</script>
@endpush