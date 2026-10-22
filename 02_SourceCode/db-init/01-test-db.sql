-- Chạy một lần khi khởi tạo volume MySQL: tạo CSDL kiểm thử cho PHPUnit và cấp quyền cho tài khoản ứng dụng.
CREATE DATABASE IF NOT EXISTS disanso_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
GRANT ALL PRIVILEGES ON disanso_test.* TO 'disanso'@'%';
FLUSH PRIVILEGES;
