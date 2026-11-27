@extends('layouts.app')

@section('title', 'المالية والدفعات')
@section('header', 'التقارير المالية والتحصيلات')

@section('content')
<div class="space-y-6">

    <!-- بطاقات الملخص المالي -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div class="bg-white p-5 rounded-xl border border-slate-200 shadow-sm">
            <p class="text-xs font-bold text-slate-500">إجمالي المبالغ المحصلة (حسب الفلتر)</p>
            <p class="text-2xl font-bold text-emerald-700 mt-1">{{ number_format((float) $totalRevenue, 2) }}</p>
        </div>

        <div class="bg-white p-5 rounded-xl border border-slate-200 shadow-sm">
            <p class="text-xs font-bold text-slate-500">الزيارات المدفوعة</p>
            <p class="text-2xl font-bold text-slate-800 mt-1">{{ $paidVisitsCount }}</p>
        </div>

        <div class="bg-white p-5 rounded-xl border border-slate-200 shadow-sm">
            <p class="text-xs font-bold text-slate-500">إجمالي الزيارات الفعالة</p>
            <p class="text-2xl font-bold text-teal-700 mt-1">{{ $totalVisitsCount }}</p>
        </div>
    </div>

    <!-- فلاتر المالية + زر التصدير -->
    <form method="GET" action="{{ route('finance.index') }}" class="bg-white p-4 rounded-xl border border-slate-200 shadow-sm">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
            <div>
                <label class="block text-xs font-bold text-slate-600 mb-1">الشهر</label>
                <select name="month" class="w-full px-3 py-2 rounded-lg border border-slate-300 text-sm bg-white">
                    <option value="all" @selected($month === 'all')>جميع الأشهر</option>
                    @for($m = 1; $m <= 12; $m++)
                        <option value="{{ $m }}" @selected((string)$month === (string)$m)>شهر {{ $m }}</option>
                    @endfor
                </select>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-600 mb-1">السنة</label>
                <select name="year" class="w-full px-3 py-2 rounded-lg border border-slate-300 text-sm bg-white">
                    <option value="all" @selected($year === 'all')>جميع السنوات</option>
                    @for($y = now()->year; $y >= now()->year - 3; $y--)
                        <option value="{{ $y }}" @selected((string)$year === (string)$y)>{{ $y }}</option>
                    @endfor
                </select>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-600 mb-1">التخصص</label>
                <select name="specialty_id" class="w-full px-3 py-2 rounded-lg border border-slate-300 text-sm bg-white">
                    <option value="">جميع التخصصات</option>
                    @foreach($specialties as $sp)
                        <option value="{{ $sp->id }}" @selected(request('specialty_id') == $sp->id)>{{ $sp->name }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-600 mb-1">الطبيب / القسم</label>
                <select name="provider_id" class="w-full px-3 py-2 rounded-lg border border-slate-300 text-sm bg-white">
                    <option value="">الكل</option>
                    @foreach($providers as $prov)
                        <option value="{{ $prov->id }}" @selected(request('provider_id') == $prov->id)>{{ $prov->name }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <div class="flex flex-wrap items-center justify-between gap-3 mt-4 pt-3 border-t border-slate-100">
            <span class="text-xs text-slate-500 flex items-center gap-1.5">
                <i data-lucide="eye-off" class="w-4 h-4 text-teal-700"></i>
                <span>بيانات المرضى معمّاة تلقائياً من الخادم لحماية الخصوصية.</span>
            </span>

            <div class="flex items-center gap-2">
                <a href="{{ route('finance.export', request()->query()) }}"
                   class="bg-emerald-700 hover:bg-emerald-800 text-white font-bold px-4 py-2 rounded-lg text-sm flex items-center gap-2 transition">
                    <i data-lucide="file-spreadsheet" class="w-4 h-4"></i>
                    <span>تصدير Excel</span>
                </a>

                <button type="submit"
                        class="bg-teal-700 hover:bg-teal-800 text-white font-bold px-5 py-2 rounded-lg text-sm transition cursor-pointer">
                    عرض التقرير
                </button>
            </div>
        </div>
    </form>

    <!-- جدول الدفعات -->
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-right border-collapse">
                <thead>
                    <tr class="bg-slate-50 border-b border-slate-200 text-xs font-bold text-slate-600">
                        <th class="py-3.5 px-4">رقم الموعد</th>
                        <th class="py-3.5 px-4">التاريخ</th>
                        <th class="py-3.5 px-4">المريض (معمّى)</th>
                        <th class="py-3.5 px-4">الهاتف (معمّى)</th>
                        <th class="py-3.5 px-4">التخصص والطبيب</th>
                        <th class="py-3.5 px-4">المبلغ المدفوع</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 text-sm">
                    @forelse($appointments as $apt)
                        <tr class="hover:bg-slate-50/70">
                            <td class="py-3 px-4 font-mono text-xs font-bold text-slate-600">#{{ $apt->id }}</td>
                            <td class="py-3 px-4 whitespace-nowrap">{{ \Carbon\Carbon::parse($apt->appointment_date)->format('Y-m-d') }}</td>
                            <td class="py-3 px-4 font-semibold text-slate-800">{{ $apt->masked_patient_name }}</td>
                            <td class="py-3 px-4 font-mono text-xs text-slate-600" dir="ltr">{{ $apt->masked_patient_phone }}</td>
                            <td class="py-3 px-4">
                                <span class="font-medium text-slate-800">{{ $apt->provider->name ?? '' }}</span>
                                <span class="text-xs text-slate-500">({{ $apt->provider->specialty->name ?? '' }})</span>
                            </td>
                            <td class="py-3 px-4 font-bold text-emerald-700">
                                {{ number_format((float) $apt->payment_amount, 2) }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="py-8 text-center text-slate-500">
                                لا توجد سجلات مالية مطابقة للفترة المحددة.
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