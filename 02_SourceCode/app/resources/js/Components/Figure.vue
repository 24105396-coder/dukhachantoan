<script setup>
import { computed, ref, watch } from 'vue';
import Icon from '@/Components/Icon.vue';
import { useT } from '@/Composables/useT';

/**
 * Ảnh có giấy phép (từ useImages) + credit nhỏ. Khung giữ tỉ lệ cố định (`ratioClass`) để không gây CLS;
 * thiếu ảnh hoặc tải lỗi → nền brand nhạt + icon (`fallbackIcon`).
 * - `fill`: ảnh phủ kín phần tử cha (cha cần `relative`), dùng cho nền hero/cột trái trang đăng nhập.
 * - `priority`: ảnh hero trên màn đầu – nạp ngay với fetchpriority="high"; còn lại loading="lazy".
 * - `credit`: caption (dòng chữ nhỏ dưới ảnh) | overlay (góc dưới ảnh) | tooltip (chỉ title + sr-only) | none.
 */
const props = defineProps({
    image: { type: Object, default: null },
    alt: { type: String, default: '' },
    ratioClass: { type: String, default: 'aspect-[16/9]' },
    fill: { type: Boolean, default: false },
    priority: { type: Boolean, default: false },
    credit: { type: String, default: 'caption' },
    fallbackIcon: { type: String, default: 'mountain' },
    roundedClass: { type: String, default: 'rounded-[var(--radius-img)]' },
    width: { type: Number, default: 1200 },
    height: { type: Number, default: 675 },
});
const { t } = useT();

/** Ảnh tải lỗi (tệp chưa có trên máy chủ) → chuyển sang fallback. */
const failed = ref(false);
watch(() => props.image?.src, () => (failed.value = false));

/** Có ảnh dùng được để hiển thị. */
const hasImage = computed(() => !!props.image?.src && !failed.value);
/** Văn bản thay thế: ưu tiên prop alt, sau đó alt trong manifest. */
const altText = computed(() => props.alt || props.image?.alt || '');
/** Dòng credit đã dịch "Ảnh: Tác giả · Giấy phép". */
const creditText = computed(() => (props.image?.credit ? t('ui.images.credit', { credit: props.image.credit }) : ''));
/** Hiện credit dạng chữ (caption/overlay) khi có ảnh và có credit. */
const showCreditText = computed(() => hasImage.value && !!creditText.value && ['caption', 'overlay'].includes(props.credit));
</script>

<template>
    <figure
        class="m-0"
        :class="fill ? 'absolute inset-0' : 'relative'"
        :title="hasImage && credit === 'tooltip' ? creditText : undefined"
        data-figure
    >
        <div class="relative overflow-hidden" :class="[fill ? 'h-full w-full' : ratioClass, roundedClass]">
            <img
                v-if="hasImage"
                :src="image.src"
                :alt="altText"
                :width="width"
                :height="height"
                :loading="priority ? 'eager' : 'lazy'"
                :fetchpriority="priority ? 'high' : undefined"
                decoding="async"
                class="absolute inset-0 h-full w-full object-cover"
                @error="failed = true"
            />
            <div v-else class="figure-fallback absolute inset-0 flex items-center justify-center" aria-hidden="true">
                <Icon :name="fallbackIcon" :size="fill ? 64 : 32" fallback="mountain" />
            </div>
            <figcaption
                v-if="showCreditText && credit === 'overlay'"
                class="figure-credit absolute bottom-0 right-0 max-w-full truncate px-2 py-0.5 text-[0.6875rem]"
            >
                <a v-if="image.sourceUrl" :href="image.sourceUrl" target="_blank" rel="noopener noreferrer" class="underline">{{ creditText }}</a>
                <span v-else>{{ creditText }}</span>
            </figcaption>
        </div>
        <figcaption v-if="showCreditText && credit === 'caption'" class="mt-1 truncate text-xs text-muted">
            <a v-if="image.sourceUrl" :href="image.sourceUrl" target="_blank" rel="noopener noreferrer" class="hover:underline">{{ creditText }}</a>
            <span v-else>{{ creditText }}</span>
        </figcaption>
        <figcaption v-else-if="hasImage && creditText && credit === 'tooltip'" class="sr-only">{{ creditText }}</figcaption>
    </figure>
</template>
