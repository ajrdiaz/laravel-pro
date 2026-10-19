<script setup lang="ts">
import { ExternalLink, Play } from '@lucide/vue';
import { ref } from 'vue';
import InlineCode from '@/components/study/InlineCode.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import type { ResourceItem } from '@/types';

defineProps<{
    resource: ResourceItem;
}>();

// The video is only requested from YouTube once the user asks for it.
const playing = ref(false);
</script>

<template>
    <div class="flex flex-col gap-3 rounded-xl border bg-card p-4">
        <div class="flex flex-col gap-1">
            <a
                :href="resource.url"
                target="_blank"
                rel="noopener noreferrer"
                class="inline-flex w-fit items-center gap-1.5 font-semibold text-primary underline-offset-4 hover:underline"
            >
                {{ resource.title }}
                <ExternalLink class="size-3.5 shrink-0" aria-hidden="true" />
                <span class="sr-only">(se abre en una pestaña nueva)</span>
            </a>
            <div class="flex flex-wrap items-center gap-2">
                <span class="text-sm text-muted-foreground">{{
                    resource.provider
                }}</span>
                <Badge variant="outline">{{ resource.language }}</Badge>
                <Badge v-if="resource.is_free" variant="secondary"
                    >Gratis</Badge
                >
                <Badge v-else variant="outline">De pago</Badge>
            </div>
        </div>

        <p class="text-muted-foreground">
            <InlineCode :text="resource.note" />
        </p>

        <template v-if="resource.embed_url">
            <div
                v-if="playing"
                class="aspect-video w-full overflow-hidden rounded-lg border bg-muted"
            >
                <iframe
                    :src="resource.embed_url"
                    :title="resource.title"
                    class="size-full"
                    loading="lazy"
                    allow="
                        accelerometer;
                        encrypted-media;
                        gyroscope;
                        picture-in-picture;
                        web-share;
                    "
                    referrerpolicy="strict-origin-when-cross-origin"
                    allowfullscreen
                />
            </div>
            <div v-else>
                <Button variant="outline" size="sm" @click="playing = true">
                    <Play aria-hidden="true" /> Ver aquí
                </Button>
            </div>
        </template>
    </div>
</template>
