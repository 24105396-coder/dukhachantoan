<script setup>
import { computed, ref } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import { useT } from '@/Composables/useT';

// Ba mức diễn giải: 1 phổ thông, 2 chuyên sâu, 3 học thuật.
// Mẫu tab ARIA: tablist/tab + aria-selected, phím mũi tên chuyển tab, chỉ tab đang chọn nằm trong thứ tự Tab.
const props = defineProps({
    current: { type: Number, default: 1 },
    available: { type: Array, default: () => [1] },
    panelId: { type: String, default: 'depth-panel' },
});
const page = usePage();
const { t } = useT();
const listEl = ref(null);

const basePath = computed(() => (page.url || '').split('?')[0]);
const levels = [1, 2, 3];
const isAvailable = (lvl) => props.available.map(Number).includes(lvl);
const tabId = (lvl) => `depth-tab-${lvl}`;
// Tab nhận focus bằng phím Tab: tab đang chọn, hoặc tab khả dụng đầu tiên nếu mức hiện tại không có
const focusLevel = computed(() => (isAvailable(props.current) ? props.current : levels.find(isAvailable)));

function onKeydown(e) {
    const keys = { ArrowRight: 1, ArrowDown: 1, ArrowLeft: -1, ArrowUp: -1 };
    const tabs = [...(listEl.value?.querySelectorAll('[role="tab"]:not([aria-disabled="true"])') || [])];
    if (!tabs.length) return;
    const idx = tabs.indexOf(document.activeElement);
    let next = null;
    if (e.key in keys) next = tabs[(idx + keys[e.key] + tabs.length) % tabs.length];
    else if (e.key === 'Home') next = tabs[0];
    else if (e.key === 'End') next = tabs[tabs.length - 1];
    if (!next) return;
    e.preventDefault();
    next.focus();
}
</script>

<template>
    <div ref="listEl" role="tablist" :aria-label="t('depth.label')" class="flex flex-wrap gap-2" @keydown="onKeydown">
        <template v-for="lvl in levels" :key="lvl">
            <Link
                v-if="isAvailable(lvl)"
                :id="tabId(lvl)"
                :href="`${basePath}?depth=${lvl}`"
                role="tab"
                class="tab inline-flex flex-col items-start leading-tight"
                :aria-selected="current === lvl ? 'true' : 'false'"
                :aria-controls="panelId"
                :tabindex="focusLevel === lvl ? 0 : -1"
                preserve-scroll
            >
                <span>{{ t(`depth.${lvl}`) }}</span>
                <span class="text-[11px] font-normal opacity-80">{{ t(`depth.${lvl}_hint`) }}</span>
            </Link>
            <span
                v-else
                :id="tabId(lvl)"
                role="tab"
                class="tab inline-flex flex-col items-start leading-tight"
                aria-selected="false"
                aria-disabled="true"
                :title="t('depth.unavailable')"
            >
                <span>{{ t(`depth.${lvl}`) }}</span>
                <span class="text-[11px] font-normal opacity-80">{{ t('depth.unavailable') }}</span>
            </span>
        </template>
    </div>
</template>
