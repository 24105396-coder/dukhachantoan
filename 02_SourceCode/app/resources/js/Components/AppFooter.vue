<script setup>
import { computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import AppLogo from '@/Components/AppLogo.vue';
import { useT } from '@/Composables/useT';
import { useLocaleRoute } from '@/Composables/useLocaleRoute';

/**
 * Chân trang học thuật bắt buộc trên mọi trang (đề CSE703073): học phần, mã học phần, nhóm và dòng
 * "Sản phẩm học thuật – phục vụ mục đích đào tạo". `compact` = bản 2 dòng cho dashboard và trang xác thực.
 */
const props = defineProps({
    compact: { type: Boolean, default: false },
    links: { type: Array, default: () => [] }, // [{ key, path }] – nhãn lấy từ nav.<key>
});
const page = usePage();
const { t } = useT();
const lr = useLocaleRoute();

// Giá trị dự phòng chỉ dùng khi shared prop `app` chưa có (ví dụ trang lỗi)
const app = computed(() => ({
    name: 'DuKhachAnToan',
    course: 'Lập trình ứng dụng web trong du lịch 2',
    courseCode: 'CSE703073',
    team: 'DuKhachAnToan',
    ...(page.props.app || {}),
}));
const notice = computed(() => app.value.notice || t('footer.academic_notice'));
const hasLinks = computed(() => props.links.length > 0);
</script>

<template>
    <footer v-if="compact" class="px-4 py-5 text-center text-xs text-muted" data-academic-footer>
        <p>
            {{ t('footer.course') }}: <span class="font-semibold text-fg">{{ app.course }}</span>
            · {{ t('footer.course_code') }}: <span class="font-semibold text-fg">{{ app.courseCode }}</span>
            · {{ t('footer.team') }}: <span class="font-semibold text-fg">{{ app.team }}</span>
        </p>
        <p class="mt-1 font-bold text-fg">{{ notice }}</p>
    </footer>

    <footer v-else class="container-page relative z-10 mt-16 pb-6" data-academic-footer>
        <div class="clay-card grid gap-8 p-6 md:grid-cols-3 md:p-8">
            <div>
                <AppLogo />
                <p class="mt-3 max-w-sm text-sm text-muted">{{ t('footer.tagline') }}</p>
            </div>
            <nav v-if="hasLinks" :aria-label="t('footer.explore')">
                <h2 class="eyebrow mb-3">{{ t('footer.explore') }}</h2>
                <ul class="grid grid-cols-2 gap-2 text-sm">
                    <li v-for="item in links" :key="item.key">
                        <Link :href="lr(item.path)" class="font-medium hover:text-link hover:underline">{{ t(`nav.${item.key}`) }}</Link>
                    </li>
                </ul>
            </nav>
            <div class="text-sm" :class="{ 'md:col-span-2': !hasLinks }">
                <h2 class="eyebrow mb-3">{{ t('footer.academic') }}</h2>
                <dl class="space-y-1">
                    <div><dt class="inline text-muted">{{ t('footer.course') }}:</dt> <dd class="inline font-semibold">{{ app.course }}</dd></div>
                    <div><dt class="inline text-muted">{{ t('footer.course_code') }}:</dt> <dd class="inline font-semibold">{{ app.courseCode }}</dd></div>
                    <div><dt class="inline text-muted">{{ t('footer.team') }}:</dt> <dd class="inline font-semibold">{{ app.team }}</dd></div>
                </dl>
                <p class="mt-3 inline-block rounded-2xl bg-lemon-100 px-3 py-2 text-xs font-bold">{{ notice }}</p>
            </div>
        </div>
        <p class="mt-4 text-center text-xs text-muted">© 2026 {{ app.name }} · {{ t('footer.map_credit') }} · {{ t('footer.data_credit') }}</p>
    </footer>
</template>
