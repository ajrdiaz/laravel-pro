<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { Checkbox } from '@/components/ui/checkbox';
import { update } from '@/routes/completions';

const props = defineProps<{
    type:
        | 'concept'
        | 'challenge'
        | 'interview_question'
        | 'lesson'
        | 'milestone';
    id: number;
    completed: boolean;
}>();

function toggle(checked: boolean | 'indeterminate') {
    router.put(
        update.url({ type: props.type, id: props.id }),
        { completed: checked === true },
        { preserveScroll: true, preserveState: true },
    );
}
</script>

<template>
    <label class="flex cursor-pointer items-start gap-3">
        <Checkbox
            class="mt-1"
            :model-value="completed"
            @update:model-value="toggle"
        />
        <span :class="completed ? 'text-muted-foreground' : ''">
            <slot />
        </span>
    </label>
</template>
