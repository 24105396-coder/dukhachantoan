<script setup>
import { Link, useForm } from '@inertiajs/vue3';
import FormField from '@/Components/FormField.vue';
import Button from '@/Components/Button.vue';
import PageMeta from '@/Components/Public/PageMeta.vue';
import { useT } from '@/Composables/useT';
import { useLocaleRoute } from '@/Composables/useLocaleRoute';
import { useClientValidation } from '@/Composables/useClientValidation';
import { PASSWORD_PATTERN, PASSWORD_RE } from '@/Utils/rules';

/** Đặt lại mật khẩu từ liên kết email: props { token, email }. */
const props = defineProps({
    token: { type: String, required: true },
    email: { type: String, default: '' },
});
const { t } = useT();
const lr = useLocaleRoute();
const form = useForm({ token: props.token, email: props.email, password: '', password_confirmation: '' });
const { validate } = useClientValidation(form, {
    email: { label: 'common.email', required: true, email: true, max: 190 },
    password: { label: 'auth.password', required: true, min: 8, pattern: { regex: PASSWORD_RE, message: 'password' } },
    password_confirmation: { label: 'auth.password_confirmation', required: true, confirmed: 'password' },
});

const submit = (e) => {
    if (!validate(e)) return;
    form.post(lr('/dat-lai-mat-khau'), { onFinish: () => form.reset('password', 'password_confirmation') });
};
</script>

<template>
    <PageMeta :title="t('auth.reset_title')" fallback-key="auth.reset_intro" />
    <h1>{{ t('auth.reset_title') }}</h1>
    <p class="mt-1 text-sm text-muted">{{ t('auth.reset_intro') }}</p>
    <form class="mt-6 space-y-4" novalidate @submit.prevent="submit">
        <FormField v-model="form.email" :label="t('common.email')" type="email" required maxlength="190" autocomplete="email" :error="form.errors.email" />
        <FormField v-model="form.password" :label="t('auth.new_password')" type="password" required minlength="8" :pattern="PASSWORD_PATTERN" :pattern-hint="t('auth.password_hint')" autocomplete="new-password" :hint="t('auth.password_hint')" :error="form.errors.password" />
        <FormField v-model="form.password_confirmation" :label="t('auth.password_confirmation')" type="password" required minlength="8" autocomplete="new-password" :error="form.errors.password_confirmation" />
        <p v-if="form.errors.token" class="field-error" role="alert">{{ form.errors.token }}</p>
        <Button type="submit" variant="primary" block :loading="form.processing" icon="key">{{ t('auth.reset_submit') }}</Button>
    </form>
    <p class="mt-6 text-center text-sm">
        <Link :href="lr('/dang-nhap')" class="link">{{ t('auth.back_to_login') }}</Link>
    </p>
</template>
