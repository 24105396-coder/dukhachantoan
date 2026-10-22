<script setup>
import { computed, ref, useId } from 'vue';
import Icon from '@/Components/Icon.vue';
import { useT } from '@/Composables/useT';

/**
 * Chọn nhiều vùng: ô tìm nhanh + danh sách checkbox cuộn được + các chip đã chọn (bấm để bỏ).
 * v-model: mảng mã vùng. zones: [{ code, name, province? }].
 */
const props = defineProps({
    zones: { type: Array, default: () => [] },
    label: { type: String, required: true },
    error: { type: String, default: '' },
    required: { type: Boolean, default: false },
});
const model = defineModel({ type: Array, default: () => [] });
const { t } = useT();
const uid = useId();
const q = ref('');
const norm = (s) => String(s || '').normalize('NFD').replace(/[̀-ͯ]/g, '').replace(/đ/gi, 'd').toLowerCase();
const filtered = computed(() => {
    const needle = norm(q.value.trim());
    if (!needle) return props.zones;
    return props.zones.filter((z) => norm(z.name).includes(needle) || norm(z.code).includes(needle) || norm(z.province?.name).includes(needle));
});
const selected = computed(() => model.value.map((c) => props.zones.find((z) => z.code === c) || { code: c, name: c }));
const toggle = (code, on) => {
    model.value = on ? [...new Set([...model.value, code])] : model.value.filter((c) => c !== code);
};
</script>

<template>
    <fieldset :aria-describedby="error ? `zp-err-${uid}` : undefined">
        <legend class="field-label">{{ label }}<span v-if="required" class="text-danger" aria-hidden="true"> *</span></legend>
        <ul v-if="selected.length" class="mb-2 flex flex-wrap gap-1.5" :aria-label="t('admin.zone_picker.selected', { n: selected.length })">
            <li v-for="z in selected" :key="z.code">
                <button type="button" class="pill bg-brand-100 py-1 text-ink-950 hover:bg-brand-200 dark:bg-brand-700 dark:text-white" :aria-label="t('admin.zone_picker.remove', { name: z.name })" @click="toggle(z.code, false)">
                    {{ z.name }}<Icon name="close" :size="14" />
                </button>
            </li>
        </ul>
        <label :for="`zp-q-${uid}`" class="sr-only">{{ t('admin.zone_picker.search') }}</label>
        <div class="relative">
            <span class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-muted"><Icon name="search" :size="18" /></span>
            <input :id="`zp-q-${uid}`" v-model="q" type="search" class="clay-input pl-10" :placeholder="t('admin.zone_picker.search')" :aria-invalid="error ? 'true' : undefined" />
        </div>
        <ul class="mt-2 max-h-56 overflow-y-auto rounded-[14px] border border-line bg-soft p-2">
            <li v-for="z in filtered" :key="z.code">
                <label class="flex cursor-pointer items-center gap-2 rounded-lg px-2 py-1.5 text-sm hover:bg-surface">
                    <input type="checkbox" class="clay-checkbox" :checked="model.includes(z.code)" @change="toggle(z.code, $event.target.checked)" />
                    <span>{{ z.name }}<span v-if="z.province?.name && z.province.name !== z.name" class="text-muted"> · {{ z.province.name }}</span></span>
                </label>
            </li>
            <li v-if="!filtered.length" class="px-2 py-1.5 text-sm text-muted">{{ t('admin.zone_picker.none') }}</li>
        </ul>
        <p v-if="error" :id="`zp-err-${uid}`" class="field-error" role="alert">{{ error }}</p>
    </fieldset>
</template>
