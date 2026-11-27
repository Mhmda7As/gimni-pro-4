@extends('layouts.app')

@section('title', 'اليومية - مواعيد اليوم')
@section('header', 'اليومية (مواعيد اليوم فقط)')

@section('content')
<div x-data="dailyDesk(@js($appointments), @js($availableSlots))" class="space-y-6">

    <!-- بطاقات الإحصاء الفورية لليوم -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div class="bg-white p-4 rounded-xl border border-slate-200 flex items-center justify-between">
            <div>
                <p class="text-xs text-slate-500 font-semibold">إجمالي مواعيد اليوم</p>
                <p class="text-2xl font-bold text-slate-800 mt-1" x-text="items.length"></p>
            </div>
            <div class="w-10 h-10 rounded-lg bg-teal-50 text-teal-700 flex items-center justify-center">
                <i data-lucide="calendar" class="w-5 h-5"></i>
            </div>
        </div>

        <div class="bg-white p-4 rounded-xl border border-slate-200 flex items-center justify-between">
            <div>
                <p class="text-xs text-slate-500 font-semibold">الحالات الحاضرة</p>
                <p class="text-2xl font-bold text-emerald-600 mt-1" x-text="items.filter(i => i.status === 'attended').length"></p>
            </div>
            <div class="w-10 h-10 rounded-lg bg-emerald-50 text-emerald-600 flex items-center justify-center">
                <i data-lucide="user-check" class="w-5 h-5"></i>
            </div>
        </div>

        <div class="bg-white p-4 rounded-xl border border-slate-200 flex items-center justify-between">
            <div>
                <p class="text-xs text-slate-500 font-semibold">تحصيلات اليوم</p>
                <p class="text-2xl font-bold text-amber-600 mt-1" x-text="totalRevenue.toFixed(2)"></p>
            </div>
            <div class="w-10 h-10 rounded-lg bg-amber-50 text-amber-600 flex items-center justify-center">
                <i data-lucide="coins" class="w-5 h-5"></i>
            </div>
        </div>
    </div>

    <!-- شريط الأدوات: البحث + الفلترة + حجز يدوي سريع -->
    <div class="bg-white p-4 rounded-xl border border-slate-200 flex flex-col md:flex-row gap-3 items-stretch md:items-center justify-between">
        <div class="flex flex-col sm:flex-row gap-3 flex-1">
            <div class="relative flex-1 max-w-md">
                <input type="text" x-model="search" placeholder="بحث باسم المريض أو رقم الهاتف..."
                       class="w-full pl-4 pr-10 py-2 rounded-lg border border-slate-300 text-sm focus:ring-2 focus:ring-teal-600 outline-none">
                <i data-lucide="search" class="w-4 h-4 text-slate-400 absolute right-3 top-2.5"></i>
            </div>

            <select x-model="selectedProvider"
                    class="px-3 py-2 rounded-lg border border-slate-300 text-sm bg-white focus:ring-2 focus:ring-teal-600 outline-none">
                <option value="">جميع الأطباء والأقسام</option>
                @foreach($providers as $provider)
                    <option value="{{ $provider->id }}">{{ $provider->name }} ({{ $provider->specialty->name ?? '' }})</option>
                @endforeach
            </select>
        </div>

        <button @click="showQuickModal = true"
                class="bg-teal-700 hover:bg-teal-800 text-white font-bold px-4 py-2 rounded-lg text-sm flex items-center justify-center gap-2 transition cursor-pointer">
            <i data-lucide="plus-circle" class="w-4 h-4"></i>
            <span>حجز يدوي سريع</span>
        </button>
    </div>

    <!-- إشعار فوري للعمليات (Toast) -->
    <div x-show="toastMessage" x-cloak x-transition
         :class="toastError ? 'bg-red-600' : 'bg-slate-800'"
         class="fixed bottom-5 left-5 text-white px-4 py-3 rounded-xl shadow-lg text-sm z-50 flex items-center gap-2">
        <span x-text="toastMessage"></span>
    </div>

    <!-- جدول مواعيد اليوم -->
    <div class="bg-white rounded-xl border border-slate-200 overflow-hidden shadow-sm">
        <div class="overflow-x-auto">
            <table class="w-full text-right border-collapse">
                <thead>
                    <tr class="bg-slate-50 border-b border-slate-200 text-xs font-bold text-slate-600">
                        <th class="py-3.5 px-4">الوقت</th>
                        <th class="py-3.5 px-4">المريض</th>
                        <th class="py-3.5 px-4">الطبيب / القسم</th>
                        <th class="py-3.5 px-4">الحالة</th>
                        <th class="py-3.5 px-4">الدفعة</th>
                        <th class="py-3.5 px-4">إجراءات سريعة</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 text-sm">
                    <template x-for="item in filteredItems" :key="item.id">
                        <tr class="hover:bg-slate-50/80 transition">
                            <td class="py-3 px-4 font-bold text-slate-800 whitespace-nowrap" x-text="item.time"></td>

                            <td class="py-3 px-4">
                                <div class="font-semibold text-slate-800 flex items-center gap-2">
                                    <span x-text="item.patient_name"></span>
                                    <span x-show="item.is_banned" class="px-2 py-0.5 text-[11px] rounded-full bg-red-100 text-red-700 font-bold">
                                        محظور
                                    </span>
                                </div>
                                <div class="text-xs text-slate-500 mt-0.5" dir="ltr" x-text="item.patient_phone"></div>
                                <div x-show="item.no_show_count > 0" class="text-[11px] text-amber-600 mt-0.5">
                                    مرات الغياب: <span x-text="item.no_show_count"></span>
                                </div>
                            </td>

                            <td class="py-3 px-4">
                                <div class="font-medium text-slate-800" x-text="item.provider_name"></div>
                                <div class="text-xs text-slate-500" x-text="item.specialty_name"></div>
                            </td>

                            <td class="py-3 px-4 whitespace-nowrap">
                                <span class="px-2.5 py-1 rounded-full text-xs font-bold"
                                      :class="{
                                          'bg-blue-100 text-blue-700': item.status === 'scheduled',
                                          'bg-emerald-100 text-emerald-700': item.status === 'attended',
                                          'bg-amber-100 text-amber-700': item.status === 'no_show'
                                      }"
                                      x-text="statusLabel(item.status)">
                                </span>
                            </td>

                            <td class="py-3 px-4 whitespace-nowrap">
                                <div class="flex items-center gap-1.5">
                                    <input type="number" step="0.01" min="0" x-model.number="item.payment_amount"
                                           @keydown.enter="savePayment(item)"
                                           class="w-24 px-2.5 py-1 rounded border border-slate-300 text-sm focus:ring-2 focus:ring-teal-600 outline-none">
                                    <button @click="savePayment(item)" title="حفظ الدفعة"
                                            class="p-1.5 rounded bg-slate-100 hover:bg-teal-600 hover:text-white text-slate-600 transition cursor-pointer">
                                        <i data-lucide="check" class="w-4 h-4"></i>
                                    </button>
                                </div>
                            </td>

                            <td class="py-3 px-4 whitespace-nowrap">
                                <div class="flex items-center gap-1.5">
                                    <button @click="changeStatus(item, 'attended')"
                                            :disabled="item.status === 'attended'"
                                            class="px-2.5 py-1.5 rounded-lg text-xs font-bold bg-emerald-600 hover:bg-emerald-700 disabled:opacity-40 text-white transition cursor-pointer">
                                        حضور
                                    </button>

                                    <button @click="changeStatus(item, 'no_show')"
                                            :disabled="item.status === 'no_show'"
                                            class="px-2.5 py-1.5 rounded-lg text-xs font-bold bg-amber-500 hover:bg-amber-600 disabled:opacity-40 text-white transition cursor-pointer">
                                        غياب
                                    </button>

                                    <button @click="confirmCancel(item)"
                                            class="px-2.5 py-1.5 rounded-lg text-xs font-bold bg-red-600 hover:bg-red-700 text-white transition cursor-pointer">
                                        إلغاء
                                    </button>

                                    <button @click="openBanModal(item)"
                                            :class="item.is_banned ? 'bg-slate-700 text-white' : 'bg-slate-100 text-slate-700 hover:bg-slate-200'"
                                            class="px-2.5 py-1.5 rounded-lg text-xs font-bold transition cursor-pointer"
                                            x-text="item.is_banned ? 'فك الحظر' : 'حظر'">
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </template>

                    <tr x-show="filteredItems.length === 0">
                        <td colspan="6" class="py-8 text-center text-slate-500">
                            لا توجد مواعيد مطابقة لبحثك اليوم.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <!-- نافذة الحجز اليدوي السريع -->
    <div x-show="showQuickModal" x-cloak class="fixed inset-0 bg-slate-900/50 flex items-center justify-center p-4 z-50">
        <div @click.away="showQuickModal = false" class="bg-white rounded-2xl max-w-md w-full p-6 shadow-xl">
            <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                <h3 class="font-bold text-lg text-slate-800">إضافة حجز يدوي لليوم</h3>
                <button @click="showQuickModal = false" class="text-slate-400 hover:text-slate-600">✕</button>
            </div>

            <form method="POST" action="{{ route('desk.quick-book') }}" class="mt-4 space-y-4">
                @csrf
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">اسم المريض الكامل</label>
                    <input type="text" name="name" required class="w-full px-3 py-2 rounded-lg border border-slate-300 text-sm">
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">رقم الهاتف (مع رمز الدولة)</label>
                    <input type="text" name="phone" required placeholder="905XXXXXXXXX" dir="ltr"
                           class="w-full px-3 py-2 rounded-lg border border-slate-300 text-sm text-right">
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">الفترة المتاحة اليوم</label>
                    <select name="time_slot_id" required class="w-full px-3 py-2 rounded-lg border border-slate-300 text-sm bg-white">
                        <option value="">-- اختر الطبيب والوقت المتاح --</option>
                        <template x-for="slot in slots" :key="slot.id">
                            <option :value="slot.id" x-text="`${slot.time} — ${slot.provider_name}`"></option>
                        </template>
                    </select>
                </div>

                <div class="flex justify-end gap-2 pt-3">
                    <button type="button" @click="showQuickModal = false"
                            class="px-4 py-2 rounded-lg border border-slate-300 text-sm text-slate-600">إلغاء</button>
                    <button type="submit"
                            class="px-4 py-2 rounded-lg bg-teal-700 hover:bg-teal-800 text-white font-bold text-sm">تأكيد الحجز</button>
                </div>
            </form>
        </div>
    </div>

    <!-- نافذة حظر / فك حظر المريض -->
    <div x-show="banModal.open" x-cloak class="fixed inset-0 bg-slate-900/50 flex items-center justify-center p-4 z-50">
        <div @click.away="banModal.open = false" class="bg-white rounded-2xl max-w-sm w-full p-6 shadow-xl">
            <h3 class="font-bold text-lg text-slate-800" x-text="banModal.item?.is_banned ? 'فك الحظر عن المريض' : 'حظر المريض'"></h3>
            <p class="text-sm text-slate-600 mt-1" x-text="banModal.item?.patient_name"></p>

            <div x-show="!banModal.item?.is_banned" class="mt-4">
                <label class="block text-xs font-bold text-slate-700 mb-1">مدة الحظر (بالأيام)</label>
                <input type="number" x-model.number="banModal.days" min="1" max="365"
                       class="w-full px-3 py-2 rounded-lg border border-slate-300 text-sm">
            </div>

            <div class="flex justify-end gap-2 mt-6">
                <button @click="banModal.open = false" class="px-4 py-2 rounded-lg border border-slate-300 text-sm">إلغاء</button>
                <button @click="submitBan()"
                        :class="banModal.item?.is_banned ? 'bg-emerald-600 hover:bg-emerald-700' : 'bg-red-600 hover:bg-red-700'"
                        class="px-4 py-2 rounded-lg text-white font-bold text-sm"
                        x-text="banModal.item?.is_banned ? 'تأكيد فك الحظر' : 'تأكيد الحظر'">
                </button>
            </div>
        </div>
    </div>

</div>
@endsection

@push('scripts')
<script>
function dailyDesk(initialAppointments, availableSlots) {
    const appointmentsBaseUrl = "{{ url('/daily-desk/appointments') }}";
    const patientsBaseUrl = "{{ url('/daily-desk/patients') }}";

    return {
        items: initialAppointments,
        slots: availableSlots,
        search: '',
        selectedProvider: '',
        showQuickModal: false,
        toastMessage: '',
        toastError: false,
        banModal: { open: false, item: null, days: 30 },

        get filteredItems() {
            return this.items.filter(item => {
                const matchesProvider = !this.selectedProvider || String(item.provider_id) === String(this.selectedProvider);
                const q = this.search.trim().toLowerCase();
                const matchesSearch = !q || item.patient_name.toLowerCase().includes(q) || item.patient_phone.includes(q);
                return matchesProvider && matchesSearch;
            });
        },

        get totalRevenue() {
            return this.items.reduce((sum, i) => sum + (parseFloat(i.payment_amount) || 0), 0);
        },

        statusLabel(status) {
            return {
                scheduled: 'محجوز',
                attended: 'حضر',
                cancelled: 'ملغي',
                no_show: 'لم يحضر'
            }[status] || status;
        },

        notify(msg, isError = false) {
            this.toastMessage = msg;
            this.toastError = isError;
            setTimeout(() => { this.toastMessage = ''; }, 3500);
        },

        async parseJsonResponse(res) {
            const text = await res.text();
            try {
                return JSON.parse(text);
            } catch {
                throw new Error('استجابة غير متوقعة من الخادم (كود ' + res.status + ')');
            }
        },

        async changeStatus(item, newStatus) {
            try {
                const res = await fetch(`${appointmentsBaseUrl}/${item.id}/status`, {
                    method: 'PATCH',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({ status: newStatus })
                });
                const data = await this.parseJsonResponse(res);
                if (!res.ok) throw new Error(data.message || 'حدث خطأ');

                if (newStatus === 'cancelled') {
                    this.items = this.items.filter(row => row.id !== item.id);
                    this.notify('تم إلغاء الموعد وحذفه من اليومية ونقله للأرشيف.');
                } else {
                    item.status = data.status;
                    if (newStatus === 'no_show') {
                        item.no_show_count = data.no_show_count;
                        item.is_banned = data.is_banned;
                        item.banned_until = data.banned_until;
                    }
                    this.notify(data.message);
                }
            } catch (e) {
                this.notify(e.message, true);
            }
        },

        confirmCancel(item) {
            if (confirm(`هل أنت متأكد من إلغاء موعد المريض (${item.patient_name})؟ سيتم حذفه من اليومية ونقله إلى الأرشيف.`)) {
                this.changeStatus(item, 'cancelled');
            }
        },

        async savePayment(item) {
            try {
                const res = await fetch(`${appointmentsBaseUrl}/${item.id}/payment`, {
                    method: 'PATCH',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({ payment_amount: item.payment_amount })
                });
                const data = await this.parseJsonResponse(res);
                if (!res.ok) throw new Error(data.message || 'فشل حفظ الدفعة');
                item.payment_amount = data.payment_amount;
                this.notify(data.message);
            } catch (e) {
                this.notify(e.message, true);
            }
        },

        openBanModal(item) {
            this.banModal = { open: true, item: item, days: 30 };
        },

        async submitBan() {
            const item = this.banModal.item;
            const action = item.is_banned ? 'unban' : 'ban';
            try {
                const res = await fetch(`${patientsBaseUrl}/${item.patient_id}/ban`, {
                    method: 'PATCH',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({ action: action, ban_days: this.banModal.days })
                });
                const data = await this.parseJsonResponse(res);
                if (!res.ok) throw new Error(data.message || 'فشل تحديث الحظر');

                this.items.forEach(row => {
                    if (row.patient_id === item.patient_id) {
                        row.is_banned = data.is_banned;
                        row.banned_until = data.banned_until;
                    }
                });

                this.banModal.open = false;
                this.notify(data.message);
            } catch (e) {
                this.notify(e.message, true);
            }
        }
    }
}
</script>
@endpush