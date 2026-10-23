<script setup>
import { Link, useForm } from '@inertiajs/vue3';
import FormField from '@/Components/FormField.vue';
import Button from '@/Components/Button.vue';
import PageMeta from '@/Components/Public/PageMeta.vue';
import { useT } from '@/Composables/useT';
import { useLocaleRoute } from '@/Composables/useLocaleRoute';
import { useClientValidation } from '@/Composables/useClientValidation';

/** Quên mật khẩu: gửi liên kết đặt lại qua email (MAIL_MAILER=log trong môi trường đào tạo). */
defineProps({
    status: { type: String, default: null },
});
const { t } = useT();
const lr = useLocaleRoute();
const form = useForm({ email: '' });
const { validate } = useClientValidation(form, {
    email: { label: 'common.email', required: true, email: true, max: 190 },
});

const submit = (e) => {
    if (!validate(e)) return;
    form.post(lr('/quen-mat-khau'), { preserveScroll: true });
};
</script>

<template>
    <PageMeta :title="t('auth.forgot_title')" fallback-key="auth.forgot_intro" />
    <h1>{{ t('auth.forgot_title') }}</h1>
    <p class="mt-1 text-sm text-muted">{{ t('auth.forgot_intro') }}</p>
    <p v-if="status" class="mt-4 callout callout-0 p-3 text-sm font-semibold" role="status">{{ status }}</p>
    <form class="mt-6 space-y-4" novalidate @submit.prevent="submit">
        <FormField v-model="form.email" :label="t('common.email')" type="email" required maxlength="190" autocomplete="email" :error="form.errors.email" />
        <Button type="submit" variant="primary" block :loading="form.processing" icon="mail">{{ t('auth.forgot_submit') }}</Button>
    </form>
    <p class="mt-6 text-center text-sm">
        <Link :href="lr('/dang-nhap')" class="link">{{ t('auth.back_to_login') }}</Link>
    </p>
</template>
