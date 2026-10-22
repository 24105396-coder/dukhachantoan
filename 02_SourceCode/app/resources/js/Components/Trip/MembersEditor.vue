<script setup>
import { computed } from 'vue';
import Button from '@/Components/Button.vue';
import FormField from '@/Components/FormField.vue';
import EmptyState from '@/Components/EmptyState.vue';
import { useT } from '@/Composables/useT';
import { emptyMember, AGE_GROUPS, MAX_MEMBERS } from '@/Utils/tripForm';
import { nationalityOptions } from '@/Utils/countries';
import { PHONE_PATTERN } from '@/Utils/rules';

/** Danh sách thành viên đoàn (tùy chọn): họ tên, điện thoại (che 4 số giữa khi hiển thị lại), quốc tịch, nhóm tuổi, nhu cầu đặc biệt. */
const props = defineProps({
    form: { type: Object, required: true },
});
const { t, te, locale } = useT();
const natOptions = computed(() => nationalityOptions(locale.value));
const ageOptions = computed(() => AGE_GROUPS.map((a) => ({ value: a, label: te('age_group', a) })));
const err = (i, f) => props.form.errors[`members.${i}.${f}`];

function add() {
    if (props.form.members.length >= MAX_MEMBERS) return;
    props.form.members = [...props.form.members, emptyMember()];
}
function remove(i) {
    props.form.members = props.form.members.filter((_, k) => k !== i);
}
</script>

<template>
    <div class="space-y-4">
        <p class="text-sm text-muted">{{ t('trip_form.members_intro') }}</p>
        <ol v-if="form.members.length" class="space-y-4">
            <li v-for="(m, i) in form.members" :key="i" class="clay-card-sm p-4 md:p-5">
                <fieldset>
                    <legend class="mb-3 font-display text-lg font-bold">{{ t('trip_form.member_n', { n: i + 1 }) }}</legend>
                    <div class="grid gap-4 md:grid-cols-2">
                        <FormField v-model="m.full_name" :label="t('trip_form.member_name')" required maxlength="120" autocomplete="off" :error="err(i, 'full_name')" />
                        <FormField
                            v-model="m.phone"
                            type="tel"
                            :label="t('common.phone')"
                            :pattern="PHONE_PATTERN"
                            inputmode="tel"
                            autocomplete="off"
                            :hint="m.phone_masked ? t('trip_form.phone_keep', { masked: m.phone_masked }) : t('validation_hint.phone')"
                            :error="err(i, 'phone')"
                        />
                        <FormField v-model="m.nationality" type="select" :label="t('account.nationality')" :options="natOptions" :placeholder="t('auth.nationality_placeholder')" :error="err(i, 'nationality')" />
                        <FormField v-model="m.age_group" type="select" :label="t('trip_form.age_group')" :options="ageOptions" :error="err(i, 'age_group')" />
                        <FormField v-model="m.special_needs" :label="t('trip_form.special_needs')" :placeholder="t('trip_form.special_needs_placeholder')" maxlength="255" class="md:col-span-2" :error="err(i, 'special_needs')" />
                    </div>
                    <div class="mt-3 flex justify-end">
                        <Button size="sm" variant="ghost" icon="trash" @click="remove(i)">{{ t('trip_form.remove_member', { n: i + 1 }) }}</Button>
                    </div>
                </fieldset>
            </li>
        </ol>
        <EmptyState v-else :title="t('trip_form.no_members')" :description="t('trip_form.no_members_hint')" compact />
        <Button icon="user-plus" :disabled="form.members.length >= MAX_MEMBERS" @click="add">{{ t('trip_form.add_member') }}</Button>
    </div>
</template>
