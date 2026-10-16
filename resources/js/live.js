/**
 * Refresco en tiempo real de las pantallas (reemplaza wire:poll).
 *
 * window.ntLive(callback, opciones) llama a callback cuando llega App\Events\DietsChanged por el
 * canal privado "diets". Respaldo: si el WebSocket no está conectado, consulta cada `fallbackMs`
 * (como el antiguo poll); conectado, solo cada `safetyMs` por si se perdió un aviso.
 *
 * Opciones:
 *  - kinds: tipos que interesan (diet, dispatch, day); vacío = todos
 *  - canRun(): false aplaza el refresco (p. ej. modal abierto); se ejecuta al volver a true
 *  - fallbackMs (5000), safetyMs (60000)
 *
 * No consulta con la pestaña oculta; al volver a ella refresca si quedó algo pendiente.
 * Devuelve { stop() }.
 */
const listeners = new Set();
let channel = null;

function connected() {
    return window.Echo?.connector?.pusher?.connection?.state === 'connected';
}

function subscribe() {
    if (channel || ! window.Echo) {
        return;
    }

    channel = window.Echo.private('diets').listen('.diets.changed', (event) => {
        listeners.forEach((listener) => listener(event?.kinds ?? []));
    });
}

window.ntLive = function (callback, options = {}) {
    const { kinds = [], canRun = () => true, fallbackMs = 5000, safetyMs = 60000 } = options;
    let pending = false;
    let running = false;
    let lastRun = Date.now();

    const run = async () => {
        if (! pending || running || document.hidden || ! canRun()) {
            return;
        }
        pending = false;
        running = true;
        lastRun = Date.now();
        try {
            await callback();
        } finally {
            running = false;
        }
    };

    const listener = (eventKinds) => {
        if (kinds.length === 0 || eventKinds.length === 0 || eventKinds.some((k) => kinds.includes(k))) {
            pending = true;
            run();
        }
    };

    // Revisión local (sin red) para aplicar lo aplazado y el respaldo
    const tick = setInterval(() => {
        if (Date.now() - lastRun >= (connected() ? safetyMs : fallbackMs)) {
            pending = true;
        }
        run();
    }, 1000);

    const onVisible = () => document.hidden || run();
    document.addEventListener('visibilitychange', onVisible);

    // Al reconectar puede haberse perdido un aviso
    const pusher = window.Echo?.connector?.pusher;
    const onState = ({ previous, current }) => {
        if (current === 'connected' && ['unavailable', 'disconnected', 'failed'].includes(previous)) {
            pending = true;
            run();
        }
    };
    pusher?.connection?.bind('state_change', onState);

    subscribe();
    listeners.add(listener);

    // Devuelve un objeto y no una función: Alpine ejecuta en el acto la función que devuelve
    // una expresión de x-init, y eso quitaba el listener apenas se registraba.
    return {
        stop() {
            clearInterval(tick);
            listeners.delete(listener);
            document.removeEventListener('visibilitychange', onVisible);
            pusher?.connection?.unbind('state_change', onState);
        },
    };
};
