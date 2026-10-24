<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import AppLogo from '@/Components/AppLogo.vue';
import FlashToast from '@/Components/FlashToast.vue';
import ThemeToggle from '@/Components/ThemeToggle.vue';
import LocaleSwitch from '@/Components/LocaleSwitch.vue';
import AppFooter from '@/Components/AppFooter.vue';
import Figure from '@/Components/Figure.vue';
import { useT } from '@/Composables/useT';
import { useLocaleRoute } from '@/Composables/useLocaleRoute';
import { useImages } from '@/Composables/useImages';

/**
 * Khung trang xác thực: thẻ trắng viền mảnh trên nền trung tính. Từ 1024px có cột trái là ảnh du lịch
 * (hero/auth, credit ở góc dưới); điện thoại/tablet chỉ còn thẻ form để màn hình gọn.
 */
const { t } = useT();
const lr = useLocaleRoute();
const images = useImages();
/** Ảnh cột trái trang đăng nhập/đăng ký (null → nền brand + icon). */
const authImage = computed(() => images.hero('auth'));
</script>

<template>
    <div class="relative flex min-h-screen flex-col">
        <header class="container-page relative z-10 flex h-20 items-center justify-between">
            <Link :href="lr('/')" class="rounded-full" :aria-label="t('nav.home_logo')"><AppLogo /></Link>
            <div class="flex items-center gap-1.5">
                <LocaleSwitch />
                <ThemeToggle />
            </div>
        </header>
        <FlashToast />
        <main id="main" class="container-page relative z-10 flex flex-1 items-start justify-center py-6 md:items-center" tabindex="-1">
            <div class="grid w-full max-w-md items-stretch gap-6 lg:max-w-5xl lg:grid-cols-[1.1fr_1fr]">
                <div class="relative hidden min-h-[32rem] overflow-hidden rounded-[var(--radius-clay)] border border-line lg:block">
                    <Figure :image="authImage" fill credit="overlay" rounded-class="" fallback-icon="route" :width="1600" :height="900" />
                    <div class="absolute inset-x-0 top-0 p-6">
                        <p class="callout callout-brand inline-block max-w-sm px-4 py-3 text-sm font-semibold">{{ t('ui.images.auth_tagline') }}</p>
                    </div>
                </div>
                <div class="clay-card w-full self-center p-6 md:p-8">
                    <slot />
                </div>
            </div>
        </main>
        <AppFooter compact />
    </div>
</template>
