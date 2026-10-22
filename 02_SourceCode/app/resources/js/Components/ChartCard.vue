<script setup>
import { ref, watch, onMounted, onBeforeUnmount, shallowRef } from 'vue';
import EmptyState from '@/Components/EmptyState.vue';
import { useT } from '@/Composables/useT';

// Chart.js nạp động (chunk vendor-chart), chỉ trang dashboard mới tải; dùng chung một Promise
let chartModule = null;
function loadChart() {
    chartModule ??= import('chart.js').then((m) => {
        m.Chart.register(
            m.LineController, m.BarController, m.DoughnutController, m.LineElement, m.BarElement, m.ArcElement,
            m.PointElement, m.CategoryScale, m.LinearScale, m.Tooltip, m.Legend, m.Filler,
        );
        return m.Chart;
    });
    return chartModule;
}

const props = defineProps({
    title: { type: String, required: true },
    type: { type: String, default: 'bar' }, // bar | line | doughnut | hbar
    labels: { type: Array, default: () => [] },
    values: { type: Array, default: () => [] },
    seriesLabel: { type: String, default: '' },
    height: { type: Number, default: 260 },
});
const { t } = useT();
const canvas = ref(null);
const chart = shallowRef(null);
const loadFailed = ref(false);
let observer = null;
let Chart = null;

// Màu đọc từ biến CSS để theo theme hiện tại
function tokens() {
    const s = getComputedStyle(document.documentElement);
    const v = (n) => s.getPropertyValue(n).trim();
    return {
        text: v('--ds-text'), muted: v('--ds-muted'), border: v('--ds-border'),
        palette: [v('--ds-primary'), v('--ds-accent'), v('--ds-info'), v('--ds-success'), v('--ds-warning'), v('--ds-danger'), v('--ds-muted')],
    };
}

function build() {
    chart.value?.destroy();
    if (!Chart || !canvas.value || !props.values.length) return;
    const c = tokens();
    const isDoughnut = props.type === 'doughnut';
    const isHbar = props.type === 'hbar';
    const color = isDoughnut ? c.palette : props.type === 'line' ? c.palette[0] : c.palette[1];
    chart.value = new Chart(canvas.value, {
        type: isHbar ? 'bar' : props.type,
        data: {
            labels: props.labels,
            datasets: [{
                label: props.seriesLabel || props.title,
                data: props.values,
                backgroundColor: props.type === 'line' ? `${c.palette[0]}33` : color,
                borderColor: isDoughnut ? 'transparent' : color,
                borderWidth: props.type === 'line' ? 2 : 0,
                fill: props.type === 'line',
                tension: 0.3,
                pointRadius: 2,
                borderRadius: isDoughnut ? 0 : 6,
            }],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            indexAxis: isHbar ? 'y' : 'x',
            plugins: {
                legend: { display: isDoughnut, position: 'bottom', labels: { color: c.text, boxWidth: 12 } },
                tooltip: { enabled: true },
            },
            scales: isDoughnut ? {} : {
                x: { ticks: { color: c.muted, maxRotation: 0, autoSkip: true }, grid: { color: c.border } },
                y: { ticks: { color: c.muted, precision: 0 }, grid: { color: c.border }, beginAtZero: true },
            },
        },
    });
}

onMounted(async () => {
    try {
        Chart = await loadChart();
    } catch {
        loadFailed.value = true;
        return;
    }
    build();
    observer = new MutationObserver(build);
    observer.observe(document.documentElement, { attributes: true, attributeFilter: ['data-theme'] });
});
watch(() => [props.labels, props.values], build, { deep: true, flush: 'post' });
onBeforeUnmount(() => {
    observer?.disconnect();
    chart.value?.destroy();
});
</script>

<template>
    <section class="card p-4">
        <h2 class="mb-3 font-body text-base font-bold">{{ title }}</h2>
        <EmptyState v-if="!values.length" icon="activity" :title="t('common.no_data')" compact />
        <p v-else-if="loadFailed" class="text-sm text-muted" role="alert">{{ t('common.chart_error') }}</p>
        <div v-else class="relative" :style="{ height: `${height}px` }">
            <canvas ref="canvas" role="img" :aria-label="title"></canvas>
        </div>
        <table v-if="values.length" class="sr-only">
            <caption>{{ title }}</caption>
            <tbody>
                <tr v-for="(l, i) in labels" :key="i"><th scope="row">{{ l }}</th><td>{{ values[i] }}</td></tr>
            </tbody>
        </table>
    </section>
</template>
