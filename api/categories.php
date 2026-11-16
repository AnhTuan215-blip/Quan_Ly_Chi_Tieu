<?php
/**
 * FinTrack VN - API Quản lý Danh mục Thu & Chi
 * Đường dẫn: api/categories.php
 * Hỗ trợ các phương thức: GET, POST, PUT, DELETE
 */

// Đặt header JSON
header('Content-Type: application/json; charset=utf-8');

// Bật bộ đệm đầu ra để tránh các ký tự khoảng trắng ngoài ý muốn làm hỏng JSON
ob_start();

require_once __DIR__ . '/../config.php';

// Kiểm tra và thiết lập phiên làm việc
if (!isset($_SESSION['user_id'])) {
    $_SESSION['user_id'] = 1;
    $_SESSION['username'] = 'admin';
    $_SESSION['full_name'] = 'Nguyễn Văn An';
    $_SESSION['email'] = 'an.nguyen@email.com';
    $_SESSION['membership_type'] = 'premium';
}

$userId = (int)$_SESSION['user_id'];

// Hàm chuẩn bị & đồng bộ bảng categories trong CSDL
function initCategoriesTable($pdo, $userId) {
    if (!$pdo instanceof PDO) return;
    
    try {
        // Tạo bảng categories nếu chưa tồn tại
        $pdo->exec("CREATE TABLE IF NOT EXISTS `categories` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `user_id` INT NOT NULL,
            `name` VARCHAR(100) NOT NULL,
            `type` ENUM('chi_tieu', 'thu_nhap') NOT NULL,
            `icon` VARCHAR(50) DEFAULT 'fa-folder',
            `color` VARCHAR(20) DEFAULT '#4F59E8',
            `budget_limit` DECIMAL(15,0) NOT NULL DEFAULT 0,
            `description` TEXT DEFAULT NULL,
            `is_active` TINYINT(1) NOT NULL DEFAULT 1,
            `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX `idx_cat_user` (`user_id`),
            INDEX `idx_cat_active` (`is_active`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

        // Đảm bảo các cột quan trọng tồn tại
        try { $pdo->exec("ALTER TABLE `categories` ADD COLUMN `budget_limit` DECIMAL(15,0) NOT NULL DEFAULT 0"); } catch (Exception $e) {}
        try { $pdo->exec("ALTER TABLE `categories` ADD COLUMN `description` TEXT DEFAULT NULL"); } catch (Exception $e) {}
        try { $pdo->exec("ALTER TABLE `categories` ADD COLUMN `is_active` TINYINT(1) NOT NULL DEFAULT 1"); } catch (Exception $e) {}

        // Kiểm tra xem đã có dữ liệu mẫu chưa
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM `categories` WHERE `user_id` = :uid AND `is_active` = 1");
        $stmt->execute([':uid' => $userId]);
        $count = (int)$stmt->fetchColumn();

        if ($count === 0) {
            $defaultCategories = [
                ['Ăn uống', 'chi_tieu', 'fa-utensils', '#F97316', 8000000, 'Cà phê, nhà hàng, siêu thị, gia đình'],
                ['Mua sắm', 'chi_tieu', 'fa-bag-shopping', '#3B82F6', 5000000, 'Thời trang, thiết bị công nghệ'],
                ['Di chuyển', 'chi_tieu', 'fa-car', '#4F59E8', 3000000, 'Xăng xe, gội xe, Grab, bảo dưỡng'],
                ['Hóa đơn điện nước', 'chi_tieu', 'fa-file-invoice-dollar', '#089182', 2500000, 'Internet, cáp quang, điện, nước'],
                ['Giải trí', 'chi_tieu', 'fa-gamepad', '#9333EA', 2000000, 'Xem phim, hòa nhạc, sách, báo điện tử'],
                ['Lương thưởng', 'thu_nhap', 'fa-briefcase', '#A96CA9', 45000000, 'Lương cứng doanh nghiệp, thưởng KPI'],
                ['Đầu tư sinh lời', 'thu_nhap', 'fa-chart-line', '#009488', 8500000, 'Cổ tức chứng khoán, tiền gửi tiết kiệm']
            ];

            $insertStmt = $pdo->prepare("INSERT INTO `categories` (`user_id`, `name`, `type`, `icon`, `color`, `budget_limit`, `description`, `is_active`) VALUES (:uid, :name, :type, :icon, :color, :budget, :description, 1)");
            foreach ($defaultCategories as $cat) {
                $bLimit = ($userId === 1) ? $cat[4] : 0;
                $insertStmt->execute([
                    ':uid' => $userId,
                    ':name' => $cat[0],
                    ':type' => $cat[1],
                    ':icon' => $cat[2],
                    ':color' => $cat[3],
                    ':budget' => $bLimit,
                    ':description' => $cat[5]
                ]);
            }
        }
    } catch (PDOException $e) {
        // Bỏ qua lỗi schema nếu đã sẵn sàng
    }
}

// Khởi tạo bảng nếu PDO kết nối được
if ($pdo instanceof PDO) {
    initCategoriesTable($pdo, $userId);
}

// Đọc payload (hỗ trợ cả JSON body lẫn $_POST)
$rawInput = file_get_contents('php://input');
$jsonInput = json_decode($rawInput, true) ?? [];
$requestData = array_merge($_POST, $jsonInput);

// Xác định phương thức HTTP thực tế
$method = $_SERVER['REQUEST_METHOD'];
if ($method === 'POST' && isset($requestData['_method'])) {
    $method = strtoupper($requestData['_method']);
} elseif ($method === 'GET' && isset($_GET['action']) && $_GET['action'] === 'delete') {
    $method = 'DELETE';
}

// Dọn dẹp buffer trước khi xuất JSON
ob_clean();

// Dữ liệu mẫu phiên làm việc (Session Fallback) phòng trường hợp PDO chưa khả dụng
if (!isset($_SESSION['mock_categories'])) {
    $_SESSION['mock_categories'] = [
        [
            'id' => 1,
            'name' => 'Ăn uống',
            'type' => 'chi_tieu',
            'icon' => 'fa-utensils',
            'color' => '#F97316',
            'budget_limit' => 8000000,
            'description' => 'Cà phê, nhà hàng, siêu thị, gia đình',
            'is_active' => 1
        ],
        [
            'id' => 2,
            'name' => 'Mua sắm',
            'type' => 'chi_tieu',
            'icon' => 'fa-bag-shopping',
            'color' => '#3B82F6',
            'budget_limit' => 5000000,
            'description' => 'Thời trang, thiết bị công nghệ',
            'is_active' => 1
        ],
        [
            'id' => 3,
            'name' => 'Di chuyển',
            'type' => 'chi_tieu',
            'icon' => 'fa-car',
            'color' => '#4F59E8',
            'budget_limit' => 3000000,
            'description' => 'Xăng xe, gội xe, Grab, bảo dưỡng',
            'is_active' => 1
        ],
        [
            'id' => 4,
            'name' => 'Hóa đơn điện nước',
            'type' => 'chi_tieu',
            'icon' => 'fa-file-invoice-dollar',
            'color' => '#089182',
            'budget_limit' => 2500000,
            'description' => 'Internet, cáp quang, điện, nước',
            'is_active' => 1
        ],
        [
            'id' => 5,
            'name' => 'Giải trí',
            'type' => 'chi_tieu',
            'icon' => 'fa-gamepad',
            'color' => '#9333EA',
            'budget_limit' => 2000000,
            'description' => 'Xem phim, hòa nhạc, sách, báo điện tử',
            'is_active' => 1
        ],
        [
            'id' => 6,
            'name' => 'Lương thưởng',
            'type' => 'thu_nhap',
            'icon' => 'fa-briefcase',
            'color' => '#A96CA9',
            'budget_limit' => 45000000,
            'description' => 'Lương cứng doanh nghiệp, thưởng KPI',
            'is_active' => 1
        ],
        [
            'id' => 7,
            'name' => 'Đầu tư sinh lời',
            'type' => 'thu_nhap',
            'icon' => 'fa-chart-line',
            'color' => '#009488',
            'budget_limit' => 8500000,
            'description' => 'Cổ tức chứng khoán, tiền gửi tiết kiệm',
            'is_active' => 1
        ]
    ];
}

// Xử lý theo từng phương thức
switch ($method) {
    // -------------------------------------------------------------
    // GET: Danh sách danh mục & thống kê số lượng
    // -------------------------------------------------------------
    case 'GET':
        $typeFilter = $_GET['type'] ?? 'all';
        $sortBy = $_GET['sort'] ?? 'budget_desc';
        $categories = [];

        if ($pdo instanceof PDO) {
            try {
                // Đếm số lượng danh mục
                $countAllStmt = $pdo->prepare("SELECT COUNT(*) FROM `categories` WHERE `user_id` = :uid AND `is_active` = 1");
                $countAllStmt->execute([':uid' => $userId]);
                $countAll = (int)$countAllStmt->fetchColumn();

                $countChiTieuStmt = $pdo->prepare("SELECT COUNT(*) FROM `categories` WHERE `user_id` = :uid AND `is_active` = 1 AND `type` IN ('chi_tieu', 'expense')");
                $countChiTieuStmt->execute([':uid' => $userId]);
                $countChiTieu = (int)$countChiTieuStmt->fetchColumn();

                $countThuNhapStmt = $pdo->prepare("SELECT COUNT(*) FROM `categories` WHERE `user_id` = :uid AND `is_active` = 1 AND `type` IN ('thu_nhap', 'income')");
                $countThuNhapStmt->execute([':uid' => $userId]);
                $countThuNhap = (int)$countThuNhapStmt->fetchColumn();

                // Xây dựng câu truy vấn theo bộ lọc
                $sql = "SELECT id, user_id, name, type, icon, color, budget_limit, description, is_active FROM `categories` WHERE `user_id` = :uid AND `is_active` = 1";
                $params = [':uid' => $userId];

                if ($typeFilter === 'chi_tieu' || $typeFilter === 'expense') {
                    $sql .= " AND `type` IN ('chi_tieu', 'expense')";
                } elseif ($typeFilter === 'thu_nhap' || $typeFilter === 'income') {
                    $sql .= " AND `type` IN ('thu_nhap', 'income')";
                }

                // Sắp xếp
                switch ($sortBy) {
                    case 'budget_asc':
                        $sql .= " ORDER BY budget_limit ASC, name ASC";
                        break;
                    case 'name_asc':
                        $sql .= " ORDER BY name ASC";
                        break;
                    case 'name_desc':
                        $sql .= " ORDER BY name DESC";
                        break;
                    case 'budget_desc':
                    default:
                        $sql .= " ORDER BY budget_limit DESC, name ASC";
                        break;
                }

                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                $rows = $stmt->fetchAll();

                foreach ($rows as $row) {
                    $normType = in_array($row['type'], ['thu_nhap', 'income']) ? 'thu_nhap' : 'chi_tieu';
                    $categories[] = [
                        'id' => (int)$row['id'],
                        'name' => $row['name'],
                        'type' => $normType,
                        'type_label' => ($normType === 'thu_nhap') ? 'Thu nhập' : 'Chi tiêu',
                        'icon' => $row['icon'] ?: 'fa-folder',
                        'color' => $row['color'] ?: '#4F59E8',
                        'budget' => (float)$row['budget_limit'],
                        'budget_limit' => (float)$row['budget_limit'],
                        'budget_formatted' => number_format((float)$row['budget_limit'], 0, ',', '.') . ' đ',
                        'description' => $row['description'] ?: '',
                        'is_active' => (int)$row['is_active']
                    ];
                }

                echo json_encode([
                    'success' => true,
                    'data' => $categories,
                    'counts' => [
                        'all' => $countAll,
                        'chi_tieu' => $countChiTieu,
                        'thu_nhap' => $countThuNhap
                    ]
                ], JSON_UNESCAPED_UNICODE);
                exit;

            } catch (PDOException $e) {
                // Nếu lỗi truy vấn, chuyển sang fallback
            }
        }

        // Mock Session Fallback khi không có PDO
        $activeMock = array_filter($_SESSION['mock_categories'], function($item) {
            return !empty($item['is_active']);
        });

        $countAll = count($activeMock);
        $countChiTieu = count(array_filter($activeMock, fn($i) => $i['type'] === 'chi_tieu'));
        $countThuNhap = count(array_filter($activeMock, fn($i) => $i['type'] === 'thu_nhap'));

        if ($typeFilter === 'chi_tieu') {
            $filtered = array_filter($activeMock, fn($i) => $i['type'] === 'chi_tieu');
        } elseif ($typeFilter === 'thu_nhap') {
            $filtered = array_filter($activeMock, fn($i) => $i['type'] === 'thu_nhap');
        } else {
            $filtered = $activeMock;
        }

        // Sắp xếp mảng
        usort($filtered, function($a, $b) use ($sortBy) {
            if ($sortBy === 'budget_asc') return $a['budget_limit'] <=> $b['budget_limit'];
            if ($sortBy === 'name_asc') return strcmp($a['name'], $b['name']);
            if ($sortBy === 'name_desc') return strcmp($b['name'], $a['name']);
            return $b['budget_limit'] <=> $a['budget_limit'];
        });

        $formattedList = [];
        foreach ($filtered as $item) {
            $formattedList[] = [
                'id' => (int)$item['id'],
                'name' => $item['name'],
                'type' => $item['type'],
                'type_label' => ($item['type'] === 'thu_nhap') ? 'Thu nhập' : 'Chi tiêu',
                'icon' => $item['icon'],
                'color' => $item['color'],
                'budget' => (float)$item['budget_limit'],
                'budget_limit' => (float)$item['budget_limit'],
                'budget_formatted' => number_format((float)$item['budget_limit'], 0, ',', '.') . ' đ',
                'description' => $item['description'] ?? '',
                'is_active' => 1
            ];
        }

        echo json_encode([
            'success' => true,
            'data' => $formattedList,
            'counts' => [
                'all' => $countAll,
                'chi_tieu' => $countChiTieu,
                'thu_nhap' => $countThuNhap
            ]
        ], JSON_UNESCAPED_UNICODE);
        exit;

    // -------------------------------------------------------------
    // POST: Thêm danh mục mới
    // -------------------------------------------------------------
    case 'POST':
        $name = trim($requestData['name'] ?? '');
        $type = in_array($requestData['type'] ?? '', ['thu_nhap', 'income']) ? 'thu_nhap' : 'chi_tieu';
        $icon = trim($requestData['icon'] ?? 'fa-folder');
        $color = trim($requestData['color'] ?? '#4F59E8');
        $budget = floatval($requestData['budget'] ?? ($requestData['budget_limit'] ?? 0));
        $description = trim($requestData['description'] ?? '');

        if (empty($name)) {
            echo json_encode(['success' => false, 'message' => 'Tên danh mục không được để trống!'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        if (empty($icon)) $icon = 'fa-folder';
        if (empty($color)) $color = '#4F59E8';
        if ($budget < 0) $budget = 0;

        if ($pdo instanceof PDO) {
            try {
                $stmt = $pdo->prepare("INSERT INTO `categories` (`user_id`, `name`, `type`, `icon`, `color`, `budget_limit`, `description`, `is_active`) VALUES (:uid, :name, :type, :icon, :color, :budget, :description, 1)");
                $stmt->execute([
                    ':uid' => $userId,
                    ':name' => $name,
                    ':type' => $type,
                    ':icon' => $icon,
                    ':color' => $color,
                    ':budget' => $budget,
                    ':description' => $description
                ]);
                $newId = (int)$pdo->lastInsertId();

                echo json_encode([
                    'success' => true,
                    'message' => 'Thêm danh mục mới thành công!',
                    'data' => [
                        'id' => $newId,
                        'name' => $name,
                        'type' => $type,
                        'type_label' => ($type === 'thu_nhap') ? 'Thu nhập' : 'Chi tiêu',
                        'icon' => $icon,
                        'color' => $color,
                        'budget' => $budget,
                        'budget_limit' => $budget,
                        'budget_formatted' => number_format($budget, 0, ',', '.') . ' đ',
                        'description' => $description,
                        'is_active' => 1
                    ]
                ], JSON_UNESCAPED_UNICODE);
                exit;

            } catch (PDOException $e) {
                echo json_encode(['success' => false, 'message' => 'Lỗi lưu cơ sở dữ liệu: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
                exit;
            }
        }

        // Mock Fallback
        $maxId = 0;
        foreach ($_SESSION['mock_categories'] as $item) {
            if ($item['id'] > $maxId) $maxId = $item['id'];
        }
        $newId = $maxId + 1;
        $newCategory = [
            'id' => $newId,
            'name' => $name,
            'type' => $type,
            'icon' => $icon,
            'color' => $color,
            'budget_limit' => $budget,
            'description' => $description,
            'is_active' => 1
        ];
        $_SESSION['mock_categories'][] = $newCategory;

        echo json_encode([
            'success' => true,
            'message' => 'Thêm danh mục mới thành công!',
            'data' => [
                'id' => $newId,
                'name' => $name,
                'type' => $type,
                'type_label' => ($type === 'thu_nhap') ? 'Thu nhập' : 'Chi tiêu',
                'icon' => $icon,
                'color' => $color,
                'budget' => $budget,
                'budget_limit' => $budget,
                'budget_formatted' => number_format($budget, 0, ',', '.') . ' đ',
                'description' => $description,
                'is_active' => 1
            ]
        ], JSON_UNESCAPED_UNICODE);
        exit;

    // -------------------------------------------------------------
    // PUT: Chỉnh sửa danh mục hiện có
    // -------------------------------------------------------------
    case 'PUT':
        $id = (int)($requestData['id'] ?? ($_GET['id'] ?? 0));
        $name = trim($requestData['name'] ?? '');
        $type = in_array($requestData['type'] ?? '', ['thu_nhap', 'income']) ? 'thu_nhap' : 'chi_tieu';
        $icon = trim($requestData['icon'] ?? 'fa-folder');
        $color = trim($requestData['color'] ?? '#4F59E8');
        $budget = floatval($requestData['budget'] ?? ($requestData['budget_limit'] ?? 0));
        $description = trim($requestData['description'] ?? '');

        if ($id <= 0) {
            echo json_encode(['success' => false, 'message' => 'Thiếu ID danh mục cần cập nhật!'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        if (empty($name)) {
            echo json_encode(['success' => false, 'message' => 'Tên danh mục không được để trống!'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        if (empty($icon)) $icon = 'fa-folder';
        if (empty($color)) $color = '#4F59E8';
        if ($budget < 0) $budget = 0;

        if ($pdo instanceof PDO) {
            try {
                $stmt = $pdo->prepare("UPDATE `categories` SET `name` = :name, `type` = :type, `icon` = :icon, `color` = :color, `budget_limit` = :budget, `description` = :description WHERE `id` = :id AND `user_id` = :uid");
                $stmt->execute([
                    ':name' => $name,
                    ':type' => $type,
                    ':icon' => $icon,
                    ':color' => $color,
                    ':budget' => $budget,
                    ':description' => $description,
                    ':id' => $id,
                    ':uid' => $userId
                ]);

                echo json_encode([
                    'success' => true,
                    'message' => 'Cập nhật danh mục thành công!',
                    'data' => [
                        'id' => $id,
                        'name' => $name,
                        'type' => $type,
                        'type_label' => ($type === 'thu_nhap') ? 'Thu nhập' : 'Chi tiêu',
                        'icon' => $icon,
                        'color' => $color,
                        'budget' => $budget,
                        'budget_limit' => $budget,
                        'budget_formatted' => number_format($budget, 0, ',', '.') . ' đ',
                        'description' => $description,
                        'is_active' => 1
                    ]
                ], JSON_UNESCAPED_UNICODE);
                exit;

            } catch (PDOException $e) {
                echo json_encode(['success' => false, 'message' => 'Lỗi cập nhật CSDL: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
                exit;
            }
        }

        // Mock Fallback
        $found = false;
        foreach ($_SESSION['mock_categories'] as &$item) {
            if ($item['id'] === $id) {
                $item['name'] = $name;
                $item['type'] = $type;
                $item['icon'] = $icon;
                $item['color'] = $color;
                $item['budget_limit'] = $budget;
                $item['description'] = $description;
                $found = true;
                break;
            }
        }

        if (!$found) {
            echo json_encode(['success' => false, 'message' => 'Không tìm thấy danh mục để cập nhật!'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        echo json_encode([
            'success' => true,
            'message' => 'Cập nhật danh mục thành công!',
            'data' => [
                'id' => $id,
                'name' => $name,
                'type' => $type,
                'type_label' => ($type === 'thu_nhap') ? 'Thu nhập' : 'Chi tiêu',
                'icon' => $icon,
                'color' => $color,
                'budget' => $budget,
                'budget_limit' => $budget,
                'budget_formatted' => number_format($budget, 0, ',', '.') . ' đ',
                'description' => $description,
                'is_active' => 1
            ]
        ], JSON_UNESCAPED_UNICODE);
        exit;

    // -------------------------------------------------------------
    // DELETE: Xóa mềm danh mục (đặt is_active = 0)
    // -------------------------------------------------------------
    case 'DELETE':
        $id = (int)($requestData['id'] ?? ($_GET['id'] ?? 0));

        if ($id <= 0) {
            echo json_encode(['success' => false, 'message' => 'Thiếu ID danh mục cần xóa!'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        if ($pdo instanceof PDO) {
            try {
                $stmt = $pdo->prepare("UPDATE `categories` SET `is_active` = 0 WHERE `id` = :id AND `user_id` = :uid");
                $stmt->execute([':id' => $id, ':uid' => $userId]);

                echo json_encode([
                    'success' => true,
                    'message' => 'Đã xóa danh mục thành công!'
                ], JSON_UNESCAPED_UNICODE);
                exit;

            } catch (PDOException $e) {
                echo json_encode(['success' => false, 'message' => 'Lỗi xóa danh mục CSDL: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
                exit;
            }
        }

        // Mock Fallback
        foreach ($_SESSION['mock_categories'] as &$item) {
            if ($item['id'] === $id) {
                $item['is_active'] = 0;
                break;
            }
        }

        echo json_encode([
            'success' => true,
            'message' => 'Đã xóa danh mục thành công!'
        ], JSON_UNESCAPED_UNICODE);
        exit;

    default:
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Phương thức HTTP không được hỗ trợ!'], JSON_UNESCAPED_UNICODE);
        exit;
}
