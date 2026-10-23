<script setup>
import { computed } from 'vue';
import PageMeta from '@/Components/Public/PageMeta.vue';
import PageHeader from '@/Components/PageHeader.vue';
import FilterBar from '@/Components/FilterBar.vue';
import ContactItem from '@/Components/ContactItem.vue';
import EmptyState from '@/Components/EmptyState.vue';
import SkeletonList from '@/Components/SkeletonList.vue';
import Icon from '@/Components/Icon.vue';
import { useT } from '@/Composables/useT';
import { useLocaleRoute } from '@/Composables/useLocaleRoute';
import { useVisitLoading } from '@/Composables/useVisitLoading';
import { enumOptions } from '@/Utils/options';

/**
 * Danh bạ khẩn cấp: số toàn quốc ghim trên cùng, rồi theo tỉnh → nhóm theo loại, mỗi mục có nút gọi tel:.
 * `contacts` chấp nhận: [ContactItem] phẳng, [{ province, contacts }] hoặc { mã tỉnh: [ContactItem] }.
 */
const props = defineProps({
    contacts: { type: [Array, Object], default: () => [] },
    national: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({}) },
    provinces: { type: Array, default: () => [] },
    types: { type: Array, default: () => [] },
});
const { t, te } = useT();
const lr = useLocaleRoute();
const { loading } = useVisitLoading();

function provinceGroups(raw) {
    if (!raw) return [];
    if (!Array.isArray(raw)) {
        return Object.entries(raw).map(([code, list]) => ({ province: list?.[0]?.province || { code, name: code }, contacts: list || [] }));
    }
    if (raw.length && Array.isArray(raw[0]?.contacts)) return raw;
    const map = new Map();
    raw.forEach((c) => {
        const code = c.province?.code || '_';
        if (!map.has(code)) map.set(code, { province: c.province || { code, name: '' }, contacts: [] });
        map.get(code).contacts.push(c);
    });
    return [...map.values()];
}
function byType(list) {
    const map = new Map();
    list.forEach((c) => {
        if (!map.has(c.contact_type)) map.set(c.contact_type, { type: c.contact_type, label: c.type_label || te('contact_type', c.contact_type), items: [] });
        map.get(c.contact_type).items.push(c);
    });
    return [...map.values()];
}
const groups = computed(() => provinceGroups(props.contacts).map((g) => ({ ...g, types: byType(g.contacts) })));
const fields = computed(() => [
    { name: 'province', type: 'select', label: t('common.province'), options: enumOptions(props.provinces) },
    { name: 'type', type: 'select', label: t('contacts.type'), options: enumOptions(props.types, te, 'contact_type') },
]);
</script>

<template>
    <PageMeta :title="t('nav.contacts')" fallback-key="contacts.intro" />
    <div class="container-page space-y-6 py-8 md:py-10">
        <PageHeader :breadcrumbs="[{ label: t('nav.home'), href: lr('/') }, { label: t('nav.contacts') }]" :eyebrow="t('contacts.eyebrow')" :title="t('nav.contacts')" :description="t('contacts.intro')" />

        <section class="callout callout-4 p-4 md:p-5" aria-labelledby="ct-national">
            <h2 id="ct-national" class="section-title text-xl"><Icon name="siren" :size="22" />{{ t('contacts.national') }}</h2>
            <p class="mt-1 text-sm">{{ t('contacts.national_hint') }}</p>
            <ul v-if="national.length" class="mt-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                <li v-for="c in national" :key="c.id"><ContactItem :contact="c" compact /></li>
            </ul>
            <EmptyState v-else :title="t('contacts.empty')" compact class="mt-4" />
        </section>

        <FilterBar :filters="filters" :fields="fields" />

        <SkeletonList v-if="loading" :count="4" :cols="2" height="h-20" />
        <template v-else-if="groups.length">
            <section v-for="g in groups" :key="g.province?.code" class="space-y-4" :aria-labelledby="`prov-${g.province?.code}`">
                <h2 :id="`prov-${g.province?.code}`" class="section-title text-xl">{{ g.province?.name || t('contacts.other') }}</h2>
                <div v-for="ty in g.types" :key="ty.type" class="space-y-2">
                    <h3 class="eyebrow">{{ ty.label }}</h3>
                    <ul class="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                        <li v-for="c in ty.items" :key="c.id"><ContactItem :contact="c" /></li>
                    </ul>
                </div>
            </section>
        </template>
        <EmptyState v-else :title="t('contacts.empty')" :description="t('contacts.empty_hint')" />
        <p class="text-xs text-muted">{{ t('contacts.disclaimer') }}</p>
    </div>
</template>
