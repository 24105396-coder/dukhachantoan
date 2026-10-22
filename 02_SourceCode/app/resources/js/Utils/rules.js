// Mẫu kiểm tra HTML5 phía máy khách, sao chép từ FormRequest tương ứng (máy chủ vẫn là nơi kiểm cuối)
export const PHONE_PATTERN = '\\+?[0-9]{9,15}';
export const PASSWORD_PATTERN = '(?=.*[a-z])(?=.*[A-Z])(?=.*[0-9]).{8,}';
export const SLUG_PATTERN = '[a-z0-9]+(-[a-z0-9]+)*';
export const YEAR_MIN = -3000;
export const YEAR_MAX = 2100;

// Dạng RegExp neo hai đầu cho useClientValidation (cùng nguồn với thuộc tính pattern ở trên)
const anchored = (p) => new RegExp(`^(?:${p})$`);
export const PHONE_RE = anchored(PHONE_PATTERN);
export const PASSWORD_RE = anchored(PASSWORD_PATTERN);
export const SLUG_RE = anchored(SLUG_PATTERN);
// Máy chủ bỏ khoảng trắng, dấu chấm, gạch ngang khỏi số điện thoại đặt lịch trước khi kiểm
export const stripPhone = (s) => s.replace(/[\s.-]/g, '');
