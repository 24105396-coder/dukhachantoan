import manifest from '@/Data/images.json';
import { useT } from '@/Composables/useT';

/** Chỉ nhận đường dẫn ảnh cục bộ (/images/...) để không nạp ảnh ngoài CSP và tránh URL lạ từ manifest. */
const isLocalFile = (file) => typeof file === 'string' && /^\/(?!\/)/.test(file);
const isHttpUrl = (url) => typeof url === 'string' && /^https?:\/\//i.test(url);

/**
 * Tra ảnh trong manifest `Data/images.json` (do dka-data-author tạo, có giấy phép dùng lại).
 * Mỗi hàm trả `{ src, alt, credit, license, sourceUrl }` hoặc null khi thiếu khóa – nơi dùng phải có fallback.
 */
export function useImages() {
    const { locale } = useT();

    /**
     * Đọc một mục ảnh theo nhóm và khóa.
     * @param {string} group hero | hazards | destinations
     * @param {string} key mã/slug trong nhóm
     * @returns {{src: string, alt: string, credit: string, license: string, sourceUrl: string|null}|null}
     */
    function resolve(group, key) {
        if (!key) return null;
        const entry = manifest?.[group]?.[key];
        if (!entry || !isLocalFile(entry.file)) return null;
        const alt = typeof entry.alt === 'string' ? entry.alt : entry.alt?.[locale.value] || entry.alt?.vi || entry.alt?.en || '';
        const credit = [entry.credit, entry.license].filter(Boolean).join(' · ');
        return {
            src: entry.file,
            alt,
            credit,
            license: entry.license || '',
            sourceUrl: isHttpUrl(entry.source_url) ? entry.source_url : null,
        };
    }

    /** Ảnh đầu trang: home | about | auth. */
    const hero = (key) => resolve('hero', key);
    /** Ảnh theo mã loại hiểm họa (hazard_types.code). */
    const hazard = (code) => resolve('hazards', code);
    /** Ảnh điểm đến theo slug (destinations.slug). */
    const destination = (slug) => resolve('destinations', slug);

    return { hero, hazard, destination };
}
