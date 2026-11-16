<?php
/**
 * FinTrack VN - API Cài đặt Hệ thống & Tùy chọn Người dùng
 * Đường dẫn: api/settings.php
 * Hỗ trợ các phương thức: GET, POST
 */

// Đặt header JSON
header('Content-Type: application/json; charset=utf-8');

ob_start();

require_once __DIR__ . '/../config.php';

// Kiểm tra và thiết lập phiên làm việc
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Vui lòng đăng nhập!'], JSON_UNESCAPED_UNICODE);
    exit;
}

$userId = (int)$_SESSION['user_id'];

// Khởi tạo bảng settings trong CSDL
function initSettingsTable($pdo, $userId) {
    if (!$pdo instanceof PDO) return;
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS `settings` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `user_id` INT NOT NULL,
            `currency` VARCHAR(10) NOT NULL DEFAULT 'VND',
            `number_format` VARCHAR(20) NOT NULL DEFAULT '100.000.000',
            `date_format` VARCHAR(20) NOT NULL DEFAULT 'DD/MM/YYYY',
            `theme` ENUM('light', 'dark', 'system') NOT NULL DEFAULT 'light',
            `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY `unique_user_settings` (`user_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

        $stmt = $pdo->prepare("SELECT COUNT(*) FROM `settings` WHERE `user_id` = :uid");
        $stmt->execute([':uid' => $userId]);
        if ((int)$stmt->fetchColumn() === 0) {
            $insert = $pdo->prepare("INSERT INTO `settings` (`user_id`, `currency`, `number_format`, `date_format`, `theme`) VALUES (:uid, 'VND', '100.000.000', 'DD/MM/YYYY', 'light')");
            $insert->execute([':uid' => $userId]);
        }
    } catch (PDOException $e) {}
}

if ($pdo instanceof PDO) {
    initSettingsTable($pdo, $userId);
}

// Dữ liệu mặc định trong Session fallback
if (!isset($_SESSION['mock_settings'])) {
    $_SESSION['mock_settings'] = [
        'currency' => 'VND',
        'number_format' => '100.000.000',
        'date_format' => 'DD/MM/YYYY',
        'theme' => 'light',
        'last_updated' => date('Y-m-d H:i:s'),
        'updated_by' => $_SESSION['full_name'] ?? 'Nguyễn Văn An'
    ];
}

$rawInput = file_get_contents('php://input');
$jsonInput = json_decode($rawInput, true) ?? [];
$requestData = array_merge($_POST, $jsonInput);

$method = $_SERVER['REQUEST_METHOD'];
if ($method === 'POST' && isset($requestData['_method']) && strtoupper($requestData['_method']) === 'GET') {
    $method = 'GET';
}

ob_clean();

switch ($method) {
    // -------------------------------------------------------------
    // GET: Lấy cấu hình hiện tại của người dùng
    // -------------------------------------------------------------
    case 'GET':
        $settingsData = null;

        if ($pdo instanceof PDO) {
            try {
                $stmt = $pdo->prepare("SELECT currency, number_format, date_format, theme, updated_at FROM `settings` WHERE `user_id` = :uid LIMIT 1");
                $stmt->execute([':uid' => $userId]);
                $row = $stmt->fetch();

                if ($row) {
                    $settingsData = [
                        'currency' => $row['currency'] ?: 'VND',
                        'number_format' => $row['number_format'] ?: '100.000.000',
                        'date_format' => $row['date_format'] ?: 'DD/MM/YYYY',
                        'theme' => $row['theme'] ?: 'light',
                        'last_updated' => $row['updated_at'] ?: date('Y-m-d H:i:s'),
                        'updated_by' => $_SESSION['full_name'] ?? 'Nguyễn Văn An'
                    ];
                }
            } catch (PDOException $e) {}
        }

        if (!$settingsData) {
            $settingsData = $_SESSION['mock_settings'];
        }

        echo json_encode([
            'success' => true,
            'data' => $settingsData
        ], JSON_UNESCAPED_UNICODE);
        exit;

    // -------------------------------------------------------------
    // POST: Cập nhật cấu hình
    // -------------------------------------------------------------
    case 'POST':
    case 'PUT':
        $currency = trim($requestData['currency'] ?? 'VND');
        $numberFormat = trim($requestData['number_format'] ?? '100.000.000');
        $dateFormat = trim($requestData['date_format'] ?? 'DD/MM/YYYY');
        $theme = trim($requestData['theme'] ?? 'light');

        // Chuẩn hóa theme
        if (!in_array($theme, ['light', 'dark', 'system'])) {
            $theme = 'light';
        }

        // Cập nhật CSDL nếu có PDO
        if ($pdo instanceof PDO) {
            try {
                $stmt = $pdo->prepare("INSERT INTO `settings` (`user_id`, `currency`, `number_format`, `date_format`, `theme`, `updated_at`) 
                    VALUES (:uid, :cur, :nf, :df, :th, NOW()) 
                    ON DUPLICATE KEY UPDATE 
                        `currency` = VALUES(`currency`), 
                        `number_format` = VALUES(`number_format`), 
                        `date_format` = VALUES(`date_format`), 
                        `theme` = VALUES(`theme`), 
                        `updated_at` = NOW()");
                $stmt->execute([
                    ':uid' => $userId,
                    ':cur' => $currency,
                    ':nf'  => $numberFormat,
                    ':df'  => $dateFormat,
                    ':th'  => $theme
                ]);
            } catch (PDOException $e) {
                // Tiếp tục cập nhật session
            }
        }

        // Cập nhật Session
        $_SESSION['mock_settings'] = [
            'currency' => $currency,
            'number_format' => $numberFormat,
            'date_format' => $dateFormat,
            'theme' => $theme,
            'last_updated' => date('Y-m-d H:i:s'),
            'updated_by' => $_SESSION['full_name'] ?? 'Nguyễn Văn An'
        ];

        echo json_encode([
            'success' => true,
            'message' => 'Cài đặt hệ thống đã được lưu và áp dụng thành công!',
            'data' => $_SESSION['mock_settings']
        ], JSON_UNESCAPED_UNICODE);
        exit;

    default:
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Phương thức HTTP không được hỗ trợ!'], JSON_UNESCAPED_UNICODE);
        exit;
}
