<script setup>
import Button from '@/Components/Button.vue';
import { useT } from '@/Composables/useT';

/** Thanh nút lưu/hủy dính dưới form. */
defineProps({
    cancelHref: { type: String, required: true },
    processing: { type: Boolean, default: false },
    isDirty: { type: Boolean, default: true },
    hasErrors: { type: Boolean, default: false },
});
const { t } = useT();
</script>

<template>
    <div class="clay-card-sm sticky bottom-20 z-20 mt-6 flex flex-wrap items-center justify-end gap-2 px-4 py-3 md:bottom-4">
        <p v-if="hasErrors" class="mr-auto text-sm text-danger" role="alert">{{ t('admin.form.has_errors') }}</p>
        <Button :href="cancelHref">{{ t('common.cancel') }}</Button>
        <Button type="submit" variant="primary" :loading="processing">
            {{ processing ? t('common.saving') : t('common.save') }}
        </Button>
    </div>
</template>
