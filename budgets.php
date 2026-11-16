<?php
/**
 * FinTrack VN - Ngân Sách & Hạn Mức
 * Chuyển hướng sang tab Ngân sách trong settings.php
 */
require_once __DIR__ . '/config.php';
header("Location: settings.php?tab=tab-budgets");
exit;
