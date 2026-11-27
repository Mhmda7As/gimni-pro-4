<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#0f766e">
    <title>@yield('title', 'نظام إدارة العيادات')</title>

    <!-- PWA Manifest -->
    <link rel="manifest" href="{{ asset('manifest.json') }}">
    <link rel="apple-touch-icon" href="{{ asset('icons/icon-192.png') }}">

    <!-- الخط العربي Cairo -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap" rel="stylesheet">

    <!-- Tailwind CSS & Alpine.js & Lucide Icons -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <script src="https://unpkg.com/lucide@latest"></script>

    <style>
        body { font-family: 'Cairo', sans-serif; }
        [x-cloak] { display: none !important; }
    </style>
</head>
<body class="bg-slate-50 text-slate-800 antialiased" x-data="pwaApp()">

    <div class="min-h-screen flex flex-col md:flex-row">
        <!-- الشريط الجانبي (Sidebar) -->
        <aside class="w-full md:w-64 bg-teal-900 text-white flex flex-col justify-between shrink-0">
            <div>
                <!-- ترويسة العيادة -->
                <div class="p-5 border-b border-teal-800 flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <i data-lucide="stethoscope" class="w-7 h-7 text-teal-300"></i>
                        <div>
                            <h1 class="font-bold text-lg leading-tight">إدارة العيادات</h1>
                            <span class="text-xs text-teal-300">
                                {{ match(auth()->user()->role) {
                                    'admin' => 'مدير النظام',
                                    'secretary' => 'الاستقبال والسكرتارية',
                                    'accountant' => 'القسم المالي',
                                    default => ''
                                } }}
                            </span>
                        </div>
                    </div>
                </div>

                <!-- روابط التنقل حسب الدور -->
                <nav class="p-3 space-y-1">
                    @if(in_array(auth()->user()->role, ['admin', 'secretary']))
                        <a href="{{ route('desk.index') }}"
                           class="flex items-center gap-3 px-4 py-2.5 rounded-lg transition {{ request()->routeIs('desk.*') ? 'bg-teal-700 text-white font-semibold' : 'text-teal-100 hover:bg-teal-800' }}">
                            <i data-lucide="calendar-check" class="w-5 h-5"></i>
                            <span>اليومية (مواعيد اليوم)</span>
                        </a>

                        <a href="{{ route('archive.index') }}"
                           class="flex items-center gap-3 px-4 py-2.5 rounded-lg transition {{ request()->routeIs('archive.*') ? 'bg-teal-700 text-white font-semibold' : 'text-teal-100 hover:bg-teal-800' }}">
                            <i data-lucide="archive" class="w-5 h-5"></i>
                            <span>الأرشيف</span>
                        </a>

                        <a href="{{ route('setup.index') }}"
                           class="flex items-center gap-3 px-4 py-2.5 rounded-lg transition {{ request()->routeIs('setup.*') ? 'bg-teal-700 text-white font-semibold' : 'text-teal-100 hover:bg-teal-800' }}">
                            <i data-lucide="users" class="w-5 h-5"></i>
                            <span>التخصصات والموظفين</span>
                        </a>

                        <a href="{{ route('schedules.index') }}"
                           class="flex items-center gap-3 px-4 py-2.5 rounded-lg transition {{ request()->routeIs('schedules.*') ? 'bg-teal-700 text-white font-semibold' : 'text-teal-100 hover:bg-teal-800' }}">
                            <i data-lucide="clock" class="w-5 h-5"></i>
                            <span>جداول مواعيد الأطباء</span>
                        </a>
                    @endif

                    @if(in_array(auth()->user()->role, ['admin', 'accountant']))
                        <a href="{{ route('finance.index') }}"
                           class="flex items-center gap-3 px-4 py-2.5 rounded-lg transition {{ request()->routeIs('finance.*') ? 'bg-teal-700 text-white font-semibold' : 'text-teal-100 hover:bg-teal-800' }}">
                            <i data-lucide="banknote" class="w-5 h-5"></i>
                            <span>المالية والدفعات</span>
                        </a>
                    @endif

                    @if(auth()->user()->role === 'admin')
    <a href="{{ route('admin.index') }}"
       class="flex items-center gap-3 px-4 py-2.5 rounded-lg transition {{ request()->routeIs('admin.index') ? 'bg-teal-700 text-white font-semibold' : 'text-teal-100 hover:bg-teal-800' }}">
        <i data-lucide="shield-check" class="w-5 h-5"></i>
        <span>الإدارة والإعدادات</span>
    </a>

    <a href="{{ route('admin.audit-logs') }}"
       class="flex items-center gap-3 px-4 py-2.5 rounded-lg transition {{ request()->routeIs('admin.audit-logs') ? 'bg-teal-700 text-white font-semibold' : 'text-teal-100 hover:bg-teal-800' }}">
        <i data-lucide="history" class="w-5 h-5"></i>
        <span>سجل الحركات</span>
    </a>
@endif
                </nav>
            </div>

            <!-- أسفل القائمة: تثبيت التطبيق وتسجيل الخروج -->
            <div class="p-4 border-t border-teal-800 space-y-2">
                <button x-show="canInstall" x-cloak @click="installApp()"
                        class="w-full flex items-center justify-center gap-2 bg-amber-500 hover:bg-amber-600 text-slate-900 font-bold py-2 px-3 rounded-lg text-sm transition cursor-pointer">
                    <i data-lucide="download" class="w-4 h-4"></i>
                    <span>تثبيت التطبيق على الجهاز</span>
                </button>

                <div class="text-xs text-teal-200 px-1 truncate">
                    المستخدم: {{ auth()->user()->name }}
                </div>

                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit"
                            class="w-full flex items-center justify-center gap-2 bg-teal-950 hover:bg-red-700 text-teal-100 py-2 px-3 rounded-lg text-sm transition cursor-pointer">
                        <i data-lucide="log-out" class="w-4 h-4"></i>
                        <span>تسجيل الخروج</span>
                    </button>
                </form>
            </div>
        </aside>

        <!-- المحتوى الرئيسي -->
        <main class="flex-1 flex flex-col min-w-0">
            <header class="bg-white border-b border-slate-200 px-6 py-4 flex items-center justify-between">
                <h2 class="text-xl font-bold text-slate-800">@yield('header', 'لوحة التحكم')</h2>
                <span class="text-sm text-slate-500">{{ now()->format('Y-m-d') }}</span>
            </header>

            <div class="p-6 flex-1">
                @if(session('success'))
                    <div class="mb-4 p-4 rounded-lg bg-emerald-50 border border-emerald-200 text-emerald-800 text-sm">
                        {{ session('success') }}
                    </div>
                @endif

                @if($errors->any())
                    <div class="mb-4 p-4 rounded-lg bg-red-50 border border-red-200 text-red-800 text-sm">
                        <ul class="list-disc list-inside">
                            @foreach($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                @yield('content')
            </div>
        </main>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            lucide.createIcons();
        });

        // تسجيل Service Worker
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', () => {
                navigator.serviceWorker.register('/service-worker.js');
            });
        }

        // منطق تثبيت PWA عبر Alpine.js
        function pwaApp() {
            return {
                deferredPrompt: null,
                canInstall: false,
                init() {
                    window.addEventListener('beforeinstallprompt', (e) => {
                        e.preventDefault();
                        this.deferredPrompt = e;
                        this.canInstall = true;
                    });
                    window.addEventListener('appinstalled', () => {
                        this.canInstall = false;
                        this.deferredPrompt = null;
                    });
                },
                async installApp() {
                    if (!this.deferredPrompt) return;
                    this.deferredPrompt.prompt();
                    const { outcome } = await this.deferredPrompt.userChoice;
                    if (outcome === 'accepted') {
                        this.canInstall = false;
                    }
                    this.deferredPrompt = null;
                }
            }
        }
    </script>
    @stack('scripts')
</body>
</html>