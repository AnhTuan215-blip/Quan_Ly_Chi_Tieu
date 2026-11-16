<?php
/**
 * FinTrack VN - Báo Cáo Phân Tích Thu Chi
 * Trang hiển thị phân tích tài chính chuyên sâu, tỷ lệ tích lũy và kiểm soát danh mục
 */

require_once __DIR__ . '/config.php';

// Kiểm tra quyền đăng nhập
checkAuth();

$userId = (int)($_SESSION['user_id'] ?? 1);

// Lấy tham số tháng và năm từ URL (mặc định Tháng 10, 2024 theo tài liệu thiết kế)
$month = isset($_GET['month']) ? (int)$_GET['month'] : 10;
$year  = isset($_GET['year'])  ? (int)$_GET['year']  : 2024;

if ($month < 1 || $month > 12) $month = 10;
if ($year < 2020 || $year > 2030) $year = 2024;

// Tính toán ngày trong tháng
$totalDaysInMonth = (int)date('t', mktime(0, 0, 0, $month, 1, $year));
$dateRangeStr = sprintf("Từ: 01/%02d/%d → Đến: %02d/%02d/%d", $month, $year, $totalDaysInMonth, $month, $year);
$monthLabel = sprintf("Tháng %02d, %d", $month, $year);

// Tháng trước để so sánh
$prevMonth = $month - 1;
$prevYear = $year;
if ($prevMonth < 1) {
    $prevMonth = 12;
    $prevYear--;
}
$prevMonthText = sprintf("tháng %02d/%d", $prevMonth, $prevYear);

// Dữ liệu mặc định (Chỉ tài khoản demo admin ID = 1 mới có số liệu mẫu demo nếu DB chưa có)
$isDemoAdmin = ($userId === 1);

$income = $isDemoAdmin ? 42500000 : 0;
$incomeChange = $isDemoAdmin ? 12.0 : 0;
$expense = $isDemoAdmin ? 18250000 : 0;
$expenseChange = $isDemoAdmin ? -5.0 : 0;
$surplus = $isDemoAdmin ? 24250000 : 0;
$surplusRatio = $isDemoAdmin ? 57.1 : 0;
$savingsRate = $isDemoAdmin ? 57.1 : 0;
$spendingRate = $isDemoAdmin ? 45.0 : 0;
$targetExceeded = $isDemoAdmin ? 12.1 : 0;

// Danh mục chi tiêu & hạn mức ngân sách
$categories = [];
if ($isDemoAdmin) {
    $categories = [
        [
            'name' => 'Ăn uống',
            'icon' => 'fa-utensils',
            'color' => '#F97316',
            'spent' => 7300000,
            'budget' => 8000000,
            'percentage' => 40.0,
            'ratio' => 91.25,
            'status' => 'Cận hạn mức',
            'badge_class' => 'badge-warning',
            'bar_color' => '#F97316'
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
            'badge_class' => 'badge-success',
            'bar_color' => '#3B82F6'
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
            'badge_class' => 'badge-success',
            'bar_color' => '#EAB308'
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
            'badge_class' => 'badge-info',
            'bar_color' => '#06B6D4'
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
            'badge_class' => 'badge-purple',
            'bar_color' => '#A855F7'
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
            'badge_class' => 'badge-success',
            'bar_color' => '#94A3B8'
        ]
    ];
}

$totalBudget = $isDemoAdmin ? 20500000 : 0;
$budgetRemaining = max(0, $totalBudget - $expense);

// Dữ liệu 6 tháng gần nhất
$sixMonthsData = [
    'labels' => ['T5', 'T6', 'T7', 'T8', 'T9', 'T10 (Nay)'],
    'income' => $isDemoAdmin ? [36500000, 38000000, 40500000, 41200000, 37950000, 42500000] : [0, 0, 0, 0, 0, 0],
    'expense' => $isDemoAdmin ? [19800000, 20500000, 18900000, 19200000, 19210000, 18250000] : [0, 0, 0, 0, 0, 0],
    'surplus' => $isDemoAdmin ? [16700000, 17500000, 21600000, 22000000, 18740000, 24250000] : [0, 0, 0, 0, 0, 0],
    'avg_income' => $isDemoAdmin ? '39.8M đ/tháng' : '0 đ/tháng',
    'avg_expense' => $isDemoAdmin ? '19.1M đ/tháng' : '0 đ/tháng',
    'avg_savings_rate' => $isDemoAdmin ? '52.0%' : '0%'
];

// Nếu có kết nối MySQL thì truy vấn dữ liệu thực tế của chính người dùng
if (isset($pdo) && $pdo !== null) {
    try {
        // Tổng thu nhập tháng đã chọn
        $stmtInc = $pdo->prepare("
            SELECT COALESCE(SUM(amount), 0) FROM `transactions` 
            WHERE user_id = ?
              AND (type = 'income' OR type = 'thu_nhap')
              AND MONTH(transaction_date) = ? 
              AND YEAR(transaction_date) = ?
        ");
        $stmtInc->execute([$userId, $month, $year]);
        $dbIncome = (float)$stmtInc->fetchColumn();

        // Tổng chi tiêu tháng đã chọn
        $stmtExp = $pdo->prepare("
            SELECT COALESCE(SUM(amount), 0) FROM `transactions` 
            WHERE user_id = ?
              AND (type = 'expense' OR type = 'chi_tieu')
              AND MONTH(transaction_date) = ? 
              AND YEAR(transaction_date) = ?
        ");
        $stmtExp->execute([$userId, $month, $year]);
        $dbExpense = (float)$stmtExp->fetchColumn();

        $income = $dbIncome;
        $expense = $dbExpense;
        $surplus = $income - $expense;
        $savingsRate = ($income > 0) ? round(($surplus / $income) * 100, 1) : 0;
        $spendingRate = ($income > 0) ? round(($expense / $income) * 100, 1) : 0;
        $surplusRatio = $savingsRate;

        // So sánh với tháng trước
        $stmtPrevInc = $pdo->prepare("
            SELECT COALESCE(SUM(amount), 0) FROM `transactions` 
            WHERE user_id = ?
              AND (type = 'income' OR type = 'thu_nhap')
              AND MONTH(transaction_date) = ? 
              AND YEAR(transaction_date) = ?
        ");
        $stmtPrevInc->execute([$userId, $prevMonth, $prevYear]);
        $dbPrevIncome = (float)$stmtPrevInc->fetchColumn();

        $stmtPrevExp = $pdo->prepare("
            SELECT COALESCE(SUM(amount), 0) FROM `transactions` 
            WHERE user_id = ?
              AND (type = 'expense' OR type = 'chi_tieu')
              AND MONTH(transaction_date) = ? 
              AND YEAR(transaction_date) = ?
        ");
        $stmtPrevExp->execute([$userId, $prevMonth, $prevYear]);
        $dbPrevExpense = (float)$stmtPrevExp->fetchColumn();

        if ($dbPrevIncome > 0) {
            $incomeChange = round((($income - $dbPrevIncome) / $dbPrevIncome) * 100, 1);
        }
        if ($dbPrevExpense > 0) {
            $expenseChange = round((($expense - $dbPrevExpense) / $dbPrevExpense) * 100, 1);
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
                $pct = ($expense > 0) ? round(($cSpent / $expense) * 100, 1) : 0;
                $ratio = ($cBudget > 0) ? round(($cSpent / $cBudget) * 100, 1) : 0;
                
                $status = 'An toàn';
                $badgeClass = 'badge-success';
                if ($ratio >= 100) {
                    $status = 'Vượt hạn mức';
                    $badgeClass = 'badge-danger';
                } elseif ($ratio >= 80) {
                    $status = 'Cận hạn mức';
                    $badgeClass = 'badge-warning';
                } elseif ($cSpent == 0) {
                    $status = 'Chưa chi tiêu';
                    $badgeClass = 'badge-success';
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
                    'badge_class' => $badgeClass,
                    'bar_color' => $c['color'] ?: '#4F59E8'
                ];
            }
            if (!empty($catList)) {
                $categories = $catList;
                $totalBudget = $totalBudgetSum;
                $budgetRemaining = max(0, $totalBudget - $expense);
            }
        }

    } catch (Exception $e) {
        // Giữ dữ liệu thiết kế mặc định nếu truy vấn gặp lỗi
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Báo Cáo Phân Tích Thu Chi - FinTrack VN</title>
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <!-- Font Awesome 6 Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <!-- Chart.js CDN -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <style>
        :root {
            --primary: #4F46E5;
            --primary-hover: #4338CA;
            --primary-light: #EEF2FF;
            --secondary: #0F172A;
            --success: #10B981;
            --success-light: #ECFDF5;
            --danger: #EF4444;
            --danger-light: #FEF2F2;
            --warning: #F59E0B;
            --warning-light: #FFFBEB;
            --info: #06B6D4;
            --info-light: #ECFEFF;
            --purple: #8B5CF6;
            --purple-light: #F5F3FF;
            --gray-50: #F8FAFC;
            --gray-100: #F1F5F9;
            --gray-200: #E2E8F0;
            --gray-300: #CBD5E1;
            --gray-400: #94A3B8;
            --gray-500: #64748B;
            --gray-600: #475569;
            --gray-700: #334155;
            --gray-800: #1E293B;
            --gray-900: #0F172A;
            --card-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.05), 0 1px 2px -1px rgba(0, 0, 0, 0.05);
            --card-shadow-hover: 0 10px 15px -3px rgba(0, 0, 0, 0.07), 0 4px 6px -4px rgba(0, 0, 0, 0.05);
            --radius-lg: 16px;
            --radius-md: 12px;
            --radius-sm: 8px;
            --font-main: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: var(--font-main);
        }

        body {
            background-color: #F8FAFC;
            color: var(--gray-800);
            min-height: 100vh;
            display: flex;
        }

        /* --- SIDEBAR --- */
        .sidebar {
            width: 260px;
            background: #FFFFFF;
            border-right: 1px solid var(--gray-200);
            display: flex;
            flex-direction: column;
            position: fixed;
            top: 0;
            bottom: 0;
            left: 0;
            z-index: 100;
        }

        .sidebar-brand {
            padding: 24px 20px;
            display: flex;
            align-items: center;
            gap: 12px;
            border-bottom: 1px solid var(--gray-100);
        }

        .brand-logo-icon {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, #4F46E5 0%, #7C3AED 100%);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #FFFFFF;
            font-size: 20px;
            box-shadow: 0 4px 10px rgba(79, 70, 229, 0.3);
        }

        .brand-text h2 {
            font-size: 18px;
            font-weight: 800;
            color: var(--gray-900);
            letter-spacing: -0.5px;
            line-height: 1.2;
        }

        .brand-text span {
            color: var(--primary);
        }

        .brand-text p {
            font-size: 11px;
            color: var(--gray-400);
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .sidebar-menu {
            list-style: none;
            padding: 20px 14px;
            flex: 1;
            overflow-y: auto;
        }

        .menu-header {
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--gray-400);
            font-weight: 700;
            padding: 8px 12px;
            margin-top: 6px;
            margin-bottom: 4px;
        }

        .sidebar-menu li {
            margin-bottom: 4px;
        }

        .sidebar-menu a {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 11px 14px;
            color: var(--gray-600);
            text-decoration: none;
            font-size: 14px;
            font-weight: 600;
            border-radius: var(--radius-sm);
            transition: all 0.2s ease;
        }

        .sidebar-menu a i {
            width: 20px;
            font-size: 16px;
            text-align: center;
            color: var(--gray-400);
            transition: color 0.2s ease;
        }

        .sidebar-menu a:hover {
            background-color: var(--gray-50);
            color: var(--primary);
        }

        .sidebar-menu a:hover i {
            color: var(--primary);
        }

        .sidebar-menu li.active a {
            background-color: var(--primary-light);
            color: var(--primary);
            font-weight: 700;
        }

        .sidebar-menu li.active a i {
            color: var(--primary);
        }

        .sidebar-user {
            padding: 16px 20px;
            border-top: 1px solid var(--gray-100);
            display: flex;
            align-items: center;
            gap: 12px;
            background: #FFFFFF;
        }

        .sidebar-user img {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid var(--gray-200);
        }

        .user-info {
            flex: 1;
            min-width: 0;
        }

        .user-name {
            font-size: 13.5px;
            font-weight: 700;
            color: var(--gray-900);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .user-badge {
            display: inline-block;
            font-size: 10.5px;
            font-weight: 700;
            color: var(--primary);
            background: var(--primary-light);
            padding: 1px 7px;
            border-radius: 20px;
            margin-top: 2px;
        }

        /* --- MAIN WRAPPER --- */
        .main-wrapper {
            margin-left: 260px;
            flex: 1;
            display: flex;
            flex-direction: column;
            min-width: 0;
        }

        /* --- HEADER / TOPBAR --- */
        .topbar {
            height: 70px;
            background: #FFFFFF;
            border-bottom: 1px solid var(--gray-200);
            padding: 0 32px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: sticky;
            top: 0;
            z-index: 99;
        }

        .topbar-search {
            position: relative;
            width: 380px;
        }

        .topbar-search input {
            width: 100%;
            height: 40px;
            background: var(--gray-50);
            border: 1px solid var(--gray-200);
            border-radius: 20px;
            padding: 0 16px 0 40px;
            font-size: 13.5px;
            color: var(--gray-700);
            outline: none;
            transition: all 0.2s ease;
        }

        .topbar-search input:focus {
            background: #FFFFFF;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
        }

        .topbar-search i {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--gray-400);
            font-size: 14px;
        }

        .topbar-actions {
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .topbar-btn {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            background: var(--gray-50);
            border: 1px solid var(--gray-200);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--gray-600);
            cursor: pointer;
            position: relative;
            transition: all 0.2s ease;
        }

        .topbar-btn:hover {
            background: var(--gray-100);
            color: var(--primary);
        }

        .topbar-btn .noti-dot {
            position: absolute;
            top: 9px;
            right: 9px;
            width: 8px;
            height: 8px;
            background: var(--danger);
            border-radius: 50%;
            border: 2px solid #FFFFFF;
        }

        .topbar-date {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 13px;
            font-weight: 600;
            color: var(--gray-600);
            background: var(--gray-50);
            padding: 8px 14px;
            border-radius: 20px;
            border: 1px solid var(--gray-200);
        }

        .topbar-date i {
            color: var(--primary);
        }

        /* --- CONTENT CONTAINER --- */
        .content-container {
            padding: 32px;
            max-width: 1440px;
            width: 100%;
            margin: 0 auto;
        }

        /* --- PAGE HEADER --- */
        .page-header {
            margin-bottom: 28px;
        }

        .header-badge-wrap {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 8px;
        }

        .badge-live {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: #EFF6FF;
            color: #2563EB;
            font-size: 11.5px;
            font-weight: 700;
            letter-spacing: 0.4px;
            padding: 4px 12px;
            border-radius: 20px;
            border: 1px solid rgba(37, 99, 235, 0.2);
        }

        .pulse-dot {
            width: 6px;
            height: 6px;
            background: #2563EB;
            border-radius: 50%;
            animation: pulse 1.8s infinite;
        }

        @keyframes pulse {
            0% { transform: scale(0.9); opacity: 0.8; }
            50% { transform: scale(1.4); opacity: 1; }
            100% { transform: scale(0.9); opacity: 0.8; }
        }

        .header-content-row {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 24px;
            flex-wrap: wrap;
        }

        .header-titles h1 {
            font-size: 28px;
            font-weight: 800;
            color: var(--gray-900);
            letter-spacing: -0.6px;
            margin-bottom: 6px;
        }

        .header-titles p {
            font-size: 14.5px;
            color: var(--gray-500);
            max-width: 620px;
            line-height: 1.5;
        }

        .header-controls {
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
        }

        .month-selector-card {
            display: flex;
            flex-direction: column;
            gap: 4px;
            background: #FFFFFF;
            padding: 8px 16px;
            border-radius: var(--radius-md);
            border: 1px solid var(--gray-200);
            box-shadow: var(--card-shadow);
        }

        .month-selector-title {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 8px;
        }

        .month-select-dropdown {
            border: none;
            background: transparent;
            font-size: 14.5px;
            font-weight: 700;
            color: var(--gray-900);
            outline: none;
            cursor: pointer;
        }

        .date-range-text {
            font-size: 11.5px;
            color: var(--gray-500);
            font-weight: 500;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 18px;
            font-size: 13.5px;
            font-weight: 600;
            border-radius: var(--radius-md);
            cursor: pointer;
            text-decoration: none;
            border: 1px solid transparent;
            transition: all 0.2s ease;
            white-space: nowrap;
        }

        .btn-green {
            background-color: #10B981;
            color: #FFFFFF;
            box-shadow: 0 2px 6px rgba(16, 185, 129, 0.25);
        }

        .btn-green:hover {
            background-color: #059669;
            transform: translateY(-1px);
            box-shadow: 0 4px 10px rgba(16, 185, 129, 0.35);
        }

        .btn-dark {
            background-color: #0F172A;
            color: #FFFFFF;
            box-shadow: 0 2px 6px rgba(15, 23, 42, 0.2);
        }

        .btn-dark:hover {
            background-color: #1E293B;
            transform: translateY(-1px);
            box-shadow: 0 4px 10px rgba(15, 23, 42, 0.3);
        }

        .btn-outline {
            background-color: #FFFFFF;
            border-color: var(--gray-300);
            color: var(--gray-700);
        }

        .btn-outline:hover {
            background-color: var(--gray-50);
            border-color: var(--gray-400);
            color: var(--gray-900);
        }

        /* --- 4 SUMMARY CARDS --- */
        .summary-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin-bottom: 28px;
        }

        .summary-card {
            background: #FFFFFF;
            border: 1px solid var(--gray-200);
            border-radius: var(--radius-lg);
            padding: 22px 20px;
            box-shadow: var(--card-shadow);
            position: relative;
            overflow: hidden;
            transition: all 0.25s ease;
        }

        .summary-card:hover {
            transform: translateY(-3px);
            box-shadow: var(--card-shadow-hover);
        }

        .card-top {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 14px;
        }

        .card-title {
            font-size: 13.5px;
            font-weight: 600;
            color: var(--gray-500);
        }

        .card-icon-pill {
            width: 38px;
            height: 38px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
        }

        .icon-income {
            background: var(--success-light);
            color: var(--success);
        }

        .icon-expense {
            background: var(--danger-light);
            color: var(--danger);
        }

        .icon-surplus {
            background: #EFF6FF;
            color: var(--primary);
        }

        .icon-savings {
            background: var(--purple-light);
            color: var(--purple);
        }

        .card-value {
            font-size: 24px;
            font-weight: 800;
            color: var(--gray-900);
            letter-spacing: -0.5px;
            margin-bottom: 8px;
        }

        .val-danger {
            color: var(--danger);
        }

        .val-success {
            color: var(--success);
        }

        .card-trend {
            font-size: 12.5px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 5px;
            flex-wrap: wrap;
        }

        .trend-up {
            color: var(--success);
        }

        .trend-down {
            color: var(--danger);
        }

        .trend-neutral {
            color: var(--gray-500);
        }

        .badge-target {
            background: var(--success-light);
            color: #047857;
            font-size: 11px;
            font-weight: 700;
            padding: 3px 8px;
            border-radius: 20px;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }

        .spending-ratio-info {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 11.5px;
            color: var(--gray-400);
            margin-top: 4px;
        }

        /* --- EXPENSE STRUCTURE SECTION (2 COLUMNS) --- */
        .section-two-cols {
            display: grid;
            grid-template-columns: 420px 1fr;
            gap: 24px;
            margin-bottom: 28px;
        }

        .content-card {
            background: #FFFFFF;
            border: 1px solid var(--gray-200);
            border-radius: var(--radius-lg);
            padding: 24px;
            box-shadow: var(--card-shadow);
        }

        .card-header-flex {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 20px;
            border-bottom: 1px solid var(--gray-100);
            padding-bottom: 14px;
        }

        .card-header-flex h3 {
            font-size: 16.5px;
            font-weight: 700;
            color: var(--gray-900);
            letter-spacing: -0.3px;
        }

        .header-sub-val {
            font-size: 13px;
            font-weight: 600;
            color: var(--danger);
            background: var(--danger-light);
            padding: 4px 10px;
            border-radius: 20px;
        }

        /* Donut Chart Box */
        .donut-chart-container {
            position: relative;
            width: 220px;
            height: 220px;
            margin: 10px auto 20px auto;
        }

        .donut-center-overlay {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            text-align: center;
            pointer-events: none;
        }

        .donut-center-overlay .center-label {
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            color: var(--gray-400);
            letter-spacing: 0.5px;
        }

        .donut-center-overlay .center-highlight {
            font-size: 20px;
            font-weight: 800;
            color: #F97316;
            line-height: 1.2;
        }

        .donut-center-overlay .center-sub {
            font-size: 11px;
            font-weight: 600;
            color: var(--gray-500);
        }

        .legend-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 10px 16px;
            padding-top: 14px;
            border-top: 1px solid var(--gray-100);
        }

        .legend-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            font-size: 12.5px;
        }

        .legend-left {
            display: flex;
            align-items: center;
            gap: 8px;
            color: var(--gray-700);
            font-weight: 500;
        }

        .legend-dot {
            width: 9px;
            height: 9px;
            border-radius: 50%;
            flex-shrink: 0;
        }

        .legend-pct {
            font-weight: 700;
            color: var(--gray-900);
        }

        /* Budget Tracking Table */
        .table-responsive {
            overflow-x: auto;
        }

        .budget-table {
            width: 100%;
            border-collapse: collapse;
            text-align: left;
            font-size: 13.5px;
        }

        .budget-table th {
            padding: 12px 14px;
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: var(--gray-400);
            background: var(--gray-50);
            border-radius: var(--radius-sm);
        }

        .budget-table td {
            padding: 14px;
            border-bottom: 1px solid var(--gray-100);
            color: var(--gray-700);
            vertical-align: middle;
        }

        .budget-table tr:last-child td {
            border-bottom: none;
        }

        .category-name-cell {
            display: flex;
            align-items: center;
            gap: 10px;
            font-weight: 600;
            color: var(--gray-900);
        }

        .cat-icon-wrap {
            width: 32px;
            height: 32px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 13px;
        }

        .progress-bar-wrap {
            width: 130px;
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .progress-track {
            width: 100%;
            height: 7px;
            background: var(--gray-100);
            border-radius: 10px;
            overflow: hidden;
        }

        .progress-fill {
            height: 100%;
            border-radius: 10px;
            transition: width 0.6s ease;
        }

        .progress-text {
            font-size: 11px;
            font-weight: 700;
            color: var(--gray-600);
            text-align: right;
        }

        .badge-status {
            display: inline-block;
            font-size: 11.5px;
            font-weight: 700;
            padding: 4px 10px;
            border-radius: 20px;
            white-space: nowrap;
        }

        .badge-warning {
            background: #FFF7ED;
            color: #C2410C;
            border: 1px solid #FFEDD5;
        }

        .badge-success {
            background: #ECFDF5;
            color: #047857;
            border: 1px solid #D1FAE5;
        }

        .badge-info {
            background: #EFF6FF;
            color: #1D4ED8;
            border: 1px solid #DBEAFE;
        }

        .badge-purple {
            background: #F5F3FF;
            color: #6D28D9;
            border: 1px solid #EDE9FE;
        }

        .budget-footer-summary {
            margin-top: 16px;
            padding: 14px 16px;
            background: var(--gray-50);
            border-radius: var(--radius-md);
            border: 1px dashed var(--gray-300);
            display: flex;
            align-items: center;
            justify-content: space-between;
            font-size: 13px;
            font-weight: 600;
            color: var(--gray-700);
            flex-wrap: wrap;
            gap: 12px;
        }

        .budget-footer-summary strong {
            color: var(--gray-900);
            font-weight: 800;
        }

        .footer-highlight-green {
            color: var(--success);
            font-weight: 800;
        }

        /* --- 6-MONTH COMPARISON BAR CHART --- */
        .chart-section {
            margin-bottom: 28px;
        }

        .chart-header-sub {
            font-size: 13px;
            color: var(--gray-500);
            margin-top: 3px;
            font-weight: 500;
        }

        .chart-legend-custom {
            display: flex;
            align-items: center;
            gap: 20px;
            flex-wrap: wrap;
        }

        .legend-tag {
            display: flex;
            align-items: center;
            gap: 7px;
            font-size: 12.5px;
            font-weight: 600;
            color: var(--gray-600);
        }

        .legend-box {
            width: 14px;
            height: 10px;
            border-radius: 2px;
        }

        .legend-line {
            width: 16px;
            height: 3px;
            border-radius: 2px;
            background: var(--success);
        }

        .bar-chart-container {
            position: relative;
            height: 340px;
            width: 100%;
            margin-top: 15px;
        }

        /* --- BOTTOM SUMMARY (3 STAT CARDS) --- */
        .three-stats-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
            margin-top: 24px;
            padding-top: 20px;
            border-top: 1px solid var(--gray-100);
        }

        .stat-item-box {
            background: var(--gray-50);
            padding: 16px 20px;
            border-radius: var(--radius-md);
            border: 1px solid var(--gray-200);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .stat-item-left .stat-title {
            font-size: 12.5px;
            color: var(--gray-500);
            font-weight: 600;
            margin-bottom: 4px;
        }

        .stat-item-left .stat-number {
            font-size: 18px;
            font-weight: 800;
            color: var(--gray-900);
        }

        .badge-solid-green {
            background: #10B981;
            color: #FFFFFF;
            font-size: 11px;
            font-weight: 700;
            padding: 4px 10px;
            border-radius: 20px;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }

        /* --- AI ANALYSIS SECTION --- */
        .ai-section {
            margin-bottom: 28px;
            background: linear-gradient(145deg, #FFFFFF 0%, #F5F3FF 100%);
            border: 1px solid #DDD6FE;
            position: relative;
        }

        .ai-badge {
            background: #EDE9FE;
            color: #6D28D9;
            font-size: 11.5px;
            font-weight: 700;
            padding: 3px 10px;
            border-radius: 20px;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .ai-link {
            font-size: 13.5px;
            font-weight: 700;
            color: var(--primary);
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            transition: gap 0.2s ease;
        }

        .ai-link:hover {
            gap: 8px;
            text-decoration: underline;
        }

        .ai-cards-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
            margin-top: 18px;
        }

        .ai-card {
            background: #FFFFFF;
            border-radius: var(--radius-md);
            padding: 20px;
            border: 1px solid #E2E8F0;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.03);
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .ai-card-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .ai-card-title {
            font-size: 14.5px;
            font-weight: 700;
            color: var(--gray-900);
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .ai-card-title i {
            color: var(--primary);
        }

        .ai-tag-pill {
            font-size: 11px;
            font-weight: 700;
            padding: 2px 8px;
            border-radius: 12px;
        }

        .tag-green {
            background: #ECFDF5;
            color: #059669;
        }

        .tag-orange {
            background: #FFF7ED;
            color: #C2410C;
        }

        .ai-card-body {
            font-size: 13.5px;
            color: var(--gray-600);
            line-height: 1.55;
        }

        /* --- BOTTOM ACTIONS BAR --- */
        .bottom-bar {
            background: #FFFFFF;
            border: 1px solid var(--gray-200);
            border-radius: var(--radius-lg);
            padding: 16px 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            box-shadow: var(--card-shadow);
            flex-wrap: wrap;
            gap: 16px;
        }

        .security-note {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 13px;
            color: var(--gray-500);
            font-weight: 500;
        }

        .security-note i {
            color: var(--success);
            font-size: 16px;
        }

        .bottom-buttons {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        /* --- MODAL STYLES --- */
        .modal-backdrop {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(15, 23, 42, 0.5);
            backdrop-filter: blur(4px);
            z-index: 1000;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .modal-box {
            background: #FFFFFF;
            border-radius: var(--radius-lg);
            width: 100%;
            max-width: 540px;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 8px 10px -6px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            animation: modalFadeIn 0.25s ease-out;
        }

        @keyframes modalFadeIn {
            from { opacity: 0; transform: scale(0.95); }
            to { opacity: 1; transform: scale(1); }
        }

        .modal-header {
            padding: 20px 24px;
            border-bottom: 1px solid var(--gray-100);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .modal-header h3 {
            font-size: 17px;
            font-weight: 700;
            color: var(--gray-900);
        }

        .modal-close-btn {
            background: transparent;
            border: none;
            font-size: 18px;
            color: var(--gray-400);
            cursor: pointer;
            padding: 4px;
        }

        .modal-close-btn:hover {
            color: var(--gray-700);
        }

        .modal-body {
            padding: 24px;
            font-size: 14px;
            color: var(--gray-700);
            line-height: 1.6;
        }

        .modal-footer {
            padding: 16px 24px;
            background: var(--gray-50);
            border-top: 1px solid var(--gray-100);
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }

        /* --- TOAST NOTIFICATION --- */
        .toast-notification {
            position: fixed;
            bottom: 24px;
            right: 24px;
            background: #0F172A;
            color: #FFFFFF;
            padding: 12px 20px;
            border-radius: var(--radius-md);
            font-size: 13.5px;
            font-weight: 600;
            box-shadow: 0 10px 15px -3px rgba(0,0,0,0.2);
            display: flex;
            align-items: center;
            gap: 10px;
            z-index: 2000;
            transform: translateY(100px);
            opacity: 0;
            transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
        }

        .toast-notification.show {
            transform: translateY(0);
            opacity: 1;
        }

        /* --- RESPONSIVE BREAKPOINTS --- */
        @media (max-width: 1200px) {
            .summary-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            .section-two-cols {
                grid-template-columns: 1fr;
            }
            .donut-chart-container {
                width: 240px;
                height: 240px;
            }
        }

        @media (max-width: 900px) {
            .sidebar {
                width: 70px;
            }
            .sidebar-brand .brand-text,
            .sidebar-menu a span,
            .menu-header,
            .sidebar-user .user-info {
                display: none;
            }
            .sidebar-menu a {
                justify-content: center;
                padding: 14px 0;
            }
            .sidebar-brand {
                justify-content: center;
                padding: 18px 0;
            }
            .main-wrapper {
                margin-left: 70px;
            }
            .three-stats-grid {
                grid-template-columns: 1fr;
            }
            .ai-cards-grid {
                grid-template-columns: 1fr;
            }
            .topbar-search {
                width: 200px;
            }
        }

        @media (max-width: 680px) {
            .content-container {
                padding: 16px;
            }
            .summary-grid {
                grid-template-columns: 1fr;
            }
            .header-content-row {
                flex-direction: column;
            }
            .header-controls {
                width: 100%;
                justify-content: flex-start;
            }
            .topbar {
                padding: 0 16px;
            }
            .topbar-search {
                display: none;
            }
            .bottom-bar {
                flex-direction: column;
                align-items: flex-start;
            }
            .bottom-buttons {
                width: 100%;
                flex-wrap: wrap;
            }
            .bottom-buttons .btn {
                flex: 1;
            }
        }

        /* --- PRINT STYLES --- */
        @media print {
            .sidebar, .topbar, .header-controls, .bottom-bar, .modal-backdrop, .toast-notification {
                display: none !important;
            }
            .main-wrapper {
                margin-left: 0 !important;
            }
            .content-container {
                padding: 0 !important;
                max-width: 100% !important;
            }
            body {
                background: #FFFFFF !important;
            }
            .content-card, .summary-card {
                border: 1px solid #CCC !important;
                box-shadow: none !important;
                page-break-inside: avoid;
            }
        }
    </style>
</head>
<body>

    <!-- ==================== SIDEBAR ==================== -->
    <aside class="sidebar">
        <div class="sidebar-brand">
            <div class="brand-logo-icon">
                <i class="fa-solid fa-chart-simple"></i>
            </div>
            <div class="brand-text">
                <h2>FinTrack <span>VN</span></h2>
                <p>Quản Lý Tài Chính</p>
            </div>
        </div>

        <ul class="sidebar-menu">
            <li class="menu-header">Menu Chính</li>
            <li>
                <a href="index.php">
                    <i class="fa-solid fa-house"></i>
                    <span>Trang chủ</span>
                </a>
            </li>
            <li>
                <a href="calendar.php">
                    <i class="fa-solid fa-calendar-days"></i>
                    <span>Lịch giao dịch</span>
                </a>
            </li>
            <li>
                <a href="add_transaction.php">
                    <i class="fa-solid fa-circle-plus"></i>
                    <span>Thêm giao dịch</span>
                </a>
            </li>
            <li class="active">
                <a href="reports.php">
                    <i class="fa-solid fa-chart-column"></i>
                    <span>Báo cáo & Phân tích</span>
                </a>
            </li>

            <li class="menu-header">Hệ Thống</li>
            <li>
                <a href="settings.php">
                    <i class="fa-solid fa-gear"></i>
                    <span>Cài đặt</span>
                </a>
            </li>
            <li>
                <a href="logout.php">
                    <i class="fa-solid fa-arrow-right-from-bracket"></i>
                    <span>Đăng xuất</span>
                </a>
            </li>
        </ul>

        <div class="sidebar-user">
            <img src="https://images.unsplash.com/photo-1535713875002-d1d0cf377fde?auto=format&fit=crop&w=200&q=80" alt="Avatar">
            <div class="user-info">
                <div class="user-name"><?= htmlspecialchars($_SESSION['user_name'] ?? 'Nguyễn Tuấn Anh') ?></div>
                <div class="user-badge"><i class="fa-solid fa-crown" style="font-size: 9px; margin-right: 2px;"></i> PRO Plus</div>
            </div>
        </div>
    </aside>

    <!-- ==================== MAIN CONTENT ==================== -->
    <div class="main-wrapper">

        <!-- Topbar -->
        <header class="topbar">
            <div class="topbar-search">
                <i class="fa-solid fa-magnifying-glass"></i>
                <input type="text" placeholder="Tìm kiếm chỉ số, báo cáo, hạng mục chi tiêu..." id="quickSearchInput">
            </div>

            <div class="topbar-actions">
                <div class="topbar-date">
                    <i class="fa-regular fa-calendar"></i>
                    <span><?= date('l, d/m/Y') ?></span>
                </div>

                <div class="topbar-btn" title="Thông báo" onclick="showToast('Bạn có 3 thông báo tài chính mới chưa đọc.')">
                    <i class="fa-regular fa-bell"></i>
                    <span class="noti-dot"></span>
                </div>

                <a href="settings.php" class="topbar-btn" title="Cài đặt">
                    <i class="fa-solid fa-sliders"></i>
                </a>
            </div>
        </header>

        <!-- Main Content Area -->
        <main class="content-container">

            <!-- PAGE HEADER -->
            <div class="page-header">
                <div class="header-badge-wrap">
                    <span class="badge-live">
                        <span class="pulse-dot"></span>
                        PHÂN TÍCH CHUYÊN SÂU • Cập nhật 10 phút trước
                    </span>
                </div>

                <div class="header-content-row">
                    <div class="header-titles">
                        <h1>Báo Cáo Phân Tích Thu Chi</h1>
                        <p>Tổng hợp hiệu suất tài chính cá nhân, tỷ lệ tích lũy và kiểm soát danh mục chi tiết.</p>
                    </div>

                    <div class="header-controls">
                        <!-- Month Selector Card -->
                        <div class="month-selector-card">
                            <div class="month-selector-title">
                                <i class="fa-regular fa-calendar-check" style="color: var(--primary);"></i>
                                <select class="month-select-dropdown" id="monthSelector" onchange="changeReportPeriod()">
                                    <option value="10-2024" <?= ($month == 10 && $year == 2024) ? 'selected' : '' ?>>Tháng 10, 2024</option>
                                    <option value="9-2024" <?= ($month == 9 && $year == 2024) ? 'selected' : '' ?>>Tháng 09, 2024</option>
                                    <option value="8-2024" <?= ($month == 8 && $year == 2024) ? 'selected' : '' ?>>Tháng 08, 2024</option>
                                    <option value="7-2024" <?= ($month == 7 && $year == 2024) ? 'selected' : '' ?>>Tháng 07, 2024</option>
                                    <option value="6-2024" <?= ($month == 6 && $year == 2024) ? 'selected' : '' ?>>Tháng 06, 2024</option>
                                    <option value="5-2024" <?= ($month == 5 && $year == 2024) ? 'selected' : '' ?>>Tháng 05, 2024</option>
                                </select>
                            </div>
                            <span class="date-range-text" id="dateRangeDisplay"><?= $dateRangeStr ?></span>
                        </div>

                        <!-- Export Buttons -->
                        <a href="api/report_data.php?export=csv&month=<?= $month ?>&year=<?= $year ?>" class="btn btn-green" id="btnExportCsv">
                            <i class="fa-solid fa-file-excel"></i>
                            <span>Xuất Excel / CSV</span>
                        </a>

                        <button type="button" class="btn btn-dark" onclick="window.print()">
                            <i class="fa-solid fa-file-pdf"></i>
                            <span>Tải PDF tóm tắt</span>
                        </button>
                    </div>
                </div>
            </div>

            <!-- 4 SUMMARY CARDS -->
            <div class="summary-grid">
                <!-- Card 1: Tổng thu nhập -->
                <div class="summary-card">
                    <div class="card-top">
                        <span class="card-title">Tổng thu nhập</span>
                        <div class="card-icon-pill icon-income">
                            <i class="fa-solid fa-arrow-trend-up"></i>
                        </div>
                    </div>
                    <div class="card-value"><?= number_format($income, 0, ',', '.') ?> đ</div>
                    <div class="card-trend trend-up">
                        <i class="fa-solid fa-arrow-up"></i>
                        <span>+<?= number_format($incomeChange, 1) ?>% so với <?= $prevMonthText ?></span>
                    </div>
                </div>

                <!-- Card 2: Tổng chi tiêu -->
                <div class="summary-card">
                    <div class="card-top">
                        <span class="card-title">Tổng chi tiêu</span>
                        <div class="card-icon-pill icon-expense">
                            <i class="fa-solid fa-arrow-trend-down"></i>
                        </div>
                    </div>
                    <div class="card-value val-danger"><?= number_format($expense, 0, ',', '.') ?> đ</div>
                    <div class="card-trend trend-down">
                        <i class="fa-solid fa-arrow-down"></i>
                        <span><?= number_format($expenseChange, 1) ?>% so với <?= $prevMonthText ?></span>
                    </div>
                </div>

                <!-- Card 3: Dòng tiền ròng / Thặng dư -->
                <div class="summary-card">
                    <div class="card-top">
                        <span class="card-title">Dòng tiền ròng / Thặng dư</span>
                        <div class="card-icon-pill icon-surplus">
                            <i class="fa-solid fa-vault"></i>
                        </div>
                    </div>
                    <div class="card-value val-success">+<?= number_format($surplus, 0, ',', '.') ?> đ</div>
                    <div class="card-trend trend-up">
                        <span>Thặng dư đối đảo <?= number_format($surplusRatio, 1) ?>% tổng thu</span>
                    </div>
                </div>

                <!-- Card 4: Tỷ lệ tiết kiệm -->
                <div class="summary-card">
                    <div class="card-top">
                        <span class="card-title">Tỷ lệ tiết kiệm</span>
                        <div class="card-icon-pill icon-savings">
                            <i class="fa-solid fa-chart-pie"></i>
                        </div>
                    </div>
                    <div class="card-value"><?= number_format($savingsRate, 1) ?>%</div>
                    <div class="card-trend">
                        <span class="badge-target">
                            <i class="fa-solid fa-circle-check"></i> Vượt mục tiêu +<?= number_format($targetExceeded, 1) ?>%
                        </span>
                    </div>
                    <div class="spending-ratio-info">
                        <i class="fa-solid fa-chart-pie"></i>
                        <span>Chi tiêu chiếm <?= number_format($spendingRate, 1) ?>% thu nhập</span>
                    </div>
                </div>
            </div>

            <!-- EXPENSE STRUCTURE SECTION (2 COLUMNS) -->
            <div class="section-two-cols">
                <!-- Cột trái: Donut Chart -->
                <div class="content-card">
                    <div class="card-header-flex">
                        <h3>Cơ Cấu Chi Tiêu <?= $monthLabel ?></h3>
                        <span class="header-sub-val">Tổng chi thực tế: <?= number_format($expense, 0, ',', '.') ?> đ</span>
                    </div>

                    <div class="donut-chart-container">
                        <canvas id="expenseDonutChart"></canvas>
                        <?php if (!empty($categories) && $expense > 0): ?>
                        <div class="donut-center-overlay">
                            <div class="center-label"><?= htmlspecialchars($categories[0]['name'] ?? 'Chi tiêu') ?></div>
                            <div class="center-highlight"><?= number_format($categories[0]['percentage'] ?? 0, 0) ?>%</div>
                            <div class="center-sub">Chi nhiều nhất</div>
                        </div>
                        <?php else: ?>
                        <div class="donut-center-overlay">
                            <div class="center-label" style="font-size:12px; color:var(--gray-400);">Chưa có</div>
                            <div class="center-highlight" style="font-size:18px;">0 đ</div>
                            <div class="center-sub">chi tiêu</div>
                        </div>
                        <?php endif; ?>
                    </div>

                    <!-- Legend list -->
                    <div class="legend-grid">
                        <?php if (empty($categories)): ?>
                        <div style="grid-column: 1 / -1; text-align: center; color: var(--gray-400); padding: 15px; font-size: 13px;">
                            Chưa có giao dịch chi tiêu trong tháng này
                        </div>
                        <?php else: ?>
                        <?php foreach ($categories as $cat): ?>
                        <div class="legend-item">
                            <div class="legend-left">
                                <span class="legend-dot" style="background-color: <?= $cat['color'] ?>;"></span>
                                <span><?= htmlspecialchars($cat['name']) ?></span>
                            </div>
                            <span class="legend-pct"><?= number_format($cat['percentage'], 0) ?>%</span>
                        </div>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Cột phải: Budget Tracking Table -->
                <div class="content-card">
                    <div class="card-header-flex">
                        <h3>Kiểm Soát Ngân Sách Theo Hạng Mục</h3>
                        <span style="font-size: 13px; font-weight: 700; color: var(--gray-600); background: var(--gray-100); padding: 4px 10px; border-radius: 20px;">
                            Tháng <?= sprintf("%02d/%d", $month, $year) ?>
                        </span>
                    </div>

                    <div class="table-responsive">
                        <table class="budget-table">
                            <thead>
                                <tr>
                                    <th>Danh mục</th>
                                    <th>Thực chi</th>
                                    <th>Ngân sách</th>
                                    <th>Tỷ lệ sử dụng</th>
                                    <th>Trạng thái</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($categories)): ?>
                                <tr>
                                    <td colspan="5" style="text-align: center; padding: 30px; color: var(--gray-400);">
                                        <i class="fa-regular fa-folder-open" style="font-size: 26px; margin-bottom: 6px; display: block; opacity: 0.6;"></i>
                                        Chưa có hạng mục chi tiêu hoặc ngân sách được thiết lập
                                    </td>
                                </tr>
                                <?php else: ?>
                                <?php foreach ($categories as $cat): ?>
                                <tr>
                                    <td>
                                        <div class="category-name-cell">
                                            <div class="cat-icon-wrap" style="background-color: <?= $cat['color'] ?>15; color: <?= $cat['color'] ?>;">
                                                <i class="fa-solid <?= $cat['icon'] ?>"></i>
                                            </div>
                                            <span><?= htmlspecialchars($cat['name']) ?></span>
                                        </div>
                                    </td>
                                    <td style="font-weight: 700; color: var(--gray-900);"><?= number_format($cat['spent'], 0, ',', '.') ?> đ</td>
                                    <td style="color: var(--gray-500);"><?= number_format($cat['budget'], 0, ',', '.') ?> đ</td>
                                    <td>
                                        <div class="progress-bar-wrap">
                                            <div class="progress-track">
                                                <div class="progress-fill" style="width: <?= min(100, $cat['ratio']) ?>%; background-color: <?= $cat['bar_color'] ?>;"></div>
                                            </div>
                                            <span class="progress-text"><?= number_format($cat['ratio'], 1) ?>%</span>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge-status <?= $cat['badge_class'] ?>"><?= $cat['status'] ?></span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Footer tóm tắt ngân sách -->
                    <div class="budget-footer-summary">
                        <div>
                            <span>Tổng hạn mức ngân sách tháng <?= $month ?>: </span>
                            <strong><?= number_format($totalBudget, 0, ',', '.') ?> đ</strong>
                        </div>
                        <div>
                            <span>Còn dư: </span>
                            <span class="footer-highlight-green">+<?= number_format($budgetRemaining, 0, ',', '.') ?> đ</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 6-MONTH COMPARISON BAR CHART SECTION -->
            <div class="content-card chart-section">
                <div class="card-header-flex">
                    <div>
                        <h3>So Sánh Dòng Tiền 6 Tháng Gần Nhất (T5 - T10)</h3>
                        <p class="chart-header-sub">Đối chiếu Thu nhập vs Chi tiêu và quỹ đạo gia tăng thặng dư tích lũy</p>
                    </div>

                    <div class="chart-legend-custom">
                        <div class="legend-tag">
                            <span class="legend-box" style="background-color: #0F172A;"></span>
                            <span>Thu nhập</span>
                        </div>
                        <div class="legend-tag">
                            <span class="legend-box" style="background-color: #EF4444;"></span>
                            <span>Chi tiêu</span>
                        </div>
                        <div class="legend-tag">
                            <span class="legend-line"></span>
                            <span>Thặng dư tích lũy</span>
                        </div>
                    </div>
                </div>

                <div class="bar-chart-container">
                    <canvas id="sixMonthCashflowChart"></canvas>
                </div>

                <!-- 3 Bottom Stat Cards -->
                <div class="three-stats-grid">
                    <div class="stat-item-box">
                        <div class="stat-item-left">
                            <div class="stat-title">Trung bình thu nhập 6 tháng</div>
                            <div class="stat-number"><?= $sixMonthsData['avg_income'] ?></div>
                        </div>
                        <div class="card-icon-pill icon-income" style="width:34px;height:34px;">
                            <i class="fa-solid fa-arrow-trend-up"></i>
                        </div>
                    </div>

                    <div class="stat-item-box">
                        <div class="stat-item-left">
                            <div class="stat-title">Trung bình chi tiêu 6 tháng</div>
                            <div class="stat-number"><?= $sixMonthsData['avg_expense'] ?></div>
                        </div>
                        <div class="card-icon-pill icon-expense" style="width:34px;height:34px;">
                            <i class="fa-solid fa-arrow-trend-down"></i>
                        </div>
                    </div>

                    <div class="stat-item-box">
                        <div class="stat-item-left">
                            <div class="stat-title">Tỷ lệ tiết kiệm bình quân</div>
                            <div class="stat-number"><?= $sixMonthsData['avg_savings_rate'] ?></div>
                        </div>
                        <span class="badge-solid-green">
                            <i class="fa-solid fa-shield-halved"></i> Vững mạnh
                        </span>
                    </div>
                </div>
            </div>

            <!-- AI ANALYSIS SECTION -->
            <div class="content-card ai-section">
                <div class="card-header-flex">
                    <div style="display: flex; align-items: center; gap: 12px;">
                        <h3 style="color: #4C1D95;">
                            <i class="fa-solid fa-wand-magic-sparkles" style="color: #7C3AED; margin-right: 6px;"></i>
                            Trợ Lý Phân Tích Thông Minh FinTrack AI
                        </h3>
                        <span class="ai-badge">
                            <span class="pulse-dot" style="background:#7C3AED;"></span>
                            Thời gian thực
                        </span>
                    </div>

                    <a href="javascript:void(0)" onclick="openModal('aiModal')" class="ai-link">
                        <span>Xem phân tích chi tiết</span>
                        <i class="fa-solid fa-chevron-right"></i>
                    </a>
                </div>

                <div class="ai-cards-grid">
                    <?php if ($isDemoAdmin): ?>
                    <!-- Insight 1 -->
                    <div class="ai-card">
                        <div class="ai-card-header">
                            <div class="ai-card-title">
                                <i class="fa-solid fa-circle-arrow-down" style="color: #10B981;"></i>
                                <span>Thói quen tích cực -15% Chi phí</span>
                            </div>
                            <span class="ai-tag-pill tag-green">-15% Chi phí</span>
                        </div>
                        <p class="ai-card-body">
                            Bạn đã cắt giảm được 15% chi phí ăn ngoài trong 2 tuần qua nhờ chuyển sang tự nấu ăn tại nhà và hạn chế đặt đồ uống giao nhanh, giúp tiết kiệm thêm xấp xỉ 1.250.000 đ cho quỹ tích lũy dự phòng.
                        </p>
                    </div>

                    <!-- Insight 2 -->
                    <div class="ai-card">
                        <div class="ai-card-header">
                            <div class="ai-card-title">
                                <i class="fa-solid fa-bell" style="color: #F59E0B;"></i>
                                <span>Khoản chi định kỳ sắp tới - Ưu tiên số 1</span>
                            </div>
                            <span class="ai-tag-pill tag-orange">Ưu tiên số 1</span>
                        </div>
                        <p class="ai-card-body">
                            Khoản chi định kỳ tiếp theo: Tiền thuê nhà (8.500.000 đ) và hóa đơn Internet/Điện sinh hoạt đến hạn vào ngày 05/11. Dòng tiền thặng dư hiện tại hoàn toàn đáp ứng tốt mà không ảnh hưởng mục tiêu tiết kiệm.
                        </p>
                    </div>
                    <?php else: ?>
                    <div class="ai-card" style="grid-column: 1 / -1; text-align: center; padding: 25px;">
                        <div style="font-size: 28px; color: #8B5CF6; margin-bottom: 8px;"><i class="fa-solid fa-wand-magic-sparkles"></i></div>
                        <h4 style="font-size: 15px; color: var(--gray-800); margin-bottom: 6px;">Chưa có đủ dữ liệu giao dịch để phân tích thông minh</h4>
                        <p style="font-size: 13px; color: var(--gray-500);">Hãy ghi chép các giao dịch thu chi đầu tiên của bạn để Trợ lý FinTrack AI bắt đầu tổng hợp và đưa ra đề xuất tài chính tối ưu!</p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- BOTTOM ACTIONS BAR -->
            <div class="bottom-bar">
                <div class="security-note">
                    <i class="fa-solid fa-shield-check"></i>
                    <span>Dữ liệu tuân thủ chuẩn mã hóa ngân hàng cá nhân & hạch toán tự động.</span>
                </div>

                <div class="bottom-buttons">
                    <button type="button" class="btn btn-outline" onclick="window.print()">
                        <i class="fa-solid fa-print"></i>
                        <span>In báo cáo</span>
                    </button>
                    <button type="button" class="btn btn-outline" onclick="openModal('shareModal')">
                        <i class="fa-solid fa-share-nodes"></i>
                        <span>Chia sẻ báo cáo</span>
                    </button>
                    <button type="button" class="btn btn-dark" onclick="openModal('customizeModal')">
                        <i class="fa-solid fa-sliders"></i>
                        <span>Tùy biến chỉ số</span>
                    </button>
                </div>
            </div>

        </main>
    </div>

    <!-- ==================== MODALS ==================== -->

    <!-- Modal 1: Chia sẻ báo cáo -->
    <div class="modal-backdrop" id="shareModal">
        <div class="modal-box">
            <div class="modal-header">
                <h3><i class="fa-solid fa-share-nodes" style="color: var(--primary); margin-right: 8px;"></i>Chia Sẻ Báo Cáo Tài Chính</h3>
                <button type="button" class="modal-close-btn" onclick="closeModal('shareModal')">&times;</button>
            </div>
            <div class="modal-body">
                <p style="margin-bottom: 14px;">Bạn có thể tạo liên kết bảo mật để chia sẻ nhanh báo cáo Tháng <?= $month ?>/<?= $year ?> cho cố vấn tài chính hoặc gia đình:</p>
                <div style="display: flex; gap: 8px; margin-bottom: 16px;">
                    <input type="text" id="shareUrlInput" readonly value="https://fintrack.vn/reports/share?token=ft_sec_<?= md5($month . $year . 'fintrack') ?>" style="flex:1; padding: 10px 14px; border: 1px solid var(--gray-300); border-radius: 8px; font-size: 13px; background: var(--gray-50);">
                    <button type="button" class="btn btn-green" onclick="copyShareLink()">
                        <i class="fa-solid fa-copy"></i> Sao chép
                    </button>
                </div>
                <div style="background: var(--info-light); border: 1px solid #BAE6FD; padding: 12px; border-radius: 8px; font-size: 12.5px; color: #0369A1;">
                    <i class="fa-solid fa-circle-info" style="margin-right: 4px;"></i>
                    Liên kết bảo mật chỉ cho phép đọc dữ liệu tổng hợp và sẽ tự động hết hạn sau 7 ngày.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('shareModal')">Đóng</button>
            </div>
        </div>
    </div>

    <!-- Modal 2: Tùy biến chỉ số -->
    <div class="modal-backdrop" id="customizeModal">
        <div class="modal-box">
            <div class="modal-header">
                <h3><i class="fa-solid fa-sliders" style="color: var(--primary); margin-right: 8px;"></i>Tùy Biến Chỉ Số Báo Cáo</h3>
                <button type="button" class="modal-close-btn" onclick="closeModal('customizeModal')">&times;</button>
            </div>
            <div class="modal-body">
                <p style="margin-bottom: 16px; font-size: 13.5px; color: var(--gray-600);">Lựa chọn các chỉ số bạn muốn ưu tiên hiển thị trên bảng điều khiển phân tích:</p>
                
                <div style="display: flex; flex-direction: column; gap: 12px;">
                    <label style="display: flex; align-items: center; gap: 10px; cursor: pointer; font-weight: 600;">
                        <input type="checkbox" checked style="width: 17px; height: 17px; accent-color: var(--primary);">
                        <span>Hiển thị tỷ lệ tiết kiệm so với mục tiêu đặt ra (Mục tiêu chuẩn: 45.0%)</span>
                    </label>
                    <label style="display: flex; align-items: center; gap: 10px; cursor: pointer; font-weight: 600;">
                        <input type="checkbox" checked style="width: 17px; height: 17px; accent-color: var(--primary);">
                        <span>Bật gợi ý phân tích xu hướng chi tiêu tự động bằng FinTrack AI</span>
                    </label>
                    <label style="display: flex; align-items: center; gap: 10px; cursor: pointer; font-weight: 600;">
                        <input type="checkbox" checked style="width: 17px; height: 17px; accent-color: var(--primary);">
                        <span>Kiểm soát hạn mức ngân sách và cảnh báo cận ngưỡng (>90%)</span>
                    </label>
                    <label style="display: flex; align-items: center; gap: 10px; cursor: pointer; font-weight: 600;">
                        <input type="checkbox" checked style="width: 17px; height: 17px; accent-color: var(--primary);">
                        <span>Biểu đồ đối chiếu dòng tiền 6 tháng gần nhất có đường thặng dư tích lũy</span>
                    </label>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('customizeModal')">Hủy bỏ</button>
                <button type="button" class="btn btn-green" onclick="saveCustomization()">Lưu cấu hình</button>
            </div>
        </div>
    </div>

    <!-- Modal 3: Phân tích chuyên sâu FinTrack AI -->
    <div class="modal-backdrop" id="aiModal">
        <div class="modal-box" style="max-width: 640px;">
            <div class="modal-header">
                <h3><i class="fa-solid fa-wand-magic-sparkles" style="color: #7C3AED; margin-right: 8px;"></i>Báo Cáo Toàn Diện FinTrack AI</h3>
                <button type="button" class="modal-close-btn" onclick="closeModal('aiModal')">&times;</button>
            </div>
            <div class="modal-body">
                <div style="background: #F5F3FF; border: 1px solid #DDD6FE; border-radius: 10px; padding: 14px; margin-bottom: 16px;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
                        <span style="font-weight: 700; color: #5B21B6; font-size: 15px;">Điểm Sức Khỏe Tài Chính: 88/100 (Hạng A - Xuất Sắc)</span>
                        <span class="badge-solid-green">Tăng +4 điểm</span>
                    </div>
                    <p style="font-size: 13px; color: #6D28D9;">Dòng tiền thặng dư duy trì ổn định trên 50% trong liên tiếp 3 tháng qua. Bạn đang kiểm soát chi phí rất khoa học.</p>
                </div>

                <h4 style="font-size: 14px; font-weight: 700; margin-bottom: 10px; color: var(--gray-800);">3 Khuyến nghị tài chính thông minh cho Tháng tiếp theo:</h4>
                <ul style="padding-left: 20px; font-size: 13.5px; color: var(--gray-700); line-height: 1.7;">
                    <li><strong>Tối ưu hóa hạn mức Ăn uống:</strong> Hiện tại danh mục này đã chạm ngưỡng 91.2% hạn mức (7.3M / 8.0M). Nên giữ nhịp sinh hoạt hiện tại trong tuần cuối.</li>
                    <li><strong>Tận dụng thặng dư 24.25M đ:</strong> Khuyến nghị chuyển 15.000.000 đ vào chứng chỉ tiền gửi hoặc quỹ mở để gia tăng lãi suất kép.</li>
                    <li><strong>Quỹ dự phòng an toàn:</strong> Bạn đã tích lũy đủ 4.2 tháng chi phí sinh hoạt thiết yếu (chuẩn quốc tế khuyến nghị từ 3 - 6 tháng).</li>
                </ul>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('aiModal')">Đóng</button>
                <button type="button" class="btn btn-green" onclick="showToast('Đã lưu khuyến nghị vào kế hoạch tháng!'); closeModal('aiModal');">Áp dụng gợi ý</button>
            </div>
        </div>
    </div>

    <!-- Toast Component -->
    <div class="toast-notification" id="toastNotification">
        <i class="fa-solid fa-circle-check" style="color: #10B981; font-size: 16px;"></i>
        <span id="toastMessage">Thông báo thao tác thành công!</span>
    </div>

    <!-- ==================== JAVASCRIPT ==================== -->
    <script>
        // Dữ liệu danh mục chi tiêu cho Doughnut Chart
        const expenseCategories = <?= json_encode($categories, JSON_UNESCAPED_UNICODE) ?>;
        const hasExpenses = expenseCategories.length > 0 && expenseCategories.some(c => c.spent > 0);
        const donutLabels = hasExpenses ? expenseCategories.map(c => c.name) : ['Chưa có chi tiêu'];
        const donutData = hasExpenses ? expenseCategories.map(c => c.spent) : [1];
        const donutColors = hasExpenses ? expenseCategories.map(c => c.color) : ['#E2E8F0'];

        // Khởi tạo Donut Chart với Chart.js
        const donutCtx = document.getElementById('expenseDonutChart').getContext('2d');
        const expenseDonutChart = new Chart(donutCtx, {
            type: 'doughnut',
            data: {
                labels: donutLabels,
                datasets: [{
                    data: donutData,
                    backgroundColor: donutColors,
                    borderWidth: 2,
                    borderColor: '#FFFFFF',
                    hoverOffset: 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '72%',
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        backgroundColor: '#0F172A',
                        padding: 12,
                        titleFont: { size: 13, family: 'Plus Jakarta Sans', weight: '700' },
                        bodyFont: { size: 12.5, family: 'Plus Jakarta Sans' },
                        cornerRadius: 8,
                        callbacks: {
                            label: function(context) {
                                const val = context.raw || 0;
                                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                const pct = ((val / total) * 100).toFixed(1);
                                return ' ' + new Intl.NumberFormat('vi-VN').format(val) + ' đ (' + pct + '%)';
                            }
                        }
                    }
                }
            }
        });

        // Dữ liệu 6 tháng gần nhất cho Combo Chart
        const sixMonths = <?= json_encode($sixMonthsData, JSON_UNESCAPED_UNICODE) ?>;
        const barCtx = document.getElementById('sixMonthCashflowChart').getContext('2d');

        const sixMonthChart = new Chart(barCtx, {
            type: 'bar',
            data: {
                labels: sixMonths.labels,
                datasets: [
                    {
                        type: 'line',
                        label: 'Thặng dư tích lũy',
                        data: sixMonths.surplus,
                        borderColor: '#10B981',
                        backgroundColor: '#10B981',
                        borderWidth: 3,
                        pointBackgroundColor: '#10B981',
                        pointBorderColor: '#FFFFFF',
                        pointBorderWidth: 2,
                        pointRadius: 5,
                        pointHoverRadius: 7,
                        tension: 0.35,
                        order: 1
                    },
                    {
                        type: 'bar',
                        label: 'Thu nhập',
                        data: sixMonths.income,
                        backgroundColor: '#0F172A',
                        borderRadius: 6,
                        barPercentage: 0.55,
                        categoryPercentage: 0.65,
                        order: 2
                    },
                    {
                        type: 'bar',
                        label: 'Chi tiêu',
                        data: sixMonths.expense,
                        backgroundColor: '#EF4444',
                        borderRadius: 6,
                        barPercentage: 0.55,
                        categoryPercentage: 0.65,
                        order: 3
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: {
                    mode: 'index',
                    intersect: false
                },
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        backgroundColor: '#0F172A',
                        padding: 12,
                        titleFont: { size: 13, family: 'Plus Jakarta Sans', weight: '700' },
                        bodyFont: { size: 12, family: 'Plus Jakarta Sans' },
                        cornerRadius: 8,
                        callbacks: {
                            label: function(context) {
                                let label = context.dataset.label || '';
                                if (label) {
                                    label += ': ';
                                }
                                if (context.parsed.y !== null) {
                                    label += new Intl.NumberFormat('vi-VN').format(context.parsed.y) + ' đ';
                                }
                                return label;
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        grid: {
                            display: false
                        },
                        ticks: {
                            font: {
                                family: 'Plus Jakarta Sans',
                                size: 12,
                                weight: '600'
                            },
                            color: '#64748B'
                        }
                    },
                    y: {
                        min: 0,
                        max: 50000000,
                        ticks: {
                            stepSize: 10000000,
                            font: {
                                family: 'Plus Jakarta Sans',
                                size: 11
                            },
                            color: '#94A3B8',
                            callback: function(value) {
                                return (value / 1000000) + 'M';
                            }
                        },
                        grid: {
                            color: '#F1F5F9'
                        }
                    }
                }
            }
        });

        // Hàm đổi tháng xem báo cáo
        function changeReportPeriod() {
            const select = document.getElementById('monthSelector');
            const val = select.value.split('-');
            const selectedMonth = val[0];
            const selectedYear = val[1];

            // Tải lại trang với query parameters
            window.location.href = `reports.php?month=${selectedMonth}&year=${selectedYear}`;
        }

        // Modal Utilities
        function openModal(id) {
            const modal = document.getElementById(id);
            if (modal) {
                modal.style.display = 'flex';
            }
        }

        function closeModal(id) {
            const modal = document.getElementById(id);
            if (modal) {
                modal.style.display = 'none';
            }
        }

        window.onclick = function(event) {
            if (event.target.classList.contains('modal-backdrop')) {
                event.target.style.display = 'none';
            }
        };

        // Toast Message Utility
        let toastTimeout;
        function showToast(message) {
            const toast = document.getElementById('toastNotification');
            const text = document.getElementById('toastMessage');
            text.innerText = message;
            toast.classList.add('show');

            clearTimeout(toastTimeout);
            toastTimeout = setTimeout(() => {
                toast.classList.remove('show');
            }, 3000);
        }

        // Copy Share Link
        function copyShareLink() {
            const input = document.getElementById('shareUrlInput');
            input.select();
            input.setSelectionRange(0, 99999);
            navigator.clipboard.writeText(input.value).then(() => {
                showToast('Đã sao chép liên kết báo cáo vào bộ nhớ tạm!');
            });
        }

        // Save Customization
        function saveCustomization() {
            closeModal('customizeModal');
            showToast('Đã lưu thành công cấu hình tùy biến chỉ số.');
        }

        // Tìm kiếm nhanh
        document.getElementById('quickSearchInput')?.addEventListener('keyup', function(e) {
            if (e.key === 'Enter') {
                const q = this.value.trim().toLowerCase();
                if (q) {
                    showToast('Đang lọc các chỉ số liên quan đến: "' + q + '"');
                }
            }
        });
    </script>
</body>
</html>
