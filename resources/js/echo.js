import Echo from 'laravel-echo';

import Pusher from 'pusher-js';
window.Pusher = Pusher;

const reverbKey = import.meta.env.VITE_REVERB_APP_KEY;

if (reverbKey) {
    window.Echo = new Echo({
        broadcaster: 'reverb',
        key: reverbKey,
        wsHost: import.meta.env.VITE_REVERB_HOST ?? window.location.hostname,
        wsPort: import.meta.env.VITE_REVERB_PORT ?? 80,
        wssPort: import.meta.env.VITE_REVERB_PORT ?? 443,
        forceTLS: (import.meta.env.VITE_REVERB_SCHEME ?? 'https') === 'https',
        enabledTransports: ['ws', 'wss'],
    });
} else {
    // No realtime backend configured (e.g. Reverb not deployed). Install a
    // no-op stub so modules that subscribe to channels keep working — they
    // just never receive events — instead of crashing all JS at import time.
    const stubChannel = {
        listen: () => stubChannel,
        stopListening: () => stubChannel,
        subscribed: () => stubChannel,
        error: () => stubChannel,
    };
    window.Echo = {
        channel: () => stubChannel,
        private: () => stubChannel,
        join: () => stubChannel,
        leave: () => {},
        leaveChannel: () => {},
        listen: () => stubChannel,
    };
}
