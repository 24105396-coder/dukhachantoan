<script setup>
import { computed } from 'vue';
import { useForm, usePage } from '@inertiajs/vue3';
import PageMeta from '@/Components/Public/PageMeta.vue';
import PageHeader from '@/Components/PageHeader.vue';
import FormField from '@/Components/FormField.vue';
import Checkbox from '@/Components/Form/Checkbox.vue';
import Button from '@/Components/Button.vue';
import ChannelMatrix from '@/Components/Account/ChannelMatrix.vue';
import { useT } from '@/Composables/useT';
import { useTheme } from '@/Composables/useTheme';
import { useLocaleRoute } from '@/Composables/useLocaleRoute';
import { useClientValidation } from '@/Composables/useClientValidation';
import { PHONE_PATTERN, PASSWORD_PATTERN, PHONE_RE, PASSWORD_RE } from '@/Utils/rules';
import { nationalityOptions } from '@/Utils/countries';
import { LOCALES, CHANNELS } from '@/Utils/options';

/**
 * Tài khoản: hồ sơ, liên hệ khẩn cấp + tùy chọn nhận thông báo (NotificationPreferences) gửi chung một PUT /tai-khoan;
 * đổi mật khẩu là form riêng (PATCH /tai-khoan/mat-khau).
 */
const props = defineProps({
    user: { type: Object, default: () => ({}) },
    preferences: { type: Object, default: () => ({}) },
    channels: { type: Array, default: () => CHANNELS },
});
const page = usePage();
const { t, te } = useT();
const lr = useLocaleRoute();
const { theme, applyLocal } = useTheme();
const locale = page.props.locale || 'vi';

const DEFAULT_CHANNELS = { 1: ['inapp'], 2: ['inapp'], 3: ['inapp', 'email'], 4: ['inapp', 'email', 'sms'] };
const prefs = props.preferences || {};
const profile = useForm({
    name: props.user?.name || '',
    phone: props.user?.phone || '',
    nationality: props.user?.nationality || '',
    emergency_contact_name: props.user?.emergency_contact_name || '',
    emergency_contact_phone: props.user?.emergency_contact_phone || '',
    locale: props.user?.locale || locale,
    theme: props.user?.theme || theme.value || 'light',
    preferences: {
        channels_level1: prefs.channels_level1 ?? DEFAULT_CHANNELS[1],
        channels_level2: prefs.channels_level2 ?? DEFAULT_CHANNELS[2],
        channels_level3: prefs.channels_level3 ?? DEFAULT_CHANNELS[3],
        channels_level4: prefs.channels_level4 ?? DEFAULT_CHANNELS[4],
        quiet_from: prefs.quiet_from || '',
        quiet_to: prefs.quiet_to || '',
        digest_daily: !!prefs.digest_daily,
        language: prefs.language || props.user?.preferred_locale || locale,
    },
});
const password = useForm({ current_password: '', password: '', password_confirmation: '' });

const profileCheck = useClientValidation(profile, {
    name: { label: 'common.name', required: true, min: 2, max: 120 },
    phone: { label: 'common.phone', pattern: { regex: PHONE_RE, message: 'phone' } },
    emergency_contact_name: { label: 'account.emergency_contact_name', max: 120 },
    emergency_contact_phone: { label: 'account.emergency_contact_phone', pattern: { regex: PHONE_RE, message: 'phone' } },
});
const passwordCheck = useClientValidation(password, {
    current_password: { label: 'account.current_password', required: true },
    password: { label: 'account.new_password', required: true, min: 8, pattern: { regex: PASSWORD_RE, message: 'password' } },
    password_confirmation: { label: 'auth.password_confirmation', required: true, confirmed: 'password' },
});

const localeOptions = LOCALES.map((code) => ({ value: code, label: te('locale', code) }));
const themeOptions = ['light', 'dark'].map((v) => ({ value: v, label: te('theme', v) }));
const countries = nationalityOptions(locale);
const prefErrors = computed(() => profile.errors);

function saveProfile(e) {
    if (!profileCheck.validate(e)) return;
    const localeChanged = profile.locale !== locale;
    profile.put(lr('/tai-khoan'), {
        preserveScroll: true,
        onSuccess: () => {
            applyLocal(profile.theme);
            // Đổi ngôn ngữ giao diện: chuyển sang cùng trang với locale mới
            if (localeChanged) window.location.href = `/${profile.locale}/tai-khoan`;
        },
    });
}

function savePassword(e) {
    if (!passwordCheck.validate(e)) return;
    password.patch(lr('/tai-khoan/mat-khau'), { preserveScroll: true, onSuccess: () => password.reset() });
}
</script>

<template>
    <PageMeta :title="t('account.title')" />
    <PageHeader :eyebrow="t('nav.account')" :title="t('account.title')" :description="t('account.intro')" />

    <form class="space-y-6" novalidate @submit.prevent="saveProfile">
        <section class="clay-card-sm p-5 md:p-6" aria-labelledby="acc-profile">
            <h2 id="acc-profile" class="section-title text-xl">{{ t('account.profile_info') }}</h2>
            <div class="mt-5 grid gap-4 md:grid-cols-2">
                <FormField v-model="profile.name" class="md:col-span-2" :label="t('common.name')" required minlength="2" maxlength="120" autocomplete="name" :error="profile.errors.name" />
                <FormField v-model="profile.phone" :label="t('common.phone')" type="tel" :pattern="PHONE_PATTERN" :pattern-hint="t('validation_hint.phone')" :hint="t('auth.phone_hint')" maxlength="16" autocomplete="tel" :error="profile.errors.phone" />
                <FormField v-model="profile.nationality" :label="t('account.nationality')" type="select" :options="countries" :placeholder="t('auth.nationality_placeholder')" :error="profile.errors.nationality" />
                <FormField v-model="profile.locale" :label="t('account.locale')" type="select" :options="localeOptions" :error="profile.errors.locale" />
                <FormField v-model="profile.theme" :label="t('account.theme')" type="select" :options="themeOptions" :error="profile.errors.theme" @update:model-value="applyLocal" />
            </div>
            <h3 class="mt-6 text-base">{{ t('account.emergency_contact') }}</h3>
            <div class="mt-3 grid gap-4 md:grid-cols-2">
                <FormField v-model="profile.emergency_contact_name" :label="t('account.emergency_contact_name')" maxlength="120" autocomplete="off" :error="profile.errors.emergency_contact_name" />
                <FormField v-model="profile.emergency_contact_phone" :label="t('account.emergency_contact_phone')" type="tel" :pattern="PHONE_PATTERN" :pattern-hint="t('validation_hint.phone')" maxlength="16" autocomplete="off" :error="profile.errors.emergency_contact_phone" />
            </div>
        </section>

        <section class="clay-card-sm p-5 md:p-6" aria-labelledby="acc-prefs">
            <h2 id="acc-prefs" class="section-title text-xl">{{ t('account.preferences') }}</h2>
            <p class="mt-2 text-sm text-muted">{{ t('account.preferences_intro') }}</p>
            <div class="mt-5">
                <ChannelMatrix
                    :model-value="profile.preferences"
                    :channels="channels"
                    :errors="prefErrors"
                    @update:model-value="(v) => (profile.preferences = v)"
                />
            </div>
            <div class="mt-5 grid gap-4 md:grid-cols-3">
                <FormField v-model="profile.preferences.quiet_from" :label="t('account.quiet_from')" type="time" :hint="t('account.quiet_hint')" :error="profile.errors['preferences.quiet_from']" />
                <FormField v-model="profile.preferences.quiet_to" :label="t('account.quiet_to')" type="time" :error="profile.errors['preferences.quiet_to']" />
                <FormField v-model="profile.preferences.language" :label="t('account.language')" type="select" :options="localeOptions" :error="profile.errors['preferences.language']" />
            </div>
            <div class="mt-4">
                <Checkbox v-model="profile.preferences.digest_daily" :label="t('account.digest_daily')" :hint="t('account.digest_hint')" />
            </div>
        </section>

        <div class="flex flex-wrap items-center gap-3">
            <Button type="submit" variant="primary" icon="check" :loading="profile.processing">{{ profile.processing ? t('common.saving') : t('common.save') }}</Button>
            <span v-if="profile.recentlySuccessful" class="text-sm font-semibold text-success" role="status">{{ t('account.saved') }}</span>
        </div>
    </form>

    <section class="clay-card-sm mt-8 p-5 md:p-6" aria-labelledby="acc-password">
        <h2 id="acc-password" class="section-title text-xl">{{ t('account.change_password') }}</h2>
        <form class="mt-5 grid gap-4 md:grid-cols-3" novalidate @submit.prevent="savePassword">
            <FormField v-model="password.current_password" :label="t('account.current_password')" type="password" required autocomplete="current-password" :error="password.errors.current_password" />
            <FormField v-model="password.password" :label="t('account.new_password')" type="password" required minlength="8" :pattern="PASSWORD_PATTERN" :pattern-hint="t('auth.password_hint')" autocomplete="new-password" :error="password.errors.password" />
            <FormField v-model="password.password_confirmation" :label="t('auth.password_confirmation')" type="password" required minlength="8" autocomplete="new-password" :error="password.errors.password_confirmation" />
            <p class="field-hint md:col-span-3">{{ t('auth.password_hint') }}</p>
            <div class="flex flex-wrap items-center gap-3 md:col-span-3">
                <Button type="submit" icon="key" :loading="password.processing">{{ t('account.update_password') }}</Button>
                <span v-if="password.recentlySuccessful" class="text-sm font-semibold text-success" role="status">{{ t('account.saved') }}</span>
            </div>
        </form>
    </section>
</template>
