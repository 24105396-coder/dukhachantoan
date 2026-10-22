<script setup>
import { computed, useId } from 'vue';
import Input from '@/Components/Form/Input.vue';
import Select from '@/Components/Form/Select.vue';
import Textarea from '@/Components/Form/Textarea.vue';

/**
 * Trường biểu mẫu: label + control + gợi ý + lỗi (role=alert, liên kết aria-describedby).
 * Dùng nhanh với v-model (type: text|email|password|number|tel|url|date|datetime-local|time|textarea|select)
 * hoặc qua slot mặc định nhận { id, invalid, describedby } để tự dựng control.
 */
const props = defineProps({
    label: { type: String, required: true },
    error: { type: [String, Array], default: null },
    hint: { type: String, default: '' },
    type: { type: String, default: 'text' },
    required: { type: Boolean, default: false },
    options: { type: Array, default: () => [] },
    placeholder: { type: String, default: '' },
    autocomplete: { type: String, default: undefined },
    rows: { type: Number, default: 4 },
    id: { type: String, default: null },
    min: { type: [String, Number], default: undefined },
    max: { type: [String, Number], default: undefined },
    step: { type: [String, Number], default: undefined },
    minlength: { type: [String, Number], default: undefined },
    maxlength: { type: [String, Number], default: undefined },
    pattern: { type: String, default: undefined },
    patternHint: { type: String, default: undefined },
    inputmode: { type: String, default: undefined },
    disabled: { type: Boolean, default: false },
});
const model = defineModel({ type: [String, Number, null], default: '' });
const autoId = useId();
const fieldId = computed(() => props.id || `f-${autoId}`);
const errorText = computed(() => (Array.isArray(props.error) ? props.error[0] : props.error));
const describedby = computed(() =>
    [props.hint ? `${fieldId.value}-hint` : null, errorText.value ? `${fieldId.value}-err` : null].filter(Boolean).join(' ') || undefined,
);
const common = computed(() => ({
    id: fieldId.value,
    required: props.required,
    disabled: props.disabled,
    invalid: !!errorText.value,
    'aria-describedby': describedby.value,
}));
</script>

<template>
    <div>
        <label :for="fieldId" class="field-label">
            {{ label }}<span v-if="required" class="text-danger" aria-hidden="true"> *</span>
        </label>
        <slot :id="fieldId" :invalid="!!errorText" :describedby="describedby">
            <Textarea v-if="type === 'textarea'" v-model="model" v-bind="common" :rows="rows" :minlength="minlength" :maxlength="maxlength" :placeholder="placeholder" />
            <Select v-else-if="type === 'select'" v-model="model" v-bind="common" :options="options" :placeholder="placeholder" />
            <Input
                v-else
                v-model="model"
                v-bind="common"
                :type="type"
                :placeholder="placeholder"
                :autocomplete="autocomplete"
                :min="min"
                :max="max"
                :step="step"
                :minlength="minlength"
                :maxlength="maxlength"
                :pattern="pattern"
                :title="patternHint"
                :inputmode="inputmode"
            />
        </slot>
        <p v-if="hint" :id="`${fieldId}-hint`" class="field-hint">{{ hint }}</p>
        <p v-if="errorText" :id="`${fieldId}-err`" class="field-error" role="alert">{{ errorText }}</p>
    </div>
</template>
