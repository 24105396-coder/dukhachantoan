<script setup>
import { Link, useForm } from '@inertiajs/vue3';
import FormField from '@/Components/FormField.vue';
import Checkbox from '@/Components/Form/Checkbox.vue';
import Button from '@/Components/Button.vue';
import PageMeta from '@/Components/Public/PageMeta.vue';
import { useT } from '@/Composables/useT';
import { useLocaleRoute } from '@/Composables/useLocaleRoute';
import { useClientValidation } from '@/Composables/useClientValidation';

defineProps({
    status: { type: String, default: null },
});
const { t } = useT();
const lr = useLocaleRoute();

const DEMO_ACCOUNTS = ['admin@dukhach.test', 'agency@dukhach.test', 'partner@dukhach.test', 'traveler@dukhach.test'];
const form = useForm({ email: '', password: '', remember: false });

const { validate } = useClientValidation(form, {
    email: { label: 'common.email', required: true, email: true, max: 190 },
    password: { label: 'auth.password', required: true, max: 255 },
});

const submit = (e) => {
    if (!validate(e)) return;
    form.post(lr('/dang-nhap'), { onFinish: () => form.reset('password') });
};
</script>

<template>
    <PageMeta :title="t('auth.login_title')" fallback-key="auth.login_intro" />
    <h1 class="text-3xl">{{ t('auth.login_title') }}</h1>
    <p class="mt-1 text-sm text-muted">{{ t('auth.login_intro') }}</p>
    <p v-if="status" class="mt-4 rounded-2xl bg-level-0-soft p-3 text-sm font-semibold" role="status">{{ status }}</p>

    <form class="mt-6 space-y-4" novalidate @submit.prevent="submit">
        <FormField v-model="form.email" :label="t('common.email')" type="email" required maxlength="190" autocomplete="username" :error="form.errors.email" />
        <FormField v-model="form.password" :label="t('auth.password')" type="password" required maxlength="255" autocomplete="current-password" :error="form.errors.password" />
        <div class="flex flex-wrap items-center justify-between gap-2">
            <Checkbox v-model="form.remember" :label="t('auth.remember')" />
            <Link :href="lr('/quen-mat-khau')" class="link text-sm">{{ t('auth.forgot_link') }}</Link>
        </div>
        <Button type="submit" variant="primary" block :loading="form.processing" icon="login">
            {{ form.processing ? t('common.loading') : t('nav.login') }}
        </Button>
    </form>

    <p class="mt-6 text-center text-sm text-muted">
        {{ t('auth.no_account') }}
        <Link :href="lr('/dang-ky')" class="link">{{ t('nav.register') }}</Link>
    </p>

    <details class="mt-6 rounded-2xl bg-soft p-4 text-xs">
        <summary class="cursor-pointer font-bold">{{ t('auth.demo_accounts') }}</summary>
        <ul class="mt-2 space-y-1">
            <li v-for="email in DEMO_ACCOUNTS" :key="email">
                <button type="button" class="link" @click="form.email = email">{{ email }}</button>
            </li>
        </ul>
        <p class="mt-2 text-muted">{{ t('auth.demo_password_hint') }}</p>
    </details>
</template>
