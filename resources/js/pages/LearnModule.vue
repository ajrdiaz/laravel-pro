<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, CircleCheck, Clock } from '@lucide/vue';
import CommandLabel from '@/components/study/CommandLabel.vue';
import NoteEditor from '@/components/study/NoteEditor.vue';
import ResourceCard from '@/components/study/ResourceCard.vue';
import { Button } from '@/components/ui/button';
import { index as challenges } from '@/routes/challenges';
import { index as flashcards } from '@/routes/flashcards';
import { index, lesson as lessonRoute } from '@/routes/learn';
import { index as quiz } from '@/routes/quiz';
import type { LearnModuleHeader, LessonSummary, ResourceGroup } from '@/types';

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

defineProps<{
    module: LearnModuleHeader;
    note: string | null;
    lessons: LessonSummary[];
    resources: ResourceGroup[];
}>();
</script>

<template>
    <Head :title="`Aprender: ${module.title}`" />

    <div class="mx-auto flex w-full max-w-3xl flex-col gap-6 p-4">
        <Link
            :href="index()"
            class="inline-flex w-fit items-center gap-1 text-sm text-muted-foreground hover:text-foreground"
        >
            <ArrowLeft class="size-4" /> Todos los módulos
        </Link>

        <header class="flex flex-col gap-2">
            <CommandLabel :command="module.command" />
            <h1 class="text-3xl leading-tight font-extrabold tracking-tight">
                {{ module.title }}
            </h1>
        </header>

        <section class="flex flex-col gap-3">
            <h2 class="text-xl font-semibold">Lecciones</h2>
            <ul class="flex flex-col gap-3">
                <li v-for="lesson in lessons" :key="lesson.slug">
                    <Link
                        :href="
                            lessonRoute({
                                module: module.slug,
                                lesson: lesson.slug,
                            })
                        "
                        class="flex flex-col gap-2 rounded-xl border bg-card p-4 transition-colors hover:border-primary focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none"
                        :class="lesson.completed ? 'border-emerald-600' : ''"
                    >
                        <span class="flex items-start justify-between gap-3">
                            <span class="font-semibold">{{
                                lesson.title
                            }}</span>
                            <span
                                v-if="lesson.completed"
                                class="inline-flex shrink-0 items-center gap-1 text-sm text-emerald-700 dark:text-emerald-400"
                            >
                                <CircleCheck
                                    class="size-4"
                                    aria-hidden="true"
                                />
                                Leída
                            </span>
                        </span>
                        <span class="text-muted-foreground">{{
                            lesson.summary
                        }}</span>
                        <span
                            class="inline-flex items-center gap-1 text-sm text-muted-foreground"
                        >
                            <Clock class="size-3.5" aria-hidden="true" />
                            {{ lesson.minutes }} min de lectura
                        </span>
                    </Link>
                </li>
            </ul>
        </section>

        <section
            v-for="group in resources"
            :key="group.kind"
            class="flex flex-col gap-3"
        >
            <h2 class="text-xl font-semibold">{{ group.label }}</h2>
            <div class="flex flex-col gap-3">
                <ResourceCard
                    v-for="item in group.items"
                    :key="item.id"
                    :resource="item"
                />
            </div>
        </section>

        <NoteEditor
            :key="module.slug"
            :module-slug="module.slug"
            :note="note"
        />

        <section class="flex flex-col gap-3 rounded-xl bg-accent p-4">
            <h2 class="font-semibold">Después de leer, practica</h2>
            <div class="flex flex-wrap gap-2">
                <Button variant="outline" size="sm" as-child>
                    <Link :href="flashcards({ query: { module: module.slug } })"
                        >Tarjetas</Link
                    >
                </Button>
                <Button variant="outline" size="sm" as-child>
                    <Link :href="quiz({ query: { module: module.slug } })"
                        >Quiz</Link
                    >
                </Button>
                <Button variant="outline" size="sm" as-child>
                    <Link :href="challenges()">Retos sin IA</Link>
                </Button>
            </div>
        </section>
    </div>
</template>
