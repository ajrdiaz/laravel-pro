<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    ArrowLeft,
    CircleCheck,
    CircleDashed,
    CircleDot,
    Trash2,
} from '@lucide/vue';
import CommandLabel from '@/components/study/CommandLabel.vue';
import ProgressBar from '@/components/study/ProgressBar.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { formatDate } from '@/lib/format';
import { index as flashcards } from '@/routes/flashcards';
import { destroy, index } from '@/routes/jobs';
import { module as learnModule } from '@/routes/learn';
import type { JobModule, JobReport } from '@/types';
import { computed } from 'vue';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Ofertas',
                href: index(),
            },
        ],
    },
});

const props = defineProps<{
    analysis: {
        id: number;
        title: string | null;
        body: string;
        created_at: string | null;
    };
    report: JobReport;
}>();

const toStudy = computed(() =>
    props.report.modules.filter((module) => module.status !== 'ready'),
);
const ready = computed(() =>
    props.report.modules.filter((module) => module.status === 'ready'),
);

const tiles = computed(() => [
    {
        label: 'Habilidades detectadas',
        value: String(props.report.summary.detected),
    },
    {
        label: 'Cubiertas por tu ruta',
        value: String(props.report.summary.covered),
    },
    {
        label: 'Tu preparación',
        value:
            props.report.summary.readiness === null
                ? '—'
                : `${props.report.summary.readiness}%`,
    },
]);

const statusLabel: Record<JobModule['status'], string> = {
    ready: 'Lo tienes',
    learning: 'En camino',
    todo: 'Por empezar',
};

function day(iso: string | null): string {
    return iso ? formatDate(iso.slice(0, 10)) : '';
}

function remove() {
    router.delete(destroy.url(props.analysis.id));
}
</script>

<template>
    <Head :title="analysis.title ?? 'Análisis de oferta'" />

    <div class="mx-auto flex w-full max-w-3xl flex-col gap-6 p-4">
        <Link
            :href="index()"
            class="inline-flex w-fit items-center gap-1 text-sm text-muted-foreground hover:text-foreground"
        >
            <ArrowLeft class="size-4" /> Todas las ofertas
        </Link>

        <header class="flex flex-col gap-2">
            <h1 class="text-3xl leading-tight font-extrabold tracking-tight">
                {{ analysis.title ?? 'Oferta sin título' }}
            </h1>
            <p class="text-sm text-muted-foreground">
                Analizada el {{ day(analysis.created_at) }}. El resultado se
                recalcula con tu progreso actual cada vez que entras.
            </p>
        </header>

        <dl class="grid grid-cols-3 gap-3">
            <div
                v-for="tile in tiles"
                :key="tile.label"
                class="min-w-0 rounded-lg border bg-card px-4 py-3"
            >
                <dd class="text-2xl font-extrabold tabular-nums sm:text-3xl">
                    {{ tile.value }}
                </dd>
                <dt class="text-sm text-muted-foreground">{{ tile.label }}</dt>
            </div>
        </dl>

        <p
            v-if="report.summary.detected === 0"
            class="rounded-xl border border-dashed bg-card p-6 text-muted-foreground"
        >
            No reconocí ninguna habilidad conocida. Comprueba que pegaste el
            texto completo de los requisitos, no solo el título del puesto.
        </p>

        <section v-if="toStudy.length > 0" class="flex flex-col gap-3">
            <h2 class="text-xl font-semibold">Qué estudiar primero</h2>
            <p class="text-muted-foreground">
                Ordenado por cuánto insiste la oferta en cada tema.
            </p>
            <ul class="flex flex-col gap-3">
                <li
                    v-for="module in toStudy"
                    :key="module.slug"
                    class="flex flex-col gap-3 rounded-xl border bg-card p-4"
                >
                    <div class="flex items-start justify-between gap-3">
                        <div class="flex min-w-0 flex-col">
                            <CommandLabel :command="module.command" />
                            <span class="font-semibold">{{
                                module.title
                            }}</span>
                        </div>
                        <span
                            class="inline-flex shrink-0 items-center gap-1 text-sm text-muted-foreground"
                        >
                            <CircleDot
                                v-if="module.status === 'learning'"
                                class="size-4 text-amber-600 dark:text-amber-400"
                                aria-hidden="true"
                            />
                            <CircleDashed
                                v-else
                                class="size-4"
                                aria-hidden="true"
                            />
                            {{ statusLabel[module.status] }}
                            · {{ module.progress.pct }}%
                        </span>
                    </div>
                    <ProgressBar
                        :value="module.progress.pct"
                        :label="`Progreso de ${module.title}`"
                    />
                    <ul class="flex flex-col gap-1">
                        <li
                            v-for="skill in module.skills"
                            :key="skill.name"
                            class="flex flex-wrap items-center gap-2"
                        >
                            <Badge variant="secondary">{{ skill.name }}</Badge>
                            <span class="text-xs text-muted-foreground"
                                >{{ skill.mentions }}
                                {{
                                    skill.mentions === 1
                                        ? 'mención'
                                        : 'menciones'
                                }}</span
                            >
                            <span
                                v-if="skill.note"
                                class="text-sm text-muted-foreground"
                                >{{ skill.note }}</span
                            >
                        </li>
                    </ul>
                    <div class="flex flex-wrap gap-2">
                        <Button variant="outline" size="sm" as-child>
                            <Link :href="learnModule(module.slug)"
                                >Aprender</Link
                            >
                        </Button>
                        <Button variant="outline" size="sm" as-child>
                            <Link
                                :href="
                                    flashcards({
                                        query: { module: module.slug },
                                    })
                                "
                                >Tarjetas</Link
                            >
                        </Button>
                    </div>
                </li>
            </ul>
        </section>

        <section v-if="ready.length > 0" class="flex flex-col gap-3">
            <h2 class="text-xl font-semibold">Ya lo tienes</h2>
            <ul class="flex flex-col gap-2">
                <li
                    v-for="module in ready"
                    :key="module.slug"
                    class="flex flex-col gap-2 rounded-xl border border-emerald-600 bg-card p-4"
                >
                    <div class="flex items-center justify-between gap-3">
                        <span
                            class="inline-flex items-center gap-2 font-semibold"
                        >
                            <CircleCheck
                                class="size-4 text-emerald-700 dark:text-emerald-400"
                                aria-hidden="true"
                            />
                            {{ module.title }}
                        </span>
                        <span class="font-mono text-sm text-muted-foreground"
                            >{{ module.progress.pct }}%</span
                        >
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <Badge
                            v-for="skill in module.skills"
                            :key="skill.name"
                            variant="secondary"
                            >{{ skill.name }}</Badge
                        >
                    </div>
                </li>
            </ul>
        </section>

        <section v-if="report.outside.length > 0" class="flex flex-col gap-3">
            <h2 class="text-xl font-semibold">Fuera de tu ruta</h2>
            <p class="text-muted-foreground">
                Temas que la oferta menciona y que ningún módulo enseña todavía.
                No significa que debas aprenderlos todos: decide cuáles pesan en
                esta oferta.
            </p>
            <ul class="flex flex-col gap-2">
                <li
                    v-for="skill in report.outside"
                    :key="skill.name"
                    class="flex flex-col gap-1 rounded-xl bg-accent p-4"
                >
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="font-semibold">{{ skill.name }}</span>
                        <Badge v-if="skill.category" variant="outline">{{
                            skill.category
                        }}</Badge>
                        <span class="text-xs text-muted-foreground"
                            >{{ skill.mentions }}
                            {{
                                skill.mentions === 1 ? 'mención' : 'menciones'
                            }}</span
                        >
                    </div>
                    <p v-if="skill.hint" class="text-sm text-muted-foreground">
                        {{ skill.hint }}
                    </p>
                </li>
            </ul>
        </section>

        <section class="flex flex-col gap-3 border-t pt-4">
            <details class="rounded-xl border bg-card p-4">
                <summary class="cursor-pointer font-semibold">
                    Ver el texto de la oferta
                </summary>
                <p
                    class="mt-3 text-sm leading-relaxed whitespace-pre-wrap text-muted-foreground"
                >
                    {{ analysis.body }}
                </p>
            </details>

            <div>
                <Dialog>
                    <DialogTrigger as-child>
                        <Button variant="outline" size="sm">
                            <Trash2 aria-hidden="true" /> Borrar este análisis
                        </Button>
                    </DialogTrigger>
                    <DialogContent>
                        <DialogHeader>
                            <DialogTitle>¿Borrar este análisis?</DialogTitle>
                            <DialogDescription>
                                Se borra el texto de la oferta y su resultado.
                                No afecta a tu progreso.
                            </DialogDescription>
                        </DialogHeader>
                        <DialogFooter class="gap-2">
                            <DialogClose as-child>
                                <Button variant="outline">Cancelar</Button>
                            </DialogClose>
                            <Button variant="destructive" @click="remove"
                                >Sí, borrar</Button
                            >
                        </DialogFooter>
                    </DialogContent>
                </Dialog>
            </div>
        </section>
    </div>
</template>
