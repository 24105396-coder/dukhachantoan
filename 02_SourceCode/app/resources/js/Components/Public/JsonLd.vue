<script>
import { computed, h } from 'vue';
import { Head } from '@inertiajs/vue3';

/**
 * Chèn dữ liệu có cấu trúc schema.org vào <head>. Dùng render function để
 * nội dung script là chuỗi thô (Inertia Head không hỗ trợ v-html); escape "<"
 * để chuỗi không thể đóng thẻ script sớm.
 */
export default {
    name: 'JsonLd',
    props: { data: { type: Object, default: null } },
    setup(props) {
        const json = computed(() => (props.data ? JSON.stringify(props.data).replace(/</g, '\\u003c') : ''));
        return () =>
            props.data
                ? h(Head, null, { default: () => [h('script', { type: 'application/ld+json', 'head-key': 'jsonld' }, json.value)] })
                : null;
    },
};
</script>
