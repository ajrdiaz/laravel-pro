<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { formatDate } from '@/lib/format';
import { compare, index, show, wizard } from '@/routes/stacks';
import type { StackCategory, StackSummary } from '@/types';

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
    categories: StackCategory[];
    stacks: StackSummary[];
}>();

const selected = ref<string>('all');

const visible = computed(() =>
    selected.value === 'all'
        ? props.stacks
        : props.stacks.filter((stack) => stack.category === selected.value),
);

function categoryLabel(value: string): string {
    return props.categories.find((c) => c.value === value)?.label ?? value;
}
</script>

<template>
    <Head title="Stacks" />

    <div class="mx-auto flex w-full max-w-3xl flex-col gap-5 p-4">
        <header class="flex flex-col gap-3">
            <span class="font-mono text-xs text-primary"
                >$ php artisan stack:choose</span
            >
            <h1 class="text-3xl font-extrabold tracking-tight">Stacks</h1>
            <p class="max-w-prose text-muted-foreground">
                Qué usar según el tipo de aplicación. Cada ficha dice cuándo
                elegirlo y cuándo no, qué tecnología va en cada capa y qué
                módulos de la ruta necesitas dominar antes.
            </p>
            <div class="flex flex-wrap gap-2">
                <Button as-child>
                    <Link :href="wizard()">¿Qué stack uso?</Link>
                </Button>
                <Button variant="outline" as-child>
                    <Link :href="compare()">Comparar stacks</Link>
                </Button>
            </div>
        </header>

        <div
            class="flex flex-wrap gap-2"
            role="group"
            aria-label="Filtrar por tipo de aplicación"
        >
            <Button
                size="sm"
                :variant="selected === 'all' ? 'default' : 'outline'"
                :aria-pressed="selected === 'all'"
                @click="selected = 'all'"
            >
                Todos
            </Button>
            <Button
                v-for="category in categories"
                :key="category.value"
                size="sm"
                :variant="selected === category.value ? 'default' : 'outline'"
                :aria-pressed="selected === category.value"
                @click="selected = category.value"
            >
                {{ category.label }}
            </Button>
        </div>

        <ul class="flex flex-col gap-3">
            <li v-for="stack in visible" :key="stack.slug">
                <Link
                    :href="show(stack.slug)"
                    class="flex flex-col gap-3 rounded-xl border bg-card p-4 transition-colors hover:border-primary focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none"
                >
                    <span class="text-xs font-medium text-primary">{{
                        categoryLabel(stack.category)
                    }}</span>
                    <h2 class="text-lg leading-snug font-semibold">
                        {{ stack.name }}
                    </h2>
                    <p class="text-muted-foreground">{{ stack.summary }}</p>
                    <div class="flex flex-wrap items-center gap-2">
                        <Badge
                            v-for="item in stack.headline"
                            :key="item"
                            variant="secondary"
                            >{{ item }}</Badge
                        >
                    </div>
                    <span class="text-xs text-muted-foreground"
                        >Revisado el {{ formatDate(stack.reviewed_on) }}</span
                    >
                </Link>
            </li>
        </ul>
    </div>
</template>
