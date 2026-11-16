<?php
/**
 * ===================================================================
 * FinTrack VN - Sổ Quản Lý Tài Chính & Chi Tiêu Thông Minh
 * Xử lý Đăng xuất người dùng (Logout Handler)
 * ===================================================================
 */

require_once __DIR__ . '/config.php';

// Xóa toàn bộ biến lưu trong session
$_SESSION = [];

// Xóa session cookie trên trình duyệt người dùng nếu có sử dụng cookie
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
}

// Hủy hoàn toàn phiên làm việc
session_destroy();

// Chuyển hướng người dùng về trang đăng nhập
redirect('auth.php');
