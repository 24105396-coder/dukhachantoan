<script setup>
import { computed } from 'vue';
import Icon from '@/Components/Icon.vue';
import { useT } from '@/Composables/useT';
import { useFormat } from '@/Composables/useFormat';

/**
 * Dòng "Nguồn: … · Cập nhật: hh:mm dd/mm" – BẮT BUỘC trên mọi thẻ cảnh báo (yêu cầu đề ĐT-15).
 * source: chuỗi tên hoặc { name, url, attribution }. Có url thì tên nguồn là liên kết mở tab mới.
 * `simulated` thêm nhãn "Mô phỏng" để không nhầm với cảnh báo thật.
 */
const props = defineProps({
    source: { type: [String, Object], default: null },
    updatedAt: { type: String, default: null },
    simulated: { type: Boolean, default: false },
});
const { t } = useT();
const { stamp } = useFormat();

const name = computed(() => (typeof props.source === 'string' ? props.source : props.source?.name) || t('source.unknown'));
const url = computed(() => (typeof props.source === 'object' && props.source?.url ? props.source.url : null));
const isSafeUrl = computed(() => !!url.value && /^https?:\/\//i.test(url.value));
const when = computed(() => stamp(props.updatedAt) || '—');
</script>

<template>
    <p class="flex flex-wrap items-center gap-x-1.5 gap-y-1 text-xs text-muted" data-source-stamp>
        <Icon name="radio" :size="14" />
        <span>
            {{ t('common.source') }}:
            <a v-if="isSafeUrl" :href="url" target="_blank" rel="noopener noreferrer" class="link font-semibold">{{ name }}</a>
            <span v-else class="font-semibold text-fg">{{ name }}</span>
        </span>
        <span aria-hidden="true">·</span>
        <span>{{ t('common.updated') }}: <time :datetime="updatedAt || undefined" class="font-semibold text-fg">{{ when }}</time></span>
        <span v-if="simulated" class="pill ml-1 border-dashed px-2 py-0 text-[0.6875rem]"><Icon name="flask" :size="12" />{{ t('common.simulated') }}</span>
    </p>
</template>
