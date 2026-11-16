<?php
/**
 * ===================================================================
 * FinTrack VN - Sổ Quản Lý Tài Chính & Chi Tiêu Thông Minh
 * Hệ thống Đăng Nhập, Đăng Ký & Khôi Phục Mật Khẩu qua Gmail
 * ===================================================================
 */

require_once __DIR__ . '/config.php';

// Tự động kiểm tra và khởi tạo dữ liệu mẫu mặc định cho user mới khi đăng ký
function setupNewUserDefaultData($pdo, $newUserId) {
    if (!$pdo instanceof PDO) return;

    try {
        // 1. Tạo các danh mục mặc định cho user mới (hạn mức ban đầu = 0 để người dùng tự thiết lập)
        $defaultCategories = [
            ['Ăn uống', 'chi_tieu', 'fa-utensils', '#F97316', 0, 'Cà phê, ăn sáng, trưa, tối và liên hoan'],
            ['Mua sắm', 'chi_tieu', 'fa-bag-shopping', '#3B82F6', 0, 'Quần áo, đồ gia dụng, thiết bị công nghệ'],
            ['Di chuyển', 'chi_tieu', 'fa-car', '#4F59E8', 0, 'Xăng xe, gửi xe, Grab, bảo dưỡng định kỳ'],
            ['Hóa đơn & Dịch vụ', 'chi_tieu', 'fa-file-invoice-dollar', '#089182', 0, 'Điện, nước, internet, thuê nhà'],
            ['Giải trí', 'chi_tieu', 'fa-gamepad', '#9333EA', 0, 'Xem phim, game, du lịch, hội họp bạn bè'],
            ['Lương thưởng', 'thu_nhap', 'fa-briefcase', '#A96CA9', 0, 'Lương cơ bản, thưởng KPI, dự án'],
            ['Đầu tư sinh lời', 'thu_nhap', 'fa-chart-line', '#009488', 0, 'Lãi tiết kiệm, cổ tức, kinh doanh thêm']
        ];

        $insCat = $pdo->prepare("INSERT INTO `categories` (`user_id`, `name`, `type`, `icon`, `color`, `budget_limit`, `description`, `is_active`) VALUES (?, ?, ?, ?, ?, ?, ?, 1)");
        foreach ($defaultCategories as $c) {
            $insCat->execute([$newUserId, $c[0], $c[1], $c[2], $c[3], $c[4], $c[5]]);
        }

        // 2. Tạo phương thức thanh toán mặc định cho user mới với số dư ban đầu = 0 đ (trống dữ liệu tài chính)
        $defaultWallets = [
            ['Tiền mặt', 'cash', 0, 1],
            ['Tài khoản Ngân hàng', 'bank', 0, 0],
            ['Ví điện tử', 'ewallet', 0, 0]
        ];

        $insWallet = $pdo->prepare("INSERT INTO `payment_methods` (`user_id`, `name`, `type`, `balance`, `is_primary`, `is_active`) VALUES (?, ?, ?, ?, ?, 1)");
        foreach ($defaultWallets as $w) {
            $insWallet->execute([$newUserId, $w[0], $w[1], $w[2], $w[3]]);
        }

        // 3. Tạo ngân sách tháng mặc định ban đầu = 0 đ
        $insBdg = $pdo->prepare("INSERT INTO `budgets` (`user_id`, `month`, `year`, `total_budget`, `warning_threshold`, `warning_yellow`, `warning_red`) VALUES (?, ?, ?, 0, 80, 1, 1)");
        $insBdg->execute([$newUserId, (int)date('m'), (int)date('Y')]);

        // 4. Tạo cài đặt mặc định
        $insSt = $pdo->prepare("INSERT INTO `settings` (`user_id`, `currency`, `number_format`, `date_format`, `theme`) VALUES (?, 'VND', '100.000.000', 'DD/MM/YYYY', 'light')");
        $insSt->execute([$newUserId]);

    } catch (Exception $e) {
        // Bỏ qua lỗi phụ nếu đã tồn tại
    }
}

// Nếu đã đăng nhập, chuyển hướng sang index.php
if (isLoggedIn()) {
    redirect('index.php');
}

$error = '';
$success = '';
$viewMode = $_GET['mode'] ?? 'login'; // 'login', 'register', 'forgot', 'reset'

// Xử lý gửi biểu mẫu POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // ===================================================================
    // 1. ĐĂNG NHẬP 1-CLICK DEMO
    // ===================================================================
    if ($action === 'demo_login') {
        try {
            $stmt = $pdo->prepare("SELECT * FROM users WHERE username = 'admin' LIMIT 1");
            $stmt->execute();
            $demoUser = $stmt->fetch();

            if ($demoUser) {
                session_regenerate_id(true);
                $_SESSION['user_id']         = $demoUser['id'];
                $_SESSION['username']        = $demoUser['username'];
                $_SESSION['full_name']       = $demoUser['full_name'];
                $_SESSION['email']           = $demoUser['email'];
                $_SESSION['membership_type'] = $demoUser['membership_type'];
                $_SESSION['avatar']          = $demoUser['avatar'];

                redirect('index.php');
            } else {
                $error = 'Không tìm thấy tài khoản Demo admin!';
            }
        } catch (PDOException $e) {
            $error = 'Lỗi truy vấn: ' . $e->getMessage();
        }
    }

    // ===================================================================
    // 2. ĐĂNG NHẬP THÔNG THƯỜNG
    // ===================================================================
    elseif ($action === 'login') {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($username) || empty($password)) {
            $error = 'Vui lòng nhập tên đăng nhập/Gmail và mật khẩu!';
        } else {
            try {
                $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? OR email = ? OR phone = ? LIMIT 1");
                $stmt->execute([$username, $username, $username]);
                $user = $stmt->fetch();

                if ($user && password_verify($password, $user['password'])) {
                    session_regenerate_id(true);
                    $_SESSION['user_id']         = $user['id'];
                    $_SESSION['username']        = $user['username'];
                    $_SESSION['full_name']       = $user['full_name'];
                    $_SESSION['email']           = $user['email'];
                    $_SESSION['membership_type'] = $user['membership_type'];
                    $_SESSION['avatar']          = $user['avatar'];

                    redirect('index.php');
                } else {
                    $error = 'Tên đăng nhập, Gmail hoặc mật khẩu không chính xác!';
                }
            } catch (PDOException $e) {
                $error = 'Lỗi kết nối cơ sở dữ liệu: ' . $e->getMessage();
            }
        }
    }

    // ===================================================================
    // 3. ĐĂNG KÝ TÀI KHOẢN MỚI (CÓ GMAIL + SĐT + GỬI XÁC NHẬN)
    // ===================================================================
    elseif ($action === 'register') {
        $fullName = trim($_POST['full_name'] ?? '');
        $username = trim($_POST['username'] ?? '');
        $email    = strtolower(trim($_POST['email'] ?? ''));
        $phone    = trim($_POST['phone'] ?? '');
        $password = $_POST['password'] ?? '';
        $repass   = $_POST['re_password'] ?? '';

        if (empty($fullName) || empty($username) || empty($email) || empty($password)) {
            $error = 'Vui lòng điền đầy đủ các thông tin bắt buộc!';
            $viewMode = 'register';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Địa chỉ Gmail / Email không đúng định dạng!';
            $viewMode = 'register';
        } elseif ($password !== $repass) {
            $error = 'Mật khẩu xác nhận không khớp!';
            $viewMode = 'register';
        } elseif (strlen($password) < 6) {
            $error = 'Mật khẩu phải chứa ít nhất 6 ký tự!';
            $viewMode = 'register';
        } else {
            try {
                // Kiểm tra trùng username hoặc email
                $checkStmt = $pdo->prepare("SELECT id, username, email FROM users WHERE username = :u OR email = :e LIMIT 1");
                $checkStmt->execute([':u' => $username, ':e' => $email]);
                $existing = $checkStmt->fetch();

                if ($existing) {
                    if ($existing['username'] === $username) {
                        $error = 'Tên đăng nhập "' . htmlspecialchars($username) . '" đã được sử dụng!';
                    } else {
                        $error = 'Gmail "' . htmlspecialchars($email) . '" đã được đăng ký tài khoản!';
                    }
                    $viewMode = 'register';
                } else {
                    // Thêm tài khoản mới vào cơ sở dữ liệu
                    $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
                    $avatar = 'https://ui-avatars.com/api/?name=' . urlencode($fullName) . '&background=4F59E8&color=fff&size=200';

                    $insStmt = $pdo->prepare("INSERT INTO users (`username`, `email`, `phone`, `password`, `full_name`, `avatar`, `membership_type`) VALUES (?, ?, ?, ?, ?, ?, 'free')");
                    $insStmt->execute([$username, $email, $phone, $hashedPassword, $fullName, $avatar]);
                    $newUserId = (int)$pdo->lastInsertId();

                    // Tự động phân bổ dữ liệu mẫu riêng biệt cho tài khoản mới này
                    setupNewUserDefaultData($pdo, $newUserId);

                    // Gửi email xác nhận đăng ký thành công qua Gmail
                    $emailSubject = "🎉 Chào mừng bạn đến với FinTrack VN - Xác nhận tài khoản";
                    $emailBody = "
                        <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; background: #1B1F3B; color: #FFFFFF; border-radius: 16px; padding: 32px;'>
                            <div style='text-align: center; margin-bottom: 24px;'>
                                <h1 style='color: #4F59E8; margin: 0; font-size: 28px;'>FinTrack VN</h1>
                                <p style='color: #94A3B8; font-size: 14px;'>Sổ Quản Lý Tài Chính & Chi Tiêu Thông Minh</p>
                            </div>
                            <div style='background: #111427; border-radius: 12px; padding: 24px; border: 1px solid rgba(255,255,255,0.1);'>
                                <h2 style='color: #10B981; font-size: 20px; margin-top: 0;'>Xác nhận đăng ký thành công!</h2>
                                <p style='color: #E2E8F0; font-size: 15px; line-height: 1.6;'>
                                    Xin chào <strong>" . htmlspecialchars($fullName) . "</strong>,<br>
                                    Tài khoản FinTrack VN của bạn đã được khởi tạo thành công. Bạn có thể bắt đầu ghi chép và theo dõi dòng tiền ngay hôm nay.
                                </p>
                                <div style='margin: 20px 0; padding: 16px; background: rgba(79, 89, 232, 0.1); border-left: 4px solid #4F59E8; border-radius: 6px;'>
                                    <p style='margin: 4px 0; font-size: 13.5px;'><strong>Tên đăng nhập:</strong> " . htmlspecialchars($username) . "</p>
                                    <p style='margin: 4px 0; font-size: 13.5px;'><strong>Gmail:</strong> " . htmlspecialchars($email) . "</p>
                                    <p style='margin: 4px 0; font-size: 13.5px;'><strong>Số điện thoại:</strong> " . htmlspecialchars($phone ?: 'Chưa cập nhật') . "</p>
                                    <p style='margin: 4px 0; font-size: 13.5px;'><strong>Không gian dữ liệu:</strong> Độc lập & Riêng tư 100%</p>
                                </div>
                            </div>
                            <p style='text-align: center; color: #64748B; font-size: 12px; margin-top: 24px;'>
                                Đây là thư xác nhận tự động từ FinTrack VN. Chúc bạn quản lý tài chính hiệu quả!
                            </p>
                        </div>
                    ";
                    sendSystemEmail($email, $emailSubject, $emailBody);

                    // Tự động đăng nhập vào tài khoản vừa tạo
                    session_regenerate_id(true);
                    $_SESSION['user_id']         = $newUserId;
                    $_SESSION['username']        = $username;
                    $_SESSION['full_name']       = $fullName;
                    $_SESSION['email']           = $email;
                    $_SESSION['membership_type'] = 'free';
                    $_SESSION['avatar']          = $avatar;

                    redirect('index.php');
                }
            } catch (PDOException $e) {
                $error = 'Lỗi hệ thống khi đăng ký: ' . $e->getMessage();
                $viewMode = 'register';
            }
        }
    }

    // ===================================================================
    // 4. QUÊN MẬT KHẨU - GỬI MÃ OTP QUA GMAIL
    // ===================================================================
    elseif ($action === 'forgot_password') {
        $email = strtolower(trim($_POST['email'] ?? ''));

        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Vui lòng nhập địa chỉ Gmail hợp lệ đã đăng ký tài khoản!';
            $viewMode = 'forgot';
        } else {
            try {
                $stmt = $pdo->prepare("SELECT id, username, full_name, email FROM users WHERE email = :e LIMIT 1");
                $stmt->execute([':e' => $email]);
                $user = $stmt->fetch();

                if (!$user) {
                    $error = 'Không tìm thấy tài khoản nào liên kết với Gmail: ' . htmlspecialchars($email);
                    $viewMode = 'forgot';
                } else {
                    // Tạo mã OTP 6 số và mã token bảo mật (hết hạn sau 15 phút)
                    $otp = sprintf("%06d", mt_rand(100000, 999999));
                    $token = bin2hex(random_bytes(24));
                    $expiresAt = date('Y-m-d H:i:s', strtotime('+15 minutes'));

                    // Lưu vào bảng password_resets
                    $insReset = $pdo->prepare("INSERT INTO password_resets (`email`, `token`, `otp`, `expires_at`) VALUES (?, ?, ?, ?)");
                    $insReset->execute([$email, $token, $otp, $expiresAt]);

                    // Gửi email chứa mã OTP và liên kết
                    $subject = "🔐 Mã OTP Đặt Lại Mật Khẩu - FinTrack VN";
                    $body = "
                        <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; background: #1B1F3B; color: #FFFFFF; border-radius: 16px; padding: 32px;'>
                            <div style='text-align: center; margin-bottom: 24px;'>
                                <h1 style='color: #4F59E8; margin: 0; font-size: 28px;'>FinTrack VN</h1>
                                <p style='color: #94A3B8; font-size: 14px;'>Khôi Phục Mật Khẩu Tài Khoản</p>
                            </div>
                            <div style='background: #111427; border-radius: 12px; padding: 24px; border: 1px solid rgba(255,255,255,0.1); text-align: center;'>
                                <p style='color: #E2E8F0; font-size: 15px;'>Xin chào <strong>" . htmlspecialchars($user['full_name']) . "</strong>,</p>
                                <p style='color: #94A3B8; font-size: 14px;'>Chúng tôi nhận được yêu cầu đặt lại mật khẩu cho tài khoản liên kết với Gmail này.</p>
                                <div style='margin: 25px auto; padding: 18px 24px; background: #1F2448; border-radius: 12px; display: inline-block; border: 2px dashed #4F59E8;'>
                                    <span style='font-size: 13px; color: #94A3B8; display: block; margin-bottom: 6px;'>MÃ XÁC NHẬN OTP CỦA BẠN</span>
                                    <span style='font-size: 32px; font-weight: 800; letter-spacing: 6px; color: #10B981; font-family: monospace;'>" . $otp . "</span>
                                </div>
                                <p style='color: #EF4444; font-size: 13px;'>Mã xác nhận này sẽ hết hiệu lực sau <strong>15 phút</strong>. Tuyệt đối không chia sẻ mã này cho bất kỳ ai.</p>
                            </div>
                        </div>
                    ";
                    sendSystemEmail($email, $subject, $body);

                    $success = 'Mã xác nhận OTP đã được gửi đến ' . htmlspecialchars($email) . '. Vui lòng kiểm tra hộp thư!';
                    $viewMode = 'reset';
                    $_GET['email'] = $email;
                }
            } catch (PDOException $e) {
                $error = 'Lỗi hệ thống: ' . $e->getMessage();
                $viewMode = 'forgot';
            }
        }
    }

    // ===================================================================
    // 5. NHẬP MÃ OTP VÀ THIẾT LẬP MẬT KHẨU MỚI
    // ===================================================================
    elseif ($action === 'reset_password') {
        $email    = strtolower(trim($_POST['email'] ?? ''));
        $otp      = trim($_POST['otp'] ?? '');
        $password = $_POST['password'] ?? '';
        $repass   = $_POST['re_password'] ?? '';

        if (empty($email) || empty($otp) || empty($password)) {
            $error = 'Vui lòng nhập đầy đủ mã OTP và mật khẩu mới!';
            $viewMode = 'reset';
        } elseif ($password !== $repass) {
            $error = 'Mật khẩu xác nhận không khớp!';
            $viewMode = 'reset';
        } elseif (strlen($password) < 6) {
            $error = 'Mật khẩu phải chứa ít nhất 6 ký tự!';
            $viewMode = 'reset';
        } else {
            try {
                // Kiểm tra OTP hợp lệ và còn hạn
                $stmt = $pdo->prepare("SELECT id FROM password_resets WHERE email = ? AND otp = ? AND expires_at > NOW() ORDER BY id DESC LIMIT 1");
                $stmt->execute([$email, $otp]);
                $resetRecord = $stmt->fetch();

                if (!$resetRecord) {
                    $error = 'Mã OTP không chính xác hoặc đã hết hạn (15 phút)! Vui lòng yêu cầu mã mới.';
                    $viewMode = 'reset';
                } else {
                    // Cập nhật mật khẩu mới cho tài khoản
                    $newHash = password_hash($password, PASSWORD_BCRYPT);
                    $updUser = $pdo->prepare("UPDATE users SET password = ? WHERE email = ?");
                    $updUser->execute([$newHash, $email]);

                    // Xóa các mã reset cũ của email này
                    $delStmt = $pdo->prepare("DELETE FROM password_resets WHERE email = ?");
                    $delStmt->execute([$email]);

                    $success = 'Đổi mật khẩu thành công! Bạn có thể đăng nhập ngay với mật khẩu mới.';
                    $viewMode = 'login';
                }
            } catch (PDOException $e) {
                $error = 'Lỗi cập nhật mật khẩu: ' . $e->getMessage();
                $viewMode = 'reset';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>
        <?= $viewMode === 'register' ? 'Đăng Ký Tài Khoản' : ($viewMode === 'forgot' ? 'Quên Mật Khẩu' : ($viewMode === 'reset' ? 'Đặt Lại Mật Khẩu' : 'Đăng Nhập')) ?> - FinTrack VN
    </title>
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        brand: {
                            navy: '#1B1F3B',
                            dark: '#111427',
                            card: '#22284E',
                            accent: '#4F59E8',
                            lightAccent: '#6366F1'
                        }
                    }
                }
            }
        }
    </script>
    <!-- FontAwesome 6 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background-color: #111427;
        }
        .bg-radial-gradient {
            background: radial-gradient(circle at 50% 0%, #2d336b 0%, #111427 75%);
        }
    </style>
</head>
<body class="bg-radial-gradient min-h-screen text-gray-100 flex items-center justify-center p-4">

    <div class="w-full max-w-md my-8">
        <!-- Logo & Thương hiệu -->
        <div class="text-center mb-6">
            <a href="index.php" class="inline-block">
                <div class="inline-flex items-center justify-center w-16 h-16 rounded-2xl bg-gradient-to-tr from-[#4F59E8] to-[#06B6D4] shadow-lg shadow-indigo-500/30 mb-3">
                    <i class="fa-solid fa-wallet text-2xl text-white"></i>
                </div>
                <h1 class="text-3xl font-extrabold tracking-tight text-white">
                    FinTrack <span class="text-[#4F59E8]">VN</span>
                </h1>
                <p class="text-xs text-gray-400 mt-1 font-medium">
                    Sổ Quản Lý Tài Chính & Chi Tiêu Thông Minh
                </p>
            </a>
        </div>

        <!-- Khung Card chính -->
        <div class="bg-[#1B1F3B] border border-gray-700/60 rounded-3xl p-7 shadow-2xl backdrop-blur-sm relative overflow-hidden">
            <!-- Nét trang trí viền trên -->
            <div class="absolute top-0 left-0 right-0 h-1 bg-gradient-to-r from-transparent via-[#4F59E8] to-transparent"></div>

            <!-- Header tab / Tiêu đề -->
            <div class="flex items-center justify-between mb-5">
                <h2 class="text-xl font-bold text-white flex items-center gap-2">
                    <?php if ($viewMode === 'register'): ?>
                        <i class="fa-solid fa-user-plus text-[#10B981]"></i>
                        <span>Đăng Ký Tài Khoản</span>
                    <?php elseif ($viewMode === 'forgot' || $viewMode === 'reset'): ?>
                        <i class="fa-solid fa-key text-[#F59E0B]"></i>
                        <span>Khôi Phục Mật Khẩu</span>
                    <?php else: ?>
                        <i class="fa-solid fa-right-to-bracket text-[#4F59E8]"></i>
                        <span>Đăng Nhập</span>
                    <?php endif; ?>
                </h2>

                <div class="flex items-center gap-1">
                    <a href="auth.php?mode=login" class="text-xs px-2.5 py-1 rounded-lg <?= $viewMode === 'login' ? 'bg-[#4F59E8] text-white font-bold' : 'text-gray-400 hover:text-white' ?> transition">Đăng nhập</a>
                    <a href="auth.php?mode=register" class="text-xs px-2.5 py-1 rounded-lg <?= $viewMode === 'register' ? 'bg-[#10B981] text-white font-bold' : 'text-gray-400 hover:text-white' ?> transition">Đăng ký</a>
                </div>
            </div>

            <!-- Thông báo Lỗi -->
            <?php if (!empty($error)): ?>
                <div class="mb-5 p-3.5 rounded-xl bg-red-500/15 border border-red-500/30 text-red-300 text-sm flex items-start gap-3">
                    <i class="fa-solid fa-triangle-exclamation text-red-400 mt-0.5 text-base flex-shrink-0"></i>
                    <div><?= htmlspecialchars($error) ?></div>
                </div>
            <?php endif; ?>

            <!-- Thông báo Thành công -->
            <?php if (!empty($success)): ?>
                <div class="mb-5 p-3.5 rounded-xl bg-emerald-500/15 border border-emerald-500/30 text-emerald-300 text-sm flex items-start gap-3">
                    <i class="fa-solid fa-circle-check text-emerald-400 mt-0.5 text-base flex-shrink-0"></i>
                    <div><?= htmlspecialchars($success) ?></div>
                </div>
            <?php endif; ?>

            <!-- Hộp Giả Lập Email Gửi Trực Tiếp Trên Localhost (Nếu có email vừa phát sinh) -->
            <?php if (!empty($_SESSION['last_sent_email'])): ?>
                <?php $mailData = $_SESSION['last_sent_email']; ?>
                <div class="mb-5 p-3.5 rounded-xl bg-indigo-500/15 border border-indigo-500/30 text-indigo-200 text-xs">
                    <div class="flex items-center justify-between font-bold mb-1 text-indigo-300">
                        <span><i class="fa-solid fa-envelope-open-text mr-1"></i> Hộp thư Gmail đã gửi (Localhost)</span>
                        <span class="text-[10px] text-gray-400"><?= $mailData['time'] ?></span>
                    </div>
                    <div class="text-gray-300">Gửi tới: <strong class="text-white"><?= htmlspecialchars($mailData['to']) ?></strong></div>
                    <div class="text-gray-300">Tiêu đề: <?= htmlspecialchars($mailData['subject']) ?></div>
                    <details class="mt-2 text-gray-400 cursor-pointer">
                        <summary class="hover:text-white">Xem nội dung thư xác nhận</summary>
                        <div class="mt-2 p-2 bg-gray-900/80 rounded border border-gray-700 max-h-48 overflow-y-auto">
                            <?= $mailData['body'] ?>
                        </div>
                    </details>
                </div>
            <?php endif; ?>

            <!-- ===================================================================
                 VIEW 1: FORM ĐĂNG NHẬP (LOGIN)
                 =================================================================== -->
            <?php if ($viewMode === 'login'): ?>
                <form action="auth.php?mode=login" method="POST" class="space-y-4">
                    <input type="hidden" name="action" value="login">

                    <div>
                        <label for="username" class="block text-xs font-semibold uppercase tracking-wider text-gray-300 mb-2">
                            Tên đăng nhập, Gmail hoặc SĐT
                        </label>
                        <div class="relative">
                            <span class="absolute inset-y-0 left-0 flex items-center pl-3.5 pointer-events-none text-gray-400">
                                <i class="fa-regular fa-user"></i>
                            </span>
                            <input type="text" 
                                   id="username" 
                                   name="username" 
                                   required 
                                   value="<?= isset($_POST['username']) ? htmlspecialchars($_POST['username']) : '' ?>"
                                   placeholder="admin hoặc an.nguyen@email.com" 
                                   class="w-full bg-[#111427]/80 border border-gray-700 focus:border-[#4F59E8] focus:ring-2 focus:ring-[#4F59E8]/30 rounded-xl pl-10 pr-4 py-3 text-sm text-white placeholder-gray-500 transition outline-none">
                        </div>
                    </div>

                    <div>
                        <div class="flex items-center justify-between mb-2">
                            <label for="password" class="block text-xs font-semibold uppercase tracking-wider text-gray-300">
                                Mật khẩu
                            </label>
                            <a href="auth.php?mode=forgot" class="text-xs text-indigo-400 hover:text-indigo-300 hover:underline">
                                Quên mật khẩu?
                            </a>
                        </div>
                        <div class="relative">
                            <span class="absolute inset-y-0 left-0 flex items-center pl-3.5 pointer-events-none text-gray-400">
                                <i class="fa-solid fa-lock"></i>
                            </span>
                            <input type="password" 
                                   id="password" 
                                   name="password" 
                                   required 
                                   placeholder="••••••••" 
                                   class="w-full bg-[#111427]/80 border border-gray-700 focus:border-[#4F59E8] focus:ring-2 focus:ring-[#4F59E8]/30 rounded-xl pl-10 pr-11 py-3 text-sm text-white placeholder-gray-500 transition outline-none">
                            <button type="button" 
                                    class="togglePasswordBtn absolute inset-y-0 right-0 flex items-center pr-3.5 text-gray-400 hover:text-gray-200">
                                <i class="fa-regular fa-eye"></i>
                            </button>
                        </div>
                    </div>

                    <button type="submit" 
                            class="w-full bg-gradient-to-r from-[#4F59E8] to-[#6366F1] hover:from-[#434bd4] hover:to-[#5558e6] text-white font-semibold py-3 px-4 rounded-xl shadow-lg shadow-indigo-500/25 transition duration-200 flex items-center justify-center gap-2 group mt-2">
                        <span>Đăng Nhập Ngay</span>
                        <i class="fa-solid fa-arrow-right text-xs group-hover:translate-x-1 transition duration-200"></i>
                    </button>
                </form>

                <div class="relative my-6 text-center">
                    <div class="absolute inset-0 flex items-center">
                        <div class="w-full border-t border-gray-700/70"></div>
                    </div>
                    <span class="relative px-3 bg-[#1B1F3B] text-xs uppercase tracking-wider text-gray-400 font-medium">
                        Hoặc dùng nhanh
                    </span>
                </div>

                <form action="auth.php" method="POST">
                    <input type="hidden" name="action" value="demo_login">
                    <button type="submit" 
                            class="w-full bg-emerald-500/10 hover:bg-emerald-500/20 border border-emerald-500/30 text-emerald-400 hover:text-emerald-300 font-semibold py-3 px-4 rounded-xl transition duration-200 flex items-center justify-center gap-2.5">
                        <i class="fa-solid fa-bolt text-emerald-400"></i>
                        <span>Đăng Nhập Nhanh Tài Khoản Demo</span>
                    </button>
                </form>

                <div class="mt-5 p-3.5 rounded-xl bg-gray-900/60 border border-gray-800 text-xs text-gray-400">
                    <div class="flex items-center justify-between mb-2 pb-1 border-b border-gray-800">
                        <span class="font-medium text-gray-300 flex items-center gap-1.5">
                            <i class="fa-solid fa-circle-info text-indigo-400"></i>
                            Tài khoản mẫu:
                        </span>
                        <button type="button" 
                                id="btnQuickFill" 
                                class="text-indigo-400 hover:text-indigo-300 font-semibold hover:underline">
                            Điền nhanh vào form
                        </button>
                    </div>
                    <div class="grid grid-cols-2 gap-2 font-mono">
                        <div>Tài khoản: <span class="text-white font-semibold">admin</span></div>
                        <div>Mật khẩu: <span class="text-white font-semibold">admin123</span></div>
                    </div>
                </div>

                <p class="text-center text-xs text-gray-400 mt-5">
                    Chưa có tài khoản riêng? 
                    <a href="auth.php?mode=register" class="text-emerald-400 font-semibold hover:underline ml-1">Đăng ký tài khoản mới</a>
                </p>

            <!-- ===================================================================
                 VIEW 2: FORM MẪU ĐĂNG KÝ NHANH (GMAIL, SĐT, QUẢN LÝ RIÊNG)
                 =================================================================== -->
            <?php elseif ($viewMode === 'register'): ?>
                <form action="auth.php?mode=register" method="POST" class="space-y-3.5">
                    <input type="hidden" name="action" value="register">

                    <div>
                        <label for="reg_fullname" class="block text-xs font-semibold uppercase tracking-wider text-gray-300 mb-1.5">
                            Họ và tên <span class="text-red-400">*</span>
                        </label>
                        <div class="relative">
                            <span class="absolute inset-y-0 left-0 flex items-center pl-3.5 pointer-events-none text-gray-400">
                                <i class="fa-solid fa-signature"></i>
                            </span>
                            <input type="text" 
                                   id="reg_fullname" 
                                   name="full_name" 
                                   required 
                                   value="<?= isset($_POST['full_name']) ? htmlspecialchars($_POST['full_name']) : '' ?>"
                                   placeholder="Ví dụ: Trần Văn Nam" 
                                   class="w-full bg-[#111427]/80 border border-gray-700 focus:border-[#10B981] focus:ring-2 focus:ring-[#10B981]/30 rounded-xl pl-10 pr-4 py-2.5 text-sm text-white placeholder-gray-500 transition outline-none">
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label for="reg_username" class="block text-xs font-semibold uppercase tracking-wider text-gray-300 mb-1.5">
                                Tên đăng nhập <span class="text-red-400">*</span>
                            </label>
                            <div class="relative">
                                <span class="absolute inset-y-0 left-0 flex items-center pl-3.5 pointer-events-none text-gray-400">
                                    <i class="fa-solid fa-at"></i>
                                </span>
                                <input type="text" 
                                       id="reg_username" 
                                       name="username" 
                                       required 
                                       value="<?= isset($_POST['username']) ? htmlspecialchars($_POST['username']) : '' ?>"
                                       placeholder="vannam123" 
                                       class="w-full bg-[#111427]/80 border border-gray-700 focus:border-[#10B981] focus:ring-2 focus:ring-[#10B981]/30 rounded-xl pl-10 pr-4 py-2.5 text-sm text-white placeholder-gray-500 transition outline-none">
                            </div>
                        </div>

                        <div>
                            <label for="reg_phone" class="block text-xs font-semibold uppercase tracking-wider text-gray-300 mb-1.5">
                                Số điện thoại (SĐT)
                            </label>
                            <div class="relative">
                                <span class="absolute inset-y-0 left-0 flex items-center pl-3.5 pointer-events-none text-gray-400">
                                    <i class="fa-solid fa-phone"></i>
                                </span>
                                <input type="tel" 
                                       id="reg_phone" 
                                       name="phone" 
                                       value="<?= isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : '' ?>"
                                       placeholder="0912345678" 
                                       class="w-full bg-[#111427]/80 border border-gray-700 focus:border-[#10B981] focus:ring-2 focus:ring-[#10B981]/30 rounded-xl pl-10 pr-4 py-2.5 text-sm text-white placeholder-gray-500 transition outline-none">
                            </div>
                        </div>
                    </div>

                    <div>
                        <label for="reg_email" class="block text-xs font-semibold uppercase tracking-wider text-gray-300 mb-1.5">
                            Gmail / Email nhận thư xác nhận <span class="text-red-400">*</span>
                        </label>
                        <div class="relative">
                            <span class="absolute inset-y-0 left-0 flex items-center pl-3.5 pointer-events-none text-gray-400">
                                <i class="fa-solid fa-envelope"></i>
                            </span>
                            <input type="email" 
                                   id="reg_email" 
                                   name="email" 
                                   required 
                                   value="<?= isset($_POST['email']) ? htmlspecialchars($_POST['email']) : '' ?>"
                                   placeholder="nam.tran@gmail.com" 
                                   class="w-full bg-[#111427]/80 border border-gray-700 focus:border-[#10B981] focus:ring-2 focus:ring-[#10B981]/30 rounded-xl pl-10 pr-4 py-2.5 text-sm text-white placeholder-gray-500 transition outline-none">
                        </div>
                        <span class="text-[11px] text-gray-400 mt-1 block">
                            * Thư xác nhận đăng ký sẽ được tự động gửi vào Gmail này.
                        </span>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label for="reg_password" class="block text-xs font-semibold uppercase tracking-wider text-gray-300 mb-1.5">
                                Mật khẩu <span class="text-red-400">*</span>
                            </label>
                            <div class="relative">
                                <span class="absolute inset-y-0 left-0 flex items-center pl-3.5 pointer-events-none text-gray-400">
                                    <i class="fa-solid fa-lock"></i>
                                </span>
                                <input type="password" 
                                       id="reg_password" 
                                       name="password" 
                                       required 
                                       placeholder="••••••••" 
                                       class="w-full bg-[#111427]/80 border border-gray-700 focus:border-[#10B981] focus:ring-2 focus:ring-[#10B981]/30 rounded-xl pl-10 pr-10 py-2.5 text-sm text-white placeholder-gray-500 transition outline-none">
                                <button type="button" 
                                        class="togglePasswordBtn absolute inset-y-0 right-0 flex items-center pr-3 text-gray-400 hover:text-gray-200">
                                    <i class="fa-regular fa-eye"></i>
                                </button>
                            </div>
                        </div>

                        <div>
                            <label for="reg_repassword" class="block text-xs font-semibold uppercase tracking-wider text-gray-300 mb-1.5">
                                Nhập lại mật khẩu <span class="text-red-400">*</span>
                            </label>
                            <div class="relative">
                                <span class="absolute inset-y-0 left-0 flex items-center pl-3.5 pointer-events-none text-gray-400">
                                    <i class="fa-solid fa-shield-check"></i>
                                </span>
                                <input type="password" 
                                       id="reg_repassword" 
                                       name="re_password" 
                                       required 
                                       placeholder="••••••••" 
                                       class="w-full bg-[#111427]/80 border border-gray-700 focus:border-[#10B981] focus:ring-2 focus:ring-[#10B981]/30 rounded-xl pl-10 pr-4 py-2.5 text-sm text-white placeholder-gray-500 transition outline-none">
                            </div>
                        </div>
                    </div>

                    <div class="p-3 bg-emerald-500/10 border border-emerald-500/20 rounded-xl text-xs text-emerald-300 flex items-center gap-2">
                        <i class="fa-solid fa-lock text-sm flex-shrink-0"></i>
                        <span>Mỗi tài khoản được cấp cơ sở dữ liệu thu/chi và ví tiền riêng biệt 100%.</span>
                    </div>

                    <button type="submit" 
                            class="w-full bg-gradient-to-r from-[#10B981] to-[#059669] hover:from-[#0d9488] hover:to-[#047857] text-white font-semibold py-3 px-4 rounded-xl shadow-lg shadow-emerald-500/25 transition duration-200 flex items-center justify-center gap-2 mt-2">
                        <span>Đăng Ký & Nhận Xác Nhận Gmail</span>
                        <i class="fa-solid fa-paper-plane text-xs"></i>
                    </button>
                </form>

                <p class="text-center text-xs text-gray-400 mt-5">
                    Đã có tài khoản? 
                    <a href="auth.php?mode=login" class="text-indigo-400 font-semibold hover:underline ml-1">Quay lại Đăng nhập</a>
                </p>

            <!-- ===================================================================
                 VIEW 3: QUÊN MẬT KHẨU - NHẬP GMAIL NHẬN MÃ OTP
                 =================================================================== -->
            <?php elseif ($viewMode === 'forgot'): ?>
                <form action="auth.php?mode=forgot" method="POST" class="space-y-4">
                    <input type="hidden" name="action" value="forgot_password">

                    <p class="text-xs text-gray-300 leading-relaxed">
                        Nhập địa chỉ Gmail của tài khoản bạn muốn lấy lại mật khẩu. Hệ thống sẽ gửi ngay mã xác nhận bảo mật OTP 6 số đến hộp thư của bạn.
                    </p>

                    <div>
                        <label for="forgot_email" class="block text-xs font-semibold uppercase tracking-wider text-gray-300 mb-2">
                            Địa chỉ Gmail đăng ký
                        </label>
                        <div class="relative">
                            <span class="absolute inset-y-0 left-0 flex items-center pl-3.5 pointer-events-none text-gray-400">
                                <i class="fa-solid fa-envelope"></i>
                            </span>
                            <input type="email" 
                                   id="forgot_email" 
                                   name="email" 
                                   required 
                                   value="<?= isset($_POST['email']) ? htmlspecialchars($_POST['email']) : '' ?>"
                                   placeholder="an.nguyen@email.com hoặc bạn@gmail.com" 
                                   class="w-full bg-[#111427]/80 border border-gray-700 focus:border-[#F59E0B] focus:ring-2 focus:ring-[#F59E0B]/30 rounded-xl pl-10 pr-4 py-3 text-sm text-white placeholder-gray-500 transition outline-none">
                        </div>
                    </div>

                    <button type="submit" 
                            class="w-full bg-gradient-to-r from-[#F59E0B] to-[#D97706] hover:from-[#d97706] hover:to-[#b45309] text-white font-semibold py-3 px-4 rounded-xl shadow-lg shadow-amber-500/25 transition duration-200 flex items-center justify-center gap-2">
                        <span>Gửi Mã Xác Nhận Vào Gmail</span>
                        <i class="fa-solid fa-paper-plane text-xs"></i>
                    </button>
                </form>

                <p class="text-center text-xs text-gray-400 mt-5">
                    Nhớ lại mật khẩu rồi? 
                    <a href="auth.php?mode=login" class="text-indigo-400 font-semibold hover:underline ml-1">Đăng nhập</a>
                </p>

            <!-- ===================================================================
                 VIEW 4: NHẬP MÃ OTP VÀ THIẾT LẬP LẠI MẬT KHẨU MỚI
                 =================================================================== -->
            <?php elseif ($viewMode === 'reset'): ?>
                <form action="auth.php?mode=reset" method="POST" class="space-y-4">
                    <input type="hidden" name="action" value="reset_password">

                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wider text-gray-300 mb-2">
                            Tài khoản Gmail
                        </label>
                        <input type="email" 
                               name="email" 
                               readonly
                               value="<?= htmlspecialchars($_GET['email'] ?? ($_POST['email'] ?? '')) ?>" 
                               class="w-full bg-gray-900 border border-gray-700 rounded-xl px-4 py-2.5 text-sm text-gray-300 outline-none">
                    </div>

                    <div>
                        <label for="reset_otp" class="block text-xs font-semibold uppercase tracking-wider text-gray-300 mb-2">
                            Mã xác nhận OTP (6 chữ số trong Gmail)
                        </label>
                        <div class="relative">
                            <span class="absolute inset-y-0 left-0 flex items-center pl-3.5 pointer-events-none text-gray-400">
                                <i class="fa-solid fa-shield-halved"></i>
                            </span>
                            <input type="text" 
                                   id="reset_otp" 
                                   name="otp" 
                                   required 
                                   maxlength="10"
                                   placeholder="Ví dụ: 123456" 
                                   class="w-full bg-[#111427]/80 border border-gray-700 focus:border-[#10B981] focus:ring-2 focus:ring-[#10B981]/30 rounded-xl pl-10 pr-4 py-3 text-sm text-white font-mono tracking-widest placeholder-gray-500 transition outline-none">
                        </div>
                    </div>

                    <div>
                        <label for="reset_password" class="block text-xs font-semibold uppercase tracking-wider text-gray-300 mb-2">
                            Mật khẩu mới
                        </label>
                        <div class="relative">
                            <span class="absolute inset-y-0 left-0 flex items-center pl-3.5 pointer-events-none text-gray-400">
                                <i class="fa-solid fa-lock"></i>
                            </span>
                            <input type="password" 
                                   id="reset_password" 
                                   name="password" 
                                   required 
                                   placeholder="Tối thiểu 6 ký tự" 
                                   class="w-full bg-[#111427]/80 border border-gray-700 focus:border-[#10B981] focus:ring-2 focus:ring-[#10B981]/30 rounded-xl pl-10 pr-10 py-3 text-sm text-white placeholder-gray-500 transition outline-none">
                            <button type="button" 
                                    class="togglePasswordBtn absolute inset-y-0 right-0 flex items-center pr-3 text-gray-400 hover:text-gray-200">
                                <i class="fa-regular fa-eye"></i>
                            </button>
                        </div>
                    </div>

                    <div>
                        <label for="reset_repassword" class="block text-xs font-semibold uppercase tracking-wider text-gray-300 mb-2">
                            Xác nhận mật khẩu mới
                        </label>
                        <div class="relative">
                            <span class="absolute inset-y-0 left-0 flex items-center pl-3.5 pointer-events-none text-gray-400">
                                <i class="fa-solid fa-lock-check"></i>
                            </span>
                            <input type="password" 
                                   id="reset_repassword" 
                                   name="re_password" 
                                   required 
                                   placeholder="Nhập lại mật khẩu mới" 
                                   class="w-full bg-[#111427]/80 border border-gray-700 focus:border-[#10B981] focus:ring-2 focus:ring-[#10B981]/30 rounded-xl pl-10 pr-4 py-3 text-sm text-white placeholder-gray-500 transition outline-none">
                        </div>
                    </div>

                    <button type="submit" 
                            class="w-full bg-gradient-to-r from-[#10B981] to-[#059669] hover:from-[#0d9488] hover:to-[#047857] text-white font-semibold py-3 px-4 rounded-xl shadow-lg shadow-emerald-500/25 transition duration-200 flex items-center justify-center gap-2">
                        <span>Lưu Mật Khẩu Mới & Đăng Nhập</span>
                        <i class="fa-solid fa-check text-xs"></i>
                    </button>
                </form>

                <p class="text-center text-xs text-gray-400 mt-5">
                    Chưa nhận được mã? 
                    <a href="auth.php?mode=forgot" class="text-amber-400 font-semibold hover:underline ml-1">Gửi lại mã OTP</a>
                </p>
            <?php endif; ?>

        </div>

        <!-- Chân trang -->
        <p class="text-center text-xs text-gray-500 mt-6 flex items-center justify-center gap-1.5">
            <i class="fa-solid fa-shield-halved text-gray-600"></i>
            FinTrack VN &copy; <?= date('Y') ?> • Bảo mật với mã hóa Bcrypt & Quản lý dữ liệu riêng biệt
        </p>
    </div>

    <script>
        // Bật/tắt ẩn hiện mật khẩu trên tất cả các ô input password
        document.querySelectorAll('.togglePasswordBtn').forEach(btn => {
            btn.addEventListener('click', function() {
                const input = this.parentElement.querySelector('input');
                const icon = this.querySelector('i');
                if (input) {
                    const isPassword = input.getAttribute('type') === 'password';
                    input.setAttribute('type', isPassword ? 'text' : 'password');
                    icon.classList.toggle('fa-eye', !isPassword);
                    icon.classList.toggle('fa-eye-slash', isPassword);
                }
            });
        });

        // Điền nhanh thông tin tài khoản mẫu ở chế độ Đăng nhập
        const btnQuickFill = document.getElementById('btnQuickFill');
        const usernameInput = document.getElementById('username');
        const passwordInput = document.getElementById('password');

        if (btnQuickFill && usernameInput && passwordInput) {
            btnQuickFill.addEventListener('click', function() {
                usernameInput.value = 'admin';
                passwordInput.value = 'admin123';
                usernameInput.focus();
            });
        }
    </script>
</body>
</html>
