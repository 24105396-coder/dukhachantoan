<script setup>
import { computed, ref, watch, useId } from 'vue';
import MapBase from '@/Components/MapBase.vue';
import { useT } from '@/Composables/useT';
import { parseGeoJson, circleGeometry, circleCenter, MAX_RADIUS_KM } from '@/Utils/geo';

/**
 * Phạm vi ảnh hưởng của cảnh báo/vùng, 3 chế độ:
 * - none: theo các vùng đã chọn (geometry = null)
 * - circle: tâm + bán kính km (nhập số hoặc bấm lên bản đồ để đặt tâm) → { type:'Circle', center:[lng,lat], radius_km }
 * - geojson: dán Polygon/MultiPolygon (tọa độ [lng, lat])
 * v-model: geometry object | null. Xem trước trên bản đồ ngay khi hợp lệ.
 */
const props = defineProps({
    error: { type: String, default: '' },
    level: { type: Number, default: 3 },
    allowNone: { type: Boolean, default: true },
    noneLabel: { type: String, default: '' },
    allowCircle: { type: Boolean, default: true },
});
const model = defineModel({ type: Object, default: null });
const { t } = useT();
const uid = useId();

function initialMode(g) {
    if (!g) return props.allowNone ? 'none' : (props.allowCircle ? 'circle' : 'geojson');
    return g.type === 'Circle' ? 'circle' : 'geojson';
}
const mode = ref(initialMode(model.value));
const c0 = model.value?.type === 'Circle' ? circleCenter(model.value.center) : null;
const lat = ref(c0?.lat ?? '');
const lng = ref(c0?.lng ?? '');
const radius = ref(model.value?.type === 'Circle' ? model.value.radius_km : 10);
const text = ref(model.value && model.value.type !== 'Circle' ? JSON.stringify(model.value, null, 2) : '');
const parseError = ref('');

function sync() {
    parseError.value = '';
    if (mode.value === 'none') {
        model.value = null;
        return;
    }
    if (mode.value === 'circle') {
        model.value = circleGeometry(lat.value, lng.value, radius.value);
        return;
    }
    if (!text.value.trim()) {
        model.value = null;
        return;
    }
    const r = parseGeoJson(text.value);
    if (r.ok) model.value = r.geometry;
    else {
        parseError.value = t(`geometry.err_${r.error}`);
        model.value = null;
    }
}
watch([mode, lat, lng, radius], sync);
function onMapClick(p) {
    if (mode.value !== 'circle') return;
    lat.value = Number(p.lat.toFixed(5));
    lng.value = Number(p.lng.toFixed(5));
}
const overlays = computed(() => (model.value ? [{ id: 'preview', geometry: model.value, level: props.level, label: t('geometry.preview') }] : []));
const modes = computed(() => [
    ...(props.allowNone ? [{ value: 'none', label: props.noneLabel || t('geometry.mode_none') }] : []),
    ...(props.allowCircle ? [{ value: 'circle', label: t('geometry.mode_circle') }] : []),
    { value: 'geojson', label: t('geometry.mode_geojson') },
]);
</script>

<template>
    <div class="space-y-4">
        <fieldset>
            <legend class="field-label">{{ t('geometry.mode') }}</legend>
            <div class="flex flex-wrap gap-2">
                <label v-for="m in modes" :key="m.value" class="pill cursor-pointer py-1.5" :class="mode === m.value ? 'border-brand-500 bg-brand-100 text-ink-950 dark:bg-brand-700 dark:text-white' : ''">
                    <input v-model="mode" type="radio" :name="`gm-${uid}`" :value="m.value" class="accent-brand-600" />{{ m.label }}
                </label>
            </div>
        </fieldset>

        <div v-if="mode === 'circle'" class="grid gap-3 sm:grid-cols-3">
            <div>
                <label :for="`g-lat-${uid}`" class="field-label">{{ t('geometry.lat') }}</label>
                <input :id="`g-lat-${uid}`" v-model.number="lat" type="number" step="0.00001" min="-90" max="90" class="clay-input" inputmode="decimal" />
            </div>
            <div>
                <label :for="`g-lng-${uid}`" class="field-label">{{ t('geometry.lng') }}</label>
                <input :id="`g-lng-${uid}`" v-model.number="lng" type="number" step="0.00001" min="-180" max="180" class="clay-input" inputmode="decimal" />
            </div>
            <div>
                <label :for="`g-r-${uid}`" class="field-label">{{ t('geometry.radius') }}</label>
                <input :id="`g-r-${uid}`" v-model.number="radius" type="number" step="0.5" min="0.5" :max="MAX_RADIUS_KM" class="clay-input" inputmode="decimal" />
            </div>
            <p class="field-hint sm:col-span-3">{{ t('geometry.circle_hint', { max: MAX_RADIUS_KM }) }}</p>
        </div>

        <div v-else-if="mode === 'geojson'">
            <label :for="`g-json-${uid}`" class="field-label">{{ t('geometry.geojson') }}</label>
            <textarea
                :id="`g-json-${uid}`"
                v-model="text"
                rows="6"
                class="clay-input font-mono text-xs"
                spellcheck="false"
                :placeholder="'{&quot;type&quot;:&quot;Polygon&quot;,&quot;coordinates&quot;:[[[109.1,12.2],[109.3,12.2],[109.3,12.4],[109.1,12.2]]]}'"
                :aria-invalid="parseError || error ? 'true' : undefined"
                :aria-describedby="`g-json-h-${uid}`"
                @blur="sync"
            ></textarea>
            <p :id="`g-json-h-${uid}`" class="field-hint">{{ t('geometry.geojson_hint') }}</p>
            <p v-if="parseError" class="field-error" role="alert">{{ parseError }}</p>
        </div>
        <p v-if="error" class="field-error" role="alert">{{ error }}</p>

        <MapBase
            v-if="mode !== 'none'"
            :overlays="overlays"
            :show-legend="false"
            height-class="h-[260px] md:h-[320px]"
            :aria-label="t('geometry.preview')"
            class="clay-card-sm p-2"
            bare
            @mapclick="onMapClick"
        />
        <p v-if="mode === 'circle'" class="text-xs text-muted">{{ t('geometry.click_hint') }}</p>
    </div>
</template>
