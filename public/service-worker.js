const CACHE_NAME = 'clinic-pwa-v1';

self.addEventListener('install', (event) => {
    self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    event.waitUntil(clients.claim());
});

self.addEventListener('fetch', (event) => {
    // تمرير جميع الطلبات للشبكة لضمان حداثة بيانات المواعيد والجلسات
    event.respondWith(fetch(event.request));
});