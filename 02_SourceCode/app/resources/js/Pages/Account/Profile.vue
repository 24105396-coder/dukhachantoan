<script setup>
import { PHONE_PATTERN, PASSWORD_PATTERN, PHONE_RE, PASSWORD_RE } from '@/Utils/rules';
import { useClientValidation } from '@/Composables/useClientValidation';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import PageHeader from '@/Components/PageHeader.vue';
import FormField from '@/Components/FormField.vue';
import { useT } from '@/Composables/useT';
import { useTheme } from '@/Composables/useTheme';
import { useLocaleRoute } from '@/Composables/useLocaleRoute';

const props = defineProps({
    user: { type: Object, default: () => ({}) },
});
const page = usePage();
const { t, te } = useT();
const lr = useLocaleRoute();
const { theme } = useTheme();

const profile = useForm({
    name: props.user?.name || '',
    phone: props.user?.phone || '',
    organization: props.user?.organization || '',
    locale: props.user?.locale || page.props.locale || 'vi',
    theme: props.user?.theme || theme.value || 'light',
});
const password = useForm({ current_password: '', password: '', password_confirmation: '' });

// Áp dụng theme ngay trên trình duyệt; giá trị được lưu cùng lần PATCH hồ sơ
function applyThemeLocally(value) {
    document.documentElement.setAttribute('data-theme', value);
    theme.value = value;
    try {
        localStorage.setItem('disanso-theme', value);
    } catch {
        // storage bị chặn: chỉ đổi trong phiên
    }
}

const profileCheck = useClientValidation(profile, {
    name: { label: 'common.name', required: true, min: 2, max: 120 },
    phone: { label: 'common.phone', pattern: { regex: PHONE_RE, message: 'phone' } },
    organization: { label: 'account.organization', max: 190 },
});
const passwordCheck = useClientValidation(password, {
    current_password: { label: 'account.current_password', required: true },
    password: { label: 'account.new_password', required: true, min: 8, pattern: { regex: PASSWORD_RE, message: 'password' } },
    password_confirmation: { label: 'auth.password_confirmation', required: true, confirmed: 'password' },
});

function saveProfile(e) {
    if (!profileCheck.validate(e)) return;
    const localeChanged = profile.locale !== page.props.locale;
    profile.patch(lr('/tai-khoan/ho-so'), {
        preserveScroll: true,
        onSuccess: () => {
            applyThemeLocally(profile.theme);
            // Đổi ngôn ngữ giao diện: chuyển sang cùng trang với locale mới
            if (localeChanged) window.location.href = `/${profile.locale}/tai-khoan/ho-so`;
        },
    });
}

function savePassword(e) {
    if (!passwordCheck.validate(e)) return;
    password.patch(lr('/tai-khoan/mat-khau'), { preserveScroll: true, onSuccess: () => password.reset() });
}

const localeOptions = (page.props.locales || [{ code: 'vi' }, { code: 'en' }]).map((l) => ({ value: l.code, label: l.label || te('locale', l.code) }));
const themeOptions = ['light', 'dark'].map((v) => ({ value: v, label: te('theme', v) }));
</script>

<template>
    <Head :title="t('account.profile_title')" />
    <div class="container-page max-w-3xl py-8">
        <PageHeader :eyebrow="t('nav.profile')" :title="t('account.profile_title')" :description="user?.email" />

        <section class="card p-5 md:p-6">
            <h2 class="font-body text-lg font-bold">{{ t('account.profile_info') }}</h2>
            <form class="mt-4 grid gap-4 sm:grid-cols-2" novalidate @submit.prevent="saveProfile">
                <FormField v-model="profile.name" class="sm:col-span-2" :label="t('common.name')" required minlength="2" maxlength="120" autocomplete="name" :error="profile.errors.name" />
                <FormField v-model="profile.phone" :label="t('common.phone')" type="tel" :pattern="PHONE_PATTERN" :pattern-hint="t('validation_hint.phone')" maxlength="16" autocomplete="tel" :error="profile.errors.phone" />
                <FormField v-model="profile.organization" :label="`${t('account.organization')} (${t('common.optional')})`" maxlength="190" autocomplete="organization" :error="profile.errors.organization" />
                <FormField v-model="profile.locale" :label="t('account.locale')" type="select" :options="localeOptions" :error="profile.errors.locale" />
                <FormField v-model="profile.theme" :label="t('account.theme')" type="select" :options="themeOptions" :error="profile.errors.theme" @update:model-value="applyThemeLocally" />
                <div class="sm:col-span-2">
                    <button type="submit" class="btn btn-primary" :disabled="profile.processing">{{ profile.processing ? t('common.saving') : t('common.save') }}</button>
                    <span v-if="profile.recentlySuccessful" class="ml-3 text-sm text-success" role="status">{{ t('account.saved') }}</span>
                </div>
            </form>
        </section>

        <section class="card mt-6 p-5 md:p-6">
            <h2 class="font-body text-lg font-bold">{{ t('account.change_password') }}</h2>
            <form class="mt-4 grid gap-4" novalidate @submit.prevent="savePassword">
                <FormField v-model="password.current_password" :label="t('account.current_password')" type="password" required autocomplete="current-password" :error="password.errors.current_password" />
                <FormField v-model="password.password" :label="t('account.new_password')" type="password" required minlength="8" :pattern="PASSWORD_PATTERN" :pattern-hint="t('auth.password_hint')" autocomplete="new-password" :hint="t('auth.password_hint')" :error="password.errors.password" />
                <FormField v-model="password.password_confirmation" :label="t('auth.password_confirmation')" type="password" required minlength="8" autocomplete="new-password" :error="password.errors.password_confirmation" />
                <div>
                    <button type="submit" class="btn btn-primary" :disabled="password.processing">{{ password.processing ? t('common.saving') : t('account.update_password') }}</button>
                    <span v-if="password.recentlySuccessful" class="ml-3 text-sm text-success" role="status">{{ t('account.saved') }}</span>
                </div>
            </form>
        </section>
    </div>
</template>
