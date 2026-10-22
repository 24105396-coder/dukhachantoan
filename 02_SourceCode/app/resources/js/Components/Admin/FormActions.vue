<script setup>
import { Link } from '@inertiajs/vue3';
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
    <div class="sticky bottom-0 z-20 -mx-4 mt-6 flex flex-wrap items-center justify-end gap-2 border-t border-border bg-bg/95 px-4 py-3 backdrop-blur md:-mx-6 md:px-6">
        <p v-if="hasErrors" class="mr-auto text-sm text-danger" role="alert">{{ t('admin.form.has_errors') }}</p>
        <Link :href="cancelHref" class="btn">{{ t('common.cancel') }}</Link>
        <button type="submit" class="btn btn-primary" :disabled="processing">
            {{ processing ? t('common.saving') : t('common.save') }}
        </button>
    </div>
</template>
