<script setup>
import { useForm } from '@inertiajs/vue3';
import FormField from '@/Components/FormField.vue';
import Icon from '@/Components/Icon.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import EmptyState from '@/Components/EmptyState.vue';
import DeleteAction from '@/Components/Admin/DeleteAction.vue';
import { useT } from '@/Composables/useT';
import { useFormat } from '@/Composables/useFormat';
import { useLocaleRoute } from '@/Composables/useLocaleRoute';

/** Khung giờ của tuyến: danh sách + xóa (khi chưa có booking) + form thêm. */
const props = defineProps({
    routeSlug: { type: String, required: true },
    slots: { type: Array, default: () => [] },
    defaultCapacity: { type: [Number, String], default: 20 },
    defaultDuration: { type: [Number, String], default: 120 },
});
const { t, te } = useT();
const { dateTime, time } = useFormat();
const lr = useLocaleRoute();

const form = useForm({
    starts_at: '',
    duration_minutes: Number(props.defaultDuration) || 120,
    capacity: Number(props.defaultCapacity) || 20,
    guide_name: '',
    language: 'vi',
});
const languageOptions = ['vi', 'en'].map((c) => ({ value: c, label: te('locale', c) }));
const base = `/quan-tri/tuyen-tham-quan/${props.routeSlug}/khung-gio`;

function submit() {
    form.post(lr(base), { preserveScroll: true, onSuccess: () => form.reset('starts_at', 'guide_name') });
}
</script>

<template>
    <div class="space-y-5">
        <EmptyState v-if="!slots.length" icon="calendar" :title="t('admin.tours.no_slots')" compact />
        <div v-else class="overflow-x-auto rounded-lg border border-border">
            <table class="table-ds">
                <caption class="sr-only">{{ t('admin.tours.slots') }}</caption>
                <thead>
                    <tr>
                        <th scope="col">{{ t('admin.tours.starts_at') }}</th>
                        <th scope="col">{{ t('admin.tours.capacity') }}</th>
                        <th scope="col">{{ t('admin.tours.guide') }}</th>
                        <th scope="col">{{ t('common.status') }}</th>
                        <th scope="col" class="text-right">{{ t('common.actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="slot in slots" :key="slot.id">
                        <td class="whitespace-nowrap">{{ dateTime(slot.starts_at) }}<span v-if="slot.ends_at" class="text-muted"> – {{ time(slot.ends_at) }}</span></td>
                        <td class="whitespace-nowrap">{{ slot.booked_count ?? 0 }} / {{ slot.capacity }}</td>
                        <td>{{ slot.guide_name || '—' }} <span v-if="slot.language" class="badge uppercase">{{ slot.language }}</span></td>
                        <td><StatusBadge v-if="slot.status" :status="slot.status" /></td>
                        <td class="text-right">
                            <DeleteAction
                                :url="lr(`${base}/${slot.id}`)"
                                :name="dateTime(slot.starts_at)"
                                :disabled="(slot.booked_count ?? 0) > 0"
                                :disabled-reason="t('admin.tours.slot_has_bookings')"
                            />
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <form class="rounded-xl border border-dashed border-border p-4" @submit.prevent="submit">
            <h3 class="mb-3 font-body text-base font-bold">{{ t('admin.tours.add_slot') }}</h3>
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
                <FormField v-model="form.starts_at" type="datetime-local" class="lg:col-span-2" :label="t('admin.tours.starts_at')" :error="form.errors.starts_at" required />
                <FormField v-model="form.duration_minutes" type="number" min="30" max="1440" :label="t('admin.tours.duration_minutes')" :error="form.errors.duration_minutes" />
                <FormField v-model="form.capacity" type="number" min="1" max="500" :label="t('admin.tours.capacity')" :error="form.errors.capacity" required />
                <FormField v-model="form.language" type="select" :label="t('admin.tours.language')" :options="languageOptions" :error="form.errors.language" required />
                <FormField v-model="form.guide_name" maxlength="120" class="sm:col-span-2 lg:col-span-4" :label="t('admin.tours.guide')" :error="form.errors.guide_name" />
                <div class="flex items-end">
                    <button type="submit" class="btn btn-primary w-full" :disabled="form.processing"><Icon name="plus" :size="18" />{{ t('admin.tours.add_slot') }}</button>
                </div>
            </div>
        </form>
    </div>
</template>
