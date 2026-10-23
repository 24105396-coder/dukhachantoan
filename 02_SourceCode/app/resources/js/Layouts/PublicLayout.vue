<script setup>
import { ref, computed, watch, onBeforeUnmount } from 'vue';
import { Link, usePage, router } from '@inertiajs/vue3';
import AppLogo from '@/Components/AppLogo.vue';
import Icon from '@/Components/Icon.vue';
import FlashToast from '@/Components/FlashToast.vue';
import ThemeToggle from '@/Components/ThemeToggle.vue';
import LocaleSwitch from '@/Components/LocaleSwitch.vue';
import AccountMenu from '@/Components/AccountMenu.vue';
import AppFooter from '@/Components/AppFooter.vue';
import RiskStatusBar from '@/Components/RiskStatusBar.vue';
import { useT } from '@/Composables/useT';
import { useLocaleRoute } from '@/Composables/useLocaleRoute';
import { PUBLIC_NAV } from '@/Utils/navigation';

/** Khung trang công khai: thanh đầu trắng viền dưới mảnh, thanh rủi ro quốc gia, chân trang học thuật. */
const page = usePage();
const { t } = useT();
const lr = useLocaleRoute();
const mobileOpen = ref(false);

const footerLinks = computed(() => [...PUBLIC_NAV, { key: 'about', path: '/gioi-thieu' }]);
const currentPath = computed(() => (page.url || '').split('?')[0]);
const isActive = (path) => currentPath.value === lr(path) || currentPath.value.startsWith(`${lr(path)}/`);

const stopNavigate = router.on('navigate', () => (mobileOpen.value = false));
onBeforeUnmount(() => {
    stopNavigate();
    document.body.classList.remove('overflow-hidden');
});
watch(mobileOpen, (v) => document.body.classList.toggle('overflow-hidden', v));
const onKey = (e) => {
    if (e.key === 'Escape') mobileOpen.value = false;
};
</script>

<template>
    <div class="relative flex min-h-screen flex-col" @keydown="onKey">
        <a href="#main" class="clay-btn clay-btn-primary sr-only focus:not-sr-only focus:fixed focus:left-4 focus:top-4 focus:z-[1200]">
            {{ t('common.skip_to_content') }}
        </a>

        <header class="sticky top-0 z-[1000] border-b border-line bg-surface" data-print-hide>
            <div class="container-page">
                <div class="flex h-16 items-center gap-2">
                    <Link :href="lr('/')" class="shrink-0 rounded-lg" :aria-label="t('nav.home_logo')">
                        <AppLogo />
                    </Link>

                    <nav class="ml-2 hidden flex-1 items-center justify-center gap-1 xl:flex" :aria-label="t('nav.main')">
                        <Link
                            v-for="item in PUBLIC_NAV"
                            :key="item.key"
                            :href="lr(item.path)"
                            class="whitespace-nowrap rounded-lg px-3 py-2 text-sm font-semibold transition-colors"
                            :class="isActive(item.path) ? 'is-selected' : 'text-muted hover:bg-soft hover:text-fg'"
                            :aria-current="isActive(item.path) ? 'page' : undefined"
                        >
                            {{ t(item.short) }}
                        </Link>
                    </nav>

                    <div class="ml-auto flex items-center gap-1.5">
                        <LocaleSwitch class="hidden sm:inline-flex" />
                        <ThemeToggle />
                        <div class="hidden md:block"><AccountMenu /></div>
                        <button
                            type="button"
                            class="clay-btn clay-btn-icon xl:hidden"
                            :aria-expanded="mobileOpen ? 'true' : 'false'"
                            aria-controls="mobile-nav"
                            :aria-label="mobileOpen ? t('common.close_menu') : t('common.open_menu')"
                            @click="mobileOpen = !mobileOpen"
                        >
                            <Icon :name="mobileOpen ? 'close' : 'menu'" />
                        </button>
                    </div>
                </div>

                <div v-show="mobileOpen" id="mobile-nav" class="max-h-[calc(100vh-5rem)] overflow-y-auto border-t border-line py-3 xl:hidden">
                    <nav class="grid gap-1 sm:grid-cols-2" :aria-label="t('nav.main')">
                        <Link
                            v-for="item in PUBLIC_NAV"
                            :key="item.key"
                            :href="lr(item.path)"
                            class="flex items-center gap-3 rounded-lg px-3 py-2.5 font-semibold"
                            :class="isActive(item.path) ? 'is-selected' : 'hover:bg-soft'"
                            :aria-current="isActive(item.path) ? 'page' : undefined"
                        >
                            <Icon :name="item.icon" :size="18" />{{ t(`nav.${item.key}`) }}
                        </Link>
                        <Link :href="lr('/gioi-thieu')" class="flex items-center gap-3 rounded-lg px-3 py-2.5 font-semibold hover:bg-soft">
                            <Icon name="info" :size="18" />{{ t('nav.about') }}
                        </Link>
                    </nav>
                    <div class="mt-3 flex flex-wrap items-center gap-2 border-t border-line pt-3">
                        <LocaleSwitch class="sm:hidden" />
                        <div class="md:hidden"><AccountMenu align="left" /></div>
                    </div>
                </div>
            </div>
        </header>

        <RiskStatusBar />
        <FlashToast />

        <main id="main" class="relative z-10 flex-1" tabindex="-1">
            <slot />
        </main>

        <AppFooter :links="footerLinks" />
    </div>
</template>
