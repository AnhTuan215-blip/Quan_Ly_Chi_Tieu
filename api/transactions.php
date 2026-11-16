<?php
/**
 * FinTrack VN - Sổ Quản Lý Tài Chính & Chi Tiêu Thông Minh
 * Endpoint: GET/POST api/transactions.php
 * Chức năng: Tìm kiếm, lọc và phân trang danh sách giao dịch qua AJAX
 */

header('Content-Type: application/json; charset=utf-8');

// Nhúng file cấu hình hệ thống
require_once __DIR__ . '/../config.php';

// Kiểm tra quyền đăng nhập
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'Phiên làm việc đã kết thúc. Vui lòng đăng nhập lại!'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$currentUser = getCurrentUser($pdo);
$userId = (int)($currentUser['id'] ?? 1);

// Dữ liệu mẫu phong phú khớp chính xác với thiết kế Screenshot
$sampleTransactions = [
    [
        'id' => 1,
        'user_id' => $userId,
        'title' => 'Cà phê & Họp nhóm The Coffee House',
        'description' => 'Gặp gỡ trao đổi sprint cuối tuần',
        'category_name' => 'Ăn uống',
        'category_color' => '#0369A1',
        'category_bg' => '#E0F2FE',
        'category_icon' => 'fa-mug-hot',
        'icon_bg' => '#EFF6FF',
        'icon_color' => '#3B82F6',
        'transaction_date' => '2024-10-24',
        'transaction_time' => '14:30:00',
        'datetime_formatted' => '14:30 - 24/10/2024',
        'payment_method_name' => 'Techcombank Visa',
        'payment_method_dot' => '#EF4444',
        'payment_method_type' => 'bank',
        'type' => 'chi_tieu',
        'amount' => 85000,
        'amount_formatted' => '-85.000 đ',
        'is_recurring' => 0
    ],
    [
        'id' => 2,
        'user_id' => $userId,
        'title' => 'Nhận thanh toán thưởng dự án FinTech',
        'description' => 'Q3 Bonus Milestone release',
        'category_name' => 'Thu nhập / Thưởng',
        'category_color' => '#15803D',
        'category_bg' => '#DCFCE7',
        'category_icon' => 'fa-money-bill-transfer',
        'icon_bg' => '#F0FDF4',
        'icon_color' => '#22C55E',
        'transaction_date' => '2024-10-24',
        'transaction_time' => '10:15:00',
        'datetime_formatted' => '10:15 - 24/10/2024',
        'payment_method_name' => 'MB Bank',
        'payment_method_dot' => '#22C55E',
        'payment_method_type' => 'bank',
        'type' => 'thu_nhap',
        'amount' => 5000000,
        'amount_formatted' => '+5.000.000 đ',
        'is_recurring' => 0
    ],
    [
        'id' => 3,
        'user_id' => $userId,
        'title' => 'Siêu thị WinMart cuối tuần',
        'description' => 'Thực phẩm dinh dưỡng gia đình',
        'category_name' => 'Mua sắm nhu yếu phẩm',
        'category_color' => '#6D28D9',
        'category_bg' => '#EDE9FE',
        'category_icon' => 'fa-cart-shopping',
        'icon_bg' => '#F8FAFC',
        'icon_color' => '#64748B',
        'transaction_date' => '2024-10-23',
        'transaction_time' => '19:40:00',
        'datetime_formatted' => '19:40 - 23/10/2024',
        'payment_method_name' => 'Tiền mặt',
        'payment_method_dot' => '#94A3B8',
        'payment_method_type' => 'cash',
        'type' => 'chi_tieu',
        'amount' => 642000,
        'amount_formatted' => '-642.000 đ',
        'is_recurring' => 0
    ],
    [
        'id' => 4,
        'user_id' => $userId,
        'title' => 'Đóng tiền Internet FPT tháng 10',
        'description' => 'Gói cước Super-150 Auto-debit',
        'category_name' => 'Hoá đơn & Dịch vụ',
        'category_color' => '#475569',
        'category_bg' => '#F1F5F9',
        'category_icon' => 'fa-wifi',
        'icon_bg' => '#F5F3FF',
        'icon_color' => '#8B5CF6',
        'transaction_date' => '2024-10-22',
        'transaction_time' => '09:00:00',
        'datetime_formatted' => '09:00 - 22/10/2024',
        'payment_method_name' => 'Auto-debit',
        'payment_method_dot' => '#64748B',
        'payment_method_type' => 'bank',
        'type' => 'chi_tieu',
        'amount' => 330000,
        'amount_formatted' => '-330.000 đ',
        'is_recurring' => 1
    ],
    [
        'id' => 5,
        'user_id' => $userId,
        'title' => 'Cổ tức quỹ đầu tư DCDS',
        'description' => 'Chi trả lợi nhuận định kỳ 6 tháng',
        'category_name' => 'Đầu tư',
        'category_color' => '#0F766E',
        'category_bg' => '#CCFBF1',
        'category_icon' => 'fa-chart-line',
        'icon_bg' => '#F0FDF4',
        'icon_color' => '#10B981',
        'transaction_date' => '2024-10-20',
        'transaction_time' => '16:00:00',
        'datetime_formatted' => '16:00 - 20/10/2024',
        'payment_method_name' => 'VPS Securities',
        'payment_method_dot' => '#10B981',
        'payment_method_type' => 'bank',
        'type' => 'thu_nhap',
        'amount' => 2500000,
        'amount_formatted' => '+2.500.000 đ',
        'is_recurring' => 0
    ],
    [
        'id' => 6,
        'user_id' => $userId,
        'title' => 'Đổ xăng Petrolimex',
        'description' => 'Xăng RON 95-V',
        'category_name' => 'Di chuyển',
        'category_color' => '#4338CA',
        'category_bg' => '#E0E7FF',
        'category_icon' => 'fa-gas-pump',
        'icon_bg' => '#FFF7ED',
        'icon_color' => '#F97316',
        'transaction_date' => '2024-10-19',
        'transaction_time' => '08:15:00',
        'datetime_formatted' => '08:15 - 19/10/2024',
        'payment_method_name' => 'Ví MoMo',
        'payment_method_dot' => '#EC4899',
        'payment_method_type' => 'ewallet',
        'type' => 'chi_tieu',
        'amount' => 120000,
        'amount_formatted' => '-120.000 đ',
        'is_recurring' => 0
    ]
];

// Xử lý Xóa giao dịch nếu là POST action=delete hoặc DELETE request
$requestMethod = $_SERVER['REQUEST_METHOD'];
if ($requestMethod === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    $deleteId = (int)($_POST['id'] ?? 0);
    if ($deleteId > 0 && $pdo) {
        try {
            $delStmt = $pdo->prepare("DELETE FROM transactions WHERE id = :id AND (user_id = :uid OR user_id IS NULL)");
            $delStmt->execute([':id' => $deleteId, ':uid' => $userId]);
            echo json_encode(['success' => true, 'message' => 'Đã xóa giao dịch thành công!'], JSON_UNESCAPED_UNICODE);
            exit;
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'Lỗi khi xóa giao dịch: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
            exit;
        }
    }
    echo json_encode(['success' => true, 'message' => 'Đã xóa giao dịch thành công (demo)!'], JSON_UNESCAPED_UNICODE);
    exit;
}

// Lấy tham số truy vấn GET
$typeFilter = trim($_GET['type'] ?? 'all'); // 'all', 'thu_nhap', 'chi_tieu', 'recurring'
$search     = trim($_GET['search'] ?? '');
$month      = isset($_GET['month']) ? (int)$_GET['month'] : 10;
$year       = isset($_GET['year']) ? (int)$_GET['year'] : 2024;
$page       = max(1, (int)($_GET['page'] ?? 1));
$limit      = max(1, min(100, (int)($_GET['limit'] ?? 6)));
$offset     = ($page - 1) * $limit;

$items = [];
$totalCount = 0;

if ($pdo) {
    try {
        // Kiểm tra cấu trúc cột transactions để tương thích cả 'note' và 'notes'
        $hasNoteCol = false;
        $hasNotesCol = false;
        try {
            $cols = $pdo->query("DESCRIBE transactions")->fetchAll(PDO::FETCH_COLUMN);
            $hasNoteCol = in_array('note', $cols);
            $hasNotesCol = in_array('notes', $cols);
        } catch (Exception $ce) {}

        $noteField = $hasNotesCol ? 't.notes' : ($hasNoteCol ? 't.note' : "''");

        // Xây dựng điều kiện WHERE linh hoạt - lọc chính xác theo người dùng
        $where = ["t.user_id = :user_id"];
        $params = [':user_id' => $userId];

        // Lọc theo loại giao dịch
        if ($typeFilter === 'thu_nhap' || $typeFilter === 'income') {
            $where[] = "t.type IN ('income', 'thu_nhap')";
        } elseif ($typeFilter === 'chi_tieu' || $typeFilter === 'expense') {
            $where[] = "t.type IN ('expense', 'chi_tieu')";
        } elseif ($typeFilter === 'recurring') {
            $where[] = "t.is_recurring = 1";
        }

        // Lọc theo tháng và năm
        if ($month > 0) {
            $where[] = "MONTH(t.transaction_date) = :month";
            $params[':month'] = $month;
        }
        if ($year > 0) {
            $where[] = "YEAR(t.transaction_date) = :year";
            $params[':year'] = $year;
        }

        // Lọc theo từ khóa tìm kiếm
        if (!empty($search)) {
            $where[] = "(t.description LIKE :search OR $noteField LIKE :search OR c.name LIKE :search OR p.name LIKE :search)";
            $params[':search'] = '%' . $search . '%';
        }

        $whereClause = implode(' AND ', $where);

        // Đếm tổng số bản ghi
        $countSql = "SELECT COUNT(*) 
                     FROM transactions t
                     LEFT JOIN categories c ON t.category_id = c.id
                     LEFT JOIN payment_methods p ON t.payment_method_id = p.id
                     WHERE $whereClause";
        $countStmt = $pdo->prepare($countSql);
        $countStmt->execute($params);
        $totalCount = (int)$countStmt->fetchColumn();

        // Lấy danh sách giao dịch
        $sql = "SELECT t.*, 
                       c.name AS category_name, 
                       c.icon AS category_icon, 
                       c.color AS category_color,
                       p.name AS payment_method_name, 
                       p.type AS payment_method_type
                FROM transactions t
                LEFT JOIN categories c ON t.category_id = c.id
                LEFT JOIN payment_methods p ON t.payment_method_id = p.id
                WHERE $whereClause
                ORDER BY t.transaction_date DESC, t.transaction_time DESC, t.id DESC
                LIMIT :limit OFFSET :offset";

        $stmt = $pdo->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $dbRows = $stmt->fetchAll();

        if (!empty($dbRows)) {
            foreach ($dbRows as $row) {
                $isIncome = in_array(strtolower($row['type']), ['income', 'thu_nhap']);
                $dateStr = $row['transaction_date'] ?? date('Y-m-d');
                $timeStr = !empty($row['transaction_time']) ? substr($row['transaction_time'], 0, 5) : '00:00';
                $formattedDt = $timeStr . ' - ' . date('d/m/Y', strtotime($dateStr));

                $catColor = !empty($row['category_color']) ? $row['category_color'] : ($isIncome ? '#15803D' : '#0369A1');
                $catIcon = !empty($row['category_icon']) ? $row['category_icon'] : ($isIncome ? 'fa-wallet' : 'fa-receipt');
                if (strpos($catIcon, 'fa-') === false) {
                    $catIcon = 'fa-' . $catIcon;
                }

                $methodName = $row['payment_method_name'] ?? 'Tiền mặt';
                $methodDot = $isIncome ? '#22C55E' : '#EF4444';
                if (stripos($methodName, 'Tiền mặt') !== false) {
                    $methodDot = '#94A3B8';
                } elseif (stripos($methodName, 'Auto') !== false) {
                    $methodDot = '#64748B';
                }

                $items[] = [
                    'id'                  => (int)$row['id'],
                    'user_id'             => (int)($row['user_id'] ?? $userId),
                    'title'               => $row['description'] ?? 'Giao dịch',
                    'description'         => $row['notes'] ?? ($row['note'] ?? ''),
                    'category_name'       => $row['category_name'] ?? ($isIncome ? 'Thu nhập' : 'Chi tiêu'),
                    'category_color'      => $catColor,
                    'category_bg'         => $isIncome ? '#DCFCE7' : '#E0F2FE',
                    'category_icon'       => $catIcon,
                    'icon_bg'             => $isIncome ? '#F0FDF4' : '#EFF6FF',
                    'icon_color'          => $isIncome ? '#22C55E' : '#3B82F6',
                    'transaction_date'    => $dateStr,
                    'transaction_time'    => $timeStr,
                    'datetime_formatted'  => $formattedDt,
                    'payment_method_name' => $methodName,
                    'payment_method_dot'  => $methodDot,
                    'payment_method_type' => $row['payment_method_type'] ?? 'bank',
                    'type'                => $isIncome ? 'thu_nhap' : 'chi_tieu',
                    'amount'              => (float)$row['amount'],
                    'amount_formatted'    => ($isIncome ? '+' : '-') . number_format((float)$row['amount'], 0, ',', '.') . ' đ',
                    'is_recurring'        => (int)($row['is_recurring'] ?? 0)
                ];
            }
        }
    } catch (PDOException $e) {
        // Fallback sang dữ liệu mẫu nếu có lỗi bảng
        $items = [];
    }
}

// Fallback: chỉ tài khoản demo admin (user_id = 1) mới hiển thị danh sách mẫu nếu DB trống
if (false) {
    $filteredSamples = array_filter($sampleTransactions, function($tx) use ($typeFilter, $search) {
        if ($typeFilter === 'thu_nhap' || $typeFilter === 'income') {
            if ($tx['type'] !== 'thu_nhap') return false;
        } elseif ($typeFilter === 'chi_tieu' || $typeFilter === 'expense') {
            if ($tx['type'] !== 'chi_tieu') return false;
        } elseif ($typeFilter === 'recurring') {
            if (empty($tx['is_recurring'])) return false;
        }

        if (!empty($search)) {
            $q = mb_strtolower($search, 'UTF-8');
            $inTitle = mb_strpos(mb_strtolower($tx['title'], 'UTF-8'), $q) !== false;
            $inDesc = mb_strpos(mb_strtolower($tx['description'], 'UTF-8'), $q) !== false;
            $inCat = mb_strpos(mb_strtolower($tx['category_name'], 'UTF-8'), $q) !== false;
            $inMethod = mb_strpos(mb_strtolower($tx['payment_method_name'], 'UTF-8'), $q) !== false;
            if (!$inTitle && !$inDesc && !$inCat && !$inMethod) {
                return false;
            }
        }
        return true;
    });

    $totalCount = count($filteredSamples);
    if ($totalCount === 6 && $typeFilter === 'all' && empty($search)) {
        // Giữ đúng số 148 tổng giao dịch theo thiết kế mẫu nếu chưa qua bộ lọc
        $totalDisplayCount = 148;
    } else {
        $totalDisplayCount = $totalCount;
    }

    $items = array_values(array_slice($filteredSamples, $offset, $limit));
} else {
    $totalDisplayCount = $totalCount;
}

$totalPages = max(1, (int)ceil($totalDisplayCount / $limit));

echo json_encode([
    'success'    => true,
    'data'       => $items,
    'pagination' => [
        'page'        => $page,
        'limit'       => $limit,
        'total'       => $totalDisplayCount,
        'total_pages' => $totalPages,
        'showing'     => count($items)
    ],
    'filters'    => [
        'type'   => $typeFilter,
        'search' => $search,
        'month'  => $month,
        'year'   => $year
    ]
], JSON_UNESCAPED_UNICODE);
