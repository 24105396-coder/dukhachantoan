<script setup>
import { Link } from '@inertiajs/vue3';
import Icon from '@/Components/Icon.vue';
import { useT } from '@/Composables/useT';

// items: [{ label, href? }] – mục cuối là trang hiện tại
defineProps({ items: { type: Array, default: () => [] } });
const { t } = useT();
</script>

<template>
    <nav v-if="items.length" :aria-label="t('common.breadcrumb')" class="text-sm text-muted">
        <ol class="flex flex-wrap items-center gap-1">
            <li v-for="(item, i) in items" :key="i" class="flex min-w-0 items-center gap-1">
                <Icon v-if="i > 0" name="chevron-right" :size="14" />
                <Link v-if="item.href && i < items.length - 1" :href="item.href" class="rounded-full px-1 font-medium hover:text-link hover:underline">{{ item.label }}</Link>
                <span v-else aria-current="page" class="line-clamp-1 font-semibold text-fg">{{ item.label }}</span>
            </li>
        </ol>
    </nav>
</template>
