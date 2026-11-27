@extends('layouts.app')

@section('title', 'الإدارة والإحصاء')
@section('header', 'لوحة المدير: الإحصاءات، الإعدادات، وحسابات الموظفين')

@section('content')
<div class="space-y-6">

    <!-- بطاقة تنبيه بارزة لعرض اسم المستخدم وكلمة المرور المولدة -->
    @if(session('generated_credentials'))
        @php $cred = session('generated_credentials'); @endphp
        <div class="bg-amber-50 border-2 border-amber-400 rounded-xl p-5 shadow-md">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <h4 class="font-bold text-amber-900 text-base flex items-center gap-2">
                        <i data-lucide="key-round" class="w-5 h-5 text-amber-600"></i>
                        <span>{{ $cred['title'] }} — انسخ البيانات وأعطها للموظف</span>
                    </h4>
                    <div class="mt-3 grid grid-cols-1 sm:grid-cols-3 gap-3 text-sm">
                        <div class="bg-white px-3 py-2 rounded-lg border border-amber-200">
                            <span class="text-xs text-slate-500 block">الموظف:</span>
                            <strong class="text-slate-800">{{ $cred['name'] }}</strong>
                        </div>
                        <div class="bg-white px-3 py-2 rounded-lg border border-amber-200">
                            <span class="text-xs text-slate-500 block">البريد الإلكتروني (اسم الدخول):</span>
                            <strong class="text-slate-800 font-mono" dir="ltr">{{ $cred['email'] }}</strong>
                        </div>
                        <div class="bg-white px-3 py-2 rounded-lg border border-amber-200">
                            <span class="text-xs text-slate-500 block">كلمة المرور الجديدة:</span>
                            <strong class="text-teal-700 font-mono text-base select-all" dir="ltr">{{ $cred['password'] }}</strong>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endif

    <!-- 1. بطاقات الإحصاءات العامة -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-white p-5 rounded-xl border border-slate-200 shadow-sm">
            <p class="text-xs font-bold text-slate-500">إجمالي المواعيد المسجلة</p>
            <p class="text-2xl font-bold text-slate-800 mt-1">{{ $totalVisits }}</p>
            <p class="text-xs text-slate-400 mt-1">ملغي: {{ $cancelledCount }}</p>
        </div>

        <div class="bg-white p-5 rounded-xl border border-slate-200 shadow-sm">
            <p class="text-xs font-bold text-slate-500">نسبة الحضور الفعلية</p>
            <p class="text-2xl font-bold text-emerald-600 mt-1">{{ $attendanceRate }}%</p>
            <p class="text-xs text-slate-400 mt-1">عدد الحضور: {{ $attendedCount }}</p>
        </div>

        <div class="bg-white p-5 rounded-xl border border-slate-200 shadow-sm">
            <p class="text-xs font-bold text-slate-500">نسبة الغياب (No-Show)</p>
            <p class="text-2xl font-bold text-amber-600 mt-1">{{ $noShowRate }}%</p>
            <p class="text-xs text-slate-400 mt-1">حالات الغياب: {{ $noShowCount }}</p>
        </div>

        <div class="bg-white p-5 rounded-xl border border-slate-200 shadow-sm">
            <p class="text-xs font-bold text-slate-500">إجمالي الإيرادات الكلية</p>
            <p class="text-2xl font-bold text-teal-700 mt-1">{{ number_format((float) $totalRevenue, 2) }}</p>
        </div>
    </div>

    <!-- 2. إدارة حسابات موظفي النظام (سكرتير، مالية، مدير) -->
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="p-5 border-b border-slate-200">
            <h3 class="font-bold text-base text-slate-800 mb-3 flex items-center gap-2">
                <i data-lucide="user-cog" class="w-5 h-5 text-teal-700"></i>
                <span>إضافة موظف نظام وتوليد بيانات دخول (سكرتير / مالية)</span>
            </h3>

            <form method="POST" action="{{ route('admin.staff.store') }}" class="grid grid-cols-1 sm:grid-cols-4 gap-3">
                @csrf
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">اسم الموظف</label>
                    <input type="text" name="name" required placeholder="مثال: سارة محمود"
                           class="w-full px-3 py-2 rounded-lg border border-slate-300 text-sm">
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">البريد الإلكتروني للدخول</label>
                    <input type="email" name="email" required placeholder="sara@clinic.com" dir="ltr"
                           class="w-full px-3 py-2 rounded-lg border border-slate-300 text-sm text-right">
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">الصلاحية / القسم</label>
                    <select name="role" required class="w-full px-3 py-2 rounded-lg border border-slate-300 text-sm bg-white">
                        <option value="secretary">سكرتير (الاستقبال والجدولة)</option>
                        <option value="accountant">مالية (المحاسب)</option>
                        <option value="admin">مدير نظام</option>
                    </select>
                </div>

                <div class="flex items-end">
                    <button type="submit"
                            class="w-full bg-teal-700 hover:bg-teal-800 text-white font-bold py-2 px-4 rounded-lg text-sm transition cursor-pointer">
                        إنشاء الحساب وتوليد كلمة مرور
                    </button>
                </div>
            </form>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-right border-collapse">
                <thead>
                    <tr class="bg-slate-50 border-b border-slate-200 text-xs font-bold text-slate-600">
                        <th class="py-3 px-4">اسم الموظف</th>
                        <th class="py-3 px-4">البريد الإلكتروني (اسم المستخدم)</th>
                        <th class="py-3 px-4">الدور</th>
                        <th class="py-3 px-4">إدارة كلمة المرور</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 text-sm">
                    @foreach($staffUsers as $staff)
                        <tr class="hover:bg-slate-50/70">
                            <td class="py-3 px-4 font-bold text-slate-800">{{ $staff->name }}</td>
                            <td class="py-3 px-4 font-mono text-xs text-slate-600" dir="ltr">{{ $staff->email }}</td>
                            <td class="py-3 px-4">
                                @php
                                    $roleBadge = match($staff->role) {
                                        'admin'      => ['bg-purple-100 text-purple-800', 'مدير النظام'],
                                        'secretary'  => ['bg-blue-100 text-blue-800', 'سكرتير'],
                                        'accountant' => ['bg-emerald-100 text-emerald-800', 'مالية'],
                                        default      => ['bg-slate-100 text-slate-700', $staff->role],
                                    };
                                @endphp
                                <span class="px-2.5 py-1 rounded-full text-xs font-bold {{ $roleBadge[0] }}">
                                    {{ $roleBadge[1] }}
                                </span>
                            </td>
                            <td class="py-3 px-4">
                                <form method="POST" action="{{ route('admin.staff.regenerate', $staff) }}"
                                      onsubmit="return confirm('هل تريد توليد كلمة مرور جديدة للموظف ({{ $staff->name }})؟ ستتوقف كلمة مروره القديمة فوراً.');">
                                    @csrf
                                    @method('PATCH')
                                    <button type="submit"
                                            class="px-3 py-1.5 rounded-lg bg-amber-100 hover:bg-amber-200 text-amber-900 font-bold text-xs flex items-center gap-1.5 transition cursor-pointer">
                                        <i data-lucide="refresh-cw" class="w-3.5 h-3.5"></i>
                                        <span>توليد كلمة مرور جديدة</span>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <!-- 3. لوحة الإعدادات العامة وزر الإيقاف العام (Kill Switch) -->
    <form method="POST" action="{{ route('admin.settings.update') }}" class="bg-white p-6 rounded-xl border border-slate-200 shadow-sm space-y-5">
        @csrf
        @method('PUT')

        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-4 border-b border-slate-100">
            <div>
                <h3 class="font-bold text-base text-slate-800">إعدادات العيادة وسياسات الحظر والحجز</h3>
                <p class="text-xs text-slate-500 mt-0.5">تنطبق هذه الإعدادات فوراً على بوت واتساب ولوحة الاستقبال.</p>
            </div>

            <label class="inline-flex items-center gap-3 px-4 py-2.5 rounded-xl border cursor-pointer {{ $settings['global_booking_enabled'] === '1' ? 'bg-emerald-50 border-emerald-200 text-emerald-900' : 'bg-red-50 border-red-200 text-red-900' }}">
                <input type="checkbox" name="global_booking_enabled" value="1"
                       @checked($settings['global_booking_enabled'] === '1')
                       class="w-4 h-4 rounded text-teal-600">
                <span class="text-sm font-bold">
                    {{ $settings['global_booking_enabled'] === '1' ? 'نظام الحجز العام: يعمل' : 'نظام الحجز العام: متوقف (Kill Switch)' }}
                </span>
            </label>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4">
            <div class="lg:col-span-2">
                <label class="block text-xs font-bold text-slate-700 mb-1">اسم المستوصف / العيادة</label>
                <input type="text" name="clinic_name" value="{{ $settings['clinic_name'] }}" required
                       class="w-full px-3 py-2 rounded-lg border border-slate-300 text-sm">
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">هاتف التواصل</label>
                <input type="text" name="clinic_phone" value="{{ $settings['clinic_phone'] }}" dir="ltr"
                       class="w-full px-3 py-2 rounded-lg border border-slate-300 text-sm text-right">
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">حد الغيابات للحظر التلقائي</label>
                <input type="number" name="auto_ban_no_show_limit" value="{{ $settings['auto_ban_no_show_limit'] }}" min="1" max="20" required
                       class="w-full px-3 py-2 rounded-lg border border-slate-300 text-sm">
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">مدة الحظر التلقائي (أيام)</label>
                <input type="number" name="auto_ban_days" value="{{ $settings['auto_ban_days'] }}" min="1" max="365" required
                       class="w-full px-3 py-2 rounded-lg border border-slate-300 text-sm">
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">فترة الانتظار بعد الإلغاء (دقائق)</label>
                <input type="number" name="cancellation_cooldown_minutes" value="{{ $settings['cancellation_cooldown_minutes'] }}" min="0" max="120" required
                       class="w-full px-3 py-2 rounded-lg border border-slate-300 text-sm">
            </div>
        </div>

        <div class="flex justify-end pt-2">
            <button type="submit"
                    class="bg-teal-700 hover:bg-teal-800 text-white font-bold px-6 py-2.5 rounded-lg text-sm transition cursor-pointer">
                حفظ الإعدادات
            </button>
        </div>
    </form>

</div>
@endsection