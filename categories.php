<?php
/**
 * FinTrack VN - Danh Mục Thu Chi
 * Chuyển hướng sang tab Danh mục trong settings.php
 */
require_once __DIR__ . '/config.php';
header("Location: settings.php?tab=tab-categories");
exit;
