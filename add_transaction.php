<?php
/**
 * FinTrack VN - Thêm Giao Dịch Mới (Add Transaction)
 * Giao diện hiện đại, tối ưu cho người dùng Việt Nam
 */

require_once __DIR__ . '/config.php';
checkAuth();

$userId = (int)($_SESSION['user_id'] ?? 1);
$isDemoAdmin = ($userId === 1);

// Lấy danh sách danh mục từ Database của chính người dùng
$categoriesExpense = [];
$categoriesIncome = [];

if ($pdo) {
    try {
        $stmtExp = $pdo->prepare("SELECT * FROM `categories` WHERE `user_id` = ? AND `type` = 'expense' AND `is_active` = 1 ORDER BY `id` ASC");
        $stmtExp->execute([$userId]);
        $categoriesExpense = $stmtExp->fetchAll();

        $stmtInc = $pdo->prepare("SELECT * FROM `categories` WHERE `user_id` = ? AND `type` = 'income' AND `is_active` = 1 ORDER BY `id` ASC");
        $stmtInc->execute([$userId]);
        $categoriesIncome = $stmtInc->fetchAll();
    } catch (Exception $e) {
        $categoriesExpense = [];
        $categoriesIncome = [];
    }
}

// Fallback dữ liệu mẫu danh mục nếu chưa có dữ liệu trong DB
if (empty($categoriesExpense)) {
    $categoriesExpense = [
        ['id' => 1, 'name' => 'Ăn uống', 'type' => 'expense', 'icon' => 'fa-utensils', 'color' => '#F97316'],
        ['id' => 2, 'name' => 'Mua sắm', 'type' => 'expense', 'icon' => 'fa-bag-shopping', 'color' => '#EC4899'],
        ['id' => 3, 'name' => 'Di chuyển', 'type' => 'expense', 'icon' => 'fa-car', 'color' => '#3B82F6'],
        ['id' => 4, 'name' => 'Nhà cửa', 'type' => 'expense', 'icon' => 'fa-house', 'color' => '#10B981'],
        ['id' => 5, 'name' => 'Giải trí', 'type' => 'expense', 'icon' => 'fa-film', 'color' => '#8B5CF6'],
        ['id' => 6, 'name' => 'Y tế & Sức...', 'type' => 'expense', 'icon' => 'fa-heart-pulse', 'color' => '#EF4444'],
        ['id' => 7, 'name' => 'Giáo dục', 'type' => 'expense', 'icon' => 'fa-graduation-cap', 'color' => '#06B6D4'],
        ['id' => 8, 'name' => 'Khoản chi...', 'type' => 'expense', 'icon' => 'fa-ellipsis', 'color' => '#64748B']
    ];
}

if (empty($categoriesIncome)) {
    $categoriesIncome = [
        ['id' => 9, 'name' => 'Tiền lương', 'type' => 'income', 'icon' => 'fa-money-bill-wave', 'color' => '#22C55E'],
        ['id' => 10, 'name' => 'Tiền thưởng', 'type' => 'income', 'icon' => 'fa-award', 'color' => '#EAB308'],
        ['id' => 11, 'name' => 'Đầu tư sinh lời', 'type' => 'income', 'icon' => 'fa-chart-line', 'color' => '#3B82F6'],
        ['id' => 12, 'name' => 'Kinh doanh phụ', 'type' => 'income', 'icon' => 'fa-shop', 'color' => '#8B5CF6'],
        ['id' => 13, 'name' => 'Lãi tiết kiệm', 'type' => 'income', 'icon' => 'fa-percent', 'color' => '#14B8A6'],
        ['id' => 14, 'name' => 'Khoản thu khác', 'type' => 'income', 'icon' => 'fa-wallet', 'color' => '#64748B']
    ];
}

// Lấy danh sách tài khoản nguồn / phương thức thanh toán của người dùng
$paymentMethods = [];
if ($pdo) {
    try {
        $stmtPm = $pdo->prepare("SELECT * FROM `payment_methods` WHERE `user_id` = ? AND `is_active` = 1 ORDER BY `is_primary` DESC, `id` ASC");
        $stmtPm->execute([$userId]);
        $paymentMethods = $stmtPm->fetchAll();
    } catch (Exception $e) {
        $paymentMethods = [];
    }
}

if (empty($paymentMethods)) {
    if ($isDemoAdmin) {
        $paymentMethods = [
            [
                'id' => 1,
                'name' => 'Techcombank Visa Signature',
                'type' => 'bank',
                'account_number' => '8829',
                'balance' => 42850000.00,
                'is_primary' => 1,
                'icon' => 'fa-credit-card'
            ],
            [
                'id' => 2,
                'name' => 'Ví Điện Tử MoMo',
                'type' => 'ewallet',
                'account_number' => '0988***123',
                'balance' => 3500000.00,
                'is_primary' => 0,
                'icon' => 'fa-wallet'
            ],
            [
                'id' => 3,
                'name' => 'Tiền mặt ví cá nhân',
                'type' => 'cash',
                'account_number' => '',
                'balance' => 1250000.00,
                'is_primary' => 0,
                'icon' => 'fa-money-bill-1'
            ],
            [
                'id' => 4,
                'name' => 'Vietcombank i-Account',
                'type' => 'bank',
                'account_number' => '5566',
                'balance' => 15400000.00,
                'is_primary' => 0,
                'icon' => 'fa-building-columns'
            ]
        ];
    } else {
        $paymentMethods = [
            [
                'id' => 1,
                'name' => 'Tiền mặt',
                'type' => 'cash',
                'account_number' => '',
                'balance' => 0.00,
                'is_primary' => 1,
                'icon' => 'fa-money-bill-1'
            ],
            [
                'id' => 2,
                'name' => 'Tài khoản Ngân hàng',
                'type' => 'bank',
                'account_number' => '',
                'balance' => 0.00,
                'is_primary' => 0,
                'icon' => 'fa-building-columns'
            ]
        ];
    }
}

// Lấy dữ liệu ngân sách tháng hiện tại của người dùng
$currentMonth = (int)date('m');
$currentYear = (int)date('Y');
$categoryBudgets = [];

if ($pdo) {
    try {
        $bStmt = $pdo->prepare("SELECT * FROM `budgets` WHERE `user_id` = ? AND `month` = ? AND `year` = ?");
        $bStmt->execute([$userId, $currentMonth, $currentYear]);
        while ($row = $bStmt->fetch()) {
            if (isset($row['category_id'])) {
                $categoryBudgets[$row['category_id']] = [
                    'amount' => (float)$row['amount'],
                    'spent' => (float)$row['spent']
                ];
            }
        }
    } catch (Exception $e) {}
}

// Chỉ tài khoản demo admin ID = 1 mới có dữ liệu ngân sách mẫu
if ($isDemoAdmin && !isset($categoryBudgets[1])) {
    $categoryBudgets[1] = [
        'amount' => 5000000.00,
        'spent' => 3800000.00
    ];
}

// Lấy 3 giao dịch vừa tạo gần đây của người dùng
$recentTransactions = [];
if ($pdo) {
    try {
        $stmtRecent = $pdo->prepare("
            SELECT t.*, c.name AS category_name, c.icon AS category_icon, c.color AS category_color, pm.name AS payment_name
            FROM `transactions` t
            LEFT JOIN `categories` c ON t.category_id = c.id
            LEFT JOIN `payment_methods` pm ON t.payment_method_id = pm.id
            WHERE t.user_id = ?
            ORDER BY t.id DESC LIMIT 3
        ");
        $stmtRecent->execute([$userId]);
        $recentTransactions = $stmtRecent->fetchAll();
    } catch (Exception $e) {}
}

if (empty($recentTransactions) && $isDemoAdmin) {
    $recentTransactions = [
        [
            'id' => 101,
            'type' => 'expense',
            'category_name' => 'Ăn uống',
            'category_icon' => 'fa-utensils',
            'category_color' => '#F97316',
            'amount' => 120000.00,
            'description' => 'Cơm trưa văn phòng & nước ép',
            'transaction_time' => '11:45',
            'payment_name' => 'Techcombank'
        ],
        [
            'id' => 100,
            'type' => 'expense',
            'category_name' => 'Di chuyển',
            'category_icon' => 'fa-car',
            'category_color' => '#3B82F6',
            'amount' => 45000.00,
            'description' => 'Grab Bike di chuyển gặp đối tác',
            'transaction_time' => '08:15',
            'payment_name' => 'Ví MoMo'
        ],
        [
            'id' => 99,
            'type' => 'income',
            'category_name' => 'Đầu tư sinh lời',
            'category_icon' => 'fa-chart-line',
            'category_color' => '#3B82F6',
            'amount' => 2500000.00,
            'description' => 'Lãi cổ tức quý 3 Techcom Securities',
            'transaction_time' => 'Hôm qua',
            'payment_name' => 'Vietcombank'
        ]
    ];
}

// Số phiếu tham chiếu
$referenceNumber = '#FTV-2410-092';
$defaultDate = date('Y-m-d');
$defaultDateDisplay = date('d/m/Y') . ' (Hôm nay)';
$defaultTime = date('H:i');
$defaultTimeDisplay = date('H:i') . ' (Chiều)';
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Thêm Giao Dịch Mới | FinTrack VN</title>
    <!-- Google Font Inter -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <!-- Font Awesome 6 Pro / Free -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    
    <style>
        :root {
            --navy: #1B1F3B;
            --navy-light: #262B50;
            --blue: #4F59E8;
            --blue-hover: #3E47D1;
            --blue-light: #EEF0FD;
            --green: #22C55E;
            --green-hover: #16A34A;
            --green-light: #ECFDF5;
            --red: #EF4444;
            --red-hover: #DC2626;
            --red-light: #FEF2F2;
            --orange: #F97316;
            --bg-page: #F8FAFC;
            --card-bg: #FFFFFF;
            --border-color: #E2E8F0;
            --border-focus: #4F59E8;
            --text-main: #0F172A;
            --text-muted: #64748B;
            --text-light: #94A3B8;
            --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
            --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.07), 0 2px 4px -2px rgba(0, 0, 0, 0.05);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.08), 0 4px 6px -4px rgba(0, 0, 0, 0.04);
            --shadow-xl: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 8px 10px -6px rgba(0, 0, 0, 0.05);
            --radius-md: 10px;
            --radius-lg: 14px;
            --radius-xl: 18px;
            --transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }

        body {
            background-color: var(--bg-page);
            color: var(--text-main);
            min-height: 100vh;
            display: flex;
            overflow-x: hidden;
        }

        /* ====== SIDEBAR ====== */
        .sidebar {
            width: 260px;
            background-color: var(--navy);
            color: #FFFFFF;
            flex-shrink: 0;
            display: flex;
            flex-direction: column;
            min-height: 100vh;
            position: fixed;
            top: 0;
            left: 0;
            bottom: 0;
            z-index: 50;
            border-right: 1px solid rgba(255, 255, 255, 0.06);
        }

        .sidebar-brand {
            padding: 24px 22px;
            display: flex;
            align-items: center;
            gap: 12px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.08);
        }

        .brand-logo-icon {
            width: 38px;
            height: 38px;
            border-radius: 10px;
            background: linear-gradient(135deg, #4F59E8, #3B82F6);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            color: #FFF;
            box-shadow: 0 4px 10px rgba(79, 89, 232, 0.4);
        }

        .brand-text h2 {
            font-size: 18px;
            font-weight: 700;
            letter-spacing: -0.3px;
            color: #FFFFFF;
            line-height: 1.2;
        }

        .brand-text span {
            font-size: 11px;
            color: #94A3B8;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .sidebar-menu {
            padding: 20px 14px;
            flex: 1;
            display: flex;
            flex-direction: column;
            gap: 6px;
            overflow-y: auto;
        }

        .menu-label {
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            color: #64748B;
            padding: 10px 12px 4px;
        }

        .nav-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 11px 14px;
            color: #94A3B8;
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
            border-radius: var(--radius-md);
            transition: var(--transition);
        }

        .nav-item:hover {
            color: #FFFFFF;
            background: rgba(255, 255, 255, 0.06);
        }

        .nav-item.active {
            background: var(--blue);
            color: #FFFFFF;
            font-weight: 600;
            box-shadow: 0 4px 12px rgba(79, 89, 232, 0.35);
        }

        .nav-item i {
            width: 20px;
            text-align: center;
            font-size: 16px;
        }

        .sidebar-user {
            padding: 16px 18px;
            border-top: 1px solid rgba(255, 255, 255, 0.08);
            background: rgba(15, 23, 42, 0.3);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .user-info-box {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .user-avatar {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: linear-gradient(135deg, #10B981, #06B6D4);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 14px;
            color: #FFF;
        }

        .user-details h4 {
            font-size: 13px;
            font-weight: 600;
            color: #F8FAFC;
        }

        .user-details p {
            font-size: 11px;
            color: #94A3B8;
        }

        .btn-logout {
            background: none;
            border: none;
            color: #94A3B8;
            cursor: pointer;
            padding: 6px;
            border-radius: 6px;
            transition: var(--transition);
        }

        .btn-logout:hover {
            color: var(--red);
            background: rgba(239, 68, 68, 0.1);
        }

        /* ====== MAIN WRAPPER ====== */
        .main-wrapper {
            margin-left: 260px;
            flex: 1;
            display: flex;
            flex-direction: column;
            min-width: 0;
        }

        /* ====== TOP HEADER ====== */
        .top-header {
            height: 70px;
            background: #FFFFFF;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 32px;
            position: sticky;
            top: 0;
            z-index: 40;
        }

        .header-search {
            position: relative;
            width: 380px;
        }

        .header-search i {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-light);
            font-size: 14px;
        }

        .header-search input {
            width: 100%;
            padding: 10px 14px 10px 38px;
            border-radius: 20px;
            border: 1px solid var(--border-color);
            background: #F8FAFC;
            font-size: 13px;
            outline: none;
            transition: var(--transition);
        }

        .header-search input:focus {
            border-color: var(--blue);
            background: #FFFFFF;
            box-shadow: 0 0 0 3px rgba(79, 89, 232, 0.12);
        }

        .header-search .search-key {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            font-size: 11px;
            background: #E2E8F0;
            padding: 2px 6px;
            border-radius: 4px;
            color: var(--text-muted);
            font-weight: 600;
        }

        .header-actions {
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .header-badge-balance {
            display: flex;
            align-items: center;
            gap: 8px;
            background: var(--green-light);
            padding: 7px 14px;
            border-radius: 20px;
            border: 1px solid rgba(34, 197, 94, 0.2);
        }

        .header-badge-balance .dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: var(--green);
        }

        .header-badge-balance span {
            font-size: 12px;
            color: var(--text-muted);
        }

        .header-badge-balance strong {
            font-size: 13px;
            color: #15803D;
            font-weight: 700;
        }

        .header-btn-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #F8FAFC;
            border: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--text-muted);
            cursor: pointer;
            position: relative;
            transition: var(--transition);
        }

        .header-btn-icon:hover {
            color: var(--blue);
            border-color: var(--blue);
            background: #FFF;
        }

        .header-btn-icon .notif-dot {
            position: absolute;
            top: 7px;
            right: 7px;
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: var(--red);
            border: 2px solid #FFF;
        }

        /* ====== PAGE CONTAINER ====== */
        .page-container {
            padding: 28px 32px 60px;
            max-width: 1400px;
            width: 100%;
            margin: 0 auto;
        }

        /* ====== PAGE HEADER ====== */
        .page-header-box {
            margin-bottom: 24px;
        }

        .page-header-top {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 8px;
        }

        .ref-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: var(--red-light);
            color: var(--red);
            border: 1px solid rgba(239, 68, 68, 0.25);
            padding: 4px 10px;
            border-radius: 6px;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.5px;
            text-transform: uppercase;
        }

        .ref-code {
            font-size: 12px;
            font-weight: 600;
            color: var(--text-muted);
            background: #F1F5F9;
            padding: 3px 8px;
            border-radius: 6px;
        }

        .page-title {
            font-size: 26px;
            font-weight: 800;
            color: var(--navy);
            letter-spacing: -0.5px;
            margin-bottom: 6px;
        }

        .page-subtitle {
            font-size: 14px;
            color: var(--text-muted);
            line-height: 1.5;
        }

        /* ====== TRANSACTION TYPE TOGGLE ====== */
        .type-toggle-wrapper {
            display: flex;
            justify-content: center;
            margin-bottom: 28px;
        }

        .type-toggle-container {
            display: inline-flex;
            background: #E2E8F0;
            padding: 5px;
            border-radius: 30px;
            gap: 4px;
            box-shadow: inset 0 2px 4px rgba(0, 0, 0, 0.05);
        }

        .type-tab-btn {
            border: none;
            background: transparent;
            padding: 10px 28px;
            border-radius: 25px;
            font-size: 14px;
            font-weight: 600;
            color: var(--text-muted);
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: var(--transition);
        }

        .type-tab-btn:hover {
            color: var(--text-main);
        }

        .type-tab-btn.active-expense {
            background: var(--red);
            color: #FFFFFF;
            box-shadow: 0 4px 12px rgba(239, 68, 68, 0.35);
        }

        .type-tab-btn.active-income {
            background: var(--green);
            color: #FFFFFF;
            box-shadow: 0 4px 12px rgba(34, 197, 94, 0.35);
        }

        /* ====== FORM & SIDEBAR GRID ====== */
        .form-layout-grid {
            display: grid;
            grid-template-columns: 65% 35%;
            gap: 28px;
            align-items: start;
        }

        @media (max-width: 1100px) {
            .form-layout-grid {
                grid-template-columns: 1fr;
            }
        }

        /* ====== FORM CARD STYLING ====== */
        .form-card {
            background: var(--card-bg);
            border-radius: var(--radius-xl);
            border: 1px solid var(--border-color);
            box-shadow: var(--shadow-sm);
            padding: 30px;
        }

        .form-section-title {
            font-size: 15px;
            font-weight: 700;
            color: var(--navy);
            margin-bottom: 14px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .form-section-title span.sub-text {
            font-size: 12px;
            font-weight: 500;
            color: var(--text-muted);
        }

        /* 1. SỐ TIỀN GIAO DỊCH */
        .amount-input-box {
            margin-bottom: 26px;
        }

        .amount-input-container {
            position: relative;
            display: flex;
            align-items: center;
            background: #F8FAFC;
            border: 2px solid var(--border-color);
            border-radius: var(--radius-lg);
            padding: 8px 18px;
            transition: var(--transition);
        }

        .amount-input-container:focus-within {
            border-color: var(--blue);
            background: #FFFFFF;
            box-shadow: 0 0 0 4px rgba(79, 89, 232, 0.12);
        }

        .amount-symbol-prefix {
            font-size: 26px;
            font-weight: 800;
            color: var(--red);
            margin-right: 8px;
            user-select: none;
            transition: color 0.2s ease;
        }

        .amount-symbol-prefix.income-mode {
            color: var(--green);
        }

        .amount-input-field {
            flex: 1;
            border: none;
            background: transparent;
            font-size: 32px;
            font-weight: 800;
            color: var(--navy);
            outline: none;
            letter-spacing: -0.5px;
        }

        .amount-currency {
            font-size: 20px;
            font-weight: 700;
            color: var(--text-muted);
            margin-left: 8px;
        }

        .amount-tools {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-left: 14px;
            padding-left: 14px;
            border-left: 1px solid var(--border-color);
        }

        .tool-icon-btn {
            width: 36px;
            height: 36px;
            border-radius: 8px;
            border: 1px solid var(--border-color);
            background: #FFFFFF;
            color: var(--text-muted);
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
            transition: var(--transition);
        }

        .tool-icon-btn:hover {
            color: var(--blue);
            border-color: var(--blue);
            background: var(--blue-light);
        }

        /* Quick Amount Buttons */
        .quick-amounts {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-top: 10px;
        }

        .quick-btn {
            border: 1px solid var(--border-color);
            background: #FFFFFF;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            color: var(--text-muted);
            cursor: pointer;
            transition: var(--transition);
        }

        .quick-btn:hover {
            background: var(--blue-light);
            border-color: var(--blue);
            color: var(--blue);
        }

        .quick-btn.clear-btn {
            color: var(--red);
            border-color: #FCA5A5;
            background: var(--red-light);
        }

        .quick-btn.clear-btn:hover {
            background: var(--red);
            color: #FFFFFF;
        }

        /* 2. CHỌN DANH MỤC PHÂN BỔ */
        .category-section {
            margin-bottom: 26px;
        }

        .categories-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 12px;
            margin-bottom: 12px;
        }

        @media (max-width: 650px) {
            .categories-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        .category-card {
            border: 1.5px solid var(--border-color);
            border-radius: var(--radius-md);
            padding: 14px 10px;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 8px;
            cursor: pointer;
            background: #FFFFFF;
            transition: var(--transition);
            text-align: center;
            user-select: none;
            position: relative;
        }

        .category-card:hover {
            border-color: #CBD5E1;
            transform: translateY(-2px);
            box-shadow: var(--shadow-sm);
        }

        .category-card.selected {
            border-color: var(--orange);
            background-color: #FFF7ED;
            box-shadow: 0 4px 12px rgba(249, 115, 22, 0.15);
        }

        .category-card.selected::after {
            content: "\f00c";
            font-family: "Font Awesome 6 Free";
            font-weight: 900;
            position: absolute;
            top: 6px;
            right: 6px;
            font-size: 9px;
            width: 16px;
            height: 16px;
            border-radius: 50%;
            background: var(--orange);
            color: #FFFFFF;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .cat-icon-wrap {
            width: 42px;
            height: 42px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            transition: var(--transition);
        }

        .cat-name {
            font-size: 13px;
            font-weight: 600;
            color: var(--navy);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            width: 100%;
        }

        .category-card.selected .cat-name {
            color: #C2410C;
            font-weight: 700;
        }

        .create-cat-link {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 13px;
            font-weight: 600;
            color: var(--blue);
            text-decoration: none;
            cursor: pointer;
            transition: var(--transition);
        }

        .create-cat-link:hover {
            color: var(--blue-hover);
            text-decoration: underline;
        }

        /* 3 & 4. NGÀY & GIỜ GIAO DỊCH */
        .datetime-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
            margin-bottom: 22px;
        }

        @media (max-width: 600px) {
            .datetime-grid {
                grid-template-columns: 1fr;
            }
        }

        .input-group {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .input-group label {
            font-size: 13px;
            font-weight: 600;
            color: var(--navy);
        }

        .input-with-icon {
            position: relative;
            display: flex;
            align-items: center;
        }

        .input-with-icon i {
            position: absolute;
            left: 14px;
            color: var(--text-muted);
            font-size: 15px;
            pointer-events: none;
        }

        .input-with-icon input,
        .input-with-icon select,
        .custom-textarea {
            width: 100%;
            padding: 11px 14px 11px 40px;
            border-radius: var(--radius-md);
            border: 1px solid var(--border-color);
            background: #FFFFFF;
            font-size: 14px;
            color: var(--text-main);
            outline: none;
            transition: var(--transition);
        }

        .input-with-icon input:focus,
        .input-with-icon select:focus,
        .custom-textarea:focus {
            border-color: var(--blue);
            box-shadow: 0 0 0 3px rgba(79, 89, 232, 0.12);
        }

        /* 5. TÀI KHOẢN NGUỒN */
        .payment-section {
            margin-bottom: 22px;
        }

        .custom-select-wrap {
            position: relative;
        }

        .custom-select-wrap select {
            appearance: none;
            padding-right: 40px;
            cursor: pointer;
        }

        .custom-select-wrap .select-arrow {
            position: absolute;
            right: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-muted);
            pointer-events: none;
            font-size: 12px;
        }

        .payment-balance-hint {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-top: 6px;
            padding: 8px 12px;
            background: #F8FAFC;
            border-radius: 8px;
            border: 1px solid var(--border-color);
        }

        .payment-balance-hint .info-text {
            font-size: 12px;
            color: var(--text-muted);
        }

        .payment-balance-hint .balance-num {
            font-size: 13px;
            font-weight: 700;
            color: var(--navy);
        }

        .primary-badge {
            background: #DBEAFE;
            color: #1E40AF;
            font-size: 10px;
            font-weight: 700;
            padding: 2px 6px;
            border-radius: 4px;
            text-transform: uppercase;
        }

        /* 6 & 7. NỘI DUNG TÓM TẮT & GHI CHÚ */
        .desc-section {
            margin-bottom: 22px;
        }

        .notes-section {
            margin-bottom: 22px;
        }

        .custom-textarea {
            padding: 12px 14px;
            min-height: 80px;
            resize: vertical;
            line-height: 1.5;
        }

        /* 8. FILE UPLOAD BIÊN LAI */
        .upload-section {
            margin-bottom: 22px;
        }

        .dropzone-box {
            border: 2px dashed #CBD5E1;
            border-radius: var(--radius-lg);
            padding: 24px 20px;
            text-align: center;
            background: #F8FAFC;
            cursor: pointer;
            transition: var(--transition);
            position: relative;
        }

        .dropzone-box:hover,
        .dropzone-box.dragover {
            border-color: var(--blue);
            background: var(--blue-light);
        }

        .dropzone-icon {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            background: #FFFFFF;
            border: 1px solid var(--border-color);
            margin: 0 auto 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--blue);
            font-size: 20px;
            box-shadow: var(--shadow-sm);
        }

        .dropzone-title {
            font-size: 14px;
            font-weight: 600;
            color: var(--navy);
            margin-bottom: 4px;
        }

        .dropzone-desc {
            font-size: 12px;
            color: var(--text-muted);
        }

        .hidden-file-input {
            display: none;
        }

        .file-preview-card {
            display: none;
            align-items: center;
            justify-content: space-between;
            background: #FFFFFF;
            border: 1px solid var(--border-color);
            padding: 10px 14px;
            border-radius: 10px;
            margin-top: 10px;
        }

        .file-info {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .file-info i {
            color: var(--blue);
            font-size: 22px;
        }

        .file-details h5 {
            font-size: 13px;
            font-weight: 600;
            color: var(--navy);
        }

        .file-details span {
            font-size: 11px;
            color: var(--text-muted);
        }

        .btn-remove-file {
            background: none;
            border: none;
            color: var(--red);
            cursor: pointer;
            padding: 6px;
            font-size: 14px;
        }

        /* 9. CHI TIÊU ĐỊNH KỲ */
        .recurring-section {
            background: #F8FAFC;
            border: 1px solid var(--border-color);
            border-radius: var(--radius-md);
            padding: 16px 18px;
            margin-bottom: 26px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
        }

        .recurring-left {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .switch-toggle {
            position: relative;
            display: inline-block;
            width: 44px;
            height: 24px;
            flex-shrink: 0;
        }

        .switch-toggle input {
            opacity: 0;
            width: 0;
            height: 0;
        }

        .slider {
            position: absolute;
            cursor: pointer;
            top: 0; left: 0; right: 0; bottom: 0;
            background-color: #CBD5E1;
            transition: .3s;
            border-radius: 34px;
        }

        .slider:before {
            position: absolute;
            content: "";
            height: 18px;
            width: 18px;
            left: 3px;
            bottom: 3px;
            background-color: white;
            transition: .3s;
            border-radius: 50%;
        }

        input:checked + .slider {
            background-color: var(--blue);
        }

        input:checked + .slider:before {
            transform: translateX(20px);
        }

        .recurring-label h5 {
            font-size: 13px;
            font-weight: 600;
            color: var(--navy);
        }

        .recurring-label p {
            font-size: 11px;
            color: var(--text-muted);
        }

        .recurring-period-select {
            padding: 6px 12px;
            border-radius: 8px;
            border: 1px solid var(--border-color);
            background: #FFFFFF;
            font-size: 13px;
            font-weight: 500;
            color: var(--navy);
            outline: none;
            transition: var(--transition);
        }

        /* ACTION BUTTONS */
        .form-actions {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 12px;
            padding-top: 10px;
            border-top: 1px solid var(--border-color);
        }

        .btn-cancel {
            background: transparent;
            border: none;
            padding: 11px 20px;
            font-size: 14px;
            font-weight: 600;
            color: var(--text-muted);
            cursor: pointer;
            border-radius: var(--radius-md);
            transition: var(--transition);
        }

        .btn-cancel:hover {
            color: var(--text-main);
            background: #F1F5F9;
        }

        .btn-save-continue {
            background: #FFFFFF;
            border: 1.5px solid var(--blue);
            color: var(--blue);
            padding: 11px 20px;
            font-size: 14px;
            font-weight: 600;
            border-radius: var(--radius-md);
            cursor: pointer;
            transition: var(--transition);
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .btn-save-continue:hover {
            background: var(--blue-light);
        }

        .btn-save-main {
            background: var(--green);
            border: none;
            color: #FFFFFF;
            padding: 11px 26px;
            font-size: 14px;
            font-weight: 700;
            border-radius: var(--radius-md);
            cursor: pointer;
            transition: var(--transition);
            display: flex;
            align-items: center;
            gap: 8px;
            box-shadow: 0 4px 12px rgba(34, 197, 94, 0.3);
        }

        .btn-save-main:hover {
            background: var(--green-hover);
            transform: translateY(-1px);
            box-shadow: 0 6px 16px rgba(34, 197, 94, 0.4);
        }

        .btn-save-main:disabled,
        .btn-save-continue:disabled {
            opacity: 0.65;
            cursor: not-allowed;
            transform: none;
        }

        /* ====== RIGHT SIDEBAR (~35%) ====== */
        .right-sidebar {
            display: flex;
            flex-direction: column;
            gap: 22px;
        }

        .widget-card {
            background: var(--card-bg);
            border-radius: var(--radius-xl);
            border: 1px solid var(--border-color);
            box-shadow: var(--shadow-sm);
            padding: 22px;
        }

        .widget-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 16px;
        }

        .widget-title {
            font-size: 14px;
            font-weight: 700;
            color: var(--navy);
            display: flex;
            align-items: center;
            gap: 8px;
        }

        /* 1. XEM TRƯỚC GIAO DỊCH */
        .live-badge {
            background: #FEF2F2;
            color: var(--red);
            border: 1px solid rgba(239, 68, 68, 0.3);
            font-size: 11px;
            font-weight: 700;
            padding: 3px 8px;
            border-radius: 12px;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }

        .live-pulse {
            width: 7px;
            height: 7px;
            border-radius: 50%;
            background: var(--red);
            box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.7);
            animation: pulse-red 1.6s infinite;
        }

        @keyframes pulse-red {
            0% {
                transform: scale(0.95);
                box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.7);
            }
            70% {
                transform: scale(1);
                box-shadow: 0 0 0 6px rgba(239, 68, 68, 0);
            }
            100% {
                transform: scale(0.95);
                box-shadow: 0 0 0 0 rgba(239, 68, 68, 0);
            }
        }

        .preview-bill-card {
            background: linear-gradient(145deg, #FFFFFF, #F8FAFC);
            border: 1px solid #E2E8F0;
            border-radius: var(--radius-lg);
            padding: 18px;
            position: relative;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.03);
        }

        .preview-top-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 14px;
        }

        .preview-cat-badge {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .preview-cat-icon {
            width: 32px;
            height: 32px;
            border-radius: 8px;
            background: #FFF7ED;
            color: var(--orange);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
        }

        .preview-cat-title {
            font-size: 13px;
            font-weight: 700;
            color: var(--navy);
        }

        .preview-type-pill {
            font-size: 11px;
            font-weight: 700;
            padding: 3px 8px;
            border-radius: 6px;
            background: var(--red-light);
            color: var(--red);
        }

        .preview-type-pill.income {
            background: var(--green-light);
            color: var(--green);
        }

        .preview-amount-wrap {
            margin-bottom: 16px;
            text-align: center;
            padding: 12px;
            background: #FFFFFF;
            border-radius: 10px;
            border: 1px solid var(--border-color);
        }

        .preview-amount-label {
            font-size: 11px;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 4px;
        }

        .preview-amount-val {
            font-size: 26px;
            font-weight: 800;
            color: var(--red);
            letter-spacing: -0.5px;
        }

        .preview-amount-val.income-mode {
            color: var(--green);
        }

        .preview-details-list {
            display: flex;
            flex-direction: column;
            gap: 8px;
            border-top: 1px dashed var(--border-color);
            padding-top: 12px;
        }

        .preview-detail-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            font-size: 12px;
        }

        .preview-detail-item .label {
            color: var(--text-muted);
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .preview-detail-item .value {
            font-weight: 600;
            color: var(--navy);
            text-align: right;
            max-width: 180px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .preview-recurring-tag {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            background: #EEF2FF;
            color: #4F46E5;
            font-size: 11px;
            font-weight: 600;
            padding: 3px 8px;
            border-radius: 6px;
            margin-top: 10px;
        }

        /* 2. TÁC ĐỘNG NGÂN SÁCH */
        .warning-badge-pill {
            background: #FEF3C7;
            color: #B45309;
            border: 1px solid #FDE68A;
            font-size: 11px;
            font-weight: 700;
            padding: 3px 8px;
            border-radius: 12px;
        }

        .warning-badge-pill.danger {
            background: #FEE2E2;
            color: #B91C1C;
            border-color: #FCA5A5;
        }

        .budget-meta-row {
            display: flex;
            justify-content: space-between;
            font-size: 12px;
            margin-bottom: 6px;
        }

        .budget-meta-row .label {
            color: var(--text-muted);
        }

        .budget-meta-row .val {
            font-weight: 700;
            color: var(--navy);
        }

        .progress-bar-container {
            width: 100%;
            height: 10px;
            border-radius: 5px;
            background: #E2E8F0;
            overflow: hidden;
            position: relative;
            margin-bottom: 10px;
        }

        .progress-fill-current {
            height: 100%;
            background: #F59E0B;
            border-radius: 5px;
            transition: width 0.3s ease;
        }

        .progress-fill-current.danger {
            background: var(--red);
        }

        .budget-impact-breakdown {
            background: #F8FAFC;
            border-radius: 8px;
            padding: 10px 12px;
            font-size: 12px;
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .impact-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .impact-item .item-lbl {
            color: var(--text-muted);
        }

        .impact-item .item-val {
            font-weight: 600;
            color: var(--navy);
        }

        .impact-item.final {
            border-top: 1px dashed var(--border-color);
            padding-top: 6px;
            font-weight: 700;
        }

        /* 3. VỪA TẠO GẦN ĐÂY */
        .link-view-all {
            font-size: 12px;
            font-weight: 600;
            color: var(--blue);
            text-decoration: none;
        }

        .link-view-all:hover {
            text-decoration: underline;
        }

        .recent-list {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .recent-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding-bottom: 10px;
            border-bottom: 1px solid var(--border-color);
        }

        .recent-item:last-child {
            border-bottom: none;
            padding-bottom: 0;
        }

        .recent-left {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .recent-icon-wrap {
            width: 34px;
            height: 34px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
        }

        .recent-title {
            font-size: 13px;
            font-weight: 600;
            color: var(--navy);
            max-width: 150px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .recent-meta {
            font-size: 11px;
            color: var(--text-muted);
        }

        .recent-amount {
            font-size: 13px;
            font-weight: 700;
            text-align: right;
        }

        .recent-amount.expense {
            color: var(--red);
        }

        .recent-amount.income {
            color: var(--green);
        }

        /* 4. TIP CARD */
        .tip-card {
            background: linear-gradient(135deg, #4F59E8, #3B82F6);
            color: #FFFFFF;
            border-radius: var(--radius-xl);
            padding: 22px;
            position: relative;
            overflow: hidden;
            box-shadow: 0 10px 20px -5px rgba(79, 89, 232, 0.4);
        }

        .tip-card::after {
            content: "\f0eb";
            font-family: "Font Awesome 6 Free";
            font-weight: 900;
            position: absolute;
            right: -10px;
            bottom: -15px;
            font-size: 100px;
            color: rgba(255, 255, 255, 0.08);
            pointer-events: none;
        }

        .tip-header {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 8px;
        }

        .tip-icon {
            width: 28px;
            height: 28px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.2);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 13px;
        }

        .tip-header h4 {
            font-size: 14px;
            font-weight: 700;
        }

        .tip-content {
            font-size: 12px;
            line-height: 1.6;
            color: rgba(255, 255, 255, 0.9);
        }

        /* ====== TOAST NOTIFICATION ====== */
        .toast-container {
            position: fixed;
            top: 24px;
            right: 24px;
            z-index: 9999;
            display: flex;
            flex-direction: column;
            gap: 10px;
            pointer-events: none;
        }

        .toast-msg {
            background: #FFFFFF;
            border-radius: 12px;
            padding: 14px 20px;
            display: flex;
            align-items: center;
            gap: 12px;
            box-shadow: var(--shadow-xl);
            border-left: 4px solid var(--green);
            pointer-events: auto;
            animation: slideIn 0.3s ease forwards;
            min-width: 300px;
            max-width: 420px;
        }

        .toast-msg.error {
            border-left-color: var(--red);
        }

        @keyframes slideIn {
            from {
                transform: translateX(100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }

        /* ====== CALCULATOR MODAL ====== */
        .calc-modal {
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(15, 23, 42, 0.5);
            backdrop-filter: blur(4px);
            z-index: 1000;
            display: none;
            align-items: center;
            justify-content: center;
        }

        .calc-card {
            background: #FFFFFF;
            border-radius: 18px;
            width: 320px;
            padding: 20px;
            box-shadow: var(--shadow-xl);
            animation: scaleIn 0.2s ease;
        }

        @keyframes scaleIn {
            from { transform: scale(0.9); opacity: 0; }
            to { transform: scale(1); opacity: 1; }
        }

        .calc-screen {
            background: #F8FAFC;
            border: 1px solid var(--border-color);
            border-radius: 10px;
            padding: 14px;
            font-size: 24px;
            font-weight: 700;
            text-align: right;
            margin-bottom: 14px;
            color: var(--navy);
            min-height: 58px;
            word-break: break-all;
        }

        .calc-keys-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 8px;
        }

        .calc-btn {
            height: 44px;
            border-radius: 8px;
            border: 1px solid var(--border-color);
            background: #FFFFFF;
            font-size: 15px;
            font-weight: 600;
            color: var(--navy);
            cursor: pointer;
            transition: var(--transition);
        }

        .calc-btn:hover {
            background: #F1F5F9;
        }

        .calc-btn.op {
            background: var(--blue-light);
            color: var(--blue);
            font-weight: 700;
        }

        .calc-btn.eq {
            background: var(--green);
            color: #FFFFFF;
            border-color: var(--green);
            grid-column: span 2;
        }

        /* ====== MODAL THÊM DANH MỤC ====== */
        .cat-modal {
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(15, 23, 42, 0.5);
            backdrop-filter: blur(4px);
            z-index: 1000;
            display: none;
            align-items: center;
            justify-content: center;
        }

        .cat-modal-card {
            background: #FFFFFF;
            border-radius: 18px;
            width: 400px;
            padding: 24px;
            box-shadow: var(--shadow-xl);
        }
    </style>
</head>
<body>

    <!-- ====== SIDEBAR CHÍNH ====== -->
    <aside class="sidebar">
        <div class="sidebar-brand">
            <div class="brand-logo-icon">
                <i class="fa-solid fa-chart-pie"></i>
            </div>
            <div class="brand-text">
                <h2>FinTrack VN</h2>
                <span>Quản Lý Chi Tiêu</span>
            </div>
        </div>

        <nav class="sidebar-menu">
            <div class="menu-label">Menu Chính</div>
            <a href="index.php" class="nav-item">
                <i class="fa-solid fa-house"></i>
                <span>Trang chủ</span>
            </a>
            <a href="calendar.php" class="nav-item">
                <i class="fa-solid fa-calendar-days"></i>
                <span>Lịch giao dịch</span>
            </a>
            <!-- MENU ITEM ĐANG HOẠT ĐỘNG: THÊM GIAO DỊCH -->
            <a href="add_transaction.php" class="nav-item active">
                <i class="fa-solid fa-circle-plus"></i>
                <span>Thêm giao dịch</span>
            </a>
            <a href="reports.php" class="nav-item">
                <i class="fa-solid fa-chart-line"></i>
                <span>Báo cáo & Phân tích</span>
            </a>

            <div class="menu-label">Hệ Thống</div>
            <a href="settings.php" class="nav-item">
                <i class="fa-solid fa-gear"></i>
                <span>Cài đặt</span>
            </a>
        </nav>

        <div class="sidebar-user">
            <div class="user-info-box">
                <div class="user-avatar">
                    <?php 
                        $userName = $_SESSION['user_name'] ?? 'Tuấn Anh';
                        echo mb_substr($userName, 0, 1, 'UTF-8');
                    ?>
                </div>
                <div class="user-details">
                    <h4><?= htmlspecialchars($userName) ?></h4>
                    <p><?= htmlspecialchars($_SESSION['user_email'] ?? 'patuan@fintrack.vn') ?></p>
                </div>
            </div>
            <a href="logout.php" class="btn-logout" title="Đăng xuất">
                <i class="fa-solid fa-right-from-bracket"></i>
            </a>
        </div>
    </aside>

    <!-- ====== KHU VỰC CHÍNH ====== -->
    <div class="main-wrapper">
        
        <!-- HEADER TRÊN CÙNG -->
        <header class="top-header">
            <div class="header-search">
                <i class="fa-solid fa-magnifying-glass"></i>
                <input type="text" placeholder="Tìm kiếm nhanh hóa đơn, giao dịch, danh mục...">
                <span class="search-key">Ctrl + K</span>
            </div>

            <div class="header-actions">
                <div class="header-badge-balance">
                    <div class="dot"></div>
                    <span>Tổng số dư:</span>
                    <strong><?= formatVND(62900000) ?></strong>
                </div>

                <div class="header-btn-icon" title="Thông báo hệ thống">
                    <i class="fa-regular fa-bell"></i>
                    <div class="notif-dot"></div>
                </div>

                <div class="header-btn-icon" title="Trợ giúp & Hướng dẫn">
                    <i class="fa-regular fa-circle-question"></i>
                </div>
            </div>
        </header>

        <!-- NỘI DUNG CHÍNH (PAGE CONTAINER) -->
        <main class="page-container">

            <!-- PAGE HEADER -->
            <div class="page-header-box">
                <div class="page-header-top">
                    <div class="ref-badge">
                        <i class="fa-solid fa-file-invoice"></i>
                        <span>SỔ QUỸ TIỀN TỆ</span>
                    </div>
                    <span class="ref-code" id="refCodeDisplay"><?= htmlspecialchars($referenceNumber) ?></span>
                </div>
                <h1 class="page-title">Thêm Giao Dịch Mới</h1>
                <p class="page-subtitle">Ghi chép nhanh các khoản thu và chi để kiểm soát dòng tiền chính xác, minh bạch.</p>
            </div>

            <!-- TRANSACTION TYPE TOGGLE (CENTERED) -->
            <div class="type-toggle-wrapper">
                <div class="type-toggle-container">
                    <button type="button" class="type-tab-btn active-expense" id="tabExpense" onclick="switchTransactionType('expense')">
                        <i class="fa-solid fa-circle-minus"></i>
                        <span>⊖ Khoản Chi Tiêu</span>
                    </button>
                    <button type="button" class="type-tab-btn" id="tabIncome" onclick="switchTransactionType('income')">
                        <i class="fa-solid fa-circle-plus"></i>
                        <span>⊕ Khoản Thu Nhập</span>
                    </button>
                </div>
            </div>

            <!-- FORM & RIGHT SIDEBAR GRID -->
            <div class="form-layout-grid">

                <!-- KHU VỰC FORM CHÍNH (~65%) -->
                <div class="form-card">
                    <form id="transactionForm" enctype="multipart/form-data" onsubmit="return false;">
                        <input type="hidden" name="type" id="hiddenType" value="expense">
                        <input type="hidden" name="category_id" id="hiddenCategory" value="1">
                        <input type="hidden" name="reference_code" id="hiddenRefCode" value="<?= htmlspecialchars($referenceNumber) ?>">

                        <!-- 1. SỐ TIỀN GIAO DỊCH (VND) -->
                        <div class="amount-input-box">
                            <label class="form-section-title">
                                <span>Số tiền giao dịch (VND) <b style="color:var(--red)">*</b></span>
                                <span class="sub-text">Hỗ trợ tính toán nhanh</span>
                            </label>

                            <div class="amount-input-container">
                                <span class="amount-symbol-prefix" id="amountPrefix">-</span>
                                <input type="text" class="amount-input-field" id="amountInput" name="amount" value="450.000" placeholder="0" autocomplete="off">
                                <span class="amount-currency">đ</span>

                                <div class="amount-tools">
                                    <button type="button" class="tool-icon-btn" title="Mở máy tính mini" onclick="openCalculator()">
                                        <i class="fa-solid fa-calculator"></i>
                                    </button>
                                    <button type="button" class="tool-icon-btn" title="Làm tròn triệu đồng" onclick="roundAmountToMillion()">
                                        <i class="fa-solid fa-sigma"></i>
                                    </button>
                                </div>
                            </div>

                            <!-- Nút cộng tiền nhanh -->
                            <div class="quick-amounts">
                                <button type="button" class="quick-btn" onclick="addQuickAmount(50000)">+50.000 đ</button>
                                <button type="button" class="quick-btn" onclick="addQuickAmount(100000)">+100.000 đ</button>
                                <button type="button" class="quick-btn" onclick="addQuickAmount(500000)">+500.000 đ</button>
                                <button type="button" class="quick-btn" onclick="addQuickAmount(1000000)">+1.000.000 đ</button>
                                <button type="button" class="quick-btn" onclick="addQuickAmount(5000000)">+5.000.000 đ</button>
                                <button type="button" class="quick-btn clear-btn" onclick="clearAmount()" title="Xóa trắng số tiền">
                                    <i class="fa-solid fa-eraser"></i> Xóa
                                </button>
                            </div>
                        </div>

                        <!-- 2. CHỌN DANH MỤC PHÂN BỔ -->
                        <div class="category-section">
                            <div class="form-section-title">
                                <span>Chọn danh mục phân bổ <b style="color:var(--red)">*</b></span>
                                <span class="sub-text" id="categoryCountLabel">8 danh mục phổ biến</span>
                            </div>

                            <!-- Grid danh mục (4 thẻ / hàng) -->
                            <div class="categories-grid" id="categoriesGrid">
                                <?php foreach ($categoriesExpense as $index => $cat): 
                                    $isSelected = ($index === 0);
                                    $bgStyle = $isSelected ? 'background-color: #FFF7ED; border-color: #F97316;' : '';
                                    $iconBg = 'background-color: ' . htmlspecialchars($cat['color']) . '1A; color: ' . htmlspecialchars($cat['color']) . ';';
                                ?>
                                    <div class="category-card <?= $isSelected ? 'selected' : '' ?>" 
                                         data-id="<?= $cat['id'] ?>"
                                         data-name="<?= htmlspecialchars($cat['name']) ?>"
                                         data-icon="<?= htmlspecialchars($cat['icon']) ?>"
                                         data-color="<?= htmlspecialchars($cat['color']) ?>"
                                         onclick="selectCategory(this)">
                                        <div class="cat-icon-wrap" style="<?= $iconBg ?>">
                                            <i class="fa-solid <?= htmlspecialchars($cat['icon']) ?>"></i>
                                        </div>
                                        <span class="cat-name"><?= htmlspecialchars($cat['name']) ?></span>
                                    </div>
                                <?php endforeach; ?>
                            </div>

                            <a href="javascript:void(0)" class="create-cat-link" onclick="openNewCategoryModal()">
                                <i class="fa-solid fa-plus-circle"></i>
                                <span>+ Tạo thêm danh mục mới</span>
                            </a>
                        </div>

                        <!-- 3 & 4. NGÀY & THỜI GIAN GIAO DỊCH -->
                        <div class="datetime-grid">
                            <div class="input-group">
                                <label for="txDate">Ngày thực hiện giao dịch <b style="color:var(--red)">*</b></label>
                                <div class="input-with-icon">
                                    <i class="fa-regular fa-calendar-days"></i>
                                    <input type="date" id="txDate" name="transaction_date" value="<?= $defaultDate ?>" required>
                                </div>
                            </div>

                            <div class="input-group">
                                <label for="txTime">Thời gian phát sinh</label>
                                <div class="input-with-icon">
                                    <i class="fa-regular fa-clock"></i>
                                    <input type="time" id="txTime" name="transaction_time" value="<?= $defaultTime ?>">
                                </div>
                            </div>
                        </div>

                        <!-- 5. TÀI KHOẢN NGUỒN / PHƯƠNG THỨC THANH TOÁN -->
                        <div class="payment-section">
                            <div class="input-group">
                                <label for="paymentMethodSelect">Tài khoản nguồn / Phương thức thanh toán <b style="color:var(--red)">*</b></label>
                                <div class="input-with-icon custom-select-wrap">
                                    <i class="fa-solid fa-credit-card"></i>
                                    <select id="paymentMethodSelect" name="payment_method_id" onchange="updatePaymentMethodInfo()">
                                        <?php foreach ($paymentMethods as $pm): ?>
                                            <option value="<?= $pm['id'] ?>" 
                                                    data-balance="<?= $pm['balance'] ?>"
                                                    data-name="<?= htmlspecialchars($pm['name']) ?>"
                                                    data-acc="<?= htmlspecialchars($pm['account_number']) ?>"
                                                    data-primary="<?= $pm['is_primary'] ?>"
                                                    <?= $pm['is_primary'] ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($pm['name']) ?> <?= $pm['is_primary'] ? '★ [Chính]' : '' ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <i class="fa-solid fa-chevron-down select-arrow"></i>
                                </div>

                                <div class="payment-balance-hint" id="paymentBalanceBox">
                                    <div class="info-text">
                                        <span class="primary-badge" id="pmBadge">Chính</span>
                                        <span id="pmAccountMask" style="margin-left: 6px;"><?= !empty($paymentMethods) ? htmlspecialchars($paymentMethods[0]['name']) : 'Ví tài khoản' ?></span>
                                    </div>
                                    <div class="balance-num" id="pmBalanceDisplay">
                                        Số dư khả dụng: <?= !empty($paymentMethods) ? formatMoney($paymentMethods[0]['balance']) : '0 đ' ?>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- 6. NỘI DUNG TÓM TẮT -->
                        <div class="desc-section">
                            <div class="input-group">
                                <label for="txDesc">Nội dung tóm tắt <b style="color:var(--red)">*</b></label>
                                <div class="input-with-icon">
                                    <i class="fa-solid fa-pen-to-square"></i>
                                    <input type="text" id="txDesc" name="description" placeholder="Đi siêu thị BigC mua đồ ăn cuối tuần" value="Đi siêu thị BigC mua đồ ăn cuối tuần" required oninput="updatePreviewCard()">
                                </div>
                            </div>
                        </div>

                        <!-- 7. GHI CHÚ CHI TIẾT & BIÊN LAI -->
                        <div class="notes-section">
                            <div class="input-group">
                                <label for="txNotes">Ghi chú chi tiết & Đính kèm biên lai hóa đơn</label>
                                <textarea id="txNotes" name="notes" class="custom-textarea" placeholder="Mua thịt bò, rau xanh và sữa chua không đường." oninput="updatePreviewCard()">Mua thịt bò, rau xanh và sữa chua không đường.</textarea>
                            </div>
                        </div>

                        <!-- 8. FILE UPLOAD BIÊN LAI HÓA ĐƠN -->
                        <div class="upload-section">
                            <div class="dropzone-box" id="dropzoneBox" onclick="document.getElementById('receiptFile').click()">
                                <div class="dropzone-icon">
                                    <i class="fa-solid fa-cloud-arrow-up"></i>
                                </div>
                                <div class="dropzone-title">Kéo thả ảnh hóa đơn hoặc bấm để tải lên</div>
                                <div class="dropzone-desc">Hỗ trợ JPG, PNG, PDF tối đa 10MB</div>
                                <input type="file" id="receiptFile" name="receipt" class="hidden-file-input" accept="image/jpeg,image/png,image/webp,application/pdf" onchange="handleFileSelect(this)">
                            </div>

                            <div class="file-preview-card" id="filePreviewCard">
                                <div class="file-info">
                                    <i class="fa-solid fa-file-invoice" id="fileIcon"></i>
                                    <div class="file-details">
                                        <h5 id="fileNameText">hoadon_bigc.jpg</h5>
                                        <span id="fileSizeText">1.2 MB</span>
                                    </div>
                                </div>
                                <button type="button" class="btn-remove-file" title="Xóa file này" onclick="removeSelectedFile()">
                                    <i class="fa-solid fa-trash-can"></i>
                                </button>
                            </div>
                        </div>

                        <!-- 9. CHI TIÊU ĐỊNH KỲ / LẶP LẠI -->
                        <div class="recurring-section">
                            <div class="recurring-left">
                                <label class="switch-toggle">
                                    <input type="checkbox" id="isRecurring" name="is_recurring" value="1" onchange="toggleRecurringPeriod(this)">
                                    <span class="slider"></span>
                                </label>
                                <div class="recurring-label">
                                    <h5>Chi tiêu định kỳ / Lặp lại</h5>
                                    <p>Tự động ghi nhận giao dịch theo chu kỳ mong muốn</p>
                                </div>
                            </div>

                            <select id="recurringPeriod" name="recurring_period" class="recurring-period-select" disabled onchange="updatePreviewCard()">
                                <option value="monthly" selected>Hàng tháng</option>
                                <option value="weekly">Hàng tuần</option>
                                <option value="quarterly">Hàng quý</option>
                                <option value="yearly">Hàng năm</option>
                            </select>
                        </div>

                        <!-- HÀNH ĐỘNG ACTION BUTTONS -->
                        <div class="form-actions">
                            <button type="button" class="btn-cancel" onclick="resetForm()">
                                Hủy bỏ
                            </button>
                            <button type="button" class="btn-save-continue" id="btnSaveContinue" onclick="submitTransaction(true)">
                                <i class="fa-solid fa-plus"></i>
                                <span>Lưu & Tiếp tục thêm</span>
                            </button>
                            <button type="button" class="btn-save-main" id="btnSaveMain" onclick="submitTransaction(false)">
                                <i class="fa-solid fa-check"></i>
                                <span>✓ Lưu giao dịch</span>
                            </button>
                        </div>
                    </form>
                </div>

                <!-- RIGHT SIDEBAR (~35%) -->
                <div class="right-sidebar">

                    <!-- 1. XEM TRƯỚC GIAO DỊCH (VỚI BADGE TRỰC TIẾP) -->
                    <div class="widget-card">
                        <div class="widget-header">
                            <div class="widget-title">
                                <i class="fa-solid fa-eye" style="color:var(--blue)"></i>
                                <span>Xem trước giao dịch</span>
                            </div>
                            <div class="live-badge">
                                <div class="live-pulse"></div>
                                <span>Trực Tiếp</span>
                            </div>
                        </div>

                        <!-- Card preview hóa đơn -->
                        <div class="preview-bill-card">
                            <div class="preview-top-row">
                                <div class="preview-cat-badge">
                                    <div class="preview-cat-icon" id="previewCatIcon">
                                        <i class="fa-solid fa-utensils"></i>
                                    </div>
                                    <span class="preview-cat-title" id="previewCatTitle">Ăn uống</span>
                                </div>
                                <span class="preview-type-pill" id="previewTypePill">KHOẢN CHI TIÊU</span>
                            </div>

                            <div class="preview-amount-wrap">
                                <div class="preview-amount-label">Số tiền ghi nhận</div>
                                <div class="preview-amount-val" id="previewAmountVal">- 450.000 đ</div>
                            </div>

                            <div class="preview-details-list">
                                <div class="preview-detail-item">
                                    <span class="label"><i class="fa-solid fa-credit-card"></i> Nguồn tiền:</span>
                                    <span class="value" id="previewPaymentVal">Techcombank (••• 8829)</span>
                                </div>
                                <div class="preview-detail-item">
                                    <span class="label"><i class="fa-regular fa-calendar"></i> Thời gian:</span>
                                    <span class="value" id="previewTimeVal"><?= date('d/m/Y') ?> • <?= $defaultTime ?></span>
                                </div>
                                <div class="preview-detail-item">
                                    <span class="label"><i class="fa-solid fa-pen"></i> Tóm tắt:</span>
                                    <span class="value" id="previewDescVal">Đi siêu thị BigC mua đồ ăn cuối tuần</span>
                                </div>
                                <div class="preview-detail-item" id="previewNotesItem">
                                    <span class="label"><i class="fa-regular fa-comment-dots"></i> Ghi chú:</span>
                                    <span class="value" id="previewNotesVal">Mua thịt bò, rau xanh...</span>
                                </div>
                            </div>

                            <div id="previewRecurringWrap" style="display:none;">
                                <div class="preview-recurring-tag">
                                    <i class="fa-solid fa-arrows-rotate"></i>
                                    <span id="previewRecurringPeriod">Lặp lại: Hàng tháng</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- 2. TÁC ĐỘNG NGÂN SÁCH (CẢNH BÁO 85%) -->
                    <div class="widget-card" id="budgetWidgetCard">
                        <div class="widget-header">
                            <div class="widget-title">
                                <i class="fa-solid fa-chart-pie" style="color:var(--orange)"></i>
                                <span>Tác động ngân sách</span>
                            </div>
                            <span class="warning-badge-pill" id="budgetWarningBadge">Cảnh báo 85%</span>
                        </div>

                        <div class="budget-meta-row">
                            <span class="label" id="budgetCatLabel">Ngân sách Ăn uống:</span>
                            <span class="val" id="budgetLimitVal">5.000.000 đ</span>
                        </div>

                        <div class="progress-bar-container">
                            <div class="progress-fill-current" id="budgetProgressFill" style="width: 85%;"></div>
                        </div>

                        <div class="budget-impact-breakdown">
                            <div class="impact-item">
                                <span class="item-lbl">Đã chi trước đó:</span>
                                <span class="item-val" id="budgetSpentBeforeVal">3.800.000 đ (76%)</span>
                            </div>
                            <div class="impact-item">
                                <span class="item-lbl">Khoản chi này:</span>
                                <span class="item-val" style="color:var(--red);" id="budgetTxAmountVal">+ 450.000 đ</span>
                            </div>
                            <div class="impact-item final">
                                <span class="item-lbl">Dự kiến sau giao dịch:</span>
                                <span class="item-val" id="budgetTotalAfterVal">4.250.000 đ (85%)</span>
                            </div>
                            <div class="impact-item" style="color:#059669; font-weight:600;">
                                <span class="item-lbl">Còn lại khả dụng:</span>
                                <span class="item-val" id="budgetRemainingVal">750.000 đ</span>
                            </div>
                        </div>
                    </div>

                    <!-- 3. VỪA TẠO GẦN ĐÂY -->
                    <div class="widget-card">
                        <div class="widget-header">
                            <div class="widget-title">
                                <i class="fa-solid fa-clock-rotate-left" style="color:var(--navy)"></i>
                                <span>Vừa tạo gần đây</span>
                            </div>
                            <a href="transactions.php" class="link-view-all">Xem tất cả</a>
                        </div>

                        <div class="recent-list" id="recentListContainer">
                            <?php if (empty($recentTransactions)): ?>
                                <div style="text-align: center; padding: 30px 15px; color: var(--text-muted); font-size: 13px;">
                                    <i class="fa-regular fa-clock" style="font-size: 26px; margin-bottom: 8px; display: block; opacity: 0.5;"></i>
                                    Chưa có giao dịch phát sinh gần đây
                                </div>
                            <?php else: ?>
                                <?php foreach ($recentTransactions as $rt): 
                                    $isExp = ($rt['type'] === 'expense');
                                    $amountClass = $isExp ? 'expense' : 'income';
                                    $sign = $isExp ? '-' : '+';
                                    $color = $rt['category_color'] ?? ($isExp ? '#F97316' : '#22C55E');
                                    $icon = $rt['category_icon'] ?? ($isExp ? 'fa-utensils' : 'fa-money-bill-wave');
                                ?>
                                    <div class="recent-item">
                                        <div class="recent-left">
                                            <div class="recent-icon-wrap" style="background: <?= $color ?>1A; color: <?= $color ?>;">
                                                <i class="fa-solid <?= $icon ?>"></i>
                                            </div>
                                            <div>
                                                <div class="recent-title"><?= htmlspecialchars($rt['description']) ?></div>
                                                <div class="recent-meta"><?= htmlspecialchars($rt['category_name']) ?> • <?= htmlspecialchars($rt['transaction_time']) ?></div>
                                            </div>
                                        </div>
                                        <div class="recent-amount <?= $amountClass ?>">
                                            <?= $sign ?><?= formatVND($rt['amount']) ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- 4. TIP CARD (MẸO QUẢN LÝ CHI TIÊU FINTRACK) -->
                    <div class="tip-card">
                        <div class="tip-header">
                            <div class="tip-icon">
                                <i class="fa-solid fa-lightbulb"></i>
                            </div>
                            <h4>Mẹo quản lý chi tiêu FinTrack</h4>
                        </div>
                        <p class="tip-content">
                            Ghi nhận giao dịch ngay tại thời điểm thanh toán giúp giảm 40% thất thoát số liệu. Hãy áp dụng quy tắc <strong>50/30/20</strong> để dòng tiền luôn ổn định và sinh lời bền vững!
                        </p>
                    </div>

                </div>
            </div>

        </main>
    </div>

    <!-- ====== TOAST CONTAINER ====== -->
    <div class="toast-container" id="toastContainer"></div>

    <!-- ====== MINI CALCULATOR MODAL ====== -->
    <div class="calc-modal" id="calcModal" onclick="if(event.target === this) closeCalculator()">
        <div class="calc-card">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px;">
                <h4 style="font-size:14px; font-weight:700; color:var(--navy);">Máy tính chi tiêu</h4>
                <button type="button" onclick="closeCalculator()" style="background:none; border:none; cursor:pointer; color:var(--text-muted);"><i class="fa-solid fa-xmark"></i></button>
            </div>
            <div class="calc-screen" id="calcScreen">0</div>
            <div class="calc-keys-grid">
                <button type="button" class="calc-btn" onclick="calcInput('C')">C</button>
                <button type="button" class="calc-btn" onclick="calcInput('DEL')">⌫</button>
                <button type="button" class="calc-btn op" onclick="calcInput('%')">%</button>
                <button type="button" class="calc-btn op" onclick="calcInput('/')">÷</button>

                <button type="button" class="calc-btn" onclick="calcInput('7')">7</button>
                <button type="button" class="calc-btn" onclick="calcInput('8')">8</button>
                <button type="button" class="calc-btn" onclick="calcInput('9')">9</button>
                <button type="button" class="calc-btn op" onclick="calcInput('*')">×</button>

                <button type="button" class="calc-btn" onclick="calcInput('4')">4</button>
                <button type="button" class="calc-btn" onclick="calcInput('5')">5</button>
                <button type="button" class="calc-btn" onclick="calcInput('6')">6</button>
                <button type="button" class="calc-btn op" onclick="calcInput('-')">-</button>

                <button type="button" class="calc-btn" onclick="calcInput('1')">1</button>
                <button type="button" class="calc-btn" onclick="calcInput('2')">2</button>
                <button type="button" class="calc-btn" onclick="calcInput('3')">3</button>
                <button type="button" class="calc-btn op" onclick="calcInput('+')">+</button>

                <button type="button" class="calc-btn" onclick="calcInput('0')">0</button>
                <button type="button" class="calc-btn" onclick="calcInput('000')">000</button>
                <button type="button" class="calc-btn eq" onclick="applyCalculator()">Áp dụng =</button>
            </div>
        </div>
    </div>

    <!-- ====== MODAL TẠO NHANH DANH MỤC MỚI ====== -->
    <div class="cat-modal" id="catModal" onclick="if(event.target === this) closeNewCategoryModal()">
        <div class="cat-modal-card">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
                <h4 style="font-size:16px; font-weight:700; color:var(--navy);">Tạo danh mục mới</h4>
                <button type="button" onclick="closeNewCategoryModal()" style="background:none; border:none; cursor:pointer; color:var(--text-muted);"><i class="fa-solid fa-xmark"></i></button>
            </div>
            <div style="display:flex; flex-direction:column; gap:12px;">
                <div>
                    <label style="font-size:12px; font-weight:600; color:var(--navy);">Tên danh mục</label>
                    <input type="text" id="newCatName" placeholder="Ví dụ: Du lịch, Thú cưng, Quà tặng..." style="width:100%; padding:10px 12px; border:1px solid var(--border-color); border-radius:8px; margin-top:4px;">
                </div>
                <div>
                    <label style="font-size:12px; font-weight:600; color:var(--navy);">Biểu tượng (FontAwesome icon)</label>
                    <select id="newCatIcon" style="width:100%; padding:10px 12px; border:1px solid var(--border-color); border-radius:8px; margin-top:4px;">
                        <option value="fa-plane">Máy bay (fa-plane)</option>
                        <option value="fa-paw">Thú cưng (fa-paw)</option>
                        <option value="fa-gift">Quà biếu (fa-gift)</option>
                        <option value="fa-gamepad">Trò chơi (fa-gamepad)</option>
                        <option value="fa-book">Sách vở (fa-book)</option>
                        <option value="fa-shirt">Trang phục (fa-shirt)</option>
                        <option value="fa-gem">Trang sức (fa-gem)</option>
                        <option value="fa-shield-halved">Bảo hiểm (fa-shield-halved)</option>
                    </select>
                </div>
                <div>
                    <label style="font-size:12px; font-weight:600; color:var(--navy);">Màu sắc đại diện</label>
                    <input type="color" id="newCatColor" value="#8B5CF6" style="width:100%; height:40px; border:1px solid var(--border-color); border-radius:8px; margin-top:4px; padding:2px; cursor:pointer;">
                </div>
                <div style="display:flex; justify-content:flex-end; gap:10px; margin-top:8px;">
                    <button type="button" onclick="closeNewCategoryModal()" style="padding:8px 16px; border:none; background:#F1F5F9; border-radius:8px; cursor:pointer; font-weight:600; color:var(--text-muted);">Đóng</button>
                    <button type="button" onclick="saveNewCategoryAjax()" style="padding:8px 18px; border:none; background:var(--blue); color:#FFF; border-radius:8px; cursor:pointer; font-weight:700;">Lưu danh mục</button>
                </div>
            </div>
        </div>
    </div>

    <!-- ====== JAVASCRIPT XỬ LÝ TOÀN BỘ LOGIC ====== -->
    <script>
        // Dữ liệu danh mục thu/chi được truyền từ PHP
        const expenseCategories = <?= json_encode($categoriesExpense, JSON_UNESCAPED_UNICODE) ?>;
        const incomeCategories = <?= json_encode($categoriesIncome, JSON_UNESCAPED_UNICODE) ?>;
        
        // Ngân sách hiện tại
        const categoryBudgets = <?= json_encode($categoryBudgets, JSON_UNESCAPED_UNICODE) ?>;

        let currentType = 'expense';
        let selectedCategory = expenseCategories[0] || { id: 1, name: 'Ăn uống', icon: 'fa-utensils', color: '#F97316' };

        // DOM elements
        const amountInput = document.getElementById('amountInput');
        const hiddenType = document.getElementById('hiddenType');
        const hiddenCategory = document.getElementById('hiddenCategory');
        const categoriesGrid = document.getElementById('categoriesGrid');
        const tabExpense = document.getElementById('tabExpense');
        const tabIncome = document.getElementById('tabIncome');
        const amountPrefix = document.getElementById('amountPrefix');

        // Khởi tạo trang
        document.addEventListener('DOMContentLoaded', function() {
            initAmountInput();
            updatePaymentMethodInfo();
            updatePreviewCard();
            updateBudgetWidget();
            setupDragAndDrop();
        });

        // 1. TỰ ĐỘNG ĐỊNH DẠNG SỐ TIỀN VỚI DẤU PHÂN CÁCH NGHÌN
        function initAmountInput() {
            amountInput.addEventListener('input', function(e) {
                let value = this.value.replace(/[^\d]/g, '');
                if (value === '') {
                    this.value = '';
                } else {
                    this.value = formatNumber(parseInt(value, 10));
                }
                updatePreviewCard();
                updateBudgetWidget();
            });

            amountInput.addEventListener('blur', function() {
                if (this.value === '' || parseInt(this.value.replace(/[^\d]/g, ''), 10) === 0) {
                    this.value = '0';
                }
                updatePreviewCard();
                updateBudgetWidget();
            });
        }

        function getRawAmount() {
            const raw = amountInput.value.replace(/[^\d]/g, '');
            return parseInt(raw, 10) || 0;
        }

        function formatNumber(num) {
            return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, '.');
        }

        function formatVNDDisplay(num) {
            return formatNumber(num) + ' đ';
        }

        // Quick amount buttons
        function addQuickAmount(plusVal) {
            let current = getRawAmount();
            current += plusVal;
            amountInput.value = formatNumber(current);
            updatePreviewCard();
            updateBudgetWidget();
        }

        function clearAmount() {
            amountInput.value = '0';
            updatePreviewCard();
            updateBudgetWidget();
        }

        function roundAmountToMillion() {
            let current = getRawAmount();
            if (current <= 0) current = 1000000;
            else current = Math.ceil(current / 1000000) * 1000000;
            amountInput.value = formatNumber(current);
            updatePreviewCard();
            updateBudgetWidget();
        }

        // 2. CHUYỂN ĐỔI GIỮA KHOẢN CHI TIÊU & KHOẢN THU NHẬP
        function switchTransactionType(type) {
            currentType = type;
            hiddenType.value = type;

            if (type === 'expense') {
                tabExpense.className = 'type-tab-btn active-expense';
                tabIncome.className = 'type-tab-btn';
                amountPrefix.textContent = '-';
                amountPrefix.className = 'amount-symbol-prefix';
                renderCategoryCards(expenseCategories);
                document.getElementById('budgetWidgetCard').style.display = 'block';
            } else {
                tabExpense.className = 'type-tab-btn';
                tabIncome.className = 'type-tab-btn active-income';
                amountPrefix.textContent = '+';
                amountPrefix.className = 'amount-symbol-prefix income-mode';
                renderCategoryCards(incomeCategories);
                // Với thu nhập có thể ẩn hoặc chuyển đổi widget ngân sách
                document.getElementById('budgetWidgetCard').style.display = 'none';
            }

            updatePreviewCard();
            updateBudgetWidget();
        }

        // Render các thẻ danh mục theo type
        function renderCategoryCards(cats) {
            categoriesGrid.innerHTML = '';
            document.getElementById('categoryCountLabel').textContent = cats.length + ' danh mục phổ biến';

            if (!cats || cats.length === 0) return;

            // Mặc định chọn phần tử đầu tiên
            selectedCategory = cats[0];
            hiddenCategory.value = selectedCategory.id;

            cats.forEach((cat, index) => {
                const card = document.createElement('div');
                card.className = 'category-card' + (index === 0 ? ' selected' : '');
                card.dataset.id = cat.id;
                card.dataset.name = cat.name;
                card.dataset.icon = cat.icon;
                card.dataset.color = cat.color;

                const iconBg = `background-color: ${cat.color}1A; color: ${cat.color};`;

                card.innerHTML = `
                    <div class="cat-icon-wrap" style="${iconBg}">
                        <i class="fa-solid ${cat.icon}"></i>
                    </div>
                    <span class="cat-name">${escapeHtml(cat.name)}</span>
                `;

                card.onclick = function() {
                    selectCategory(this);
                };

                categoriesGrid.appendChild(card);
            });
        }

        function selectCategory(el) {
            document.querySelectorAll('.category-card').forEach(c => c.classList.remove('selected'));
            el.classList.add('selected');

            selectedCategory = {
                id: el.dataset.id,
                name: el.dataset.name,
                icon: el.dataset.icon,
                color: el.dataset.color
            };

            hiddenCategory.value = selectedCategory.id;
            updatePreviewCard();
            updateBudgetWidget();
        }

        // 3. CẬP NHẬT THÔNG TIN TÀI KHOẢN NGUỒN
        function updatePaymentMethodInfo() {
            const select = document.getElementById('paymentMethodSelect');
            const opt = select.options[select.selectedIndex];
            if (!opt) return;

            const name = opt.dataset.name;
            const balance = parseFloat(opt.dataset.balance) || 0;
            const isPrimary = opt.dataset.primary === '1';
            const acc = opt.dataset.acc;

            document.getElementById('pmBadge').style.display = isPrimary ? 'inline-block' : 'none';
            document.getElementById('pmAccountMask').textContent = name + (acc ? ` (Đuôi ~${acc})` : '');
            document.getElementById('pmBalanceDisplay').textContent = 'Số dư khả dụng: ' + formatVNDDisplay(balance);

            updatePreviewCard();
        }

        // 4. CẬP NHẬT XEM TRƯỚC GIAO DỊCH (REAL-TIME PREVIEW)
        function updatePreviewCard() {
            const rawAmount = getRawAmount();
            const desc = document.getElementById('txDesc').value.trim() || 'Chưa nhập mô tả';
            const notes = document.getElementById('txNotes').value.trim();
            const dateVal = document.getElementById('txDate').value;
            const timeVal = document.getElementById('txTime').value;

            // Cập nhật Danh mục
            const previewCatIcon = document.getElementById('previewCatIcon');
            const previewCatTitle = document.getElementById('previewCatTitle');
            previewCatIcon.style.backgroundColor = (selectedCategory.color || '#F97316') + '22';
            previewCatIcon.style.color = selectedCategory.color || '#F97316';
            previewCatIcon.innerHTML = `<i class="fa-solid ${selectedCategory.icon || 'fa-tag'}"></i>`;
            previewCatTitle.textContent = selectedCategory.name || 'Danh mục';

            // Cập nhật Loại giao dịch
            const typePill = document.getElementById('previewTypePill');
            const amountVal = document.getElementById('previewAmountVal');

            if (currentType === 'expense') {
                typePill.textContent = 'KHOẢN CHI TIÊU';
                typePill.className = 'preview-type-pill';
                amountVal.className = 'preview-amount-val';
                amountVal.textContent = '- ' + formatVNDDisplay(rawAmount);
            } else {
                typePill.textContent = 'KHOẢN THU NHẬP';
                typePill.className = 'preview-type-pill income';
                amountVal.className = 'preview-amount-val income-mode';
                amountVal.textContent = '+ ' + formatVNDDisplay(rawAmount);
            }

            // Cập nhật Tài khoản
            const pmSelect = document.getElementById('paymentMethodSelect');
            const pmOpt = pmSelect.options[pmSelect.selectedIndex];
            if (pmOpt) {
                const pmName = pmOpt.dataset.name;
                const pmAcc = pmOpt.dataset.acc ? ` (••• ${pmOpt.dataset.acc})` : '';
                document.getElementById('previewPaymentVal').textContent = pmName + pmAcc;
            }

            // Cập nhật Thời gian
            let formattedDate = dateVal;
            if (dateVal) {
                const parts = dateVal.split('-');
                if (parts.length === 3) formattedDate = `${parts[2]}/${parts[1]}/${parts[0]}`;
            }
            document.getElementById('previewTimeVal').textContent = (formattedDate || 'Hôm nay') + ' • ' + (timeVal || '14:30');

            // Cập nhật Mô tả
            document.getElementById('previewDescVal').textContent = desc;

            // Ghi chú
            const notesWrap = document.getElementById('previewNotesItem');
            if (notes) {
                notesWrap.style.display = 'flex';
                document.getElementById('previewNotesVal').textContent = notes;
            } else {
                notesWrap.style.display = 'none';
            }

            // Định kỳ
            const isRec = document.getElementById('isRecurring').checked;
            const recWrap = document.getElementById('previewRecurringWrap');
            if (isRec) {
                recWrap.style.display = 'block';
                const recPeriodText = document.getElementById('recurringPeriod').options[document.getElementById('recurringPeriod').selectedIndex].text;
                document.getElementById('previewRecurringPeriod').textContent = 'Lặp lại: ' + recPeriodText;
            } else {
                recWrap.style.display = 'none';
            }
        }

        // 5. CẬP NHẬT TÁC ĐỘNG NGÂN SÁCH (BUDGET IMPACT)
        function updateBudgetWidget() {
            if (currentType !== 'expense') return;

            const catId = selectedCategory.id;
            const txAmount = getRawAmount();

            // Lấy thông tin ngân sách của danh mục (hoặc lấy mặc định nếu chưa có)
            let budget = categoryBudgets[catId];
            if (!budget) {
                // Mặc định ngân sách 5.000.000 đ và đã chi 3.800.000 đ nếu là danh mục ăn uống, hoặc sinh tỷ lệ tương ứng
                if (catId == 1) {
                    budget = { amount: 5000000, spent: 3800000 };
                } else {
                    budget = { amount: 3000000, spent: 1200000 };
                }
            }

            const limit = budget.amount;
            const spentBefore = budget.spent;
            const spentAfter = spentBefore + txAmount;
            const percentBefore = Math.round((spentBefore / limit) * 100);
            const percentAfter = Math.min(Math.round((spentAfter / limit) * 100), 100);
            const rawPercentAfter = Math.round((spentAfter / limit) * 100);
            const remaining = Math.max(0, limit - spentAfter);

            document.getElementById('budgetCatLabel').textContent = 'Ngân sách ' + selectedCategory.name + ':';
            document.getElementById('budgetLimitVal').textContent = formatVNDDisplay(limit);
            document.getElementById('budgetSpentBeforeVal').textContent = formatVNDDisplay(spentBefore) + ` (${percentBefore}%)`;
            document.getElementById('budgetTxAmountVal').textContent = '+ ' + formatVNDDisplay(txAmount);
            document.getElementById('budgetTotalAfterVal').textContent = formatVNDDisplay(spentAfter) + ` (${rawPercentAfter}%)`;
            document.getElementById('budgetRemainingVal').textContent = formatVNDDisplay(remaining);

            const badge = document.getElementById('budgetWarningBadge');
            const progress = document.getElementById('budgetProgressFill');

            progress.style.width = percentAfter + '%';

            if (rawPercentAfter >= 100) {
                badge.className = 'warning-badge-pill danger';
                badge.textContent = `Vượt ${rawPercentAfter}%!`;
                progress.className = 'progress-fill-current danger';
            } else if (rawPercentAfter >= 80) {
                badge.className = 'warning-badge-pill';
                badge.textContent = `Cảnh báo ${rawPercentAfter}%`;
                progress.className = 'progress-fill-current';
            } else {
                badge.className = 'warning-badge-pill';
                badge.style.background = '#ECFDF5';
                badge.style.color = '#059669';
                badge.style.borderColor = '#A7F3D0';
                badge.textContent = `An toàn ${rawPercentAfter}%`;
                progress.className = 'progress-fill-current';
                progress.style.background = '#22C55E';
            }
        }

        // 6. XỬ LÝ UPLOAD FILE BIÊN LAI (DRAG & DROP)
        function setupDragAndDrop() {
            const dropzone = document.getElementById('dropzoneBox');
            ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(evt => {
                dropzone.addEventListener(evt, function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                }, false);
            });

            ['dragenter', 'dragover'].forEach(evt => {
                dropzone.addEventListener(evt, () => dropzone.classList.add('dragover'), false);
            });

            ['dragleave', 'drop'].forEach(evt => {
                dropzone.addEventListener(evt, () => dropzone.classList.remove('dragover'), false);
            });

            dropzone.addEventListener('drop', function(e) {
                const dt = e.dataTransfer;
                const files = dt.files;
                if (files.length > 0) {
                    document.getElementById('receiptFile').files = files;
                    showFileInfo(files[0]);
                }
            });
        }

        function handleFileSelect(input) {
            if (input.files && input.files[0]) {
                showFileInfo(input.files[0]);
            }
        }

        function showFileInfo(file) {
            const previewCard = document.getElementById('filePreviewCard');
            const dropzone = document.getElementById('dropzoneBox');
            const fileNameText = document.getElementById('fileNameText');
            const fileSizeText = document.getElementById('fileSizeText');
            const fileIcon = document.getElementById('fileIcon');

            fileNameText.textContent = file.name;
            const sizeKB = Math.round(file.size / 1024);
            fileSizeText.textContent = sizeKB > 1024 ? (sizeKB / 1024).toFixed(1) + ' MB' : sizeKB + ' KB';

            if (file.type.includes('pdf')) {
                fileIcon.className = 'fa-solid fa-file-pdf';
                fileIcon.style.color = '#EF4444';
            } else {
                fileIcon.className = 'fa-solid fa-file-image';
                fileIcon.style.color = '#3B82F6';
            }

            dropzone.style.display = 'none';
            previewCard.style.display = 'flex';
        }

        function removeSelectedFile() {
            document.getElementById('receiptFile').value = '';
            document.getElementById('dropzoneBox').style.display = 'block';
            document.getElementById('filePreviewCard').style.display = 'none';
        }

        function toggleRecurringPeriod(toggle) {
            document.getElementById('recurringPeriod').disabled = !toggle.checked;
            updatePreviewCard();
        }

        // 7. GỬI FORM AJAX LÊN API/SAVE_TRANSACTION.PHP
        function submitTransaction(keepAdding) {
            const amount = getRawAmount();
            if (amount <= 0) {
                showToast('Vui lòng nhập số tiền giao dịch hợp lệ lớn hơn 0 đ!', 'error');
                amountInput.focus();
                return;
            }

            const desc = document.getElementById('txDesc').value.trim();
            if (!desc) {
                showToast('Vui lòng nhập nội dung tóm tắt cho giao dịch!', 'error');
                document.getElementById('txDesc').focus();
                return;
            }

            const btnSaveMain = document.getElementById('btnSaveMain');
            const btnSaveContinue = document.getElementById('btnSaveContinue');

            btnSaveMain.disabled = true;
            btnSaveContinue.disabled = true;
            btnSaveMain.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Đang lưu...';

            const form = document.getElementById('transactionForm');
            const formData = new FormData(form);

            fetch('api/save_transaction.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                btnSaveMain.disabled = false;
                btnSaveContinue.disabled = false;
                btnSaveMain.innerHTML = '<i class="fa-solid fa-check"></i> ✓ Lưu giao dịch';

                if (data.success) {
                    showToast(data.message || 'Lưu giao dịch thành công!', 'success');

                    // Cập nhật số dư tài khoản vừa lưu
                    if (data.data && data.data.new_balance !== undefined) {
                        const sel = document.getElementById('paymentMethodSelect');
                        const opt = sel.options[sel.selectedIndex];
                        if (opt) {
                            opt.dataset.balance = data.data.new_balance;
                            updatePaymentMethodInfo();
                        }
                    }

                    // Thêm giao dịch vừa lưu vào danh sách "Vừa tạo gần đây"
                    prependRecentTransaction(data.data);

                    // Tạo mã phiếu mới cho lần tiếp theo
                    generateNewRefCode();

                    if (keepAdding) {
                        // Reset một phần form để tiếp tục nhập nhanh
                        amountInput.value = '0';
                        document.getElementById('txDesc').value = '';
                        document.getElementById('txNotes').value = '';
                        removeSelectedFile();
                        updatePreviewCard();
                        updateBudgetWidget();
                        amountInput.focus();
                    } else {
                        // Thông báo và chuyển hướng hoặc giữ trạng thái hoàn thành
                        setTimeout(() => {
                            window.location.href = 'transactions.php';
                        }, 1200);
                    }
                } else {
                    showToast(data.message || 'Không thể lưu giao dịch!', 'error');
                }
            })
            .catch(err => {
                btnSaveMain.disabled = false;
                btnSaveContinue.disabled = false;
                btnSaveMain.innerHTML = '<i class="fa-solid fa-check"></i> ✓ Lưu giao dịch';
                showToast('Lỗi kết nối máy chủ: ' + err.message, 'error');
            });
        }

        function prependRecentTransaction(tx) {
            const list = document.getElementById('recentListContainer');
            if (!list || !tx) return;

            const isExp = (tx.type === 'expense');
            const amountClass = isExp ? 'expense' : 'income';
            const sign = isExp ? '-' : '+';
            const color = selectedCategory.color || '#F97316';
            const icon = selectedCategory.icon || 'fa-utensils';

            const item = document.createElement('div');
            item.className = 'recent-item';
            item.innerHTML = `
                <div class="recent-left">
                    <div class="recent-icon-wrap" style="background: ${color}1A; color: ${color};">
                        <i class="fa-solid ${icon}"></i>
                    </div>
                    <div>
                        <div class="recent-title">${escapeHtml(tx.description)}</div>
                        <div class="recent-meta">${escapeHtml(tx.category_name)} • Vừa xong</div>
                    </div>
                </div>
                <div class="recent-amount ${amountClass}">
                    ${sign}${formatVNDDisplay(tx.amount)}
                </div>
            `;

            list.insertBefore(item, list.firstChild);
            // Giữ tối đa 3 item
            if (list.children.length > 3) {
                list.removeChild(list.lastChild);
            }
        }

        function generateNewRefCode() {
            const rand = Math.floor(100 + Math.random() * 900);
            const newCode = '#FTV-2410-' + rand;
            document.getElementById('refCodeDisplay').textContent = newCode;
            document.getElementById('hiddenRefCode').value = newCode;
        }

        function resetForm() {
            if (confirm('Bạn có chắc chắn muốn hủy bỏ và nhập lại thông tin giao dịch?')) {
                document.getElementById('transactionForm').reset();
                amountInput.value = '450.000';
                removeSelectedFile();
                switchTransactionType('expense');
                updatePaymentMethodInfo();
                updatePreviewCard();
                updateBudgetWidget();
            }
        }

        // 8. MINI CALCULATOR LOGIC
        let calcVal = '0';
        function openCalculator() {
            calcVal = getRawAmount().toString();
            document.getElementById('calcScreen').textContent = calcVal;
            document.getElementById('calcModal').style.display = 'flex';
        }

        function closeCalculator() {
            document.getElementById('calcModal').style.display = 'none';
        }

        function calcInput(key) {
            const screen = document.getElementById('calcScreen');
            if (key === 'C') {
                calcVal = '0';
            } else if (key === 'DEL') {
                calcVal = calcVal.slice(0, -1);
                if (calcVal === '') calcVal = '0';
            } else if (key === '%') {
                try {
                    let res = eval(calcVal);
                    calcVal = (res / 100).toString();
                } catch(e) {}
            } else {
                if (calcVal === '0' && !isNaN(key)) {
                    calcVal = key;
                } else {
                    calcVal += key;
                }
            }
            screen.textContent = calcVal;
        }

        function applyCalculator() {
            try {
                // Đánh giá biểu thức toán học an toàn
                let cleanExp = calcVal.replace(/[^0-9+\-*/.]/g, '');
                let res = Math.round(Function('"use strict";return (' + cleanExp + ')')());
                if (!isNaN(res) && res >= 0) {
                    amountInput.value = formatNumber(res);
                    updatePreviewCard();
                    updateBudgetWidget();
                    closeCalculator();
                }
            } catch(e) {
                alert('Phép tính không hợp lệ!');
            }
        }

        // 9. MODAL TẠO DANH MỤC MỚI NHANH
        function openNewCategoryModal() {
            document.getElementById('catModal').style.display = 'flex';
            document.getElementById('newCatName').focus();
        }

        function closeNewCategoryModal() {
            document.getElementById('catModal').style.display = 'none';
        }

        function saveNewCategoryAjax() {
            const name = document.getElementById('newCatName').value.trim();
            const icon = document.getElementById('newCatIcon').value;
            const color = document.getElementById('newCatColor').value;

            if (!name) {
                alert('Vui lòng nhập tên danh mục!');
                return;
            }

            const formData = new FormData();
            formData.append('name', name);
            formData.append('type', currentType);
            formData.append('icon', icon);
            formData.append('color', color);

            fetch('api/save_category.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.success && data.category) {
                    showToast('Đã thêm danh mục mới thành công!', 'success');
                    closeNewCategoryModal();
                    document.getElementById('newCatName').value = '';

                    const newCat = data.category;
                    if (currentType === 'expense') {
                        expenseCategories.push(newCat);
                        renderCategoryCards(expenseCategories);
                    } else {
                        incomeCategories.push(newCat);
                        renderCategoryCards(incomeCategories);
                    }

                    // Tự động chọn danh mục vừa tạo
                    const cards = document.querySelectorAll('.category-card');
                    if (cards.length > 0) {
                        selectCategory(cards[cards.length - 1]);
                    }
                } else {
                    alert(data.message || 'Lỗi thêm danh mục');
                }
            })
            .catch(err => {
                alert('Lỗi: ' + err.message);
            });
        }

        // 10. TOAST NOTIFICATION UTILITY
        function showToast(message, type = 'success') {
            const container = document.getElementById('toastContainer');
            const toast = document.createElement('div');
            toast.className = 'toast-msg ' + (type === 'error' ? 'error' : '');

            const iconClass = type === 'error' ? 'fa-triangle-exclamation' : 'fa-circle-check';
            const iconColor = type === 'error' ? 'var(--red)' : 'var(--green)';

            toast.innerHTML = `
                <i class="fa-solid ${iconClass}" style="color:${iconColor}; font-size: 20px;"></i>
                <div style="flex:1; font-size:13px; font-weight:600; color:var(--navy); line-height:1.4;">
                    ${escapeHtml(message)}
                </div>
            `;

            container.appendChild(toast);

            setTimeout(() => {
                toast.style.transition = 'opacity 0.4s ease, transform 0.4s ease';
                toast.style.opacity = '0';
                toast.style.transform = 'translateY(-10px)';
                setTimeout(() => toast.remove(), 400);
            }, 3500);
        }

        function escapeHtml(text) {
            if (!text) return '';
            const map = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            };
            return text.toString().replace(/[&<>"']/g, m => map[m]);
        }
    </script>
</body>
</html>
