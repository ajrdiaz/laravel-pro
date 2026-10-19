<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const page = usePage();

const tiles = computed(() => {
    const stats = page.props.stats;

    return [
        {
            label: 'Progreso total',
            value: `${stats?.progress ?? 0}%`,
            due: false,
        },
        {
            label: 'Tarjetas para hoy',
            value: String(stats?.due_cards ?? 0),
            due: true,
        },
        {
            label: 'Días seguidos',
            value: String(stats?.streak ?? 0),
            due: false,
        },
    ];
});
</script>

<template>
    <dl class="grid grid-cols-3 gap-3">
        <div
            v-for="tile in tiles"
            :key="tile.label"
            class="min-w-0 rounded-lg border bg-card px-4 py-3"
        >
            <dd
                class="text-2xl font-extrabold tabular-nums sm:text-3xl"
                :class="tile.due ? 'text-amber-600 dark:text-amber-400' : ''"
            >
                {{ tile.value }}
            </dd>
            <dt class="text-sm text-muted-foreground">{{ tile.label }}</dt>
        </div>
    </dl>
</template>
