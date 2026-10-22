<script setup>
import Badge from '@/Components/Badge.vue';
import { useT } from '@/Composables/useT';
import { ALERT_LEVELS, CHANNELS } from '@/Utils/options';

/**
 * Ma trận chọn kênh nhận thông báo theo mức cảnh báo: hàng = mức 1–4, cột = inapp/email/sms.
 * Mức trong `lockedLevels` luôn bật mọi kênh (máy chủ cũng áp quy tắc này).
 * v-model là object { channels_level1: ['inapp', ...], ..., channels_level4: [...] } (bản sao mới mỗi lần đổi).
 */
const props = defineProps({
    channels: { type: Array, default: () => CHANNELS },
    errors: { type: Object, default: () => ({}) },
    // Mức luôn gửi qua mọi kênh (mức 4 khẩn cấp): ô được đánh dấu sẵn và khóa
    lockedLevels: { type: Array, default: () => [4] },
});
const model = defineModel({ type: Object, required: true });
const { t, te } = useT();

const keyOf = (level) => `channels_level${level}`;
const isLocked = (level) => props.lockedLevels.includes(level);
const isChecked = (level, ch) => isLocked(level) || (model.value?.[keyOf(level)] || []).includes(ch);
function toggle(level, ch, checked) {
    const current = model.value?.[keyOf(level)] || [];
    const next = checked ? [...new Set([...current, ch])] : current.filter((c) => c !== ch);
    model.value = { ...model.value, [keyOf(level)]: next };
}
const errorOf = (level) => props.errors?.[`preferences.${keyOf(level)}`] || props.errors?.[keyOf(level)];
</script>

<template>
    <div class="overflow-x-auto rounded-[20px] border border-line">
        <table class="table-clay">
            <caption class="sr-only">{{ t('account.channels_caption') }}</caption>
            <thead>
                <tr>
                    <th scope="col">{{ t('account.level') }}</th>
                    <th v-for="ch in channels" :key="ch" scope="col" class="text-center">{{ te('channel', ch) }}</th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="level in ALERT_LEVELS" :key="level">
                    <th scope="row" class="bg-transparent text-left normal-case tracking-normal">
                        <Badge :level="level" size="sm" />
                        <p v-if="errorOf(level)" class="field-error" role="alert">{{ errorOf(level) }}</p>
                    </th>
                    <td v-for="ch in channels" :key="ch" class="text-center">
                        <input
                            type="checkbox"
                            class="clay-checkbox"
                            :checked="isChecked(level, ch)"
                            :disabled="isLocked(level)"
                            :aria-label="t('account.channel_for', { channel: te('channel', ch), level: t(`enums.levels.${level}`) })"
                            @change="toggle(level, ch, $event.target.checked)"
                        />
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</template>
