self.addEventListener('push', (event) => {
    const payload = event.data ? event.data.json() : {};
    event.waitUntil(self.registration.showNotification(payload.title || 'Orbitra', {
        body: payload.body || '',
        icon: payload.icon || '/favicon.ico',
        data: payload.data || {},
    }));
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();
    event.waitUntil(clients.openWindow(event.notification.data?.url || '/app'));
});
