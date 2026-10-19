<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import { Lightbulb } from '@lucide/vue';
import JobAnalysisController from '@/actions/App/Http/Controllers/Study/JobAnalysisController';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { formatDate } from '@/lib/format';
import { index, show } from '@/routes/jobs';
import type { JobAnalysisItem } from '@/types';

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

defineProps<{
    analyses: JobAnalysisItem[];
}>();

function day(iso: string | null): string {
    return iso ? formatDate(iso.slice(0, 10)) : '';
}
</script>

<template>
    <Head title="Ofertas" />

    <div class="mx-auto flex w-full max-w-3xl flex-col gap-6 p-4">
        <header class="flex flex-col gap-3">
            <span class="font-mono text-xs text-primary"
                >$ php artisan jobs:analyze</span
            >
            <h1 class="text-3xl font-extrabold tracking-tight">Ofertas</h1>
            <p class="max-w-prose text-muted-foreground">
                Pega la descripción de una oferta de trabajo y mira qué temas de
                tu ruta pide, cuánto llevas de cada uno y qué se queda fuera. El
                análisis ocurre en tu servidor, sin enviar el texto a ningún
                servicio externo.
            </p>
        </header>

        <Form
            v-bind="JobAnalysisController.store.form()"
            class="flex flex-col gap-4 rounded-xl border bg-card p-4"
            v-slot="{ errors, processing }"
        >
            <div class="grid gap-2">
                <Label for="title">Título (opcional)</Label>
                <Input
                    id="title"
                    name="title"
                    placeholder="Backend Laravel en una fintech"
                    maxlength="120"
                />
                <InputError :message="errors.title" />
            </div>

            <div class="grid gap-2">
                <Label for="body">Texto de la oferta</Label>
                <Textarea
                    id="body"
                    name="body"
                    rows="10"
                    required
                    placeholder="Pega aquí los requisitos y la descripción del puesto…"
                />
                <InputError :message="errors.body" />
            </div>

            <div>
                <Button type="submit" :disabled="processing">{{
                    processing ? 'Analizando…' : 'Analizar oferta'
                }}</Button>
            </div>
        </Form>

        <section
            class="flex flex-col gap-3 rounded-xl bg-accent p-4"
            aria-labelledby="job-tips"
        >
            <h2 id="job-tips" class="flex items-center gap-2 font-semibold">
                <Lightbulb class="size-4 text-primary" aria-hidden="true" />
                Cómo sacarle partido
            </h2>
            <ul class="flex list-disc flex-col gap-1 pl-5 text-sm">
                <li>
                    Pega la oferta completa, con los requisitos y lo que «se
                    valora»: el título del puesto no basta.
                </li>
                <li>
                    Reconoce tecnologías por su nombre. No mide años de
                    experiencia ni el nivel que piden.
                </li>
                <li>
                    Analiza dos o tres ofertas del mismo puesto: lo que se
                    repite en todas es lo que de verdad piden.
                </li>
                <li>
                    Vuelve a abrir un análisis cuando hayas estudiado: se
                    recalcula con tu progreso.
                </li>
                <li>
                    Si una oferta pide algo que ninguna lección cubre, sale en
                    «Fuera de tu ruta», con una pista por dónde empezar.
                </li>
            </ul>
        </section>

        <section v-if="analyses.length > 0" class="flex flex-col gap-3">
            <h2 class="text-xl font-semibold">Tus análisis</h2>
            <ul class="flex flex-col gap-3">
                <li v-for="analysis in analyses" :key="analysis.id">
                    <Link
                        :href="show(analysis.id)"
                        class="flex flex-col gap-1 rounded-xl border bg-card p-4 transition-colors hover:border-primary focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none"
                    >
                        <span class="font-semibold">{{
                            analysis.title ?? 'Oferta sin título'
                        }}</span>
                        <span class="text-sm text-muted-foreground"
                            >{{ day(analysis.created_at) }} ·
                            {{ analysis.skills }}
                            {{
                                analysis.skills === 1
                                    ? 'habilidad detectada'
                                    : 'habilidades detectadas'
                            }}</span
                        >
                    </Link>
                </li>
            </ul>
        </section>
    </div>
</template>
