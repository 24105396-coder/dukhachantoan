<script setup>
import { Link } from '@inertiajs/vue3';
import CoverImage from '@/Components/CoverImage.vue';
import { useT } from '@/Composables/useT';
import { useFormat } from '@/Composables/useFormat';

defineProps({
    figure: { type: Object, required: true },
    // Cấp tiêu đề theo ngữ cảnh: 2 khi danh sách đứng ngay dưới h1, 3 khi nằm trong một mục h2
    headingLevel: { type: Number, default: 3 },
    // Ảnh của thẻ đầu danh sách thường là LCP: tải ngay, không lazy
    eager: { type: Boolean, default: false },
});
const { te } = useT();
const { yearRange } = useFormat();
</script>

<template>
    <article class="card card-hover flex h-full flex-col overflow-hidden text-center">
        <Link :href="figure.url" tabindex="-1" aria-hidden="true" class="block p-4 pb-0">
            <CoverImage :src="figure.portrait" :alt="figure.title" icon="user" ratio="aspect-square" class="mx-auto w-28 rounded-full ring-4 ring-accent-soft" :eager="eager" />
        </Link>
        <div class="flex flex-1 flex-col items-center gap-1 p-4">
            <component :is="`h${headingLevel}`" class="text-base font-bold">
                <Link :href="figure.url" class="hover:text-primary">{{ figure.title }}</Link>
            </component>
            <p class="text-xs font-semibold text-accent-strong">{{ yearRange(figure.birth_year, figure.death_year) }}</p>
            <span class="badge">{{ te('figure_type', figure.figure_type) }}</span>
            <p class="mt-1 line-clamp-3 text-sm text-muted">{{ figure.summary }}</p>
        </div>
    </article>
</template>
