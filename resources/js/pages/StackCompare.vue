<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ArrowLeft } from '@lucide/vue';
import { reactive, watch } from 'vue';
import { Badge } from '@/components/ui/badge';
import InlineCode from '@/components/study/InlineCode.vue';
import { Label } from '@/components/ui/label';
import { compare, index, show } from '@/routes/stacks';
import type { CompareColumn } from '@/types';

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
    options: { slug: string; name: string }[];
    selected: string[];
    columns: CompareColumn[];
    layers: { layer: string; label: string }[];
}>();

// Three pickers; an empty one just means "no third stack".
const picks = reactive<string[]>([
    props.selected[0] ?? '',
    props.selected[1] ?? '',
    props.selected[2] ?? '',
]);

// The server drops repeated and unknown stacks, so show what it actually compared.
watch(
    () => props.selected,
    (selected) => {
        for (const position of picks.keys()) {
            picks[position] = selected[position] ?? '';
        }
    },
);

function update() {
    const stacks = picks.filter((slug) => slug !== '');

    router.get(
        compare.url(stacks.length > 0 ? { query: { stacks } } : {}),
        {},
        { preserveScroll: true, preserveState: true },
    );
}

const listRows: {
    label: string;
    key: 'when_to_use' | 'when_to_avoid' | 'pros' | 'cons';
}[] = [
    { label: 'Elígelo cuando', key: 'when_to_use' },
    { label: 'Evítalo cuando', key: 'when_to_avoid' },
    { label: 'Ventajas', key: 'pros' },
    { label: 'Costos', key: 'cons' },
];
</script>

<template>
    <Head title="Comparar stacks" />

    <div class="mx-auto flex w-full max-w-5xl flex-col gap-6 p-4">
        <Link
            :href="index()"
            class="inline-flex w-fit items-center gap-1 text-sm text-muted-foreground hover:text-foreground"
        >
            <ArrowLeft class="size-4" /> Todos los stacks
        </Link>

        <header class="flex flex-col gap-3">
            <h1 class="text-3xl font-extrabold tracking-tight">
                Comparar stacks
            </h1>
            <p class="max-w-prose text-muted-foreground">
                Elige dos o tres y míralos capa por capa. La última fila dice
                qué tan preparado estás para cada uno según tu progreso.
            </p>
        </header>

        <div class="grid gap-3 sm:grid-cols-3">
            <div
                v-for="(_, position) in picks"
                :key="position"
                class="grid gap-2"
            >
                <Label :for="`stack-${position}`">
                    {{
                        position < 2
                            ? `Stack ${position + 1}`
                            : 'Stack 3 (opcional)'
                    }}
                </Label>
                <select
                    :id="`stack-${position}`"
                    v-model="picks[position]"
                    class="h-9 w-full rounded-md border border-input bg-transparent px-3 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 dark:bg-input/30"
                    @change="update"
                >
                    <option value="">— Ninguno —</option>
                    <option
                        v-for="option in options"
                        :key="option.slug"
                        :value="option.slug"
                    >
                        {{ option.name }}
                    </option>
                </select>
            </div>
        </div>

        <p
            v-if="columns.length === 0"
            class="rounded-xl border border-dashed bg-card p-6 text-muted-foreground"
        >
            Elige al menos dos stacks para ver la comparación.
        </p>

        <div v-else class="overflow-x-auto rounded-xl border">
            <table
                class="w-full min-w-[40rem] border-collapse text-left text-sm"
            >
                <thead>
                    <tr class="border-b bg-muted/50">
                        <th
                            scope="col"
                            class="p-3 font-medium text-muted-foreground"
                        >
                            <span class="sr-only">Capa</span>
                        </th>
                        <th
                            v-for="column in columns"
                            :key="column.slug"
                            scope="col"
                            class="min-w-48 p-3 align-top"
                        >
                            <Link
                                :href="show(column.slug)"
                                class="font-semibold text-primary underline-offset-4 hover:underline"
                                >{{ column.name }}</Link
                            >
                            <div class="mt-2 flex flex-wrap gap-1 font-normal">
                                <Badge
                                    v-for="item in column.headline"
                                    :key="item"
                                    variant="secondary"
                                    >{{ item }}</Badge
                                >
                            </div>
                        </th>
                    </tr>
                </thead>
                <tbody class="[&>tr]:border-b [&>tr:last-child]:border-b-0">
                    <tr>
                        <th
                            scope="row"
                            class="p-3 align-top font-medium text-muted-foreground"
                        >
                            Resumen
                        </th>
                        <td
                            v-for="column in columns"
                            :key="column.slug"
                            class="p-3 align-top"
                        >
                            <InlineCode :text="column.summary" />
                        </td>
                    </tr>

                    <tr v-for="layer in layers" :key="layer.layer">
                        <th
                            scope="row"
                            class="p-3 align-top font-medium text-muted-foreground"
                        >
                            {{ layer.label }}
                        </th>
                        <td
                            v-for="column in columns"
                            :key="column.slug"
                            class="p-3 align-top"
                        >
                            <ul
                                v-if="column.layers[layer.layer]"
                                class="flex flex-col gap-1"
                            >
                                <li
                                    v-for="name in column.layers[layer.layer]"
                                    :key="name"
                                >
                                    <InlineCode :text="name" />
                                </li>
                            </ul>
                            <span v-else class="text-muted-foreground">—</span>
                        </td>
                    </tr>

                    <tr v-for="row in listRows" :key="row.key">
                        <th
                            scope="row"
                            class="p-3 align-top font-medium text-muted-foreground"
                        >
                            {{ row.label }}
                        </th>
                        <td
                            v-for="column in columns"
                            :key="column.slug"
                            class="p-3 align-top"
                        >
                            <ul class="flex list-disc flex-col gap-1 pl-4">
                                <li v-for="item in column[row.key]" :key="item">
                                    <InlineCode :text="item" />
                                </li>
                            </ul>
                        </td>
                    </tr>

                    <tr class="bg-muted/30">
                        <th
                            scope="row"
                            class="p-3 align-top font-medium text-muted-foreground"
                        >
                            Tu preparación
                        </th>
                        <td
                            v-for="column in columns"
                            :key="column.slug"
                            class="p-3 align-top"
                        >
                            <span class="text-2xl font-extrabold tabular-nums"
                                >{{ column.readiness }}%</span
                            >
                            <span class="block text-muted-foreground"
                                >de {{ column.modules }} módulos</span
                            >
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
