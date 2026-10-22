<script setup>
import { ref, computed, watch, nextTick, onBeforeUnmount } from 'vue';
import Icon from '@/Components/Icon.vue';
import ProvenanceBadge from '@/Components/ProvenanceBadge.vue';
import EmptyState from '@/Components/EmptyState.vue';
import { useT } from '@/Composables/useT';

const props = defineProps({
    images: { type: Array, default: () => [] },
    title: { type: String, default: '' },
});
const { t } = useT();

const index = ref(-1);
const isOpen = computed(() => index.value >= 0);
const current = computed(() => props.images[index.value] || null);
const closeBtn = ref(null);
let lastFocus = null;

function open(i) {
    lastFocus = document.activeElement;
    index.value = i;
}
function close() {
    index.value = -1;
    nextTick(() => lastFocus?.focus?.());
}
function step(delta) {
    const n = props.images.length;
    index.value = (index.value + delta + n) % n;
}
function onKey(e) {
    if (!isOpen.value) return;
    if (e.key === 'Escape') close();
    if (e.key === 'ArrowRight') step(1);
    if (e.key === 'ArrowLeft') step(-1);
}

watch(isOpen, (v) => {
    document.body.classList.toggle('overflow-hidden', v);
    if (v) {
        document.addEventListener('keydown', onKey);
        nextTick(() => closeBtn.value?.focus());
    } else {
        document.removeEventListener('keydown', onKey);
    }
});
onBeforeUnmount(() => {
    document.removeEventListener('keydown', onKey);
    document.body.classList.remove('overflow-hidden');
});
</script>

<template>
    <section :aria-label="title || t('media.gallery')">
        <EmptyState v-if="!images.length" icon="image" :title="t('media.empty')" compact />
        <ul v-else class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
            <li v-for="(img, i) in images" :key="img.id || i">
                <figure class="card overflow-hidden">
                    <button
                        type="button"
                        class="block w-full"
                        :aria-label="t('media.open_image', { caption: img.caption || i + 1 })"
                        @click="open(i)"
                    >
                        <img :src="img.url" :alt="img.caption || title" loading="lazy" decoding="async" class="aspect-[4/3] w-full object-cover transition-transform hover:scale-[1.03]" />
                    </button>
                    <figcaption class="space-y-1 p-2">
                        <p v-if="img.caption" class="line-clamp-2 text-xs">{{ img.caption }}</p>
                        <ProvenanceBadge :item="img" compact />
                    </figcaption>
                </figure>
            </li>
        </ul>

        <Teleport to="body">
            <div
                v-if="isOpen && current"
                class="fixed inset-0 z-[1300] flex flex-col bg-black/90 p-4 text-white"
                role="dialog"
                aria-modal="true"
                :aria-label="current.caption || t('media.gallery')"
            >
                <div class="flex items-center justify-between gap-2">
                    <p class="text-sm opacity-80">{{ index + 1 }} / {{ images.length }}</p>
                    <button ref="closeBtn" type="button" class="btn btn-icon border-white/30 bg-white/10 text-white hover:bg-white/20" :aria-label="t('common.close')" @click="close">
                        <Icon name="close" />
                    </button>
                </div>
                <div class="relative flex min-h-0 flex-1 items-center justify-center py-4">
                    <button v-if="images.length > 1" type="button" class="btn btn-icon absolute left-0 border-white/30 bg-white/10 text-white hover:bg-white/20" :aria-label="t('common.previous')" @click="step(-1)">
                        <Icon name="chevronLeft" />
                    </button>
                    <img :src="current.url" :alt="current.caption || title" class="max-h-full max-w-full object-contain" />
                    <button v-if="images.length > 1" type="button" class="btn btn-icon absolute right-0 border-white/30 bg-white/10 text-white hover:bg-white/20" :aria-label="t('common.next')" @click="step(1)">
                        <Icon name="chevronRight" />
                    </button>
                </div>
                <div class="mx-auto w-full max-w-3xl rounded-xl bg-surface p-3 text-text">
                    <p v-if="current.caption" class="mb-2 text-sm font-medium">{{ current.caption }}</p>
                    <ProvenanceBadge :item="current" />
                </div>
            </div>
        </Teleport>
    </section>
</template>
