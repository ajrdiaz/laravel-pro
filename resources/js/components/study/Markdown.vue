<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import hljs from 'highlight.js/lib/core';
import bash from 'highlight.js/lib/languages/bash';
import css from 'highlight.js/lib/languages/css';
import ini from 'highlight.js/lib/languages/ini';
import javascript from 'highlight.js/lib/languages/javascript';
import json from 'highlight.js/lib/languages/json';
import php from 'highlight.js/lib/languages/php';
import sql from 'highlight.js/lib/languages/sql';
import typescript from 'highlight.js/lib/languages/typescript';
import xml from 'highlight.js/lib/languages/xml';
import yaml from 'highlight.js/lib/languages/yaml';
import { nextTick, onMounted, useTemplateRef, watch } from 'vue';

hljs.registerLanguage('bash', bash);
hljs.registerLanguage('css', css);
hljs.registerLanguage('ini', ini);
hljs.registerLanguage('javascript', javascript);
hljs.registerLanguage('json', json);
hljs.registerLanguage('php', php);
hljs.registerLanguage('sql', sql);
hljs.registerLanguage('typescript', typescript);
hljs.registerLanguage('xml', xml);
hljs.registerLanguage('yaml', yaml);
// Vue single-file components are HTML-like, so they highlight well as XML.
hljs.registerAliases('vue', { languageName: 'xml' });

const props = defineProps<{
    /** HTML produced by the server from trusted Markdown, with raw HTML already stripped. */
    html: string;
}>();

const root = useTemplateRef<HTMLElement>('root');

function enhance() {
    root.value
        ?.querySelectorAll<HTMLElement>('pre code')
        .forEach((block) => hljs.highlightElement(block));

    // External links open in a new tab without leaking the opener.
    root.value
        ?.querySelectorAll<HTMLAnchorElement>('a[href^="http"]')
        .forEach((link) => {
            link.target = '_blank';
            link.rel = 'noopener noreferrer';
        });
}

onMounted(enhance);

watch(
    () => props.html,
    async () => {
        await nextTick();
        enhance();
    },
);

// Links to other pages of this app navigate like any Inertia link instead of reloading.
function onClick(event: MouseEvent) {
    const link = (event.target as HTMLElement).closest('a');
    const href = link?.getAttribute('href');

    if (
        !href ||
        !href.startsWith('/') ||
        href.startsWith('//') ||
        event.button !== 0 ||
        event.metaKey ||
        event.ctrlKey ||
        event.shiftKey
    ) {
        return;
    }

    event.preventDefault();
    router.visit(href);
}
</script>

<template>
    <!-- eslint-disable-next-line vue/no-v-html -- the server strips raw HTML from the Markdown -->
    <div
        ref="root"
        class="lesson-content prose max-w-none prose-neutral dark:prose-invert"
        @click="onClick"
        v-html="html"
    />
</template>
