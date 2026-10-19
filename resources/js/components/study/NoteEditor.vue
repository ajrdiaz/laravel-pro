<script setup lang="ts">
import { useHttp } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { Textarea } from '@/components/ui/textarea';
import { update } from '@/routes/notes';

const MAX_LENGTH = 20000;
const SAVE_DELAY = 800;

const props = defineProps<{
    moduleSlug: string;
    note: string | null;
}>();

// A request that stays on the page: a visit would reload props and could swallow what
// the user is typing.
const http = useHttp<{ body: string }, { saved: boolean }>(
    () => update(props.moduleSlug),
    { body: props.note ?? '' },
);

// What the server has, to know whether there is anything left to send.
const saved = ref(props.note ?? '');
let timer: ReturnType<typeof setTimeout> | undefined;
const failed = ref(false);

async function save() {
    clearTimeout(timer);

    if (http.body === saved.value || http.processing) {
        return;
    }

    const sending = http.body;
    failed.value = false;

    try {
        await http.submit();
        saved.value = sending;
    } catch {
        // Validation errors show up in `http.errors`; anything else is a lost connection.
        failed.value = !http.hasErrors;
    }

    // The user kept typing while the request was on its way.
    if (http.body !== saved.value && !failed.value && !http.hasErrors) {
        schedule();
    }
}

function schedule() {
    clearTimeout(timer);
    timer = setTimeout(save, SAVE_DELAY);
}

watch(
    () => http.body,
    () => {
        failed.value = false;
        schedule();
    },
);

// Leaving the page should not drop the last few keystrokes.
onBeforeUnmount(save);

const status = computed(() => {
    if (http.errors.body) {
        return http.errors.body;
    }

    if (failed.value) {
        return 'No se pudo guardar. Revisa tu conexión: lo intento de nuevo cuando sigas escribiendo.';
    }

    if (http.processing) {
        return 'Guardando…';
    }

    return http.body === saved.value && http.wasSuccessful ? 'Guardado' : '';
});
</script>

<template>
    <section class="flex flex-col gap-2">
        <div class="flex flex-wrap items-baseline justify-between gap-2">
            <label for="module-note" class="text-xl font-semibold"
                >Mis notas</label
            >
            <span
                class="text-sm"
                :class="
                    http.errors.body || failed
                        ? 'text-destructive'
                        : 'text-muted-foreground'
                "
                role="status"
                aria-live="polite"
                >{{ status }}</span
            >
        </div>
        <p class="text-sm text-muted-foreground">
            Apuntes tuyos sobre este módulo, solo los ves tú. Se guardan solos
            mientras escribes.
        </p>
        <Textarea
            id="module-note"
            v-model="http.body"
            class="min-h-32 font-mono"
            :maxlength="MAX_LENGTH"
            :aria-invalid="http.errors.body ? true : undefined"
            placeholder="Lo que no quieres olvidar, dudas para repasar, comandos…"
            @blur="save"
        />
    </section>
</template>
