<script setup lang="ts">
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import type { ModuleOption } from '@/types';

defineProps<{
    id: string;
    label: string;
    allLabel: string;
    modules: ModuleOption[];
}>();

const model = defineModel<string>({ required: true });
</script>

<template>
    <div class="flex flex-wrap items-center gap-2">
        <Label :for="id" class="text-muted-foreground">{{ label }}</Label>
        <Select v-model="model">
            <SelectTrigger :id="id" class="w-full sm:w-72">
                <SelectValue />
            </SelectTrigger>
            <SelectContent>
                <SelectItem value="all">{{ allLabel }}</SelectItem>
                <SelectItem
                    v-for="module in modules"
                    :key="module.slug"
                    :value="module.slug"
                >
                    {{ module.title }}
                </SelectItem>
            </SelectContent>
        </Select>
    </div>
</template>
