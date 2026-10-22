<script setup>
import { ref, onMounted } from 'vue';
import Icon from '@/Components/Icon.vue';
import { useT } from '@/Composables/useT';

/**
 * Xem mô hình 3D/AR bằng <model-viewer>. Thư viện được tự host (gói npm, chunk vendor-model-viewer)
 * thay cho CDN unpkg để loại rủi ro chuỗi cung ứng (OWASP A08) và chỉ nạp khi trang có mô hình.
 */
const props = defineProps({
    src: { type: String, required: true },
    alt: { type: String, default: '' },
    poster: { type: String, default: null },
});
const { t } = useT();
const status = ref('loading'); // loading | ready | error

function loadModelViewer() {
    if (window.customElements?.get('model-viewer')) return Promise.resolve();
    return import('@google/model-viewer');
}

onMounted(() => {
    if (!props.src) return;
    loadModelViewer()
        .then(() => window.customElements.whenDefined('model-viewer'))
        .then(() => (status.value = 'ready'))
        .catch(() => (status.value = 'error'));
});
</script>

<template>
    <section class="card overflow-hidden" :aria-label="t('artifacts.model_3d')">
        <div class="flex items-center justify-between gap-2 border-b border-border p-3">
            <h2 class="flex items-center gap-2 font-body text-base font-bold"><Icon name="cube" :size="18" />{{ t('artifacts.model_3d') }}</h2>
            <span class="text-xs text-muted">{{ t('artifacts.model_hint') }}</span>
        </div>
        <div class="relative aspect-square bg-surface-2 sm:aspect-[4/3]">
            <model-viewer
                v-if="status === 'ready'"
                :src="src"
                :alt="alt"
                :poster="poster || undefined"
                ar
                ar-modes="webxr scene-viewer quick-look"
                camera-controls
                auto-rotate
                shadow-intensity="1"
                touch-action="pan-y"
                style="width: 100%; height: 100%; background: transparent"
            >
                <button slot="ar-button" class="btn btn-primary absolute bottom-3 right-3">{{ t('artifacts.view_ar') }}</button>
            </model-viewer>
            <div v-else-if="status === 'loading'" class="skeleton absolute inset-0 rounded-none" role="status" :aria-label="t('common.loading')"></div>
            <p v-else class="absolute inset-0 flex items-center justify-center p-6 text-center text-sm text-muted" role="alert">{{ t('artifacts.model_error') }}</p>
        </div>
    </section>
</template>
