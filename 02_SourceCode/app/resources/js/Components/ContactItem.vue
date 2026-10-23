<script setup>
import { computed } from 'vue';
import Icon from '@/Components/Icon.vue';
import Badge from '@/Components/Badge.vue';
import { useT } from '@/Composables/useT';
import { useFormat } from '@/Composables/useFormat';

/**
 * Một đầu mối khẩn cấp theo Resource ContactItem: nút gọi `tel:` lớn, giờ làm việc, ngôn ngữ,
 * nhãn "Chưa xác minh" khi is_verified=false, nguồn và ngày xác minh. `compact` dùng trong cột bên.
 */
const props = defineProps({
    contact: { type: Object, required: true },
    compact: { type: Boolean, default: false },
});
const { t, te } = useT();
const { date } = useFormat();

// Chỉ giữ chữ số và dấu + cho href tel:
const telHref = (phone) => `tel:${String(phone || '').replace(/[^\d+]/g, '')}`;
const typeLabel = computed(() => props.contact.type_label || te('contact_type', props.contact.contact_type));
const languages = computed(() => (props.contact.languages || []).map((l) => te('locale', l) || l).join(', '));
const sourceUrl = computed(() => {
    const url = props.contact.source?.url;
    return url && /^https?:\/\//i.test(url) ? url : null;
});
</script>

<template>
    <article class="clay-card-sm flex h-full flex-col p-4" :class="compact ? 'gap-2' : 'gap-2.5'" :data-verified="contact.is_verified ? 'true' : 'false'">
        <div class="flex flex-wrap items-center gap-2">
            <span class="eyebrow">{{ typeLabel }}</span>
            <Badge v-if="!contact.is_verified" tone="outline" size="sm" icon="circle-alert">{{ t('contact.unverified') }}</Badge>
        </div>
        <div :class="compact ? 'flex flex-wrap items-center justify-between gap-2' : 'contents'">
            <h3 class="font-display text-[0.9375rem] font-bold leading-snug md:text-base" :class="compact ? 'min-w-0 flex-1' : ''">{{ contact.name }}</h3>
            <p v-if="contact.note && !compact" class="text-sm text-muted">{{ contact.note }}</p>

            <div class="flex flex-wrap gap-2">
                <a
                    v-if="contact.phone"
                    :href="telHref(contact.phone)"
                    class="clay-btn clay-btn-primary"
                    :class="compact ? 'clay-btn-sm' : ''"
                    :aria-label="t('contact.call', { phone: contact.phone, name: contact.name })"
                >
                    <Icon name="phone" :size="compact ? 16 : 18" /><span class="font-bold tabular-nums" :class="compact ? 'text-sm' : 'text-base'">{{ contact.phone }}</span>
                </a>
                <a
                    v-if="contact.phone_alt"
                    :href="telHref(contact.phone_alt)"
                    class="clay-btn"
                    :class="compact ? 'clay-btn-sm' : ''"
                    :aria-label="t('contact.call', { phone: contact.phone_alt, name: contact.name })"
                >
                    <Icon name="phone" :size="16" />{{ contact.phone_alt }}
                </a>
            </div>
        </div>

        <dl v-if="!compact" class="grid gap-1 text-sm">
            <div v-if="contact.hours" class="flex gap-2"><dt class="text-muted"><Icon name="clock" :size="16" /><span class="sr-only">{{ t('contact.hours') }}</span></dt><dd>{{ contact.hours }}</dd></div>
            <div v-if="languages" class="flex gap-2"><dt class="text-muted"><Icon name="languages" :size="16" /><span class="sr-only">{{ t('contact.languages') }}</span></dt><dd>{{ languages }}</dd></div>
            <div v-if="contact.address" class="flex gap-2"><dt class="text-muted"><Icon name="map-pin" :size="16" /><span class="sr-only">{{ t('contact.address') }}</span></dt><dd>{{ contact.address }}</dd></div>
        </dl>

        <p v-if="contact.source?.name || contact.verified_at" class="mt-auto border-t border-line pt-2 text-xs text-muted" :class="compact ? 'line-clamp-1' : ''">
            <template v-if="contact.source?.name">
                {{ t('common.source') }}:
                <a v-if="sourceUrl" :href="sourceUrl" target="_blank" rel="noopener noreferrer" class="link">{{ contact.source.name }}</a>
                <span v-else class="font-semibold text-fg">{{ contact.source.name }}</span>
            </template>
            <template v-if="contact.is_verified && contact.verified_at"> · {{ t('contact.verified_on', { date: date(contact.verified_at) }) }}</template>
        </p>
    </article>
</template>
