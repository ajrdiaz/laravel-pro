<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import CommandLabel from '@/components/study/CommandLabel.vue';
import ProgressBar from '@/components/study/ProgressBar.vue';
import { groupBySection } from '@/lib/sections';
import { index, module } from '@/routes/learn';
import type { LearnModuleSummary } from '@/types';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Aprender',
                href: index(),
            },
        ],
    },
});

const props = defineProps<{
    modules: LearnModuleSummary[];
}>();

const sections = computed(() => groupBySection(props.modules));

function readPct(read: number, total: number): number {
    return total > 0 ? Math.round((read * 100) / total) : 0;
}
</script>

<template>
    <Head title="Aprender" />

    <div class="mx-auto flex w-full max-w-3xl flex-col gap-5 p-4">
        <header class="flex flex-col gap-3">
            <span class="font-mono text-xs text-primary"
                >$ php artisan learn</span
            >
            <h1 class="text-3xl font-extrabold tracking-tight">Aprender</h1>
            <p class="max-w-prose text-muted-foreground">
                Para cada módulo, una lección propia y recursos curados:
                documentación oficial, cursos y videos. La mayoría están en
                inglés a propósito: es el idioma de la documentación y de las
                entrevistas.
            </p>
        </header>

        <section
            v-for="section in sections"
            :key="section.name"
            class="flex flex-col gap-3"
        >
            <h2
                class="text-sm font-semibold tracking-wider text-muted-foreground uppercase"
            >
                {{ section.name }}
            </h2>
            <ul class="flex flex-col gap-3">
                <li v-for="item in section.items" :key="item.slug">
                    <Link
                        :href="module(item.slug)"
                        class="grid grid-cols-[auto_minmax(0,1fr)] gap-x-3 gap-y-2 rounded-xl border bg-card p-4 transition-colors hover:border-primary focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none"
                    >
                        <span
                            class="w-6 text-xl font-extrabold text-muted-foreground tabular-nums"
                            >{{ item.position }}</span
                        >
                        <span class="flex min-w-0 flex-col">
                            <CommandLabel :command="item.command" />
                            <span class="font-semibold">{{ item.title }}</span>
                        </span>
                        <span class="col-start-2 text-sm text-muted-foreground">
                            {{ item.lessons.total }}
                            {{
                                item.lessons.total === 1
                                    ? 'lección'
                                    : 'lecciones'
                            }}
                            ({{ item.lessons.read }} leídas) ·
                            {{ item.resources }} recursos
                        </span>
                        <div class="col-start-2">
                            <ProgressBar
                                :value="
                                    readPct(
                                        item.lessons.read,
                                        item.lessons.total,
                                    )
                                "
                                :label="`Lecciones leídas de ${item.title}`"
                            />
                        </div>
                    </Link>
                </li>
            </ul>
        </section>
    </div>
</template>
