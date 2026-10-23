<script setup>
import { ref, onMounted, onBeforeUnmount, useId } from 'vue';
import { Link } from '@inertiajs/vue3';
import Icon from '@/Components/Icon.vue';
import Button from '@/Components/Button.vue';
import { useT } from '@/Composables/useT';
import { useLocaleRoute } from '@/Composables/useLocaleRoute';
import { usePermissions } from '@/Composables/usePermissions';

/** Menu tài khoản (người đã đăng nhập) hoặc nút Đăng nhập/Đăng ký (khách). Esc hoặc bấm ra ngoài để đóng. */
defineProps({
    align: { type: String, default: 'right' }, // right | left | up (thanh dưới điện thoại)
});
const { t, te } = useT();
const lr = useLocaleRoute();
const { user, canAccessAdmin, adminHome, unread } = usePermissions();
const menuId = `acc-${useId()}`;

const open = ref(false);
const root = ref(null);
const close = () => (open.value = false);
const onDocClick = (e) => {
    if (root.value && !root.value.contains(e.target)) close();
};
const onKey = (e) => {
    if (e.key === 'Escape' && open.value) {
        close();
        root.value?.querySelector('button')?.focus();
    }
};

onMounted(() => {
    document.addEventListener('click', onDocClick);
    document.addEventListener('keydown', onKey);
});
onBeforeUnmount(() => {
    document.removeEventListener('click', onDocClick);
    document.removeEventListener('keydown', onKey);
});

const itemClass = 'flex items-center gap-3 rounded-full px-3 py-2 text-sm font-semibold hover:bg-soft';
</script>

<template>
    <div v-if="user" ref="root" class="relative">
        <button
            type="button"
            class="clay-btn clay-btn-sm gap-2 pl-1.5"
            :aria-expanded="open ? 'true' : 'false'"
            :aria-controls="menuId"
            :aria-label="t('nav.account_menu')"
            @click="open = !open"
        >
            <span class="flex h-7 w-7 items-center justify-center rounded-full bg-brand-500 text-xs font-extrabold text-on-brand" aria-hidden="true">
                {{ user.name?.charAt(0)?.toUpperCase() }}
            </span>
            <span class="hidden max-w-[9rem] truncate lg:inline">{{ user.name }}</span>
            <Icon name="chevron-down" :size="16" />
        </button>
        <div
            v-show="open"
            :id="menuId"
            class="clay-card absolute z-[1050] mt-2 w-64 p-2"
            :class="[align === 'left' ? 'left-0' : 'right-0', align === 'up' ? 'bottom-full mb-2' : '']"
        >
            <div class="px-3 py-2">
                <p class="truncate text-sm font-bold">{{ user.name }}</p>
                <p class="truncate text-xs text-muted">{{ user.email }}</p>
                <p class="eyebrow mt-1">{{ te('roles', user.role) }}</p>
            </div>
            <ul class="space-y-0.5">
                <li><Link :href="lr('/hanh-trinh')" :class="itemClass" @click="close"><Icon name="route" :size="18" />{{ t('nav.trips') }}</Link></li>
                <li>
                    <Link :href="lr('/thong-bao')" :class="itemClass" @click="close">
                        <Icon name="bell" :size="18" />{{ t('nav.notifications') }}
                        <span v-if="unread" class="ml-auto rounded-full bg-level-4 px-2 text-xs font-bold text-level-4-fg">{{ unread }}</span>
                    </Link>
                </li>
                <li><Link :href="lr('/tai-khoan')" :class="itemClass" @click="close"><Icon name="user-cog" :size="18" />{{ t('nav.account') }}</Link></li>
                <li v-if="canAccessAdmin"><Link :href="lr(adminHome)" :class="itemClass" @click="close"><Icon name="dashboard" :size="18" />{{ t('nav.admin') }}</Link></li>
                <li>
                    <Link :href="lr('/dang-xuat')" method="post" as="button" :class="[itemClass, 'w-full text-left text-danger']">
                        <Icon name="logout" :size="18" />{{ t('nav.logout') }}
                    </Link>
                </li>
            </ul>
        </div>
    </div>
    <div v-else class="flex items-center gap-1.5">
        <Button :href="lr('/dang-nhap')" size="sm" icon="login">{{ t('nav.login') }}</Button>
        <Button :href="lr('/dang-ky')" size="sm" variant="primary" class="hidden sm:inline-flex">{{ t('nav.register') }}</Button>
    </div>
</template>
