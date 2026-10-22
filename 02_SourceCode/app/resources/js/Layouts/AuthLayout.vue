<script setup>
import { Link } from '@inertiajs/vue3';
import AppLogo from '@/Components/AppLogo.vue';
import FlashToast from '@/Components/FlashToast.vue';
import ThemeToggle from '@/Components/ThemeToggle.vue';
import LocaleSwitch from '@/Components/LocaleSwitch.vue';
import AppFooter from '@/Components/AppFooter.vue';
import BackgroundBlobs from '@/Components/BackgroundBlobs.vue';
import { useT } from '@/Composables/useT';
import { useLocaleRoute } from '@/Composables/useLocaleRoute';

/** Khung trang xác thực: thẻ clay giữa trang trên nền bạc hà có blob. */
const { t } = useT();
const lr = useLocaleRoute();
</script>

<template>
    <div class="relative flex min-h-screen flex-col">
        <BackgroundBlobs />
        <header class="container-page relative z-10 flex h-20 items-center justify-between">
            <Link :href="lr('/')" class="rounded-full" :aria-label="t('nav.home_logo')"><AppLogo /></Link>
            <div class="flex items-center gap-1.5">
                <LocaleSwitch />
                <ThemeToggle />
            </div>
        </header>
        <FlashToast />
        <main id="main" class="relative z-10 flex flex-1 items-start justify-center px-4 py-6 md:items-center" tabindex="-1">
            <div class="clay-card w-full max-w-md p-6 md:p-8">
                <slot />
            </div>
        </main>
        <AppFooter compact />
    </div>
</template>
