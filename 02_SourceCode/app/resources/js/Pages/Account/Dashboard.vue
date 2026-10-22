<script setup>
import { computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import PageHeader from '@/Components/PageHeader.vue';
import EmptyState from '@/Components/EmptyState.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import CoverImage from '@/Components/CoverImage.vue';
import RouteCard from '@/Components/RouteCard.vue';
import Icon from '@/Components/Icon.vue';
import { useT } from '@/Composables/useT';
import { useFormat } from '@/Composables/useFormat';
import { useLocaleRoute } from '@/Composables/useLocaleRoute';
import { usePermissions } from '@/Composables/usePermissions';

const props = defineProps({
    bookings: { type: Array, default: () => [] },
    contributions: { type: Array, default: () => [] },
    favorites: { type: Array, default: () => [] },
    ownRoutes: { type: Array, default: () => [] },
});
const { t, te } = useT();
const { money, dateTime, date } = useFormat();
const lr = useLocaleRoute();
const { user, canAccessAdmin, adminHome } = usePermissions();

const FAVORITE_ICON = { site: 'landmark', artifact: 'vase', figure: 'user' };
const activeBookings = computed(() => props.bookings.filter((b) => ['held', 'pending_payment'].includes(b.status)).length);
</script>

<template>
    <Head :title="t('account.dashboard_title')" />
    <div class="container-page py-8">
        <PageHeader :eyebrow="t('nav.dashboard')" :title="t('account.greeting', { name: user?.name || '' })" :description="t('account.dashboard_intro')">
            <template #actions>
                <Link :href="lr('/tai-khoan/ho-so')" class="btn"><Icon name="user" :size="18" />{{ t('nav.profile') }}</Link>
                <Link :href="lr('/dong-gop/moi')" class="btn btn-primary"><Icon name="upload" :size="18" />{{ t('nav.contribute') }}</Link>
                <Link v-if="canAccessAdmin" :href="lr(adminHome)" class="btn"><Icon name="shield" :size="18" />{{ t('nav.admin') }}</Link>
            </template>
        </PageHeader>

        <p v-if="activeBookings" class="mb-6 rounded-lg bg-warning-soft p-3 text-sm text-warning" role="status">
            {{ t('account.pending_bookings', { n: activeBookings }) }}
        </p>

        <div class="grid gap-8 lg:grid-cols-2">
            <section class="min-w-0">
                <h2 class="section-title mb-4">{{ t('account.my_bookings') }}</h2>
                <EmptyState v-if="!bookings.length" icon="ticket" :title="t('account.no_bookings')">
                    <Link :href="lr('/tuyen-tham-quan')" class="btn btn-primary">{{ t('account.browse_tours') }}</Link>
                </EmptyState>
                <ul v-else class="space-y-3">
                    <li v-for="b in bookings" :key="b.id">
                        <Link :href="lr(`/dat-lich/${b.code}`)" class="card card-hover flex flex-wrap items-center gap-3 p-4">
                            <div class="min-w-0 flex-1">
                                <p class="font-semibold">{{ b.slot?.route?.name }}</p>
                                <p class="text-sm text-muted">{{ dateTime(b.slot?.starts_at) }} · {{ t('booking.people', { n: b.quantity }) }}</p>
                                <p class="text-xs text-muted">{{ b.code }}</p>
                            </div>
                            <div class="flex flex-col items-end gap-1">
                                <StatusBadge :status="b.status" />
                                <span class="font-semibold text-primary">{{ money(b.total_vnd) }}</span>
                            </div>
                        </Link>
                    </li>
                </ul>
            </section>

            <section class="min-w-0">
                <h2 class="section-title mb-4">{{ t('account.my_contributions') }}</h2>
                <EmptyState v-if="!contributions.length" icon="inbox" :title="t('account.no_contributions')">
                    <Link :href="lr('/dong-gop/moi')" class="btn btn-primary">{{ t('nav.contribute') }}</Link>
                </EmptyState>
                <ul v-else class="space-y-3">
                    <li v-for="c in contributions" :key="c.id">
                        <Link :href="lr(`/dong-gop/${c.id}`)" class="card card-hover flex flex-wrap items-center gap-3 p-4">
                            <div class="min-w-0 flex-1">
                                <p class="truncate font-semibold">{{ c.title }}</p>
                                <p class="text-sm text-muted">{{ te('contribution_type', c.contribution_type) }} · {{ date(c.created_at) }}</p>
                            </div>
                            <StatusBadge :status="c.status" />
                        </Link>
                    </li>
                </ul>
            </section>
        </div>

        <section class="mt-10">
            <h2 class="section-title mb-4">{{ t('account.favorites') }}</h2>
            <EmptyState v-if="!favorites.length" icon="heart" :title="t('account.no_favorites')" :description="t('account.no_favorites_hint')" />
            <ul v-else class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4">
                <li v-for="f in favorites" :key="`${f.type}-${f.slug}`">
                    <Link :href="f.url" class="card card-hover block overflow-hidden">
                        <CoverImage :src="typeof f.cover === 'string' ? f.cover : f.cover?.url" :alt="f.title" :icon="FAVORITE_ICON[f.type] || 'landmark'" />
                        <div class="p-3">
                            <p class="eyebrow">{{ te('subject_type', f.type) }}</p>
                            <p class="line-clamp-2 text-sm font-semibold">{{ f.title }}</p>
                        </div>
                    </Link>
                </li>
            </ul>
        </section>

        <section v-if="ownRoutes.length || user?.role === 'contributor'" class="mt-10">
            <div class="mb-4 flex flex-wrap items-center justify-between gap-2">
                <h2 class="section-title">{{ t('account.own_routes') }}</h2>
                <Link :href="lr('/quan-tri/tuyen-tham-quan')" class="btn btn-sm">{{ t('account.manage_routes') }}</Link>
            </div>
            <EmptyState v-if="!ownRoutes.length" icon="route" :title="t('account.no_own_routes')" />
            <ul v-else class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                <li v-for="r in ownRoutes" :key="r.id"><RouteCard :route="r" /></li>
            </ul>
        </section>
    </div>
</template>
