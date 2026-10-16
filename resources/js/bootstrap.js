/**
 * We'll load the axios HTTP library which allows us to easily issue requests
 * to our Laravel back-end. This library automatically handles sending the
 * CSRF token as a header based on the value of the "XSRF" token cookie.
 */

import axios from 'axios';
window.axios = axios;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

/**
 * Tiempo real (protocolo Pusher: Soketi en local/servidor propio, Pusher o Reverb).
 * Sin VITE_PUSHER_APP_KEY no se conecta y las pantallas usan el respaldo por consulta (live.js).
 */
import Pusher from 'pusher-js';
import Echo from 'laravel-echo';
import './live';

const key = import.meta.env.VITE_PUSHER_APP_KEY;

if (key) {
    window.Pusher = Pusher;

    const scheme = import.meta.env.VITE_PUSHER_SCHEME || 'https';
    const host = import.meta.env.VITE_PUSHER_HOST;
    const port = Number(import.meta.env.VITE_PUSHER_PORT) || (scheme === 'https' ? 443 : 80);

    window.Echo = new Echo({
        broadcaster: 'pusher',
        key,
        cluster: import.meta.env.VITE_PUSHER_APP_CLUSTER || 'mt1',
        ...(host ? { wsHost: host, wsPort: port, wssPort: port } : {}),
        forceTLS: scheme === 'https',
        enabledTransports: ['ws', 'wss'],
    });
}
