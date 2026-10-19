<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Clock } from '@lucide/vue';
import ProgressBar from '@/components/study/ProgressBar.vue';
import { Badge } from '@/components/ui/badge';
import { index, show } from '@/routes/projects';
import type { ProjectSummary } from '@/types';

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
    projects: ProjectSummary[];
}>();
</script>

<template>
    <Head title="Proyectos" />

    <div class="mx-auto flex w-full max-w-3xl flex-col gap-5 p-4">
        <header class="flex flex-col gap-3">
            <span class="font-mono text-xs text-primary"
                >$ git commit -m "portafolio"</span
            >
            <h1 class="text-3xl font-extrabold tracking-tight">Proyectos</h1>
            <p class="max-w-prose text-muted-foreground">
                Leer no basta: un portafolio con proyectos reales es lo que
                enseñas en una entrevista. Cada proyecto viene dividido en hitos
                que puedes ir marcando, con lo que debes tener al final para
                mostrarlo. No cuentan en el progreso de la ruta: son práctica
                aparte.
            </p>
        </header>

        <ul class="flex flex-col gap-3">
            <li v-for="project in projects" :key="project.slug">
                <Link
                    :href="show(project.slug)"
                    class="flex flex-col gap-3 rounded-xl border bg-card p-4 transition-colors hover:border-primary focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none"
                    :class="
                        project.progress.total > 0 &&
                        project.progress.done === project.progress.total
                            ? 'border-emerald-600'
                            : ''
                    "
                >
                    <span class="flex flex-wrap items-center gap-2">
                        <Badge variant="secondary">{{
                            project.level.label
                        }}</Badge>
                        <span
                            class="inline-flex items-center gap-1 text-sm text-muted-foreground"
                        >
                            <Clock class="size-3.5" aria-hidden="true" />
                            unas {{ project.hours }} h
                        </span>
                        <span
                            v-if="project.stack"
                            class="text-sm text-muted-foreground"
                            >· {{ project.stack.name }}</span
                        >
                    </span>
                    <span class="text-lg leading-snug font-semibold">{{
                        project.title
                    }}</span>
                    <span class="text-muted-foreground">{{
                        project.summary
                    }}</span>
                    <span class="flex items-center gap-3">
                        <span class="flex-1">
                            <ProgressBar
                                :value="project.progress.pct"
                                :label="`Hitos de ${project.title}`"
                            />
                        </span>
                        <span
                            class="font-mono text-sm text-muted-foreground tabular-nums"
                            >{{ project.progress.done }}/{{
                                project.progress.total
                            }}
                            hitos</span
                        >
                    </span>
                </Link>
            </li>
        </ul>
    </div>
</template>
