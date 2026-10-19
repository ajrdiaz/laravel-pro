<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ChevronDown } from '@lucide/vue';
import { computed, ref } from 'vue';
import CommandLabel from '@/components/study/CommandLabel.vue';
import CompletionToggle from '@/components/study/CompletionToggle.vue';
import InlineCode from '@/components/study/InlineCode.vue';
import ProgressBar from '@/components/study/ProgressBar.vue';
import StatsRow from '@/components/study/StatsRow.vue';
import { Button } from '@/components/ui/button';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
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
import { groupBySection } from '@/lib/sections';
import { dashboard } from '@/routes';
import { index as flashcards } from '@/routes/flashcards';
import { module as learnModule } from '@/routes/learn';
import { destroy } from '@/routes/progress';
import { index as quiz } from '@/routes/quiz';
import type { RoadmapModule } from '@/types';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Ruta',
                href: dashboard(),
            },
        ],
    },
});

const props = defineProps<{
    modules: RoadmapModule[];
}>();

const sections = computed(() => groupBySection(props.modules));

const open = ref<number[]>(props.modules.slice(0, 1).map((m) => m.id));

function setOpen(id: number, value: boolean) {
    open.value = value
        ? [...open.value, id]
        : open.value.filter((openId) => openId !== id);
}

const confirmingReset = ref(false);

function resetProgress() {
    router.delete(destroy.url(), {
        preserveScroll: true,
        onSuccess: () => (confirmingReset.value = false),
    });
}
</script>

<template>
    <Head title="Ruta" />

    <div class="mx-auto flex w-full max-w-3xl flex-col gap-5 p-4">
        <header class="flex flex-col gap-3">
            <span class="font-mono text-xs text-primary"
                >$ php artisan study</span
            >
            <h1 class="text-3xl font-extrabold tracking-tight">Ruta Laravel</h1>
            <p class="max-w-prose text-muted-foreground">
                Repasa lo que la IA te está haciendo por ti. Rutina sugerida: 10
                minutos de tarjetas cada día, un módulo por semana, una pregunta
                de entrevista en inglés en voz alta y un reto sin IA el fin de
                semana.
            </p>
            <StatsRow />
        </header>

        <p class="text-muted-foreground">
            Marca un concepto solo cuando puedas explicarlo en voz alta, sin
            mirar la documentación ni preguntarle a la IA.
        </p>

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
            <Collapsible
                v-for="module in section.items"
                :key="module.id"
                :open="open.includes(module.id)"
                class="rounded-xl border bg-card"
                @update:open="setOpen(module.id, $event)"
            >
                <CollapsibleTrigger
                    class="group grid w-full grid-cols-[auto_minmax(0,1fr)_auto] items-center gap-x-3 gap-y-2 rounded-xl p-4 text-left"
                >
                    <span
                        class="w-6 text-xl font-extrabold text-muted-foreground tabular-nums"
                        >{{ module.position }}</span
                    >
                    <span class="flex min-w-0 flex-col">
                        <CommandLabel :command="module.command" />
                        <span class="font-semibold">{{ module.title }}</span>
                    </span>
                    <span class="flex items-center gap-2">
                        <span
                            class="font-mono text-sm text-muted-foreground tabular-nums"
                            >{{ module.progress.pct }}%</span
                        >
                        <ChevronDown
                            class="size-4 text-muted-foreground transition-transform group-data-[state=open]:rotate-180 motion-reduce:transition-none"
                        />
                    </span>
                    <div
                        class="col-span-3 col-start-1 sm:col-span-2 sm:col-start-2"
                    >
                        <ProgressBar
                            :value="module.progress.pct"
                            :label="`Progreso de ${module.title}`"
                        />
                    </div>
                </CollapsibleTrigger>

                <CollapsibleContent class="flex flex-col gap-4 px-4 pb-4">
                    <h3
                        class="text-xs font-semibold tracking-wider text-muted-foreground uppercase"
                    >
                        Lo puedo explicar sin ayuda
                    </h3>
                    <ul class="flex flex-col gap-2">
                        <li
                            v-for="concept in module.concepts"
                            :key="concept.id"
                        >
                            <CompletionToggle
                                type="concept"
                                :id="concept.id"
                                :completed="concept.completed"
                            >
                                <InlineCode :text="concept.body" />
                            </CompletionToggle>
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
                                >Tarjetas del módulo</Link
                            >
                        </Button>
                        <Button variant="outline" size="sm" as-child>
                            <Link
                                :href="quiz({ query: { module: module.slug } })"
                                >Quiz del módulo</Link
                            >
                        </Button>
                    </div>
                </CollapsibleContent>
            </Collapsible>
        </section>

        <footer
            class="flex flex-wrap items-center justify-between gap-3 border-t pt-4 text-sm"
        >
            <span class="text-muted-foreground"
                >Tu progreso se guarda en tu cuenta.</span
            >
            <Dialog v-model:open="confirmingReset">
                <DialogTrigger as-child>
                    <Button variant="outline" size="sm">Borrar progreso</Button>
                </DialogTrigger>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>¿Borrar todo tu progreso?</DialogTitle>
                        <DialogDescription>
                            Se borran los conceptos marcados, las lecciones
                            leídas, el nivel de las tarjetas, los quizzes
                            acertados, los retos, las preguntas practicadas, los
                            hitos de tus proyectos y tu racha. Tus notas y las
                            ofertas que analizaste se conservan. Esta acción no
                            se puede deshacer.
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter class="gap-2">
                        <DialogClose as-child>
                            <Button variant="outline">Cancelar</Button>
                        </DialogClose>
                        <Button variant="destructive" @click="resetProgress"
                            >Sí, borrar</Button
                        >
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </footer>
    </div>
</template>
