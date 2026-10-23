<script setup>
import { Link, useForm, usePage } from '@inertiajs/vue3';
import FormField from '@/Components/FormField.vue';
import Button from '@/Components/Button.vue';
import PageMeta from '@/Components/Public/PageMeta.vue';
import { useT } from '@/Composables/useT';
import { useLocaleRoute } from '@/Composables/useLocaleRoute';
import { useClientValidation } from '@/Composables/useClientValidation';
import { PHONE_PATTERN, PASSWORD_PATTERN, PHONE_RE, PASSWORD_RE } from '@/Utils/rules';
import { nationalityOptions } from '@/Utils/countries';
import { LOCALES } from '@/Utils/options';

const page = usePage();
const { t, te } = useT();
const lr = useLocaleRoute();
const locale = page.props.locale || 'vi';

const form = useForm({
    name: '',
    email: '',
    phone: '',
    nationality: locale === 'vi' ? 'VN' : '',
    preferred_locale: locale,
    password: '',
    password_confirmation: '',
});

const { validate } = useClientValidation(form, {
    name: { label: 'common.name', required: true, min: 2, max: 120 },
    email: { label: 'common.email', required: true, email: true, max: 190 },
    phone: { label: 'common.phone', pattern: { regex: PHONE_RE, message: 'phone' } },
    nationality: { label: 'auth.nationality', required: true },
    preferred_locale: { label: 'auth.preferred_locale', required: true },
    password: { label: 'auth.password', required: true, min: 8, pattern: { regex: PASSWORD_RE, message: 'password' } },
    password_confirmation: { label: 'auth.password_confirmation', required: true, confirmed: 'password' },
});

const countries = nationalityOptions(locale);
const localeOptions = LOCALES.map((code) => ({ value: code, label: te('locale', code) }));

const submit = (e) => {
    if (!validate(e)) return;
    form.post(lr('/dang-ky'), { onFinish: () => form.reset('password', 'password_confirmation') });
};
</script>

<template>
    <PageMeta :title="t('auth.register_title')" fallback-key="auth.register_intro" />
    <h1>{{ t('auth.register_title') }}</h1>
    <p class="mt-1 text-sm text-muted">{{ t('auth.register_intro') }}</p>
    <form class="mt-6 grid gap-4 sm:grid-cols-2" novalidate @submit.prevent="submit">
        <FormField v-model="form.name" class="sm:col-span-2" :label="t('common.name')" required minlength="2" maxlength="120" autocomplete="name" :error="form.errors.name" />
        <FormField v-model="form.email" class="sm:col-span-2" :label="t('common.email')" type="email" required maxlength="190" autocomplete="email" :error="form.errors.email" />
        <FormField
            v-model="form.phone"
            class="sm:col-span-2"
            :label="`${t('common.phone')} (${t('common.optional')})`"
            type="tel"
            :pattern="PHONE_PATTERN"
            :pattern-hint="t('validation_hint.phone')"
            :hint="t('auth.phone_hint')"
            maxlength="16"
            autocomplete="tel"
            :error="form.errors.phone"
        />
        <FormField v-model="form.nationality" :label="t('auth.nationality')" type="select" required :options="countries" :placeholder="t('auth.nationality_placeholder')" :error="form.errors.nationality" />
        <FormField v-model="form.preferred_locale" :label="t('auth.preferred_locale')" type="select" required :options="localeOptions" :error="form.errors.preferred_locale" />
        <FormField v-model="form.password" class="sm:col-span-2" :label="t('auth.password')" type="password" required minlength="8" :pattern="PASSWORD_PATTERN" :pattern-hint="t('auth.password_hint')" autocomplete="new-password" :hint="t('auth.password_hint')" :error="form.errors.password" />
        <FormField v-model="form.password_confirmation" class="sm:col-span-2" :label="t('auth.password_confirmation')" type="password" required minlength="8" autocomplete="new-password" :error="form.errors.password_confirmation" />
        <Button type="submit" variant="primary" block class="sm:col-span-2" :loading="form.processing" icon="user-plus">
            {{ form.processing ? t('common.loading') : t('auth.create_account') }}
        </Button>
    </form>
    <p class="mt-6 text-center text-sm text-muted">
        {{ t('auth.have_account') }}
        <Link :href="lr('/dang-nhap')" class="link">{{ t('nav.login') }}</Link>
    </p>
</template>
