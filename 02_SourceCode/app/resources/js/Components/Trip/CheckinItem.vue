<script setup>
import { computed } from 'vue';
import Icon from '@/Components/Icon.vue';
import { useT } from '@/Composables/useT';
import { useFormat } from '@/Composables/useFormat';

/** Một lần báo an toàn (Resource CheckinItem): trạng thái (màu + icon + chữ), người báo, lời nhắn, vị trí, phản hồi của cơ quan. */
const props = defineProps({
    checkin: { type: Object, required: true },
    showResponse: { type: Boolean, default: true },
});
const { t, te } = useT();
const { dateTime, ago, number } = useFormat();
const META = {
    safe: { icon: 'shield-check', cls: 'border-level-0 bg-level-0-soft' },
    need_help: { icon: 'siren', cls: 'border-level-4 bg-level-4-soft' },
    unknown: { icon: 'help', cls: 'border-line bg-soft' },
};
const meta = computed(() => META[props.checkin.status] || META.unknown);
const hasLocation = computed(() => props.checkin.lat !== null && props.checkin.lat !== undefined && props.checkin.lng !== null && props.checkin.lng !== undefined);
const mapsHref = computed(() => `https://www.openstreetmap.org/?mlat=${props.checkin.lat}&mlon=${props.checkin.lng}#map=15/${props.checkin.lat}/${props.checkin.lng}`);
</script>

<template>
    <article class="rounded-[20px] border p-3.5" :class="meta.cls" :data-status="checkin.status">
        <div class="flex items-start gap-3">
            <span class="icon-tile h-10 w-10"><Icon :name="meta.icon" :size="20" /></span>
            <div class="min-w-0 flex-1">
                <p class="font-bold">
                    {{ te('checkin_status', checkin.status) }}
                    <span class="font-normal text-muted">· {{ checkin.user?.name || t('dashboard.anonymous') }}</span>
                </p>
                <p v-if="checkin.message" class="mt-0.5 break-words text-sm">“{{ checkin.message }}”</p>
                <p class="mt-1 text-xs text-muted">
                    <time :datetime="checkin.checked_at" :title="dateTime(checkin.checked_at)">{{ ago(checkin.checked_at) }}</time>
                    <template v-if="checkin.source"> · {{ te('checkin_source', checkin.source) }}</template>
                    <template v-if="checkin.alert"> · {{ checkin.alert.code }}</template>
                    <template v-if="hasLocation">
                        ·
                        <a :href="mapsHref" target="_blank" rel="noopener noreferrer" class="link">{{ number(checkin.lat, 4) }}, {{ number(checkin.lng, 4) }}</a>
                    </template>
                </p>
                <div v-if="showResponse && checkin.response_status" class="mt-2 rounded-xl bg-surface p-2 text-sm">
                    <p class="font-semibold"><Icon name="user-check" :size="14" class="inline align-[-2px]" /> {{ te('status', checkin.response_status) }}<template v-if="checkin.responder?.name"> · {{ checkin.responder.name }}</template></p>
                    <p v-if="checkin.response_note" class="text-muted">{{ checkin.response_note }}</p>
                    <p v-if="checkin.responded_at" class="text-xs text-muted">{{ dateTime(checkin.responded_at) }}</p>
                </div>
            </div>
        </div>
    </article>
</template>
