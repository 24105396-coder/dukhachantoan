<script setup>
import { computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import PageHeader from '@/Components/PageHeader.vue';
import FilterBar from '@/Components/FilterBar.vue';
import Pagination from '@/Components/Pagination.vue';
import ProvenanceBadge from '@/Components/ProvenanceBadge.vue';
import EmptyState from '@/Components/EmptyState.vue';
import Icon from '@/Components/Icon.vue';
import DeleteAction from '@/Components/Admin/DeleteAction.vue';
import { useT } from '@/Composables/useT';
import { useLocaleRoute } from '@/Composables/useLocaleRoute';
import { rowsOf } from '@/Utils/options';

const props = defineProps({
    media: { type: Object, default: () => ({ data: [] }) },
    filters: { type: Object, default: () => ({}) },
});
const { t, te } = useT();
const lr = useLocaleRoute();

const MEDIA_TYPES = ['image', 'audio', 'video', 'model3d', 'document'];
const LICENSES = ['public_domain', 'cc_by', 'cc_by_sa', 'cc_by_nc', 'all_rights_reserved', 'simulated'];
const items = computed(() => rowsOf(props.media));
const fields = computed(() => [
    { name: 'q', type: 'search', label: t('common.keyword') },
    { name: 'type', type: 'select', label: t('admin.media.media_type'), options: MEDIA_TYPES.map((c) => ({ value: c, label: te('media_type', c) })) },
    { name: 'license', type: 'select', label: t('provenance.license'), options: LICENSES.map((c) => ({ value: c, label: te('license', c) })) },
]);
const thumbIcon = (m) => ({ audio: 'volume', video: 'play', model3d: 'cube', document: 'file' })[m.media_type] || 'image';
</script>

<template>
    <Head :title="t('admin.media.title')" />
    <PageHeader :title="t('admin.media.title')" :description="t('admin.media.description')" />
    <FilterBar :filters="filters" :fields="fields" class="mb-4" />

    <EmptyState v-if="!items.length" icon="image" :title="t('admin.media.empty')" />
    <ul v-else class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 2xl:grid-cols-4">
        <li v-for="m in items" :key="m.id" class="card flex flex-col overflow-hidden">
            <div class="relative aspect-video bg-surface-2">
                <img v-if="m.media_type === 'image' && m.url" :src="m.url" :alt="m.caption || ''" loading="lazy" class="h-full w-full object-cover" />
                <div v-else class="flex h-full flex-col items-center justify-center gap-2 text-accent-strong">
                    <Icon :name="thumbIcon(m)" :size="36" />
                    <audio v-if="m.media_type === 'audio' && m.url" :src="m.url" controls preload="none" class="w-11/12" :aria-label="m.caption || te('media_type', 'audio')"></audio>
                    <span v-else-if="m.media_type === 'audio'" class="text-xs">{{ t('admin.media.tts_only') }}</span>
                </div>
                <span class="badge absolute left-2 top-2">{{ te('media_type', m.media_type) }}<template v-if="m.locale"> · {{ m.locale }}</template></span>
                <span v-if="m.is_cover" class="badge badge-accent absolute right-2 top-2">{{ t('admin.media.cover') }}</span>
            </div>
            <div class="flex flex-1 flex-col gap-2 p-3">
                <div class="flex items-start justify-between gap-2">
                    <p class="line-clamp-2 text-sm font-medium">{{ m.caption || t('admin.media.no_caption') }}</p>
                    <div class="flex shrink-0 items-center gap-1">
                        <Link v-if="m.subject?.edit_url" :href="m.subject.edit_url" class="btn btn-ghost btn-sm" :title="t('admin.media.open_subject')">
                            <Icon name="edit" :size="16" />{{ t('common.edit') }}
                        </Link>
                        <DeleteAction :url="lr(`/quan-tri/media/${m.id}`)" :name="m.caption || `#${m.id}`" />
                    </div>
                </div>
                <p v-if="m.subject" class="text-xs text-muted">
                    {{ te('subject_type', m.subject.type) }}:
                    <a v-if="m.subject.url" :href="m.subject.url" class="link" target="_blank" rel="noopener">{{ m.subject.title }}</a>
                    <span v-else class="text-text">{{ m.subject.title }}</span>
                </p>
                <ProvenanceBadge :item="m" class="mt-auto" />
            </div>
        </li>
    </ul>
    <Pagination :paginator="media" />
</template>
