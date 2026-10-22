<script setup>
import { PHONE_PATTERN, PASSWORD_PATTERN, PHONE_RE, PASSWORD_RE } from '@/Utils/rules';
import { useClientValidation } from '@/Composables/useClientValidation';
import { Link, useForm } from '@inertiajs/vue3';
import FormField from '@/Components/FormField.vue';
import { useT } from '@/Composables/useT';
import { useLocaleRoute } from '@/Composables/useLocaleRoute';
import PageMeta from '@/Components/Public/PageMeta.vue';

const { t } = useT();
const lr = useLocaleRoute();
const form = useForm({ name: '', email: '', phone: '', password: '', password_confirmation: '' });

const { validate } = useClientValidation(form, {
    name: { label: 'common.name', required: true, min: 2, max: 120 },
    email: { label: 'common.email', required: true, email: true, max: 190 },
    phone: { label: 'common.phone', pattern: { regex: PHONE_RE, message: 'phone' } },
    password: { label: 'auth.password', required: true, min: 8, pattern: { regex: PASSWORD_RE, message: 'password' } },
    password_confirmation: { label: 'auth.password_confirmation', required: true, confirmed: 'password' },
});

const submit = (e) => {
    if (!validate(e)) return;
    form.post(lr('/dang-ky'), { onFinish: () => form.reset('password', 'password_confirmation') });
};
</script>

<template>
    <PageMeta :title="t('auth.register_title')" fallback-key="auth.register_intro" />
    <div class="card p-6 md:p-8">
        <h1 class="text-2xl font-bold">{{ t('auth.register_title') }}</h1>
        <p class="mt-1 text-sm text-muted">{{ t('auth.register_intro') }}</p>
        <form class="mt-6 space-y-4" novalidate @submit.prevent="submit">
            <FormField v-model="form.name" :label="t('common.name')" required minlength="2" maxlength="120" autocomplete="name" :error="form.errors.name" />
            <FormField v-model="form.email" :label="t('common.email')" type="email" required maxlength="190" autocomplete="email" :error="form.errors.email" />
            <FormField v-model="form.phone" :label="`${t('common.phone')} (${t('common.optional')})`" type="tel" :pattern="PHONE_PATTERN" :pattern-hint="t('validation_hint.phone')" maxlength="16" autocomplete="tel" :error="form.errors.phone" />
            <FormField v-model="form.password" :label="t('auth.password')" type="password" required minlength="8" :pattern="PASSWORD_PATTERN" :pattern-hint="t('auth.password_hint')" autocomplete="new-password" :hint="t('auth.password_hint')" :error="form.errors.password" />
            <FormField v-model="form.password_confirmation" :label="t('auth.password_confirmation')" type="password" required minlength="8" autocomplete="new-password" :error="form.errors.password_confirmation" />
            <button type="submit" class="btn btn-primary w-full" :disabled="form.processing">
                {{ form.processing ? t('common.loading') : t('auth.create_account') }}
            </button>
        </form>
        <p class="mt-6 text-center text-sm text-muted">
            {{ t('auth.have_account') }}
            <Link :href="lr('/dang-nhap')" class="link font-semibold">{{ t('nav.login') }}</Link>
        </p>
    </div>
</template>
