<script setup>
import { computed } from 'vue';

// Tô sáng các từ khớp mà không dùng v-html (an toàn XSS).
// So khớp không phân biệt dấu tiếng Việt: "thang" khớp "Thăng" (máy tìm kiếm trả từ đã bỏ dấu).
const props = defineProps({
    text: { type: String, default: '' },
    terms: { type: Array, default: () => [] },
});

/** Bỏ dấu một ký tự (giữ nguyên độ dài 1 để ánh xạ vị trí về chuỗi gốc). */
function fold(ch) {
    const base = ch.normalize('NFD').replace(/[̀-ͯ]/g, '');
    const lower = (base || ch).toLowerCase();
    if (lower === 'đ') return 'd';
    return lower.length === 1 ? lower : ch.toLowerCase();
}

const parts = computed(() => {
    const text = props.text || '';
    const words = [...new Set(props.terms.filter((w) => w && w.length > 1).map((w) => [...w].map(fold).join('')))];
    if (!text || !words.length) return [{ text, hit: false }];

    const chars = [...text];
    const folded = chars.map(fold).join('');
    const hits = new Array(chars.length).fill(false);
    words.forEach((w) => {
        let from = folded.indexOf(w);
        while (from !== -1) {
            for (let i = from; i < from + w.length; i += 1) hits[i] = true;
            from = folded.indexOf(w, from + w.length);
        }
    });

    // Gom các ký tự liên tiếp cùng trạng thái thành từng đoạn
    return chars.reduce((out, ch, i) => {
        const last = out[out.length - 1];
        if (last && last.hit === hits[i]) {
            return [...out.slice(0, -1), { text: last.text + ch, hit: last.hit }];
        }
        return [...out, { text: ch, hit: hits[i] }];
    }, []);
});
</script>

<template>
    <span><template v-for="(p, i) in parts" :key="i"><mark v-if="p.hit" class="rounded bg-accent-soft px-0.5 text-text">{{ p.text }}</mark><template v-else>{{ p.text }}</template></template></span>
</template>
