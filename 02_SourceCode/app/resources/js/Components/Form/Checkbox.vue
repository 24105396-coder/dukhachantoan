<script setup>
import { useId } from 'vue';

/**
 * Ô đánh dấu có nhãn bấm được. v-model nhận boolean, hoặc mảng khi truyền `value` (nhóm checkbox).
 */
defineOptions({ inheritAttrs: false });
const props = defineProps({
    label: { type: String, required: true },
    value: { type: [String, Number, Boolean], default: undefined },
    hint: { type: String, default: '' },
    srOnlyLabel: { type: Boolean, default: false },
});
const model = defineModel({ type: [Boolean, Array], default: false });
const id = `cb-${useId()}`;
</script>

<template>
    <div class="flex items-start gap-2.5">
        <input :id="id" v-model="model" v-bind="$attrs" type="checkbox" :value="props.value" class="clay-checkbox mt-0.5" :aria-describedby="hint ? `${id}-hint` : undefined" />
        <label :for="id" class="text-sm font-medium" :class="srOnlyLabel ? 'sr-only' : ''">
            {{ label }}
            <span v-if="hint" :id="`${id}-hint`" class="block text-xs font-normal text-muted">{{ hint }}</span>
        </label>
    </div>
</template>
