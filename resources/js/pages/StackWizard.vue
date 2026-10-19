<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ArrowLeft } from '@lucide/vue';
import { computed, nextTick, reactive, ref, useTemplateRef } from 'vue';
import InlineCode from '@/components/study/InlineCode.vue';
import ProgressBar from '@/components/study/ProgressBar.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { module as learnModule } from '@/routes/learn';
import { compare, index, show, wizard } from '@/routes/stacks';
import type {
    WizardAnswers,
    WizardCard,
    WizardQuestion,
    WizardResult,
} from '@/types';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Stacks',
                href: index(),
            },
        ],
    },
});

const props = defineProps<{
    questions: WizardQuestion[];
    answers: WizardAnswers;
    result: WizardResult | null;
}>();

// One answer per single-choice question, and a list for the multiple-choice one.
const single = reactive<Record<string, string | null>>({
    clients: props.answers.clients,
    team: props.answers.team,
    ui: props.answers.ui,
});
const extras = ref<string[]>([...props.answers.extras]);

const results = useTemplateRef<HTMLElement>('results');

const complete = computed(
    () => single.clients !== null && single.team !== null && single.ui !== null,
);

function submit() {
    router.get(
        wizard.url(),
        {
            clients: single.clients ?? undefined,
            team: single.team ?? undefined,
            ui: single.ui ?? undefined,
            extras: extras.value,
        },
        {
            preserveScroll: true,
            preserveState: true,
            // The answer appears below the form: bring it into view.
            onSuccess: () =>
                nextTick(() =>
                    results.value?.scrollIntoView({ block: 'start' }),
                ),
        },
    );
}

function reset() {
    single.clients = single.team = single.ui = null;
    extras.value = [];
    router.get(wizard.url(), {}, { preserveState: true });
}

const compareHref = computed(() => {
    if (!props.result) {
        return null;
    }

    const slugs = [...props.result.base, ...props.result.addons]
        .slice(0, 3)
        .map((card) => card.slug);

    return slugs.length >= 2 ? compare({ query: { stacks: slugs } }) : null;
});

const cards = (
    result: WizardResult,
): { title: string; items: WizardCard[] }[] =>
    [
        { title: 'Empieza por', items: result.base },
        { title: 'Y añade', items: result.addons },
    ].filter((group) => group.items.length > 0);
</script>

<template>
    <Head title="¿Qué stack uso?" />

    <div class="mx-auto flex w-full max-w-3xl flex-col gap-6 p-4">
        <Link
            :href="index()"
            class="inline-flex w-fit items-center gap-1 text-sm text-muted-foreground hover:text-foreground"
        >
            <ArrowLeft class="size-4" /> Todos los stacks
        </Link>

        <header class="flex flex-col gap-3">
            <span class="font-mono text-xs text-primary"
                >$ php artisan stack:choose</span
            >
            <h1 class="text-3xl font-extrabold tracking-tight">
                ¿Qué stack uso?
            </h1>
            <p class="max-w-prose text-muted-foreground">
                Responde tres preguntas y marca lo que necesites. Te propongo un
                stack para empezar y las piezas que conviene añadir. Es una
                guía, no un veredicto: lee la ficha de cada uno antes de
                decidir.
            </p>
        </header>

        <form class="flex flex-col gap-5" @submit.prevent="submit">
            <fieldset
                v-for="question in questions"
                :key="question.key"
                class="flex flex-col gap-2"
            >
                <legend class="font-semibold">{{ question.title }}</legend>
                <p v-if="question.help" class="text-sm text-muted-foreground">
                    {{ question.help }}
                </p>

                <label
                    v-for="option in question.options"
                    :key="option.value"
                    class="flex cursor-pointer items-start gap-3 rounded-lg border bg-card p-3 transition-colors hover:border-primary has-[:checked]:border-primary has-[:checked]:bg-accent has-[:focus-visible]:ring-[3px] has-[:focus-visible]:ring-ring/50"
                >
                    <input
                        v-if="question.multiple"
                        v-model="extras"
                        type="checkbox"
                        :value="option.value"
                        class="mt-1 size-4 shrink-0 accent-primary"
                    />
                    <input
                        v-else
                        v-model="single[question.key]"
                        type="radio"
                        :name="question.key"
                        :value="option.value"
                        class="mt-1 size-4 shrink-0 accent-primary"
                    />
                    <span>{{ option.label }}</span>
                </label>
            </fieldset>

            <div class="flex flex-wrap gap-2">
                <Button type="submit" :disabled="!complete"
                    >Recomendar un stack</Button
                >
                <Button type="button" variant="outline" @click="reset"
                    >Empezar de nuevo</Button
                >
            </div>
            <p v-if="!complete" class="text-sm text-muted-foreground">
                Responde las tres primeras preguntas para ver una recomendación.
            </p>
        </form>

        <section
            v-if="result"
            ref="results"
            class="flex scroll-mt-4 flex-col gap-6 border-t pt-6"
            aria-live="polite"
        >
            <h2 class="text-2xl font-bold">Tu recomendación</h2>

            <div
                v-for="group in cards(result)"
                :key="group.title"
                class="flex flex-col gap-3"
            >
                <h3
                    class="text-sm font-semibold tracking-wider text-muted-foreground uppercase"
                >
                    {{ group.title }}
                </h3>

                <Link
                    v-for="card in group.items"
                    :key="card.slug"
                    :href="show(card.slug)"
                    class="flex flex-col gap-3 rounded-xl border bg-card p-4 transition-colors hover:border-primary focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none"
                >
                    <span class="text-xs font-medium text-primary">{{
                        card.category
                    }}</span>
                    <span class="text-lg leading-snug font-semibold">{{
                        card.name
                    }}</span>
                    <span class="text-muted-foreground"
                        ><InlineCode :text="card.summary"
                    /></span>
                    <span class="rounded-lg bg-accent p-3 text-sm"
                        ><strong>Por qué: </strong><InlineCode :text="card.why"
                    /></span>
                    <span class="flex flex-wrap gap-2">
                        <Badge
                            v-for="item in card.headline"
                            :key="item"
                            variant="secondary"
                            >{{ item }}</Badge
                        >
                    </span>
                </Link>
            </div>

            <div class="flex flex-col gap-3 rounded-xl border bg-card p-4">
                <h3 class="font-semibold">Qué tan preparado estás</h3>
                <p class="text-sm text-muted-foreground">
                    Promedio de tu progreso en los
                    {{ result.preparation.modules }} módulos que piden estos
                    stacks.
                </p>
                <div class="flex items-center gap-3">
                    <span class="text-3xl font-extrabold tabular-nums"
                        >{{ result.preparation.readiness }}%</span
                    >
                    <div class="flex-1">
                        <ProgressBar
                            :value="result.preparation.readiness"
                            label="Preparación para el stack recomendado"
                        />
                    </div>
                </div>

                <template v-if="result.preparation.startWith.length > 0">
                    <h4 class="text-sm font-semibold">
                        Empieza por estos módulos
                    </h4>
                    <ul class="flex flex-col gap-1">
                        <li
                            v-for="item in result.preparation.startWith"
                            :key="item.slug"
                            class="flex items-center justify-between gap-3 text-sm"
                        >
                            <Link
                                :href="learnModule(item.slug)"
                                class="text-primary underline-offset-4 hover:underline"
                                >{{ item.title }}</Link
                            >
                            <span class="font-mono text-muted-foreground"
                                >{{ item.pct }}%</span
                            >
                        </li>
                    </ul>
                </template>
            </div>

            <div v-if="compareHref">
                <Button variant="outline" as-child>
                    <Link :href="compareHref">Comparar estos stacks</Link>
                </Button>
            </div>
        </section>
    </div>
</template>
