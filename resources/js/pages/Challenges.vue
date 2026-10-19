<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import CommandLabel from '@/components/study/CommandLabel.vue';
import CompletionToggle from '@/components/study/CompletionToggle.vue';
import InlineCode from '@/components/study/InlineCode.vue';
import { index } from '@/routes/challenges';
import type { Challenge } from '@/types';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Retos sin IA',
                href: index(),
            },
        ],
    },
});

defineProps<{
    challenges: Challenge[];
}>();
</script>

<template>
    <Head title="Retos sin IA" />

    <div class="mx-auto flex w-full max-w-3xl flex-col gap-5 p-4">
        <p
            class="rounded-lg bg-amber-50 px-4 py-3 text-amber-950 dark:bg-amber-950/30 dark:text-amber-100"
        >
            <strong>Reglas:</strong> sin IA ni autocompletado inteligente, solo
            la documentación oficial de Laravel. Ponte un límite de 60 a 90
            minutos. Si te trabas, anota la duda y sigue; al final puedes
            preguntarle a la IA por qué te trabaste.
        </p>

        <ul class="flex flex-col gap-3">
            <li
                v-for="challenge in challenges"
                :key="challenge.id"
                class="flex flex-col gap-3 rounded-xl border bg-card p-4"
                :class="challenge.completed ? 'border-emerald-600' : ''"
            >
                <CommandLabel :command="challenge.module.command" />
                <h2 class="text-lg font-semibold">
                    {{ challenge.module.title }}
                </h2>
                <p><InlineCode :text="challenge.body" /></p>
                <CompletionToggle
                    type="challenge"
                    :id="challenge.id"
                    :completed="challenge.completed"
                >
                    Completado sin IA
                </CompletionToggle>
            </li>
        </ul>
    </div>
</template>
