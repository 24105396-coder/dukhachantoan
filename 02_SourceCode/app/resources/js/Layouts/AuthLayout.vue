<script setup>
import { Link } from '@inertiajs/vue3';
import AppLogo from '@/Components/AppLogo.vue';
import FlashToast from '@/Components/FlashToast.vue';
import ThemeToggle from '@/Components/ThemeToggle.vue';
import LocaleSwitch from '@/Components/LocaleSwitch.vue';
import AppFooter from '@/Components/AppFooter.vue';
import { useT } from '@/Composables/useT';
import { useLocaleRoute } from '@/Composables/useLocaleRoute';

const { t } = useT();
const lr = useLocaleRoute();
</script>

<template>
    <div class="flex min-h-screen flex-col bg-bg">
        <header class="container-page flex h-16 items-center justify-between">
            <Link :href="lr('/')" class="rounded-lg" :aria-label="t('nav.home_logo')"><AppLogo /></Link>
            <div class="flex items-center gap-1">
                <LocaleSwitch />
                <ThemeToggle />
            </div>
        </header>
        <FlashToast />
        <main id="main" class="flex flex-1 items-start justify-center px-4 py-8 md:items-center">
            <div class="w-full max-w-md">
                <slot />
            </div>
        </main>
        <AppFooter compact />
    </div>
</template>
