import { router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { toast } from 'vue-sonner';

/** Chrome and Edge fire this when the app can be installed; the DOM typings lack it. */
type BeforeInstallPromptEvent = Event & {
    prompt: () => Promise<void>;
    userChoice: Promise<{ outcome: 'accepted' | 'dismissed' }>;
};

/** The caches that hold lessons for offline reading, see public/sw.js. */
const PAGES_CACHE_PREFIX = 'ruta-pages-';

/** Who the saved lessons belong to. */
const OFFLINE_USER_KEY = 'ruta-offline-user';

const installEvent = ref<BeforeInstallPromptEvent | null>(null);

/** Whether the browser is ready to install the app, so a button can be offered. */
export const canInstall = computed(() => installEvent.value !== null);

export async function installApp(): Promise<void> {
    const event = installEvent.value;

    if (event === null) {
        return;
    }

    await event.prompt();
    await event.userChoice;

    // The event can only be used once.
    installEvent.value = null;
}

/**
 * Deletes the lessons saved for offline reading. They include the user's notes, so they
 * must not outlive the session on a shared device.
 */
export async function clearOfflineData(): Promise<void> {
    if (!('caches' in window)) {
        return;
    }

    const names = await caches.keys();

    await Promise.all(
        names
            .filter((name) => name.startsWith(PAGES_CACHE_PREFIX))
            .map((name) => caches.delete(name)),
    );
}

/**
 * Saved lessons belong to whoever saved them. If a different user is signed in now (the
 * session of the first one may have expired without a log out), start from scratch.
 */
async function keepOfflineDataToOneUser(userId: number): Promise<void> {
    try {
        if (localStorage.getItem(OFFLINE_USER_KEY) === String(userId)) {
            return;
        }

        await clearOfflineData();
        localStorage.setItem(OFFLINE_USER_KEY, String(userId));
    } catch {
        // Storage can be blocked (a private window): the lessons are then cleared on log out only.
    }
}

function registerServiceWorker(): void {
    // In development the worker would serve stale files and fight with hot reloading.
    if (!import.meta.env.PROD || !('serviceWorker' in navigator)) {
        return;
    }

    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/sw.js').catch((error: unknown) => {
            console.warn('The service worker could not be registered.', error);
        });
    });
}

export function initializePwa(): void {
    registerServiceWorker();

    window.addEventListener('beforeinstallprompt', (event) => {
        // Hold the prompt back, so it appears when the user presses the button.
        event.preventDefault();
        installEvent.value = event as BeforeInstallPromptEvent;
    });

    window.addEventListener('appinstalled', () => {
        installEvent.value = null;
    });

    router.on('navigate', (event) => {
        const user = event.detail.page.props.auth?.user;

        if (user) {
            void keepOfflineDataToOneUser(user.id);
        }
    });

    // A visit that cannot reach the server, typically a page that was never opened online.
    router.on('networkError', () => {
        toast.error(
            'No se pudo conectar. Sin conexión solo puedes abrir las lecciones que ya visitaste.',
        );
    });
}
