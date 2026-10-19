<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { Check, X } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import InlineCode from '@/components/study/InlineCode.vue';
import ModuleSelect from '@/components/study/ModuleSelect.vue';
import { Button } from '@/components/ui/button';
import { answer, index } from '@/routes/quiz';
import type { ModuleOption, QuizQuestion } from '@/types';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Quiz',
                href: index(),
            },
        ],
    },
});

const props = defineProps<{
    modules: ModuleOption[];
    filters: { module: string };
    questions: QuizQuestion[];
}>();

// The server always sends the correct answer first; the order shown is shuffled here.
function shuffledOrder(length: number): number[] {
    const order = Array.from({ length }, (_, i) => i);

    for (let i = order.length - 1; i > 0; i--) {
        const j = Math.floor(Math.random() * (i + 1));
        [order[i], order[j]] = [order[j] as number, order[i] as number];
    }

    return order;
}

const position = ref(0);
const picked = ref<number | null>(null);
const score = ref(0);
const orders = ref<number[][]>([]);

function startRound() {
    position.value = 0;
    picked.value = null;
    score.value = 0;
    orders.value = props.questions.map((q) => shuffledOrder(q.options.length));
}

startRound();
watch(() => props.questions, startRound);

const question = computed(() => props.questions[position.value]);
const order = computed(() => orders.value[position.value] ?? []);
const finished = computed(
    () =>
        props.questions.length > 0 && position.value >= props.questions.length,
);
const isLast = computed(() => position.value + 1 >= props.questions.length);

const selectedModule = computed({
    get: () => props.filters.module,
    set: (module: string) => {
        router.get(index.url(module === 'all' ? {} : { query: { module } }));
    },
});

function pick(optionIndex: number) {
    const current = question.value;

    if (!current || picked.value !== null) {
        return;
    }

    picked.value = optionIndex;

    if (optionIndex === current.correct_index) {
        score.value++;
    }

    router.post(
        answer.url(current.id),
        { selected: optionIndex },
        { preserveScroll: true, preserveState: true, only: ['stats'] },
    );
}

function next() {
    position.value++;
    picked.value = null;
}

// Ask for a fresh draw, then restart even if the server happens to return the same
// questions in the same order (the `questions` watcher wouldn't fire then).
function newRound() {
    router.reload({ only: ['questions'], onSuccess: startRound });
}
</script>

<template>
    <Head title="Quiz" />

    <div class="mx-auto flex w-full max-w-3xl flex-col gap-5 p-4">
        <ModuleSelect
            id="quiz-module"
            v-model="selectedModule"
            label="Preguntas de"
            all-label="Todos (6 al azar)"
            :modules="modules"
        />

        <section
            v-if="finished"
            class="flex flex-col items-center gap-3 rounded-2xl border border-dashed bg-card p-8 text-center"
        >
            <span class="text-5xl font-extrabold tabular-nums"
                >{{ score }}/{{ questions.length }}</span
            >
            <p class="max-w-prose text-muted-foreground">
                {{
                    score === questions.length
                        ? 'Perfecto. Pasa al reto de este módulo.'
                        : 'Revisa en la documentación las que fallaste y vuelve a intentarlo.'
                }}
            </p>
            <Button @click="newRound">Otra ronda</Button>
        </section>

        <section
            v-else-if="question"
            class="flex flex-col gap-4 rounded-2xl border bg-card p-6"
        >
            <div class="flex items-center justify-between gap-3">
                <span class="text-sm font-medium text-primary">{{
                    question.module
                }}</span>
                <span class="font-mono text-sm text-muted-foreground"
                    >{{ position + 1 }} de {{ questions.length }}</span
                >
            </div>

            <p class="text-xl leading-snug font-semibold">
                <InlineCode :text="question.question" />
            </p>

            <div class="flex flex-col gap-2">
                <button
                    v-for="optionIndex in order"
                    :key="optionIndex"
                    type="button"
                    class="flex items-start gap-3 rounded-lg border px-4 py-3 text-left transition-colors enabled:hover:border-primary disabled:cursor-default"
                    :class="{
                        'border-emerald-600 bg-emerald-50 dark:bg-emerald-950/40':
                            picked !== null &&
                            optionIndex === question.correct_index,
                        'border-red-600 bg-red-50 dark:bg-red-950/40':
                            picked === optionIndex &&
                            optionIndex !== question.correct_index,
                    }"
                    :disabled="picked !== null"
                    @click="pick(optionIndex)"
                >
                    <span class="flex-1">
                        <InlineCode
                            :text="question.options[optionIndex] ?? ''"
                        />
                    </span>
                    <Check
                        v-if="
                            picked !== null &&
                            optionIndex === question.correct_index
                        "
                        class="mt-0.5 size-4 shrink-0 text-emerald-700 dark:text-emerald-400"
                        aria-label="Respuesta correcta"
                    />
                    <X
                        v-else-if="picked === optionIndex"
                        class="mt-0.5 size-4 shrink-0 text-red-700 dark:text-red-400"
                        aria-label="Tu respuesta, incorrecta"
                    />
                </button>
            </div>

            <template v-if="picked !== null">
                <p
                    class="border-l-2 border-primary py-1 pl-3"
                    aria-live="polite"
                >
                    <strong>{{
                        picked === question.correct_index ? 'Correcto.' : 'No.'
                    }}</strong>
                    <InlineCode :text="` ${question.explanation}`" />
                </p>
                <div>
                    <Button @click="next">{{
                        isLast ? 'Ver resultado' : 'Siguiente'
                    }}</Button>
                </div>
            </template>
        </section>

        <p v-else class="text-muted-foreground">
            No hay preguntas para mostrar.
        </p>
    </div>
</template>
