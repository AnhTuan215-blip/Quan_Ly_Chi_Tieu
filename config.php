<?php
/**
 * ===================================================================
 * FinTrack VN - Sổ Quản Lý Tài Chính & Chi Tiêu Thông Minh
 * Tệp cấu hình hệ thống & Kết nối cơ sở dữ liệu PDO
 * ===================================================================
 */

// Đặt múi giờ mặc định Việt Nam
date_default_timezone_set('Asia/Ho_Chi_Minh');

// Khởi tạo session an toàn nếu chưa có
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Cấu hình kết nối MySQL PDO
define('DB_HOST', 'localhost');
define('DB_PORT', '3306');
define('DB_NAME', 'fintrack_vn');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

$pdo = null;

try {
    $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
} catch (PDOException $e) {
    // Thử kết nối với 127.0.0.1 nếu localhost gặp vấn đề phân giải socket
    try {
        $dsnFallback = "mysql:host=127.0.0.1;port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
        $pdo = new PDO($dsnFallback, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    } catch (PDOException $ex) {
        $errorMessage = $ex->getMessage();
        // Thông báo lỗi trực quan nếu chưa khởi động MySQL hoặc chưa import database
        die("
        <!DOCTYPE html>
        <html lang=\"vi\">
        <head>
            <meta charset=\"UTF-8\">
            <meta name=\"viewport\" content=\"width=device-width, initial-scale=1.0\">
            <title>Lỗi Kết Nối Cơ Sở Dữ Liệu - FinTrack VN</title>
            <link href=\"https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css\" rel=\"stylesheet\">
            <link href=\"https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap\" rel=\"stylesheet\">
            <style>body { font-family: 'Plus Jakarta Sans', sans-serif; }</style>
        </head>
        <body class=\"bg-gray-900 flex items-center justify-center min-h-screen p-4\">
            <div class=\"bg-gray-800 rounded-2xl shadow-2xl p-8 max-w-lg w-full border border-gray-700 text-gray-200\">
                <div class=\"w-12 h-12 bg-red-500/20 text-red-400 rounded-xl flex items-center justify-center mb-4 text-2xl font-bold\">
                    ⚠️
                </div>
                <h2 class=\"text-xl font-bold text-white mb-2\">Không thể kết nối cơ sở dữ liệu!</h2>
                <p class=\"text-gray-400 text-sm mb-4 leading-relaxed\">
                    Hệ thống không thể kết nối tới cơ sở dữ liệu <code>" . DB_NAME . "</code>. Vui lòng đảm bảo MySQL đang chạy trong XAMPP/WAMP và bạn đã nhập tập tin <strong>database.sql</strong> vào phpMyAdmin.
                </p>
                <div class=\"bg-gray-950 border border-gray-700 rounded-lg p-3 mb-5 text-xs font-mono text-red-400 break-all\">
                    " . htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8') . "
                </div>
                <div class=\"flex items-center space-x-3\">
                    <button onclick=\"window.location.reload()\" class=\"w-full bg-indigo-600 hover:bg-indigo-700 text-white font-medium py-2.5 px-4 rounded-xl transition duration-200 text-sm\">
                        Thử kết nối lại
                    </button>
                </div>
            </div>
        </body>
        </html>
        ");
    }
}

// Tự động kiểm tra và nâng cấp schema bảng nếu cần
if ($pdo instanceof PDO) {
    try {
        // Thêm cột phone vào users nếu chưa có
        $pdo->exec("ALTER TABLE `users` ADD COLUMN `phone` VARCHAR(20) DEFAULT NULL");
    } catch (Exception $e) {}

    try {
        // Tạo bảng password_resets nếu chưa có
        $pdo->exec("CREATE TABLE IF NOT EXISTS `password_resets` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `email` VARCHAR(100) NOT NULL,
            `token` VARCHAR(100) NOT NULL,
            `otp` VARCHAR(10) NOT NULL,
            `expires_at` DATETIME NOT NULL,
            `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX `idx_resets_email` (`email`),
            INDEX `idx_resets_token` (`token`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
    } catch (Exception $e) {}
}

/**
 * Hàm gửi email xác nhận hoặc mã OTP qua Gmail
 * Hỗ trợ mail() PHP thuần và ghi log xem trực tiếp trên localhost nếu chưa cấu hình SMTP
 * 
 * @param string $toEmail Địa chỉ Gmail người nhận
 * @param string $subject Tiêu đề thư
 * @param string $bodyContent Nội dung HTML
 * @return bool
 */
function sendSystemEmail($toEmail, $subject, $bodyContent) {
    $headers = "MIME-Version: 1.0\r\n";
    $headers .= "Content-type: text/html; charset=UTF-8\r\n";
    $headers .= "From: FinTrack VN <support@fintrack.vn>\r\n";
    $headers .= "Reply-To: support@fintrack.vn\r\n";
    $headers .= "X-Mailer: PHP/" . phpversion();

    // Thử gửi qua hàm mail()
    $sent = @mail($toEmail, $subject, $bodyContent, $headers);

    // Lưu phiên gửi email gần nhất để hiển thị thông báo trực quan trên giao diện localhost
    $_SESSION['last_sent_email'] = [
        'to' => $toEmail,
        'subject' => $subject,
        'body' => $bodyContent,
        'time' => date('H:i:s d/m/Y')
    ];

    return true;
}

// ===================================================================
// CÁC HÀM TIỆN ÍCH HỆ THỐNG (HELPER FUNCTIONS)
// ===================================================================

/**
 * Định dạng số tiền sang định dạng tiền tệ Việt Nam (Ví dụ: 1.500.000 đ)
 * 
 * @param float|int|string $amount Số tiền cần định dạng
 * @param string $suffix Hậu tố đơn vị (mặc định: 'đ')
 * @return string Chuỗi tiền tệ đã định dạng
 */
function formatMoney($amount, $suffix = 'đ') {
    $num = (float)$amount;
    return number_format($num, 0, ',', '.') . ' ' . $suffix;
}

/**
 * Alias hỗ trợ tương thích ngược cho formatMoney
 * 
 * @param float|int|string $amount
 * @return string
 */
function formatVND($amount) {
    return formatMoney($amount, 'đ');
}

/**
 * Định dạng ngày sang DD/MM/YYYY (hoặc định dạng mong muốn)
 * 
 * @param string|null $date Chuỗi ngày tháng (YYYY-MM-DD hoặc timestamp)
 * @param string $format Định dạng ngày (mặc định: 'd/m/Y')
 * @return string Ngày đã định dạng
 */
function formatDate($date, $format = 'd/m/Y') {
    if (empty($date)) {
        return '';
    }
    $timestamp = is_numeric($date) ? (int)$date : strtotime($date);
    return $timestamp ? date($format, $timestamp) : $date;
}

/**
 * Lấy tên tháng bằng tiếng Việt (Ví dụ: 'Tháng 10')
 * 
 * @param int|string $month Số thứ tự tháng (1-12)
 * @return string Tên tháng tiếng Việt
 */
function getMonthName($month) {
    $m = (int)$month;
    $monthNames = [
        1  => 'Tháng 1',
        2  => 'Tháng 2',
        3  => 'Tháng 3',
        4  => 'Tháng 4',
        5  => 'Tháng 5',
        6  => 'Tháng 6',
        7  => 'Tháng 7',
        8  => 'Tháng 8',
        9  => 'Tháng 9',
        10 => 'Tháng 10',
        11 => 'Tháng 11',
        12 => 'Tháng 12'
    ];
    return $monthNames[$m] ?? "Tháng $month";
}

/**
 * Lấy thông tin người dùng hiện tại từ Session và Cơ sở dữ liệu
 * 
 * @param PDO|null $pdo Đối tượng kết nối PDO
 * @return array|null Mảng thông tin người dùng hoặc null nếu chưa đăng nhập
 */
function getCurrentUser($pdo = null) {
    if (empty($_SESSION['user_id'])) {
        return null;
    }

    $userId = (int)$_SESSION['user_id'];

    if ($pdo instanceof PDO) {
        try {
            $stmt = $pdo->prepare("SELECT id, username, email, full_name, avatar, membership_type, created_at FROM users WHERE id = :id LIMIT 1");
            $stmt->execute([':id' => $userId]);
            $user = $stmt->fetch();
            if ($user) {
                return $user;
            }
        } catch (PDOException $e) {
            // Khi có sự cố truy vấn, dùng dữ liệu trong session
        }
    }

    return [
        'id'              => $userId,
        'username'        => $_SESSION['username'] ?? '',
        'full_name'       => $_SESSION['full_name'] ?? ($_SESSION['user_name'] ?? 'Người dùng'),
        'email'           => $_SESSION['email'] ?? ($_SESSION['user_email'] ?? ''),
        'membership_type' => $_SESSION['membership_type'] ?? ($_SESSION['user_role'] ?? 'free'),
        'avatar'          => $_SESSION['avatar'] ?? null
    ];
}

/**
 * Kiểm tra xem người dùng hiện tại đã đăng nhập hay chưa
 * 
 * @return bool True nếu đã đăng nhập, ngược lại False
 */
function isLoggedIn() {
    return !empty($_SESSION['user_id']);
}

/**
 * Bắt buộc đăng nhập để truy cập trang (chuyển hướng sang auth.php nếu chưa đăng nhập)
 */
function requireAuth() {
    if (!isLoggedIn()) {
        redirect('auth.php');
    }
}

/**
 * Alias hỗ trợ tương thích ngược cho requireAuth
 */
function checkAuth() {
    requireAuth();
}

/**
 * Chuyển hướng trình duyệt tới URL chỉ định
 * 
 * @param string $url Đường dẫn cần chuyển đến
 */
function redirect($url) {
    header("Location: " . $url);
    exit();
}

/**
 * Làm sạch dữ liệu đầu vào chống tấn công XSS
 * 
 * @param mixed $input Chuỗi hoặc mảng chuỗi
 * @return mixed Chuỗi hoặc mảng đã được làm sạch
 */
function sanitize($input) {
    if (is_array($input)) {
        return array_map('sanitize', $input);
    }
    return htmlspecialchars(trim((string)$input), ENT_QUOTES, 'UTF-8');
}
