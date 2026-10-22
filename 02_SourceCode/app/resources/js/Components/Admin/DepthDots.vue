<script setup>
import { useT } from '@/Composables/useT';

/** Ba chấm 1-2-3 cho biết độ sâu diễn giải nào đã có. */
const props = defineProps({
    depths: { type: Array, default: () => [] },
    locale: { type: String, required: true },
});
const { t } = useT();
const has = (d) => (props.depths || []).map(Number).includes(d);
</script>

<template>
    <span class="inline-flex items-center gap-1" :aria-label="t('admin.common.depths_aria', { locale: locale.toUpperCase(), list: (depths || []).join(', ') || '—' })">
        <span class="w-5 text-xs font-bold uppercase text-muted">{{ locale }}</span>
        <span
            v-for="d in [1, 2, 3]"
            :key="d"
            class="flex h-5 w-5 items-center justify-center rounded-full text-[10px] font-bold"
            :class="has(d) ? 'bg-success text-on-primary' : 'border border-border text-muted'"
            aria-hidden="true"
        >{{ d }}</span>
    </span>
</template>
