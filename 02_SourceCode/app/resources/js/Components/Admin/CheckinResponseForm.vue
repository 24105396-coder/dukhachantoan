<script setup>
import { useId } from 'vue';
import { useForm } from '@inertiajs/vue3';
import Button from '@/Components/Button.vue';
import { useT } from '@/Composables/useT';
import { useLocaleRoute } from '@/Composables/useLocaleRoute';

/** Form phản hồi inline cho một báo an toàn: trạng thái xử lý + ghi chú → POST /quan-tri/an-toan/{id}/phan-hoi. */
const props = defineProps({
    checkin: { type: Object, required: true },
});
const { t, te } = useT();
const lr = useLocaleRoute();
const uid = useId();
const RESPONSE_STATUSES = ['open', 'in_progress', 'closed'];
const form = useForm({ response_status: props.checkin.response_status || 'in_progress', response_note: props.checkin.response_note || '' });

function submit() {
    form.post(lr(`/quan-tri/an-toan/${props.checkin.id}/phan-hoi`), { preserveScroll: true, only: ['checkins', 'flash', 'errors'] });
}
</script>

<template>
    <form class="grid min-w-64 gap-2" novalidate @submit.prevent="submit">
        <div>
            <label :for="`rs-${uid}`" class="sr-only">{{ t('admin.checkins.response_status_for', { id: checkin.id }) }}</label>
            <select :id="`rs-${uid}`" v-model="form.response_status" class="clay-input py-1.5 text-sm">
                <option v-for="s in RESPONSE_STATUSES" :key="s" :value="s">{{ te('status', s) }}</option>
            </select>
        </div>
        <div>
            <label :for="`rn-${uid}`" class="sr-only">{{ t('admin.checkins.note_for', { id: checkin.id }) }}</label>
            <textarea
                :id="`rn-${uid}`"
                v-model="form.response_note"
                rows="2"
                maxlength="1000"
                class="clay-input text-sm"
                :placeholder="t('admin.checkins.note_placeholder')"
                :aria-invalid="form.errors.response_note ? 'true' : undefined"
            ></textarea>
            <p v-if="form.errors.response_note || form.errors.response_status" class="field-error" role="alert">{{ form.errors.response_note || form.errors.response_status }}</p>
        </div>
        <div class="flex items-center gap-2">
            <Button type="submit" size="sm" variant="primary" icon="send" :loading="form.processing">{{ t('admin.checkins.respond') }}</Button>
            <span v-if="form.recentlySuccessful" class="text-xs font-semibold text-success" role="status">{{ t('account.saved') }}</span>
        </div>
    </form>
</template>
