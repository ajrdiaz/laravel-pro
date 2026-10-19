<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import {
    BookOpen,
    Boxes,
    Briefcase,
    FolderGit2,
    GraduationCap,
    Layers,
    ListChecks,
    Mic,
    Route,
    SquareTerminal,
} from '@lucide/vue';
import { computed } from 'vue';
import AppLogo from '@/components/AppLogo.vue';
import InstallAppButton from '@/components/InstallAppButton.vue';
import NavFooter from '@/components/NavFooter.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { dashboard } from '@/routes';
import { index as challenges } from '@/routes/challenges';
import { index as flashcards } from '@/routes/flashcards';
import { index as interview } from '@/routes/interview';
import { index as jobs } from '@/routes/jobs';
import { index as learn } from '@/routes/learn';
import { index as projects } from '@/routes/projects';
import { index as quiz } from '@/routes/quiz';
import { index as stacks } from '@/routes/stacks';
import type { NavItem } from '@/types';

const page = usePage();

const mainNavItems = computed<NavItem[]>(() => [
    {
        title: 'Ruta',
        href: dashboard(),
        icon: Route,
    },
    {
        title: 'Aprender',
        href: learn(),
        icon: GraduationCap,
    },
    {
        title: 'Tarjetas',
        href: flashcards(),
        icon: Layers,
        badge: page.props.stats?.due_cards,
    },
    {
        title: 'Quiz',
        href: quiz(),
        icon: ListChecks,
    },
    {
        title: 'Retos sin IA',
        href: challenges(),
        icon: SquareTerminal,
    },
    {
        title: 'Entrevista',
        href: interview(),
        icon: Mic,
    },
    {
        title: 'Stacks',
        href: stacks(),
        icon: Boxes,
    },
    {
        title: 'Proyectos',
        href: projects(),
        icon: FolderGit2,
    },
    {
        title: 'Ofertas',
        href: jobs(),
        icon: Briefcase,
    },
]);

const footerNavItems: NavItem[] = [
    {
        title: 'Laravel Docs',
        href: 'https://laravel.com/docs',
        icon: BookOpen,
    },
    {
        title: 'Inertia Docs',
        href: 'https://inertiajs.com/docs',
        icon: BookOpen,
    },
    {
        title: 'Vue Docs',
        href: 'https://vuejs.org/guide/introduction.html',
        icon: BookOpen,
    },
];
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="dashboard()">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <NavMain :items="mainNavItems" />
        </SidebarContent>

        <SidebarFooter>
            <InstallAppButton />
            <NavFooter :items="footerNavItems" />
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
