// Quy đổi trạng thái yêu cầu (sự cố, check-in cần hỗ trợ) về 3 bước Đã gửi → Đang xử lý → Đã xử lý

/** Bước theo trạng thái duyệt sự cố. */
const INCIDENT = {
    pending: { step: 1, key: 'pending', icon: 'clock', callout: 'callout-2' },
    verified: { step: 3, key: 'done', icon: 'circle-check', callout: 'callout-0' },
    rejected: { step: 3, key: 'rejected', icon: 'circle-x', callout: '' },
};

/** Bước theo trạng thái phản hồi check-in của cơ quan. */
const CHECKIN = {
    open: { step: 1, key: 'pending', icon: 'clock', callout: 'callout-2' },
    in_progress: { step: 2, key: 'in_progress', icon: 'refresh', callout: 'callout-1' },
    closed: { step: 3, key: 'done', icon: 'circle-check', callout: 'callout-0' },
};

/**
 * Trạng thái hiển thị của một yêu cầu.
 * @param {'incident'|'checkin'} type loại yêu cầu
 * @param {object} item bản ghi RequestItem
 * @returns {{step: number, key: string, icon: string, callout: string}} bước 1–3, khóa nhãn ui.requests.status.*, icon, lớp viền trái
 */
export function requestState(type, item) {
    if (type === 'incident') return INCIDENT[item?.status] || INCIDENT.pending;
    return CHECKIN[item?.response_status] || CHECKIN.open;
}
