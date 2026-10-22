<script setup>
import { computed } from 'vue';
import Button from '@/Components/Button.vue';
import FormField from '@/Components/FormField.vue';
import { useT } from '@/Composables/useT';
import { emptyLeg, MAX_LEGS } from '@/Utils/tripForm';

/**
 * Sửa danh sách chặng: thêm/xóa/đổi thứ tự (nút lên/xuống, dùng được bằng bàn phím), chọn vùng rồi điểm đến
 * (lọc theo vùng), giờ bắt đầu/kết thúc giới hạn trong khoảng ngày hành trình. Lỗi đọc theo khóa `legs.i.field`.
 */
const props = defineProps({
    form: { type: Object, required: true }, // useForm
    zones: { type: Array, default: () => [] },
    destinations: { type: Array, default: () => [] },
});
const { t } = useT();

const zoneOptions = computed(() => props.zones.map((z) => ({
    value: z.code,
    label: z.province?.name && z.province.name !== z.name ? `${z.name} (${z.province.name})` : z.name,
})));
const destOptions = (zoneCode) => props.destinations
    .filter((d) => d.zone?.code === zoneCode)
    .map((d) => ({ value: d.slug, label: d.name }));
const minDt = computed(() => (props.form.start_date ? `${props.form.start_date}T00:00` : undefined));
const maxDt = computed(() => (props.form.end_date ? `${props.form.end_date}T23:59` : undefined));
const err = (i, f) => props.form.errors[`legs.${i}.${f}`];

function add() {
    if (props.form.legs.length >= MAX_LEGS) return;
    const last = props.form.legs[props.form.legs.length - 1];
    const day = last?.ends_at ? last.ends_at.slice(0, 10) : props.form.start_date;
    props.form.legs = [...props.form.legs, emptyLeg(day)];
}
function remove(i) {
    props.form.legs = props.form.legs.filter((_, k) => k !== i);
}
function move(i, dir) {
    const j = i + dir;
    if (j < 0 || j >= props.form.legs.length) return;
    const next = [...props.form.legs];
    [next[i], next[j]] = [next[j], next[i]];
    props.form.legs = next;
}
function onZone(leg) {
    // Đổi vùng thì bỏ điểm đến không còn thuộc vùng
    if (leg.destination_slug && !destOptions(leg.zone_code).some((d) => d.value === leg.destination_slug)) leg.destination_slug = '';
}
</script>

<template>
    <div class="space-y-4">
        <p v-if="form.errors.legs" class="field-error rounded-xl bg-level-4-soft p-3" role="alert">{{ form.errors.legs }}</p>
        <ol class="space-y-4">
            <li v-for="(leg, i) in form.legs" :key="i" class="clay-card-sm p-4 md:p-5">
                <fieldset>
                    <legend class="sr-only">{{ t('trip.leg_n', { n: i + 1 }) }}</legend>
                    <div class="mb-3 flex flex-wrap items-center gap-2">
                        <span class="font-display text-lg font-bold" aria-hidden="true">{{ t('trip.leg_n', { n: i + 1 }) }}</span>
                        <div class="ml-auto flex gap-1">
                            <Button size="sm" variant="ghost" icon="arrow-up" icon-only :label="t('trip_form.move_up', { n: i + 1 })" :disabled="i === 0" @click="move(i, -1)" />
                            <Button size="sm" variant="ghost" icon="arrow-down" icon-only :label="t('trip_form.move_down', { n: i + 1 })" :disabled="i === form.legs.length - 1" @click="move(i, 1)" />
                            <Button size="sm" variant="ghost" icon="trash" icon-only :label="t('trip_form.remove_leg', { n: i + 1 })" :disabled="form.legs.length === 1" @click="remove(i)" />
                        </div>
                    </div>
                    <div class="grid gap-4 md:grid-cols-2">
                        <FormField v-model="leg.zone_code" type="select" :label="t('trip_form.zone')" :options="zoneOptions" :placeholder="t('admin.form.choose')" required :error="err(i, 'zone_code')" @update:model-value="onZone(leg)" />
                        <FormField
                            v-model="leg.destination_slug"
                            type="select"
                            :label="t('trip_form.destination')"
                            :options="destOptions(leg.zone_code)"
                            :placeholder="leg.zone_code ? t('trip_form.destination_any') : t('trip_form.choose_zone_first')"
                            :disabled="!leg.zone_code || !destOptions(leg.zone_code).length"
                            :hint="leg.zone_code && !destOptions(leg.zone_code).length ? t('trip_form.no_destinations') : ''"
                            :error="err(i, 'destination_slug')"
                        />
                        <FormField v-model="leg.starts_at" type="datetime-local" :label="t('trip_form.starts_at')" :min="minDt" :max="maxDt" required :error="err(i, 'starts_at')" />
                        <FormField v-model="leg.ends_at" type="datetime-local" :label="t('trip_form.ends_at')" :min="leg.starts_at || minDt" :max="maxDt" required :error="err(i, 'ends_at')" />
                        <FormField v-model="leg.lodging" :label="t('trip.lodging')" :placeholder="t('trip_form.lodging_placeholder')" maxlength="255" :error="err(i, 'lodging')" />
                        <FormField v-model="leg.transport" :label="t('trip.transport')" :placeholder="t('trip_form.transport_placeholder')" maxlength="100" :error="err(i, 'transport')" />
                        <FormField v-model="leg.note" type="textarea" :rows="2" :label="t('common.note')" maxlength="500" class="md:col-span-2" :error="err(i, 'note')" />
                    </div>
                </fieldset>
            </li>
        </ol>
        <Button icon="plus" :disabled="form.legs.length >= MAX_LEGS" @click="add">{{ t('trip_form.add_leg') }}</Button>
        <p class="text-xs text-muted">{{ t('trip_form.legs_hint') }}</p>
    </div>
</template>
