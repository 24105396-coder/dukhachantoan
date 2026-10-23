<script setup>
import { computed, reactive } from 'vue';
import PageMeta from '@/Components/Public/PageMeta.vue';
import PageHeader from '@/Components/PageHeader.vue';
import Badge from '@/Components/Badge.vue';
import Button from '@/Components/Button.vue';
import Icon from '@/Components/Icon.vue';
import AlertCard from '@/Components/AlertCard.vue';
import ContactItem from '@/Components/ContactItem.vue';
import EmptyState from '@/Components/EmptyState.vue';
import { useT } from '@/Composables/useT';
import { useFormat } from '@/Composables/useFormat';
import { useLocaleRoute } from '@/Composables/useLocaleRoute';

/** Hướng dẫn ứng phó (GuideDetail): 3 giai đoạn trước/trong/sau dạng checklist tự đánh dấu, nguồn, danh bạ, cảnh báo liên quan, nút in. */
const props = defineProps({
    guide: { type: Object, required: true },
    contacts: { type: Array, default: () => [] },
    relatedAlerts: { type: Array, default: () => [] },
});
const { t, te } = useT();
const { date } = useFormat();
const lr = useLocaleRoute();

const PHASES = [
    { key: 'before', icon: 'clipboard-check', tone: 'callout-1' },
    { key: 'during', icon: 'siren', tone: 'callout-3' },
    { key: 'after', icon: 'shield-check', tone: 'callout-0' },
];
const steps = (key) => props.guide[`${key}_steps`] || [];
// Đánh dấu chỉ lưu trong phiên xem (checklist cá nhân, không gửi máy chủ)
const done = reactive({});
const doneCount = (key) => steps(key).filter((_, i) => done[`${key}-${i}`]).length;
const isSafeUrl = (s) => /^https?:\/\//i.test(s);
const crumbs = computed(() => [
    { label: t('nav.home'), href: lr('/') },
    { label: t('nav.guides'), href: lr('/huong-dan-ung-pho') },
    { label: props.guide.title },
]);
const printPage = () => window.print();
</script>

<template>
    <PageMeta :title="guide.title" :description="guide.summary" />
    <div class="container-page space-y-6 py-8 md:py-10">
        <PageHeader :breadcrumbs="crumbs" :eyebrow="guide.hazard?.name || t('nav.guides')" :title="guide.title" :description="guide.summary">
            <template #actions>
                <Button icon="printer" class="print:hidden" @click="printPage">{{ t('guides.print') }}</Button>
            </template>
        </PageHeader>
        <div class="flex flex-wrap gap-2">
            <Badge v-if="guide.min_severity" :level="guide.min_severity" soft :label="t('guide.from_level', { label: t(`enums.levels.${guide.min_severity}`) })" />
            <span v-if="guide.audience" class="pill"><Icon name="users" :size="14" />{{ te('audience', guide.audience) }}</span>
            <span v-if="guide.updated_at" class="pill">{{ t('guides.updated', { date: date(guide.updated_at) }) }}</span>
        </div>
        <p v-if="guide.is_machine_translated" class="callout callout-2 p-3 text-sm" role="note">{{ t('common.machine_translated_notice') }}</p>

        <div class="grid gap-4 lg:grid-cols-3">
            <section v-for="p in PHASES" :key="p.key" class="callout p-4 md:p-5 print:break-inside-avoid" :class="p.tone" :aria-labelledby="`ph-${p.key}`">
                <div class="mb-3 flex items-center gap-3">
                    <span class="icon-tile"><Icon :name="p.icon" :size="22" /></span>
                    <div>
                        <h2 :id="`ph-${p.key}`" class="font-display text-xl font-bold">{{ t(`guides.phase.${p.key}`) }}</h2>
                        <p v-if="steps(p.key).length" class="text-xs text-muted" aria-live="polite">{{ t('guides.progress', { done: doneCount(p.key), total: steps(p.key).length }) }}</p>
                    </div>
                </div>
                <ul v-if="steps(p.key).length" class="space-y-2">
                    <li v-for="(s, i) in steps(p.key)" :key="i">
                        <label class="flex cursor-pointer items-start gap-3 rounded-xl bg-surface p-2.5 text-sm">
                            <input v-model="done[`${p.key}-${i}`]" type="checkbox" class="clay-checkbox mt-0.5" />
                            <span :class="done[`${p.key}-${i}`] ? 'text-muted line-through' : ''">{{ s }}</span>
                        </label>
                    </li>
                </ul>
                <p v-else class="text-sm text-muted">{{ t('common.no_data') }}</p>
            </section>
        </div>

        <div class="grid gap-5 lg:grid-cols-[1.4fr_1fr]">
            <section class="space-y-3 print:hidden" aria-labelledby="g-alerts">
                <h2 id="g-alerts" class="section-title text-xl">{{ t('guides.related_alerts') }}</h2>
                <ul v-if="relatedAlerts.length" class="grid gap-4 md:grid-cols-2">
                    <li v-for="a in relatedAlerts" :key="a.code"><AlertCard :alert="a" compact /></li>
                </ul>
                <EmptyState v-else :title="t('guides.no_related_alerts')" compact />
            </section>
            <section class="space-y-3" aria-labelledby="g-contacts">
                <h2 id="g-contacts" class="section-title text-xl">{{ t('nav.contacts') }}</h2>
                <p v-if="guide.related_contacts_hint" class="text-sm text-muted">{{ guide.related_contacts_hint }}</p>
                <ul v-if="contacts.length" class="grid gap-3">
                    <li v-for="c in contacts" :key="c.id"><ContactItem :contact="c" compact /></li>
                </ul>
                <EmptyState v-else :title="t('contacts.empty')" compact />
            </section>
        </div>

        <section v-if="guide.sources?.length" class="clay-card-sm p-5" aria-labelledby="g-sources">
            <h2 id="g-sources" class="section-title text-lg">{{ t('common.sources') }}</h2>
            <ul class="mt-3 list-disc space-y-1 pl-5 text-sm">
                <li v-for="(s, i) in guide.sources" :key="i" class="break-words">
                    <a v-if="isSafeUrl(s)" :href="s" target="_blank" rel="noopener noreferrer" class="link">{{ s }}</a>
                    <span v-else>{{ s }}</span>
                </li>
            </ul>
        </section>
    </div>
</template>
