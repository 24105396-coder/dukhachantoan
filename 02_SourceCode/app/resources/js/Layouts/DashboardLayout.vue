<script setup>
import { ref, computed, watch, nextTick, onBeforeUnmount } from 'vue';
import { Link, usePage, router } from '@inertiajs/vue3';
import AppLogo from '@/Components/AppLogo.vue';
import Icon from '@/Components/Icon.vue';
import FlashToast from '@/Components/FlashToast.vue';
import AppFooter from '@/Components/AppFooter.vue';
import ThemeToggle from '@/Components/ThemeToggle.vue';
import LocaleSwitch from '@/Components/LocaleSwitch.vue';
import AccountMenu from '@/Components/AccountMenu.vue';
import BackgroundBlobs from '@/Components/BackgroundBlobs.vue';
import DashboardNavItem from '@/Components/DashboardNavItem.vue';
import { useT } from '@/Composables/useT';
import { useLocaleRoute } from '@/Composables/useLocaleRoute';
import { usePermissions } from '@/Composables/usePermissions';
import { dashboardNavFor, NAV_GROUPS } from '@/Utils/navigation';

/**
 * Khung dashboard (Admin/*, tài khoản, hành trình, thông báo của người đăng nhập):
 * desktop ≥1024px sidebar clay nổi 288px; tablet 768–1023px thanh icon 80px; điện thoại thanh dưới + ngăn kéo.
 */
const page = usePage();
const { t, te } = useT();
const lr = useLocaleRoute();
const { user, role, unread } = usePermissions();
const drawerOpen = ref(false);
const drawer = ref(null);
const MAX_BOTTOM_ITEMS = 4;

const items = computed(() => dashboardNavFor(role.value));
const groups = computed(() =>
    NAV_GROUPS.map((g) => ({ key: g, items: items.value.filter((i) => i.group === g) })).filter((g) => g.items.length),
);
const bottomItems = computed(() => items.value.filter((i) => i.primary).slice(0, MAX_BOTTOM_ITEMS));
const currentPath = computed(() => (page.url || '').split('?')[0]);
const isActive = (item) => {
    const target = lr(item.path);
    return item.exact ? currentPath.value === target : currentPath.value === target || currentPath.value.startsWith(`${target}/`);
};
const badgeOf = (item) => (item.badge === 'unread' ? unread.value : 0);

const stopNavigate = router.on('navigate', () => (drawerOpen.value = false));
watch(drawerOpen, (open) => {
    document.body.classList.toggle('overflow-hidden', open);
    if (open) nextTick(() => drawer.value?.querySelector('a, button')?.focus());
});
onBeforeUnmount(() => {
    stopNavigate();
    document.body.classList.remove('overflow-hidden');
});
const onKey = (e) => {
    if (e.key === 'Escape') drawerOpen.value = false;
};
</script>

<template>
    <div class="relative min-h-screen" @keydown="onKey">
        <BackgroundBlobs />
        <a href="#main" class="clay-btn clay-btn-primary sr-only focus:not-sr-only focus:fixed focus:left-4 focus:top-4 focus:z-[1200]">
            {{ t('common.skip_to_content') }}
        </a>

        <!-- Sidebar: thanh icon 80px (tablet) → thẻ clay 288px (desktop) -->
        <aside
            class="clay-card fixed bottom-4 left-4 top-4 z-[1001] hidden flex-col overflow-hidden md:flex md:w-20 lg:w-72"
            :aria-label="t('dashboard.sidebar')"
        >
            <div class="flex flex-col items-center gap-1 px-3 pb-3 pt-5 lg:items-start lg:px-5">
                <Link :href="lr('/')" class="rounded-full" :aria-label="t('nav.home_logo')">
                    <AppLogo class="lg:hidden" compact />
                    <span class="hidden lg:inline"><AppLogo /></span>
                </Link>
                <p v-if="user" class="eyebrow mt-2 hidden lg:block">{{ te('roles', role) }}</p>
            </div>
            <nav class="flex-1 overflow-y-auto px-3 pb-3 lg:px-4" :aria-label="t('dashboard.nav_label')">
                <div v-for="g in groups" :key="g.key" class="mb-3">
                    <p class="eyebrow hidden px-4 pb-1.5 pt-2 lg:block">{{ t(`dashboard.group.${g.key}`) }}</p>
                    <hr class="mx-auto my-2 w-8 border-line lg:hidden" />
                    <ul class="flex flex-col items-center gap-1 lg:items-stretch">
                        <li v-for="item in g.items" :key="item.key">
                            <DashboardNavItem :href="lr(item.path)" :label="t(item.label)" :icon="item.icon" :active="isActive(item)" :badge="badgeOf(item)" rail class="lg:hidden" />
                            <div class="hidden lg:block"><DashboardNavItem :href="lr(item.path)" :label="t(item.label)" :icon="item.icon" :active="isActive(item)" :badge="badgeOf(item)" /></div>
                        </li>
                    </ul>
                </div>
            </nav>
            <div class="border-t border-line p-3 lg:px-4">
                <DashboardNavItem :href="lr('/')" :label="t('dashboard.back_to_site')" icon="arrow-left" rail class="mx-auto lg:hidden" />
                <div class="hidden lg:block"><DashboardNavItem :href="lr('/')" :label="t('dashboard.back_to_site')" icon="arrow-left" /></div>
            </div>
        </aside>

        <div class="relative z-10 flex min-h-screen flex-col px-3 pb-28 pt-3 md:pb-4 md:pl-28 md:pr-4 md:pt-4 lg:pl-80">
            <header class="mb-4 flex items-center gap-2">
                <Link :href="lr('/')" class="rounded-full md:hidden" :aria-label="t('nav.home_logo')"><AppLogo compact /></Link>
                <p v-if="user" class="hidden truncate text-sm text-muted md:block">
                    {{ t('dashboard.greeting', { name: user.name }) }}
                    <span v-if="user.province" class="pill ml-2">{{ user.province.name }}</span>
                </p>
                <div class="ml-auto flex items-center gap-1.5">
                    <LocaleSwitch />
                    <ThemeToggle />
                    <Link
                        v-if="user"
                        :href="lr('/thong-bao')"
                        class="clay-btn clay-btn-icon relative rounded-full bg-raised"
                        :aria-label="t('nav.notifications_count', { n: unread })"
                    >
                        <Icon name="bell" />
                        <span v-if="unread" class="absolute -right-1 -top-1 rounded-full bg-level-4 px-1.5 text-xs font-bold leading-5 text-level-4-fg" aria-hidden="true">{{ unread > 99 ? '99+' : unread }}</span>
                    </Link>
                    <AccountMenu />
                </div>
            </header>

            <FlashToast />

            <main id="main" class="clay-card min-w-0 flex-1 p-4 md:p-6 lg:p-8" tabindex="-1">
                <slot />
            </main>

            <AppFooter compact />
        </div>

        <!-- Điện thoại: thanh dưới tối đa 4 mục + nút Menu mở ngăn kéo -->
        <nav class="clay-card fixed inset-x-3 bottom-3 z-[1001] flex items-center justify-around rounded-full p-1.5 md:hidden" :aria-label="t('dashboard.bottom_nav')">
            <Link
                v-for="item in bottomItems"
                :key="item.key"
                :href="lr(item.path)"
                class="relative flex min-w-0 flex-1 flex-col items-center gap-0.5 rounded-full px-1 py-1.5 text-[0.6875rem] font-bold"
                :class="isActive(item) ? 'bg-linear-to-br from-brand-400 to-brand-500 text-on-brand' : ''"
                :aria-current="isActive(item) ? 'page' : undefined"
            >
                <Icon :name="item.icon" :size="20" />
                <span class="max-w-full truncate">{{ t(item.label) }}</span>
                <span v-if="badgeOf(item)" class="absolute right-2 top-0 rounded-full bg-level-4 px-1 text-[0.625rem] leading-4 text-level-4-fg">{{ badgeOf(item) }}</span>
            </Link>
            <button
                type="button"
                class="flex min-w-0 flex-1 flex-col items-center gap-0.5 rounded-full px-1 py-1.5 text-[0.6875rem] font-bold"
                :aria-expanded="drawerOpen ? 'true' : 'false'"
                aria-controls="dashboard-drawer"
                @click="drawerOpen = true"
            >
                <Icon name="menu" :size="20" />
                <span>{{ t('dashboard.menu') }}</span>
            </button>
        </nav>

        <div v-if="drawerOpen" class="fixed inset-0 z-[1100] bg-overlay md:hidden" aria-hidden="true" @click="drawerOpen = false"></div>
        <aside
            v-show="drawerOpen"
            id="dashboard-drawer"
            ref="drawer"
            class="clay-card fixed inset-y-3 left-3 z-[1101] flex w-[min(20rem,calc(100vw-1.5rem))] flex-col overflow-hidden md:hidden"
            role="dialog"
            aria-modal="true"
            :aria-label="t('dashboard.nav_label')"
        >
            <div class="flex items-center justify-between gap-2 p-4">
                <AppLogo />
                <button type="button" class="clay-btn clay-btn-icon clay-btn-sm" :aria-label="t('common.close_menu')" @click="drawerOpen = false">
                    <Icon name="close" :size="18" />
                </button>
            </div>
            <nav class="flex-1 overflow-y-auto px-3 pb-4" :aria-label="t('dashboard.nav_label')">
                <div v-for="g in groups" :key="g.key" class="mb-3">
                    <p class="eyebrow px-4 pb-1.5">{{ t(`dashboard.group.${g.key}`) }}</p>
                    <ul class="space-y-1">
                        <li v-for="item in g.items" :key="item.key">
                            <DashboardNavItem :href="lr(item.path)" :label="t(item.label)" :icon="item.icon" :active="isActive(item)" :badge="badgeOf(item)" />
                        </li>
                    </ul>
                </div>
                <DashboardNavItem :href="lr('/')" :label="t('dashboard.back_to_site')" icon="arrow-left" />
            </nav>
        </aside>
    </div>
</template>
