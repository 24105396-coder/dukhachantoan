<script setup>
import { useForm, usePage, Link } from '@inertiajs/vue3';
import Icon from '@/Components/Icon.vue';
import { useT } from '@/Composables/useT';
import { useLocaleRoute } from '@/Composables/useLocaleRoute';

const props = defineProps({
    type: { type: String, required: true }, // site | artifact | figure
    id: { type: Number, required: true },
    active: { type: Boolean, default: false },
});
const page = usePage();
const { t } = useT();
const lr = useLocaleRoute();
const form = useForm({ type: props.type, id: props.id });

const toggle = () => form.post(lr('/yeu-thich'), { preserveScroll: true });
</script>

<template>
    <button
        v-if="page.props.auth?.user"
        type="button"
        class="btn"
        :class="active ? 'border-primary text-primary' : ''"
        :aria-pressed="active"
        :disabled="form.processing"
        @click="toggle"
    >
        <Icon name="heart" :size="18" :class="active ? 'fill-current' : ''" />
        {{ active ? t('common.favorited') : t('common.favorite') }}
    </button>
    <Link v-else :href="lr('/dang-nhap')" class="btn"><Icon name="heart" :size="18" />{{ t('common.favorite') }}</Link>
</template>
