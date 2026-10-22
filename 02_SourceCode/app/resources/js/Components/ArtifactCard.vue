<script setup>
import { Link } from '@inertiajs/vue3';
import CoverImage from '@/Components/CoverImage.vue';
import { useT } from '@/Composables/useT';
import { useFormat } from '@/Composables/useFormat';

defineProps({
    artifact: { type: Object, required: true },
    // Cấp tiêu đề theo ngữ cảnh: 2 khi danh sách đứng ngay dưới h1, 3 khi nằm trong một mục h2
    headingLevel: { type: Number, default: 3 },
    // Ảnh của thẻ đầu danh sách thường là LCP: tải ngay, không lazy
    eager: { type: Boolean, default: false },
});
const { t, te } = useT();
const { yearRange } = useFormat();
</script>

<template>
    <article class="card card-hover flex h-full flex-col overflow-hidden">
        <Link :href="artifact.url" tabindex="-1" aria-hidden="true">
            <CoverImage :src="artifact.cover?.url" :alt="artifact.cover?.caption || artifact.title" icon="vase" ratio="aspect-square" :eager="eager">
                <span v-if="artifact.is_national_treasure" class="badge badge-primary absolute left-3 top-3 shadow">
                    {{ t('artifacts.national_treasure') }}
                </span>
            </CoverImage>
        </Link>
        <div class="flex flex-1 flex-col gap-1.5 p-4">
            <p class="eyebrow">{{ te('artifact_type', artifact.artifact_type) }}</p>
            <component :is="`h${headingLevel}`" class="text-base font-bold leading-snug">
                <Link :href="artifact.url" class="hover:text-primary">{{ artifact.title }}</Link>
            </component>
            <p class="line-clamp-2 text-sm text-muted">{{ artifact.summary }}</p>
            <p class="mt-auto pt-2 text-xs text-muted">
                <span v-if="artifact.material">{{ artifact.material }}</span>
                <span v-if="artifact.material && (artifact.year_from || artifact.year_to)"> · </span>
                <span>{{ yearRange(artifact.year_from, artifact.year_to) }}</span>
            </p>
            <p v-if="artifact.site" class="truncate text-xs font-medium text-accent-strong">{{ artifact.site.title }}</p>
        </div>
    </article>
</template>
