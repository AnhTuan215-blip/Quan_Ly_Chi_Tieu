-- ===================================================================
-- CƠ SỞ DỮ LIỆU ỨNG DỤNG QUẢN LÝ CHI TIÊU CÁ NHÂN: FINTRACK VN
-- Hệ thống: FinTrack VN - Sổ Quản Lý Tài Chính & Chi Tiêu Thông Minh
-- Phiên bản: 1.0.0
-- Mã hóa: UTF-8 (utf8mb4_unicode_ci)
-- ===================================================================

CREATE DATABASE IF NOT EXISTS `fintrack_vn` 
DEFAULT CHARACTER SET utf8mb4 
COLLATE utf8mb4_unicode_ci;

USE `fintrack_vn`;

-- Tắt kiểm tra khóa ngoại tạm thời để tránh xung đột thứ tự tạo bảng
SET FOREIGN_KEY_CHECKS = 0;

-- -------------------------------------------------------------------
-- 1. Bảng: users (Thông tin người dùng hệ thống)
-- -------------------------------------------------------------------
DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `username` VARCHAR(50) NOT NULL UNIQUE,
  `email` VARCHAR(100) NOT NULL UNIQUE,
  `phone` VARCHAR(20) DEFAULT NULL,
  `password` VARCHAR(255) NOT NULL COMMENT 'Mật khẩu đã được mã hóa bằng password_hash()',
  `full_name` VARCHAR(100) NOT NULL,
  `avatar` VARCHAR(255) DEFAULT NULL,
  `membership_type` ENUM('free', 'premium') NOT NULL DEFAULT 'free',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_users_username` (`username`),
  INDEX `idx_users_email` (`email`),
  INDEX `idx_users_phone` (`phone`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Danh sách người dùng';

-- -------------------------------------------------------------------
-- Bảng: password_resets (Mã xác thực OTP / Khôi phục mật khẩu qua Gmail)
-- -------------------------------------------------------------------
DROP TABLE IF EXISTS `password_resets`;
CREATE TABLE `password_resets` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `email` VARCHAR(100) NOT NULL,
  `token` VARCHAR(100) NOT NULL,
  `otp` VARCHAR(10) NOT NULL,
  `expires_at` DATETIME NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_resets_email` (`email`),
  INDEX `idx_resets_token` (`token`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Quản lý mã khôi phục mật khẩu';
-- 2. Bảng: categories (Danh mục chi tiêu và thu nhập)
-- -------------------------------------------------------------------
DROP TABLE IF EXISTS `categories`;
CREATE TABLE `categories` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `name` VARCHAR(100) NOT NULL,
  `type` ENUM('chi_tieu', 'thu_nhap') NOT NULL COMMENT 'chi_tieu: Chi phí, thu_nhap: Thu nhập',
  `icon` VARCHAR(50) DEFAULT 'fa-folder',
  `color` VARCHAR(7) DEFAULT '#4F59E8',
  `budget_limit` DECIMAL(15,0) NOT NULL DEFAULT 0 COMMENT 'Hạn mức ngân sách hàng tháng cho danh mục này (VND)',
  `description` TEXT DEFAULT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_cat_user_type` (`user_id`, `type`),
  INDEX `idx_cat_is_active` (`is_active`),
  CONSTRAINT `fk_categories_user` FOREIGN KEY (`user_id`) 
    REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Danh mục phân loại thu chi';

-- -------------------------------------------------------------------
-- 3. Bảng: payment_methods (Phương thức thanh toán / Ví / Tài khoản)
-- -------------------------------------------------------------------
DROP TABLE IF EXISTS `payment_methods`;
CREATE TABLE `payment_methods` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `name` VARCHAR(100) NOT NULL COMMENT 'Ví dụ: Techcombank Visa, Tiền mặt, Ví MoMo',
  `type` ENUM('bank', 'cash', 'ewallet', 'credit_card') NOT NULL,
  `balance` DECIMAL(15,0) NOT NULL DEFAULT 0 COMMENT 'Số dư khả dụng hiện tại (VND)',
  `is_primary` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 nếu là phương thức mặc định',
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_pm_user` (`user_id`),
  INDEX `idx_pm_active` (`is_active`),
  CONSTRAINT `fk_pm_user` FOREIGN KEY (`user_id`) 
    REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Tài khoản, ví và phương thức thanh toán';

-- -------------------------------------------------------------------
-- 4. Bảng: transactions (Giao dịch thu / chi chi tiết)
-- -------------------------------------------------------------------
DROP TABLE IF EXISTS `transactions`;
CREATE TABLE `transactions` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `category_id` INT NOT NULL,
  `payment_method_id` INT DEFAULT NULL,
  `type` ENUM('chi_tieu', 'thu_nhap') NOT NULL,
  `amount` DECIMAL(15,0) NOT NULL COMMENT 'Số tiền giao dịch (VND)',
  `description` VARCHAR(255) DEFAULT NULL COMMENT 'Mô tả ngắn gọn',
  `note` TEXT DEFAULT NULL COMMENT 'Ghi chú chi tiết giao dịch',
  `transaction_date` DATE NOT NULL COMMENT 'Ngày phát sinh giao dịch',
  `transaction_time` TIME DEFAULT NULL COMMENT 'Thời gian phát sinh',
  `is_recurring` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Giao dịch lặp lại định kỳ',
  `recurring_period` ENUM('daily', 'weekly', 'monthly', 'yearly') DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_tx_user_date` (`user_id`, `transaction_date`),
  INDEX `idx_tx_type` (`type`),
  INDEX `idx_tx_category` (`category_id`),
  INDEX `idx_tx_payment_method` (`payment_method_id`),
  CONSTRAINT `fk_tx_user` FOREIGN KEY (`user_id`) 
    REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_tx_category` FOREIGN KEY (`category_id`) 
    REFERENCES `categories` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_tx_payment_method` FOREIGN KEY (`payment_method_id`) 
    REFERENCES `payment_methods` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Lịch sử giao dịch chi tiêu và thu nhập';

-- -------------------------------------------------------------------
-- 5. Bảng: budgets (Ngân sách chi tiêu theo tháng)
-- -------------------------------------------------------------------
DROP TABLE IF EXISTS `budgets`;
CREATE TABLE `budgets` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `month` INT NOT NULL COMMENT 'Tháng (1 - 12)',
  `year` INT NOT NULL COMMENT 'Năm (ví dụ 2024)',
  `total_budget` DECIMAL(15,0) NOT NULL DEFAULT 25000000 COMMENT 'Tổng ngân sách dự trù tháng (VND)',
  `warning_threshold` INT NOT NULL DEFAULT 80 COMMENT 'Mức phần trăm cảnh báo vượt ngân sách (%)',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `unique_budget` (`user_id`, `month`, `year`),
  INDEX `idx_budget_user_time` (`user_id`, `year`, `month`),
  CONSTRAINT `fk_budgets_user` FOREIGN KEY (`user_id`) 
    REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Kế hoạch ngân sách tháng';

-- -------------------------------------------------------------------
-- 6. Bảng: settings (Cấu hình cài đặt cá nhân của người dùng)
-- -------------------------------------------------------------------
DROP TABLE IF EXISTS `settings`;
CREATE TABLE `settings` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `currency` VARCHAR(10) NOT NULL DEFAULT 'VND',
  `number_format` VARCHAR(20) NOT NULL DEFAULT '100.000.000',
  `date_format` VARCHAR(20) NOT NULL DEFAULT 'DD/MM/YYYY',
  `theme` ENUM('light', 'dark', 'system') NOT NULL DEFAULT 'light',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `unique_user_settings` (`user_id`),
  CONSTRAINT `fk_settings_user` FOREIGN KEY (`user_id`) 
    REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Cài đặt cá nhân';

-- Bật lại kiểm tra khóa ngoại
SET FOREIGN_KEY_CHECKS = 1;


-- ===================================================================
-- DỮ LIỆU MẪU MẶC ĐỊNH (SAMPLE DATA)
-- ===================================================================

-- 1. Người dùng mặc định: Nguyễn Văn An (admin / admin123)
-- Hash '$2y$10$wnJDy2t8/4t8317aEpbmB.5l2pEYxJVCdI5pRr1avpLXwfS4kujue' tương ứng mật khẩu 'admin123'
INSERT INTO `users` (`id`, `username`, `email`, `password`, `full_name`, `avatar`, `membership_type`, `created_at`) 
VALUES (
  1, 
  'admin', 
  'an.nguyen@email.com', 
  '$2y$10$wnJDy2t8/4t8317aEpbmB.5l2pEYxJVCdI5pRr1avpLXwfS4kujue', 
  'Nguyễn Văn An', 
  'https://images.unsplash.com/photo-1535713875002-d1d0cf377fde?auto=format&fit=crop&w=200&q=80', 
  'premium', 
  '2024-10-01 00:00:00'
);

-- 2. Cài đặt mặc định cho người dùng ID = 1
INSERT INTO `settings` (`user_id`, `currency`, `number_format`, `date_format`, `theme`) 
VALUES (1, 'VND', '100.000.000', 'DD/MM/YYYY', 'light');

-- 3. Ngân sách tháng 10/2024 cho người dùng ID = 1
INSERT INTO `budgets` (`user_id`, `month`, `year`, `total_budget`, `warning_threshold`) 
VALUES (1, 10, 2024, 25000000, 80);

-- 4. Danh mục mẫu cho người dùng ID = 1
INSERT INTO `categories` (`id`, `user_id`, `name`, `type`, `icon`, `color`, `budget_limit`, `description`, `is_active`) VALUES
-- Danh mục chi tiêu
(1, 1, 'Ăn uống', 'chi_tieu', 'fa-utensils', '#F97316', 8000000, 'Chi phí ăn sáng, trưa, tối, cà phê và tiệc tùng', 1),
(2, 1, 'Mua sắm', 'chi_tieu', 'fa-shopping-bag', '#3B82F6', 4000000, 'Quần áo, phụ kiện, thiết bị công nghệ và đồ gia dụng', 1),
(3, 1, 'Di chuyển', 'chi_tieu', 'fa-car', '#4F59E8', 2500000, 'Xăng xe, bảo dưỡng định kỳ, gửi xe, Grab, taxi', 1),
(4, 1, 'Hóa đơn & Dịch vụ', 'chi_tieu', 'fa-file-invoice', '#089182', 3500000, 'Tiền điện sinh hoạt, nước máy, cáp internet, điện thoại di động', 1),
(5, 1, 'Giải trí', 'chi_tieu', 'fa-gamepad', '#9333EA', 2000000, 'Xem phim rạp CGV, trò chơi, dã ngoại, hòa nhạc', 1),
(6, 1, 'Giáo dục', 'chi_tieu', 'fa-book', '#06B6D4', 2000000, 'Sách kinh tế, kỹ năng mềm, các khóa học online chứng chỉ', 1),
(7, 1, 'Y tế & Sức khỏe', 'chi_tieu', 'fa-heartbeat', '#EF4444', 1500000, 'Thực phẩm chức năng, thuốc men, phòng tập gym thể thao', 1),
(8, 1, 'Nhà cửa', 'chi_tieu', 'fa-home', '#F59E0B', 1500000, 'Tiền bảo trì căn hộ, sửa chữa, mua sắm đồ đạc nội thất mới', 1),
-- Danh mục thu nhập
(9, 1, 'Lương thưởng', 'thu_nhap', 'fa-briefcase', '#A96CA9', 0, 'Lương cố định nhận định kỳ hàng tháng, thưởng theo quý và dự án', 1),
(10, 1, 'Đầu tư sinh lời', 'thu_nhap', 'fa-chart-line', '#009488', 0, 'Cổ tức chứng khoán, tiền lãi tiết kiệm ngân hàng, quỹ mở', 1);

-- 5. Phương thức thanh toán mẫu cho người dùng ID = 1
INSERT INTO `payment_methods` (`id`, `user_id`, `name`, `type`, `balance`, `is_primary`, `is_active`) VALUES
(1, 1, 'Techcombank Visa Signature', 'bank', 42850000, 1, 1),
(2, 1, 'MB Bank', 'bank', 15000000, 0, 1),
(3, 1, 'Tiền mặt', 'cash', 5000000, 0, 1),
(4, 1, 'Ví MoMo', 'ewallet', 2800000, 0, 1);

-- 6. Giao dịch mẫu trong Tháng 10/2024 (16 giao dịch gồm cả thu nhập và chi tiêu)
INSERT INTO `transactions` (`id`, `user_id`, `category_id`, `payment_method_id`, `type`, `amount`, `description`, `note`, `transaction_date`, `transaction_time`, `is_recurring`, `recurring_period`) VALUES
(1,  1, 9,  1, 'thu_nhap', 45000000, 'Lương tháng 09/2024', 'Chuyển khoản từ Công ty Cổ phần Công nghệ FinTrack', '2024-10-01', '08:30:00', 1, 'monthly'),
(2,  1, 1,  4, 'chi_tieu', 85000,    'Ăn sáng Phở Thìn & Cà phê', 'Bát phở bò tái lăn và cafe đen đá', '2024-10-02', '07:45:00', 0, NULL),
(3,  1, 4,  1, 'chi_tieu', 1450000,  'Thanh toán điện nước tháng 09', 'Hóa đơn tiền điện EVN và nước sạch Hà Nội', '2024-10-03', '12:15:00', 0, NULL),
(4,  1, 2,  1, 'chi_tieu', 799000,   'Mua áo sơ mi Uniqlo', 'Chi nhánh Vincom Center Metropolis', '2024-10-05', '18:20:00', 0, NULL),
(5,  1, 3,  3, 'chi_tieu', 650000,   'Đổ xăng xe ô tô Petrolimex', 'Trạm xăng số 18 đường Láng Hạ', '2024-10-07', '09:10:00', 0, NULL),
(6,  1, 10, 2, 'thu_nhap', 4200000,  'Lãi cổ tức quỹ đầu tư', 'Chốt lời danh mục cổ tức quý 3 MB Capital', '2024-10-10', '11:30:00', 0, NULL),
(7,  1, 1,  1, 'chi_tieu', 1280000,  'Ăn tối nhà hàng Haidilao Hotpot', 'Tiệc lẩu cuối tuần cùng người thân', '2024-10-12', '19:30:00', 0, NULL),
(8,  1, 7,  2, 'chi_tieu', 2100000,  'Gói tập California Fitness 3 tháng', 'Gia hạn gói tập gym và yoga chuyên sâu', '2024-10-14', '14:00:00', 0, NULL),
(9,  1, 6,  4, 'chi_tieu', 450000,   'Mua sách tài chính cá nhân Tiki', 'Sách tâm lý học về tiền và quản lý dòng tiền', '2024-10-15', '10:00:00', 0, NULL),
(10, 1, 5,  4, 'chi_tieu', 320000,   'Vé xem phim CGV IMAX & bắp nước', 'Phim khoa học viễn tưởng cuối tuần', '2024-10-18', '20:45:00', 0, NULL),
(11, 1, 2,  1, 'chi_tieu', 2500000,  'Quà tặng Ngày Phụ nữ Việt Nam 20/10', 'Bộ quà mỹ phẩm và hoa tươi tặng mẹ và vợ', '2024-10-20', '16:30:00', 0, NULL),
(12, 1, 4,  4, 'chi_tieu', 275000,   'Cước Internet cáp quang Viettel', 'Gói NetPlus tốc độ cao tháng 10/2024', '2024-10-22', '08:15:00', 1, 'monthly'),
(13, 1, 9,  1, 'thu_nhap', 8000000,  'Thưởng hiệu suất dự án Q3', 'Khen thưởng hoàn thành vượt chỉ tiêu dự án', '2024-10-25', '15:00:00', 0, NULL),
(14, 1, 3,  3, 'chi_tieu', 850000,   'Bảo dưỡng xe máy Honda định kỳ', 'Thay dầu máy Motul và bảo trì nhông xích', '2024-10-27', '10:20:00', 0, NULL),
(15, 1, 8,  4, 'chi_tieu', 680000,   'Mua đèn bàn làm việc và decor', 'Đèn chống cận thị và phụ kiện bàn làm việc', '2024-10-29', '17:00:00', 0, NULL),
(16, 1, 1,  1, 'chi_tieu', 950000,   'Tiệc liên hoan sinh nhật & tổng kết tháng', 'Bữa trưa gặp mặt đội nhóm kinh doanh', '2024-10-31', '12:00:00', 0, NULL);
