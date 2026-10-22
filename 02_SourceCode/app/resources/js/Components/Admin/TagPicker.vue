<script setup>
import { ref, computed } from 'vue';
import { useT } from '@/Composables/useT';

/** Chọn nhiều thẻ dạng chip checkbox, nhóm theo tag_group, có ô lọc. */
const props = defineProps({
    tags: { type: Array, default: () => [] },
    error: { type: [String, Array], default: null },
});
const selected = defineModel({ type: Array, default: () => [] });
const { t, te } = useT();
const query = ref('');

const groups = computed(() => {
    const q = query.value.trim().toLowerCase();
    const map = new Map();
    (props.tags || [])
        .filter((tag) => !q || `${tag.name} ${tag.slug}`.toLowerCase().includes(q))
        .forEach((tag) => {
            const g = tag.group || tag.tag_group || 'khac';
            if (!map.has(g)) map.set(g, []);
            map.get(g).push(tag);
        });
    return [...map.entries()].map(([group, items]) => ({ group, items }));
});

function toggle(id) {
    selected.value = selected.value.includes(id) ? selected.value.filter((x) => x !== id) : [...selected.value, id];
}
</script>

<template>
    <div>
        <div class="mb-3 flex flex-wrap items-end gap-3">
            <div class="min-w-0 flex-1">
                <label for="tag-filter" class="label">{{ t('admin.form.tag_filter') }}</label>
                <input id="tag-filter" v-model="query" type="search" class="input" :placeholder="t('common.search_placeholder')" />
            </div>
            <p class="pb-2 text-sm text-muted">{{ t('admin.form.tags_selected', { n: selected.length }) }}</p>
        </div>
        <p v-if="!groups.length" class="text-sm text-muted">{{ t('common.no_data') }}</p>
        <fieldset v-for="g in groups" :key="g.group" class="mb-3">
            <legend class="mb-2 text-xs font-bold uppercase tracking-wider text-muted">{{ te('tag_group', g.group) }}</legend>
            <div class="flex flex-wrap gap-2">
                <label
                    v-for="tag in g.items"
                    :key="tag.id"
                    class="badge cursor-pointer select-none px-3 py-1 text-sm"
                    :class="selected.includes(tag.id) ? 'badge-primary ring-1 ring-primary' : ''"
                >
                    <input type="checkbox" class="sr-only" :checked="selected.includes(tag.id)" @change="toggle(tag.id)" />
                    <span aria-hidden="true">{{ selected.includes(tag.id) ? '✓' : '+' }}</span> {{ tag.name }}
                </label>
            </div>
        </fieldset>
        <p v-if="error" class="field-error" role="alert">{{ Array.isArray(error) ? error[0] : error }}</p>
    </div>
</template>
