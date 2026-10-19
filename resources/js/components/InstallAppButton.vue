<script setup lang="ts">
import { Download } from '@lucide/vue';
import {
    SidebarGroup,
    SidebarGroupContent,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { canInstall, installApp } from '@/lib/pwa';
</script>

<template>
    <!-- Only when the browser says the app can be installed (Chrome, Edge, Android). -->
    <SidebarGroup v-if="canInstall" class="group-data-[collapsible=icon]:p-0">
        <SidebarGroupContent>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton
                        tooltip="Instalar la app"
                        data-test="install-app"
                        @click="installApp"
                    >
                        <Download />
                        <span>Instalar app</span>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarGroupContent>
    </SidebarGroup>
</template>
