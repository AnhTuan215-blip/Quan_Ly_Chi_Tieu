<?php
/**
 * FinTrack VN - API Tạo Danh Mục Mới Nhanh
 * Endpoint: POST api/save_category.php
 */

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Vui lòng đăng nhập!'], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Chỉ chấp nhận POST!'], JSON_UNESCAPED_UNICODE);
    exit;
}

$user_id = $_SESSION['user_id'];
$name = trim($_POST['name'] ?? '');
$type = trim($_POST['type'] ?? 'expense');
$icon = trim($_POST['icon'] ?? 'fa-tags');
$color = trim($_POST['color'] ?? '#4F59E8');

if (empty($name)) {
    echo json_encode(['success' => false, 'message' => 'Vui lòng nhập tên danh mục!'], JSON_UNESCAPED_UNICODE);
    exit;
}

if (!in_array($type, ['expense', 'income'])) {
    $type = 'expense';
}

try {
    $newId = rand(100, 999);
    if ($pdo) {
        $stmt = $pdo->prepare("INSERT INTO `categories` (`user_id`, `name`, `type`, `icon`, `color`, `is_default`) VALUES (?, ?, ?, ?, ?, 0)");
        $stmt->execute([$user_id, $name, $type, $icon, $color]);
        $newId = (int)$pdo->lastInsertId();
    }

    echo json_encode([
        'success' => true,
        'message' => 'Đã thêm danh mục mới thành công!',
        'category' => [
            'id' => $newId,
            'name' => $name,
            'type' => $type,
            'icon' => $icon,
            'color' => $color
        ]
    ], JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Lỗi: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
