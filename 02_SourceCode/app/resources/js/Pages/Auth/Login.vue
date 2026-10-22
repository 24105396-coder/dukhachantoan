<script setup>
import { Link, useForm } from '@inertiajs/vue3';
import FormField from '@/Components/FormField.vue';
import { useT } from '@/Composables/useT';
import { useLocaleRoute } from '@/Composables/useLocaleRoute';
import { useClientValidation } from '@/Composables/useClientValidation';
import PageMeta from '@/Components/Public/PageMeta.vue';

defineProps({
    status: { type: String, default: null },
});
const { t } = useT();
const lr = useLocaleRoute();

const DEMO_ACCOUNTS = ['admin@disanso.test', 'manager@disanso.test', 'partner@disanso.test', 'user@disanso.test'];
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
    <div class="card p-6 md:p-8">
        <h1 class="text-2xl font-bold">{{ t('auth.login_title') }}</h1>
        <p class="mt-1 text-sm text-muted">{{ t('auth.login_intro') }}</p>
        <p v-if="status" class="mt-4 rounded-lg bg-success-soft p-3 text-sm text-success" role="status">{{ status }}</p>

        <form class="mt-6 space-y-4" novalidate @submit.prevent="submit">
            <FormField v-model="form.email" :label="t('common.email')" type="email" required maxlength="190" autocomplete="username" :error="form.errors.email" />
            <FormField v-model="form.password" :label="t('auth.password')" type="password" required maxlength="255" autocomplete="current-password" :error="form.errors.password" />
            <label class="flex items-center gap-2 text-sm">
                <input v-model="form.remember" type="checkbox" class="h-4 w-4 accent-[var(--ds-primary)]" />
                {{ t('auth.remember') }}
            </label>
            <button type="submit" class="btn btn-primary w-full" :disabled="form.processing">
                {{ form.processing ? t('common.loading') : t('nav.login') }}
            </button>
        </form>

        <p class="mt-6 text-center text-sm text-muted">
            {{ t('auth.no_account') }}
            <Link :href="lr('/dang-ky')" class="link font-semibold">{{ t('nav.register') }}</Link>
        </p>

        <details class="mt-6 rounded-lg bg-surface-2 p-3 text-xs">
            <summary class="cursor-pointer font-semibold">{{ t('auth.demo_accounts') }}</summary>
            <ul class="mt-2 space-y-1">
                <li v-for="email in DEMO_ACCOUNTS" :key="email">
                    <button type="button" class="link" @click="form.email = email">{{ email }}</button>
                </li>
            </ul>
            <p class="mt-2 text-muted">{{ t('auth.demo_password_hint') }}</p>
        </details>
    </div>
</template>
