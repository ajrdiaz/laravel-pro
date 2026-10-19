<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, ArrowRight, Clock } from '@lucide/vue';
import CommandLabel from '@/components/study/CommandLabel.vue';
import CompletionToggle from '@/components/study/CompletionToggle.vue';
import Markdown from '@/components/study/Markdown.vue';
import NoteEditor from '@/components/study/NoteEditor.vue';
import { Button } from '@/components/ui/button';
import { index as challenges } from '@/routes/challenges';
import { index as flashcards } from '@/routes/flashcards';
import {
    index,
    lesson as lessonRoute,
    module as moduleRoute,
} from '@/routes/learn';
import { index as quiz } from '@/routes/quiz';
import type { LearnModuleHeader, LessonDetail, LessonLink } from '@/types';

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
    lesson: LessonDetail;
    previous: LessonLink | null;
    next: LessonLink | null;
}>();
</script>

<template>
    <Head :title="lesson.title" />

    <article class="mx-auto flex w-full max-w-3xl flex-col gap-6 p-4">
        <Link
            :href="moduleRoute(module.slug)"
            class="inline-flex w-fit items-center gap-1 text-sm text-muted-foreground hover:text-foreground"
        >
            <ArrowLeft class="size-4" /> {{ module.title }}
        </Link>

        <header class="flex flex-col gap-3">
            <CommandLabel :command="module.command" />
            <h1 class="text-3xl leading-tight font-extrabold tracking-tight">
                {{ lesson.title }}
            </h1>
            <p class="max-w-prose text-muted-foreground">
                {{ lesson.summary }}
            </p>
            <span
                class="inline-flex items-center gap-1 text-sm text-muted-foreground"
            >
                <Clock class="size-3.5" aria-hidden="true" />
                {{ lesson.minutes }} min de lectura
            </span>
        </header>

        <Markdown :html="lesson.html" />

        <section
            class="flex flex-col gap-4 rounded-xl border bg-card p-4"
            :class="lesson.completed ? 'border-emerald-600' : ''"
        >
            <CompletionToggle
                type="lesson"
                :id="lesson.id"
                :completed="lesson.completed"
            >
                Marcar esta lección como leída
            </CompletionToggle>
            <div class="flex flex-wrap gap-2">
                <Button variant="outline" size="sm" as-child>
                    <Link :href="flashcards({ query: { module: module.slug } })"
                        >Tarjetas del módulo</Link
                    >
                </Button>
                <Button variant="outline" size="sm" as-child>
                    <Link :href="quiz({ query: { module: module.slug } })"
                        >Quiz del módulo</Link
                    >
                </Button>
                <Button variant="outline" size="sm" as-child>
                    <Link :href="challenges()">Retos sin IA</Link>
                </Button>
            </div>
        </section>

        <NoteEditor
            :key="module.slug"
            :module-slug="module.slug"
            :note="note"
        />

        <nav
            v-if="previous || next"
            class="flex flex-wrap justify-between gap-3"
            aria-label="Lecciones del módulo"
        >
            <Button v-if="previous" variant="outline" as-child>
                <Link
                    :href="
                        lessonRoute({
                            module: module.slug,
                            lesson: previous.slug,
                        })
                    "
                >
                    <ArrowLeft aria-hidden="true" /> {{ previous.title }}
                </Link>
            </Button>
            <span v-else />
            <Button v-if="next" variant="outline" as-child>
                <Link
                    :href="
                        lessonRoute({ module: module.slug, lesson: next.slug })
                    "
                >
                    {{ next.title }} <ArrowRight aria-hidden="true" />
                </Link>
            </Button>
        </nav>
    </article>
</template>
