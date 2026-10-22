<script setup>
import { computed } from 'vue';
import { useT } from '@/Composables/useT';
import { useFormat } from '@/Composables/useFormat';

// Hiển thị nguồn gốc / bản quyền của một media (minh bạch hóa xuất xứ tư liệu)
const props = defineProps({
    item: { type: Object, required: true },
    compact: { type: Boolean, default: false },
});
const { t, te } = useT();
const { date } = useFormat();

const licenseText = computed(() => props.item.license_label || te('license', props.item.license));
const tone = computed(() => {
    switch (props.item.license) {
        case 'public_domain':
        case 'cc_by':
        case 'cc_by_sa':
            return 'badge-success';
        case 'cc_by_nc':
            return 'badge-info';
        case 'simulated':
            return 'badge-warning';
        default:
            return 'badge-danger';
    }
});
</script>

<template>
    <div class="flex flex-wrap items-center gap-x-2 gap-y-1 text-xs" :class="compact ? '' : 'leading-relaxed'">
        <span class="badge" :class="tone" :title="t('provenance.license')">© {{ licenseText }}</span>
        <span v-if="item.source_name" class="text-muted">
            {{ t('provenance.source') }}:
            <a v-if="item.source_url" :href="item.source_url" target="_blank" rel="noopener noreferrer nofollow" class="link">{{ item.source_name }}</a>
            <span v-else class="text-text">{{ item.source_name }}</span>
        </span>
        <span v-if="item.author" class="text-muted">{{ t('provenance.author') }}: <span class="text-text">{{ item.author }}</span></span>
        <template v-if="!compact">
            <span v-if="item.rights_holder" class="text-muted">{{ t('provenance.rights_holder') }}: <span class="text-text">{{ item.rights_holder }}</span></span>
            <span v-if="item.captured_at" class="text-muted">{{ t('provenance.captured_at') }}: <span class="text-text">{{ date(item.captured_at) }}</span></span>
            <span v-if="item.provenance_note" class="w-full text-muted italic">{{ item.provenance_note }}</span>
        </template>
    </div>
</template>
