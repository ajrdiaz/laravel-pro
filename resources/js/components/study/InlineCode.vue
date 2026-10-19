<script setup lang="ts">
import { computed } from 'vue';

const props = defineProps<{
    text: string;
}>();

// Odd-numbered parts sit between backticks and render as code.
const parts = computed(() => props.text.split('`'));
</script>

<template>
    <template v-for="(part, index) in parts" :key="index">
        <code
            v-if="index % 2 === 1"
            class="rounded bg-muted px-1 py-0.5 font-mono text-[0.86em] break-words"
            >{{ part }}</code
        >
        <template v-else>{{ part }}</template>
    </template>
</template>
