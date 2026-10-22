<script setup>
import { computed, useId } from 'vue';

/**
 * Trường biểu mẫu có label, gợi ý, lỗi. Dùng nhanh với v-model (input/textarea/select)
 * hoặc qua slot mặc định nhận { id, invalid, describedby } để tự dựng control.
 */
const props = defineProps({
    label: { type: String, required: true },
    error: { type: [String, Array], default: null },
    hint: { type: String, default: '' },
    type: { type: String, default: 'text' }, // text | email | password | number | textarea | select | date | datetime-local | tel | url
    required: { type: Boolean, default: false },
    options: { type: Array, default: () => [] }, // [{value,label}] cho select
    placeholder: { type: String, default: '' },
    autocomplete: { type: String, default: undefined },
    rows: { type: Number, default: 4 },
    id: { type: String, default: null },
    min: { type: [String, Number], default: undefined },
    max: { type: [String, Number], default: undefined },
    step: { type: [String, Number], default: undefined },
    // Ràng buộc HTML5 phía máy khách, khớp FormRequest (máy chủ vẫn kiểm lại)
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
</script>

<template>
    <div>
        <label :for="fieldId" class="label">
            {{ label }}<span v-if="required" class="text-danger" aria-hidden="true"> *</span>
        </label>
        <slot :id="fieldId" :invalid="!!errorText" :describedby="describedby">
            <textarea
                v-if="type === 'textarea'"
                :id="fieldId"
                v-model="model"
                class="input"
                :rows="rows"
                :required="required"
                :minlength="minlength"
                :maxlength="maxlength"
                :placeholder="placeholder"
                :disabled="disabled"
                :aria-invalid="!!errorText"
                :aria-describedby="describedby"
            ></textarea>
            <select
                v-else-if="type === 'select'"
                :id="fieldId"
                v-model="model"
                class="input"
                :required="required"
                :disabled="disabled"
                :aria-invalid="!!errorText"
                :aria-describedby="describedby"
            >
                <option v-if="placeholder" value="">{{ placeholder }}</option>
                <option v-for="o in options" :key="o.value" :value="o.value">{{ o.label }}</option>
            </select>
            <input
                v-else
                :id="fieldId"
                v-model="model"
                class="input"
                :type="type"
                :required="required"
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
                :disabled="disabled"
                :aria-invalid="!!errorText"
                :aria-describedby="describedby"
            />
        </slot>
        <p v-if="hint" :id="`${fieldId}-hint`" class="field-hint">{{ hint }}</p>
        <p v-if="errorText" :id="`${fieldId}-err`" class="field-error" role="alert">{{ errorText }}</p>
    </div>
</template>
