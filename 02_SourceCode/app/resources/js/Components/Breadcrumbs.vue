<script setup>
import { Link } from '@inertiajs/vue3';
import Icon from '@/Components/Icon.vue';
import { useT } from '@/Composables/useT';

// items: [{ label, href? }] – mục cuối là trang hiện tại
defineProps({ items: { type: Array, default: () => [] } });
const { t } = useT();
</script>

<template>
    <nav :aria-label="t('common.breadcrumb')" class="text-sm text-muted">
        <ol class="flex flex-wrap items-center gap-1">
            <li v-for="(item, i) in items" :key="i" class="flex items-center gap-1">
                <Icon v-if="i > 0" name="chevronRight" :size="14" />
                <Link v-if="item.href && i < items.length - 1" :href="item.href" class="hover:text-primary hover:underline">{{ item.label }}</Link>
                <span v-else aria-current="page" class="line-clamp-1 text-text">{{ item.label }}</span>
            </li>
        </ol>
    </nav>
</template>
