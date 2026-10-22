<script setup>
import { PHONE_PATTERN, PHONE_RE, stripPhone } from '@/Utils/rules';
import { ref, computed } from 'vue';
import { Link, useForm, usePage } from '@inertiajs/vue3';
import Breadcrumbs from '@/Components/Breadcrumbs.vue';
import CoverImage from '@/Components/CoverImage.vue';
import MapView from '@/Components/MapView.vue';
import Icon from '@/Components/Icon.vue';
import EmptyState from '@/Components/EmptyState.vue';
import FormField from '@/Components/FormField.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import { useT } from '@/Composables/useT';
import { useFormat } from '@/Composables/useFormat';
import { useLocaleRoute } from '@/Composables/useLocaleRoute';
import PageMeta from '@/Components/Public/PageMeta.vue';
import { useClientValidation } from '@/Composables/useClientValidation';

const props = defineProps({
    route: { type: Object, required: true },
    holdMinutes: { type: Number, default: 15 },
    canBook: { type: Boolean, default: false },
    // Số giờ trước giờ khởi hành còn được hủy miễn phí (config disanso.booking.cancel_before_hours)
    cancelBeforeHours: { type: Number, default: 24 },
});
const page = usePage();
const { t, te } = useT();
const { money, duration, number, longDate, time } = useFormat();
const lr = useLocaleRoute();

const MAX_QTY = 10;
const user = computed(() => page.props.auth?.user || null);
const stops = computed(() => [...(props.route.stops || [])].sort((a, b) => a.stop_order - b.stop_order));
const slots = computed(() => props.route.slots || []);
const mapPoints = computed(() =>
    stops.value
        .filter((s) => s.site?.lat != null && s.site?.lng != null)
        .map((s) => ({
            lat: s.site.lat,
            lng: s.site.lng,
            title: `${s.stop_order}. ${s.site.title}`,
            url: s.site.url,
            cover: s.site.cover?.url,
            subtitle: s.site.province?.name,
        })),
);
const highlights = computed(() => {
    const h = props.route.highlights;
    if (!h) return [];
    if (Array.isArray(h)) return h;
    return String(h).split(/\r?\n/).map((x) => x.replace(/^[-•*]\s*/, '').trim()).filter(Boolean);
});

const selectedId = ref(slots.value.find((s) => isBookable(s))?.id ?? null);
const selected = computed(() => slots.value.find((s) => s.id === selectedId.value) || null);

function isBookable(s) {
    return s.status === 'open' && (s.available ?? 0) > 0 && new Date(s.starts_at).getTime() > Date.now();
}

const form = useForm({
    quantity: 1,
    contact_name: user.value?.name || '',
    contact_phone: user.value?.phone || '',
    contact_email: user.value?.email || '',
    note: '',
});
const maxQty = computed(() => Math.min(MAX_QTY, selected.value?.available ?? MAX_QTY));
const total = computed(() => (Number(form.quantity) || 0) * (props.route.price_vnd || 0));

// Số khách chỉ kiểm biên nghiệp vụ 1..MAX_QTY; số chỗ còn lại do máy chủ kiểm trong transaction (báo "Chỉ còn N chỗ")
const { validate } = useClientValidation(form, {
    quantity: { label: 'booking.quantity', required: true, number: { min: 1, max: MAX_QTY, integer: true } },
    contact_name: { label: 'booking.contact_name', required: true, min: 2, max: 120 },
    contact_phone: { label: 'booking.contact_phone', required: true, pattern: { regex: PHONE_RE, message: 'phone', normalize: stripPhone } },
    contact_email: { label: 'booking.contact_email', required: true, email: true, max: 190 },
    note: { label: 'common.note', max: 500 },
});

function submit(e) {
    if (!selected.value || !validate(e)) return;
    form.post(lr(`/dat-lich/${selected.value.id}`), { preserveScroll: true });
}
</script>

<template>
    <PageMeta :title="route.name" :description="route.description || ''" fallback-key="tours.intro" :image="route.cover?.url" />
    <div class="container-page py-6">
        <Breadcrumbs :items="[{ label: t('nav.home'), href: lr('/') }, { label: t('nav.tours'), href: lr('/tuyen-tham-quan') }, { label: route.name }]" />

        <section class="mt-4 grid gap-6 lg:grid-cols-5">
            <div class="lg:col-span-3">
                <CoverImage :src="route.cover?.url" :alt="route.cover?.caption || route.name" icon="route" ratio="aspect-[16/9]" eager class="rounded-xl" />
            </div>
            <div class="lg:col-span-2">
                <p v-if="route.theme" class="eyebrow">{{ route.theme.name }}</p>
                <h1 class="mt-1 text-3xl font-bold md:text-4xl">{{ route.name }}</h1>
                <ul class="mt-4 grid grid-cols-2 gap-3 text-sm">
                    <li class="card p-3"><span class="block text-xs text-muted">{{ t('tours.duration') }}</span><strong>{{ duration(route.duration_minutes) }}</strong></li>
                    <li class="card p-3"><span class="block text-xs text-muted">{{ t('tours.distance') }}</span><strong>{{ route.distance_km ? `${number(route.distance_km, 1)} km` : '—' }}</strong></li>
                    <li class="card p-3"><span class="block text-xs text-muted">{{ t('tours.difficulty') }}</span><strong>{{ te('difficulty', route.difficulty) || '—' }}</strong></li>
                    <li class="card p-3"><span class="block text-xs text-muted">{{ t('tours.price_per_person') }}</span><strong class="text-primary">{{ money(route.price_vnd) }}</strong></li>
                </ul>
                <p v-if="route.province" class="mt-3 flex items-center gap-1 text-sm text-muted"><Icon name="pin" :size="16" />{{ route.province.name }}</p>
                <p v-if="route.owner" class="mt-1 text-sm text-muted">{{ t('tours.operated_by') }}: <span class="font-medium text-text">{{ route.owner.name }}</span></p>
                <div class="mt-4 flex flex-wrap gap-2">
                    <a href="#booking" class="btn btn-primary"><Icon name="ticket" :size="18" />{{ t('tours.book_now') }}</a>
                    <a :href="`/api/v1/ical/routes/${route.slug}.ics`" class="btn"><Icon name="calendar" :size="18" />{{ t('tours.ical') }}</a>
                </div>
            </div>
        </section>

        <div class="mt-10 grid gap-8 lg:grid-cols-3">
            <div class="min-w-0 space-y-10 lg:col-span-2">
                <section>
                    <h2 class="section-title">{{ t('tours.about') }}</h2>
                    <p class="mt-3 whitespace-pre-line text-muted">{{ route.description }}</p>
                    <ul v-if="highlights.length" class="mt-4 space-y-2">
                        <li v-for="(h, i) in highlights" :key="i" class="flex gap-2"><span class="text-accent-strong"><Icon name="star" :size="18" /></span>{{ h }}</li>
                    </ul>
                </section>

                <section>
                    <h2 class="section-title">{{ t('tours.itinerary') }}</h2>
                    <EmptyState v-if="!stops.length" icon="pin" :title="t('tours.no_stops')" compact class="mt-3" />
                    <template v-else>
                        <MapView :points="mapPoints" :cluster="false" height="320px" :label="t('tours.itinerary_map')" class="mt-4" />
                        <ol class="mt-6 space-y-4">
                            <li v-for="s in stops" :key="s.stop_order" class="card flex gap-4 overflow-hidden">
                                <CoverImage :src="s.site?.cover?.url" :alt="s.site?.title" ratio="aspect-square" class="w-24 shrink-0 sm:w-32" />
                                <div class="min-w-0 flex-1 py-3 pr-3">
                                    <p class="eyebrow">{{ t('tours.stop_n', { n: s.stop_order }) }} · {{ duration(s.dwell_minutes) }}</p>
                                    <h3 class="text-base font-bold">
                                        <Link v-if="s.site?.url" :href="s.site.url" class="hover:text-primary">{{ s.site.title }}</Link>
                                        <span v-else>{{ s.site?.title }}</span>
                                    </h3>
                                    <p v-if="s.note" class="mt-1 text-sm text-muted">{{ s.note }}</p>
                                    <p v-else-if="s.site?.summary" class="mt-1 line-clamp-2 text-sm text-muted">{{ s.site.summary }}</p>
                                </div>
                            </li>
                        </ol>
                    </template>
                </section>
            </div>

            <aside id="booking" class="min-w-0 scroll-mt-20 space-y-4">
                <section class="card p-4">
                    <h2 class="font-body text-lg font-bold">{{ t('tours.slots') }}</h2>
                    <EmptyState v-if="!slots.length" icon="calendar" :title="t('tours.no_upcoming')" compact class="mt-3" />
                    <fieldset v-else class="mt-3 space-y-2">
                        <legend class="sr-only">{{ t('tours.choose_slot') }}</legend>
                        <label
                            v-for="s in slots"
                            :key="s.id"
                            class="flex cursor-pointer items-start gap-3 rounded-lg border p-3"
                            :class="[selectedId === s.id ? 'border-primary bg-primary-soft' : 'border-border', !isBookable(s) ? 'cursor-not-allowed opacity-60' : '']"
                        >
                            <input v-model="selectedId" type="radio" name="slot" :value="s.id" :disabled="!isBookable(s)" class="mt-1 accent-[var(--ds-primary)]" />
                            <span class="min-w-0 flex-1 text-sm">
                                <span class="block font-semibold">{{ longDate(s.starts_at) }}</span>
                                <span class="block text-muted">{{ time(s.starts_at) }}<template v-if="s.ends_at"> – {{ time(s.ends_at) }}</template></span>
                                <span class="mt-1 flex flex-wrap gap-x-3 gap-y-1 text-xs text-muted">
                                    <span v-if="s.language">{{ t('tours.language') }}: {{ te('locale', s.language) }}</span>
                                    <span v-if="s.guide_name">{{ t('tours.guide') }}: {{ s.guide_name }}</span>
                                </span>
                            </span>
                            <span class="flex flex-col items-end gap-1 text-xs">
                                <StatusBadge v-if="s.status !== 'open'" :status="s.status" />
                                <span :class="(s.available ?? 0) > 0 ? 'text-success' : 'text-danger'" class="font-semibold">
                                    {{ (s.available ?? 0) > 0 ? t('tours.seats_left', { n: s.available, total: s.capacity }) : t('tours.full') }}
                                </span>
                            </span>
                        </label>
                    </fieldset>
                </section>

                <section class="card p-4">
                    <h2 class="font-body text-lg font-bold">{{ t('tours.booking_form') }}</h2>
                    <div v-if="!user" class="mt-3 text-sm">
                        <p class="text-muted">{{ t('tours.login_to_book') }}</p>
                        <Link :href="lr('/dang-nhap')" class="btn btn-primary mt-3 w-full">{{ t('nav.login') }}</Link>
                    </div>
                    <p v-else-if="!canBook" class="mt-3 rounded-lg bg-warning-soft p-3 text-sm text-warning">{{ t('tours.cannot_book') }}</p>
                    <p v-else-if="!selected" class="mt-3 text-sm text-muted">{{ t('tours.choose_slot') }}</p>
                    <form v-else class="mt-3 space-y-3" novalidate @submit.prevent="submit">
                        <FormField v-model.number="form.quantity" :label="t('booking.quantity')" type="number" :min="1" :max="maxQty" required :error="form.errors.quantity" :hint="t('booking.quantity_hint', { n: maxQty })" />
                        <FormField v-model="form.contact_name" :label="t('booking.contact_name')" required minlength="2" maxlength="120" autocomplete="name" :error="form.errors.contact_name" />
                        <FormField v-model="form.contact_phone" :label="t('booking.contact_phone')" type="tel" required :pattern="PHONE_PATTERN" :pattern-hint="t('validation_hint.phone')" maxlength="16" autocomplete="tel" :error="form.errors.contact_phone" />
                        <FormField v-model="form.contact_email" :label="t('booking.contact_email')" type="email" required maxlength="190" autocomplete="email" :error="form.errors.contact_email" />
                        <FormField v-model="form.note" :label="`${t('common.note')} (${t('common.optional')})`" type="textarea" :rows="3" maxlength="500" :error="form.errors.note" />
                        <div class="flex items-center justify-between border-t border-border pt-3">
                            <span class="text-sm text-muted">{{ t('booking.estimated_total') }}</span>
                            <strong class="font-display text-xl text-primary">{{ money(total) }}</strong>
                        </div>
                        <p class="text-xs text-muted">{{ t('tours.hold_note', { n: holdMinutes }) }}</p>
                        <p class="flex gap-1.5 text-xs text-muted"><Icon name="info" :size="14" class="mt-0.5 shrink-0" />{{ t('tours.cancel_policy', { n: cancelBeforeHours }) }}</p>
                        <button type="submit" class="btn btn-primary w-full" :disabled="form.processing">
                            <Icon name="ticket" :size="18" />{{ form.processing ? t('common.saving') : t('tours.hold_seats') }}
                        </button>
                    </form>
                </section>
            </aside>
        </div>
    </div>
</template>
