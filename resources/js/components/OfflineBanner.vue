<script setup lang="ts">
import { WifiOff } from '@lucide/vue';
import { useOnlineStatus } from '@/composables/useOnlineStatus';

const { online } = useOnlineStatus();
</script>

<template>
    <!-- The live region stays in the page, so a screen reader announces the change. -->
    <div role="status" aria-live="polite">
        <p
            v-if="!online"
            class="flex items-center gap-2 border-b border-amber-300 bg-amber-50 px-4 py-2 text-sm text-amber-900 dark:border-amber-700 dark:bg-amber-950 dark:text-amber-100"
        >
            <WifiOff class="size-4 shrink-0" aria-hidden="true" />
            Sin conexión. Puedes leer las lecciones que ya abriste; lo demás
            necesita internet.
        </p>
    </div>
</template>
