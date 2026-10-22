import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';

/** Cờ quyền phía giao diện (chỉ ẩn/hiện; máy chủ vẫn kiểm tra quyền). */
export function usePermissions() {
    const page = usePage();
    const user = computed(() => page.props.auth?.user || null);
    const role = computed(() => user.value?.role || null);
    const isAdmin = computed(() => role.value === 'admin');
    const isStaff = computed(() => ['admin', 'manager'].includes(role.value));
    const canAccessAdmin = computed(() => ['admin', 'manager', 'contributor'].includes(role.value));
    // Contributor không vào được trang tổng quan (/quan-tri) nên trỏ thẳng tới tuyến tham quan
    const adminHome = computed(() => (isStaff.value ? '/quan-tri' : '/quan-tri/tuyen-tham-quan'));
    return { user, role, isAdmin, isStaff, canAccessAdmin, adminHome };
}
