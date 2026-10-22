<script setup>
import { ref, useId } from 'vue';
import { useT } from '@/Composables/useT';
import { LOCALES } from '@/Utils/options';

/**
 * Tab ngôn ngữ vi/en cho nội dung song ngữ (WAI-ARIA tabs: mũi tên trái/phải chuyển tab).
 * Slot mặc định nhận { locale } và được render cho MỌI ngôn ngữ (ẩn tab không chọn) để lỗi kiểm tra không bị mất.
 * errorLocales: ['en'] – đánh dấu tab có lỗi.
 */
const props = defineProps({
    errorLocales: { type: Array, default: () => [] },
    label: { type: String, default: '' },
});
const { t, te } = useT();
const uid = useId();
const active = ref(LOCALES[0]);
const tabs = ref([]);

function onKey(e, i) {
    const dir = e.key === 'ArrowRight' ? 1 : e.key === 'ArrowLeft' ? -1 : 0;
    if (!dir) return;
    e.preventDefault();
    const next = (i + dir + LOCALES.length) % LOCALES.length;
    active.value = LOCALES[next];
    tabs.value[next]?.focus();
}
defineExpose({ show: (l) => (active.value = l) });
</script>

<template>
    <div>
        <div role="tablist" :aria-label="label || t('admin.form.languages')" class="mb-4 inline-flex gap-1 rounded-full bg-soft p-1">
            <button
                v-for="(l, i) in LOCALES"
                :id="`tab-${uid}-${l}`"
                :key="l"
                :ref="(el) => (tabs[i] = el)"
                type="button"
                role="tab"
                class="rounded-full px-4 py-1.5 text-sm font-bold"
                :class="active === l ? 'bg-linear-to-br from-brand-400 to-brand-500 text-on-brand shadow-clay-btn' : ''"
                :aria-selected="active === l ? 'true' : 'false'"
                :aria-controls="`panel-${uid}-${l}`"
                :tabindex="active === l ? 0 : -1"
                @click="active = l"
                @keydown="onKey($event, i)"
            >
                {{ te('locale', l) }}
                <span v-if="props.errorLocales.includes(l)" class="ml-1 inline-block h-2 w-2 rounded-full bg-level-4" aria-hidden="true"></span>
                <span v-if="props.errorLocales.includes(l)" class="sr-only">({{ t('admin.form.tab_has_errors') }})</span>
            </button>
        </div>
        <div
            v-for="l in LOCALES"
            v-show="active === l"
            :id="`panel-${uid}-${l}`"
            :key="l"
            role="tabpanel"
            :aria-labelledby="`tab-${uid}-${l}`"
            :lang="l"
        >
            <slot :locale="l" />
        </div>
    </div>
</template>
