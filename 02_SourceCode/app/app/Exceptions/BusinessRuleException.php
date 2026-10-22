<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Vi phạm quy tắc nghiệp vụ (chuyển trạng thái không hợp lệ, hành trình không sửa được…).
 * bootstrap/app.php: web → quay lại trang với flash error + lỗi field; API → 422 JSON {message}.
 */
class BusinessRuleException extends RuntimeException
{
    public function __construct(string $message, public readonly string $field = 'status')
    {
        parent::__construct($message);
    }
}
