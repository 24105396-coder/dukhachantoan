<script setup>
import { ref, computed, watch } from 'vue';
import { Link, usePage, router } from '@inertiajs/vue3';
import AppLogo from '@/Components/AppLogo.vue';
import Icon from '@/Components/Icon.vue';
import FlashToast from '@/Components/FlashToast.vue';
import ThemeToggle from '@/Components/ThemeToggle.vue';
import LocaleSwitch from '@/Components/LocaleSwitch.vue';
import AccountMenu from '@/Components/AccountMenu.vue';
import AppFooter from '@/Components/AppFooter.vue';
import { useT } from '@/Composables/useT';
import { useLocaleRoute } from '@/Composables/useLocaleRoute';

const page = usePage();
const { t } = useT();
const lr = useLocaleRoute();
const mobileOpen = ref(false);

const nav = computed(() => [
    { key: 'sites', path: '/di-san' },
    { key: 'artifacts', path: '/hien-vat' },
    { key: 'figures', path: '/nhan-vat' },
    { key: 'timeline', path: '/dong-thoi-gian' },
    { key: 'map', path: '/ban-do' },
    { key: 'tours', path: '/tuyen-tham-quan' },
    { key: 'tags', path: '/the' },
    { key: 'search', path: '/tim-kiem' },
]);

const footerLinks = computed(() => [...nav.value, { key: 'about', path: '/gioi-thieu' }]);
const currentPath = computed(() => (page.url || '').split('?')[0]);
const isActive = (path) => currentPath.value.startsWith(lr(path));

// Đóng menu di động sau mỗi lần điều hướng
router.on('navigate', () => (mobileOpen.value = false));
watch(mobileOpen, (v) => document.body.classList.toggle('overflow-hidden', v));
</script>

<template>
    <div class="flex min-h-screen flex-col">
        <a href="#main" class="sr-only focus:not-sr-only focus:fixed focus:left-4 focus:top-4 focus:z-[1200] btn btn-primary">
            {{ t('common.skip_to_content') }}
        </a>

        <header class="sticky top-0 z-[1000] border-b border-border bg-bg/90 backdrop-blur supports-[backdrop-filter]:bg-bg/80">
            <div class="container-page flex h-16 items-center gap-3">
                <Link :href="lr('/')" class="shrink-0 rounded-lg" :aria-label="t('nav.home_logo')">
                    <AppLogo />
                </Link>

                <nav class="ml-4 hidden flex-1 items-center gap-1 xl:flex" :aria-label="t('nav.main')">
                    <Link
                        v-for="item in nav"
                        :key="item.key"
                        :href="lr(item.path)"
                        class="rounded-full px-3 py-1.5 text-sm font-medium transition-colors hover:bg-surface-2"
                        :class="isActive(item.path) ? 'bg-primary-soft text-primary' : ''"
                        :aria-current="isActive(item.path) ? 'page' : undefined"
                    >
                        {{ t(`nav.${item.key}`) }}
                    </Link>
                </nav>

                <div class="ml-auto flex items-center gap-1">
                    <Link :href="lr('/tim-kiem')" class="btn btn-ghost btn-icon xl:hidden" :aria-label="t('nav.search')">
                        <Icon name="search" />
                    </Link>
                    <LocaleSwitch />
                    <ThemeToggle />
                    <div class="hidden md:block"><AccountMenu /></div>
                    <button
                        type="button"
                        class="btn btn-ghost btn-icon xl:hidden"
                        :aria-expanded="mobileOpen"
                        aria-controls="mobile-nav"
                        :aria-label="mobileOpen ? t('common.close_menu') : t('common.open_menu')"
                        @click="mobileOpen = !mobileOpen"
                    >
                        <Icon :name="mobileOpen ? 'close' : 'menu'" />
                    </button>
                </div>
            </div>

            <div v-show="mobileOpen" id="mobile-nav" class="border-t border-border bg-bg xl:hidden">
                <nav class="container-page grid gap-1 py-3 sm:grid-cols-2" :aria-label="t('nav.main')">
                    <Link
                        v-for="item in nav"
                        :key="item.key"
                        :href="lr(item.path)"
                        class="rounded-lg px-3 py-2.5 font-medium hover:bg-surface-2"
                        :class="isActive(item.path) ? 'bg-primary-soft text-primary' : ''"
                        :aria-current="isActive(item.path) ? 'page' : undefined"
                    >
                        {{ t(`nav.${item.key}`) }}
                    </Link>
                    <Link :href="lr('/gioi-thieu')" class="rounded-lg px-3 py-2.5 font-medium hover:bg-surface-2">{{ t('nav.about') }}</Link>
                </nav>
                <div class="container-page border-t border-border py-3 md:hidden">
                    <AccountMenu />
                </div>
            </div>
        </header>

        <FlashToast />

        <main id="main" class="flex-1" tabindex="-1">
            <slot />
        </main>

        <AppFooter :links="footerLinks" />
    </div>
</template>
