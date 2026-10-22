<script setup>
import { ref, onMounted, onBeforeUnmount } from 'vue';
import { Link } from '@inertiajs/vue3';
import Icon from '@/Components/Icon.vue';
import { useT } from '@/Composables/useT';
import { useLocaleRoute } from '@/Composables/useLocaleRoute';
import { usePermissions } from '@/Composables/usePermissions';

const { t, te } = useT();
const lr = useLocaleRoute();
const { user, canAccessAdmin, adminHome } = usePermissions();

const open = ref(false);
const root = ref(null);

const close = () => (open.value = false);
const onDocClick = (e) => {
    if (root.value && !root.value.contains(e.target)) close();
};
const onKey = (e) => {
    if (e.key === 'Escape') close();
};

onMounted(() => {
    document.addEventListener('click', onDocClick);
    document.addEventListener('keydown', onKey);
});
onBeforeUnmount(() => {
    document.removeEventListener('click', onDocClick);
    document.removeEventListener('keydown', onKey);
});
</script>

<template>
    <div v-if="user" ref="root" class="relative">
        <button
            type="button"
            class="btn btn-ghost btn-sm"
            :aria-expanded="open"
            aria-haspopup="menu"
            :aria-label="t('nav.account_menu')"
            @click="open = !open"
        >
            <span class="flex h-7 w-7 items-center justify-center rounded-full bg-primary text-xs font-bold text-on-primary">
                {{ user.name?.charAt(0)?.toUpperCase() }}
            </span>
            <span class="hidden max-w-[10rem] truncate lg:inline">{{ user.name }}</span>
            <Icon name="chevronDown" :size="16" />
        </button>
        <div
            v-show="open"
            class="card absolute right-0 z-50 mt-2 w-60 p-2"
            role="menu"
        >
            <p class="px-3 py-2 text-xs text-muted">
                {{ user.email }}<br />
                <span class="badge badge-accent mt-1">{{ te('roles', user.role) }}</span>
            </p>
            <Link :href="lr('/tai-khoan')" class="flex items-center gap-2 rounded-lg px-3 py-2 text-sm hover:bg-surface-2" role="menuitem" @click="close">
                <Icon name="dashboard" :size="18" /> {{ t('nav.dashboard') }}
            </Link>
            <Link :href="lr('/tai-khoan/ho-so')" class="flex items-center gap-2 rounded-lg px-3 py-2 text-sm hover:bg-surface-2" role="menuitem" @click="close">
                <Icon name="user" :size="18" /> {{ t('nav.profile') }}
            </Link>
            <Link :href="lr('/dong-gop/moi')" class="flex items-center gap-2 rounded-lg px-3 py-2 text-sm hover:bg-surface-2" role="menuitem" @click="close">
                <Icon name="upload" :size="18" /> {{ t('nav.contribute') }}
            </Link>
            <Link v-if="canAccessAdmin" :href="lr(adminHome)" class="flex items-center gap-2 rounded-lg px-3 py-2 text-sm hover:bg-surface-2" role="menuitem" @click="close">
                <Icon name="shield" :size="18" /> {{ t('nav.admin') }}
            </Link>
            <Link
                :href="lr('/dang-xuat')"
                method="post"
                as="button"
                class="flex w-full items-center gap-2 rounded-lg px-3 py-2 text-left text-sm text-danger hover:bg-surface-2"
                role="menuitem"
            >
                <Icon name="logout" :size="18" /> {{ t('nav.logout') }}
            </Link>
        </div>
    </div>
    <div v-else class="flex items-center gap-1">
        <Link :href="lr('/dang-nhap')" class="btn btn-ghost btn-sm">{{ t('nav.login') }}</Link>
        <Link :href="lr('/dang-ky')" class="btn btn-primary btn-sm hidden sm:inline-flex">{{ t('nav.register') }}</Link>
    </div>
</template>
