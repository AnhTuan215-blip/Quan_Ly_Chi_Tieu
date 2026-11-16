<?php
/**
 * FinTrack VN - Sổ Quản Lý Tài Chính & Chi Tiêu Thông Minh
 * Trang: Lịch Giao Dịch & Chi Tiêu Hàng Ngày (Transaction Calendar)
 * Đường dẫn: calendar.php
 */

// Bắt đầu session và nạp file cấu hình
$configPath = __DIR__ . '/config.php';
if (file_exists($configPath)) {
    require_once $configPath;
} else {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
}

// Kiểm tra quyền xác thực người dùng
if (function_exists('checkAuth')) {
    checkAuth();
}

$userId = (int)($_SESSION['user_id'] ?? 1);
$isDemoAdmin = ($userId === 1);

$currentUser = [
    'id' => $userId,
    'name' => $_SESSION['full_name'] ?? ($_SESSION['user_name'] ?? 'Nguyễn Văn An'),
    'email' => $_SESSION['email'] ?? ($_SESSION['user_email'] ?? 'an.nguyen@email.com'),
    'role' => $_SESSION['membership_type'] ?? ($_SESSION['user_role'] ?? 'Thành viên FinTrack'),
    'avatar' => $_SESSION['avatar'] ?? 'https://images.unsplash.com/photo-1535713875002-d1d0cf377fde?auto=format&fit=crop&w=200&q=80'
];

// Lấy tham số tháng và năm từ URL (mặc định Tháng 10/2024 theo thiết kế)
$selectedMonth = isset($_GET['month']) ? (int)$_GET['month'] : 10;
$selectedYear = isset($_GET['year']) ? (int)$_GET['year'] : 2024;
$selectedDate = isset($_GET['date']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['date']) ? $_GET['date'] : '2024-10-24';

// Tính toán tháng trước và tháng sau
$prevMonth = $selectedMonth - 1;
$prevYear = $selectedYear;
if ($prevMonth < 1) {
    $prevMonth = 12;
    $prevYear--;
}

$nextMonth = $selectedMonth + 1;
$nextYear = $selectedYear;
if ($nextMonth > 12) {
    $nextMonth = 1;
    $nextYear++;
}

// Danh sách danh mục & phương thức thanh toán cho modal thêm giao dịch
$categoriesList = [
    ['id' => 1, 'name' => 'Ăn uống', 'type' => 'expense', 'icon' => 'fa-utensils', 'color' => '#F97316'],
    ['id' => 2, 'name' => 'Mua sắm', 'type' => 'expense', 'icon' => 'fa-bag-shopping', 'color' => '#EC4899'],
    ['id' => 3, 'name' => 'Di chuyển', 'type' => 'expense', 'icon' => 'fa-car', 'color' => '#3B82F6'],
    ['id' => 4, 'name' => 'Hóa đơn & Dịch vụ', 'type' => 'expense', 'icon' => 'fa-file-invoice', 'color' => '#089182'],
    ['id' => 5, 'name' => 'Giải trí', 'type' => 'expense', 'icon' => 'fa-film', 'color' => '#8B5CF6'],
    ['id' => 6, 'name' => 'Giáo dục', 'type' => 'expense', 'icon' => 'fa-graduation-cap', 'color' => '#06B6D4'],
    ['id' => 7, 'name' => 'Y tế & Sức khỏe', 'type' => 'expense', 'icon' => 'fa-heart-pulse', 'color' => '#EF4444'],
    ['id' => 8, 'name' => 'Nhà cửa', 'type' => 'expense', 'icon' => 'fa-house', 'color' => '#10B981'],
    ['id' => 9, 'name' => 'Lương thưởng', 'type' => 'income', 'icon' => 'fa-briefcase', 'color' => '#10B981'],
    ['id' => 10, 'name' => 'Đầu tư sinh lời', 'type' => 'income', 'icon' => 'fa-chart-line', 'color' => '#009488']
];

$paymentMethodsList = [
    ['id' => 1, 'name' => 'Techcombank Visa Signature', 'type' => 'bank'],
    ['id' => 2, 'name' => 'Ví Điện Tử MoMo', 'type' => 'ewallet'],
    ['id' => 3, 'name' => 'Tiền mặt ví cá nhân', 'type' => 'cash'],
    ['id' => 4, 'name' => 'MB Bank', 'type' => 'bank']
];

// Nếu có kết nối MySQL, nạp danh mục và phương thức thanh toán từ DB
if (isset($pdo) && $pdo) {
    try {
        $dbCats = $pdo->query("SELECT id, name, type, icon, color FROM categories WHERE is_active = 1 OR is_default = 1 ORDER BY id ASC")->fetchAll();
        if (!empty($dbCats)) {
            $categoriesList = $dbCats;
        }
        $dbPms = $pdo->query("SELECT id, name, type FROM payment_methods WHERE is_active = 1 ORDER BY is_primary DESC, id ASC")->fetchAll();
        if (!empty($dbPms)) {
            $paymentMethodsList = $dbPms;
        }
    } catch (Exception $e) {}
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lịch Giao Dịch & Chi Tiêu Hàng Ngày - FinTrack VN</title>
    <!-- Google Fonts: Inter & Plus Jakarta Sans -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <!-- Font Awesome 6 Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    
    <style>
        :root {
            --primary: #4F59E8;
            --primary-hover: #4338CA;
            --primary-light: #EEF2FF;
            --primary-dark: #3730A3;
            
            --success: #10B981;
            --success-hover: #059669;
            --success-light: #ECFDF5;
            --success-border: #A7F3D0;
            --success-text: #065F46;
            
            --danger: #EF4444;
            --danger-hover: #DC2626;
            --danger-light: #FEF2F2;
            --danger-border: #FECACA;
            --danger-text: #991B1B;
            
            --warning: #F59E0B;
            --warning-light: #FFFBEB;
            --warning-border: #FDE68A;
            --warning-text: #92400E;
            
            --info: #3B82F6;
            --info-light: #EFF6FF;
            --info-border: #BFDBFE;
            --info-text: #1E40AF;
            
            --slate-50: #F8FAFC;
            --slate-100: #F1F5F9;
            --slate-200: #E2E8F0;
            --slate-300: #CBD5E1;
            --slate-400: #94A3B8;
            --slate-500: #64748B;
            --slate-600: #475569;
            --slate-700: #334155;
            --slate-800: #1E293B;
            --slate-900: #0F172A;
            
            --sidebar-width: 260px;
            --header-height: 72px;
            --radius-sm: 8px;
            --radius-md: 12px;
            --radius-lg: 16px;
            --radius-xl: 20px;
            
            --shadow-subtle: 0 1px 3px rgba(15, 23, 42, 0.05), 0 1px 2px rgba(15, 23, 42, 0.03);
            --shadow-card: 0 4px 6px -1px rgba(15, 23, 42, 0.05), 0 2px 4px -2px rgba(15, 23, 42, 0.05);
            --shadow-elevated: 0 10px 15px -3px rgba(15, 23, 42, 0.08), 0 4px 6px -4px rgba(15, 23, 42, 0.05);
            --shadow-glow: 0 10px 25px -5px rgba(79, 89, 232, 0.2);
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Plus Jakarta Sans', 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background-color: var(--slate-50);
            color: var(--slate-900);
            min-height: 100vh;
            display: flex;
            overflow-x: hidden;
            -webkit-font-smoothing: antialiased;
        }

        /* ----------------------------------------------------
           1. SIDEBAR (THANH ĐIỀU HƯỚNG TRÁI)
        ---------------------------------------------------- */
        .sidebar {
            width: var(--sidebar-width);
            background-color: #FFFFFF;
            border-right: 1px solid var(--slate-200);
            display: flex;
            flex-direction: column;
            position: fixed;
            top: 0;
            bottom: 0;
            left: 0;
            z-index: 100;
            transition: all 0.3s ease;
        }

        .sidebar-brand {
            height: var(--header-height);
            display: flex;
            align-items: center;
            padding: 0 24px;
            gap: 12px;
            border-bottom: 1px solid var(--slate-100);
        }

        .brand-logo-icon {
            width: 40px;
            height: 40px;
            border-radius: var(--radius-md);
            background: linear-gradient(135deg, #10B981 0%, #059669 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: #FFFFFF;
            font-size: 20px;
            box-shadow: 0 4px 10px rgba(16, 185, 129, 0.3);
        }

        .brand-text h1 {
            font-size: 18px;
            font-weight: 800;
            color: var(--slate-900);
            letter-spacing: -0.3px;
            line-height: 1.2;
        }

        .brand-text span {
            font-size: 11px;
            color: var(--slate-400);
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .sidebar-nav {
            flex: 1;
            padding: 20px 16px;
            overflow-y: auto;
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .nav-section-title {
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.6px;
            color: var(--slate-400);
            padding: 8px 12px 4px 12px;
            margin-top: 10px;
        }

        .nav-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 11px 14px;
            border-radius: var(--radius-md);
            color: var(--slate-600);
            text-decoration: none;
            font-size: 14px;
            font-weight: 600;
            transition: all 0.2s ease;
            position: relative;
        }

        .nav-item i {
            font-size: 17px;
            width: 22px;
            text-align: center;
            color: var(--slate-400);
            transition: color 0.2s ease;
        }

        .nav-item:hover {
            background-color: var(--slate-100);
            color: var(--slate-900);
        }

        .nav-item:hover i {
            color: var(--primary);
        }

        /* Lịch giao dịch item ACTIVE */
        .nav-item.active {
            background: linear-gradient(135deg, #ECFDF5 0%, #E6FBF2 100%);
            color: #065F46;
            font-weight: 700;
            border: 1px solid var(--success-border);
            box-shadow: 0 2px 6px rgba(16, 185, 129, 0.12);
        }

        .nav-item.active i {
            color: var(--success);
        }

        .nav-item.active::after {
            content: '';
            position: absolute;
            right: 12px;
            width: 6px;
            height: 6px;
            border-radius: 50%;
            background-color: var(--success);
        }

        .sidebar-footer {
            padding: 16px;
            border-top: 1px solid var(--slate-100);
            background: #FFFFFF;
        }

        .vip-card {
            background: linear-gradient(135deg, #0F172A 0%, #1E293B 100%);
            border-radius: var(--radius-md);
            padding: 16px;
            color: #FFFFFF;
            position: relative;
            overflow: hidden;
        }

        .vip-card::before {
            content: '';
            position: absolute;
            top: -20px;
            right: -20px;
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: rgba(16, 185, 129, 0.2);
            filter: blur(15px);
        }

        .vip-card-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 8px;
        }

        .vip-badge {
            background: linear-gradient(135deg, #F59E0B 0%, #D97706 100%);
            font-size: 10px;
            font-weight: 800;
            padding: 2px 7px;
            border-radius: 6px;
            letter-spacing: 0.5px;
            text-transform: uppercase;
        }

        .vip-card h4 {
            font-size: 13px;
            font-weight: 700;
            margin-bottom: 4px;
        }

        .vip-card p {
            font-size: 11px;
            color: var(--slate-300);
            line-height: 1.4;
        }

        /* ----------------------------------------------------
           2. MAIN CONTAINER VÀ TOP HEADER
        ---------------------------------------------------- */
        .main-wrapper {
            margin-left: var(--sidebar-width);
            flex: 1;
            display: flex;
            flex-direction: column;
            min-width: 0;
        }

        .top-header {
            height: var(--header-height);
            background-color: #FFFFFF;
            border-bottom: 1px solid var(--slate-200);
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 32px;
            position: sticky;
            top: 0;
            z-index: 90;
        }

        .header-left {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .breadcrumb {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 13px;
            color: var(--slate-400);
        }

        .breadcrumb a {
            color: var(--slate-500);
            text-decoration: none;
            font-weight: 500;
        }

        .breadcrumb a:hover {
            color: var(--primary);
        }

        .breadcrumb span.current {
            color: var(--slate-800);
            font-weight: 600;
        }

        .header-search {
            position: relative;
            width: 280px;
        }

        .header-search input {
            width: 100%;
            height: 38px;
            padding: 0 12px 0 36px;
            background-color: var(--slate-100);
            border: 1px solid transparent;
            border-radius: var(--radius-sm);
            font-size: 13px;
            color: var(--slate-800);
            outline: none;
            transition: all 0.2s ease;
        }

        .header-search input:focus {
            background-color: #FFFFFF;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(79, 89, 232, 0.1);
        }

        .header-search i {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--slate-400);
            font-size: 13px;
        }

        .header-right {
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .header-date-badge {
            background-color: var(--slate-100);
            padding: 6px 12px;
            border-radius: var(--radius-sm);
            font-size: 12px;
            font-weight: 600;
            color: var(--slate-600);
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .btn-quick-add {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-hover) 100%);
            color: #FFFFFF;
            border: none;
            border-radius: var(--radius-sm);
            padding: 8px 16px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            box-shadow: 0 2px 6px rgba(79, 89, 232, 0.25);
            transition: all 0.2s ease;
        }

        .btn-quick-add:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 10px rgba(79, 89, 232, 0.35);
        }

        .header-icon-btn {
            width: 38px;
            height: 38px;
            border-radius: var(--radius-sm);
            border: 1px solid var(--slate-200);
            background-color: #FFFFFF;
            color: var(--slate-600);
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 15px;
            position: relative;
            transition: all 0.2s;
        }

        .header-icon-btn:hover {
            background-color: var(--slate-100);
            color: var(--slate-900);
        }

        .header-icon-btn .badge-dot {
            position: absolute;
            top: 8px;
            right: 8px;
            width: 7px;
            height: 7px;
            border-radius: 50%;
            background-color: var(--danger);
        }

        .user-profile {
            display: flex;
            align-items: center;
            gap: 10px;
            padding-left: 8px;
            cursor: pointer;
        }

        .user-avatar {
            width: 38px;
            height: 38px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid #FFFFFF;
            box-shadow: 0 0 0 1px var(--slate-200);
        }

        .user-info {
            line-height: 1.2;
        }

        .user-info .name {
            font-size: 13px;
            font-weight: 700;
            color: var(--slate-900);
        }

        .user-info .role {
            font-size: 11px;
            color: var(--success);
            font-weight: 600;
        }

        /* ----------------------------------------------------
           3. NỘI DUNG CHÍNH (CONTENT AREA)
        ---------------------------------------------------- */
        .content-container {
            padding: 28px 32px 48px 32px;
            max-width: 1480px;
            width: 100%;
            margin: 0 auto;
        }

        /* Page Header */
        .page-header {
            margin-bottom: 24px;
        }

        .header-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background-color: var(--success-light);
            color: var(--success-text);
            border: 1px solid var(--success-border);
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.6px;
            text-transform: uppercase;
            padding: 4px 10px;
            border-radius: 6px;
            margin-bottom: 8px;
        }

        .page-title {
            font-size: 26px;
            font-weight: 800;
            color: var(--slate-900);
            letter-spacing: -0.5px;
            margin-bottom: 6px;
        }

        .page-subtitle {
            font-size: 14px;
            color: var(--slate-500);
            font-weight: 400;
        }

        /* Month Navigation Bar */
        .month-nav-bar {
            background-color: #FFFFFF;
            border: 1px solid var(--slate-200);
            border-radius: var(--radius-md);
            padding: 12px 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 24px;
            box-shadow: var(--shadow-subtle);
        }

        .nav-controls {
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .nav-btn {
            background-color: var(--slate-100);
            border: 1px solid var(--slate-200);
            color: var(--slate-700);
            padding: 7px 14px;
            border-radius: var(--radius-sm);
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: all 0.2s ease;
        }

        .nav-btn:hover {
            background-color: var(--slate-200);
            color: var(--slate-900);
        }

        .nav-btn.today-btn {
            background-color: var(--primary-light);
            color: var(--primary);
            border-color: rgba(79, 89, 232, 0.2);
        }

        .nav-btn.today-btn:hover {
            background-color: #E0E7FF;
        }

        .current-month-display {
            font-size: 18px;
            font-weight: 800;
            color: var(--slate-900);
            letter-spacing: -0.3px;
            min-width: 170px;
            text-align: center;
        }

        .filter-controls {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .custom-select {
            appearance: none;
            background-color: var(--slate-50);
            border: 1px solid var(--slate-200);
            color: var(--slate-700);
            font-size: 13px;
            font-weight: 600;
            padding: 8px 32px 8px 14px;
            border-radius: var(--radius-sm);
            outline: none;
            cursor: pointer;
            background-image: url("data:image/svg+xml;charset=UTF-8,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%2364748b' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3e%3cpolyline points='6 9 12 15 18 9'%3e%3c/polyline%3e%3c/svg%3e");
            background-repeat: no-repeat;
            background-position: right 10px center;
            background-size: 14px;
            transition: all 0.2s;
        }

        .custom-select:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(79, 89, 232, 0.1);
        }

        /* ----------------------------------------------------
           4. 3 THẺ TÓM TẮT (3 SUMMARY CARDS)
        ---------------------------------------------------- */
        .summary-cards-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
            margin-bottom: 24px;
        }

        .summary-card {
            background-color: #FFFFFF;
            border: 1px solid var(--slate-200);
            border-radius: var(--radius-lg);
            padding: 20px 24px;
            box-shadow: var(--shadow-subtle);
            position: relative;
            overflow: hidden;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .summary-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-card);
        }

        .card-top {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 12px;
        }

        .card-title {
            font-size: 13px;
            font-weight: 600;
            color: var(--slate-500);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .card-icon {
            width: 40px;
            height: 40px;
            border-radius: var(--radius-md);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 17px;
        }

        .icon-blue { background-color: var(--info-light); color: var(--info); }
        .icon-red { background-color: var(--danger-light); color: var(--danger); }
        .icon-purple { background-color: #F3E8FF; color: #9333EA; }

        .card-main-val {
            font-size: 26px;
            font-weight: 800;
            color: var(--slate-900);
            letter-spacing: -0.5px;
            margin-bottom: 10px;
            line-height: 1.2;
        }

        .card-main-val.val-red {
            color: var(--danger);
        }

        .card-footer-info {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 13px;
            color: var(--slate-600);
        }

        .card-progress-bar {
            width: 100%;
            height: 6px;
            background-color: var(--slate-100);
            border-radius: 999px;
            margin-bottom: 8px;
            overflow: hidden;
        }

        .card-progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #10B981, #059669);
            border-radius: 999px;
            transition: width 0.4s ease;
        }

        .tag-pill {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            font-size: 12px;
            font-weight: 700;
            padding: 3px 8px;
            border-radius: 6px;
        }

        .tag-success {
            background-color: var(--success-light);
            color: var(--success-text);
        }

        /* ----------------------------------------------------
           5. BỐ CỤC 2 CỘT: LỊCH (~60%) & CHI TIẾT (~40%)
        ---------------------------------------------------- */
        .calendar-layout-grid {
            display: grid;
            grid-template-columns: 1.45fr 1fr;
            gap: 24px;
            align-items: start;
        }

        /* Khung Lịch Tài Chính (Left ~60%) */
        .calendar-main-card {
            background-color: #FFFFFF;
            border: 1px solid var(--slate-200);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-subtle);
            overflow: hidden;
        }

        .card-header-styled {
            padding: 20px 24px;
            border-bottom: 1px solid var(--slate-200);
            display: flex;
            align-items: center;
            justify-content: space-between;
            background-color: #FFFFFF;
        }

        .header-title-group h2 {
            font-size: 17px;
            font-weight: 800;
            color: var(--slate-900);
            letter-spacing: -0.3px;
            margin-bottom: 3px;
        }

        .header-title-group p {
            font-size: 13px;
            color: var(--slate-500);
        }

        .badge-selected-pill {
            background-color: var(--primary-light);
            color: var(--primary);
            border: 1px solid rgba(79, 89, 232, 0.2);
            font-size: 12px;
            font-weight: 700;
            padding: 5px 12px;
            border-radius: 20px;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        /* Grid header các ngày trong tuần (Thứ 2 -> Chủ Nhật) */
        .weekday-headers {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            background-color: var(--slate-50);
            border-bottom: 1px solid var(--slate-200);
        }

        .weekday-cell {
            padding: 12px 4px;
            text-align: center;
            font-size: 12px;
            font-weight: 700;
            color: var(--slate-500);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .weekday-cell.weekend {
            color: var(--danger);
        }

        /* Lưới các ô ngày */
        .calendar-days-grid {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 1px;
            background-color: var(--slate-200);
        }

        .day-cell {
            background-color: #FFFFFF;
            min-height: 96px;
            padding: 8px 8px 6px 8px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            cursor: pointer;
            position: relative;
            transition: all 0.15s ease;
            user-select: none;
        }

        .day-cell:hover:not(.empty-day) {
            background-color: #F8FAFC;
            z-index: 2;
            box-shadow: inset 0 0 0 2px var(--slate-300);
        }

        .day-cell.empty-day {
            background-color: var(--slate-50);
            cursor: default;
            opacity: 0.45;
        }

        .day-cell-top {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
        }

        .day-number {
            font-size: 14px;
            font-weight: 700;
            color: var(--slate-800);
            width: 26px;
            height: 26px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 6px;
        }

        .day-dots {
            display: flex;
            align-items: center;
            gap: 3px;
            padding-top: 4px;
        }

        .dot-indicator {
            width: 6px;
            height: 6px;
            border-radius: 50%;
        }

        .dot-green { background-color: var(--success); }
        .dot-red { background-color: var(--danger); }
        .dot-yellow { background-color: var(--warning); }

        .day-cell-amount {
            font-size: 11px;
            font-weight: 700;
            border-radius: 4px;
            padding: 2px 4px;
            text-align: right;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            margin-top: 4px;
        }

        .amount-positive {
            color: var(--success-text);
            background-color: var(--success-light);
        }

        .amount-negative {
            color: var(--danger-text);
            background-color: var(--danger-light);
        }

        /* Ô ngày Hôm Nay */
        .day-cell.is-today {
            background-color: #F0FDF4;
            box-shadow: inset 0 0 0 2px var(--success);
        }

        .day-cell.is-today .day-number {
            background-color: var(--success);
            color: #FFFFFF;
        }

        /* Ô ngày Đang Chọn */
        .day-cell.is-selected {
            background-color: #F5F7FF;
            box-shadow: inset 0 0 0 2px var(--primary);
            z-index: 3;
        }

        .day-cell.is-selected .day-number {
            background-color: var(--primary);
            color: #FFFFFF;
        }

        /* Chú thích màu sắc (Legend) */
        .calendar-legend {
            padding: 14px 24px;
            border-top: 1px solid var(--slate-200);
            display: flex;
            align-items: center;
            justify-content: flex-start;
            gap: 24px;
            background-color: #FFFFFF;
            font-size: 12px;
            color: var(--slate-600);
            font-weight: 600;
        }

        .legend-item {
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .legend-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
        }

        /* Weekly Summary Bar bên dưới lịch */
        .weekly-summary-card {
            margin-top: 18px;
            background: linear-gradient(135deg, #F0FDF4 0%, #FFFFFF 100%);
            border: 1px solid #BBF7D0;
            border-radius: var(--radius-lg);
            padding: 18px 22px;
            box-shadow: var(--shadow-subtle);
        }

        .weekly-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 8px;
        }

        .weekly-title {
            font-size: 14px;
            font-weight: 800;
            color: var(--slate-900);
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .badge-status-stable {
            background-color: #DCFCE7;
            color: #15803D;
            border: 1px solid #86EFAC;
            font-size: 11px;
            font-weight: 700;
            padding: 3px 8px;
            border-radius: 20px;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }

        .weekly-desc {
            font-size: 13px;
            color: var(--slate-600);
            margin-bottom: 12px;
            font-weight: 500;
        }

        .weekly-progress-wrap {
            position: relative;
        }

        .weekly-progress-bar {
            width: 100%;
            height: 8px;
            background-color: #E2E8F0;
            border-radius: 999px;
            overflow: hidden;
        }

        .weekly-progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #10B981, #059669);
            border-radius: 999px;
            width: 45%;
        }

        /* ----------------------------------------------------
           6. BẢNG CHI TIẾT GIAO DỊCH (RIGHT ~40%)
        ---------------------------------------------------- */
        .tx-details-panel {
            background-color: #FFFFFF;
            border: 1px solid var(--slate-200);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-subtle);
            padding: 24px;
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .panel-header-top {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 4px;
        }

        .badge-current-day {
            background-color: var(--info-light);
            color: var(--info-text);
            border: 1px solid var(--info-border);
            font-size: 11px;
            font-weight: 700;
            padding: 3px 8px;
            border-radius: 6px;
            text-transform: uppercase;
        }

        .panel-date-display {
            font-size: 13px;
            font-weight: 700;
            color: var(--slate-600);
        }

        .panel-main-title {
            font-size: 20px;
            font-weight: 800;
            color: var(--slate-900);
            letter-spacing: -0.4px;
        }

        /* 3 Hộp tóm tắt nhỏ ngày đang chọn */
        .day-stat-boxes {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 12px;
        }

        .mini-stat-box {
            padding: 12px 14px;
            border-radius: var(--radius-md);
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .box-label {
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .box-val {
            font-size: 15px;
            font-weight: 800;
            letter-spacing: -0.3px;
            white-space: nowrap;
        }

        .box-income {
            background-color: var(--info-light);
            border: 1px solid var(--info-border);
        }
        .box-income .box-label { color: var(--info-text); }
        .box-income .box-val { color: var(--info); }

        .box-expense {
            background-color: var(--danger-light);
            border: 1px solid var(--danger-border);
        }
        .box-expense .box-label { color: var(--danger-text); }
        .box-expense .box-val { color: var(--danger); }

        .box-diff {
            background-color: var(--success-light);
            border: 1px solid var(--success-border);
        }
        .box-diff .box-label { color: var(--success-text); }
        .box-diff .box-val { color: var(--success); }

        /* Tiêu đề danh sách giao dịch & bộ sắp xếp */
        .tx-list-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding-bottom: 10px;
            border-bottom: 1px solid var(--slate-100);
        }

        .tx-list-title {
            font-size: 12px;
            font-weight: 800;
            color: var(--slate-500);
            letter-spacing: 0.6px;
            text-transform: uppercase;
        }

        .sort-select {
            font-size: 12px;
            font-weight: 600;
            color: var(--slate-600);
            background-color: transparent;
            border: none;
            outline: none;
            cursor: pointer;
        }

        /* Danh sách các item giao dịch */
        .tx-items-list {
            display: flex;
            flex-direction: column;
            gap: 12px;
            max-height: 420px;
            overflow-y: auto;
            padding-right: 4px;
        }

        /* Custom scrollbar */
        .tx-items-list::-webkit-scrollbar {
            width: 5px;
        }
        .tx-items-list::-webkit-scrollbar-track {
            background: var(--slate-100);
            border-radius: 4px;
        }
        .tx-items-list::-webkit-scrollbar-thumb {
            background: var(--slate-300);
            border-radius: 4px;
        }

        .tx-item-card {
            background-color: #FFFFFF;
            border: 1px solid var(--slate-200);
            border-radius: var(--radius-md);
            padding: 14px;
            display: flex;
            align-items: flex-start;
            gap: 12px;
            transition: all 0.2s ease;
            position: relative;
        }

        .tx-item-card:hover {
            border-color: var(--slate-300);
            box-shadow: 0 2px 8px rgba(15, 23, 42, 0.05);
            transform: translateY(-1px);
        }

        .tx-item-icon {
            width: 40px;
            height: 40px;
            border-radius: var(--radius-md);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
            flex-shrink: 0;
            color: #FFFFFF;
        }

        .tx-item-body {
            flex: 1;
            min-width: 0;
        }

        .tx-body-top {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 4px;
        }

        .tx-item-title {
            font-size: 14px;
            font-weight: 700;
            color: var(--slate-900);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            padding-right: 8px;
        }

        .tx-item-amount {
            font-size: 14px;
            font-weight: 800;
            white-space: nowrap;
            letter-spacing: -0.2px;
        }

        .tx-amount-inc { color: var(--success); }
        .tx-amount-exp { color: var(--danger); }

        .tx-meta-row {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 12px;
            color: var(--slate-500);
            margin-bottom: 6px;
        }

        .category-badge-small {
            font-size: 11px;
            font-weight: 700;
            padding: 2px 6px;
            border-radius: 4px;
            background-color: var(--slate-100);
            color: var(--slate-700);
        }

        .tx-desc-quote {
            font-size: 12px;
            color: var(--slate-600);
            font-style: italic;
            line-height: 1.4;
            background-color: var(--slate-50);
            padding: 5px 8px;
            border-left: 2px solid var(--slate-300);
            border-radius: 0 4px 4px 0;
            margin-top: 4px;
        }

        .tx-item-actions {
            display: flex;
            align-items: center;
            gap: 6px;
            opacity: 0.4;
            transition: opacity 0.2s;
            margin-left: 4px;
        }

        .tx-item-card:hover .tx-item-actions {
            opacity: 1;
        }

        .btn-action-icon {
            width: 28px;
            height: 28px;
            border-radius: 6px;
            border: 1px solid var(--slate-200);
            background-color: #FFFFFF;
            color: var(--slate-500);
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 11px;
            transition: all 0.15s;
        }

        .btn-action-icon:hover {
            background-color: var(--slate-100);
            color: var(--slate-800);
        }

        .btn-action-icon.btn-delete:hover {
            background-color: var(--danger-light);
            color: var(--danger);
            border-color: var(--danger-border);
        }

        /* Nút lớn thêm giao dịch */
        .btn-add-for-day {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-hover) 100%);
            color: #FFFFFF;
            border: none;
            border-radius: var(--radius-md);
            padding: 12px 18px;
            font-size: 14px;
            font-weight: 700;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            box-shadow: 0 4px 12px rgba(79, 89, 232, 0.25);
            transition: all 0.2s ease;
            width: 100%;
        }

        .btn-add-for-day:hover {
            transform: translateY(-1px);
            box-shadow: 0 6px 16px rgba(79, 89, 232, 0.35);
        }

        /* Thẻ nhắc hẹn thanh toán sắp tới */
        .reminder-card-bottom {
            background: linear-gradient(135deg, #FFFBEB 0%, #FEF3C7 100%);
            border: 1px solid var(--warning-border);
            border-radius: var(--radius-md);
            padding: 16px;
        }

        .reminder-card-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 10px;
        }

        .reminder-title {
            font-size: 13px;
            font-weight: 800;
            color: var(--warning-text);
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .reminder-badge-count {
            background-color: var(--warning);
            color: #FFFFFF;
            font-size: 11px;
            font-weight: 800;
            padding: 2px 7px;
            border-radius: 999px;
        }

        .reminder-items-list {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .reminder-single-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            font-size: 12px;
            color: var(--slate-700);
            background: rgba(255, 255, 255, 0.7);
            padding: 8px 10px;
            border-radius: 6px;
        }

        .reminder-single-item .item-due {
            font-size: 11px;
            font-weight: 700;
            color: var(--danger);
        }

        /* Empty state khi ngày không có giao dịch */
        .empty-day-state {
            padding: 40px 20px;
            text-align: center;
            color: var(--slate-400);
        }

        .empty-day-state i {
            font-size: 42px;
            margin-bottom: 12px;
            color: var(--slate-300);
        }

        .empty-day-state h4 {
            font-size: 14px;
            font-weight: 700;
            color: var(--slate-700);
            margin-bottom: 4px;
        }

        .empty-day-state p {
            font-size: 12px;
            color: var(--slate-500);
        }

        /* ----------------------------------------------------
           7. MODAL THÊM GIAO DỊCH
        ---------------------------------------------------- */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: rgba(15, 23, 42, 0.5);
            backdrop-filter: blur(4px);
            z-index: 1000;
            display: none;
            align-items: center;
            justify-content: center;
            padding: 20px;
            opacity: 0;
            transition: opacity 0.2s ease;
        }

        .modal-overlay.active {
            display: flex;
            opacity: 1;
        }

        .modal-card {
            background-color: #FFFFFF;
            border-radius: var(--radius-xl);
            max-width: 520px;
            width: 100%;
            box-shadow: 0 20px 25px -5px rgba(15, 23, 42, 0.2), 0 8px 10px -6px rgba(15, 23, 42, 0.1);
            overflow: hidden;
            transform: scale(0.95);
            transition: transform 0.2s ease;
        }

        .modal-overlay.active .modal-card {
            transform: scale(1);
        }

        .modal-header {
            padding: 20px 24px;
            border-bottom: 1px solid var(--slate-200);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .modal-header h3 {
            font-size: 18px;
            font-weight: 800;
            color: var(--slate-900);
        }

        .btn-close-modal {
            background: none;
            border: none;
            font-size: 18px;
            color: var(--slate-400);
            cursor: pointer;
            padding: 4px;
        }

        .btn-close-modal:hover {
            color: var(--slate-700);
        }

        .modal-body {
            padding: 24px;
            max-height: 75vh;
            overflow-y: auto;
        }

        .form-group {
            margin-bottom: 16px;
        }

        .form-label {
            display: block;
            font-size: 13px;
            font-weight: 700;
            color: var(--slate-700);
            margin-bottom: 6px;
        }

        .form-control {
            width: 100%;
            padding: 10px 14px;
            border: 1px solid var(--slate-200);
            border-radius: var(--radius-sm);
            font-size: 14px;
            color: var(--slate-800);
            outline: none;
            transition: all 0.2s;
        }

        .form-control:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(79, 89, 232, 0.1);
        }

        .type-toggle-group {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
            background-color: var(--slate-100);
            padding: 4px;
            border-radius: var(--radius-sm);
        }

        .type-btn {
            border: none;
            padding: 9px;
            border-radius: 6px;
            font-size: 13px;
            font-weight: 700;
            cursor: pointer;
            background: transparent;
            color: var(--slate-600);
            transition: all 0.2s;
        }

        .type-btn.active.btn-exp {
            background-color: #FFFFFF;
            color: var(--danger);
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }

        .type-btn.active.btn-inc {
            background-color: #FFFFFF;
            color: var(--success);
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }

        .form-row-2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
        }

        .modal-footer {
            padding: 16px 24px;
            border-top: 1px solid var(--slate-200);
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 12px;
            background-color: var(--slate-50);
        }

        .btn-secondary {
            background-color: #FFFFFF;
            border: 1px solid var(--slate-200);
            color: var(--slate-700);
            padding: 9px 18px;
            border-radius: var(--radius-sm);
            font-size: 13px;
            font-weight: 700;
            cursor: pointer;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-hover) 100%);
            color: #FFFFFF;
            border: none;
            padding: 9px 20px;
            border-radius: var(--radius-sm);
            font-size: 13px;
            font-weight: 700;
            cursor: pointer;
        }

        /* Toast thông báo */
        .toast-notification {
            position: fixed;
            bottom: 24px;
            right: 24px;
            background-color: var(--slate-900);
            color: #FFFFFF;
            padding: 14px 20px;
            border-radius: var(--radius-md);
            box-shadow: 0 10px 15px -3px rgba(0,0,0,0.3);
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 13px;
            font-weight: 600;
            z-index: 2000;
            transform: translateY(100px);
            opacity: 0;
            transition: all 0.3s ease;
        }

        .toast-notification.show {
            transform: translateY(0);
            opacity: 1;
        }

        /* Responsive */
        @media (max-width: 1200px) {
            .calendar-layout-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 900px) {
            .summary-cards-grid {
                grid-template-columns: 1fr;
            }
            .sidebar {
                transform: translateX(-100%);
            }
            .main-wrapper {
                margin-left: 0;
            }
        }
    </style>
</head>
<body>

    <!-- 1. THANH ĐIỀU HƯỚNG TRÁI (LEFT SIDEBAR) -->
    <aside class="sidebar">
        <div class="sidebar-brand">
            <div class="brand-logo-icon">
                <i class="fa-solid fa-wallet"></i>
            </div>
            <div class="brand-text">
                <h1>FinTrack VN</h1>
                <span>Sổ Quản Lý Tài Chính</span>
            </div>
        </div>

        <nav class="sidebar-nav">
            <div class="nav-section-title">QUẢN LÝ DÒNG TIỀN</div>
            
            <a href="index.php" class="nav-item">
                <i class="fa-solid fa-house"></i>
                <span>Trang chủ</span>
            </a>

            <!-- Menu 'Lịch giao dịch' ACTIVE theo yêu cầu -->
            <a href="calendar.php" class="nav-item active">
                <i class="fa-solid fa-calendar-days"></i>
                <span>Lịch giao dịch</span>
            </a>

            <a href="add_transaction.php" class="nav-item">
                <i class="fa-solid fa-circle-plus"></i>
                <span>Thêm giao dịch</span>
            </a>

            <a href="reports.php" class="nav-item">
                <i class="fa-solid fa-chart-column"></i>
                <span>Báo cáo & Phân tích</span>
            </a>

            <div class="nav-section-title">HỆ THỐNG</div>

            <a href="settings.php" class="nav-item">
                <i class="fa-solid fa-gear"></i>
                <span>Cài đặt</span>
            </a>
        </nav>

        <div class="sidebar-footer">
            <div class="vip-card">
                <div class="vip-card-header">
                    <h4>FinTrack VIP</h4>
                    <span class="vip-badge">PREMIUM</span>
                </div>
                <p>Quản lý tài chính cá nhân thông minh không giới hạn giao dịch.</p>
            </div>
        </div>
    </aside>

    <!-- 2. PHẦN CHÍNH (MAIN WRAPPER) -->
    <div class="main-wrapper">
        
        <!-- Header trên cùng -->
        <header class="top-header">
            <div class="header-left">
                <div class="breadcrumb">
                    <a href="index.php">FinTrack VN</a>
                    <i class="fa-solid fa-chevron-right" style="font-size: 10px;"></i>
                    <span class="current">Lịch giao dịch</span>
                </div>

                <div class="header-search">
                    <i class="fa-solid fa-magnifying-glass"></i>
                    <input type="text" placeholder="Tìm kiếm giao dịch, hóa đơn...">
                </div>
            </div>

            <div class="header-right">
                <div class="header-date-badge">
                    <i class="fa-regular fa-calendar"></i>
                    <span id="headerLiveDate">Thứ Năm, 24/10/2024</span>
                </div>

                <button class="btn-quick-add" onclick="openAddTransactionModal()">
                    <i class="fa-solid fa-plus"></i>
                    <span>Thêm giao dịch</span>
                </button>

                <button class="header-icon-btn" title="Thông báo">
                    <i class="fa-regular fa-bell"></i>
                    <span class="badge-dot"></span>
                </button>

                <div class="user-profile">
                    <img src="<?= htmlspecialchars($currentUser['avatar']) ?>" alt="Avatar" class="user-avatar">
                    <div class="user-info">
                        <div class="name"><?= htmlspecialchars($currentUser['name']) ?></div>
                        <div class="role"><i class="fa-solid fa-circle-check"></i> <?= htmlspecialchars($currentUser['role']) ?></div>
                    </div>
                </div>
            </div>
        </header>

        <!-- 3. NỘI DUNG TRANG (PAGE CONTENT) -->
        <main class="content-container">
            
            <!-- Page Header -->
            <div class="page-header">
                <div class="header-badge">
                    <i class="fa-solid fa-shield-halved"></i>
                    <span>SỔ QUẢN LÝ TÀI CHÍNH</span>
                </div>
                <h1 class="page-title">Lịch Giao Dịch & Chi Tiêu Hàng Ngày</h1>
                <p class="page-subtitle" id="pageSubtitle">Theo dõi nhịp độ chi tiêu và dòng tiền theo từng ngày trong tháng <?= $selectedMonth ?>/<?= $selectedYear ?></p>
            </div>

            <!-- Month Navigation & Filters -->
            <div class="month-nav-bar">
                <div class="nav-controls">
                    <a href="calendar.php?month=<?= $prevMonth ?>&year=<?= $prevYear ?>" class="nav-btn">
                        <i class="fa-solid fa-chevron-left"></i>
                        <span>Tháng <?= $prevMonth ?></span>
                    </a>

                    <div class="current-month-display" id="currentMonthLabel">
                        Tháng <?= $selectedMonth ?>, <?= $selectedYear ?>
                    </div>

                    <a href="calendar.php?month=<?= $nextMonth ?>&year=<?= $nextYear ?>" class="nav-btn">
                        <span>Tháng <?= $nextMonth ?></span>
                        <i class="fa-solid fa-chevron-right"></i>
                    </a>

                    <button class="nav-btn today-btn" onclick="goToToday()">
                        <i class="fa-solid fa-crosshairs"></i>
                        <span>Hôm nay</span>
                    </button>
                </div>

                <div class="filter-controls">
                    <label for="filterSelect" style="font-size: 13px; font-weight: 600; color: var(--slate-500);">Lọc hiển thị:</label>
                    <select id="filterSelect" class="custom-select" onchange="onFilterChange(this.value)">
                        <option value="all">Tất cả loại giao dịch</option>
                        <option value="expense">Chỉ chi tiêu</option>
                        <option value="income">Chỉ thu nhập</option>
                        <option value="recurring">Giao dịch định kỳ</option>
                    </select>
                </div>
            </div>

            <!-- 3 Summary Cards -->
            <div class="summary-cards-grid">
                
                <!-- Card 1: Tổng ngày phát sinh -->
                <div class="summary-card">
                    <div class="card-top">
                        <span class="card-title">Tổng ngày phát sinh</span>
                        <div class="card-icon icon-blue">
                            <i class="fa-solid fa-calendar-check"></i>
                        </div>
                    </div>
                    <div class="card-main-val" id="sumActiveDays"><?= $isDemoAdmin ? '22 / 31 ngày' : '0 / 31 ngày' ?></div>
                    <div class="card-progress-bar">
                        <div class="card-progress-fill" id="sumActiveProgress" style="width: <?= $isDemoAdmin ? '71%' : '0%' ?>;"></div>
                    </div>
                    <div class="card-footer-info">
                        <span class="tag-pill tag-success"><i class="fa-solid fa-chart-line"></i> <?= $isDemoAdmin ? '71%' : '0%' ?></span>
                        <span id="sumActiveRateText">Tỷ lệ phát sinh dòng tiền <?= $isDemoAdmin ? '71%' : '0%' ?></span>
                    </div>
                </div>

                <!-- Card 2: Ngày chi tiêu cao nhất -->
                <div class="summary-card">
                    <div class="card-top">
                        <span class="card-title">Ngày chi tiêu cao nhất</span>
                        <div class="card-icon icon-red">
                            <i class="fa-solid fa-arrow-trend-down"></i>
                        </div>
                    </div>
                    <div class="card-main-val val-red" id="sumMaxExpenseAmount"><?= $isDemoAdmin ? '-3.850.000 đ' : '0 đ' ?></div>
                    <div class="card-footer-info" style="margin-top: 14px;">
                        <i class="fa-solid fa-circle-exclamation text-danger" style="font-size: 14px;"></i>
                        <span id="sumMaxExpenseDesc"><?= $isDemoAdmin ? '15/10/2024 • Phí bảo hiểm xe ô tô' : 'Chưa có chi tiêu' ?></span>
                    </div>
                </div>

                <!-- Card 3: Trung bình chi / ngày -->
                <div class="summary-card">
                    <div class="card-top">
                        <span class="card-title">Trung bình chi / ngày</span>
                        <div class="card-icon icon-purple">
                            <i class="fa-solid fa-calculator"></i>
                        </div>
                    </div>
                    <div class="card-main-val" id="sumAvgDaily"><?= $isDemoAdmin ? '588.000 đ / ngày' : '0 đ / ngày' ?></div>
                    <div class="card-footer-info" style="margin-top: 14px;">
                        <span class="tag-pill tag-success" id="sumAvgComparison">
                            <i class="fa-solid fa-arrow-down"></i> <?= $isDemoAdmin ? 'Thấp hơn 12% so với tháng 9' : 'Chưa có dữ liệu chi tiêu' ?>
                        </span>
                    </div>
                </div>

            </div>

            <!-- Bố cục 2 cột: Khung Lịch (~60%) & Chi tiết giao dịch (~40%) -->
            <div class="calendar-layout-grid">
                
                <!-- CỘT TRÁI: KHUNG LỊCH TÀI CHÍNH -->
                <div class="calendar-column">
                    <div class="calendar-main-card">
                        
                        <!-- Header Lịch -->
                        <div class="card-header-styled">
                            <div class="header-title-group">
                                <h2>Khung Lịch Tài Chính</h2>
                                <p id="calendarSubtitle">Tháng <?= $selectedMonth ?>, năm <?= $selectedYear ?> • Nhấp vào ngày để tra cứu giao dịch</p>
                            </div>
                            <div class="badge-selected-pill" id="selectedBadgeIndicator">
                                <i class="fa-solid fa-check"></i>
                                <span>Đang chọn: 24/10/2024</span>
                            </div>
                        </div>

                        <!-- Hàng tiêu đề các thứ -->
                        <div class="weekday-headers">
                            <div class="weekday-cell">Thứ 2</div>
                            <div class="weekday-cell">Thứ 3</div>
                            <div class="weekday-cell">Thứ 4</div>
                            <div class="weekday-cell">Thứ 5</div>
                            <div class="weekday-cell">Thứ 6</div>
                            <div class="weekday-cell">Thứ 7</div>
                            <div class="weekday-cell weekend">Chủ Nhật</div>
                        </div>

                        <!-- Lưới ngày sẽ được render động từ JS/PHP -->
                        <div class="calendar-days-grid" id="calendarDaysGrid">
                            <!-- Sẽ được fill bằng JavaScript qua API -->
                        </div>

                        <!-- Chú thích ký hiệu màu sắc -->
                        <div class="calendar-legend">
                            <div class="legend-item">
                                <span class="legend-dot dot-green"></span>
                                <span>Khoản thu nhập</span>
                            </div>
                            <div class="legend-item">
                                <span class="legend-dot dot-red"></span>
                                <span>Khoản chi tiêu</span>
                            </div>
                            <div class="legend-item">
                                <span class="legend-dot dot-yellow"></span>
                                <span>Hạn thanh toán / Định kỳ</span>
                            </div>
                        </div>

                    </div>

                    <!-- Weekly Summary Bar bên dưới lịch -->
                    <div class="weekly-summary-card">
                        <div class="weekly-header">
                            <div class="weekly-title">
                                <i class="fa-solid fa-chart-simple text-success"></i>
                                <span id="weeklyTitle">Tuần 4 (21/10 - 27/10)</span>
                            </div>
                            <span class="badge-status-stable" id="weeklyBadge">
                                <i class="fa-solid fa-circle-check"></i> Ổn định
                            </span>
                        </div>
                        <div class="weekly-desc" id="weeklyDesc">
                            Tổng chi tiêu 5 ngày đầu tuần: 1.312.000 đ • Đạt 45% định mức tuần
                        </div>
                        <div class="weekly-progress-wrap">
                            <div class="weekly-progress-bar">
                                <div class="weekly-progress-fill" id="weeklyProgressFill" style="width: 45%;"></div>
                            </div>
                        </div>
                    </div>

                </div>

                <!-- CỘT PHẢI: CHI TIẾT GIAO DỊCH (~40%) -->
                <div class="details-column">
                    <div class="tx-details-panel">
                        
                        <!-- Panel Header -->
                        <div>
                            <div class="panel-header-top">
                                <span class="badge-current-day">
                                    <i class="fa-regular fa-calendar-check"></i> Ngày đang chọn
                                </span>
                                <span class="panel-date-display" id="panelSelectedDayText">Thứ Năm, 24/10/2024</span>
                            </div>
                            <h3 class="panel-main-title">Chi Tiết Giao Dịch</h3>
                        </div>

                        <!-- 3 Hộp tóm tắt: Thu, Chi, Chênh lệch -->
                        <div class="day-stat-boxes">
                            <div class="mini-stat-box box-income">
                                <span class="box-label">Tổng thu</span>
                                <span class="box-val" id="dayBoxIncome">+5.000.000 đ</span>
                            </div>
                            <div class="mini-stat-box box-expense">
                                <span class="box-label">Tổng chi</span>
                                <span class="box-val" id="dayBoxExpense">-727.000 đ</span>
                            </div>
                            <div class="mini-stat-box box-diff">
                                <span class="box-label">Chênh lệch</span>
                                <span class="box-val" id="dayBoxDiff">+4.273.000 đ</span>
                            </div>
                        </div>

                        <!-- Tiêu đề danh sách giao dịch -->
                        <div class="tx-list-header">
                            <span class="tx-list-title" id="txCountHeading">5 GIAO DỊCH PHÁT SINH</span>
                            <select class="sort-select" id="sortSelect" onchange="sortCurrentTransactions(this.value)">
                                <option value="time">Sắp xếp theo giờ</option>
                                <option value="amount_desc">Số tiền giảm dần</option>
                                <option value="amount_asc">Số tiền tăng dần</option>
                            </select>
                        </div>

                        <!-- Danh sách giao dịch -->
                        <div class="tx-items-list" id="txItemsContainer">
                            <!-- Được render qua AJAX/JS -->
                        </div>

                        <!-- Nút lớn thêm giao dịch cho ngày đang chọn -->
                        <button class="btn-add-for-day" id="btnAddDayTx" onclick="openAddTransactionModal()">
                            <i class="fa-solid fa-plus"></i>
                            <span id="btnAddDayTxText">Thêm giao dịch cho ngày 24/10/2024</span>
                        </button>

                        <!-- Thẻ Nhắc hẹn thanh toán sắp tới -->
                        <div class="reminder-card-bottom">
                            <div class="reminder-card-header">
                                <div class="reminder-title">
                                    <i class="fa-regular fa-bell"></i>
                                    <span>Nhắc hẹn thanh toán sắp tới</span>
                                </div>
                                <span class="reminder-badge-count" id="reminderCountBadge">2</span>
                            </div>
                            <div class="reminder-items-list" id="reminderItemsList">
                                <div class="reminder-single-item">
                                    <span>25/10: Hóa đơn tiền điện sinh hoạt EVN (1.450.000 đ)</span>
                                    <span class="item-due">Còn 1 ngày</span>
                                </div>
                                <div class="reminder-single-item">
                                    <span>28/10: Sao kê thẻ tín dụng Techcombank (5.200.000 đ)</span>
                                    <span class="item-due">Còn 4 ngày</span>
                                </div>
                            </div>
                        </div>

                    </div>
                </div>

            </div>

        </main>
    </div>

    <!-- 4. MODAL THÊM GIAO DỊCH MỚI -->
    <div class="modal-overlay" id="addTxModal">
        <div class="modal-card">
            <div class="modal-header">
                <h3>Thêm Giao Dịch Mới</h3>
                <button class="btn-close-modal" onclick="closeAddTransactionModal()">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>
            <form id="addTxForm" onsubmit="handleCreateTransaction(event)">
                <div class="modal-body">
                    
                    <!-- Chọn loại giao dịch: Chi tiêu / Thu nhập -->
                    <div class="form-group">
                        <label class="form-label">Loại giao dịch</label>
                        <div class="type-toggle-group">
                            <button type="button" class="type-btn active btn-exp" id="modalBtnExpense" onclick="setModalType('chi_tieu')">
                                <i class="fa-solid fa-arrow-down"></i> Khoản chi tiêu
                            </button>
                            <button type="button" class="type-btn btn-inc" id="modalBtnIncome" onclick="setModalType('thu_nhap')">
                                <i class="fa-solid fa-arrow-up"></i> Khoản thu nhập
                            </button>
                        </div>
                        <input type="hidden" name="type" id="modalTxType" value="chi_tieu">
                    </div>

                    <!-- Số tiền -->
                    <div class="form-group">
                        <label class="form-label" for="modalAmount">Số tiền (VND) <span style="color: var(--danger);">*</span></label>
                        <input type="text" class="form-control" id="modalAmount" name="amount" placeholder="Ví dụ: 150.000" required oninput="formatCurrencyInput(this)">
                    </div>

                    <!-- Ngày và Giờ -->
                    <div class="form-row-2">
                        <div class="form-group">
                            <label class="form-label" for="modalDate">Ngày giao dịch</label>
                            <input type="date" class="form-control" id="modalDate" name="date" value="<?= $selectedDate ?>" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="modalTime">Giờ phát sinh</label>
                            <input type="time" class="form-control" id="modalTime" name="time" value="12:00">
                        </div>
                    </div>

                    <!-- Danh mục -->
                    <div class="form-group">
                        <label class="form-label" for="modalCategory">Danh mục</label>
                        <select class="form-control" id="modalCategory" name="category_id">
                            <?php foreach ($categoriesList as $cat): ?>
                                <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Phương thức thanh toán -->
                    <div class="form-group">
                        <label class="form-label" for="modalPaymentMethod">Phương thức thanh toán / Ví</label>
                        <select class="form-control" id="modalPaymentMethod" name="payment_method_id">
                            <?php foreach ($paymentMethodsList as $pm): ?>
                                <option value="<?= $pm['id'] ?>"><?= htmlspecialchars($pm['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Tiêu đề / Mô tả -->
                    <div class="form-group">
                        <label class="form-label" for="modalDesc">Tiêu đề giao dịch <span style="color: var(--danger);">*</span></label>
                        <input type="text" class="form-control" id="modalDesc" name="description" placeholder="Ví dụ: Cơm trưa văn phòng, Tiền điện thoại..." required>
                    </div>

                    <!-- Ghi chú chi tiết -->
                    <div class="form-group">
                        <label class="form-label" for="modalNote">Ghi chú chi tiết</label>
                        <textarea class="form-control" id="modalNote" name="note" rows="2" placeholder="Ghi chú thêm về địa điểm, người tham gia..."></textarea>
                    </div>

                    <!-- Lặp lại định kỳ -->
                    <div class="form-group" style="display: flex; align-items: center; gap: 8px;">
                        <input type="checkbox" id="modalRecurring" name="is_recurring" value="1" style="width: 16px; height: 16px; accent-color: var(--primary);">
                        <label for="modalRecurring" style="font-size: 13px; font-weight: 600; color: var(--slate-700); cursor: pointer;">
                            Đánh dấu là giao dịch lặp lại định kỳ (Hàng tháng)
                        </label>
                    </div>

                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-secondary" onclick="closeAddTransactionModal()">Hủy bỏ</button>
                    <button type="submit" class="btn-primary" id="btnSubmitTx">
                        <i class="fa-solid fa-check"></i> Lưu giao dịch
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Toast thông báo -->
    <div class="toast-notification" id="toastMessage">
        <i class="fa-solid fa-circle-check text-success" id="toastIcon"></i>
        <span id="toastText">Thao tác thành công!</span>
    </div>

    <!-- 5. JAVASCRIPT XỬ LÝ LỊCH VÀ AJAX DỮ LIỆU -->
    <script>
        // Trạng thái ứng dụng hiện tại
        const APP_STATE = {
            currentMonth: <?= $selectedMonth ?>,
            currentYear: <?= $selectedYear ?>,
            selectedDate: '<?= $selectedDate ?>',
            activeFilter: 'all',
            monthData: null,
            dayTransactions: []
        };

        // Khi trang tải xong, tự động gọi API lấy dữ liệu lịch và chi tiết ngày
        document.addEventListener('DOMContentLoaded', () => {
            loadCalendarMonthData(APP_STATE.currentMonth, APP_STATE.currentYear);
            loadDateDetails(APP_STATE.selectedDate);
        });

        /**
         * Định dạng tiền tệ VND
         */
        function formatVND(amount) {
            return new Intl.NumberFormat('vi-VN').format(amount) + ' đ';
        }

        /**
         * Hiển thị toast thông báo
         */
        function showToast(message, isSuccess = true) {
            const toast = document.getElementById('toastMessage');
            const toastText = document.getElementById('toastText');
            const toastIcon = document.getElementById('toastIcon');

            toastText.textContent = message;
            if (isSuccess) {
                toastIcon.className = 'fa-solid fa-circle-check text-success';
            } else {
                toastIcon.className = 'fa-solid fa-triangle-exclamation text-danger';
            }

            toast.classList.add('show');
            setTimeout(() => {
                toast.classList.remove('show');
            }, 3500);
        }

        /**
         * Gọi AJAX tải dữ liệu tháng cho Khung Lịch và 3 Thẻ Tóm Tắt
         */
        function loadCalendarMonthData(month, year) {
            const url = `api/calendar_data.php?month=${month}&year=${year}&filter=${APP_STATE.activeFilter}`;
            
            fetch(url)
                .then(response => response.json())
                .then(data => {
                    APP_STATE.monthData = data;
                    renderSummaryCards(data.summary);
                    renderWeeklySummary(data.weekly);
                    renderCalendarGrid(data);
                })
                .catch(err => {
                    console.error('Lỗi khi tải dữ liệu tháng:', err);
                });
        }

        /**
         * Cập nhật 3 Thẻ Tóm Tắt ở đầu trang
         */
        function renderSummaryCards(summary) {
            if (!summary) return;

            // Card 1: Tổng ngày phát sinh
            const totalDays = summary.total_days || 31;
            const activeDays = (summary.active_days !== undefined) ? summary.active_days : 0;
            const activeRate = (summary.active_rate !== undefined) ? summary.active_rate : 0;

            document.getElementById('sumActiveDays').textContent = `${activeDays} / ${totalDays} ngày`;
            document.getElementById('sumActiveProgress').style.width = `${activeRate}%`;
            document.getElementById('sumActiveRateText').textContent = `Tỷ lệ phát sinh dòng tiền ${activeRate}%`;

            // Card 2: Ngày chi tiêu cao nhất
            if (summary.max_expense_day) {
                document.getElementById('sumMaxExpenseAmount').textContent = summary.max_expense_day.amount_formatted;
                document.getElementById('sumMaxExpenseDesc').textContent = summary.max_expense_day.description;
            }

            // Card 3: Trung bình chi / ngày
            if (summary.avg_daily_expense) {
                document.getElementById('sumAvgDaily').textContent = summary.avg_daily_expense.amount_formatted;
                document.getElementById('sumAvgComparison').innerHTML = `<i class="fa-solid fa-arrow-down"></i> ${summary.avg_daily_expense.comparison}`;
            }
        }

        /**
         * Cập nhật thanh Weekly Summary
         */
        function renderWeeklySummary(weekly) {
            if (!weekly) return;
            document.getElementById('weeklyTitle').textContent = weekly.title || 'Tuần hiện tại';
            document.getElementById('weeklyDesc').textContent = weekly.description || 'Chưa có chi tiêu phát sinh trong tuần';
            document.getElementById('weeklyProgressFill').style.width = `${weekly.percentage !== undefined ? weekly.percentage : 0}%`;
            document.getElementById('weeklyBadge').innerHTML = `<i class="fa-solid fa-circle-check"></i> ${weekly.status || 'Ổn định'}`;
        }

        /**
         * Vẽ Khung Lịch Tài Chính (Calendar Grid)
         */
        function renderCalendarGrid(data) {
            const gridContainer = document.getElementById('calendarDaysGrid');
            gridContainer.innerHTML = '';

            const month = data.month;
            const year = data.year;
            const daysInMonth = data.summary.total_days || new Date(year, month, 0).getDate();

            // Xác định ngày bắt đầu của tháng (Thứ 2 = 1, Chủ Nhật = 7)
            const firstDayObj = new Date(year, month - 1, 1);
            let firstDayOfWeek = firstDayObj.getDay(); // 0 = Sunday, 1 = Monday...
            let leadingEmptyDays = firstDayOfWeek === 0 ? 6 : firstDayOfWeek - 1;

            // Lấy số ngày tháng trước để hiển thị mờ
            const prevMonthLastDate = new Date(year, month - 1, 0).getDate();
            
            // Vẽ các ngày cuối của tháng trước
            for (let i = leadingEmptyDays - 1; i >= 0; i--) {
                const dayNum = prevMonthLastDate - i;
                const cell = document.createElement('div');
                cell.className = 'day-cell empty-day';
                cell.innerHTML = `
                    <div class="day-cell-top">
                        <span class="day-number">${dayNum}</span>
                    </div>
                `;
                gridContainer.appendChild(cell);
            }

            // Ngày hiện tại thật của hệ thống
            const now = new Date();
            const todayStr = `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}-${String(now.getDate()).padStart(2, '0')}`;

            // Vẽ các ngày trong tháng hiện tại
            for (let d = 1; d <= daysInMonth; d++) {
                const dateKey = `${year}-${String(month).padStart(2, '0')}-${String(d).padStart(2, '0')}`;
                const dayInfo = data.days[dateKey] || {
                    income: 0,
                    expense: 0,
                    net: 0,
                    count: 0,
                    has_income: false,
                    has_expense: false,
                    has_recurring: false
                };

                const isToday = (dateKey === todayStr) || (dateKey === '2024-10-24' && APP_STATE.currentMonth === 10);
                const isSelected = (dateKey === APP_STATE.selectedDate);

                const cell = document.createElement('div');
                cell.className = `day-cell ${isToday ? 'is-today' : ''} ${isSelected ? 'is-selected' : ''}`;
                cell.dataset.date = dateKey;
                cell.onclick = () => selectDay(dateKey);

                // Lọc theo filter loại giao dịch
                let showIncome = dayInfo.has_income;
                let showExpense = dayInfo.has_expense;
                let showRecurring = dayInfo.has_recurring;

                if (APP_STATE.activeFilter === 'expense') {
                    showIncome = false;
                } else if (APP_STATE.activeFilter === 'income') {
                    showExpense = false;
                } else if (APP_STATE.activeFilter === 'recurring') {
                    showIncome = false;
                    showExpense = false;
                }

                // Chấm màu
                let dotsHtml = '';
                if (showIncome) dotsHtml += '<span class="dot-indicator dot-green" title="Thu nhập"></span>';
                if (showExpense) dotsHtml += '<span class="dot-indicator dot-red" title="Chi tiêu"></span>';
                if (showRecurring) dotsHtml += '<span class="dot-indicator dot-yellow" title="Định kỳ"></span>';

                // Số tiền ròng
                let amountHtml = '';
                if (dayInfo.count > 0 && (dayInfo.net !== 0 || dayInfo.income > 0 || dayInfo.expense > 0)) {
                    if (dayInfo.net > 0 && APP_STATE.activeFilter !== 'expense') {
                        amountHtml = `<div class="day-cell-amount amount-positive">+${formatVND(dayInfo.net)}</div>`;
                    } else if (dayInfo.net < 0 && APP_STATE.activeFilter !== 'income') {
                        amountHtml = `<div class="day-cell-amount amount-negative">-${formatVND(Math.abs(dayInfo.net))}</div>`;
                    }
                }

                cell.innerHTML = `
                    <div class="day-cell-top">
                        <span class="day-number">${d}</span>
                        <div class="day-dots">${dotsHtml}</div>
                    </div>
                    ${amountHtml}
                `;

                gridContainer.appendChild(cell);
            }

            // Vẽ các ngày đầu của tháng tiếp theo để lấp đầy ô lưới
            const totalCellsSoFar = leadingEmptyDays + daysInMonth;
            const remainingCells = (totalCellsSoFar % 7 === 0) ? 0 : 7 - (totalCellsSoFar % 7);
            for (let nextD = 1; nextD <= remainingCells; nextD++) {
                const cell = document.createElement('div');
                cell.className = 'day-cell empty-day';
                cell.innerHTML = `
                    <div class="day-cell-top">
                        <span class="day-number">${nextD}</span>
                    </div>
                `;
                gridContainer.appendChild(cell);
            }
        }

        /**
         * Xử lý khi người dùng nhấp chọn 1 ngày trên lịch
         */
        function selectDay(dateStr) {
            APP_STATE.selectedDate = dateStr;

            // Đổi class is-selected trên grid
            document.querySelectorAll('.day-cell').forEach(cell => {
                if (cell.dataset.date === dateStr) {
                    cell.classList.add('is-selected');
                } else {
                    cell.classList.remove('is-selected');
                }
            });

            // Cập nhật badge đang chọn
            const dateParts = dateStr.split('-');
            const displayDate = `${dateParts[2]}/${dateParts[1]}/${dateParts[0]}`;
            document.getElementById('selectedBadgeIndicator').innerHTML = `<i class="fa-solid fa-check"></i> <span>Đang chọn: ${displayDate}</span>`;

            // Tải chi tiết giao dịch của ngày qua AJAX
            loadDateDetails(dateStr);
        }

        /**
         * Gọi AJAX nạp dữ liệu chi tiết giao dịch cho 1 ngày
         */
        function loadDateDetails(dateStr) {
            const url = `api/calendar_data.php?date=${dateStr}`;

            fetch(url)
                .then(response => response.json())
                .then(data => {
                    renderTransactionDetailsPanel(data);
                })
                .catch(err => {
                    console.error('Lỗi khi tải chi tiết ngày:', err);
                });
        }

        /**
         * Hiển thị nội dung cột Chi Tiết Giao Dịch
         */
        function renderTransactionDetailsPanel(data) {
            // Header ngày
            document.getElementById('panelSelectedDayText').textContent = data.formatted_date;
            
            // Cập nhật text nút thêm giao dịch
            const dateParts = data.date.split('-');
            const dFmt = `${dateParts[2]}/${dateParts[1]}/${dateParts[0]}`;
            document.getElementById('btnAddDayTxText').textContent = `Thêm giao dịch cho ngày ${dFmt}`;

            // Modal form date update
            document.getElementById('modalDate').value = data.date;

            // 3 mini boxes
            document.getElementById('dayBoxIncome').textContent = data.summary.total_income_formatted;
            document.getElementById('dayBoxExpense').textContent = data.summary.total_expense_formatted;
            document.getElementById('dayBoxDiff').textContent = data.summary.net_diff_formatted;

            // Số lượng giao dịch heading
            const count = data.summary.tx_count;
            document.getElementById('txCountHeading').textContent = `${count} GIAO DỊCH PHÁT SINH`;

            // Danh sách giao dịch
            const container = document.getElementById('txItemsContainer');
            container.innerHTML = '';
            APP_STATE.dayTransactions = data.transactions || [];

            if (APP_STATE.dayTransactions.length === 0) {
                container.innerHTML = `
                    <div class="empty-day-state">
                        <i class="fa-regular fa-calendar-xmark"></i>
                        <h4>Không có giao dịch phát sinh</h4>
                        <p>Nhấp vào nút bên dưới để ghi chép giao dịch mới cho ngày này.</p>
                    </div>
                `;
                return;
            }

            renderTransactionItems(APP_STATE.dayTransactions);

            // Upcoming reminders
            if (data.upcoming_reminders && data.upcoming_reminders.length > 0) {
                const remList = document.getElementById('reminderItemsList');
                remList.innerHTML = '';
                document.getElementById('reminderCountBadge').textContent = data.upcoming_reminders.length;
                data.upcoming_reminders.forEach(r => {
                    const rDiv = document.createElement('div');
                    rDiv.className = 'reminder-single-item';
                    rDiv.innerHTML = `
                        <span>${r.due_date}: ${r.title} (${r.amount_formatted})</span>
                        <span class="item-due">${r.status}</span>
                    `;
                    remList.appendChild(rDiv);
                });
            }
        }

        /**
         * Render các phần tử thẻ giao dịch chi tiết
         */
        function renderTransactionItems(items) {
            const container = document.getElementById('txItemsContainer');
            container.innerHTML = '';

            items.forEach(tx => {
                const isExpense = tx.type === 'expense' || tx.type === 'chi_tieu';
                const sign = isExpense ? '-' : '+';
                const amtClass = isExpense ? 'tx-amount-exp' : 'tx-amount-inc';
                const iconColor = tx.category_color || (isExpense ? '#F97316' : '#10B981');
                const catIcon = tx.category_icon || (isExpense ? 'fa-tags' : 'fa-money-bill-wave');

                const card = document.createElement('div');
                card.className = 'tx-item-card';
                card.innerHTML = `
                    <div class="tx-item-icon" style="background-color: ${iconColor};">
                        <i class="fa-solid ${catIcon}"></i>
                    </div>
                    <div class="tx-item-body">
                        <div class="tx-body-top">
                            <div class="tx-item-title" title="${tx.title}">${tx.title}</div>
                            <div class="tx-item-amount ${amtClass}">${tx.amount_formatted}</div>
                        </div>
                        <div class="tx-meta-row">
                            <span class="category-badge-small">${tx.category_name}</span>
                            <span>•</span>
                            <span>${tx.time}</span>
                            <span>•</span>
                            <span>${tx.payment_method}</span>
                        </div>
                        ${tx.note ? `<div class="tx-desc-quote">“${tx.note}”</div>` : ''}
                    </div>
                    <div class="tx-item-actions">
                        <button class="btn-action-icon" title="Chỉnh sửa" onclick="handleEditTransaction(${tx.id})">
                            <i class="fa-solid fa-pen-to-square"></i>
                        </button>
                        <button class="btn-action-icon btn-delete" title="Xóa giao dịch" onclick="handleDeleteTransaction(${tx.id})">
                            <i class="fa-solid fa-trash-can"></i>
                        </button>
                    </div>
                `;
                container.appendChild(card);
            });
        }

        /**
         * Sắp xếp giao dịch trong panel
         */
        function sortCurrentTransactions(criteria) {
            const items = [...APP_STATE.dayTransactions];
            if (criteria === 'time') {
                items.sort((a, b) => a.time.localeCompare(b.time));
            } else if (criteria === 'amount_desc') {
                items.sort((a, b) => b.amount - a.amount);
            } else if (criteria === 'amount_asc') {
                items.sort((a, b) => a.amount - b.amount);
            }
            renderTransactionItems(items);
        }

        /**
         * Bộ lọc loại giao dịch (Dropdown trên thanh điều hướng tháng)
         */
        function onFilterChange(val) {
            APP_STATE.activeFilter = val;
            loadCalendarMonthData(APP_STATE.currentMonth, APP_STATE.currentYear);
        }

        /**
         * Nút "Hôm nay": Chuyển về ngày hiện tại
         */
        function goToToday() {
            const today = new Date();
            const year = today.getFullYear();
            const month = today.getMonth() + 1;
            const dateStr = `${year}-${String(month).padStart(2, '0')}-${String(today.getDate()).padStart(2, '0')}`;

            if (year !== APP_STATE.currentYear || month !== APP_STATE.currentMonth) {
                window.location.href = `calendar.php?month=${month}&year=${year}&date=${dateStr}`;
            } else {
                selectDay(dateStr);
            }
        }

        /**
         * Xử lý Modal Thêm Giao Dịch
         */
        function openAddTransactionModal() {
            document.getElementById('modalDate').value = APP_STATE.selectedDate;
            document.getElementById('addTxModal').classList.add('active');
        }

        function closeAddTransactionModal() {
            document.getElementById('addTxModal').classList.remove('active');
            document.getElementById('addTxForm').reset();
            setModalType('chi_tieu');
        }

        function setModalType(type) {
            document.getElementById('modalTxType').value = type;
            const btnExp = document.getElementById('modalBtnExpense');
            const btnInc = document.getElementById('modalBtnIncome');

            if (type === 'chi_tieu') {
                btnExp.classList.add('active');
                btnInc.classList.remove('active');
            } else {
                btnInc.classList.add('active');
                btnExp.classList.remove('active');
            }
        }

        function formatCurrencyInput(input) {
            let val = input.value.replace(/\D/g, '');
            if (val) {
                input.value = new Intl.NumberFormat('vi-VN').format(val);
            } else {
                input.value = '';
            }
        }

        /**
         * Gửi AJAX thêm giao dịch mới
         */
        function handleCreateTransaction(e) {
            e.preventDefault();
            const form = document.getElementById('addTxForm');
            const formData = new FormData(form);

            fetch('api/calendar_data.php?action=add_transaction', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(res => {
                if (res.status === 'success') {
                    showToast('Đã thêm giao dịch thành công!');
                    closeAddTransactionModal();
                    // Làm mới lại dữ liệu tháng và ngày
                    loadCalendarMonthData(APP_STATE.currentMonth, APP_STATE.currentYear);
                    loadDateDetails(APP_STATE.selectedDate);
                } else {
                    showToast(res.message || 'Lỗi khi lưu giao dịch', false);
                }
            })
            .catch(err => {
                console.error(err);
                showToast('Lỗi kết nối máy chủ', false);
            });
        }

        /**
         * Xóa giao dịch qua AJAX
         */
        function handleDeleteTransaction(id) {
            if (!confirm('Bạn có chắc chắn muốn xóa giao dịch này không?')) return;

            const formData = new FormData();
            formData.append('id', id);

            fetch('api/calendar_data.php?action=delete_transaction', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(res => {
                if (res.status === 'success') {
                    showToast('Đã xóa giao dịch thành công!');
                    loadCalendarMonthData(APP_STATE.currentMonth, APP_STATE.currentYear);
                    loadDateDetails(APP_STATE.selectedDate);
                } else {
                    showToast(res.message || 'Lỗi khi xóa', false);
                }
            })
            .catch(err => {
                console.error(err);
                showToast('Lỗi khi gửi yêu cầu', false);
            });
        }

        /**
         * Chỉnh sửa giao dịch (Placeholder cho form sửa)
         */
        function handleEditTransaction(id) {
            showToast('Chế độ chỉnh sửa giao dịch #' + id);
            openAddTransactionModal();
        }
    </script>
</body>
</html>
