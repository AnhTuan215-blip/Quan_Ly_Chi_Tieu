<?php
/**
 * FinTrack VN - API Báo Cáo Phân Tích Thu Chi
 * Đường dẫn: /api/report_data.php
 */

require_once dirname(__DIR__) . '/config.php';

// Kiểm tra phiên đăng nhập
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Vui lòng đăng nhập!'], JSON_UNESCAPED_UNICODE);
    exit;
}

$userId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 1;
$month = isset($_GET['month']) ? (int)$_GET['month'] : 10;
$year = isset($_GET['year']) ? (int)$_GET['year'] : 2024;
$isExport = isset($_GET['export']) && $_GET['export'] === 'csv';

if ($month < 1 || $month > 12) $month = 10;
if ($year < 2020 || $year > 2030) $year = 2024;

// Hàm định dạng ngày trong tháng
$totalDaysInMonth = (int)date('t', mktime(0, 0, 0, $month, 1, $year));
$dateRangeStr = sprintf("Từ: 01/%02d/%d → Đến: %02d/%02d/%d", $month, $year, $totalDaysInMonth, $month, $year);
$monthLabel = sprintf("Tháng %02d, %d", $month, $year);
$prevMonth = $month - 1;
$prevYear = $year;
if ($prevMonth < 1) {
    $prevMonth = 12;
    $prevYear--;
}
$prevMonthLabel = sprintf("tháng %02d/%d", $prevMonth, $prevYear);

// Dữ liệu chuẩn cơ bản cho Tháng 10/2024 (phù hợp với bản thiết kế FinTrack VN)
$defaultOctData = [
    'summary' => [
        'income' => 42500000,
        'income_change' => 12.0,
        'expense' => 18250000,
        'expense_change' => -5.0,
        'surplus' => 24250000,
        'surplus_ratio' => 57.1,
        'savings_rate' => 57.1,
        'spending_rate' => 45.0,
        'target_exceeded' => 12.1
    ],
    'categories' => [
        [
            'name' => 'Ăn uống',
            'icon' => 'fa-utensils',
            'color' => '#F97316',
            'spent' => 7300000,
            'budget' => 8000000,
            'percentage' => 40.0,
            'ratio' => 91.25,
            'status' => 'Cận hạn mức',
            'status_type' => 'warning'
        ],
        [
            'name' => 'Mua sắm cá nhân',
            'icon' => 'fa-bag-shopping',
            'color' => '#3B82F6',
            'spent' => 3650000,
            'budget' => 4000000,
            'percentage' => 20.0,
            'ratio' => 91.25,
            'status' => 'An toàn',
            'status_type' => 'success'
        ],
        [
            'name' => 'Nhà ở & Hóa đơn',
            'icon' => 'fa-house',
            'color' => '#EAB308',
            'spent' => 3285000,
            'budget' => 3500000,
            'percentage' => 18.0,
            'ratio' => 93.86,
            'status' => 'An toàn',
            'status_type' => 'success'
        ],
        [
            'name' => 'Di chuyển',
            'icon' => 'fa-car',
            'color' => '#06B6D4',
            'spent' => 2190000,
            'budget' => 2500000,
            'percentage' => 12.0,
            'ratio' => 87.60,
            'status' => 'Tiết kiệm tốt',
            'status_type' => 'info'
        ],
        [
            'name' => 'Giải trí & Thể thao',
            'icon' => 'fa-gamepad',
            'color' => '#A855F7',
            'spent' => 1095000,
            'budget' => 1500000,
            'percentage' => 6.0,
            'ratio' => 73.00,
            'status' => 'Tối ưu',
            'status_type' => 'purple'
        ],
        [
            'name' => 'Khoản chi khác',
            'icon' => 'fa-ellipsis',
            'color' => '#94A3B8',
            'spent' => 730000,
            'budget' => 1000000,
            'percentage' => 4.0,
            'ratio' => 73.00,
            'status' => 'An toàn',
            'status_type' => 'success'
        ]
    ],
    'budget_summary' => [
        'total_budget' => 20500000,
        'total_spent' => 18250000,
        'remaining' => 2250000
    ],
    'history_6_months' => [
        'labels' => ['T5', 'T6', 'T7', 'T8', 'T9', 'T10 (Nay)'],
        'income' => [36500000, 38000000, 40500000, 41200000, 37950000, 42500000],
        'expense' => [19800000, 20500000, 18900000, 19200000, 19210000, 18250000],
        'surplus' => [16700000, 17500000, 21600000, 22000000, 18740000, 24250000],
        'avg_income' => 39800000,
        'avg_income_text' => '39.8M đ/tháng',
        'avg_expense' => 19143000,
        'avg_expense_text' => '19.1M đ/tháng',
        'avg_savings_rate' => 52.0,
        'avg_savings_rate_text' => '52.0%'
    ],
    'ai_insights' => [
        [
            'type' => 'positive',
            'badge' => '-15% Chi phí',
            'title' => 'Thói quen tích cực -15% Chi phí',
            'content' => 'Bạn đã cắt giảm được 15% chi phí ăn ngoài trong 2 tuần qua nhờ duy trì nấu ăn tại nhà và giảm gọi đồ uống giao tận nơi, giúp tăng thặng dư thêm ~1.250.000 đ vào quỹ tích lũy.'
        ],
        [
            'type' => 'upcoming',
            'badge' => 'Ưu tiên số 1',
            'title' => 'Khoản chi định kỳ sắp tới - Ưu tiên số 1',
            'content' => 'Khoản chi định kỳ tiếp theo: Tiền thuê nhà (8.500.000 đ) và hóa đơn Internet dự kiến vào ngày 05 tháng tới. Dòng tiền thặng dư hiện tại hoàn toàn đảm bảo thanh toán an toàn.'
        ]
    ]
];

$isDemoAdmin = ($userId === 1);

// Khởi tạo biến trả về (Chỉ tài khoản demo admin ID = 1 mới hiển thị số liệu mẫu nếu chưa có dữ liệu)
if (false) {
    $reportData = $defaultOctData;
} else {
    $reportData = [
        'summary' => [
            'income' => 0,
            'income_change' => 0,
            'expense' => 0,
            'expense_change' => 0,
            'surplus' => 0,
            'surplus_ratio' => 0,
            'savings_rate' => 0,
            'spending_rate' => 0,
            'target_exceeded' => 0
        ],
        'categories' => [],
        'budget_summary' => [
            'total_budget' => 0,
            'total_spent' => 0,
            'remaining' => 0
        ],
        'history_6_months' => [
            'labels' => ['T5', 'T6', 'T7', 'T8', 'T9', 'T10 (Nay)'],
            'income' => [0, 0, 0, 0, 0, 0],
            'expense' => [0, 0, 0, 0, 0, 0],
            'surplus' => [0, 0, 0, 0, 0, 0],
            'avg_income' => 0,
            'avg_income_text' => '0 đ/tháng',
            'avg_expense' => 0,
            'avg_expense_text' => '0 đ/tháng',
            'avg_savings_rate' => 0,
            'avg_savings_rate_text' => '0%'
        ],
        'ai_insights' => [
            [
                'type' => 'info',
                'badge' => 'Bắt đầu',
                'title' => 'Chưa có đủ dữ liệu giao dịch',
                'content' => 'Hãy ghi chép các giao dịch thu chi đầu tiên của bạn để Trợ lý FinTrack AI bắt đầu tổng hợp và đưa ra đề xuất tài chính tối ưu!'
            ]
        ]
    ];
}

// Nếu có kết nối MySQL, kiểm tra và lấy dữ liệu thực từ DB
if (isset($pdo) && $pdo !== null) {
    try {
        // Chỉ tài khoản demo admin ID = 1 mới auto-seed nếu trống
        if (false) {
            $stmtCheck = $pdo->prepare("SELECT COUNT(*) FROM `transactions` WHERE user_id = ? AND MONTH(transaction_date) = 10 AND YEAR(transaction_date) = 2024");
            $stmtCheck->execute([$userId]);
            $txCount = (int)$stmtCheck->fetchColumn();

            if ($txCount < 6) {
                $octTransactions = [
                    ['income', 'Lương tháng 10/2024', 35000000, '2024-10-05', '09:00:00'],
                    ['income', 'Thưởng hiệu suất quý 3', 4500000, '2024-10-15', '14:30:00'],
                    ['income', 'Lợi nhuận cổ tức & đầu tư', 3000000, '2024-10-25', '10:15:00'],
                    ['expense', 'Ăn uống gia đình & nhà hàng', 7300000, '2024-10-12', '19:30:00'],
                    ['expense', 'Mua sắm thời trang & phụ kiện', 3650000, '2024-10-18', '16:00:00'],
                    ['expense', 'Tiền thuê căn hộ & điện nước', 3285000, '2024-10-08', '11:00:00'],
                    ['expense', 'Xăng xe & bảo dưỡng định kỳ', 2190000, '2024-10-14', '08:45:00'],
                    ['expense', 'Xem phim, thể thao & dã ngoại', 1095000, '2024-10-22', '20:15:00'],
                    ['expense', 'Các chi phí phát sinh cá nhân', 730000, '2024-10-28', '15:20:00']
                ];
                try {
                    $cStmt = $pdo->query("SELECT id FROM categories LIMIT 1");
                    $firstCatId = $cStmt->fetchColumn() ?: 1;
                    $pmStmt = $pdo->query("SELECT id FROM payment_methods LIMIT 1");
                    $firstPmId = $pmStmt->fetchColumn() ?: 1;

                    $insTx = $pdo->prepare("
                        INSERT INTO transactions (user_id, type, category_id, payment_method_id, amount, transaction_date, transaction_time, description)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    foreach ($octTransactions as $tx) {
                        $insTx->execute([$userId, $tx[0], $firstCatId, $firstPmId, $tx[2], $tx[3], $tx[4], $tx[1]]);
                    }
                } catch (Exception $e) {}
            }
        }

        // Truy vấn tổng thu nhập tháng này của người dùng
        $stmtInc = $pdo->prepare("
            SELECT COALESCE(SUM(amount), 0) FROM `transactions` 
            WHERE user_id = ?
              AND (type = 'income' OR type = 'thu_nhap')
              AND MONTH(transaction_date) = ? 
              AND YEAR(transaction_date) = ?
        ");
        $stmtInc->execute([$userId, $month, $year]);
        $realIncome = (float)$stmtInc->fetchColumn();

        // Truy vấn tổng chi tiêu tháng này của người dùng
        $stmtExp = $pdo->prepare("
            SELECT COALESCE(SUM(amount), 0) FROM `transactions` 
            WHERE user_id = ?
              AND (type = 'expense' OR type = 'chi_tieu')
              AND MONTH(transaction_date) = ? 
              AND YEAR(transaction_date) = ?
        ");
        $stmtExp->execute([$userId, $month, $year]);
        $realExpense = (float)$stmtExp->fetchColumn();

        // Truy vấn tháng trước để so sánh %
        $stmtPrevInc = $pdo->prepare("
            SELECT COALESCE(SUM(amount), 0) FROM `transactions` 
            WHERE user_id = ?
              AND (type = 'income' OR type = 'thu_nhap')
              AND MONTH(transaction_date) = ? 
              AND YEAR(transaction_date) = ?
        ");
        $stmtPrevInc->execute([$userId, $prevMonth, $prevYear]);
        $prevIncome = (float)$stmtPrevInc->fetchColumn();

        $stmtPrevExp = $pdo->prepare("
            SELECT COALESCE(SUM(amount), 0) FROM `transactions` 
            WHERE user_id = ?
              AND (type = 'expense' OR type = 'chi_tieu')
              AND MONTH(transaction_date) = ? 
              AND YEAR(transaction_date) = ?
        ");
        $stmtPrevExp->execute([$userId, $prevMonth, $prevYear]);
        $prevExpense = (float)$stmtPrevExp->fetchColumn();

        if ($realIncome > 0 || $realExpense > 0) {
            $surplus = $realIncome - $realExpense;
            $incomeChange = ($prevIncome > 0) ? round((($realIncome - $prevIncome) / $prevIncome) * 100, 1) : 0;
            $expenseChange = ($prevExpense > 0) ? round((($realExpense - $prevExpense) / $prevExpense) * 100, 1) : 0;
            $savingsRate = ($realIncome > 0) ? round(($surplus / $realIncome) * 100, 1) : 0;
            $spendingRate = ($realIncome > 0) ? round(($realExpense / $realIncome) * 100, 1) : 0;
            $surplusRatio = $savingsRate;

            $reportData['summary'] = [
                'income' => $realIncome,
                'income_change' => $incomeChange,
                'expense' => $realExpense,
                'expense_change' => $expenseChange,
                'surplus' => $surplus,
                'surplus_ratio' => $surplusRatio,
                'savings_rate' => $savingsRate,
                'spending_rate' => $spendingRate,
                'target_exceeded' => round(max(0, $savingsRate - 45.0), 1)
            ];
        }

        // Lấy danh mục chi tiêu thực tế kèm số tiền chi tiêu
        $catStmt = $pdo->prepare("
            SELECT c.id, c.name, c.icon, c.color, COALESCE(c.budget_limit, 0) AS budget,
                   COALESCE(SUM(t.amount), 0) AS spent
            FROM categories c
            LEFT JOIN transactions t ON t.category_id = c.id 
                 AND t.user_id = ? 
                 AND MONTH(t.transaction_date) = ? 
                 AND YEAR(t.transaction_date) = ? 
                 AND t.type IN ('expense', 'chi_tieu')
            WHERE c.user_id = ? AND c.type IN ('expense', 'chi_tieu') AND c.is_active = 1
            GROUP BY c.id, c.name, c.icon, c.color, c.budget_limit
            ORDER BY spent DESC, budget DESC
        ");
        $catStmt->execute([$userId, $month, $year, $userId]);
        $dbCats = $catStmt->fetchAll();

        if (!empty($dbCats)) {
            $catList = [];
            $totalBudgetSum = 0;
            foreach ($dbCats as $c) {
                $cSpent = (float)$c['spent'];
                $cBudget = (float)$c['budget'];
                $totalBudgetSum += $cBudget;
                $pct = ($realExpense > 0) ? round(($cSpent / $realExpense) * 100, 1) : 0;
                $ratio = ($cBudget > 0) ? round(($cSpent / $cBudget) * 100, 1) : 0;
                
                $status = 'An toàn';
                $statusType = 'success';
                if ($ratio >= 100) {
                    $status = 'Vượt hạn mức';
                    $statusType = 'danger';
                } elseif ($ratio >= 80) {
                    $status = 'Cận hạn mức';
                    $statusType = 'warning';
                } elseif ($cSpent == 0) {
                    $status = 'Chưa chi tiêu';
                    $statusType = 'success';
                }

                $catList[] = [
                    'name' => $c['name'],
                    'icon' => $c['icon'] ?: 'fa-folder',
                    'color' => $c['color'] ?: '#4F59E8',
                    'spent' => $cSpent,
                    'budget' => $cBudget,
                    'percentage' => $pct,
                    'ratio' => $ratio,
                    'status' => $status,
                    'status_type' => $statusType
                ];
            }
            if (!empty($catList)) {
                $reportData['categories'] = $catList;
                $reportData['budget_summary'] = [
                    'total_budget' => $totalBudgetSum,
                    'total_spent' => $realExpense,
                    'remaining' => max(0, $totalBudgetSum - $realExpense)
                ];
            }
        }

    } catch (Exception $e) {
        // Fallback
    }
}

// Xử lý xuất file CSV nếu có request
if ($isExport) {
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="FinTrack_BaoCao_Thang_' . sprintf("%02d_%d", $month, $year) . '.csv"');
    header('Pragma: no-cache');
    header('Expires: 0');

    // Xuất UTF-8 BOM để Excel hiển thị đúng tiếng Việt có dấu
    echo "\xEF\xBB\xBF";

    $output = fopen('php://output', 'w');

    fputcsv($output, ['BÁO CÁO PHÂN TÍCH THU CHI - FINTRACK VN']);
    fputcsv($output, ['Kỳ báo cáo:', $monthLabel, 'Khoảng thời gian:', $dateRangeStr]);
    fputcsv($output, ['Thời gian xuất:', date('d/m/Y H:i:s')]);
    fputcsv($output, []);

    fputcsv($output, ['--- CHỈ SỐ TỔNG HỢP ---']);
    fputcsv($output, ['Chỉ tiêu', 'Giá trị (VNĐ)', 'Biến động / Tỷ lệ']);
    fputcsv($output, ['Tổng thu nhập', number_format($reportData['summary']['income'], 0, ',', '.') . ' đ', '+' . $reportData['summary']['income_change'] . '%']);
    fputcsv($output, ['Tổng chi tiêu', number_format($reportData['summary']['expense'], 0, ',', '.') . ' đ', $reportData['summary']['expense_change'] . '%']);
    fputcsv($output, ['Dòng tiền ròng (Thặng dư)', number_format($reportData['summary']['surplus'], 0, ',', '.') . ' đ', $reportData['summary']['surplus_ratio'] . '% tổng thu']);
    fputcsv($output, ['Tỷ lệ tiết kiệm', $reportData['summary']['savings_rate'] . '%', 'Vượt mục tiêu +' . $reportData['summary']['target_exceeded'] . '%']);
    fputcsv($output, []);

    fputcsv($output, ['--- KIỂM SOÁT NGÂN SÁCH THEO HẠNG MỤC ---']);
    fputcsv($output, ['Hạng mục', 'Thực chi (VNĐ)', 'Ngân sách (VNĐ)', 'Tỷ lệ chi tiêu (%)', 'Tỷ lệ sử dụng hạn mức (%)', 'Trạng thái']);
    foreach ($reportData['categories'] as $cat) {
        fputcsv($output, [
            $cat['name'],
            number_format($cat['spent'], 0, ',', '.') . ' đ',
            number_format($cat['budget'], 0, ',', '.') . ' đ',
            $cat['percentage'] . '%',
            $cat['ratio'] . '%',
            $cat['status']
        ]);
    }
    fputcsv($output, ['TỔNG CỘNG HẠN MỨC', number_format($reportData['budget_summary']['total_spent'], 0, ',', '.') . ' đ', number_format($reportData['budget_summary']['total_budget'], 0, ',', '.') . ' đ', '100%', '', 'Còn dư: ' . number_format($reportData['budget_summary']['remaining'], 0, ',', '.') . ' đ']);
    fputcsv($output, []);

    fputcsv($output, ['--- DÒNG TIỀN 6 THÁNG GẦN NHẤT ---']);
    fputcsv($output, ['Kỳ', 'Thu nhập (VNĐ)', 'Chi tiêu (VNĐ)', 'Thặng dư tích lũy (VNĐ)']);
    $labels = $reportData['history_6_months']['labels'];
    $incomes = $reportData['history_6_months']['income'];
    $expenses = $reportData['history_6_months']['expense'];
    $surpluses = $reportData['history_6_months']['surplus'];
    for ($i = 0; $i < count($labels); $i++) {
        fputcsv($output, [
            $labels[$i],
            number_format($incomes[$i], 0, ',', '.') . ' đ',
            number_format($expenses[$i], 0, ',', '.') . ' đ',
            number_format($surpluses[$i], 0, ',', '.') . ' đ'
        ]);
    }
    fputcsv($output, []);
    fputcsv($output, ['Trung bình thu nhập 6 tháng:', $reportData['history_6_months']['avg_income_text']]);
    fputcsv($output, ['Trung bình chi tiêu 6 tháng:', $reportData['history_6_months']['avg_expense_text']]);
    fputcsv($output, ['Tỷ lệ tiết kiệm bình quân:', $reportData['history_6_months']['avg_savings_rate_text'] . ' (Vững mạnh)']);

    fclose($output);
    exit;
}

// Trả về JSON cho Frontend
header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-cache, no-store, must-revalidate');

echo json_encode([
    'status' => 'success',
    'month' => $month,
    'year' => $year,
    'month_label' => $monthLabel,
    'prev_month_label' => $prevMonthLabel,
    'date_range' => $dateRangeStr,
    'data' => $reportData
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
exit;
