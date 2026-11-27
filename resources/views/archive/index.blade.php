@extends('layouts.app')

@section('title', 'الأرشيف - سجل المواعيد السابقة')
@section('header', 'الأرشيف (للقراءة فقط)')

@section('content')
<div class="space-y-6">

    <!-- بطاقة ملخص سريعة للنتائج المفلترة -->
    <div class="bg-white p-4 rounded-xl border border-slate-200 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-lg bg-slate-100 text-slate-700 flex items-center justify-center">
                <i data-lucide="archive" class="w-5 h-5"></i>
            </div>
            <div>
                <p class="text-xs text-slate-500 font-semibold">عدد السجلات المطابقة للبحث</p>
                <p class="text-lg font-bold text-slate-800">{{ $appointments->total() }} موعد</p>
            </div>
        </div>

        <div class="flex items-center gap-6">
            <div class="text-left sm:text-right">
                <p class="text-xs text-slate-500 font-semibold">إجمالي الدفعات في هذه النتائج</p>
                <p class="text-lg font-bold text-teal-700">{{ number_format((float) $totalAmount, 2) }}</p>
            </div>

            <!-- زر التصدير إلى Excel بنفس الفلاتر الحالية -->
            <a href="{{ route('archive.export', request()->query()) }}"
               class="bg-emerald-700 hover:bg-emerald-800 text-white font-bold px-4 py-2.5 rounded-lg text-sm flex items-center gap-2 transition">
                <i data-lucide="file-spreadsheet" class="w-4 h-4"></i>
                <span>تصدير Excel</span>
            </a>
        </div>
    </div>

    <!-- نموذج الفلترة والبحث -->
    <form method="GET" action="{{ route('archive.index') }}" class="bg-white p-4 rounded-xl border border-slate-200">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3">
            <div>
                <label class="block text-xs font-bold text-slate-600 mb-1">بحث بالمريض أو الهاتف</label>
                <input type="text" name="search" value="{{ request('search') }}" placeholder="الاسم أو الرقم..."
                       class="w-full px-3 py-2 rounded-lg border border-slate-300 text-sm focus:ring-2 focus:ring-teal-600 outline-none">
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-600 mb-1">الطبيب / القسم</label>
                <select name="provider_id" class="w-full px-3 py-2 rounded-lg border border-slate-300 text-sm bg-white focus:ring-2 focus:ring-teal-600 outline-none">
                    <option value="">الكل</option>
                    @foreach($providers as $provider)
                        <option value="{{ $provider->id }}" @selected(request('provider_id') == $provider->id)>
                            {{ $provider->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-600 mb-1">الحالة</label>
                <select name="status" class="w-full px-3 py-2 rounded-lg border border-slate-300 text-sm bg-white focus:ring-2 focus:ring-teal-600 outline-none">
                    <option value="">جميع الحالات</option>
                    <option value="attended" @selected(request('status') === 'attended')>حضر</option>
                    <option value="no_show" @selected(request('status') === 'no_show')>لم يحضر</option>
                    <option value="cancelled" @selected(request('status') === 'cancelled')>ملغي</option>
                    <option value="scheduled" @selected(request('status') === 'scheduled')>محجوز (منتهي)</option>
                </select>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-600 mb-1">من تاريخ</label>
                <input type="date" name="date_from" value="{{ request('date_from') }}"
                       class="w-full px-3 py-2 rounded-lg border border-slate-300 text-sm focus:ring-2 focus:ring-teal-600 outline-none">
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-600 mb-1">إلى تاريخ</label>
                <input type="date" name="date_to" value="{{ request('date_to') }}"
                       class="w-full px-3 py-2 rounded-lg border border-slate-300 text-sm focus:ring-2 focus:ring-teal-600 outline-none">
            </div>
        </div>

        <div class="flex items-center justify-end gap-2 mt-4 pt-3 border-t border-slate-100">
            @if(request()->hasAny(['search', 'provider_id', 'status', 'date_from', 'date_to']))
                <a href="{{ route('archive.index') }}" class="px-4 py-2 rounded-lg border border-slate-300 text-sm text-slate-600 hover:bg-slate-50">
                    إعادة تعيين
                </a>
            @endif
            <button type="submit" class="bg-teal-700 hover:bg-teal-800 text-white font-bold px-5 py-2 rounded-lg text-sm flex items-center gap-2 transition cursor-pointer">
                <i data-lucide="filter" class="w-4 h-4"></i>
                <span>تطبيق الفلتر</span>
            </button>
        </div>
    </form>

    <!-- جدول الأرشيف (للقراءة فقط) -->
    <div class="bg-white rounded-xl border border-slate-200 overflow-hidden shadow-sm">
        <div class="overflow-x-auto">
            <table class="w-full text-right border-collapse">
                <thead>
                    <tr class="bg-slate-50 border-b border-slate-200 text-xs font-bold text-slate-600">
                        <th class="py-3.5 px-4">#</th>
                        <th class="py-3.5 px-4">التاريخ والوقت</th>
                        <th class="py-3.5 px-4">المريض</th>
                        <th class="py-3.5 px-4">الطبيب / القسم</th>
                        <th class="py-3.5 px-4">الحالة</th>
                        <th class="py-3.5 px-4">الدفعة</th>
                        <th class="py-3.5 px-4">ملاحظات</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 text-sm">
                    @forelse($appointments as $apt)
                        <tr class="hover:bg-slate-50/70">
                            <td class="py-3 px-4 text-slate-400 font-mono text-xs">{{ $apt->id }}</td>
                            <td class="py-3 px-4 whitespace-nowrap">
                                <div class="font-bold text-slate-800">{{ \Carbon\Carbon::parse($apt->appointment_date)->format('Y-m-d') }}</div>
                                <div class="text-xs text-slate-500">{{ \Carbon\Carbon::parse($apt->start_time)->format('H:i') }}</div>
                            </td>
                            <td class="py-3 px-4">
                                <div class="font-semibold text-slate-800">{{ $apt->patient->last_registered_name ?? 'غير محدد' }}</div>
                                <div class="text-xs text-slate-500" dir="ltr">{{ $apt->patient->phone_number ?? '' }}</div>
                            </td>
                            <td class="py-3 px-4">
                                <div class="font-medium text-slate-800">{{ $apt->provider->name ?? 'غير محدد' }}</div>
                                <div class="text-xs text-slate-500">{{ $apt->provider->specialty->name ?? '' }}</div>
                            </td>
                            <td class="py-3 px-4 whitespace-nowrap">
                                @php
                                    $badge = match($apt->status) {
                                        'attended'  => ['bg-emerald-100 text-emerald-700', 'حضر'],
                                        'no_show'   => ['bg-amber-100 text-amber-700', 'لم يحضر'],
                                        'cancelled' => ['bg-red-100 text-red-700', 'ملغي'],
                                        default     => ['bg-slate-100 text-slate-700', 'منتهي'],
                                    };
                                @endphp
                                <span class="px-2.5 py-1 rounded-full text-xs font-bold {{ $badge[0] }}">
                                    {{ $badge[1] }}
                                </span>
                            </td>
                            <td class="py-3 px-4 font-bold text-slate-800 whitespace-nowrap">
                                {{ number_format((float) $apt->payment_amount, 2) }}
                            </td>
                            <td class="py-3 px-4 text-xs text-slate-500 max-w-xs truncate" title="{{ $apt->notes }}">
                                {{ $apt->notes ?: '—' }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="py-8 text-center text-slate-500">
                                لا توجد مواعيد مؤرشفة أو سابقة مطابقة للبحث.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($appointments->hasPages())
            <div class="p-4 border-t border-slate-100 bg-slate-50">
                {{ $appointments->links() }}
            </div>
        @endif
    </div>

</div>
@endsection