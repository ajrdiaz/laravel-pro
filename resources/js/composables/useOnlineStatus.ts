import { onBeforeUnmount, onMounted, ref } from 'vue';

/** Whether the browser thinks it has a connection, kept up to date as it changes. */
export function useOnlineStatus() {
    const online = ref(
        typeof navigator === 'undefined' ? true : navigator.onLine,
    );

    const update = () => {
        online.value = navigator.onLine;
    };

    onMounted(() => {
        window.addEventListener('online', update);
        window.addEventListener('offline', update);
        update();
    });

    onBeforeUnmount(() => {
        window.removeEventListener('online', update);
        window.removeEventListener('offline', update);
    });

    return { online };
}
