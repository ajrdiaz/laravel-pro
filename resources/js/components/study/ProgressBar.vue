<script setup lang="ts">
defineProps<{
    value: number;
    label: string;
}>();
</script>

<template>
    <div
        class="h-1 overflow-hidden rounded-full bg-border"
        role="progressbar"
        :aria-label="label"
        aria-valuemin="0"
        aria-valuemax="100"
        :aria-valuenow="value"
    >
        <div
            class="h-full bg-primary transition-[width] duration-300 motion-reduce:transition-none"
            :style="{ width: `${value}%` }"
        />
    </div>
</template>
