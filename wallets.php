<?php
/**
 * FinTrack VN - Tài Khoản & Ví
 * Chuyển hướng sang tab Tài khoản & Ví trong settings.php
 */
require_once __DIR__ . '/config.php';
header("Location: settings.php?tab=tab-wallets");
exit;
