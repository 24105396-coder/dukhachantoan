<script setup>
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import PageMeta from '@/Components/Public/PageMeta.vue';
import Button from '@/Components/Button.vue';
import CheckinPanel from '@/Components/Trip/CheckinPanel.vue';
import HotlineStrip from '@/Components/Home/HotlineStrip.vue';
import { useT } from '@/Composables/useT';
import { useFormat } from '@/Composables/useFormat';

/**
 * Báo an toàn qua liên kết chia sẻ (không cần tài khoản): hai nút lớn, lời nhắn, vị trí tùy chọn.
 * GET và POST cùng URL; máy chủ giới hạn 10 lần/giờ/token. trip: thông tin rút gọn để người báo biết đúng đoàn.
 */
const props = defineProps({
    trip: { type: Object, default: null },
    token: { type: String, default: '' },
});
const page = usePage();
const { t } = useT();
const { date } = useFormat();
const postUrl = computed(() => (page.url || '').split('?')[0]);
const backHref = computed(() => postUrl.value.replace(/\/bao-an-toan\/?$/, ''));
</script>

<template>
    <PageMeta :title="t('checkin.title')" fallback-key="checkin.intro">
        <meta head-key="robots" name="robots" content="noindex, nofollow" />
    </PageMeta>
    <div class="container-page max-w-2xl space-y-5 py-8 md:py-10">
        <header class="space-y-1">
            <p class="eyebrow">{{ t('shared.eyebrow') }}</p>
            <h1>{{ t('checkin.title') }}</h1>
            <p v-if="trip" class="text-muted">
                <strong class="text-fg">{{ trip.title }}</strong>
                <template v-if="trip.start_date"> · {{ date(trip.start_date) }} – {{ date(trip.end_date) }}</template>
            </p>
        </header>
        <CheckinPanel :url="postUrl" :title="t('shared.checkin_for_group')" />
        <section class="clay-card-sm space-y-2 p-4" aria-labelledby="sc-hot">
            <h2 id="sc-hot" class="text-base font-bold">{{ t('shared.emergency_first') }}</h2>
            <HotlineStrip />
        </section>
        <Button :href="backHref" variant="ghost" icon="arrow-left">{{ t('shared.back_to_trip') }}</Button>
    </div>
</template>
