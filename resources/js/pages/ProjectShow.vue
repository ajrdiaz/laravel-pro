<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, Clock } from '@lucide/vue';
import CommandLabel from '@/components/study/CommandLabel.vue';
import CompletionToggle from '@/components/study/CompletionToggle.vue';
import InlineCode from '@/components/study/InlineCode.vue';
import ProgressBar from '@/components/study/ProgressBar.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { module as learnModule } from '@/routes/learn';
import { index } from '@/routes/projects';
import { show as stackShow } from '@/routes/stacks';
import type { ProjectDetail, ProjectMilestone, ProjectModule } from '@/types';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Proyectos',
                href: index(),
            },
        ],
    },
});

defineProps<{
    project: ProjectDetail;
    milestones: ProjectMilestone[];
    modules: ProjectModule[];
}>();
</script>

<template>
    <Head :title="project.title" />

    <div class="mx-auto flex w-full max-w-3xl flex-col gap-6 p-4">
        <Link
            :href="index()"
            class="inline-flex w-fit items-center gap-1 text-sm text-muted-foreground hover:text-foreground"
        >
            <ArrowLeft class="size-4" /> Todos los proyectos
        </Link>

        <header class="flex flex-col gap-3">
            <span class="flex flex-wrap items-center gap-2">
                <Badge variant="secondary">{{ project.level.label }}</Badge>
                <span
                    class="inline-flex items-center gap-1 text-sm text-muted-foreground"
                >
                    <Clock class="size-3.5" aria-hidden="true" />
                    unas {{ project.hours }} h
                </span>
            </span>
            <h1 class="text-3xl leading-tight font-extrabold tracking-tight">
                {{ project.title }}
            </h1>
            <p class="max-w-prose text-muted-foreground">
                {{ project.summary }}
            </p>
            <p v-if="project.stack" class="text-sm text-muted-foreground">
                Stack:
                <Link
                    :href="stackShow(project.stack.slug)"
                    class="text-primary underline-offset-4 hover:underline"
                    >{{ project.stack.name }}</Link
                >
            </p>
        </header>

        <section class="flex flex-col gap-3">
            <div class="flex items-center justify-between gap-3">
                <h2 class="text-xl font-semibold">Hitos</h2>
                <span
                    class="font-mono text-sm text-muted-foreground tabular-nums"
                    >{{ project.progress.done }}/{{
                        project.progress.total
                    }}</span
                >
            </div>
            <ProgressBar
                :value="project.progress.pct"
                :label="`Hitos de ${project.title}`"
            />

            <ol class="flex flex-col gap-3">
                <li
                    v-for="milestone in milestones"
                    :key="milestone.id"
                    class="rounded-xl border bg-card p-4"
                    :class="milestone.completed ? 'border-emerald-600' : ''"
                >
                    <CompletionToggle
                        type="milestone"
                        :id="milestone.id"
                        :completed="milestone.completed"
                    >
                        <span class="font-semibold">{{ milestone.title }}</span>
                    </CompletionToggle>
                    <p class="mt-2 pl-7 text-muted-foreground">
                        <InlineCode :text="milestone.detail" />
                    </p>
                </li>
            </ol>
        </section>

        <section class="flex flex-col gap-3 rounded-xl bg-accent p-4">
            <h2 class="font-semibold">Qué debes tener al final</h2>
            <ul class="flex list-disc flex-col gap-1 pl-5">
                <li v-for="item in project.deliverables" :key="item">
                    <InlineCode :text="item" />
                </li>
            </ul>
        </section>

        <section v-if="project.stretch.length > 0" class="flex flex-col gap-2">
            <h2 class="text-xl font-semibold">Para ir más lejos</h2>
            <p class="text-sm text-muted-foreground">
                Opcional. Hazlo cuando los hitos estén completos.
            </p>
            <ul class="flex list-disc flex-col gap-1 pl-5">
                <li v-for="item in project.stretch" :key="item">
                    <InlineCode :text="item" />
                </li>
            </ul>
        </section>

        <section class="flex flex-col gap-3 border-t pt-4">
            <h2 class="text-xl font-semibold">Módulos que practicas</h2>
            <p class="text-sm text-muted-foreground">
                Si te atascas, vuelve a la lección. Tu progreso en cada uno:
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
                    <div>
                        <Button variant="outline" size="sm" as-child>
                            <Link :href="learnModule(module.slug)"
                                >Aprender</Link
                            >
                        </Button>
                    </div>
                </li>
            </ul>
        </section>
    </div>
</template>
