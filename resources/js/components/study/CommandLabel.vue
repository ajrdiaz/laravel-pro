<script setup lang="ts">
defineProps<{
    /** The whole command line, such as `php artisan route:list` or `npm run dev`. */
    command: string;
}>();
</script>

<template>
    <span class="font-mono text-xs break-all text-primary"
        >$ {{ command }}</span
    >
</template>
