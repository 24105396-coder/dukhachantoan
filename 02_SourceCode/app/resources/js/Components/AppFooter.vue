<script setup>
import { computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import AppLogo from '@/Components/AppLogo.vue';
import { useT } from '@/Composables/useT';
import { useLocaleRoute } from '@/Composables/useLocaleRoute';
import { useMapProvider } from '@/Composables/useMapProvider';

/**
 * Chân trang dùng chung, bắt buộc trên mọi trang (Mục 2.4 đề thi): tên học phần, mã học phần,
 * tên nhóm và dòng "Sản phẩm học thuật". `compact` = bản 2 dòng cho quản trị và trang xác thực;
 * bản đầy đủ (trang công khai) thêm logo và danh sách liên kết `links`.
 */
const props = defineProps({
    compact: { type: Boolean, default: false },
    links: { type: Array, default: () => [] }, // [{ key, path }] – nhãn lấy từ nav.<key>
});
const page = usePage();
const { t } = useT();
const lr = useLocaleRoute();
const { creditKey } = useMapProvider();

// Giá trị dự phòng chỉ dùng khi shared prop `app` chưa có (ví dụ trang lỗi Inertia)
const app = computed(() => ({
    name: 'DiSanSo',
    course: 'Lập trình ứng dụng web trong du lịch 2',
    courseCode: 'CSE703073',
    team: 'DiSanSo',
    ...(page.props.app || {}),
}));
const notice = computed(() => app.value.notice || t('footer.academic_notice'));
const hasLinks = computed(() => props.links.length > 0);
</script>

<template>
    <footer v-if="compact" class="border-t border-border px-4 py-4 text-center text-xs text-muted md:px-6" data-academic-footer>
        <p>
            {{ t('footer.course') }}: <span class="font-medium text-text">{{ app.course }}</span>
            · {{ t('footer.course_code') }}: <span class="font-medium text-text">{{ app.courseCode }}</span>
            · {{ t('footer.team') }}: <span class="font-medium text-text">{{ app.team }}</span>
        </p>
        <p class="mt-1 font-semibold text-accent-strong">{{ notice }}</p>
    </footer>

    <footer v-else class="mt-16 border-t border-border bg-surface" data-academic-footer>
        <div class="ornament"></div>
        <div class="container-page grid gap-8 py-10 md:grid-cols-3">
            <div>
                <AppLogo />
                <p class="mt-3 max-w-sm text-sm text-muted">{{ t('footer.tagline') }}</p>
            </div>
            <nav v-if="hasLinks" :aria-label="t('footer.explore')">
                <h2 class="mb-3 font-body text-sm font-bold uppercase tracking-wider text-muted">{{ t('footer.explore') }}</h2>
                <ul class="grid grid-cols-2 gap-2 text-sm">
                    <li v-for="item in links" :key="item.key">
                        <Link :href="lr(item.path)" class="hover:text-primary hover:underline">{{ t(`nav.${item.key}`) }}</Link>
                    </li>
                </ul>
            </nav>
            <div class="text-sm" :class="{ 'md:col-span-2': !hasLinks }">
                <h2 class="mb-3 font-body text-sm font-bold uppercase tracking-wider text-muted">{{ t('footer.academic') }}</h2>
                <dl class="space-y-1">
                    <div><dt class="inline text-muted">{{ t('footer.course') }}:</dt> <dd class="inline font-medium">{{ app.course }}</dd></div>
                    <div><dt class="inline text-muted">{{ t('footer.course_code') }}:</dt> <dd class="inline font-medium">{{ app.courseCode }}</dd></div>
                    <div><dt class="inline text-muted">{{ t('footer.team') }}:</dt> <dd class="inline font-medium">{{ app.team }}</dd></div>
                </dl>
                <p class="mt-3 rounded-lg bg-accent-soft px-3 py-2 text-xs font-semibold text-accent-strong">{{ notice }}</p>
            </div>
        </div>
        <div class="border-t border-border">
            <p class="container-page py-4 text-xs text-muted">© 2026 {{ app.name }} · {{ t(creditKey) }}</p>
        </div>
    </footer>
</template>
