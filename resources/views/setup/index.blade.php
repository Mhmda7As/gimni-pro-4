@extends('layouts.app')

@section('title', 'التخصصات والخدمات الصحية')
@section('header', 'إعدادات العيادة: التخصصات والكادر الصحي')

@section('content')
<div class="space-y-8">

    <!-- ===================================================================== -->
    <!-- القسم الأول (في الأعلى): إدارة التخصصات الطبية                         -->
    <!-- ===================================================================== -->
    <section class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="p-5 border-b border-slate-200 bg-slate-50/70 flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div>
                <h3 class="font-bold text-base text-slate-800 flex items-center gap-2">
                    <i data-lucide="layers" class="w-5 h-5 text-teal-700"></i>
                    <span>1. التخصصات الطبية والأقسام ({{ $specialties->count() }})</span>
                </h3>
                <p class="text-xs text-slate-500 mt-0.5">أضف التخصصات أو أوقف الحجز مؤقتاً عن أي تخصص.</p>
            </div>

            <!-- نموذج إضافة تخصص جديد -->
            <form method="POST" action="{{ route('setup.specialties.store') }}" class="flex gap-2 w-full md:w-auto">
                @csrf
                <input type="text" name="name" required placeholder="اسم التخصص الجديد (مثال: الباطنية، الأسنان...)"
                       class="flex-1 md:w-72 px-3.5 py-2 rounded-lg border border-slate-300 text-sm bg-white focus:ring-2 focus:ring-teal-600 outline-none">
                <button type="submit"
                        class="bg-teal-700 hover:bg-teal-800 text-white font-bold px-5 py-2 rounded-lg text-sm transition cursor-pointer shrink-0">
                    + إضافة تخصص
                </button>
            </form>
        </div>

        <div class="p-5">
            @if($specialties->isEmpty())
                <p class="text-center text-sm text-slate-500 py-4">لا توجد تخصصات مضافة بعد.</p>
            @else
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                    @foreach($specialties as $specialty)
                        <div class="p-4 rounded-xl border border-slate-200 bg-slate-50/40 flex items-center justify-between gap-3">
                            <div>
                                <div class="font-bold text-slate-800 text-sm">{{ $specialty->name }}</div>
                                <div class="text-xs text-slate-500 mt-0.5">
                                    الكادر المرتبط: <strong class="text-slate-700">{{ $specialty->providers_count }}</strong>
                                </div>
                            </div>

                            <form method="POST" action="{{ route('setup.specialties.toggle', $specialty) }}">
                                @csrf
                                @method('PATCH')
                                <button type="submit"
                                        class="px-3 py-1.5 rounded-lg text-xs font-bold transition cursor-pointer flex items-center gap-1.5 {{ $specialty->is_booking_enabled ? 'bg-emerald-100 text-emerald-800 hover:bg-emerald-200' : 'bg-red-100 text-red-700 hover:bg-red-200' }}">
                                    <span class="w-2 h-2 rounded-full {{ $specialty->is_booking_enabled ? 'bg-emerald-600' : 'bg-red-600' }}"></span>
                                    <span>{{ $specialty->is_booking_enabled ? 'الحجز مفعّل' : 'الحجز متوقف' }}</span>
                                </button>
                            </form>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </section>

    <!-- ===================================================================== -->
    <!-- القسم الثاني (في الأسفل): الكادر الصحي (أطباء، أشعة، مخبر)           -->
    <!-- ===================================================================== -->
    <section class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="p-5 border-b border-slate-200 bg-slate-50/70">
            <h3 class="font-bold text-base text-slate-800 flex items-center gap-2">
                <i data-lucide="stethoscope" class="w-5 h-5 text-teal-700"></i>
                <span>2. مقدمو الخدمات الصحية (أطباء / أشعة / مخبر)</span>
            </h3>
            <p class="text-xs text-slate-500 mt-0.5">
                تُستخدم هذه البيانات لتنظيم الحجوزات وجداول المواعيد (دون إنشاء حسابات دخول).
            </p>

            <!-- نموذج إضافة مقدم خدمة صحية -->
            <form method="POST" action="{{ route('setup.providers.store') }}" class="mt-4 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3">
                @csrf
                <div class="lg:col-span-2">
                    <label class="block text-xs font-bold text-slate-700 mb-1">اسم الطبيب أو القسم</label>
                    <input type="text" name="name" required placeholder="مثال: د. أحمد العلي / قسم الأشعة..."
                           class="w-full px-3 py-2 rounded-lg border border-slate-300 text-sm bg-white focus:ring-2 focus:ring-teal-600 outline-none">
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">التصنيف</label>
                    <select name="type" required class="w-full px-3 py-2 rounded-lg border border-slate-300 text-sm bg-white focus:ring-2 focus:ring-teal-600 outline-none">
                        <option value="doctor">طبيب</option>
                        <option value="radiology">أشعة</option>
                        <option value="lab">مخبر</option>
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">التخصص</label>
                    <select name="specialty_id" required class="w-full px-3 py-2 rounded-lg border border-slate-300 text-sm bg-white focus:ring-2 focus:ring-teal-600 outline-none">
                        <option value="">-- اختر التخصص --</option>
                        @foreach($specialties as $specialty)
                            <option value="{{ $specialty->id }}">{{ $specialty->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="flex items-end">
                    <button type="submit"
                            class="w-full bg-teal-700 hover:bg-teal-800 text-white font-bold py-2 px-4 rounded-lg text-sm transition cursor-pointer">
                        + إضافة للنظام
                    </button>
                </div>

                <div class="sm:col-span-2 lg:col-span-5">
                    <label class="inline-flex items-center gap-2 text-xs text-slate-700 cursor-pointer">
                        <input type="checkbox" name="is_recurring" value="1" checked class="rounded text-teal-600 focus:ring-teal-500">
                        <span class="font-semibold">تفعيل التكرار الأسبوعي التلقائي لجدول مواعيده</span>
                    </label>
                </div>
            </form>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-right border-collapse">
                <thead>
                    <tr class="bg-slate-50 border-b border-slate-200 text-xs font-bold text-slate-600">
                        <th class="py-3.5 px-4">الاسم</th>
                        <th class="py-3.5 px-4">التصنيف</th>
                        <th class="py-3.5 px-4">التخصص</th>
                        <th class="py-3.5 px-4">التكرار الأسبوعي</th>
                        <th class="py-3.5 px-4">الحالة في الحجز</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 text-sm">
                    @forelse($providers as $provider)
                        <tr class="hover:bg-slate-50/70">
                            <td class="py-3 px-4 font-bold text-slate-800">{{ $provider->name }}</td>
                            <td class="py-3 px-4">
                                @php
                                    $typeLabel = match($provider->type) {
                                        'doctor'    => 'طبيب',
                                        'radiology' => 'أشعة',
                                        'lab'       => 'مخبر',
                                        default     => $provider->type,
                                    };
                                @endphp
                                <span class="text-xs font-bold text-teal-800 bg-teal-50 px-2.5 py-1 rounded">
                                    {{ $typeLabel }}
                                </span>
                            </td>
                            <td class="py-3 px-4 text-slate-600">{{ $provider->specialty->name ?? '—' }}</td>
                            <td class="py-3 px-4">
                                <form method="POST" action="{{ route('setup.providers.recurring', $provider) }}">
                                    @csrf
                                    @method('PATCH')
                                    <button type="submit"
                                            class="px-3 py-1 rounded-lg text-xs font-bold transition cursor-pointer {{ $provider->is_recurring ? 'bg-blue-100 text-blue-700 hover:bg-blue-200' : 'bg-slate-100 text-slate-600 hover:bg-slate-200' }}">
                                        {{ $provider->is_recurring ? 'متكرر أسبوعياً' : 'يدوي (غير متكرر)' }}
                                    </button>
                                </form>
                            </td>
                            <td class="py-3 px-4">
                                <form method="POST" action="{{ route('setup.providers.active', $provider) }}">
                                    @csrf
                                    @method('PATCH')
                                    <button type="submit"
                                            class="px-3 py-1 rounded-lg text-xs font-bold transition cursor-pointer {{ $provider->is_active ? 'bg-emerald-100 text-emerald-700 hover:bg-emerald-200' : 'bg-red-100 text-red-700 hover:bg-red-200' }}">
                                        {{ $provider->is_active ? 'نشط ومتاح' : 'موقوف' }}
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="py-8 text-center text-slate-500">
                                لا يوجد أطباء أو أقسام تشخيصية مسجلة بعد.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

</div>
@endsection