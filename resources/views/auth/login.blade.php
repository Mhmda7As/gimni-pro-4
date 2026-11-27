<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#0f766e">
    <title>تسجيل الدخول - نظام إدارة العيادات</title>
    <link rel="manifest" href="{{ asset('manifest.json') }}">
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>body { font-family: 'Cairo', sans-serif; }</style>
</head>
<body class="bg-slate-100 min-h-screen flex items-center justify-center p-4">

    <div class="w-full max-w-md bg-white rounded-2xl shadow-lg border border-slate-200 p-8">
        <div class="text-center mb-8">
            <div class="w-14 h-14 bg-teal-100 text-teal-700 rounded-xl flex items-center justify-center mx-auto mb-3 font-bold text-2xl">
                +
            </div>
            <h1 class="text-2xl font-bold text-slate-800">نظام إدارة العيادات</h1>
            <p class="text-sm text-slate-500 mt-1">سجّل الدخول للوصول إلى لوحة التحكم</p>
        </div>

        @if($errors->any())
            <div class="mb-4 p-3 rounded-lg bg-red-50 border border-red-200 text-red-700 text-sm">
                {{ $errors->first() }}
            </div>
        @endif

        <form method="POST" action="{{ route('login.attempt') }}" class="space-y-5">
            @csrf
            <div>
                <label for="email" class="block text-sm font-semibold text-slate-700 mb-1">البريد الإلكتروني</label>
                <input type="email" id="email" name="email" value="{{ old('email') }}" required autofocus
                       class="w-full px-4 py-2.5 rounded-lg border border-slate-300 focus:ring-2 focus:ring-teal-600 focus:border-teal-600 outline-none"
                       placeholder="name@clinic.com">
            </div>

            <div>
                <label for="password" class="block text-sm font-semibold text-slate-700 mb-1">كلمة المرور</label>
                <input type="password" id="password" name="password" required
                       class="w-full px-4 py-2.5 rounded-lg border border-slate-300 focus:ring-2 focus:ring-teal-600 focus:border-teal-600 outline-none"
                       placeholder="••••••••">
            </div>

            <div class="flex items-center justify-between">
                <label class="inline-flex items-center gap-2 text-sm text-slate-600 cursor-pointer">
                    <input type="checkbox" name="remember" class="rounded text-teal-600 focus:ring-teal-500">
                    <span>تذكرني على هذا الجهاز</span>
                </label>
            </div>

            <button type="submit"
                    class="w-full bg-teal-700 hover:bg-teal-800 text-white font-bold py-2.5 rounded-lg transition cursor-pointer">
                دخول
            </button>
        </form>
    </div>

    <script>
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', () => navigator.serviceWorker.register('/service-worker.js'));
        }
    </script>
</body>
</html>