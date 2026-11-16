# FinTrack VN - Sổ Quản Lý Tài Chính & Chi Tiêu Cá Nhân Thông Minh

Ứng dụng web quản lý thu chi cá nhân hiện đại, trực quan và bảo mật được xây dựng bằng **PHP thuần, MySQL, HTML5, CSS3 (Tailwind CSS) và Vanilla JavaScript**.

---

##  Tính Năng Nổi Bật

1. **Tổng Quan Dòng Tiền (Dashboard)**:
   - Theo dõi tổng số dư khả dụng, tổng thu nhập, tổng chi tiêu và hạn mức ngân sách tháng.
   - Biểu đồ xu hướng dòng tiền (Cash Flow) trực quan qua Chart.js.
   - Hệ thống cảnh báo thông minh khi danh mục chi tiêu chạm ngưỡng ngân sách.
   - Lọc và tìm kiếm giao dịch theo thời gian thực (AJAX).

2. **Lịch Giao Dịch & Chi Tiêu Hàng Ngày (Calendar)**:
   - Khung lịch tài chính theo dõi biến động dòng tiền từng ngày.
   - Nhận diện trực quan ngày thu (+) / ngày chi (-) bằng các điểm màu chỉ dẫn.
   - Xem chi tiết từng giao dịch trong ngày và thêm nhanh giao dịch cho ngày được chọn.

3. **Thêm Giao Dịch Thu / Chi (Add Transaction)**:
   - Giao diện nhập liệu tối ưu UX: nút chọn nhanh mệnh giá (+50k, +100k, +500k,...).
   - Phân loại danh mục thu nhập và chi tiêu.
   - Lựa chọn nguồn tiền / ví thanh toán và cập nhật số dư tức thời.
   - Dự báo tác động ngân sách trực tiếp trước khi lưu.
   - Đính kèm biên lai / hóa đơn chi tiêu.

4. **Báo Cáo & Phân Tích Chuyên Sâu (Reports & Analytics)**:
   - Biểu đồ tròn (Doughnut Chart) phân tích cơ cấu chi tiêu theo tỷ lệ %.
   - Bảng kiểm soát tiến độ ngân sách theo từng danh mục.
   - Biểu đồ cột so sánh dòng tiền thu/chi 6 tháng gần nhất.
   - Trợ lý FinTrack AI gợi ý thói quen chi tiêu thông minh.

5. **Cài Đặt & Cấu Hình Hệ Thống (Settings)**:
   - Quản lý danh mục thu & chi (Thêm, sửa, xóa danh mục).
   - Thiết lập hạn mức ngân sách tháng và cấu hình ngưỡng cảnh báo vàng (80%) / đỏ (100%).
   - Quản lý tài khoản ngân hàng, ví tiền mặt, ví điện tử.
   - Tùy chỉnh định dạng tiền tệ, ngày tháng.

6. **Hệ Thống Xác Thực & Bảo Mật (Authentication)**:
   - Đăng nhập linh hoạt bằng: Tên đăng nhập, Gmail hoặc Số điện thoại.
   - Nút đăng nhập 1-click cho tài khoản Demo.
   - Form đăng ký nhanh với xác nhận qua Gmail.
   - Tính năng Quên mật khẩu & Khôi phục an toàn bằng mã OTP 6 số qua Gmail.
   - Phân vùng dữ liệu độc lập: mỗi người dùng mới bắt đầu với không gian tài chính sạch riêng biệt (0 đ).

---

## 🛠️ Công Nghệ Sử Dụng

- **Backend**: PHP 8.x (PDO MySQL, Prepared Statements chống SQL Injection)
- **Database**: MySQL / MariaDB (`fintrack_vn`)
- **Frontend**: HTML5, CSS3, Tailwind CSS CDN, Font Awesome 6, Chart.js
- **Mã hóa**: PHP `password_hash()` (Bcrypt)

---

## 🚀 Hướng Dẫn Cài Đặt & Chạy Trên XAMPP

1. **Khởi động XAMPP**:
   - Bật dịch vụ **Apache** và **MySQL** trong XAMPP Control Panel.

2. **Cài đặt Cơ sở dữ liệu**:
   - Truy cập phpMyAdmin: `http://localhost/phpmyadmin`
   - Tạo cơ sở dữ liệu tên: `fintrack_vn`
   - Import tệp `database.sql` vào cơ sở dữ liệu `fintrack_vn`.

3. **Cấu hình kết nối**:
   - Kiểm tra tệp `config.php`:
     ```php
     define('DB_HOST', 'localhost');
     define('DB_PORT', '3306');
     define('DB_NAME', 'fintrack_vn');
     define('DB_USER', 'root');
     define('DB_PASS', '');
     ```

4. **Truy cập ứng dụng**:
   - Mở trình duyệt và truy cập: `http://localhost/QL%20chi%20tieu/` hoặc `http://localhost/QL%20chi%20tieu/auth.php`

---

## 🔑 Tài Khoản Trải Nghiệm Mẫu

- **Tài khoản Demo Admin (có sẵn dữ liệu mẫu T10/2024)**:
  - Tên đăng nhập: `admin`
  - Mật khẩu: `admin123`
- **Tài khoản Mới**: Bạn có thể bấm vào **Đăng ký** để tạo tài khoản riêng biệt hoàn toàn mới.
