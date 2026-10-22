// Mẫu kiểm tra HTML5 phía máy khách, sao chép từ FormRequest tương ứng (máy chủ vẫn là nơi kiểm cuối)
export const PHONE_PATTERN = '\\+?[0-9]{9,15}';
export const PASSWORD_PATTERN = '(?=.*[a-z])(?=.*[A-Z])(?=.*[0-9]).{8,}';
export const NATIONALITY_PATTERN = '[A-Za-z]{2}';

// Dạng RegExp neo hai đầu cho useClientValidation (cùng nguồn với thuộc tính pattern ở trên)
const anchored = (p) => new RegExp(`^(?:${p})$`);
export const PHONE_RE = anchored(PHONE_PATTERN);
export const PASSWORD_RE = anchored(PASSWORD_PATTERN);
// Máy chủ bỏ khoảng trắng, dấu chấm, gạch ngang khỏi số điện thoại trước khi kiểm
export const stripPhone = (s) => s.replace(/[\s.-]/g, '');
