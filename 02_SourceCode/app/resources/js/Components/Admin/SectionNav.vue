<script setup>
import { useT } from '@/Composables/useT';

/** Mục lục neo cho form dài: sections [{id, label, error?}]. */
defineProps({ sections: { type: Array, required: true } });
const { t } = useT();
</script>

<template>
    <nav class="mb-4 overflow-x-auto" :aria-label="t('admin.form.sections')">
        <ul class="flex gap-2 pb-1">
            <li v-for="s in sections" :key="s.id" class="shrink-0">
                <a :href="`#${s.id}`" class="tab inline-flex items-center gap-2">
                    {{ s.label }}
                    <span v-if="s.error" class="h-2 w-2 rounded-full bg-danger" :title="t('admin.form.tab_has_errors')"></span>
                </a>
            </li>
        </ul>
    </nav>
</template>
