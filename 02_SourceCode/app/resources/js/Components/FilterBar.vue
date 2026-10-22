<script setup>
import { reactive, watch } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import Icon from '@/Components/Icon.vue';
import { useT } from '@/Composables/useT';

/**
 * Thanh lọc chung: gửi GET về chính URL hiện tại với query đã làm sạch.
 * fields: [{ name, type: 'search'|'select'|'date'|'checkbox', label, options?: [{value,label}], placeholder? }]
 */
const props = defineProps({
    filters: { type: Object, default: () => ({}) },
    fields: { type: Array, required: true },
    url: { type: String, default: null },
    only: { type: Array, default: () => [] },
});
const page = usePage();
const { t } = useT();

// Query string trả về "1"/"true" cho checkbox: chuẩn hóa về boolean để ô được tick đúng
function initial(field, filters) {
    const v = filters?.[field.name];
    if (field.type === 'checkbox') return v === true || v === 1 || v === '1' || v === 'true';
    return v ?? '';
}

const state = reactive(Object.fromEntries(props.fields.map((f) => [f.name, initial(f, props.filters)])));

watch(
    () => props.filters,
    (f) => props.fields.forEach((fd) => (state[fd.name] = initial(fd, f))),
);

function clean(obj) {
    return Object.fromEntries(
        Object.entries(obj).filter(([, v]) => v !== '' && v !== null && v !== undefined && v !== false),
    );
}

function submit() {
    const target = props.url || (page.url || '').split('?')[0];
    const query = clean(state);
    Object.keys(query).forEach((k) => query[k] === true && (query[k] = 1));
    router.get(target, query, { preserveState: true, preserveScroll: true, replace: true, only: props.only.length ? props.only : undefined });
}

function reset() {
    props.fields.forEach((f) => (state[f.name] = f.type === 'checkbox' ? false : ''));
    submit();
}

const hasActive = () => Object.keys(clean(state)).length > 0;
</script>

<template>
    <form class="card grid gap-3 p-4 sm:grid-cols-2 lg:grid-cols-4" role="search" @submit.prevent="submit">
        <div v-for="f in fields" :key="f.name" :class="f.type === 'search' ? 'sm:col-span-2' : ''">
            <template v-if="f.type === 'checkbox'">
                <label class="flex h-full items-end gap-2 pb-2 text-sm font-medium">
                    <input v-model="state[f.name]" type="checkbox" class="h-4 w-4 accent-[var(--ds-primary)]" @change="submit" />
                    {{ f.label }}
                </label>
            </template>
            <template v-else>
                <label :for="`flt-${f.name}`" class="label">{{ f.label }}</label>
                <select v-if="f.type === 'select'" :id="`flt-${f.name}`" v-model="state[f.name]" class="input" @change="submit">
                    <option value="">{{ f.placeholder || t('common.all') }}</option>
                    <option v-for="o in f.options" :key="o.value" :value="o.value">{{ o.label }}</option>
                </select>
                <input v-else-if="f.type === 'date'" :id="`flt-${f.name}`" v-model="state[f.name]" type="date" class="input" @change="submit" />
                <div v-else class="relative">
                    <span class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-muted"><Icon name="search" :size="18" /></span>
                    <input :id="`flt-${f.name}`" v-model="state[f.name]" type="search" class="input pl-10" :placeholder="f.placeholder || t('common.search_placeholder')" />
                </div>
            </template>
        </div>
        <div class="flex items-end gap-2 sm:col-span-2 lg:col-span-4 lg:justify-end">
            <button type="submit" class="btn btn-primary"><Icon name="filter" :size="18" />{{ t('common.apply') }}</button>
            <button v-if="hasActive()" type="button" class="btn" @click="reset">{{ t('common.reset_filters') }}</button>
        </div>
    </form>
</template>
