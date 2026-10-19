<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { ref } from 'vue';
import CompletionToggle from '@/components/study/CompletionToggle.vue';
import { Button } from '@/components/ui/button';
import { index } from '@/routes/interview';
import type { InterviewQuestion } from '@/types';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Entrevista',
                href: index(),
            },
        ],
    },
});

defineProps<{
    questions: InterviewQuestion[];
}>();

const showingPoints = ref<number[]>([]);

function togglePoints(id: number) {
    showingPoints.value = showingPoints.value.includes(id)
        ? showingPoints.value.filter((shownId) => shownId !== id)
        : [...showingPoints.value, id];
}
</script>

<template>
    <Head title="Entrevista" />

    <div class="mx-auto flex w-full max-w-3xl flex-col gap-5 p-4">
        <p class="rounded-lg bg-accent px-4 py-3">
            <strong>Cómo practicar:</strong> lee la pregunta, responde en inglés
            en voz alta durante 1 o 2 minutos (grábate si puedes) y después
            compara con los puntos clave.
        </p>

        <ul class="flex flex-col gap-3">
            <li
                v-for="question in questions"
                :key="question.id"
                class="flex flex-col gap-3 rounded-xl border bg-card p-4"
                :class="question.completed ? 'border-emerald-600' : ''"
            >
                <span class="font-mono text-xs text-muted-foreground">{{
                    question.module
                }}</span>
                <p class="text-lg leading-snug font-semibold" lang="en">
                    {{ question.question }}
                </p>
                <ul
                    v-if="showingPoints.includes(question.id)"
                    class="flex list-disc flex-col gap-1 pl-5"
                    lang="en"
                >
                    <li v-for="point in question.points" :key="point">
                        {{ point }}
                    </li>
                </ul>
                <div class="flex flex-wrap items-center gap-x-4 gap-y-2">
                    <Button
                        variant="outline"
                        size="sm"
                        :aria-expanded="showingPoints.includes(question.id)"
                        @click="togglePoints(question.id)"
                    >
                        {{
                            showingPoints.includes(question.id)
                                ? 'Ocultar puntos clave'
                                : 'Ver puntos clave'
                        }}
                    </Button>
                    <CompletionToggle
                        type="interview_question"
                        :id="question.id"
                        :completed="question.completed"
                    >
                        Practicada en voz alta
                    </CompletionToggle>
                </div>
            </li>
        </ul>
    </div>
</template>
