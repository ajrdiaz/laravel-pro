<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, Check, ExternalLink, Minus, Plus, X } from '@lucide/vue';
import CommandLabel from '@/components/study/CommandLabel.vue';
import InlineCode from '@/components/study/InlineCode.vue';
import ProgressBar from '@/components/study/ProgressBar.vue';
import { Button } from '@/components/ui/button';
import { formatDate } from '@/lib/format';
import { index as flashcards } from '@/routes/flashcards';
import { module as learnModule } from '@/routes/learn';
import { index as quiz } from '@/routes/quiz';
import { index } from '@/routes/stacks';
import type { StackDetail, StackLayerGroup, StackModule } from '@/types';

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

defineProps<{
    stack: StackDetail;
    layers: StackLayerGroup[];
    modules: StackModule[];
}>();
</script>

<template>
    <Head :title="stack.name" />

    <div class="mx-auto flex w-full max-w-3xl flex-col gap-6 p-4">
        <Link
            :href="index()"
            class="inline-flex w-fit items-center gap-1 text-sm text-muted-foreground hover:text-foreground"
        >
            <ArrowLeft class="size-4" /> Todos los stacks
        </Link>

        <header class="flex flex-col gap-3">
            <span class="text-xs font-medium text-primary">{{
                stack.category.label
            }}</span>
            <h1 class="text-3xl leading-tight font-extrabold tracking-tight">
                {{ stack.name }}
            </h1>
            <p class="max-w-prose text-muted-foreground">{{ stack.summary }}</p>
            <p class="text-sm text-muted-foreground">
                Revisado el {{ formatDate(stack.reviewed_on) }}. El ecosistema
                cambia rápido: confirma versiones y requisitos en las fuentes
                que aparecen al final.
            </p>
        </header>

        <div class="grid gap-3 sm:grid-cols-2">
            <section class="flex flex-col gap-3 rounded-xl border bg-card p-4">
                <h2 class="font-semibold">Elígelo cuando</h2>
                <ul class="flex flex-col gap-2">
                    <li
                        v-for="item in stack.when_to_use"
                        :key="item"
                        class="flex gap-2"
                    >
                        <Check
                            class="mt-1 size-4 shrink-0 text-emerald-700 dark:text-emerald-400"
                            aria-hidden="true"
                        />
                        <span><InlineCode :text="item" /></span>
                    </li>
                </ul>
            </section>
            <section class="flex flex-col gap-3 rounded-xl border bg-card p-4">
                <h2 class="font-semibold">Evítalo cuando</h2>
                <ul class="flex flex-col gap-2">
                    <li
                        v-for="item in stack.when_to_avoid"
                        :key="item"
                        class="flex gap-2"
                    >
                        <X
                            class="mt-1 size-4 shrink-0 text-red-700 dark:text-red-400"
                            aria-hidden="true"
                        />
                        <span><InlineCode :text="item" /></span>
                    </li>
                </ul>
            </section>
        </div>

        <section class="flex flex-col gap-3">
            <h2 class="text-xl font-semibold">Capas del stack</h2>
            <div class="divide-y rounded-xl border bg-card">
                <div
                    v-for="group in layers"
                    :key="group.layer"
                    class="grid gap-x-4 gap-y-2 p-4 sm:grid-cols-[10rem_minmax(0,1fr)]"
                >
                    <h3
                        class="text-xs font-semibold tracking-wider text-muted-foreground uppercase"
                    >
                        {{ group.label }}
                    </h3>
                    <dl class="flex flex-col gap-3">
                        <div v-for="item in group.items" :key="item.name">
                            <dt class="font-semibold">
                                <InlineCode :text="item.name" />
                            </dt>
                            <dd class="text-muted-foreground">
                                <InlineCode :text="item.note" />
                            </dd>
                        </div>
                    </dl>
                </div>
            </div>
        </section>

        <div class="grid gap-3 sm:grid-cols-2">
            <section class="flex flex-col gap-3 rounded-xl border bg-card p-4">
                <h2 class="font-semibold">Ventajas</h2>
                <ul class="flex flex-col gap-2">
                    <li
                        v-for="item in stack.pros"
                        :key="item"
                        class="flex gap-2"
                    >
                        <Plus
                            class="mt-1 size-4 shrink-0 text-emerald-700 dark:text-emerald-400"
                            aria-hidden="true"
                        />
                        <span><InlineCode :text="item" /></span>
                    </li>
                </ul>
            </section>
            <section class="flex flex-col gap-3 rounded-xl border bg-card p-4">
                <h2 class="font-semibold">Costos</h2>
                <ul class="flex flex-col gap-2">
                    <li
                        v-for="item in stack.cons"
                        :key="item"
                        class="flex gap-2"
                    >
                        <Minus
                            class="mt-1 size-4 shrink-0 text-amber-700 dark:text-amber-400"
                            aria-hidden="true"
                        />
                        <span><InlineCode :text="item" /></span>
                    </li>
                </ul>
            </section>
        </div>

        <section class="flex flex-col gap-3">
            <h2 class="text-xl font-semibold">Lo que necesitas dominar</h2>
            <p class="text-muted-foreground">
                Módulos de tu ruta que este stack da por sabidos, con tu
                progreso en cada uno.
            </p>
            <ul class="flex flex-col gap-3">
                <li
                    v-for="module in modules"
                    :key="module.slug"
                    class="flex flex-col gap-2 rounded-xl border bg-card p-4"
                >
                    <div class="flex items-start justify-between gap-3">
                        <div class="flex min-w-0 flex-col">
                            <CommandLabel :command="module.command" />
                            <span class="font-semibold">{{
                                module.title
                            }}</span>
                        </div>
                        <span
                            class="font-mono text-sm text-muted-foreground tabular-nums"
                            >{{ module.progress.pct }}%</span
                        >
                    </div>
                    <ProgressBar
                        :value="module.progress.pct"
                        :label="`Progreso de ${module.title}`"
                    />
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
                        <Button variant="outline" size="sm" as-child>
                            <Link
                                :href="quiz({ query: { module: module.slug } })"
                                >Quiz</Link
                            >
                        </Button>
                    </div>
                </li>
            </ul>

            <div
                v-if="stack.also_learn.length > 0"
                class="rounded-xl bg-accent p-4"
            >
                <h3 class="font-semibold">Aún no está en tu ruta</h3>
                <p class="mt-1 text-sm text-muted-foreground">
                    Temas que este stack también pide y que todavía no tienen
                    módulo propio.
                </p>
                <ul class="mt-3 flex list-disc flex-col gap-1 pl-5">
                    <li v-for="topic in stack.also_learn" :key="topic">
                        <InlineCode :text="topic" />
                    </li>
                </ul>
            </div>
        </section>

        <section class="flex flex-col gap-3 border-t pt-4">
            <h2 class="text-lg font-semibold">Fuentes</h2>
            <ul class="flex flex-col gap-2">
                <li v-for="source in stack.sources" :key="source.url">
                    <a
                        :href="source.url"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="inline-flex items-center gap-1.5 text-primary underline-offset-4 hover:underline"
                    >
                        {{ source.title }}
                        <ExternalLink class="size-3.5" aria-hidden="true" />
                        <span class="sr-only"
                            >(se abre en una pestaña nueva)</span
                        >
                    </a>
                </li>
            </ul>
        </section>
    </div>
</template>
