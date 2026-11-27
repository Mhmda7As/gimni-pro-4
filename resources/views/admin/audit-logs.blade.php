@extends('layouts.app')

@section('title', 'سجل الحركات')
@section('header', 'سجل حركات الموظفين والنظام (للقراءة فقط)')

@section('content')
<div class="space-y-6">

    <!-- فلاتر البحث في السجل -->
    <form method="GET" action="{{ route('admin.audit-logs') }}" class="bg-white p-4 rounded-xl border border-slate-200 shadow-sm">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
            <div>
                <label class="block text-xs font-bold text-slate-600 mb-1">الموظف / المصدر</label>
                <select name="user_id" class="w-full px-3 py-2 rounded-lg border border-slate-300 text-sm bg-white">
                    <option value="">الجميع</option>
                    <option value="system" @selected(request('user_id') === 'system')>النظام / بوت واتساب</option>
                    @foreach($users as $u)
                        <option value="{{ $u->id }}" @selected((string)request('user_id') === (string)$u->id)>
                            {{ $u->name }} ({{ $u->role }})
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-600 mb-1">نوع العملية</label>
                <select name="action" class="w-full px-3 py-2 rounded-lg border border-slate-300 text-sm bg-white">
                    <option value="">جميع العمليات</option>
                    @foreach($actions as $act)
                        <option value="{{ $act }}" @selected(request('action') === $act)>{{ $act }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-600 mb-1">من تاريخ</label>
                <input type="date" name="date_from" value="{{ request('date_from') }}"
                       class="w-full px-3 py-2 rounded-lg border border-slate-300 text-sm">
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-600 mb-1">إلى تاريخ</label>
                <input type="date" name="date_to" value="{{ request('date_to') }}"
                       class="w-full px-3 py-2 rounded-lg border border-slate-300 text-sm">
            </div>
        </div>

        <div class="flex items-center justify-between mt-4 pt-3 border-t border-slate-100">
            <span class="text-xs text-slate-500 flex items-center gap-1.5">
                <i data-lucide="lock" class="w-3.5 h-3.5 text-amber-600"></i>
                <span>هذا السجل محمي ضد التعديل أو الحذف لضمان الرقابة الكاملة.</span>
            </span>

            <div class="flex items-center gap-2">
                @if(request()->hasAny(['user_id', 'action', 'date_from', 'date_to']))
                    <a href="{{ route('admin.audit-logs') }}" class="px-4 py-2 rounded-lg border border-slate-300 text-sm text-slate-600 hover:bg-slate-50">
                        إعادة تعيين
                    </a>
                @endif
                <button type="submit" class="bg-teal-700 hover:bg-teal-800 text-white font-bold px-5 py-2 rounded-lg text-sm cursor-pointer">
                    تصفية السجل
                </button>
            </div>
        </div>
    </form>

    <!-- جدول الحركات -->
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="px-5 py-3.5 bg-slate-50 border-b border-slate-200 font-bold text-sm text-slate-700 flex items-center justify-between">
            <span>السجل الزمني للحركات</span>
            <span class="text-xs text-slate-500">إجمالي السجلات: {{ $logs->total() }}</span>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-right border-collapse">
                <thead>
                    <tr class="bg-slate-50/60 border-b border-slate-200 text-xs font-bold text-slate-600">
                        <th class="py-3 px-4">التاريخ والوقت</th>
                        <th class="py-3 px-4">الموظف</th>
                        <th class="py-3 px-4">العملية</th>
                        <th class="py-3 px-4">السجل المستهدف</th>
                        <th class="py-3 px-4">التفاصيل</th>
                        <th class="py-3 px-4">IP</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 text-xs">
                    @forelse($logs as $log)
                        <tr class="hover:bg-slate-50/70">
                            <td class="py-3 px-4 whitespace-nowrap font-mono text-slate-600" dir="ltr">
                                {{ $log->created_at?->format('Y-m-d H:i:s') }}
                            </td>
                            <td class="py-3 px-4 font-bold text-slate-800">
                                {{ $log->user->name ?? 'النظام / واتساب' }}
                            </td>
                            <td class="py-3 px-4">
                                <span class="px-2.5 py-1 rounded bg-teal-50 text-teal-800 font-mono font-semibold">
                                    {{ $log->action }}
                                </span>
                            </td>
                            <td class="py-3 px-4 font-mono text-slate-600">
                                {{ class_basename($log->model_type) }} #{{ $log->model_id }}
                            </td>
                            <td class="py-3 px-4 max-w-xs truncate text-slate-500 font-mono" dir="ltr" title="{{ json_encode($log->new_values, JSON_UNESCAPED_UNICODE) }}">
                                {{ $log->new_values ? \Illuminate\Support\Str::limit(json_encode($log->new_values, JSON_UNESCAPED_UNICODE), 60) : '—' }}
                            </td>
                            <td class="py-3 px-4 font-mono text-slate-400" dir="ltr">
                                {{ $log->ip_address ?? '—' }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="py-8 text-center text-slate-500">
                                لا توجد حركات مطابقة للفلاتر المحددة.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($logs->hasPages())
            <div class="p-4 border-t border-slate-100 bg-slate-50">
                {{ $logs->links() }}
            </div>
        @endif
    </div>

</div>
@endsection