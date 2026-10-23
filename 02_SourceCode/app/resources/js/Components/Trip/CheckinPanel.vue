<script setup>
import { computed, useId } from 'vue';
import { useForm } from '@inertiajs/vue3';
import Icon from '@/Components/Icon.vue';
import Button from '@/Components/Button.vue';
import { useT } from '@/Composables/useT';
import { useGeolocation } from '@/Composables/useGeolocation';
import { useFormat } from '@/Composables/useFormat';

/**
 * Báo an toàn một chạm: hai nút lớn "An toàn" / "Cần hỗ trợ" (gửi ngay), lời nhắn và vị trí tùy chọn
 * (chỉ lấy khi người dùng bấm "Gắn vị trí" – trình duyệt xin phép). Dùng cho Trips/Show và Trips/SharedCheckin.
 * alerts: [AlertCard] – cho phép gắn cảnh báo liên quan (alert_code) khi báo từ trang hành trình.
 */
const props = defineProps({
    url: { type: String, required: true },
    alerts: { type: Array, default: () => [] },
    title: { type: String, default: '' },
    headingLevel: { type: Number, default: 2 },
});
const emit = defineEmits(['sent']);
const { t } = useT();
const { number } = useFormat();
const uid = useId();
const geo = useGeolocation();
const MESSAGE_MAX = 500;

const form = useForm({ status: '', message: '', lat: null, lng: null, alert_code: '' });
const headingTag = computed(() => `h${Math.min(6, Math.max(2, props.headingLevel))}`);

async function attachLocation() {
    const c = await geo.locate();
    if (c) {
        form.lat = c.lat;
        form.lng = c.lng;
    }
}
function removeLocation() {
    geo.clear();
    form.lat = null;
    form.lng = null;
}

function send(status) {
    form.status = status;
    form
        .transform((data) => ({ ...data, alert_code: data.alert_code || null, message: data.message?.trim() || null }))
        .post(props.url, {
            preserveScroll: true,
            onSuccess: () => {
                form.reset('message', 'alert_code');
                emit('sent', status);
            },
        });
}
const geoMessage = computed(() => {
    if (geo.status.value === 'denied') return t('checkin.geo_denied');
    if (geo.status.value === 'unavailable') return t('checkin.geo_unavailable');
    if (geo.status.value === 'unsupported') return t('checkin.geo_unsupported');
    return '';
});
</script>

<template>
    <section class="clay-card-sm space-y-4 p-4 md:p-5" :aria-labelledby="`ck-${uid}`">
        <component :is="headingTag" :id="`ck-${uid}`" class="section-title text-lg">{{ title || t('checkin.title') }}</component>
        <p class="text-sm text-muted">{{ t('checkin.intro') }}</p>

        <div>
            <label :for="`ck-msg-${uid}`" class="field-label">{{ t('checkin.message') }} <span class="font-normal text-muted">({{ t('common.optional') }})</span></label>
            <textarea
                :id="`ck-msg-${uid}`"
                v-model="form.message"
                rows="2"
                class="clay-input"
                :maxlength="MESSAGE_MAX"
                :placeholder="t('checkin.message_placeholder')"
                :aria-invalid="form.errors.message ? 'true' : undefined"
                :aria-describedby="form.errors.message ? `ck-msg-err-${uid}` : undefined"
            ></textarea>
            <p v-if="form.errors.message" :id="`ck-msg-err-${uid}`" class="field-error" role="alert">{{ form.errors.message }}</p>
        </div>

        <div v-if="alerts.length">
            <label :for="`ck-al-${uid}`" class="field-label">{{ t('checkin.related_alert') }} <span class="font-normal text-muted">({{ t('common.optional') }})</span></label>
            <select :id="`ck-al-${uid}`" v-model="form.alert_code" class="clay-input">
                <option value="">{{ t('common.none') }}</option>
                <option v-for="a in alerts" :key="a.code" :value="a.code">{{ a.code }} – {{ a.headline }}</option>
            </select>
        </div>

        <div class="flex flex-wrap items-center gap-2 text-sm">
            <Button v-if="form.lat === null" size="sm" icon="locate" :loading="geo.status.value === 'locating'" @click="attachLocation">{{ t('checkin.attach_location') }}</Button>
            <template v-else>
                <span class="pill"><Icon name="map-pin" :size="14" />{{ t('checkin.location_attached', { lat: number(form.lat, 4), lng: number(form.lng, 4) }) }}</span>
                <Button size="sm" variant="ghost" icon="map-pin-off" @click="removeLocation">{{ t('checkin.remove_location') }}</Button>
            </template>
            <p v-if="geoMessage" class="w-full text-xs text-danger" role="status">{{ geoMessage }}</p>
            <p v-else class="w-full text-xs text-muted">{{ t('checkin.location_hint') }}</p>
        </div>

        <div class="grid gap-3 sm:grid-cols-2">
            <button
                type="button"
                class="clay-btn clay-btn-primary clay-btn-lg min-h-20 flex-col gap-1 text-lg"
                :disabled="form.processing"
                :aria-busy="form.processing && form.status === 'safe' ? 'true' : undefined"
                data-checkin="safe"
                @click="send('safe')"
            >
                <Icon name="shield-check" :size="28" />{{ t('checkin.safe') }}
            </button>
            <button
                type="button"
                class="clay-btn clay-btn-danger clay-btn-lg min-h-20 flex-col gap-1 text-lg"
                :disabled="form.processing"
                :aria-busy="form.processing && form.status === 'need_help' ? 'true' : undefined"
                data-checkin="need_help"
                @click="send('need_help')"
            >
                <Icon name="siren" :size="28" />{{ t('checkin.need_help') }}
            </button>
        </div>
        <p v-if="form.errors.status || form.errors.lat" class="field-error" role="alert">{{ form.errors.status || form.errors.lat }}</p>
        <p class="text-xs text-muted">{{ t('checkin.need_help_hint') }}</p>
        <p v-if="form.recentlySuccessful" class="callout callout-0 p-2 text-sm font-semibold" role="status">{{ t('checkin.sent') }}</p>
    </section>
</template>
