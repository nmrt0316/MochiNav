<?php
/* ==========================================================================
   糯米引导页 - 全站统计代码输出模块
   说明：此文件可被任意页面通过 include 引入，用于在页面底部原样输出统计代码。
   ========================================================================== */

if (!defined('NUOMI_ANALYTICS_LOADED')) {
    define('NUOMI_ANALYTICS_LOADED', true);

    $analyticsCode = '';
    $dataFile = __DIR__ . '/admin/data.json';

    if (file_exists($dataFile)) {
        $raw = @file_get_contents($dataFile);
        $jsonData = json_decode($raw, true);
        if (is_array($jsonData) && isset($jsonData['site']['analyticsCode'])) {
            $analyticsCode = trim((string)$jsonData['site']['analyticsCode']);
        }
    }

    if ($analyticsCode !== '') {
        echo "\n" . $analyticsCode . "\n";
    }
}