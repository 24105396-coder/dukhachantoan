import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';

export const ROLES = ['admin', 'agency', 'partner', 'traveler'];

/** Cờ quyền phía giao diện (chỉ ẩn/hiện; máy chủ vẫn kiểm tra bằng middleware role và Policy). */
export function usePermissions() {
    const page = usePage();
    const user = computed(() => page.props.auth?.user || null);
    const role = computed(() => user.value?.role || null);
    const isAdmin = computed(() => role.value === 'admin');
    const isAgency = computed(() => role.value === 'agency');
    const isPartner = computed(() => role.value === 'partner');
    const isTraveler = computed(() => role.value === 'traveler');
    // agency + admin vào khu /quan-tri
    const isStaff = computed(() => isAdmin.value || isAgency.value);
    const canAccessAdmin = isStaff;
    const canManageTrips = computed(() => isPartner.value || isTraveler.value || isStaff.value);
    const adminHome = '/quan-tri';
    const unread = computed(() => Number(user.value?.unread_notifications || 0));
    return { user, role, isAdmin, isAgency, isPartner, isTraveler, isStaff, canAccessAdmin, canManageTrips, adminHome, unread };
}
