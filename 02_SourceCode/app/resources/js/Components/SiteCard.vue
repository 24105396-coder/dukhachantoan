<script setup>
import { Link } from '@inertiajs/vue3';
import CoverImage from '@/Components/CoverImage.vue';
import Icon from '@/Components/Icon.vue';
import { useT } from '@/Composables/useT';
import { useFormat } from '@/Composables/useFormat';

defineProps({
    site: { type: Object, required: true },
    // Cấp tiêu đề theo ngữ cảnh: 2 khi danh sách đứng ngay dưới h1, 3 khi nằm trong một mục h2
    headingLevel: { type: Number, default: 3 },
    // Ảnh của thẻ đầu danh sách thường là LCP: tải ngay, không lazy
    eager: { type: Boolean, default: false },
});
const { te } = useT();
const { year } = useFormat();
</script>

<template>
    <article class="card card-hover group flex h-full flex-col overflow-hidden">
        <Link :href="site.url" class="block focus-visible:outline-offset-[-3px]" tabindex="-1" aria-hidden="true">
            <CoverImage :src="site.cover?.url" :alt="site.cover?.caption || site.title" :eager="eager">
                <span
                    v-if="site.recognition_level && site.recognition_level !== 'chua_xep_hang'"
                    class="badge badge-accent absolute left-3 top-3 shadow"
                >
                    {{ te('recognition_level', site.recognition_level) }}
                </span>
            </CoverImage>
        </Link>
        <div class="flex flex-1 flex-col gap-2 p-4">
            <p class="eyebrow">{{ te('site_type', site.site_type) }}</p>
            <component :is="`h${headingLevel}`" class="text-lg font-bold">
                <Link :href="site.url" class="hover:text-primary">{{ site.title }}</Link>
            </component>
            <p class="line-clamp-3 text-sm text-muted">{{ site.summary }}</p>
            <p class="mt-auto flex flex-wrap items-center gap-x-3 gap-y-1 pt-2 text-xs text-muted">
                <span v-if="site.province" class="inline-flex items-center gap-1"><Icon name="pin" :size="14" />{{ site.province.name }}</span>
                <span v-if="site.period" class="inline-flex items-center gap-1"><Icon name="clock" :size="14" />{{ site.period.name }}</span>
                <span v-else-if="site.year_built" class="inline-flex items-center gap-1"><Icon name="clock" :size="14" />{{ year(site.year_built) }}</span>
            </p>
        </div>
    </article>
</template>
