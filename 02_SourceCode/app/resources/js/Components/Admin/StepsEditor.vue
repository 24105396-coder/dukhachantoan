<script setup>
import { ref, nextTick, useId } from 'vue';
import Button from '@/Components/Button.vue';
import { useT } from '@/Composables/useT';

/**
 * Danh sách chuỗi sửa được (các bước hướng dẫn, nguồn tham khảo): thêm, xóa, lên/xuống bằng nút (dùng được bằng bàn phím).
 * v-model: string[]. errorPrefix: khóa lỗi máy chủ, ví dụ 'translations.vi.before_steps' → lỗi '…before_steps.2'.
 */
const props = defineProps({
    label: { type: String, required: true },
    errors: { type: Object, default: () => ({}) },
    errorPrefix: { type: String, default: '' },
    max: { type: Number, default: 30 },
    placeholder: { type: String, default: '' },
    multiline: { type: Boolean, default: true },
});
const model = defineModel({ type: Array, default: () => [] });
const { t } = useT();
const uid = useId();
const list = ref(null);

function add() {
    if (model.value.length >= props.max) return;
    model.value = [...model.value, ''];
    nextTick(() => list.value?.querySelectorAll('textarea, input')[model.value.length - 1]?.focus());
}
function remove(i) {
    model.value = model.value.filter((_, k) => k !== i);
}
function move(i, dir) {
    const j = i + dir;
    if (j < 0 || j >= model.value.length) return;
    const next = [...model.value];
    [next[i], next[j]] = [next[j], next[i]];
    model.value = next;
}
function update(i, v) {
    model.value = model.value.map((x, k) => (k === i ? v : x));
}
const errOf = (i) => props.errors[`${props.errorPrefix}.${i}`];
</script>

<template>
    <fieldset>
        <legend class="field-label">{{ label }}</legend>
        <p v-if="errors[errorPrefix]" class="field-error mb-2" role="alert">{{ errors[errorPrefix] }}</p>
        <ol ref="list" class="space-y-2">
            <li v-for="(item, i) in model" :key="i" class="flex items-start gap-2">
                <span class="mt-2.5 w-6 shrink-0 text-right text-sm font-bold text-muted" aria-hidden="true">{{ i + 1 }}.</span>
                <div class="min-w-0 flex-1">
                    <label :for="`st-${uid}-${i}`" class="sr-only">{{ t('admin.form.item_n', { label, n: i + 1 }) }}</label>
                    <textarea
                        v-if="multiline"
                        :id="`st-${uid}-${i}`"
                        :value="item"
                        rows="2"
                        class="clay-input"
                        :placeholder="placeholder"
                        :aria-invalid="errOf(i) ? 'true' : undefined"
                        @input="update(i, $event.target.value)"
                    ></textarea>
                    <input v-else :id="`st-${uid}-${i}`" :value="item" class="clay-input" :placeholder="placeholder" :aria-invalid="errOf(i) ? 'true' : undefined" @input="update(i, $event.target.value)" />
                    <p v-if="errOf(i)" class="field-error" role="alert">{{ errOf(i) }}</p>
                </div>
                <div class="flex shrink-0 flex-col gap-1 sm:flex-row">
                    <Button size="sm" variant="ghost" icon="arrow-up" icon-only :label="t('admin.form.move_up', { n: i + 1 })" :disabled="i === 0" @click="move(i, -1)" />
                    <Button size="sm" variant="ghost" icon="arrow-down" icon-only :label="t('admin.form.move_down', { n: i + 1 })" :disabled="i === model.length - 1" @click="move(i, 1)" />
                    <Button size="sm" variant="ghost" icon="trash" icon-only :label="t('admin.form.remove_item', { n: i + 1 })" @click="remove(i)" />
                </div>
            </li>
        </ol>
        <Button size="sm" icon="plus" class="mt-2" :disabled="model.length >= max" @click="add">{{ t('admin.form.add_item') }}</Button>
    </fieldset>
</template>
