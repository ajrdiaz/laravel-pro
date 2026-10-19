<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import InlineCode from '@/components/study/InlineCode.vue';
import ModuleSelect from '@/components/study/ModuleSelect.vue';
import { Button } from '@/components/ui/button';
import { exportMethod, index, review } from '@/routes/flashcards';
import type { Flashcard, ModuleOption } from '@/types';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Tarjetas',
                href: index(),
            },
        ],
    },
});

const props = defineProps<{
    modules: ModuleOption[];
    filters: { module: string; all: boolean };
    total: number;
    cards: Flashcard[];
}>();

// The queue lives on the client: a missed card goes to the back of it and comes
// round again, while the server only records the schedule.
const queue = ref<Flashcard[]>([...props.cards]);
const revealed = ref(false);

watch(
    () => props.cards,
    (cards) => {
        queue.value = [...cards];
        revealed.value = false;
    },
);

const current = computed(() => queue.value[0]);

function filterQuery(overrides: { module?: string; all?: boolean } = {}) {
    const module = overrides.module ?? props.filters.module;
    const all = overrides.all ?? props.filters.all;

    return {
        ...(module !== 'all' ? { module } : {}),
        ...(all ? { all: 1 } : {}),
    };
}

const selectedModule = computed({
    get: () => props.filters.module,
    set: (module: string) => {
        router.get(index.url({ query: filterQuery({ module }) }));
    },
});

function answer(knew: boolean) {
    const card = current.value;

    if (!card) {
        return;
    }

    queue.value.shift();

    if (!knew) {
        queue.value.push({ ...card, box: 0 });
    }

    revealed.value = false;

    router.post(
        review.url(card.id),
        { knew },
        { preserveScroll: true, preserveState: true, only: ['stats'] },
    );
}
</script>

<template>
    <Head title="Tarjetas" />

    <div class="mx-auto flex w-full max-w-3xl flex-col gap-5 p-4">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <ModuleSelect
                id="card-module"
                v-model="selectedModule"
                label="Módulo"
                all-label="Todos los módulos"
                :modules="modules"
            />

            <!-- A file download, so a plain link rather than an Inertia visit. -->
            <Button variant="outline" size="sm" as-child>
                <a
                    :href="
                        exportMethod.url({
                            query:
                                filters.module === 'all'
                                    ? {}
                                    : { module: filters.module },
                        })
                    "
                    download
                    >Exportar a Anki</a
                >
            </Button>
        </div>

        <section
            v-if="current"
            class="flex min-h-60 flex-col gap-4 rounded-2xl border bg-card p-6"
            aria-live="polite"
        >
            <div class="flex items-center justify-between gap-3">
                <span class="text-sm font-medium text-primary">{{
                    current.module
                }}</span>
                <span class="font-mono text-sm text-muted-foreground"
                    >{{ queue.length }}
                    {{ filters.all ? 'en esta ronda' : 'pendientes' }}</span
                >
            </div>

            <p class="text-xl leading-snug font-semibold">
                <InlineCode :text="current.question" />
            </p>

            <div v-if="revealed" class="border-t border-dashed pt-4">
                <InlineCode :text="current.answer" />
            </div>
            <p v-else class="text-muted-foreground">
                Responde en voz alta o en papel antes de mirar.
            </p>

            <div class="mt-auto flex flex-wrap items-center gap-2">
                <template v-if="revealed">
                    <Button variant="destructive" @click="answer(false)"
                        >No lo sabía</Button
                    >
                    <Button @click="answer(true)">Lo sabía</Button>
                </template>
                <Button v-else @click="revealed = true"
                    >Mostrar respuesta</Button
                >
                <span class="font-mono text-xs text-muted-foreground"
                    >Nivel {{ current.box }} de 5</span
                >
            </div>
        </section>

        <section
            v-else
            class="flex flex-col items-center gap-3 rounded-2xl border border-dashed bg-card p-8 text-center"
        >
            <h2 class="text-lg font-semibold">Estás al día</h2>
            <p class="max-w-prose text-muted-foreground">
                No hay tarjetas pendientes{{
                    filters.module === 'all' ? '' : ' en este módulo'
                }}. Cada tarjeta que sabes vuelve más tarde: 1, 3, 7, 14 y 30
                días.
            </p>
            <Button v-if="total > 0" variant="outline" as-child>
                <Link :href="index({ query: filterQuery({ all: true }) })"
                    >Repasar las {{ total }} igual</Link
                >
            </Button>
        </section>
    </div>
</template>
