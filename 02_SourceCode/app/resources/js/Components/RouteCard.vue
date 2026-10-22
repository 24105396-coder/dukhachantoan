<script setup>
import { Link } from '@inertiajs/vue3';
import CoverImage from '@/Components/CoverImage.vue';
import Icon from '@/Components/Icon.vue';
import { useT } from '@/Composables/useT';
import { useFormat } from '@/Composables/useFormat';

defineProps({
    route: { type: Object, required: true },
    // Cấp tiêu đề theo ngữ cảnh: 2 khi danh sách đứng ngay dưới h1, 3 khi nằm trong một mục h2
    headingLevel: { type: Number, default: 3 },
    // Ảnh của thẻ đầu danh sách thường là LCP: tải ngay, không lazy
    eager: { type: Boolean, default: false },
});
const { t, te } = useT();
const { money, duration, dateTime, number } = useFormat();
</script>

<template>
    <article class="card card-hover flex h-full flex-col overflow-hidden">
        <Link :href="route.url" tabindex="-1" aria-hidden="true">
            <CoverImage :src="route.cover?.url" :alt="route.cover?.caption || route.name" icon="route" ratio="aspect-[16/9]" :eager="eager">
                <span v-if="route.difficulty" class="badge absolute left-3 top-3 shadow">{{ te('difficulty', route.difficulty) }}</span>
            </CoverImage>
        </Link>
        <div class="flex flex-1 flex-col gap-2 p-4">
            <p v-if="route.theme" class="eyebrow">{{ route.theme.name }}</p>
            <component :is="`h${headingLevel}`" class="text-lg font-bold">
                <Link :href="route.url" class="hover:text-primary">{{ route.name }}</Link>
            </component>
            <p class="line-clamp-2 text-sm text-muted">{{ route.description }}</p>
            <ul class="flex flex-wrap gap-x-4 gap-y-1 text-xs text-muted">
                <li class="inline-flex items-center gap-1"><Icon name="clock" :size="14" />{{ duration(route.duration_minutes) }}</li>
                <li v-if="route.distance_km" class="inline-flex items-center gap-1"><Icon name="route" :size="14" />{{ number(route.distance_km, 1) }} km</li>
                <li class="inline-flex items-center gap-1"><Icon name="pin" :size="14" />{{ t('tours.stops_count', { n: route.stops_count ?? 0 }) }}</li>
            </ul>
            <div class="mt-auto flex items-end justify-between gap-2 border-t border-border pt-3">
                <div class="text-xs text-muted">
                    <template v-if="route.next_slot">
                        {{ t('tours.next_slot') }}<br />
                        <span class="font-semibold text-text">{{ dateTime(route.next_slot.starts_at) }}</span>
                    </template>
                    <template v-else>{{ t('tours.no_upcoming') }}</template>
                </div>
                <p class="text-right font-display text-lg font-bold text-primary">{{ money(route.price_vnd) }}</p>
            </div>
        </div>
    </article>
</template>
