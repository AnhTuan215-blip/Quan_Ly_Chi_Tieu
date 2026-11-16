<?php
/**
 * FinTrack VN - API Quản lý Ngân sách Tháng & Cảnh báo Thông minh
 * Đường dẫn: api/budgets.php
 * Hỗ trợ các phương thức: GET, POST
 */

header('Content-Type: application/json; charset=utf-8');

ob_start();

require_once __DIR__ . '/../config.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Vui lòng đăng nhập!'], JSON_UNESCAPED_UNICODE);
    exit;
}

$userId = (int)$_SESSION['user_id'];

// Khởi tạo bảng budgets trong CSDL
function initBudgetsTable($pdo, $userId) {
    if (!$pdo instanceof PDO) return;
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS `budgets` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `user_id` INT NOT NULL,
            `month` INT NOT NULL,
            `year` INT NOT NULL,
            `total_budget` DECIMAL(15,0) NOT NULL DEFAULT 25000000,
            `warning_threshold` INT NOT NULL DEFAULT 80,
            `warning_yellow` TINYINT(1) NOT NULL DEFAULT 1,
            `warning_red` TINYINT(1) NOT NULL DEFAULT 1,
            `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY `unique_user_month_year` (`user_id`, `month`, `year`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

        try { $pdo->exec("ALTER TABLE `budgets` ADD COLUMN `total_budget` DECIMAL(15,0) NOT NULL DEFAULT 25000000"); } catch (Exception $e) {}
        try { $pdo->exec("ALTER TABLE `budgets` ADD COLUMN `warning_threshold` INT NOT NULL DEFAULT 80"); } catch (Exception $e) {}
        try { $pdo->exec("ALTER TABLE `budgets` ADD COLUMN `warning_yellow` TINYINT(1) NOT NULL DEFAULT 1"); } catch (Exception $e) {}
        try { $pdo->exec("ALTER TABLE `budgets` ADD COLUMN `warning_red` TINYINT(1) NOT NULL DEFAULT 1"); } catch (Exception $e) {}

        // Kiểm tra xem đã có bản ghi tháng 10/2024 chưa (chỉ tạo mặc định cho demo admin)
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM `budgets` WHERE `user_id` = :uid AND `month` = 10 AND `year` = 2024");
        $stmt->execute([':uid' => $userId]);
        if ((int)$stmt->fetchColumn() === 0 && $userId === 1) {
            $insert = $pdo->prepare("INSERT INTO `budgets` (`user_id`, `month`, `year`, `total_budget`, `warning_threshold`, `warning_yellow`, `warning_red`) VALUES (:uid, 10, 2024, 25000000, 80, 1, 1)");
            $insert->execute([':uid' => $userId]);
        }
    } catch (PDOException $e) {}
}

if ($pdo instanceof PDO) {
    initBudgetsTable($pdo, $userId);
}

// Session Fallback
if (!isset($_SESSION['mock_budgets'])) {
    $_SESSION['mock_budgets'] = ($userId === 1) ? [
        '10_2024' => [
            'month' => 10,
            'year' => 2024,
            'total_budget' => 25000000,
            'spent' => 13600000,
            'warning_threshold' => 80,
            'warning_yellow' => 1,
            'warning_red' => 1
        ]
    ] : [];
}

$rawInput = file_get_contents('php://input');
$jsonInput = json_decode($rawInput, true) ?? [];
$requestData = array_merge($_POST, $jsonInput);

$method = $_SERVER['REQUEST_METHOD'];
if ($method === 'POST' && isset($requestData['_method']) && strtoupper($requestData['_method']) === 'GET') {
    $method = 'GET';
}

ob_clean();

// Lấy tham số tháng và năm
$month = isset($requestData['month']) ? (int)$requestData['month'] : (isset($_GET['month']) ? (int)$_GET['month'] : 10);
$year = isset($requestData['year']) ? (int)$requestData['year'] : (isset($_GET['year']) ? (int)$_GET['year'] : 2024);

if ($month < 1 || $month > 12) $month = 10;
if ($year < 2000 || $year > 2100) $year = 2024;

$budgetKey = "{$month}_{$year}";

switch ($method) {
    // -------------------------------------------------------------
    // GET: Lấy thông tin ngân sách & tiến độ tháng
    // -------------------------------------------------------------
    case 'GET':
        $totalBudget = ($userId === 1) ? 25000000.0 : 0.0;
        $warningThreshold = 80;
        $warningYellow = 1;
        $warningRed = 1;
        $spent = ($userId === 1) ? 13600000.0 : 0.0;

        if ($pdo instanceof PDO) {
            try {
                // Đọc cài đặt ngân sách tháng
                $stmt = $pdo->prepare("SELECT total_budget, warning_threshold, warning_yellow, warning_red FROM `budgets` WHERE `user_id` = :uid AND `month` = :m AND `year` = :y LIMIT 1");
                $stmt->execute([':uid' => $userId, ':m' => $month, ':y' => $year]);
                $budgetRow = $stmt->fetch();

                if ($budgetRow) {
                    $totalBudget = floatval($budgetRow['total_budget'] ?: 0);
                    $warningThreshold = (int)($budgetRow['warning_threshold'] ?: 80);
                    $warningYellow = isset($budgetRow['warning_yellow']) ? (int)$budgetRow['warning_yellow'] : 1;
                    $warningRed = isset($budgetRow['warning_red']) ? (int)$budgetRow['warning_red'] : 1;
                }

                // Tính tổng chi tiêu thực tế từ bảng transactions
                $txStmt = $pdo->prepare("SELECT SUM(amount) as total_spent FROM `transactions` WHERE `user_id` = :uid AND `type` IN ('chi_tieu', 'expense') AND MONTH(transaction_date) = :m AND YEAR(transaction_date) = :y");
                $txStmt->execute([':uid' => $userId, ':m' => $month, ':y' => $year]);
                $txRow = $txStmt->fetch();

                if ($txRow && $txRow['total_spent'] !== null) {
                    $spent = floatval($txRow['total_spent']);
                } else if ($userId !== 1) {
                    $spent = 0.0;
                }
            } catch (PDOException $e) {}
        } else {
            if (isset($_SESSION['mock_budgets'][$budgetKey])) {
                $mb = $_SESSION['mock_budgets'][$budgetKey];
                $totalBudget = floatval($mb['total_budget']);
                $spent = floatval($mb['spent']);
                $warningThreshold = (int)$mb['warning_threshold'];
                $warningYellow = (int)$mb['warning_yellow'];
                $warningRed = (int)$mb['warning_red'];
            }
        }

        $remaining = max(0, $totalBudget - $spent);
        $percentage = ($totalBudget > 0) ? round(($spent / $totalBudget) * 100, 1) : 0;

        // Đánh giá kỷ luật tài chính
        if ($percentage <= 60) {
            $disciplineRating = 'Xuất Sắc';
            $disciplineColor = '#10B981';
            $disciplineDesc = "Kiểm soát chi tiêu tuyệt vời, duy trì tỷ lệ dưới 60% ngân sách.";
        } elseif ($percentage <= 80) {
            $disciplineRating = 'Tốt';
            $disciplineColor = '#3B82F6';
            $disciplineDesc = "Chi tiêu trong tầm kiểm soát ổn định, còn dư ngân sách dự phòng an toàn.";
        } elseif ($percentage <= 100) {
            $disciplineRating = 'Cảnh Báo';
            $disciplineColor = '#F59E0B';
            $disciplineDesc = "Đã chạm mức cảnh báo an toàn. Cần cân nhắc cắt giảm các khoản chi không thiết yếu.";
        } else {
            $disciplineRating = 'Vượt Ngân Sách';
            $disciplineColor = '#EF4444';
            $disciplineDesc = "Đã vượt hạn mức chi tiêu dự trù của tháng!";
        }

        echo json_encode([
            'success' => true,
            'data' => [
                'month' => $month,
                'year' => $year,
                'month_label' => "Tháng $month / $year",
                'total_budget' => $totalBudget,
                'total_budget_formatted' => number_format($totalBudget, 0, ',', '.') . ' đ',
                'spent' => $spent,
                'spent_formatted' => number_format($spent, 0, ',', '.') . ' đ',
                'remaining' => $remaining,
                'remaining_formatted' => number_format($remaining, 0, ',', '.') . ' đ',
                'percentage' => $percentage,
                'discipline_rating' => $disciplineRating,
                'discipline_color' => $disciplineColor,
                'discipline_desc' => $disciplineDesc,
                'warning_threshold' => $warningThreshold,
                'warning_yellow' => (bool)$warningYellow,
                'warning_red' => (bool)$warningRed
            ]
        ], JSON_UNESCAPED_UNICODE);
        exit;

    // -------------------------------------------------------------
    // POST: Cập nhật hạn mức ngân sách & cảnh báo
    // -------------------------------------------------------------
    case 'POST':
    case 'PUT':
        $totalBudget = floatval($requestData['total_budget'] ?? 25000000);
        if ($totalBudget <= 0) $totalBudget = 25000000;

        $warningThreshold = isset($requestData['warning_threshold']) ? (int)$requestData['warning_threshold'] : 80;
        if ($warningThreshold < 10) $warningThreshold = 10;
        if ($warningThreshold > 100) $warningThreshold = 100;

        $warningYellow = isset($requestData['warning_yellow']) ? (int)$requestData['warning_yellow'] : 1;
        $warningRed = isset($requestData['warning_red']) ? (int)$requestData['warning_red'] : 1;

        if ($pdo instanceof PDO) {
            try {
                $stmt = $pdo->prepare("INSERT INTO `budgets` (`user_id`, `month`, `year`, `total_budget`, `warning_threshold`, `warning_yellow`, `warning_red`) 
                    VALUES (:uid, :m, :y, :tb, :wt, :wy, :wr) 
                    ON DUPLICATE KEY UPDATE 
                        `total_budget` = VALUES(`total_budget`), 
                        `warning_threshold` = VALUES(`warning_threshold`), 
                        `warning_yellow` = VALUES(`warning_yellow`), 
                        `warning_red` = VALUES(`warning_red`)");
                $stmt->execute([
                    ':uid' => $userId,
                    ':m'   => $month,
                    ':y'   => $year,
                    ':tb'  => $totalBudget,
                    ':wt'  => $warningThreshold,
                    ':wy'  => $warningYellow,
                    ':wr'  => $warningRed
                ]);
            } catch (PDOException $e) {}
        }

        $spent = 13600000.0;
        if (isset($_SESSION['mock_budgets'][$budgetKey])) {
            $spent = floatval($_SESSION['mock_budgets'][$budgetKey]['spent']);
        }

        $_SESSION['mock_budgets'][$budgetKey] = [
            'month' => $month,
            'year' => $year,
            'total_budget' => $totalBudget,
            'spent' => $spent,
            'warning_threshold' => $warningThreshold,
            'warning_yellow' => $warningYellow,
            'warning_red' => $warningRed
        ];

        $remaining = max(0, $totalBudget - $spent);
        $percentage = ($totalBudget > 0) ? round(($spent / $totalBudget) * 100, 1) : 0;

        if ($percentage <= 60) {
            $disciplineRating = 'Xuất Sắc';
            $disciplineColor = '#10B981';
            $disciplineDesc = "Kiểm soát chi tiêu tuyệt vời, duy trì tỷ lệ dưới 60% ngân sách.";
        } elseif ($percentage <= 80) {
            $disciplineRating = 'Tốt';
            $disciplineColor = '#3B82F6';
            $disciplineDesc = "Chi tiêu trong tầm kiểm soát ổn định, còn dư ngân sách dự phòng an toàn.";
        } elseif ($percentage <= 100) {
            $disciplineRating = 'Cảnh Báo';
            $disciplineColor = '#F59E0B';
            $disciplineDesc = "Đã chạm mức cảnh báo an toàn. Cần cân nhắc cắt giảm các khoản chi không thiết yếu.";
        } else {
            $disciplineRating = 'Vượt Ngân Sách';
            $disciplineColor = '#EF4444';
            $disciplineDesc = "Đã vượt hạn mức chi tiêu dự trù của tháng!";
        }

        echo json_encode([
            'success' => true,
            'message' => 'Cập nhật ngân sách tháng ' . $month . '/' . $year . ' thành công!',
            'data' => [
                'month' => $month,
                'year' => $year,
                'month_label' => "Tháng $month / $year",
                'total_budget' => $totalBudget,
                'total_budget_formatted' => number_format($totalBudget, 0, ',', '.') . ' đ',
                'spent' => $spent,
                'spent_formatted' => number_format($spent, 0, ',', '.') . ' đ',
                'remaining' => $remaining,
                'remaining_formatted' => number_format($remaining, 0, ',', '.') . ' đ',
                'percentage' => $percentage,
                'discipline_rating' => $disciplineRating,
                'discipline_color' => $disciplineColor,
                'discipline_desc' => $disciplineDesc,
                'warning_threshold' => $warningThreshold,
                'warning_yellow' => (bool)$warningYellow,
                'warning_red' => (bool)$warningRed
            ]
        ], JSON_UNESCAPED_UNICODE);
        exit;

    default:
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Phương thức HTTP không được hỗ trợ!'], JSON_UNESCAPED_UNICODE);
        exit;
}
