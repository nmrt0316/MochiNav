<?php ob_start(); ?>
<?php
/* ==========================================================================
   糯米の引导页 - 后台管理入口 admin/index.php
   ========================================================================== */

/* ==========================================================================
   1. 系统初始化与常量定义
   ========================================================================== */
@ini_set('memory_limit', '512M');

/* 默认值唯一权威来源（前后台共用） */
require_once __DIR__ . '/defaults.php';

error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

define('DATA_FILE', __DIR__ . '/data.json');
define('ADMIN_CONF', __DIR__ . '/.admin.conf');
define('PHOTO_DIR', __DIR__ . '/../photos');
define('MAX_UPLOAD', 5 * 1024 * 1024);
define('ALLOWED_EXT', ['jpg', 'jpeg', 'png', 'gif', 'webp']);
define('JS_DIR',    __DIR__ . '/../assets/js');
define('GIRL_DIR',  __DIR__ . '/../photos/kanban');
define('PRELOADER_DIR', __DIR__ . '/../assets/preloaders');
define('MAX_JS_UPLOAD',  2 * 1024 * 1024);
define('MAX_GIF_UPLOAD', 10 * 1024 * 1024);
define('ALLOWED_GIF_EXT', ['gif', 'png', 'webp', 'jpg', 'jpeg']);

define('LOCALBG_DIR',     __DIR__ . '/../photos/localbg');
define('LOCALBANNER_DIR', __DIR__ . '/../photos/localbanner');

/* 缩略图目录（自动创建，不参与原图管理） */
define('PHOTO_THUMB_DIR',       __DIR__ . '/../photos/thumbs');
define('GIRL_THUMB_DIR',        __DIR__ . '/../photos/kanban_thumbs');
define('LOCALBG_THUMB_DIR',     __DIR__ . '/../photos/localbg_thumbs');
define('LOCALBANNER_THUMB_DIR', __DIR__ . '/../photos/localbanner_thumbs');

/* 缩略图长边尺寸（像素），保持等比缩放 */
define('THUMB_MAX_SIZE', 400);

/* 在线升级工作目录（常量 UPDATE_DIR 等定义在 defaults.php） */
if (!is_dir(UPDATE_DIR)) @mkdir(UPDATE_DIR, 0755, true);

foreach ([
    PHOTO_DIR, GIRL_DIR, JS_DIR, PRELOADER_DIR, LOCALBG_DIR, LOCALBANNER_DIR,
    PHOTO_THUMB_DIR, GIRL_THUMB_DIR, LOCALBG_THUMB_DIR, LOCALBANNER_THUMB_DIR
] as $__dir) {
    if (!is_dir($__dir)) @mkdir($__dir, 0755, true);
}
unset($__dir);

if (session_status() === PHP_SESSION_NONE) {
    session_name('NUOMI_ADMIN');
    session_start();
}

/* ==========================================================================
   2. 核心默认配置
   --------------------------------------------------------------------------
   所有默认值统一定义在 admin/defaults.php（前后台共用），
   这里不再重复定义，避免 "Cannot redeclare" fatal error。
   ========================================================================== */

/* ==========================================================================
   3. 通用工具与数据规范化
   ========================================================================== */
function respond($ok, $msg = '', $extra = []) {
    ob_end_clean();
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(array_merge(['ok' => (bool)$ok, 'msg' => $msg], $extra), JSON_UNESCAPED_UNICODE);
    exit;
}

function is_remote_url($path) {
    return preg_match('#^https?://#i', trim((string)$path)) === 1;
}

function sanitize_filename($name, $fallback = 'file') {
    $name = (string)$name;
    $name = str_replace(['\\', '/'], '_', $name);
    $name = basename($name);
    $name = preg_replace('/[^\p{L}\p{N}._\- ]+/u', '_', $name);
    $name = preg_replace('/_{2,}/', '_', $name);
    $name = trim($name, '._ ');
    if ($name === '' || $name === false) $name = $fallback;
    if (mb_strlen($name) > 90) {
        $ext  = pathinfo($name, PATHINFO_EXTENSION);
        $base = pathinfo($name, PATHINFO_FILENAME);
        $name = mb_substr($base, 0, 70) . ($ext !== '' ? '.' . $ext : '');
    }
    return $name;
}

function unique_filename($dir, $filename) {
    $ext  = pathinfo($filename, PATHINFO_EXTENSION);
    $base = pathinfo($filename, PATHINFO_FILENAME);
    $candidate = $filename;
    $i = 1;
    while (file_exists($dir . '/' . $candidate)) {
        $i++;
        $candidate = $base . '_' . $i . ($ext !== '' ? '.' . $ext : '');
        if ($i > 999) {
            $candidate = $base . '_' . bin2hex(random_bytes(4)) . ($ext !== '' ? '.' . $ext : '');
            break;
        }
    }
    return $candidate;
}

function normalize_card($c) {
    return [
        'id'       => isset($c['id']) ? (string)$c['id'] : '',
        'type'     => (($c['type'] ?? 'link') === 'image') ? 'image' : 'link',
        'category' => isset($c['category']) ? (string)$c['category'] : '',
        'name'     => isset($c['name']) ? trim((string)$c['name']) : '',
        'sub'      => isset($c['sub']) ? (string)$c['sub'] : '',
        'icon'     => isset($c['icon']) ? (string)$c['icon'] : '',
        'href'     => isset($c['href']) ? trim((string)$c['href']) : '',
        'image'    => isset($c['image']) ? (string)$c['image'] : '',
    ];
}

function normalize_effect($e) {
    $name     = isset($e['name']) ? trim((string)$e['name']) : '';
    $matchKey = isset($e['matchKey']) ? trim((string)$e['matchKey']) : '';
    $file     = isset($e['file']) ? trim((string)$e['file']) : '';
    if ($matchKey === '') $matchKey = $name;
    if ($name === '') {
        if (is_remote_url($file)) {
            $p = parse_url($file, PHP_URL_PATH);
            $name = pathinfo($p ?: '', PATHINFO_FILENAME);
        } else {
            $name = pathinfo($file, PATHINFO_FILENAME);
        }
    }
    return [
        'id'         => isset($e['id']) ? (string)$e['id'] : '',
        'matchKey'   => $matchKey,
        'name'       => $name,
        'file'       => $file,
        'icon'       => isset($e['icon']) ? trim((string)$e['icon']) : '✨',
        'enabled'    => !empty($e['enabled']),
        'autoToggle' => !empty($e['autoToggle']),
    ];
}

function normalize_girl($g) {
    return [
        'file' => isset($g['file']) ? trim((string)$g['file']) : '',
        'name' => isset($g['name']) ? trim((string)$g['name']) : '',
    ];
}

function normalize_local_images($arr) {
    if (!is_array($arr)) return [];
    $out = [];
    foreach ($arr as $item) {
        if (!is_array($item)) continue;
        $file = isset($item['file']) ? trim((string)$item['file']) : '';
        if ($file === '') continue;
        $entry = [
            'file' => $file,
            'name' => isset($item['name']) && trim((string)$item['name']) !== ''
                ? trim((string)$item['name'])
                : pathinfo($file, PATHINFO_FILENAME),
        ];
        if (isset($item['fp']) && is_string($item['fp']) && $item['fp'] !== '') {
            $entry['fp'] = $item['fp'];
        }
        $t = isset($item['target']) ? strtolower(trim((string)$item['target'])) : '';
        if (!in_array($t, ['desktop', 'mobile', 'both'], true)) $t = 'both';
        $entry['target'] = $t;
        $out[] = $entry;
    }
    return $out;
}

function normalize_ua_templates($arr) {
    $out = [];
    if (is_array($arr)) {
        foreach ($arr as $t) {
            if (!is_array($t)) continue;
            $ua = trim((string)($t['ua'] ?? ''));
            if ($ua === '') continue;
            $id = isset($t['id']) && trim((string)$t['id']) !== ''
                ? trim((string)$t['id'])
                : uniqid('ua_');
            $out[] = [
                'id'             => $id,
                'name'           => isset($t['name']) && trim((string)$t['name']) !== ''
                    ? trim((string)$t['name']) : '未命名模板',
                'ua'             => $ua,
                'accept'         => trim((string)($t['accept'] ?? '')),
                'acceptLanguage' => trim((string)($t['acceptLanguage'] ?? '')),
                'acceptEncoding' => trim((string)($t['acceptEncoding'] ?? '')),
                'extraHeaders'   => (string)($t['extraHeaders'] ?? ''),
                'builtin'        => !empty($t['builtin']),
            ];
        }
    }
    $hasMobile  = false;
    $hasDesktop = false;
    foreach ($out as $t) {
        if ($t['id'] === 'ua_mobile_default')  $hasMobile  = true;
        if ($t['id'] === 'ua_desktop_default') $hasDesktop = true;
    }
    foreach (ua_builtin_templates() as $tpl) {
        if ($tpl['id'] === 'ua_mobile_default'  && $hasMobile)  continue;
        if ($tpl['id'] === 'ua_desktop_default' && $hasDesktop) continue;
        $out[] = $tpl;
    }
    return $out;
}

function find_ua_template($templates, $id) {
    if (!is_array($templates) || $id === '') return null;
    foreach ($templates as $t) {
        if (($t['id'] ?? '') === $id) return $t;
    }
    return null;
}

function build_fetch_ctx($type, $data) {
    $uaKey = ($type === 'bg') ? 'localBgUaId' : 'localBannerUaId';
    $uaId  = trim((string)($data['site'][$uaKey] ?? ''));
    $templates = $data['uaTemplates'] ?? [];

    $tpl = find_ua_template($templates, $uaId);
    if (!$tpl) {
        $fallbackId = ($type === 'bg') ? 'ua_mobile_default' : 'ua_desktop_default';
        $tpl = find_ua_template($templates, $fallbackId);
    }
    if (!$tpl) {
        $tpl = [
            'ua'             => 'Mozilla/5.0 (compatible; NuomiAdmin/1.0)',
            'accept'         => '',
            'acceptLanguage' => '',
            'acceptEncoding' => '',
            'extraHeaders'   => '',
        ];
    }

    $headers = [];
    if (!empty($tpl['accept']))         $headers[] = 'Accept: '         . $tpl['accept'];
    if (!empty($tpl['acceptLanguage'])) $headers[] = 'Accept-Language: '. $tpl['acceptLanguage'];
    if (!empty($tpl['acceptEncoding'])) $headers[] = 'Accept-Encoding: '. $tpl['acceptEncoding'];
    if (!empty($tpl['extraHeaders'])) {
        foreach (preg_split('/\r\n|\r|\n/', $tpl['extraHeaders']) as $line) {
            $line = trim($line);
            if ($line !== '' && strpos($line, ':') !== false) $headers[] = $line;
        }
    }

    return stream_context_create([
        'http'  => ['timeout' => 15, 'user_agent' => $tpl['ua'], 'header' => $headers],
        'https' => ['timeout' => 15, 'user_agent' => $tpl['ua'], 'header' => $headers],
    ]);
}

function normalize_data($data) {
    if (!is_array($data)) $data = [];
    if (!isset($data['categories']) || !is_array($data['categories'])) $data['categories'] = [];
    if (!isset($data['cards']) || !is_array($data['cards'])) $data['cards'] = [];

    $temp = [];
    foreach ($data['categories'] as $cat) {
        if (is_string($cat)) {
            $temp[] = ['name' => trim($cat), 'icon' => ''];
        } elseif (is_array($cat) && isset($cat['name'])) {
            $temp[] = [
                'name' => trim((string)$cat['name']),
                'icon' => isset($cat['icon']) ? trim($cat['icon']) : ''
            ];
        }
    }
    $data['categories'] = $temp;

    $cards = [];
    foreach ($data['cards'] as $c) {
        if (!is_array($c)) continue;
        $cards[] = normalize_card($c);
    }
    $data['cards'] = $cards;

    if (!isset($data['jsEffects']) || !is_array($data['jsEffects'])) $data['jsEffects'] = [];
    $effs = [];
    foreach ($data['jsEffects'] as $e) {
        if (!is_array($e)) continue;
        $n = normalize_effect($e);
        if ($n['file'] !== '') {
            if ($n['id'] === '') $n['id'] = uniqid('eff_');
            $effs[] = $n;
        }
    }
    $data['jsEffects'] = $effs;

    if (!isset($data['boardGirl']) || !is_array($data['boardGirl'])) {
        $data['boardGirl'] = ['enabled' => true, 'girls' => []];
    }
    if (!isset($data['boardGirl']['girls']) || !is_array($data['boardGirl']['girls'])) {
        $data['boardGirl']['girls'] = [];
    }
    $girls = [];
    foreach ($data['boardGirl']['girls'] as $g) {
        if (!is_array($g)) continue;
        $n = normalize_girl($g);
        if ($n['file'] !== '') $girls[] = $n;
    }
    $data['boardGirl']['girls'] = $girls;
    $data['boardGirl']['enabled'] = !empty($data['boardGirl']['enabled']);

    if (!isset($data['photoOrder']) || !is_array($data['photoOrder'])) $data['photoOrder'] = [];
    $po = [];
    foreach ($data['photoOrder'] as $p) {
        if (is_string($p) && $p !== '') $po[] = $p;
    }
    $data['photoOrder'] = $po;

    if (!isset($data['preloaders']) || !is_array($data['preloaders'])) {
        $data['preloaders'] = [];
    }
    $pres = [];
    foreach ($data['preloaders'] as $p) {
        if (!is_array($p)) continue;
        $file = isset($p['file']) ? trim((string)$p['file']) : '';
        if ($file === '') continue;
        $key = isset($p['key']) && trim((string)$p['key']) !== ''
            ? trim((string)$p['key'])
            : preg_replace('/\.css$/i', '', basename($file));
        $key = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $key);
        if ($key === '') continue;
        $pres[] = [
            'key'         => $key,
            'name'        => isset($p['name']) ? trim((string)$p['name']) : $key,
            'description' => isset($p['description']) ? trim((string)$p['description']) : '',
            'file'        => $file,
            'enabled'     => !empty($p['enabled']),
        ];
    }
    $data['preloaders'] = $pres;

    if (!isset($data['site']) || !is_array($data['site'])) $data['site'] = [];
    foreach (site_defaults() as $k => $v) {
        if (!array_key_exists($k, $data['site'])) $data['site'][$k] = $v;
    }

    if (!isset($data['tips']) || !is_array($data['tips'])) {
        $data['tips'] = tips_defaults();
    } else {
        $tipsDef = tips_defaults();
        $data['tips']['enabled'] = !empty($data['tips']['enabled']);

        $dur = isset($data['tips']['duration']) ? (int)$data['tips']['duration'] : $tipsDef['duration'];
        if ($dur < 500)   $dur = 500;
        if ($dur > 60000) $dur = 60000;
        $data['tips']['duration'] = $dur;

        if (!isset($data['tips']['texts']) || !is_array($data['tips']['texts'])) {
            $data['tips']['texts'] = $tipsDef['texts'];
        } else {
            $clean = [];
            foreach ($data['tips']['texts'] as $t) {
                if (!is_string($t)) continue;
                $t = trim($t);
                if ($t !== '') $clean[] = $t;
            }
            $data['tips']['texts'] = count($clean) > 0 ? $clean : $tipsDef['texts'];
        }

        if (!isset($data['tips']['periodTexts']) || !is_array($data['tips']['periodTexts'])) {
            $data['tips']['periodTexts'] = $tipsDef['periodTexts'];
        } else {
            foreach (['night','morning','noon','afternoon','evening'] as $p) {
                $arr = $data['tips']['periodTexts'][$p] ?? null;
                if (!is_array($arr)) {
                    $data['tips']['periodTexts'][$p] = $tipsDef['periodTexts'][$p];
                    continue;
                }
                $clean = [];
                foreach ($arr as $t) {
                    if (!is_string($t)) continue;
                    $t = trim($t);
                    if ($t !== '') $clean[] = $t;
                }
                $data['tips']['periodTexts'][$p] = count($clean) > 0 ? $clean : $tipsDef['periodTexts'][$p];
            }
        }
        $data['tips']['api']      = isset($data['tips']['api']) ? trim((string)$data['tips']['api']) : '';
        $data['tips']['apiField'] = isset($data['tips']['apiField']) && trim((string)$data['tips']['apiField']) !== ''
            ? trim((string)$data['tips']['apiField']) : 'text';
    }

    if (!isset($data['localBg']) || !is_array($data['localBg'])) {
        $data['localBg'] = ['enabled' => true, 'images' => []];
    }
    $data['localBg']['enabled'] = !empty($data['localBg']['enabled']);
    $data['localBg']['images']  = normalize_local_images($data['localBg']['images'] ?? []);

    if (!isset($data['localBanner']) || !is_array($data['localBanner'])) {
        $data['localBanner'] = ['enabled' => true, 'images' => []];
    }
    $data['localBanner']['enabled'] = !empty($data['localBanner']['enabled']);
    $data['localBanner']['images']  = normalize_local_images($data['localBanner']['images'] ?? []);

    $data['uaTemplates'] = normalize_ua_templates($data['uaTemplates'] ?? []);

    $uaIds = array_column($data['uaTemplates'], 'id');
    if (!in_array($data['site']['localBgUaId'] ?? '', $uaIds, true)) {
        $data['site']['localBgUaId'] = 'ua_mobile_default';
    }
    if (!in_array($data['site']['localBannerUaId'] ?? '', $uaIds, true)) {
        $data['site']['localBannerUaId'] = 'ua_desktop_default';
    }

    /* ---------- 公告 ---------- */
    if (!isset($data['announcements']) || !is_array($data['announcements'])) {
        $data['announcements'] = [];
    }
    $annList = [];
    $validTypes = ['info', 'success', 'warning', 'error'];
    foreach ($data['announcements'] as $a) {
        if (!is_array($a)) continue;
        $id = isset($a['id']) && trim((string)$a['id']) !== ''
            ? trim((string)$a['id'])
            : uniqid('ann_');
        $title = isset($a['title']) ? trim((string)$a['title']) : '';
        if ($title === '') continue;

        $type = isset($a['type']) ? strtolower(trim((string)$a['type'])) : 'info';
        if (!in_array($type, $validTypes, true)) $type = 'info';

        $annList[] = [
            'id'      => $id,
            'title'   => $title,
            'content' => isset($a['content']) ? (string)$a['content'] : '',
            'type'    => $type,
            'pinned'  => !empty($a['pinned']),
            'enabled' => !empty($a['enabled']),
            'created' => isset($a['created']) && $a['created'] !== ''
                ? (string)$a['created']
                : date('Y-m-d H:i:s'),
            'updated' => isset($a['updated']) && $a['updated'] !== ''
                ? (string)$a['updated']
                : (isset($a['created']) && $a['created'] !== '' ? (string)$a['created'] : date('Y-m-d H:i:s')),
        ];
    }
    $data['announcements'] = $annList;

    return $data;
}

/* ==========================================================================
   4. 数据读写与文件处理
   ========================================================================== */
function load_data() {
    if (!file_exists(DATA_FILE)) {
        $default = factory_defaults();
        $default = normalize_data($default);
        $json = json_encode($default, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            http_response_code(500);
            echo '初始化 data.json JSON编码失败：' . json_last_error_msg();
            exit;
        }
        if (file_put_contents(DATA_FILE, $json, LOCK_EX) === false) {
            http_response_code(500);
            echo '无法创建 admin/data.json，请检查admin目录写权限';
            exit;
        }
        return $default;
    }
    $raw = @file_get_contents(DATA_FILE);
    if ($raw === false) {
        http_response_code(500);
        echo '读取 admin/data.json 失败，请检查权限';
        exit;
    }
    $data = json_decode($raw, true);
    if ($data === null || !is_array($data)) {
        http_response_code(500);
        echo '解析 data.json 失败：' . json_last_error_msg();
        exit;
    }
    return normalize_data($data);
}

function save_data($data) {
    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    if ($json === false) {
        http_response_code(500);
        echo '保存data.json JSON编码失败：' . json_last_error_msg();
        exit;
    }
    $tmp = DATA_FILE . '.tmp.' . getmypid();
    if (@file_put_contents($tmp, $json, LOCK_EX) === false) {
        http_response_code(500);
        echo '写入临时文件失败，请检查admin目录权限';
        exit;
    }
    if (!@rename($tmp, DATA_FILE)) {
        @unlink($tmp);
        http_response_code(500);
        echo 'rename保存data.json失败';
        exit;
    }
    return true;
}

function read_conf() {
    if (!file_exists(ADMIN_CONF)) return null;
    $raw = @file_get_contents(ADMIN_CONF);
    if ($raw === false) return null;
    $raw = preg_replace('/^<\?php\s+exit;\s*\?>\s*/', '', $raw);
    $data = json_decode($raw, true);
    return is_array($data) ? $data : null;
}

function write_conf($data) {
    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    if ($json === false) {
        http_response_code(500);
        echo '账号配置JSON编码失败：' . json_last_error_msg();
        exit;
    }
    $content = "<?php exit; ?>\n" . $json;
    if (@file_put_contents(ADMIN_CONF, $content, LOCK_EX) === false) {
        http_response_code(500);
        echo '写入 .admin.conf 失败，请检查权限';
        exit;
    }
    return true;
}

function has_conf() { return file_exists(ADMIN_CONF); }
function is_login() { return !empty($_SESSION['nuomi_admin_login']); }
function require_login() { if (!is_login()) respond(false, '未登录或会话已过期，请重新登录'); }

/* ==========================================================================
   缩略图工具
   ========================================================================== */

function thumb_path_for($originalRelPath) {
    $p = (string)$originalRelPath;
    if ($p === '') return '';

    if (strpos($p, 'photos/kanban/') === 0) {
        return 'photos/kanban_thumbs/' . substr($p, 14);
    }
    if (strpos($p, 'photos/localbg/') === 0) {
        return 'photos/localbg_thumbs/' . substr($p, 15);
    }
    if (strpos($p, 'photos/localbanner/') === 0) {
        return 'photos/localbanner_thumbs/' . substr($p, 19);
    }
    if (strpos($p, 'photos/') === 0) {
        return 'photos/thumbs/' . substr($p, 7);
    }
    return '';
}

function make_thumbnail($srcAbsPath, $thumbAbsPath, $maxSize = THUMB_MAX_SIZE) {
    if (!is_file($srcAbsPath)) return false;

    static $failedCache = [];
    if (isset($failedCache[$srcAbsPath])) return false;

    static $budget = 30;
    if ($budget <= 0) return false;

    $info = @getimagesize($srcAbsPath);
    if ($info === false) { $failedCache[$srcAbsPath] = true; return false; }
    list($w, $h, $type) = $info;
    if ($w <= 0 || $h <= 0) { $failedCache[$srcAbsPath] = true; return false; }

    $ratio = min($maxSize / $w, $maxSize / $h, 1);
    $nw = max(1, (int)round($w * $ratio));
    $nh = max(1, (int)round($h * $ratio));

    $thumbDir = dirname($thumbAbsPath);
    if (!is_dir($thumbDir)) @mkdir($thumbDir, 0755, true);

    if (class_exists('Imagick')) {
        $im = null;
        try {
            $im = new Imagick();
            $im->setResourceLimit(Imagick::RESOURCETYPE_MEMORY, 256 * 1024 * 1024);
            $im->setResourceLimit(Imagick::RESOURCETYPE_MAP,    512 * 1024 * 1024);
            $im->setResourceLimit(Imagick::RESOURCETYPE_DISK,   1 * 1024 * 1024 * 1024);

            $im->setBackgroundColor(new ImagickPixel('none'));
            $im->readImage($srcAbsPath);
            $im->setImageBackgroundColor(new ImagickPixel('none'));

            if ($type === IMAGETYPE_GIF) {
                $coalesced = $im->coalesceImages();
                $im->clear();
                $im->destroy();

                $coalesced->setIteratorIndex(0);
                $frame = $coalesced->getImage();
                $coalesced->clear();
                $coalesced->destroy();

                $frame->setImagePage(0, 0, 0, 0);
                $frame->setImageBackgroundColor(new ImagickPixel('none'));

                $im = $frame;
            }

            $im->thumbnailImage($nw, $nh, true);

            $ok = false;
            switch ($type) {
                case IMAGETYPE_JPEG:
                    $canvas = new Imagick();
                    $canvas->newImage($nw, $nh, new ImagickPixel('white'));
                    $canvas->setImageFormat('jpeg');
                    $canvas->compositeImage($im, Imagick::COMPOSITE_OVER, 0, 0);
                    $canvas->setImageCompressionQuality(85);
                    $ok = $canvas->writeImage($thumbAbsPath);
                    $canvas->clear();
                    $canvas->destroy();
                    break;
                case IMAGETYPE_PNG:
                    $im->setImageFormat('png');
                    $im->setOption('png:compression-level', '8');
                    $ok = $im->writeImage($thumbAbsPath);
                    break;
                case IMAGETYPE_GIF:
                    $im->setImageFormat('gif');
                    $ok = $im->writeImage($thumbAbsPath);
                    break;
                case IMAGETYPE_WEBP:
                    $im->setImageFormat('webp');
                    $im->setImageCompressionQuality(85);
                    $ok = $im->writeImage($thumbAbsPath);
                    break;
            }

            $im->clear();
            $im->destroy();
            $im = null;

            if ($ok) {
                @chmod($thumbAbsPath, 0644);
                $budget--;
                return true;
            }
        } catch (Exception $e) {
            if ($im instanceof Imagick) {
                try { $im->clear(); $im->destroy(); } catch (Exception $e2) {}
            }
        }
    }

    if (!function_exists('imagecreatetruecolor')) { $failedCache[$srcAbsPath] = true; return false; }

    $channels = ($type === IMAGETYPE_JPEG || $type === IMAGETYPE_GIF) ? 3 : 4;
    $needBytes = $w * $h * $channels;
    $limitStr = (string)ini_get('memory_limit');
    $limitBytes = -1;
    if ($limitStr !== '' && $limitStr !== '-1') {
        $last = strtolower($limitStr[strlen($limitStr) - 1]);
        $num = (int)$limitStr;
        switch ($last) {
            case 'g': $num *= 1024 * 1024 * 1024; break;
            case 'm': $num *= 1024 * 1024; break;
            case 'k': $num *= 1024; break;
        }
        $limitBytes = $num;
    }
    if ($limitBytes > 0) {
        $used = memory_get_usage(true);
        if ($needBytes > ($limitBytes - $used) * 0.6) {
            return false;
        }
    }

    $srcImg = null;
    switch ($type) {
        case IMAGETYPE_JPEG: $srcImg = @imagecreatefromjpeg($srcAbsPath); break;
        case IMAGETYPE_PNG:  $srcImg = @imagecreatefrompng($srcAbsPath);  break;
        case IMAGETYPE_GIF:  $srcImg = @imagecreatefromgif($srcAbsPath);  break;
        case IMAGETYPE_WEBP:
            if (function_exists('imagecreatefromwebp')) $srcImg = @imagecreatefromwebp($srcAbsPath);
            break;
    }
    if (!$srcImg) { $failedCache[$srcAbsPath] = true; return false; }

    $dstImg = imagecreatetruecolor($nw, $nh);

    if ($type === IMAGETYPE_PNG || $type === IMAGETYPE_GIF || $type === IMAGETYPE_WEBP) {
        imagealphablending($dstImg, false);
        imagesavealpha($dstImg, true);
        $transparent = imagecolorallocatealpha($dstImg, 0, 0, 0, 127);
        imagefilledrectangle($dstImg, 0, 0, $nw, $nh, $transparent);
    } else {
        $white = imagecolorallocate($dstImg, 255, 255, 255);
        imagefilledrectangle($dstImg, 0, 0, $nw, $nh, $white);
    }

    imagecopyresampled($dstImg, $srcImg, 0, 0, 0, 0, $nw, $nh, $w, $h);

    $ok = false;
    switch ($type) {
        case IMAGETYPE_JPEG:
            $ok = @imagejpeg($dstImg, $thumbAbsPath, 85);
            break;
        case IMAGETYPE_PNG:
            $ok = @imagepng($dstImg, $thumbAbsPath, 8);
            break;
        case IMAGETYPE_GIF:
            $transparentIdx = imagecolorallocatealpha($dstImg, 0, 0, 0, 127);
            imagecolortransparent($dstImg, $transparentIdx);
            $ok = @imagegif($dstImg, $thumbAbsPath);
            break;
        case IMAGETYPE_WEBP:
            if (function_exists('imagewebp')) $ok = @imagewebp($dstImg, $thumbAbsPath, 85);
            break;
    }

    imagedestroy($srcImg);
    imagedestroy($dstImg);

    if ($ok) {
        @chmod($thumbAbsPath, 0644);
        $budget--;
        return true;
    }
    $failedCache[$srcAbsPath] = true;
    return false;
}

function decorate_images($items) {
    if (!is_array($items)) return [];
    foreach ($items as &$it) {
        if (!is_array($it) || empty($it['file'])) continue;
        $thumbRel = thumb_path_for($it['file']);
        if ($thumbRel === '') { $it['thumb'] = $it['file']; continue; }

        $thumbAbs = __DIR__ . '/../' . $thumbRel;
        $srcAbs   = __DIR__ . '/../' . $it['file'];

        if (!is_file($thumbAbs) && is_file($srcAbs)) {
            make_thumbnail($srcAbs, $thumbAbs, THUMB_MAX_SIZE);
        }

        $it['thumb'] = is_file($thumbAbs) ? $thumbRel : $it['file'];
    }
    unset($it);
    return $items;
}

function delete_with_thumb($absPath, $relPath) {
    if (is_file($absPath)) @unlink($absPath);
    $thumbRel = thumb_path_for($relPath);
    if ($thumbRel !== '') {
        $thumbAbs = __DIR__ . '/../' . $thumbRel;
        if (is_file($thumbAbs)) @unlink($thumbAbs);
    }
}

function rename_with_thumb($oldAbs, $newAbs, $oldRel, $newRel) {
    if (!@rename($oldAbs, $newAbs)) return false;
    @chmod($newAbs, 0644);

    $oldThumbRel = thumb_path_for($oldRel);
    $newThumbRel = thumb_path_for($newRel);
    if ($oldThumbRel !== '' && $newThumbRel !== '') {
        $oldThumbAbs = __DIR__ . '/../' . $oldThumbRel;
        $newThumbAbs = __DIR__ . '/../' . $newThumbRel;
        if (is_file($oldThumbAbs)) {
            $newThumbDir = dirname($newThumbAbs);
            if (!is_dir($newThumbDir)) @mkdir($newThumbDir, 0755, true);
            @rename($oldThumbAbs, $newThumbAbs);
            @chmod($newThumbAbs, 0644);
        }
    }
    return true;
}

/* ==========================================================================
   5. 目录扫描与数据同步
   ========================================================================== */
function list_photos($data = null) {
    $files = [];
    if (is_dir(PHOTO_DIR)) {
        $items = @scandir(PHOTO_DIR);
        if ($items !== false) {
            foreach ($items as $f) {
                if ($f === '.' || $f === '..' || $f[0] === '.') continue;
                $full = PHOTO_DIR . '/' . $f;
                if (is_dir($full)) continue;
                $ext = strtolower(pathinfo($f, PATHINFO_EXTENSION));
                if (in_array($ext, ALLOWED_EXT, true)) $files['photos/' . $f] = true;
            }
        }
    }

    $order = null;
    if ($data !== null && isset($data['photoOrder']) && is_array($data['photoOrder'])) $order = $data['photoOrder'];

    $orderedPaths = [];
    if ($order === null) {
        $orderedPaths = array_keys($files);
        sort($orderedPaths, SORT_STRING);
    } else {
        $seen = [];
        foreach ($order as $p) {
            if (is_string($p) && isset($files[$p]) && !isset($seen[$p])) {
                $orderedPaths[] = $p; $seen[$p] = true;
            }
        }
        $rest = array_keys($files); sort($rest, SORT_STRING);
        foreach ($rest as $p) { if (!isset($seen[$p])) $orderedPaths[] = $p; }
    }

    $out = [];
    foreach ($orderedPaths as $p) {
        $thumbRel = thumb_path_for($p);
        $thumbAbs = $thumbRel !== '' ? __DIR__ . '/../' . $thumbRel : '';
        $srcAbs   = __DIR__ . '/../' . $p;
        if ($thumbRel !== '' && !is_file($thumbAbs) && is_file($srcAbs)) {
            make_thumbnail($srcAbs, $thumbAbs, THUMB_MAX_SIZE);
        }
        $out[] = [
            'file'  => $p,
            'thumb' => ($thumbRel !== '' && is_file($thumbAbs)) ? $thumbRel : $p,
        ];
    }
    return $out;
}

function list_js_files() {
    $out = [];
    if (!is_dir(JS_DIR)) return $out;
    $items = @scandir(JS_DIR);
    if ($items === false) return $out;
    foreach ($items as $f) {
        if ($f === '.' || $f === '..' || $f[0] === '.') continue;
        if (strtolower(pathinfo($f, PATHINFO_EXTENSION)) === 'js') $out[] = 'assets/js/' . $f;
    }
    sort($out, SORT_STRING);
    return $out;
}

function list_girl_files() {
    $out = [];
    if (!is_dir(GIRL_DIR)) @mkdir(GIRL_DIR, 0755, true);
    $items = @scandir(GIRL_DIR);
    if ($items === false) return $out;
    foreach ($items as $f) {
        if ($f === '.' || $f === '..' || $f[0] === '.') continue;
        $ext = strtolower(pathinfo($f, PATHINFO_EXTENSION));
        if (in_array($ext, ALLOWED_GIF_EXT, true)) $out[] = 'photos/kanban/' . $f;
    }
    sort($out, SORT_STRING);
    return $out;
}

function list_preloader_files() {
    $out = [];
    if (!is_dir(PRELOADER_DIR)) return $out;
    $items = @scandir(PRELOADER_DIR);
    if ($items === false) return $out;
    foreach ($items as $f) {
        if ($f === '.' || $f === '..' || $f[0] === '.') continue;
        if (strtolower(pathinfo($f, PATHINFO_EXTENSION)) === 'css') $out[] = 'assets/preloaders/' . $f;
    }
    sort($out, SORT_STRING);
    return $out;
}

function extract_effect_matchkey($content, $fallback = '') {
    if (!is_string($content) || $content === '') return $fallback;

    if (preg_match('/EffectsRegistry\s*\.\s*push\s*\(\s*\{.*?name\s*:\s*[\'"`]([^\'"`]+)[\'"`]/is', $content, $m)) {
        return trim($m[1]);
    }

    if (preg_match('/EffectsRegistry\s*\.\s*push\s*\(\s*\{.*?name\s*:\s*([A-Za-z_$][\w$]*)/is', $content, $m)) {
        $varName = $m[1];
        $pattern = '/(?:var|let|const)\s+' . preg_quote($varName, '/') . '\s*=\s*[\'"`]([^\'"`]+)[\'"`]/';
        if (preg_match($pattern, $content, $m2)) {
            return trim($m2[1]);
        }
    }

    if (preg_match('/\bname\s*:\s*[\'"`]([^\'"`]+)[\'"`]/i', $content, $m)) {
        return trim($m[1]);
    }

    return $fallback;
}

function detect_effect_autotoggle($content) {
    if (!is_string($content) || $content === '') return false;
    if (!preg_match('/\bmodule\b/i', $content)) return false;
    $hasStart = (bool)preg_match('/\bstart\s*[:=(]/i', $content);
    $hasStop  = (bool)preg_match('/\bstop\s*[:=(]/i', $content);
    return $hasStart && $hasStop;
}

function extract_preloader_meta($content, $fallbackName) {
    $name = $fallbackName;
    $desc = '';
    if (preg_match('/@name\s+([^\r\n*]+)/i', (string)$content, $m)) {
        $name = trim($m[1]);
    }
    if (preg_match('/@description\s+([^\r\n*]+)/i', (string)$content, $m)) {
        $desc = trim($m[1]);
    }
    return ['name' => $name, 'description' => $desc];
}

function sync_js_effects(&$data) {
    if (!is_dir(JS_DIR)) @mkdir(JS_DIR, 0755, true);
    $diskFiles = list_js_files();
    $diskMap = array_flip($diskFiles);
    if (!isset($data['jsEffects']) || !is_array($data['jsEffects'])) $data['jsEffects'] = [];

    $newEffs = []; $seenLocal = [];
    foreach ($data['jsEffects'] as $e) {
        if (!is_array($e)) continue;
        $f = isset($e['file']) ? trim((string)$e['file']) : '';
        if ($f === '') continue;
        if (is_remote_url($f)) {
            if (!isset($e['matchKey']) || trim((string)$e['matchKey']) === '') { $p = parse_url($f, PHP_URL_PATH); $e['matchKey'] = pathinfo($p ?: '', PATHINFO_FILENAME); }
            if (!isset($e['name']) || trim((string)$e['name']) === '') { $p = parse_url($f, PHP_URL_PATH); $e['name'] = pathinfo($p ?: '', PATHINFO_FILENAME); }
            if (!isset($e['autoToggle'])) $e['autoToggle'] = false;
            $newEffs[] = $e; continue;
        }
        if (isset($diskMap[$f])) {
            $full = __DIR__ . '/../' . $f;
            $content = @file_get_contents($full);

            if (!isset($e['matchKey']) || trim((string)$e['matchKey']) === '') {
                $extracted = extract_effect_matchkey($content === false ? '' : $content, '');
                $e['matchKey'] = $extracted !== '' ? $extracted : pathinfo($f, PATHINFO_FILENAME);
            }

            if (!isset($e['name']) || trim((string)$e['name']) === '') $e['name'] = pathinfo($f, PATHINFO_FILENAME);
            $e['autoToggle'] = detect_effect_autotoggle($content === false ? '' : $content);
            $newEffs[] = $e; $seenLocal[$f] = true;
        }
    }
    foreach ($diskFiles as $f) {
        if (isset($seenLocal[$f])) continue;
        $full = __DIR__ . '/../' . $f; $content = @file_get_contents($full); $fallback = pathinfo($f, PATHINFO_FILENAME);
        $matchKey = extract_effect_matchkey($content === false ? '' : $content, $fallback);
        $newEffs[] = [
            'id' => uniqid('eff_'), 'matchKey' => $matchKey, 'name' => $fallback,
            'file' => $f, 'icon' => '✨', 'enabled' => true,
            'autoToggle' => detect_effect_autotoggle($content === false ? '' : $content),
        ];
    }
    $changed = false;
    if (count($newEffs) !== count($data['jsEffects'])) { $changed = true; }
    else {
        foreach ($newEffs as $i => $e) {
            if (!isset($data['jsEffects'][$i])
                || ($data['jsEffects'][$i]['file'] ?? '') !== ($e['file'] ?? '')
                || ($data['jsEffects'][$i]['matchKey'] ?? '') !== ($e['matchKey'] ?? '')
                || ($data['jsEffects'][$i]['name'] ?? '') !== ($e['name'] ?? '')
                || (bool)($data['jsEffects'][$i]['autoToggle'] ?? false) !== (bool)($e['autoToggle'] ?? false)) {
                $changed = true; break;
            }
        }
    }
    if ($changed) $data['jsEffects'] = $newEffs;
    return $changed;
}

function sync_board_girls(&$data) {
    if (!is_dir(GIRL_DIR)) @mkdir(GIRL_DIR, 0755, true);
    $files = list_girl_files(); $fileMap = array_flip($files);
    if (!isset($data['boardGirl']) || !is_array($data['boardGirl'])) $data['boardGirl'] = ['enabled' => true, 'girls' => []];
    if (!isset($data['boardGirl']['girls']) || !is_array($data['boardGirl']['girls'])) $data['boardGirl']['girls'] = [];

    $newGirls = []; $seen = [];
    foreach ($data['boardGirl']['girls'] as $g) {
        if (!is_array($g)) continue;
        $f = isset($g['file']) ? trim((string)$g['file']) : '';
        if ($f === '') continue;
        if (isset($fileMap[$f])) { $newGirls[] = $g; $seen[$f] = true; }
    }
    foreach ($files as $f) {
        if (isset($seen[$f])) continue;
        $newGirls[] = ['file' => $f, 'name' => pathinfo($f, PATHINFO_FILENAME)];
    }
    $changed = false;
    if (count($newGirls) !== count($data['boardGirl']['girls'])) { $changed = true; }
    else {
        foreach ($newGirls as $i => $g) {
            if (!isset($data['boardGirl']['girls'][$i]) || ($data['boardGirl']['girls'][$i]['file'] ?? '') !== ($g['file'] ?? '')) { $changed = true; break; }
        }
    }
    if ($changed) $data['boardGirl']['girls'] = $newGirls;

    foreach ($data['boardGirl']['girls'] as $g) {
        if (empty($g['file'])) continue;
        $thumbRel = thumb_path_for($g['file']);
        if ($thumbRel === '') continue;
        $thumbAbs = __DIR__ . '/../' . $thumbRel;
        $srcAbs   = __DIR__ . '/../' . $g['file'];
        if (!is_file($thumbAbs) && is_file($srcAbs)) {
            make_thumbnail($srcAbs, $thumbAbs, THUMB_MAX_SIZE);
        }
    }
    return $changed;
}

function sync_preloaders(&$data) {
    if (!is_dir(PRELOADER_DIR)) @mkdir(PRELOADER_DIR, 0755, true);
    $diskFiles = list_preloader_files();
    $diskMap = array_flip($diskFiles);
    if (!isset($data['preloaders']) || !is_array($data['preloaders'])) $data['preloaders'] = [];

    $newList = [];
    $seen = [];
    foreach ($data['preloaders'] as $p) {
        if (!is_array($p)) continue;
        $file = $p['file'] ?? '';
        if ($file === '' || !isset($diskMap[$file])) continue;
        $key = preg_replace('/\.css$/i', '', basename($file));
        $key = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $key);
        if ($key === '' || isset($seen[$key])) continue;
        $seen[$key] = true;

        $full = __DIR__ . '/../' . $file;
        $content = is_file($full) ? @file_get_contents($full) : '';
        $meta = extract_preloader_meta($content === false ? '' : $content, $key);

        $name = isset($p['name']) && trim((string)$p['name']) !== '' ? trim((string)$p['name']) : $meta['name'];
        $desc = isset($p['description']) && trim((string)$p['description']) !== '' ? trim((string)$p['description']) : $meta['description'];

        $newList[] = [
            'key'         => $key,
            'name'        => $name,
            'description' => $desc,
            'file'        => $file,
            'enabled'     => !empty($p['enabled']),
        ];
    }

    foreach ($diskFiles as $f) {
        $key = preg_replace('/\.css$/i', '', basename($f));
        $key = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $key);
        if ($key === '' || isset($seen[$key])) continue;
        $seen[$key] = true;

        $full = __DIR__ . '/../' . $f;
        $content = @file_get_contents($full);
        $meta = extract_preloader_meta($content === false ? '' : $content, $key);

        $newList[] = [
            'key'         => $key,
            'name'        => $meta['name'],
            'description' => $meta['description'],
            'file'        => $f,
            'enabled'     => true,
        ];
    }

    $changed = false;
    if (count($newList) !== count($data['preloaders'])) $changed = true;
    else {
        foreach ($newList as $i => $p) {
            if (!isset($data['preloaders'][$i])
                || ($data['preloaders'][$i]['key'] ?? '') !== ($p['key'] ?? '')
                || ($data['preloaders'][$i]['file'] ?? '') !== ($p['file'] ?? '')
                || ($data['preloaders'][$i]['name'] ?? '') !== ($p['name'] ?? '')
                || ($data['preloaders'][$i]['description'] ?? '') !== ($p['description'] ?? '')) {
                $changed = true; break;
            }
        }
    }
    if ($changed) $data['preloaders'] = $newList;
    return $changed;
}

function sync_local_images(&$data, $type, $newFileTargets = []) {
    $dir       = ($type === 'bg') ? LOCALBG_DIR : LOCALBANNER_DIR;
    $relPrefix = ($type === 'bg') ? 'photos/localbg/' : 'photos/localbanner/';
    $key       = ($type === 'bg') ? 'localBg' : 'localBanner';

    if (!is_dir($dir)) @mkdir($dir, 0755, true);

    $files = [];
    $items = @scandir($dir);
    if ($items !== false) {
        foreach ($items as $f) {
            if ($f === '.' || $f === '..' || $f[0] === '.') continue;
            $ext = strtolower(pathinfo($f, PATHINFO_EXTENSION));
            if (in_array($ext, ALLOWED_EXT, true)) $files[$relPrefix . $f] = true;
        }
    }

    if (!isset($data[$key]) || !is_array($data[$key])) $data[$key] = ['enabled' => true, 'images' => []];
    if (!isset($data[$key]['images']) || !is_array($data[$key]['images'])) $data[$key]['images'] = [];

    $newList = []; $seen = [];
    foreach ($data[$key]['images'] as $item) {
        if (!is_array($item)) continue;
        $f = isset($item['file']) ? trim((string)$item['file']) : '';
        if ($f === '' || !isset($files[$f])) continue;
        $newList[] = $item; $seen[$f] = true;
    }
    foreach (array_keys($files) as $f) {
        if (isset($seen[$f])) continue;
        
        $fullPath = __DIR__ . '/../' . $f;
        $tgt = 'both';
        $info = @getimagesize($fullPath);
        if ($info !== false && $info[0] > 0 && $info[1] > 0) {
            if ($info[0] > $info[1] * 1.1) $tgt = 'desktop';
            elseif ($info[1] > $info[0] * 1.1) $tgt = 'mobile';
        }
        if ($type === 'banner') $tgt = 'desktop';

        if (isset($newFileTargets[$f]) && in_array($newFileTargets[$f], ['desktop','mobile','both'], true)) {
            $tgt = $newFileTargets[$f];
        }

        $newList[] = [
            'file'   => $f,
            'name'   => pathinfo($f, PATHINFO_FILENAME),
            'target' => $tgt,
        ];
    }

    $changed = count($newList) !== count($data[$key]['images']);
    if (!$changed) {
        foreach ($newList as $i => $item) {
            if (!isset($data[$key]['images'][$i])
                || ($data[$key]['images'][$i]['file'] ?? '') !== ($item['file'] ?? '')) {
                $changed = true; break;
            }
        }
    }

    $fpChanged = false;
    foreach ($newList as $i => $item) {
        if (isset($item['fp']) && is_string($item['fp']) && $item['fp'] !== '') continue;
        $fullPath = __DIR__ . '/../' . $item['file'];
        $fp = compute_local_fp($fullPath);
        if ($fp !== '') {
            $newList[$i]['fp'] = $fp;
            $fpChanged = true;
        }
    }
    if ($fpChanged) $changed = true;

    if ($changed) $data[$key]['images'] = $newList;

    foreach ($newList as $item) {
        if (empty($item['file'])) continue;
        $thumbRel = thumb_path_for($item['file']);
        if ($thumbRel === '') continue;
        $thumbAbs = __DIR__ . '/../' . $thumbRel;
        $srcAbs   = __DIR__ . '/../' . $item['file'];
        if (!is_file($thumbAbs) && is_file($srcAbs)) {
            make_thumbnail($srcAbs, $thumbAbs, THUMB_MAX_SIZE);
        }
    }

    return $changed;
}

function purge_directory_files($dir) {
    if (!is_dir($dir)) return 0;
    $count = 0; $items = @scandir($dir);
    if ($items === false) return 0;
    foreach ($items as $f) {
        if ($f === '.' || $f === '..' || $f[0] === '.') continue;
        $full = $dir . '/' . $f;
        if (is_file($full) && @unlink($full)) $count++;
    }
    return $count;
}

function admin_img_src($path) {
    $p = trim((string)$path);
    if ($p === '') return '';
    if (stripos($p, 'http://') === 0 || stripos($p, 'https://') === 0) return $p;
    if (substr($p, 0, 2) === '//') return $p;
    if (substr($p, 0, 1) === '/') return $p;
    return '../' . $p;
}

function compute_local_fp($fullPath) {
    if (!is_file($fullPath)) return '';
    $fp = @md5_file($fullPath);
    return $fp === false ? '' : $fp;
}

function compute_local_fp_from_string($raw) {
    if (!is_string($raw) || $raw === '') return '';
    return md5($raw);
}

/* ==========================================================================
   5.5 在线升级工具函数
   ========================================================================== */
function update_ensure_dir() {
    if (!is_dir(UPDATE_DIR)) {
        @mkdir(UPDATE_DIR, 0755, true);
    }
    return is_dir(UPDATE_DIR);
}

function update_lock_path() {
    return UPDATE_DIR . '/update.lock';
}

function update_acquire_lock() {
    update_ensure_dir();
    $lockFile = update_lock_path();
    $fp = @fopen($lockFile, 'c');
    if (!$fp) return false;
    if (!flock($fp, LOCK_EX | LOCK_NB)) {
        fclose($fp);
        return false;
    }
    return $fp;
}

function update_release_lock($fp) {
    if ($fp) {
        @flock($fp, LOCK_UN);
        @fclose($fp);
    }
}

function update_state_path() {
    return UPDATE_DIR . '/state.json';
}

function update_atomic_write_json($filePath, $data) {
    $dir = dirname($filePath);
    if (!is_dir($dir)) @mkdir($dir, 0755, true);
    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    if ($json === false) return false;
    $tmp = $filePath . '.tmp.' . getmypid() . '.' . bin2hex(random_bytes(3));
    if (@file_put_contents($tmp, $json, LOCK_EX) === false) {
        @unlink($tmp);
        return false;
    }
    if (!@rename($tmp, $filePath)) {
        @unlink($tmp);
        return false;
    }
    @chmod($filePath, 0644);
    return true;
}

function update_read_state() {
    $file = update_state_path();
    $default = [
        'status' => 'idle',
        'version' => '',
        'message' => '',
        'started' => '',
        'finished' => '',
        'backup_dir' => '',
        'download_url' => '',
    ];
    if (!file_exists($file)) return $default;
    $raw = @file_get_contents($file);
    $data = json_decode($raw, true);
    if (!is_array($data)) return $default;

    if (($data['status'] ?? '') === 'applying') {
        $started = strtotime($data['started'] ?? '');
        if ($started !== false && (time() - $started) > 600) {
            $data['status'] = 'failed';
            $data['message'] = '升级过程超过 10 分钟未完成，已自动标记为失败';
            $data['finished'] = date('Y-m-d H:i:s');
            update_atomic_write_json($file, $data);
        }
    }
    return $data;
}

function update_write_state($state) {
    return update_atomic_write_json(update_state_path(), $state);
}

function update_history_path() {
    return UPDATE_DIR . '/history.json';
}

function update_read_history() {
    $file = update_history_path();
    if (!file_exists($file)) return [];
    $raw = @file_get_contents($file);
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

function update_append_history($entry) {
    $history = update_read_history();
    array_unshift($history, $entry);
    if (count($history) > 50) $history = array_slice($history, 0, 50);
    return update_atomic_write_json(update_history_path(), $history);
}

function update_env_check() {
    $result = [
        'writable' => true,
        'php_user' => '',
        'owner' => '',
        'reason' => '',
    ];

    if (function_exists('posix_getpwuid') && function_exists('posix_geteuid')) {
        $info = @posix_getpwuid(posix_geteuid());
        if (is_array($info) && !empty($info['name'])) {
            $result['php_user'] = (string)$info['name'];
        }
    }
    if ($result['php_user'] === '') {
        $result['php_user'] = get_current_user() ?: 'unknown';
    }

    $rootDir = __DIR__ . '/..';
    if (function_exists('posix_getpwuid') && function_exists('fileowner')) {
        $uid = @fileowner($rootDir);
        if ($uid !== false) {
            $info = @posix_getpwuid($uid);
            if (is_array($info) && !empty($info['name'])) {
                $result['owner'] = (string)$info['name'];
            }
        }
    }

    $testFile = $rootDir . '/.update_write_test_' . bin2hex(random_bytes(4)) . '.tmp';
    $writeOk = @file_put_contents($testFile, 'test', LOCK_EX);
    if ($writeOk === false) {
        $result['writable'] = false;
        $result['reason'] = 'PHP 运行用户「' . $result['php_user'] . '」无法在项目根目录创建文件。';
        if ($result['owner'] !== '' && $result['owner'] !== $result['php_user']) {
            $result['reason'] .= ' 文件所有者是「' . $result['owner'] . '」。建议：把项目目录所有者改为 PHP 运行用户，或设置 775 权限并加入同一用户组；共享主机用户建议直接从 GitHub 下载手动覆盖。';
        } else {
            $result['reason'] .= ' 建议：检查项目根目录权限（一般 755，文件 644），或直接把目录所有者改为 PHP 运行用户。';
        }
        return $result;
    }
    $delOk = @unlink($testFile);
    if (!$delOk) {
        $result['writable'] = false;
        $result['reason'] = 'PHP 运行用户「' . $result['php_user'] . '」能创建文件但无法删除，可能是权限不足。';
        return $result;
    }

    $testAdminFile = __DIR__ . '/.update_write_test_' . bin2hex(random_bytes(4)) . '.tmp';
    $writeAdminOk = @file_put_contents($testAdminFile, 'test', LOCK_EX);
    if ($writeAdminOk === false) {
        $result['writable'] = false;
        $result['reason'] = 'PHP 运行用户「' . $result['php_user'] . '」无法在 admin 目录创建文件。';
        return $result;
    }
    @unlink($testAdminFile);

    return $result;
}

function update_fetch_release($force = false) {
    update_ensure_dir();
    $cacheFile = UPDATE_DIR . '/github_release_cache.json';

    if (!$force && file_exists($cacheFile)) {
        $mtime = @filemtime($cacheFile);
        if ($mtime !== false && (time() - $mtime) < 300) {
            $raw = @file_get_contents($cacheFile);
            $cached = json_decode($raw, true);
            if (is_array($cached) && isset($cached['data'])) {
                return ['ok' => true, 'data' => $cached['data'], 'cached' => true];
            }
        }
    }

    $repo = UPDATE_GITHUB_REPO;
    $url = 'https://api.github.com/repos/' . $repo . '/releases/latest';

    $ctx = stream_context_create([
        'http' => [
            'method' => 'GET',
            'header' => "User-Agent: NuomiNav-Updater/1.0\r\nAccept: application/vnd.github+json\r\n",
            'timeout' => 15,
            'ignore_errors' => true,
        ],
        'https' => [
            'method' => 'GET',
            'header' => "User-Agent: NuomiNav-Updater/1.0\r\nAccept: application/vnd.github+json\r\n",
            'timeout' => 15,
            'ignore_errors' => true,
        ],
    ]);

    $raw = @file_get_contents($url, false, $ctx);
    if ($raw === false) {
        return ['ok' => false, 'msg' => '无法连接 GitHub API，请检查服务器网络或 allow_url_fopen 设置。'];
    }

    $json = json_decode($raw, true);
    if (!is_array($json)) {
        return ['ok' => false, 'msg' => 'GitHub API 返回格式异常。'];
    }

    if (isset($json['message'])) {
        return ['ok' => false, 'msg' => 'GitHub API 错误：' . (string)$json['message']];
    }

    $tag = (string)($json['tag_name'] ?? '');
    if ($tag === '') {
        return ['ok' => false, 'msg' => 'GitHub Release 缺少 tag_name。'];
    }
    $version = ltrim($tag, 'vV');

    $downloadUrl = '';
    $sha256 = '';
    if (isset($json['assets']) && is_array($json['assets'])) {
        foreach ($json['assets'] as $asset) {
            if (!is_array($asset)) continue;
            $name = (string)($asset['name'] ?? '');
            $u = (string)($asset['browser_download_url'] ?? '');
            if ($u === '' || $name === '') continue;
            if (strtolower(pathinfo($name, PATHINFO_EXTENSION)) === 'zip') {
                $downloadUrl = $u;
                if (!empty($asset['digest']) && stripos($asset['digest'], 'sha256:') === 0) {
                    $sha256 = substr($asset['digest'], 7);
                }
                break;
            }
        }
    }

    $data = [
        'version' => $version,
        'tag' => $tag,
        'name' => (string)($json['name'] ?? ''),
        'body' => (string)($json['body'] ?? ''),
        'published_at' => (string)($json['published_at'] ?? ''),
        'html_url' => (string)($json['html_url'] ?? ''),
        'download_url' => $downloadUrl,
        'sha256' => $sha256,
    ];

    update_atomic_write_json($cacheFile, ['ts' => time(), 'data' => $data]);
    return ['ok' => true, 'data' => $data, 'cached' => false];
}

function update_validate_download_url($url) {
    $host = parse_url((string)$url, PHP_URL_HOST);
    if (!$host) return false;
    $host = strtolower($host);
    $whitelist = [
        'github.com',
        'raw.githubusercontent.com',
        'objects.githubusercontent.com',
        'codeload.github.com',
    ];
    foreach ($whitelist as $allowed) {
        if ($host === $allowed || substr($host, -strlen('.' . $allowed)) === '.' . $allowed) {
            return true;
        }
    }
    return false;
}

function update_validate_relative_path($path) {
    $path = str_replace('\\', '/', (string)$path);
    if ($path === '') return false;
    if ($path[0] === '/') return false;
    if (preg_match('#^[A-Za-z]:#', $path)) return false;
    if (strpos($path, '..') !== false) return false;
    foreach (explode('/', $path) as $seg) {
        if ($seg === '' || $seg === '.') return false;
    }
    return true;
}

function update_is_preserved($relPath) {
    $rel = ltrim(str_replace('\\', '/', (string)$relPath), '/');

    $preserved = [
        'admin/data.json',
        'admin/.admin.conf',
        'admin/.update',
    ];
    foreach ($preserved as $p) {
        if ($rel === $p) return true;
        if (strpos($rel, $p . '/') === 0) return true;
    }
    $preservedDirs = [
        'photos',
        'assets/js',
        'assets/preloaders',
    ];
    foreach ($preservedDirs as $d) {
        if ($rel === $d) return true;
        if (strpos($rel, $d . '/') === 0) return true;
    }
    $preservedFiles = ['README.md', 'LICENSE', 'CHANGELOG.md'];
    if (in_array($rel, $preservedFiles, true)) return true;

    return false;
}

function update_is_allowed_overwrite($relPath) {
    $rel = ltrim(str_replace('\\', '/', (string)$relPath), '/');
    if ($rel === '') return false;
    if (update_is_preserved($rel)) return false;

    if (in_array($rel, ['index.php', '404.php', 'announcement.php', 'analytics.php'], true)) return true;

    if (strpos($rel, 'admin/') === 0) {
        $ext = strtolower(pathinfo($rel, PATHINFO_EXTENSION));
        if (in_array($ext, ['php', 'css', 'js', 'md', 'txt', 'html', 'htm'], true)) return true;
        return false;
    }

    if (strpos($rel, 'assets/') === 0) {
        if (strpos($rel, 'assets/js/') === 0) return false;
        if (strpos($rel, 'assets/preloaders/') === 0) return false;
        $ext = strtolower(pathinfo($rel, PATHINFO_EXTENSION));
        if (in_array($ext, ['php', 'css', 'js', 'html', 'htm', 'json', 'txt'], true)) return true;
        return false;
    }

    return false;
}

function update_scan_files($baseDir) {
    $result = [];
    if (!is_dir($baseDir)) return $result;
    $baseDir = rtrim(str_replace('\\', '/', $baseDir), '/');
    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($baseDir, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::LEAVES_ONLY
    );
    foreach ($it as $file) {
        if ($file->isDir()) continue;
        $abs = str_replace('\\', '/', $file->getPathname());
        $rel = ltrim(substr($abs, strlen($baseDir)), '/');
        $result[] = $rel;
    }
    return $result;
}

function update_backup_current() {
    update_ensure_dir();
    $ts = date('Ymd_His');
    $backupDir = UPDATE_DIR . '/backup/' . $ts;
    if (!is_dir($backupDir)) @mkdir($backupDir, 0755, true);

    $rootDir = realpath(__DIR__ . '/..');
    if ($rootDir === false) return ['ok' => false, 'msg' => '无法定位项目根目录'];
    $rootDir = str_replace('\\', '/', $rootDir);

    $files = update_scan_files($rootDir);
    $copied = 0;
    foreach ($files as $rel) {
        if (!update_is_allowed_overwrite($rel)) continue;
        $src = $rootDir . '/' . $rel;
        $dst = $backupDir . '/' . $rel;
        $dstDir = dirname($dst);
        if (!is_dir($dstDir)) @mkdir($dstDir, 0755, true);
        if (@copy($src, $dst)) $copied++;
    }

    return ['ok' => true, 'dir' => $backupDir, 'count' => $copied];
}

function update_rmdir_recursive($dir) {
    if (!is_dir($dir)) return;
    $items = @scandir($dir);
    if ($items === false) return;
    foreach ($items as $f) {
        if ($f === '.' || $f === '..') continue;
        $full = $dir . '/' . $f;
        if (is_dir($full)) update_rmdir_recursive($full);
        else @unlink($full);
    }
    @rmdir($dir);
}

function update_cleanup_old_backups() {
    $baseDir = UPDATE_DIR . '/backup';
    if (!is_dir($baseDir)) return;
    $items = @scandir($baseDir);
    if ($items === false) return;
    $dirs = [];
    foreach ($items as $f) {
        if ($f === '.' || $f === '..') continue;
        $full = $baseDir . '/' . $f;
        if (is_dir($full)) $dirs[] = $full;
    }
    usort($dirs, function ($a, $b) { return strcmp($b, $a); });
    $keep = max(1, (int)UPDATE_BACKUP_KEEP);
    for ($i = $keep; $i < count($dirs); $i++) {
        update_rmdir_recursive($dirs[$i]);
    }
}

function update_get_latest_backup() {
    $baseDir = UPDATE_DIR . '/backup';
    if (!is_dir($baseDir)) return null;
    $items = @scandir($baseDir);
    if ($items === false) return null;
    $dirs = [];
    foreach ($items as $f) {
        if ($f === '.' || $f === '..') continue;
        $full = $baseDir . '/' . $f;
        if (is_dir($full)) $dirs[] = $full;
    }
    if (count($dirs) === 0) return null;
    usort($dirs, function ($a, $b) { return strcmp($b, $a); });
    return $dirs[0];
}

function update_download_package($url, $sha256 = '') {
    update_ensure_dir();
    if (!update_validate_download_url($url)) {
        return ['ok' => false, 'msg' => '下载地址不在白名单内'];
    }
    if (!class_exists('ZipArchive')) {
        return ['ok' => false, 'msg' => '服务器未安装 PHP zip 扩展，无法解压更新包'];
    }

    $packageFile = UPDATE_DIR . '/package.zip';
    $maxBytes = (int)UPDATE_MAX_PACKAGE_MB * 1024 * 1024;

    $ctx = stream_context_create([
        'http' => [
            'method' => 'GET',
            'header' => "User-Agent: NuomiNav-Updater/1.0\r\nAccept: application/octet-stream\r\n",
            'timeout' => 120,
            'follow_location' => 1,
            'max_redirects' => 5,
        ],
        'https' => [
            'method' => 'GET',
            'header' => "User-Agent: NuomiNav-Updater/1.0\r\nAccept: application/octet-stream\r\n",
            'timeout' => 120,
            'follow_location' => 1,
            'max_redirects' => 5,
        ],
    ]);

    $fp = @fopen($url, 'rb', false, $ctx);
    if (!$fp) {
        return ['ok' => false, 'msg' => '无法打开下载链接'];
    }

    $out = @fopen($packageFile, 'wb');
    if (!$out) {
        @fclose($fp);
        return ['ok' => false, 'msg' => '无法写入更新包文件，请检查 admin/.update 目录权限'];
    }

    $total = 0;
    $ok = true;
    while (!feof($fp)) {
        $chunk = @fread($fp, 65536);
        if ($chunk === false) { $ok = false; break; }
        $total += strlen($chunk);
        if ($total > $maxBytes) { $ok = false; break; }
        if (@fwrite($out, $chunk) === false) { $ok = false; break; }
    }
    @fclose($fp);
    @fclose($out);

    if (!$ok) {
        @unlink($packageFile);
        return ['ok' => false, 'msg' => '下载失败或超过 ' . UPDATE_MAX_PACKAGE_MB . 'MB 上限'];
    }

    if ($sha256 !== '') {
        $actual = @hash_file('sha256', $packageFile);
        if ($actual === false || strcasecmp($actual, $sha256) !== 0) {
            @unlink($packageFile);
            return ['ok' => false, 'msg' => 'SHA256 校验失败'];
        }
    }

    return ['ok' => true, 'file' => $packageFile, 'size' => $total];
}

function update_extract_package($packageFile) {
    if (!is_file($packageFile)) {
        return ['ok' => false, 'msg' => '更新包文件不存在'];
    }
    if (!class_exists('ZipArchive')) {
        return ['ok' => false, 'msg' => '服务器未安装 PHP zip 扩展'];
    }

    $extractDir = UPDATE_DIR . '/package';
    if (is_dir($extractDir)) update_rmdir_recursive($extractDir);
    @mkdir($extractDir, 0755, true);

    $zip = new ZipArchive();
    if ($zip->open($packageFile) !== true) {
        return ['ok' => false, 'msg' => '无法打开 ZIP 文件'];
    }

    $maxTotal = (int)UPDATE_MAX_EXTRACTED_MB * 1024 * 1024;
    $totalBytes = 0;
    $extractedCount = 0;
    $ok = true;
    $msg = '';

    for ($i = 0; $i < $zip->numFiles; $i++) {
        $entry = $zip->statIndex($i);
        if ($entry === false) continue;
        $name = (string)$entry['name'];
        $size = (int)$entry['size'];

        $norm = str_replace('\\', '/', $name);
        if ($norm === '' || $norm === '/') continue;
        if ($norm[0] === '/') { $ok = false; $msg = '非法绝对路径：' . $name; break; }
        if (preg_match('#^[A-Za-z]:#', $norm)) { $ok = false; $msg = '非法 Windows 盘符路径：' . $name; break; }
        if (strpos($norm, '..') !== false) { $ok = false; $msg = '非法路径穿越：' . $name; break; }
        if (substr($norm, -1) === '/') continue;

        $totalBytes += $size;
        if ($totalBytes > $maxTotal) {
            $ok = false;
            $msg = '解压后总大小超过 ' . UPDATE_MAX_EXTRACTED_MB . 'MB 上限';
            break;
        }

        $target = $extractDir . '/' . $norm;
        $targetDir = dirname($target);
        if (!is_dir($targetDir)) @mkdir($targetDir, 0755, true);

        $contents = $zip->getFromIndex($i);
        if ($contents === false) continue;
        if (@file_put_contents($target, $contents) === false) {
            $ok = false;
            $msg = '写入文件失败：' . $name;
            break;
        }
        $extractedCount++;
    }

    $zip->close();

    if (!$ok) {
        update_rmdir_recursive($extractDir);
        return ['ok' => false, 'msg' => $msg];
    }

    return ['ok' => true, 'dir' => $extractDir, 'count' => $extractedCount];
}

function update_find_package_root($extractDir) {
    $items = @scandir($extractDir);
    if ($items === false) return $extractDir;
    $dirs = [];
    $files = [];
    foreach ($items as $f) {
        if ($f === '.' || $f === '..') continue;
        $full = $extractDir . '/' . $f;
        if (is_dir($full)) $dirs[] = $full;
        else $files[] = $full;
    }
    if (count($dirs) === 1 && count($files) === 0) {
        if (is_file($dirs[0] . '/index.php')) {
            return $dirs[0];
        }
    }
    return $extractDir;
}

function update_restore_from_backup($backupDir) {
    if (!is_dir($backupDir)) return false;
    $rootDir = realpath(__DIR__ . '/..');
    if ($rootDir === false) return false;
    $rootDir = str_replace('\\', '/', $rootDir);

    $files = update_scan_files($backupDir);
    foreach ($files as $rel) {
        $rel = ltrim(str_replace('\\', '/', $rel), '/');
        if ($rel === '') continue;
        $src = $backupDir . '/' . $rel;
        $dst = $rootDir . '/' . $rel;
        $dstDir = dirname($dst);
        if (!is_dir($dstDir)) @mkdir($dstDir, 0755, true);
        @copy($src, $dst);
        @chmod($dst, 0644);
    }
    return true;
}

function update_apply_package() {
    $extractDir = UPDATE_DIR . '/package';
    if (!is_dir($extractDir)) {
        return ['ok' => false, 'msg' => '更新包尚未解压，请先下载'];
    }

    $packageRoot = update_find_package_root($extractDir);

    $rootDir = realpath(__DIR__ . '/..');
    if ($rootDir === false) {
        return ['ok' => false, 'msg' => '无法定位项目根目录'];
    }
    $rootDir = str_replace('\\', '/', $rootDir);

    $backup = update_backup_current();
    if (!$backup['ok']) {
        return ['ok' => false, 'msg' => '备份失败：' . ($backup['msg'] ?? '未知错误')];
    }
    $backupDir = $backup['dir'];

    $files = update_scan_files($packageRoot);
    $toApply = [];
    $preserved = 0;
    $skipped = 0;
    foreach ($files as $rel) {
        $rel = ltrim(str_replace('\\', '/', $rel), '/');
        if ($rel === '') continue;
        if (update_is_preserved($rel)) { $preserved++; continue; }
        if (!update_is_allowed_overwrite($rel)) { $skipped++; continue; }
        $toApply[] = $rel;
    }

    if (count($toApply) === 0) {
        return ['ok' => false, 'msg' => '更新包中没有可覆盖的文件（可能版本包结构异常）'];
    }

    $applied = [];
    $failed = null;
    foreach ($toApply as $rel) {
        $src = $packageRoot . '/' . $rel;
        $dst = $rootDir . '/' . $rel;
        $dstDir = dirname($dst);
        if (!is_dir($dstDir)) @mkdir($dstDir, 0755, true);
        if (!@copy($src, $dst)) {
            $failed = '覆盖失败：' . $rel;
            break;
        }
        @chmod($dst, 0644);
        $applied[] = $rel;
    }

    if ($failed !== null) {
        update_restore_from_backup($backupDir);
        return ['ok' => false, 'msg' => $failed . '，已自动回滚'];
    }

    update_cleanup_old_backups();

    return [
        'ok' => true,
        'applied' => count($applied),
        'preserved' => $preserved,
        'skipped' => $skipped,
        'backup_dir' => $backupDir,
    ];
}

/* ==========================================================================
   6. API 路由分发
   ========================================================================== */
$action = isset($_POST['action']) ? $_POST['action'] : (isset($_GET['action']) ? $_GET['action'] : '');
if ($action !== '') {
    switch ($action) {
        case 'init':
            if (has_conf()) respond(false, '系统已完成安装，请直接登录');

            $user  = trim($_POST['username'] ?? '');
            $pass  = $_POST['password'] ?? '';
            $pass2 = $_POST['password2'] ?? '';
            if ($user === '') respond(false, '用户名不能为空');
            if (strlen($pass) < 6) respond(false, '密码至少需要 6 位');
            if ($pass !== $pass2) respond(false, '两次输入的密码不一致');

            foreach (['bgApi', 'bannerApi', 'githubUrl', 'poweredByUrl'] as $f) {
                $v = trim((string)($_POST[$f] ?? ''));
                if ($v !== '' && !preg_match('#^https?://#i', $v)) {
                    respond(false, '链接地址必须以 http:// 或 https:// 开头');
                }
            }

            $data = load_data();
            $defaults = site_defaults();

            foreach (['pageTitle','title','subtitle','avatar','favicon','bgApi','bannerApi',
                      'copyright','githubUrl','poweredBy','poweredByUrl'] as $f) {
                $data['site'][$f] = trim((string)($_POST[$f] ?? ''));
            }

            $data['site']['githubEnabled'] = (isset($_POST['githubEnabled']) && $_POST['githubEnabled'] === '1') ? '1' : '0';

            $numSpec = [
                'bgInterval'       => [3, 60, true],
                'bannerInterval'   => [3, 60, true],
                'cacheLimit'       => [2, 20, true],
                'adminClickCount'  => [3, 30, true],
                'adminClickWindow' => [1, 60, false],
            ];
            foreach ($numSpec as $f => $spec) {
                list($min, $max, $isInt) = $spec;
                $raw = trim((string)($_POST[$f] ?? ''));
                if ($raw === '') { $data['site'][$f] = $defaults[$f]; continue; }
                if ($isInt) {
                    $v = (int)$raw;
                    if ($v < $min) $v = $min;
                    if ($v > $max) $v = $max;
                    $data['site'][$f] = (string)$v;
                } else {
                    $v = (float)$raw;
                    if ($v < $min) $v = $min;
                    if ($v > $max) $v = $max;
                    $data['site'][$f] = rtrim(rtrim(number_format($v, 1, '.', ''), '0'), '.');
                }
            }

            save_data($data);

            write_conf([
                'username' => $user,
                'hash'     => password_hash($pass, PASSWORD_DEFAULT),
                'created'  => date('c'),
            ]);

            $_SESSION['nuomi_admin_login'] = true;
            respond(true, '安装成功');
            break;

        case 'login':
            $user = trim($_POST['username'] ?? '');
            $pass = $_POST['password'] ?? '';
            if (!has_conf()) respond(false, '系统尚未安装');
            $conf = read_conf();
            if (!$conf || $user !== ($conf['username'] ?? '') || !password_verify($pass, $conf['hash'] ?? '')) respond(false, '用户名或密码错误');
            $_SESSION['nuomi_admin_login'] = true;
            respond(true, '登录成功');
            break;

        case 'logout':
            $_SESSION = [];
            if (ini_get('session.use_cookies')) {
                $p = session_get_cookie_params();
                setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
            }
            session_destroy();
            respond(true, '已退出登录');
            break;

        /* ============================================================
           卡片管理
           ============================================================ */
        case 'save_card':
            require_login();
            $card = normalize_card([
                'id' => $_POST['id'] ?? '', 'type' => $_POST['type'] ?? 'link', 'category' => $_POST['category'] ?? '',
                'name' => $_POST['name'] ?? '', 'sub' => $_POST['sub'] ?? '', 'icon' => $_POST['icon'] ?? '',
                'href' => $_POST['href'] ?? '', 'image' => $_POST['image'] ?? '',
            ]);
            if ($card['name'] === '') respond(false, '卡片名称不能为空');
            if ($card['category'] === '') respond(false, '请选择分类');
            $data = load_data();
            $catNameList = array_column($data['categories'], 'name');
            if (!in_array($card['category'], $catNameList, true)) respond(false, '所选分类不存在：' . $card['category']);
            if ($card['type'] === 'link' && $card['href'] === '') respond(false, '链接卡片必须填写跳转网址');
            if ($card['type'] === 'image' && $card['image'] === '') respond(false, '图片卡片必须填写弹窗大图');
            if ($card['id'] === '') {
                $card['id'] = uniqid('card_');
                $data['cards'][] = $card;
                save_data($data);
                respond(true, '卡片已新增', ['card' => $card]);
            } else {
                $found = false;
                foreach ($data['cards'] as $k => $c) { if ($c['id'] === $card['id']) { $data['cards'][$k] = $card; $found = true; break; } }
                if (!$found) $data['cards'][] = $card;
                save_data($data);
                respond(true, '卡片已保存', ['card' => $card]);
            }
            break;

        case 'delete_card':
            require_login();
            $id = $_POST['id'] ?? '';
            $data = load_data();
            $before = count($data['cards']);
            $data['cards'] = array_values(array_filter($data['cards'], function ($c) use ($id) { return $c['id'] !== $id; }));
            if (count($data['cards']) === $before) respond(false, '卡片不存在或已删除');
            save_data($data);
            respond(true, '卡片已删除');
            break;

        case 'save_sort':
            require_login();
            $order = json_decode($_POST['ids'] ?? '', true);
            if (!is_array($order)) respond(false, '排序数据无效');
            $data = load_data(); $byId = [];
            foreach ($data['cards'] as $c) { if (isset($c['id'])) $byId[$c['id']] = $c; }
            $newCards = [];
            foreach ($order as $id) { if (isset($byId[$id])) { $newCards[] = $byId[$id]; unset($byId[$id]); } }
            foreach ($byId as $c) $newCards[] = $c;
            $data['cards'] = $newCards;
            save_data($data);
            respond(true, '排序已保存');
            break;

        /* ============================================================
           分类管理
           ============================================================ */
        case 'add_category':
            require_login();
            $name = trim($_POST['name'] ?? ''); $icon = trim($_POST['icon'] ?? '');
            if ($name === '') respond(false, '分类名称不能为空');
            $data = load_data();
            foreach ($data['categories'] as $c) { if ($c['name'] === $name) respond(false, '分类「' . $name . '」已存在'); }
            $data['categories'][] = ['name' => $name, 'icon' => $icon];
            save_data($data);
            respond(true, '分类已添加', ['categories' => $data['categories']]);
            break;

        case 'rename_category':
            require_login();
            $old = trim($_POST['old_name'] ?? ''); $new = trim($_POST['new_name'] ?? ''); $newIcon = trim($_POST['new_icon'] ?? '');
            if ($new === '') respond(false, '新分类名称不能为空');
            $data = load_data();
            $foundIdx = -1;
            foreach ($data['categories'] as $idx => $item) { if ($item['name'] === $old) { $foundIdx = $idx; break; } }
            if ($foundIdx === -1) respond(false, '原分类不存在');
            foreach ($data['categories'] as $k => $c) { if ($k !== $foundIdx && $c['name'] === $new) respond(false, '新分类名称已存在'); }
            $data['categories'][$foundIdx]['name'] = $new;
            $data['categories'][$foundIdx]['icon'] = $newIcon;
            foreach ($data['cards'] as $k => $card) { if ($card['category'] === $old) $data['cards'][$k]['category'] = $new; }
            save_data($data);
            respond(true, '分类已修改', ['categories' => $data['categories']]);
            break;

        case 'delete_category':
            require_login();
            $name = trim($_POST['name'] ?? '');
            $data = load_data();
            if (!in_array($name, array_column($data['categories'], 'name'), true)) respond(false, '分类不存在');
            $count = 0;
            foreach ($data['cards'] as $card) { if ($card['category'] === $name) $count++; }
            if ($count > 0) respond(false, '该分类下还有 ' . $count . ' 张卡片，请先移动或删除这些卡片');
            $data['categories'] = array_values(array_filter($data['categories'], function ($c) use ($name) { return $c['name'] !== $name; }));
            save_data($data);
            respond(true, '分类已删除', ['categories' => $data['categories']]);
            break;

        case 'save_category_sort':
            require_login();
            $order = json_decode($_POST['names'] ?? '', true);
            if (!is_array($order)) respond(false, '排序数据无效');
            $data = load_data(); $byName = [];
            foreach ($data['categories'] as $c) { $byName[$c['name']] = $c; }
            $newCats = [];
            foreach ($order as $n) { if (is_string($n) && isset($byName[$n])) { $newCats[] = $byName[$n]; unset($byName[$n]); } }
            foreach ($byName as $c) $newCats[] = $c;
            $data['categories'] = $newCats;
            save_data($data);
            respond(true, '分类顺序已保存', ['categories' => $data['categories']]);
            break;

        /* ============================================================
           图片管理
           ============================================================ */
        case 'upload':
            require_login();
            if (empty($_FILES['file'])) respond(false, '未收到文件');
            $file = $_FILES['file'];
            if ($file['error'] !== UPLOAD_ERR_OK) respond(false, '上传失败，错误码：' . $file['error']);
            if ($file['size'] > MAX_UPLOAD) respond(false, '文件超过 5MB 限制');
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            if (!in_array($ext, ALLOWED_EXT, true)) respond(false, '仅支持 jpg / png / gif / webp 格式');
            if (!@getimagesize($file['tmp_name'])) respond(false, '文件不是有效图片');
            if (!is_dir(PHOTO_DIR)) @mkdir(PHOTO_DIR, 0755, true);
            $baseName = sanitize_filename(pathinfo($file['name'], PATHINFO_FILENAME), 'image');
            $newname  = unique_filename(PHOTO_DIR, $baseName . '.' . $ext);
            $dest = PHOTO_DIR . '/' . $newname;
            if (!@move_uploaded_file($file['tmp_name'], $dest)) respond(false, '保存文件失败，请检查 photos 目录写权限');
            @chmod($dest, 0644);
            $path = 'photos/' . $newname;

            $thumbRel = thumb_path_for($path);
            if ($thumbRel !== '') make_thumbnail($dest, __DIR__ . '/../' . $thumbRel, THUMB_MAX_SIZE);

            $data = load_data();
            if (!isset($data['photoOrder']) || !is_array($data['photoOrder'])) $data['photoOrder'] = [];
            $data['photoOrder'][] = $path;
            save_data($data);
            respond(true, '上传成功', ['path' => $path, 'photos' => list_photos($data)]);
            break;

        case 'delete_photo':
            require_login();
            $path = $_POST['path'] ?? '';
            $force = !empty($_POST['force']);
            $base = realpath(PHOTO_DIR);
            if ($base === false) respond(false, 'photos 目录不存在');
            $full = realpath(__DIR__ . '/../' . $path);
            if ($full === false || strpos($full, $base . DIRECTORY_SEPARATOR) !== 0) respond(false, '非法路径');
            if (!is_file($full)) respond(false, '文件不存在');

            $data = load_data();
            $pathWithSlash = '/' . $path;

            if (!$force) {
                $usages = [];
                foreach ($data['cards'] as $c) {
                    if (($c['icon'] ?? '') === $path) {
                        $usages[] = '卡片「' . ($c['name'] ?? '未命名') . '」的图标';
                    }
                    if (($c['image'] ?? '') === $path) {
                        $usages[] = '卡片「' . ($c['name'] ?? '未命名') . '」的弹窗大图';
                    }
                }
                foreach ($data['categories'] as $c) {
                    if (($c['icon'] ?? '') === $path) {
                        $usages[] = '分类「' . ($c['name'] ?? '未命名') . '」的图标';
                    }
                }
                $siteMap = ['avatar' => '前台头像', 'favicon' => '网站图标', 'adminAvatar' => '后台头像'];
                foreach ($siteMap as $field => $label) {
                    $v = $data['site'][$field] ?? '';
                    if ($v === $path || $v === $pathWithSlash) {
                        $usages[] = $label;
                    }
                }

                if (count($usages) > 0) {
                    respond(false, '被使用中', ['usages' => $usages]);
                }
            }

            delete_with_thumb($full, $path);

            foreach ($data['cards'] as $k => $c) {
                if (($c['icon'] ?? '') === $path) $data['cards'][$k]['icon'] = '';
                if (($c['image'] ?? '') === $path) $data['cards'][$k]['image'] = '';
            }
            foreach ($data['categories'] as $k => $cat) {
                if (($cat['icon'] ?? '') === $path) $data['categories'][$k]['icon'] = '';
            }
            $defaults = site_defaults();
            foreach (['avatar', 'favicon', 'adminAvatar'] as $field) {
                $v = $data['site'][$field] ?? '';
                if ($v === $path || $v === $pathWithSlash) {
                    $data['site'][$field] = $defaults[$field];
                }
            }
            if (isset($data['photoOrder']) && is_array($data['photoOrder'])) {
                $data['photoOrder'] = array_values(array_filter($data['photoOrder'], function ($p) use ($path) { return $p !== $path; }));
            }
            save_data($data);
            respond(true, '图片已删除', ['photos' => list_photos($data)]);
            break;

        case 'rename_photo':
            require_login();
            $old = trim($_POST['path'] ?? ''); $newName = trim($_POST['name'] ?? '');
            if ($old === '') respond(false, '缺少原路径');
            if ($newName === '') respond(false, '名称不能为空');
            if (strpos($old, 'photos/') !== 0) respond(false, '只能重命名 photos/ 下的图片');
            if (strpos($old, '..') !== false) respond(false, '非法路径');
            if (preg_match('#[\\\\/:*?"<>|]#', $newName)) respond(false, '名称不能包含 \\ / : * ? " < > | 等字符');
            if (substr($newName, 0, 1) === '.') respond(false, '名称不能以点开头');
            if (mb_strlen($newName) > 60) respond(false, '名称过长（最多 60 字）');
            $base = realpath(PHOTO_DIR); $oldReal = realpath(__DIR__ . '/../' . $old);
            if ($base === false || $oldReal === false) respond(false, '文件不存在');
            if (strpos($oldReal, $base . DIRECTORY_SEPARATOR) !== 0) respond(false, '非法路径');
            if (!is_file($oldReal)) respond(false, '文件不存在');
            $ext = pathinfo($oldReal, PATHINFO_EXTENSION);
            $newFile = $newName . '.' . $ext; $newFull = PHOTO_DIR . '/' . $newFile; $newRel = 'photos/' . $newFile;
            if ($newRel === $old) respond(true, '名称未改变', ['path' => $old]);
            if (file_exists($newFull)) respond(false, '目标文件名已存在');

            if (!rename_with_thumb($oldReal, $newFull, $old, $newRel)) respond(false, '重命名失败，请检查权限');

            $data = load_data();
            foreach ($data['cards'] as $k => $c) {
                if (($c['icon'] ?? '') === $old) $data['cards'][$k]['icon'] = $newRel;
                if (($c['image'] ?? '') === $old) $data['cards'][$k]['image'] = $newRel;
            }
            foreach ($data['categories'] as $k => $cat) { if (($cat['icon'] ?? '') === $old) $data['categories'][$k]['icon'] = $newRel; }
            if (isset($data['photoOrder']) && is_array($data['photoOrder'])) {
                foreach ($data['photoOrder'] as $k => $p) { if ($p === $old) $data['photoOrder'][$k] = $newRel; }
            }
            $oldWithSlash = '/' . $old;
            $newWithSlash = '/' . $newRel;
            foreach (['avatar', 'favicon', 'adminAvatar'] as $siteField) {
                $v = $data['site'][$siteField] ?? '';
                if ($v === $old) {
                    $data['site'][$siteField] = $newRel;
                } elseif ($v === $oldWithSlash) {
                    $data['site'][$siteField] = $newWithSlash;
                }
            }
            save_data($data);
            respond(true, '重命名成功', ['old' => $old, 'new' => $newRel, 'photos' => list_photos($data)]);
            break;

        case 'save_photo_sort':
            require_login();
            $order = json_decode($_POST['paths'] ?? '', true);
            if (!is_array($order)) respond(false, '排序数据无效');
            $valid = [];
            foreach ($order as $p) {
                if (!is_string($p) || $p === '') continue;
                if (strpos($p, 'photos/') !== 0) continue;
                if (strpos($p, '..') !== false) continue;
                $full = __DIR__ . '/../' . $p;
                if (is_file($full)) $valid[] = $p;
            }
            $data = load_data(); $data['photoOrder'] = $valid;
            save_data($data);
            respond(true, '图片顺序已保存');
            break;

        /* ============================================================
           JS 特效
           ============================================================ */
        case 'upload_js':
            require_login();
            if (empty($_FILES['file'])) respond(false, '未收到文件');
            $file = $_FILES['file'];
            if ($file['error'] !== UPLOAD_ERR_OK) respond(false, '上传失败，错误码：' . $file['error']);
            if ($file['size'] > MAX_JS_UPLOAD) respond(false, 'JS 文件超过 2MB 限制');
            if (strtolower(pathinfo($file['name'], PATHINFO_EXTENSION)) !== 'js') respond(false, '仅支持 .js 文件');
            $content = @file_get_contents($file['tmp_name']);
            if ($content === false) respond(false, '无法读取文件内容');
            if (stripos($content, '<?php') !== false || stripos($content, '<?=') !== false) respond(false, 'JS 文件包含 PHP 标签，已阻止上传');
            if (!is_dir(JS_DIR)) @mkdir(JS_DIR, 0755, true);
            $baseName = sanitize_filename(pathinfo($file['name'], PATHINFO_FILENAME), 'effect');
            $newname  = unique_filename(JS_DIR, $baseName . '.js');
            $dest = JS_DIR . '/' . $newname;
            if (!@move_uploaded_file($file['tmp_name'], $dest)) respond(false, '保存文件失败，请检查 assets/js 目录写权限');
            @chmod($dest, 0644);
            $path = 'assets/js/' . $newname;
            $matchKey = extract_effect_matchkey($content, $baseName);
            $autoToggle = detect_effect_autotoggle($content);
            $data = load_data();
            $effect = [
                'id' => uniqid('eff_'), 'matchKey' => $matchKey, 'name' => $baseName,
                'file' => $path, 'icon' => '✨', 'enabled' => true,
                'autoToggle' => $autoToggle,
            ];
            $data['jsEffects'][] = $effect;
            save_data($data);
            respond(true, '上传成功', ['effect' => $effect, 'effects' => $data['jsEffects']]);
            break;

        case 'add_js_effect_url':
            require_login();
            $url = trim($_POST['url'] ?? ''); $name = trim($_POST['name'] ?? ''); $icon = trim($_POST['icon'] ?? ''); $matchKey = trim($_POST['matchKey'] ?? '');
            if ($url === '') respond(false, '请输入 JS 文件 URL');
            if (!is_remote_url($url)) respond(false, 'URL 必须以 http:// 或 https:// 开头');
            if (strlen($url) > 1000) respond(false, 'URL 过长');
            $urlPath = parse_url($url, PHP_URL_PATH);
            $fallback = pathinfo($urlPath ?: '', PATHINFO_FILENAME);
            if ($fallback === '') $fallback = '远程特效';
            $content = null;
            if ($matchKey === '') {
                $ctx = stream_context_create(['http' => ['timeout' => 5, 'user_agent' => 'NuomiAdmin/1.0'], 'https' => ['timeout' => 5, 'user_agent' => 'NuomiAdmin/1.0']]);
                $content = @file_get_contents($url, false, $ctx);
                $matchKey = $content !== false ? extract_effect_matchkey($content, $fallback) : $fallback;
            }
            if ($name === '') $name = $fallback;
            if ($icon === '') $icon = '✨';
            $data = load_data();
            foreach ($data['jsEffects'] as $e) { if (($e['file'] ?? '') === $url) respond(false, '该 URL 已经存在'); }
            $effect = [
                'id' => uniqid('eff_'), 'matchKey' => $matchKey, 'name' => $name,
                'file' => $url, 'icon' => $icon, 'enabled' => true,
                'autoToggle' => $content !== false ? detect_effect_autotoggle($content) : false,
            ];
            $data['jsEffects'][] = $effect;
            save_data($data);
            respond(true, '远程特效已添加', ['effect' => $effect, 'effects' => $data['jsEffects']]);
            break;

        case 'save_js_effect':
            require_login();
            $id   = $_POST['id'] ?? '';
            $name = trim($_POST['name'] ?? '');
            $icon = trim($_POST['icon'] ?? '✨');
            $enabled = !empty($_POST['enabled']);
            $newFilename = isset($_POST['new_filename']) ? trim((string)$_POST['new_filename']) : null;
            $newMatchKey = isset($_POST['matchKey']) ? trim((string)$_POST['matchKey']) : null;
            if ($name === '') respond(false, '特效名称不能为空');

            $data = load_data();
            $idx = -1;
            foreach ($data['jsEffects'] as $k => $e) { if ($e['id'] === $id) { $idx = $k; break; } }
            if ($idx === -1) respond(false, '特效不存在');

            $eff = $data['jsEffects'][$idx];

            if ($newFilename !== null && $newFilename !== '' && !is_remote_url($eff['file'])) {
                $safe = sanitize_filename($newFilename, 'effect');
                $safe = preg_replace('/\.js$/i', '', $safe);
                if ($safe === '') $safe = 'effect';
                $currentBase = pathinfo($eff['file'], PATHINFO_FILENAME);
                if ($safe !== $currentBase) {
                    $oldFull = __DIR__ . '/../' . $eff['file'];
                    $newRel  = dirname($eff['file']) . '/' . $safe . '.js';
                    $newFull = __DIR__ . '/../' . $newRel;
                    if (!is_file($oldFull)) respond(false, '原 JS 文件不存在，无法重命名');
                    if (file_exists($newFull)) respond(false, '目标文件名已存在');
                    if (!@rename($oldFull, $newFull)) respond(false, '重命名失败，请检查权限');
                    @chmod($newFull, 0644);
                    $eff['file'] = $newRel;
                    if ($name === $currentBase) $name = $safe;
                }
            }

            $data['jsEffects'][$idx]['file']    = $eff['file'];
            $data['jsEffects'][$idx]['name']    = $name;
            $data['jsEffects'][$idx]['icon']    = $icon;
            $data['jsEffects'][$idx]['enabled'] = $enabled;

            if ($newMatchKey !== null && $newMatchKey !== '') {
                $data['jsEffects'][$idx]['matchKey'] = $newMatchKey;
            }

            save_data($data);
            respond(true, '已保存', ['effects' => $data['jsEffects']]);
            break;

        case 'read_js_matchkey':
            require_login();
            $file = trim($_POST['file'] ?? '');
            if ($file === '') respond(false, '缺少文件路径');

            $content = false;
            if (is_remote_url($file)) {
                $ctx = stream_context_create([
                    'http'  => ['timeout' => 5, 'user_agent' => 'NuomiAdmin/1.0'],
                    'https' => ['timeout' => 5, 'user_agent' => 'NuomiAdmin/1.0'],
                ]);
                $content = @file_get_contents($file, false, $ctx);
                $urlPath = parse_url($file, PHP_URL_PATH);
                $fallback = pathinfo($urlPath ?: '', PATHINFO_FILENAME) ?: '远程特效';
            } else {
                $full = __DIR__ . '/../' . $file;
                if (!is_file($full)) respond(false, '文件不存在');
                $content = @file_get_contents($full);
                $fallback = pathinfo($file, PATHINFO_FILENAME);
            }
            if ($content === false) respond(false, '无法读取文件内容');

            $matchKey = extract_effect_matchkey($content, $fallback);
            if ($matchKey === '') respond(false, '未能从脚本中提取到 matchKey');

            respond(true, '已读取', [
                'matchKey'   => $matchKey,
                'autoToggle' => detect_effect_autotoggle($content),
            ]);
            break;

        case 'toggle_js_effect':
            require_login();
            $id = $_POST['id'] ?? ''; $enabled = !empty($_POST['enabled']);
            $data = load_data(); $found = false;
            foreach ($data['jsEffects'] as $k => $e) { if ($e['id'] === $id) { $data['jsEffects'][$k]['enabled'] = $enabled; $found = true; break; } }
            if (!$found) respond(false, '特效不存在');
            save_data($data);
            respond(true, $enabled ? '已开启' : '已关闭');
            break;

        case 'delete_js_effect':
            require_login();
            $id = $_POST['id'] ?? '';
            $data = load_data(); $target = null;
            foreach ($data['jsEffects'] as $e) { if ($e['id'] === $id) { $target = $e; break; } }
            if (!$target) respond(false, '特效不存在');
            $data['jsEffects'] = array_values(array_filter($data['jsEffects'], function($e) use ($id) { return $e['id'] !== $id; }));
            if (!empty($target['file']) && !is_remote_url($target['file'])) {
                $stillUsed = false;
                foreach ($data['jsEffects'] as $e) { if (($e['file'] ?? '') === $target['file']) { $stillUsed = true; break; } }
                if (!$stillUsed) {
                    $base = realpath(JS_DIR); $full = realpath(__DIR__ . '/../' . $target['file']);
                    if ($base !== false && $full !== false && strpos($full, $base . DIRECTORY_SEPARATOR) === 0 && is_file($full)) @unlink($full);
                }
            }
            save_data($data);
            respond(true, '已删除', ['effects' => $data['jsEffects']]);
            break;

        case 'save_effect_sort':
            require_login();
            $order = json_decode($_POST['ids'] ?? '', true);
            if (!is_array($order)) respond(false, '排序数据无效');
            $data = load_data(); $byId = [];
            foreach ($data['jsEffects'] as $e) { if (isset($e['id'])) $byId[$e['id']] = $e; }
            $newEffs = [];
            foreach ($order as $id) { if (isset($byId[$id])) { $newEffs[] = $byId[$id]; unset($byId[$id]); } }
            foreach ($byId as $e) $newEffs[] = $e;
            $data['jsEffects'] = $newEffs;
            save_data($data);
            respond(true, '特效顺序已保存');
            break;

        /* ============================================================
           加载页主题
           ============================================================ */
        case 'upload_preloader':
            require_login();
            if (empty($_FILES['file'])) respond(false, '未收到文件');
            $file = $_FILES['file'];
            if ($file['error'] !== UPLOAD_ERR_OK) respond(false, '上传失败，错误码：' . $file['error']);
            if ($file['size'] > 2 * 1024 * 1024) respond(false, '文件超过 2MB 限制');
            if (strtolower(pathinfo($file['name'], PATHINFO_EXTENSION)) !== 'css') respond(false, '仅支持 .css 文件');
            $content = @file_get_contents($file['tmp_name']);
            if ($content === false) respond(false, '无法读取文件内容');
            if (stripos($content, '<?php') !== false || stripos($content, '<?=') !== false) respond(false, 'CSS 文件包含 PHP 标签，已阻止上传');
            if (stripos($content, '</style') !== false) respond(false, 'CSS 文件包含 </style> 标签，请去掉首尾的 <style>...</style> 再上传');
            if (stripos($content, '<script') !== false) respond(false, 'CSS 文件包含 <script> 标签，已阻止上传');
            if (stripos($content, '</script') !== false) respond(false, 'CSS 文件包含 </script> 标签，已阻止上传');
            if (!is_dir(PRELOADER_DIR)) @mkdir(PRELOADER_DIR, 0755, true);
            $baseName = sanitize_filename(pathinfo($file['name'], PATHINFO_FILENAME), 'preloader');
            $newname  = unique_filename(PRELOADER_DIR, $baseName . '.css');
            $dest = PRELOADER_DIR . '/' . $newname;
            if (!@move_uploaded_file($file['tmp_name'], $dest)) respond(false, '保存文件失败，请检查 assets/preloaders 目录写权限');
            @chmod($dest, 0644);
            $path = 'assets/preloaders/' . $newname;
            $key  = preg_replace('/\.css$/i', '', $newname);
            $key  = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $key);
            $meta = extract_preloader_meta($content, $baseName);
            $data = load_data();
            $data['preloaders'][] = [
                'key' => $key, 'name' => $meta['name'], 'description' => $meta['description'],
                'file' => $path, 'enabled' => true,
            ];
            save_data($data);
            respond(true, '上传成功', ['preloaders' => $data['preloaders']]);
            break;

        case 'toggle_preloader':
            require_login();
            $key = $_POST['key'] ?? '';
            $enabled = !empty($_POST['enabled']);
            $data = load_data();
            $found = false;
            foreach ($data['preloaders'] as $i => $p) {
                if ($p['key'] === $key) { $data['preloaders'][$i]['enabled'] = $enabled; $found = true; break; }
            }
            if (!$found) respond(false, '主题不存在');
            save_data($data);
            respond(true, $enabled ? '已启用' : '已禁用');
            break;

        case 'save_preloader':
            require_login();
            $key = $_POST['key'] ?? '';
            $name = trim($_POST['name'] ?? '');
            $desc = trim($_POST['description'] ?? '');
            $newFilename = isset($_POST['new_filename']) ? trim((string)$_POST['new_filename']) : '';
            if ($name === '') respond(false, '名称不能为空');

            $data = load_data();
            $idx = -1;
            foreach ($data['preloaders'] as $i => $p) { if ($p['key'] === $key) { $idx = $i; break; } }
            if ($idx === -1) respond(false, '主题不存在');

            $item = $data['preloaders'][$idx];
            $newKey = $item['key'];

            if ($newFilename !== '') {
                $safe = sanitize_filename($newFilename, 'preloader');
                $safe = preg_replace('/\.css$/i', '', $safe);
                if ($safe === '') $safe = 'preloader';
                $currentBase = pathinfo($item['file'], PATHINFO_FILENAME);
                if ($safe !== $currentBase) {
                    $oldFull = __DIR__ . '/../' . $item['file'];
                    $newRel  = dirname($item['file']) . '/' . $safe . '.css';
                    $newFull = __DIR__ . '/../' . $newRel;
                    if (!is_file($oldFull)) respond(false, '原文件不存在，无法重命名');
                    if (file_exists($newFull)) respond(false, '目标文件名已存在');
                    if (!@rename($oldFull, $newFull)) respond(false, '重命名失败，请检查权限');
                    @chmod($newFull, 0644);
                    $item['file'] = $newRel;
                    $newKey = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $safe);
                }
            }

            if ($newKey !== $item['key']) {
                foreach ($data['preloaders'] as $i => $p) {
                    if ($i !== $idx && $p['key'] === $newKey) respond(false, '新 key 与现有主题冲突');
                }
            }

            $oldKey = $data['preloaders'][$idx]['key'];
            $data['preloaders'][$idx]['file']        = $item['file'];
            $data['preloaders'][$idx]['key']         = $newKey;
            $data['preloaders'][$idx]['name']        = $name;
            $data['preloaders'][$idx]['description'] = $desc;

            if ($oldKey !== $newKey && ($data['site']['preloaderStyle'] ?? '') === $oldKey) {
                $data['site']['preloaderStyle'] = $newKey;
            }
            save_data($data);
            respond(true, '已保存', ['preloaders' => $data['preloaders']]);
            break;

        case 'delete_preloader':
            require_login();
            $key = $_POST['key'] ?? '';
            $data = load_data();
            $target = null;
            foreach ($data['preloaders'] as $p) { if ($p['key'] === $key) { $target = $p; break; } }
            if (!$target) respond(false, '主题不存在');
            $data['preloaders'] = array_values(array_filter($data['preloaders'], function($p) use ($key) { return $p['key'] !== $key; }));
            $base = realpath(PRELOADER_DIR); $full = realpath(__DIR__ . '/../' . $target['file']);
            if ($base !== false && $full !== false && strpos($full, $base . DIRECTORY_SEPARATOR) === 0 && is_file($full)) @unlink($full);

            if (($data['site']['preloaderStyle'] ?? '') === $key) {
                $data['site']['preloaderStyle'] = count($data['preloaders']) > 0 ? $data['preloaders'][0]['key'] : '';
            }
            save_data($data);
            respond(true, '已删除', ['preloaders' => $data['preloaders']]);
            break;

        case 'save_preloader_sort':
            require_login();
            $order = json_decode($_POST['keys'] ?? '', true);
            if (!is_array($order)) respond(false, '排序数据无效');
            $data = load_data();
            $byKey = [];
            foreach ($data['preloaders'] as $p) { $byKey[$p['key']] = $p; }
            $newList = [];
            foreach ($order as $k) { if (isset($byKey[$k])) { $newList[] = $byKey[$k]; unset($byKey[$k]); } }
            foreach ($byKey as $p) $newList[] = $p;
            $data['preloaders'] = $newList;
            if (count($newList) > 0) {
                $data['site']['preloaderStyle'] = $newList[0]['key'];
            }
            save_data($data);
            respond(true, '顺序已保存，首个主题已设为默认');
            break;

        /* ============================================================
           本地背景兜底
           ============================================================ */
        case 'auto_detect_local_bg':
            require_login();
            $type = (($_POST['type'] ?? 'bg') === 'banner') ? 'banner' : 'bg';
            $key  = ($type === 'bg') ? 'localBg' : 'localBanner';
            
            $data = load_data();
            if (!isset($data[$key]['images']) || !is_array($data[$key]['images']) || count($data[$key]['images']) === 0) {
                respond(false, '当前没有本地图片，无法自动分类');
            }
            
            $stats = ['desktop' => 0, 'mobile' => 0, 'both' => 0, 'failed' => 0, 'total' => 0];
            foreach ($data[$key]['images'] as $k => $item) {
                if (empty($item['file'])) { $stats['failed']++; continue; }
                $stats['total']++;
                $fullPath = __DIR__ . '/../' . $item['file'];
                if (!is_file($fullPath)) { $stats['failed']++; continue; }
                
                $info = @getimagesize($fullPath);
                if ($info !== false && $info[0] > 0 && $info[1] > 0) {
                    if ($info[0] > $info[1] * 1.1) $data[$key]['images'][$k]['target'] = 'desktop';
                    elseif ($info[1] > $info[0] * 1.1) $data[$key]['images'][$k]['target'] = 'mobile';
                    else $data[$key]['images'][$k]['target'] = 'both';
                    $stats[$data[$key]['images'][$k]['target']]++;
                } else {
                    $stats['failed']++;
                }
            }
            save_data($data);
            
            $success = $stats['desktop'] + $stats['mobile'] + $stats['both'];
            $parts = [];
            if ($stats['mobile'] > 0)  $parts[] = '📱 移动端（竖屏）' . $stats['mobile'] . ' 张';
            if ($stats['desktop'] > 0) $parts[] = '🖥 桌面端（横屏）' . $stats['desktop'] . ' 张';
            if ($stats['both'] > 0)    $parts[] = '🌐 通用' . $stats['both'] . ' 张';
            $detail = count($parts) > 0 ? '：' . implode('、', $parts) : '';
            
            if ($success === 0) {
                respond(false, '自动分类失败：未能识别任何图片' . ($stats['failed'] > 0 ? '（' . $stats['failed'] . ' 张图片无法读取）' : ''), [
                    'stats' => $stats
                ]);
            }
            
            $msg = '自动分类完成' . $detail;
            if ($stats['failed'] > 0) {
                $msg .= '，另有 ' . $stats['failed'] . ' 张读取失败';
            }
            
            respond(true, $msg, [
                'images' => decorate_images($data[$key]['images']),
                'stats'  => $stats
            ]);
            break;

        case 'upload_local_bg':
            require_login();
            $type = (($_POST['type'] ?? 'bg') === 'banner') ? 'banner' : 'bg';
            if (empty($_FILES['file'])) respond(false, '未收到文件');
            $file = $_FILES['file'];
            if ($file['error'] !== UPLOAD_ERR_OK) respond(false, '上传失败，错误码：' . $file['error']);
            if ($file['size'] > MAX_UPLOAD) respond(false, '文件超过 5MB 限制');
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            if (!in_array($ext, ALLOWED_EXT, true)) respond(false, '仅支持 jpg / png / gif / webp 格式');
            if (!@getimagesize($file['tmp_name'])) respond(false, '文件不是有效图片');
            $dir       = ($type === 'bg') ? LOCALBG_DIR : LOCALBANNER_DIR;
            $relPrefix = ($type === 'bg') ? 'photos/localbg/' : 'photos/localbanner/';
            $key       = ($type === 'bg') ? 'localBg' : 'localBanner';
            if (!is_dir($dir)) @mkdir($dir, 0755, true);
            $baseName = sanitize_filename(pathinfo($file['name'], PATHINFO_FILENAME), 'local');
            $newname  = unique_filename($dir, $baseName . '.' . $ext);
            $dest     = $dir . '/' . $newname;
            if (!@move_uploaded_file($file['tmp_name'], $dest)) respond(false, '保存文件失败，请检查目录写权限');
            @chmod($dest, 0644);

            $newFp = compute_local_fp($dest);
            if ($newFp === '') {
                @unlink($dest);
                respond(false, '无法计算文件指纹，请重试');
            }
            $data = load_data();
            if (!isset($data[$key]) || !is_array($data[$key])) $data[$key] = ['enabled' => true, 'images' => []];
            if (!isset($data[$key]['images']) || !is_array($data[$key]['images'])) $data[$key]['images'] = [];
            foreach ($data[$key]['images'] as $existing) {
                if (isset($existing['fp']) && $existing['fp'] === $newFp) {
                    @unlink($dest);
                    respond(false, '内容与已有图片「' . ($existing['name'] ?? '') . '」完全一致，请勿重复上传');
                }
            }

            $relNew = $relPrefix . $newname;
            
            $info = @getimagesize($dest);
            $target = 'both';
            if ($info !== false && $info[0] > 0 && $info[1] > 0) {
                if ($info[0] > $info[1] * 1.1) $target = 'desktop';
                elseif ($info[1] > $info[0] * 1.1) $target = 'mobile';
            }
            if ($type === 'banner') $target = 'desktop';

            $data[$key]['images'][] = ['file' => $relNew, 'name' => $baseName, 'fp' => $newFp, 'target' => $target];

            $thumbRel = thumb_path_for($relNew);
            if ($thumbRel !== '') make_thumbnail($dest, __DIR__ . '/../' . $thumbRel, THUMB_MAX_SIZE);

            save_data($data);
            respond(true, '上传成功', ['images' => decorate_images($data[$key]['images'])]);
            break;

        case 'delete_local_bg':
            require_login();
            $type = (($_POST['type'] ?? 'bg') === 'banner') ? 'banner' : 'bg';
            $file = trim((string)($_POST['file'] ?? ''));
            if ($file === '') respond(false, '缺少文件路径');
            $dir       = ($type === 'bg') ? LOCALBG_DIR : LOCALBANNER_DIR;
            $relPrefix = ($type === 'bg') ? 'photos/localbg/' : 'photos/localbanner/';
            $key       = ($type === 'bg') ? 'localBg' : 'localBanner';
            if (strpos($file, $relPrefix) !== 0 || strpos($file, '..') !== false) respond(false, '非法路径');
            $data = load_data();
            if (!isset($data[$key]['images']) || !is_array($data[$key]['images'])) $data[$key]['images'] = [];
            $before = count($data[$key]['images']);
            $data[$key]['images'] = array_values(array_filter($data[$key]['images'], function ($i) use ($file) {
                return ($i['file'] ?? '') !== $file;
            }));
            if (count($data[$key]['images']) === $before) respond(false, '图片不存在或已删除');

            $base = realpath($dir); $full = realpath(__DIR__ . '/../' . $file);
            if ($base !== false && $full !== false && strpos($full, $base . DIRECTORY_SEPARATOR) === 0 && is_file($full)) {
                delete_with_thumb($full, $file);
            } else {
                $thumbRel = thumb_path_for($file);
                if ($thumbRel !== '') {
                    $thumbAbs = __DIR__ . '/../' . $thumbRel;
                    if (is_file($thumbAbs)) @unlink($thumbAbs);
                }
            }

            save_data($data);
            respond(true, '已删除', ['images' => decorate_images($data[$key]['images'])]);
            break;

        case 'rename_local_bg':
            require_login();
            $type = (($_POST['type'] ?? 'bg') === 'banner') ? 'banner' : 'bg';
            $file = trim((string)($_POST['file'] ?? ''));
            $newName = trim((string)($_POST['name'] ?? ''));
            $newTarget = trim((string)($_POST['target'] ?? ''));
            
            if ($file === '') respond(false, '缺少文件路径');
            if ($newName === '') respond(false, '名称不能为空');
            if (preg_match('#[\\\\/:*?"<>|]#', $newName)) respond(false, '名称不能包含 \\ / : * ? " < > | 等字符');
            if (substr($newName, 0, 1) === '.') respond(false, '名称不能以点开头');
            if (mb_strlen($newName) > 60) respond(false, '名称过长（最多 60 字）');
            
            $dir       = ($type === 'bg') ? LOCALBG_DIR : LOCALBANNER_DIR;
            $relPrefix = ($type === 'bg') ? 'photos/localbg/' : 'photos/localbanner/';
            $key       = ($type === 'bg') ? 'localBg' : 'localBanner';
            if (strpos($file, $relPrefix) !== 0 || strpos($file, '..') !== false) respond(false, '非法路径');
            
            $base = realpath($dir); $oldReal = realpath(__DIR__ . '/../' . $file);
            if ($base === false || $oldReal === false || !is_file($oldReal)) respond(false, '文件不存在');
            if (strpos($oldReal, $base . DIRECTORY_SEPARATOR) !== 0) respond(false, '非法路径');
            
            $newName = sanitize_filename($newName, 'local');
            $ext = pathinfo($oldReal, PATHINFO_EXTENSION);
            $newFile = $newName . '.' . $ext;
            $newFull = $dir . '/' . $newFile;
            $newRel  = $relPrefix . $newFile;
            
            if ($newRel !== $file) {
                if (file_exists($newFull)) respond(false, '目标文件名已存在');
                if (!rename_with_thumb($oldReal, $newFull, $file, $newRel)) respond(false, '重命名失败，请检查权限');
            }
            
            $data = load_data();
            if (!isset($data[$key]['images']) || !is_array($data[$key]['images'])) $data[$key]['images'] = [];
            $found = false;
            foreach ($data[$key]['images'] as $k => $item) {
                if (($item['file'] ?? '') === $file) {
                    $data[$key]['images'][$k]['file'] = $newRel;
                    $data[$key]['images'][$k]['name'] = $newName;
                    
                    if ($newTarget === 'auto') {
                        $info = @getimagesize($oldReal);
                        if ($info !== false && $info[0] > 0 && $info[1] > 0) {
                            if ($info[0] > $info[1] * 1.1) $data[$key]['images'][$k]['target'] = 'desktop';
                            elseif ($info[1] > $info[0] * 1.1) $data[$key]['images'][$k]['target'] = 'mobile';
                            else $data[$key]['images'][$k]['target'] = 'both';
                        } else {
                            $data[$key]['images'][$k]['target'] = 'both';
                        }
                    } elseif (in_array($newTarget, ['desktop', 'mobile', 'both'], true)) {
                        $data[$key]['images'][$k]['target'] = $newTarget;
                    }
                    
                    $found = true; break;
                }
            }
            if (!$found) respond(false, '图片不存在');
            save_data($data);
            respond(true, '已重命名', ['images' => decorate_images($data[$key]['images'])]);
            break;

        case 'save_local_bg_sort':
            require_login();
            $type = (($_POST['type'] ?? 'bg') === 'banner') ? 'banner' : 'bg';
            $key  = ($type === 'bg') ? 'localBg' : 'localBanner';
            $order = json_decode($_POST['files'] ?? '', true);
            if (!is_array($order)) respond(false, '排序数据无效');
            $data = load_data();
            if (!isset($data[$key]['images']) || !is_array($data[$key]['images'])) $data[$key]['images'] = [];
            $byFile = [];
            foreach ($data[$key]['images'] as $item) { if (isset($item['file'])) $byFile[$item['file']] = $item; }
            $newList = [];
            foreach ($order as $f) { if (isset($byFile[$f])) { $newList[] = $byFile[$f]; unset($byFile[$f]); } }
            foreach ($byFile as $item) $newList[] = $item;
            $data[$key]['images'] = $newList;
            save_data($data);
            respond(true, '顺序已保存', ['images' => $newList]);
            break;

        case 'toggle_local_bg':
            require_login();
            $type = (($_POST['type'] ?? 'bg') === 'banner') ? 'banner' : 'bg';
            $key  = ($type === 'bg') ? 'localBg' : 'localBanner';
            $data = load_data();
            if (!isset($data[$key]) || !is_array($data[$key])) $data[$key] = ['enabled' => true, 'images' => []];
            $data[$key]['enabled'] = !empty($_POST['enabled']);
            save_data($data);
            respond(true, $data[$key]['enabled'] ? '已启用' : '已禁用');
            break;

        case 'fetch_local_bg_from_api':
            require_login();
            $type = (($_POST['type'] ?? 'bg') === 'banner') ? 'banner' : 'bg';
            $count = (int)($_POST['count'] ?? 5);
            if ($count < 1)  $count = 1;
            if ($count > 20) $count = 20;

            $data = load_data();
            $apiKey = ($type === 'bg') ? 'bgApi' : 'bannerApi';
            $apiUrl = trim((string)($data['site'][$apiKey] ?? ''));
            if ($apiUrl === '') respond(false, '请先在站点设置中配置' . ($type === 'bg' ? '背景' : 'Banner') . '轮播 API');

            $ctx = build_fetch_ctx($type, $data);

            $dir       = ($type === 'bg') ? LOCALBG_DIR : LOCALBANNER_DIR;
            $relPrefix = ($type === 'bg') ? 'photos/localbg/' : 'photos/localbanner/';
            $key       = ($type === 'bg') ? 'localBg' : 'localBanner';
            if (!is_dir($dir)) @mkdir($dir, 0755, true);

            $existingFps = [];
            if (isset($data[$key]['images']) && is_array($data[$key]['images'])) {
                foreach ($data[$key]['images'] as $item) {
                    if (isset($item['fp']) && is_string($item['fp']) && $item['fp'] !== '') {
                        $existingFps[$item['fp']] = $item['name'] ?? '';
                    }
                }
            }

            $newFileTargets = [];
            $saved = 0; $failed = 0; $duplicated = 0;
            for ($i = 0; $i < $count; $i++) {
                $sep = strpos($apiUrl, '?') !== false ? '&' : '?';
                $url = $apiUrl . $sep . '_t=' . time() . mt_rand(100, 999) . '&_rand=' . bin2hex(random_bytes(4));
                $raw = @file_get_contents($url, false, $ctx);
                if ($raw === false || strlen($raw) < 100) { $failed++; continue; }

                $tmpFp = compute_local_fp_from_string($raw);
                if ($tmpFp !== '' && isset($existingFps[$tmpFp])) {
                    $duplicated++;
                    continue;
                }

                $ext = 'jpg';
                $target = 'both';
                $info = @getimagesizefromstring($raw);
                if ($info !== false && !empty($info['mime'])) {
                    $mime = strtolower($info['mime']);
                    if (strpos($mime, 'png')  !== false) $ext = 'png';
                    elseif (strpos($mime, 'gif')  !== false) $ext = 'gif';
                    elseif (strpos($mime, 'webp') !== false) $ext = 'webp';
                    elseif (strpos($mime, 'jpeg') !== false) $ext = 'jpg';
                    elseif (strpos($mime, 'jpg')  !== false) $ext = 'jpg';

                    if ($info[0] > $info[1] * 1.1) $target = 'desktop';
                    elseif ($info[1] > $info[0] * 1.1) $target = 'mobile';
                }
                if ($type === 'banner') $target = 'desktop';

                $prefix   = ($type === 'bg') ? 'localbg_' : 'localbanner_';
                $baseName = $prefix . date('Ymd_His') . '_' . bin2hex(random_bytes(3));
                $newname  = unique_filename($dir, $baseName . '.' . $ext);
                $dest     = $dir . '/' . $newname;
                if (@file_put_contents($dest, $raw) === false) { $failed++; continue; }
                @chmod($dest, 0644);
                $newFileTargets[$relPrefix . $newname] = $target;

                $thumbRel = thumb_path_for($relPrefix . $newname);
                if ($thumbRel !== '') {
                    make_thumbnail($dest, __DIR__ . '/../' . $thumbRel, THUMB_MAX_SIZE);
                }

                $finalFp = compute_local_fp($dest);
                if ($finalFp !== '') {
                    $existingFps[$finalFp] = $baseName;
                }
                $saved++;
            }

            if ($saved === 0) {
                if ($duplicated > 0 && $failed === 0) {
                    respond(false, '本次抓取到的 ' . $duplicated . ' 张图片都与已有内容重复');
                }
                respond(false, '抓取失败，未能保存任何图片（API 可能不可用）');
            }

            $data = load_data();
            sync_local_images($data, $type, $newFileTargets);
            save_data($data);
            $msg = '成功抓取 ' . $saved . ' 张';
            if ($duplicated > 0) $msg .= '，去重 ' . $duplicated . ' 张';
            if ($failed > 0) $msg .= '，失败 ' . $failed . ' 张';
            respond(true, $msg, ['images' => decorate_images($data[$key]['images'])]);
            break;

        /* ============================================================
           UA 模板管理
           ============================================================ */
        case 'add_ua_template':
            require_login();
            $name = trim($_POST['name'] ?? '');
            $ua   = trim($_POST['ua'] ?? '');
            if ($name === '') respond(false, '模板名称不能为空');
            if ($ua === '')   respond(false, 'User-Agent 不能为空');
            $data = load_data();
            if (!isset($data['uaTemplates']) || !is_array($data['uaTemplates'])) $data['uaTemplates'] = [];
            $tpl = [
                'id'             => uniqid('ua_'),
                'name'           => $name,
                'ua'             => $ua,
                'accept'         => trim((string)($_POST['accept'] ?? '')),
                'acceptLanguage' => trim((string)($_POST['acceptLanguage'] ?? '')),
                'acceptEncoding' => trim((string)($_POST['acceptEncoding'] ?? '')),
                'extraHeaders'   => (string)($_POST['extraHeaders'] ?? ''),
                'builtin'        => false,
            ];
            $data['uaTemplates'][] = $tpl;
            save_data($data);
            respond(true, '模板已添加', ['templates' => $data['uaTemplates'], 'template' => $tpl]);
            break;

        case 'save_ua_template':
            require_login();
            $id = trim($_POST['id'] ?? '');
            if ($id === '') respond(false, '缺少模板 ID');
            $name = trim($_POST['name'] ?? '');
            $ua   = trim($_POST['ua'] ?? '');
            if ($name === '') respond(false, '模板名称不能为空');
            if ($ua === '')   respond(false, 'User-Agent 不能为空');
            $data = load_data();
            $idx = -1;
            foreach ($data['uaTemplates'] as $i => $t) {
                if (($t['id'] ?? '') === $id) { $idx = $i; break; }
            }
            if ($idx === -1) respond(false, '模板不存在');
            $data['uaTemplates'][$idx]['name']           = $name;
            $data['uaTemplates'][$idx]['ua']             = $ua;
            $data['uaTemplates'][$idx]['accept']         = trim((string)($_POST['accept'] ?? ''));
            $data['uaTemplates'][$idx]['acceptLanguage'] = trim((string)($_POST['acceptLanguage'] ?? ''));
            $data['uaTemplates'][$idx]['acceptEncoding'] = trim((string)($_POST['acceptEncoding'] ?? ''));
            $data['uaTemplates'][$idx]['extraHeaders']   = (string)($_POST['extraHeaders'] ?? '');
            save_data($data);
            respond(true, '模板已保存', ['templates' => $data['uaTemplates']]);
            break;

        case 'delete_ua_template':
            require_login();
            $id = trim($_POST['id'] ?? '');
            if ($id === '') respond(false, '缺少模板 ID');
            $data = load_data();
            $target = null;
            foreach ($data['uaTemplates'] as $t) {
                if (($t['id'] ?? '') === $id) { $target = $t; break; }
            }
            if (!$target) respond(false, '模板不存在');
            if (!empty($target['builtin'])) respond(false, '内置模板不允许删除，可以编辑它的内容');
            $data['uaTemplates'] = array_values(array_filter($data['uaTemplates'], function($t) use ($id) {
                return ($t['id'] ?? '') !== $id;
            }));
            if (($data['site']['localBgUaId'] ?? '') === $id)     $data['site']['localBgUaId'] = 'ua_mobile_default';
            if (($data['site']['localBannerUaId'] ?? '') === $id) $data['site']['localBannerUaId'] = 'ua_desktop_default';
            save_data($data);
            respond(true, '模板已删除', ['templates' => $data['uaTemplates']]);
            break;

        case 'set_fetch_ua':
            require_login();
            $type = (($_POST['type'] ?? 'bg') === 'banner') ? 'banner' : 'bg';
            $templateId = trim($_POST['templateId'] ?? '');
            if ($templateId === '') respond(false, '缺少模板 ID');
            $data = load_data();
            $found = false;
            foreach ($data['uaTemplates'] as $t) {
                if (($t['id'] ?? '') === $templateId) { $found = true; break; }
            }
            if (!$found) respond(false, '模板不存在');
            if ($type === 'bg') {
                $data['site']['localBgUaId'] = $templateId;
            } else {
                $data['site']['localBannerUaId'] = $templateId;
            }
            save_data($data);
            respond(true, '已切换抓取 UA 模板', ['templateId' => $templateId]);
            break;

        /* ============================================================
           看板娘 / 语录
           ============================================================ */
        case 'toggle_board_girl':
            require_login();
            $enabled = !empty($_POST['enabled']);
            $data = load_data();
            $data['boardGirl']['enabled'] = $enabled;
            save_data($data);
            respond(true, $enabled ? '看板娘已开启' : '看板娘已关闭');
            break;

        case 'upload_girl':
            require_login();
            if (empty($_FILES['file'])) respond(false, '未收到文件');
            $file = $_FILES['file'];
            if ($file['error'] !== UPLOAD_ERR_OK) respond(false, '上传失败，错误码：' . $file['error']);
            if ($file['size'] > MAX_GIF_UPLOAD) respond(false, '文件超过 10MB 限制');
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            if (!in_array($ext, ALLOWED_GIF_EXT, true)) respond(false, '仅支持 gif / png / webp 格式');
            if (!@getimagesize($file['tmp_name'])) respond(false, '文件不是有效图片');
            if (!is_dir(GIRL_DIR)) @mkdir(GIRL_DIR, 0755, true);
            $baseName = sanitize_filename(pathinfo($file['name'], PATHINFO_FILENAME), 'girl');
            $newname  = unique_filename(GIRL_DIR, $baseName . '.' . $ext);
            $dest = GIRL_DIR . '/' . $newname;
            if (!@move_uploaded_file($file['tmp_name'], $dest)) respond(false, '保存失败，请检查 photos/kanban 目录写权限');
            @chmod($dest, 0644);
            $path = 'photos/kanban/' . $newname;

            $thumbRel = thumb_path_for($path);
            if ($thumbRel !== '') make_thumbnail($dest, __DIR__ . '/../' . $thumbRel, THUMB_MAX_SIZE);

            $data = load_data();
            $girl = ['file' => $path, 'name' => $baseName];
            $data['boardGirl']['girls'][] = $girl;
            save_data($data);
            respond(true, '上传成功', ['girl' => $girl, 'girls' => decorate_images($data['boardGirl']['girls'])]);
            break;

        case 'rename_girl':
            require_login();
            $oldFile = trim($_POST['file'] ?? ''); $newName = trim($_POST['name'] ?? '');
            if ($oldFile === '') respond(false, '缺少文件路径');
            if ($newName === '') respond(false, '名称不能为空');
            if (strpos($oldFile, 'photos/kanban/') !== 0) respond(false, '只能重命名 photos/kanban/ 下的文件');
            if (strpos($oldFile, '..') !== false) respond(false, '非法路径');
            if (preg_match('#[\\\\/:*?"<>|]#', $newName)) respond(false, '名称不能包含 \\ / : * ? " < > | 等字符');
            if (substr($newName, 0, 1) === '.') respond(false, '名称不能以点开头');
            if (mb_strlen($newName) > 60) respond(false, '名称过长（最多 60 字）');
            $base = realpath(GIRL_DIR); $oldReal = realpath(__DIR__ . '/../' . $oldFile);
            if ($base === false || $oldReal === false) respond(false, '文件不存在');
            if (strpos($oldReal, $base . DIRECTORY_SEPARATOR) !== 0) respond(false, '非法路径');
            if (!is_file($oldReal)) respond(false, '文件不存在');
            $ext = pathinfo($oldReal, PATHINFO_EXTENSION);
            $newFile = $newName . '.' . $ext; $newFull = GIRL_DIR . '/' . $newFile; $newRel = 'photos/kanban/' . $newFile;
            $data = load_data();
            if ($newRel !== $oldFile) {
                if (file_exists($newFull)) respond(false, '目标文件名已存在');
                if (!rename_with_thumb($oldReal, $newFull, $oldFile, $newRel)) respond(false, '重命名失败，请检查权限');
            }
            $found = false;
            foreach ($data['boardGirl']['girls'] as $k => $g) {
                if ($g['file'] === $oldFile) { $data['boardGirl']['girls'][$k]['file'] = $newRel; $data['boardGirl']['girls'][$k]['name'] = $newName; $found = true; break; }
            }
            if (!$found) respond(false, '形象不存在');
            save_data($data);
            respond(true, '重命名成功', ['old' => $oldFile, 'new' => $newRel, 'girls' => decorate_images($data['boardGirl']['girls'])]);
            break;

        case 'delete_girl':
            require_login();
            $file = $_POST['file'] ?? '';
            if ($file === '') respond(false, '缺少文件路径');
            $data = load_data();
            $before = count($data['boardGirl']['girls']);
            $data['boardGirl']['girls'] = array_values(array_filter($data['boardGirl']['girls'], function($g) use ($file) { return $g['file'] !== $file; }));
            if (count($data['boardGirl']['girls']) === $before) respond(false, '形象不存在或已删除');

            $base = realpath(GIRL_DIR); $full = realpath(__DIR__ . '/../' . $file);
            if ($base !== false && $full !== false && strpos($full, $base . DIRECTORY_SEPARATOR) === 0 && is_file($full)) {
                delete_with_thumb($full, $file);
            } else {
                $thumbRel = thumb_path_for($file);
                if ($thumbRel !== '') {
                    $thumbAbs = __DIR__ . '/../' . $thumbRel;
                    if (is_file($thumbAbs)) @unlink($thumbAbs);
                }
            }

            save_data($data);
            respond(true, '已删除', ['girls' => decorate_images($data['boardGirl']['girls'])]);
            break;

        case 'save_girl_sort':
            require_login();
            $order = json_decode($_POST['files'] ?? '', true);
            if (!is_array($order)) respond(false, '排序数据无效');
            $data = load_data(); $byFile = [];
            foreach ($data['boardGirl']['girls'] as $g) { if (isset($g['file'])) $byFile[$g['file']] = $g; }
            $newGirls = [];
            foreach ($order as $f) { if (isset($byFile[$f])) { $newGirls[] = $byFile[$f]; unset($byFile[$f]); } }
            foreach ($byFile as $g) $newGirls[] = $g;
            $data['boardGirl']['girls'] = $newGirls;
            save_data($data);
            respond(true, '看板娘顺序已保存');
            break;

        case 'save_tips':
            require_login();
            $data = load_data(); $tipsDef = tips_defaults();
            if (!isset($data['tips']) || !is_array($data['tips'])) $data['tips'] = $tipsDef;
            $data['tips']['enabled'] = !empty($_POST['enabled']);

            $durSec = isset($_POST['duration']) ? (float)$_POST['duration'] : ($tipsDef['duration'] / 1000);
            $dur = (int)round($durSec * 1000);
            if ($dur < 500)   $dur = 500;
            if ($dur > 60000) $dur = 60000;
            $data['tips']['duration'] = $dur;

            $textsRaw = (string)($_POST['texts'] ?? ''); $textsArr = [];
            foreach (preg_split('/\r\n|\r|\n/', $textsRaw) as $line) { $line = trim($line); if ($line !== '') $textsArr[] = $line; }
            $data['tips']['texts'] = count($textsArr) > 0 ? $textsArr : $tipsDef['texts'];
            foreach (['night','morning','noon','afternoon','evening'] as $p) {
                $raw = (string)($_POST['period_' . $p] ?? ''); $arr = [];
                foreach (preg_split('/\r\n|\r|\n/', $raw) as $line) { $line = trim($line); if ($line !== '') $arr[] = $line; }
                $data['tips']['periodTexts'][$p] = count($arr) > 0 ? $arr : $tipsDef['periodTexts'][$p];
            }
            $api = trim((string)($_POST['api'] ?? ''));
            if ($api !== '' && !preg_match('#^https?://#i', $api)) respond(false, '远程语录 API 必须以 http:// 或 https:// 开头');
            $data['tips']['api'] = $api;
            $field = trim((string)($_POST['apiField'] ?? 'text'));
            $data['tips']['apiField'] = $field !== '' ? $field : 'text';
            save_data($data);
            respond(true, '语录设置已保存');
            break;

        /* ============================================================
           站点设置 / 备份 / 清除 / 账号
           ============================================================ */
        case 'save_site':
            require_login();
            $data = load_data();
            if (!isset($data['site']) || !is_array($data['site'])) $data['site'] = [];
            $fields = [
                'pageTitle','title','subtitle','avatar','favicon','bgApi','bannerApi',
                'bgInterval','bannerInterval','cacheLimit',
                'adminAvatar','adminTitle','adminSubtitle','adminPageTitle','adminHeaderTitle',
                'adminClickCount','adminClickWindow',
                'localBgPriority','localBannerPriority',
                'copyright','githubUrl',
                'poweredBy','poweredByUrl',
            ];
            foreach ($fields as $f) {
                if (array_key_exists($f, $_POST)) $data['site'][$f] = trim((string)$_POST[$f]);
            }

            foreach (['localBgPriority', 'localBannerPriority'] as $pk) {
                if (isset($data['site'][$pk]) && !in_array($data['site'][$pk], ['api', 'local'], true)) {
                    $data['site'][$pk] = 'api';
                }
            }

            if (array_key_exists('githubEnabled', $_POST)) {
                $data['site']['githubEnabled'] = $_POST['githubEnabled'] === '1' ? '1' : '0';
            }

            foreach (['githubUrl', 'poweredByUrl'] as $f) {
                $v = trim((string)($data['site'][$f] ?? ''));
                if ($v !== '' && !preg_match('#^https?://#i', $v)) {
                    respond(false, ($f === 'githubUrl' ? 'GitHub' : 'Powered by') . ' 链接必须以 http:// 或 https:// 开头');
                }
            }

            if (array_key_exists('analyticsCode', $_POST)) {
                $analyticsCode = (string)$_POST['analyticsCode'];
                $analyticsCode = str_replace(["\r\n", "\r"], "\n", $analyticsCode);
                $analyticsCode = trim($analyticsCode);

                if ($analyticsCode !== '') {
                    if (!preg_match('/<[a-zA-Z][a-zA-Z0-9]*(\s[^>]*)?>/i', $analyticsCode)) {
                        respond(false, '统计代码格式无效：未检测到 HTML 标签，请粘贴服务商提供的完整代码片段');
                    }
                    if (stripos($analyticsCode, '<?php') !== false || stripos($analyticsCode, '<?=') !== false) {
                        respond(false, '统计代码格式无效：不允许包含 PHP 标签');
                    }
                }

                $data['site']['analyticsCode'] = $analyticsCode;
            }

            if (isset($_POST['preloader'])) {
                $data['site']['preloader'] = $_POST['preloader'] === '1' ? '1' : '0';
            }

            foreach (['bgApi','bannerApi'] as $f) {
                $v = (string)($data['site'][$f] ?? '');
                if ($v !== '' && !preg_match('#^https?://#i', $v)) respond(false, '轮播接口地址必须以 http:// 或 https:// 开头');
            }

            $bgInt = (int)($data['site']['bgInterval'] ?? 10);
            if ($bgInt < 3) $bgInt = 3; if ($bgInt > 60) $bgInt = 60;
            $data['site']['bgInterval'] = (string)$bgInt;

            $bannerInt = (int)($data['site']['bannerInterval'] ?? 6);
            if ($bannerInt < 3) $bannerInt = 3; if ($bannerInt > 60) $bannerInt = 60;
            $data['site']['bannerInterval'] = (string)$bannerInt;

            $cacheLimit = (int)($data['site']['cacheLimit'] ?? 8);
            if ($cacheLimit < 2) $cacheLimit = 2; if ($cacheLimit > 20) $cacheLimit = 20;
            $data['site']['cacheLimit'] = (string)$cacheLimit;

            $cnt = (int)($data['site']['adminClickCount'] ?? 10);
            if ($cnt < 3) $cnt = 3; if ($cnt > 30) $cnt = 30;
            $data['site']['adminClickCount'] = (string)$cnt;
            $win = (float)($data['site']['adminClickWindow'] ?? 10);
            if ($win < 1) $win = 1; if ($win > 60) $win = 60;
            $data['site']['adminClickWindow'] = rtrim(rtrim(number_format($win, 1, '.', ''), '0'), '.');
            save_data($data);
            respond(true, '设置已保存');
            break;

        case 'import':
            require_login();
            if (empty($_FILES['backup'])) respond(false, '未选择备份文件');
            $file = $_FILES['backup'];
            if ($file['error'] !== UPLOAD_ERR_OK) respond(false, '上传失败，错误码：' . $file['error']);
            $raw = @file_get_contents($file['tmp_name']);
            if ($raw === false) respond(false, '无法读取备份文件');
            $data = json_decode($raw, true);
            if (!is_array($data)) respond(false, '备份文件不是有效的 JSON');
            $data = normalize_data($data);
            save_data($data);
            respond(true, '导入成功，已完整覆盖现有数据', ['cards' => $data['cards'], 'categories' => $data['categories']]);
            break;

        case 'export':
            if (!is_login()) { header('Location: ' . $_SERVER['PHP_SELF']); exit; }
            if (!file_exists(DATA_FILE)) respond(false, '数据文件不存在');
            $raw = @file_get_contents(DATA_FILE);
            if ($raw === false) respond(false, '无法读取数据文件');
            ob_end_clean();
            header('Content-Type: application/json; charset=utf-8');
            header('Content-Disposition: attachment; filename="nuomi_backup_' . date('Ymd_His') . '.json"');
            echo $raw;
            exit;

        case 'reset_data':
            require_login();
            $mode = $_POST['mode'] ?? 'partial';

            if ($mode === 'partial') {
                $modulesRaw = $_POST['modules'] ?? '[]';
                $modules = json_decode($modulesRaw, true);
                if (!is_array($modules) || count($modules) === 0) respond(false, '请至少选择一个要清除的内容');
                $validModules = ['cards', 'photos', 'effects', 'girls', 'tips', 'site', 'preloaders', 'localbg', 'ua', 'announcements'];
                $modules = array_values(array_intersect($modules, $validModules));
                if (count($modules) === 0) respond(false, '未选择有效的清除模块');
                $data = load_data(); $summary = [];
                if (in_array('cards', $modules, true)) { $data['categories'] = []; $data['cards'] = []; $summary[] = '卡片与分类'; }
                if (in_array('photos', $modules, true)) {
                    $cnt = purge_directory_files(PHOTO_DIR);
                    purge_directory_files(PHOTO_THUMB_DIR);
                    $data['photoOrder'] = [];
                    $summary[] = '图片（' . $cnt . '）';
                }
                if (in_array('effects', $modules, true)) { $cnt = purge_directory_files(JS_DIR); $data['jsEffects'] = []; $summary[] = 'JS特效（' . $cnt . '）'; }
                if (in_array('girls', $modules, true)) {
                    $cnt = purge_directory_files(GIRL_DIR);
                    purge_directory_files(GIRL_THUMB_DIR);
                    $data['boardGirl']['girls'] = [];
                    $summary[] = '看板娘（' . $cnt . '）';
                }
                if (in_array('tips', $modules, true)) { $data['tips'] = tips_defaults(); $summary[] = '语录设置'; }
                if (in_array('site', $modules, true)) { $data['site'] = site_defaults(); $summary[] = '站点设置'; }
                if (in_array('preloaders', $modules, true)) {
                    $cnt = purge_directory_files(PRELOADER_DIR);
                    $data['preloaders'] = factory_defaults()['preloaders'];
                    $data['site']['preloaderStyle'] = 'elegant';
                    $summary[] = '加载页主题（' . $cnt . '）';
                }
                if (in_array('localbg', $modules, true)) {
                    $c1 = purge_directory_files(LOCALBG_DIR);
                    $c2 = purge_directory_files(LOCALBANNER_DIR);
                    purge_directory_files(LOCALBG_THUMB_DIR);
                    purge_directory_files(LOCALBANNER_THUMB_DIR);
                    $data['localBg']     = ['enabled' => true, 'images' => []];
                    $data['localBanner'] = ['enabled' => true, 'images' => []];
                    $summary[] = '本地背景（' . ($c1 + $c2) . '）';
                }
                if (in_array('ua', $modules, true)) {
                    $builtin = [];
                    foreach ($data['uaTemplates'] as $t) {
                        if (!empty($t['builtin'])) $builtin[] = $t;
                    }
                    $data['uaTemplates'] = $builtin;
                    $data['site']['localBgUaId'] = 'ua_mobile_default';
                    $data['site']['localBannerUaId'] = 'ua_desktop_default';
                    $summary[] = 'UA 模板（仅保留内置）';
                }
                if (in_array('announcements', $modules, true)) {
                    $data['announcements'] = [];
                    $summary[] = '公告';
                }
                save_data($data);
                respond(true, '已清除：' . implode('、', $summary));
            }

            if ($mode === 'all') {
                $def = factory_defaults();
                $def = normalize_data($def);
                save_data($def);
                $jsCount = purge_directory_files(JS_DIR);
                $girlCount = purge_directory_files(GIRL_DIR);
                $preCount = purge_directory_files(PRELOADER_DIR);
                $localBg1 = purge_directory_files(LOCALBG_DIR);
                $localBg2 = purge_directory_files(LOCALBANNER_DIR);
                purge_directory_files(PHOTO_THUMB_DIR);
                purge_directory_files(GIRL_THUMB_DIR);
                purge_directory_files(LOCALBG_THUMB_DIR);
                purge_directory_files(LOCALBANNER_THUMB_DIR);
                respond(true, '已恢复出厂设置', ['purgedJs' => $jsCount, 'purgedGirls' => $girlCount, 'purgedPreloaders' => $preCount, 'purgedLocal' => $localBg1 + $localBg2]);
            }

            if ($mode === 'full') {
                $def = factory_defaults();
                $def = normalize_data($def);
                save_data($def);
                $photoCount = purge_directory_files(PHOTO_DIR);
                $girlCount = purge_directory_files(GIRL_DIR);
                $jsCount = purge_directory_files(JS_DIR);
                $preCount = purge_directory_files(PRELOADER_DIR);
                $localBgCount = purge_directory_files(LOCALBG_DIR);
                $localBannerCount = purge_directory_files(LOCALBANNER_DIR);
                purge_directory_files(PHOTO_THUMB_DIR);
                purge_directory_files(GIRL_THUMB_DIR);
                purge_directory_files(LOCALBG_THUMB_DIR);
                purge_directory_files(LOCALBANNER_THUMB_DIR);
                if (file_exists(ADMIN_CONF)) @unlink(ADMIN_CONF);
                $_SESSION = [];
                if (ini_get('session.use_cookies')) {
                    $p = session_get_cookie_params();
                    setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
                }
                session_destroy();
                respond(true, '已完全清空所有配置，系统已恢复为全新安装状态', [
                    'purgedPhotos'    => $photoCount,
                    'purgedGirls'     => $girlCount,
                    'purgedJs'        => $jsCount,
                    'purgedPreloaders'=> $preCount,
                    'purgedLocalBg'   => $localBgCount,
                    'purgedLocalBanner' => $localBannerCount,
                    'needInit'        => true
                ]);
            }
            respond(false, '未知的清除模式：' . $mode);
            break;

        case 'change_password':
            require_login();
            $old = $_POST['old_password'] ?? ''; $new = $_POST['new_password'] ?? ''; $new2 = $_POST['new_password2'] ?? '';
            if ($new === '' || $new2 === '') respond(false, '请填写新密码');
            if ($new !== $new2) respond(false, '两次输入的新密码不一致');
            if (strlen($new) < 6) respond(false, '新密码至少需要 6 位');
            $conf = read_conf();
            if (!$conf || !password_verify($old, $conf['hash'] ?? '')) respond(false, '原密码错误');
            $conf['hash'] = password_hash($new, PASSWORD_DEFAULT);
            $conf['updated'] = date('c');
            write_conf($conf);
            respond(true, '密码修改成功');
            break;

        case 'change_username':
            require_login();
            $new = trim($_POST['new_username'] ?? ''); $pwd = $_POST['password'] ?? '';
            if ($new === '') respond(false, '新用户名不能为空');
            if (mb_strlen($new) > 30) respond(false, '用户名过长（最多 30 字）');
            if (preg_match('#[\s<>"\'\\\\]#u', $new)) respond(false, '用户名不能包含空格、引号、反斜杠或尖括号');
            $conf = read_conf();
            if (!$conf || !password_verify($pwd, $conf['hash'] ?? '')) respond(false, '当前密码错误');
            if ($new === ($conf['username'] ?? '')) respond(false, '新用户名与当前相同');
            $conf['username'] = $new; $conf['updated'] = date('c');
            write_conf($conf);
            respond(true, '用户名已修改');
            break;

        /* ============================================================
           公告管理
           ============================================================ */
        case 'save_announcement':
            require_login();
            $id      = trim((string)($_POST['id'] ?? ''));
            $title   = trim((string)($_POST['title'] ?? ''));
            $content = (string)($_POST['content'] ?? '');
            $type    = strtolower(trim((string)($_POST['type'] ?? 'info')));
            $pinned  = !empty($_POST['pinned']);
            $enabled = !empty($_POST['enabled']);

            if ($title === '') respond(false, '公告标题不能为空');
            if (mb_strlen($title) > 100) respond(false, '标题过长（最多 100 字）');
            if (!in_array($type, ['info','success','warning','error'], true)) $type = 'info';
            if (stripos($content, '<script') !== false || stripos($content, '</script') !== false) {
                respond(false, '公告内容不允许包含 <script> 标签');
            }

            $data = load_data();
            if (!isset($data['announcements']) || !is_array($data['announcements'])) {
                $data['announcements'] = [];
            }

            if ($id === '') {
                $newAnn = [
                    'id'      => uniqid('ann_'),
                    'title'   => $title,
                    'content' => $content,
                    'type'    => $type,
                    'pinned'  => $pinned,
                    'enabled' => $enabled,
                    'created' => date('Y-m-d H:i:s'),
                    'updated' => date('Y-m-d H:i:s'),
                ];
                $data['announcements'][] = $newAnn;
                save_data($data);
                respond(true, '公告已添加', ['announcement' => $newAnn, 'announcements' => $data['announcements']]);
            } else {
                $found = false;
                foreach ($data['announcements'] as $k => $a) {
                    if (($a['id'] ?? '') === $id) {
                        $data['announcements'][$k]['title']   = $title;
                        $data['announcements'][$k]['content'] = $content;
                        $data['announcements'][$k]['type']    = $type;
                        $data['announcements'][$k]['pinned']  = $pinned;
                        $data['announcements'][$k]['enabled'] = $enabled;
                        $data['announcements'][$k]['updated'] = date('Y-m-d H:i:s');
                        $found = true;
                        break;
                    }
                }
                if (!$found) respond(false, '公告不存在');
                save_data($data);
                respond(true, '公告已保存', ['announcements' => $data['announcements']]);
            }
            break;

        case 'delete_announcement':
            require_login();
            $id = trim((string)($_POST['id'] ?? ''));
            if ($id === '') respond(false, '缺少公告 ID');
            $data = load_data();
            if (!isset($data['announcements']) || !is_array($data['announcements'])) {
                $data['announcements'] = [];
            }
            $before = count($data['announcements']);
            $data['announcements'] = array_values(array_filter($data['announcements'], function ($a) use ($id) {
                return ($a['id'] ?? '') !== $id;
            }));
            if (count($data['announcements']) === $before) respond(false, '公告不存在或已删除');
            save_data($data);
            respond(true, '公告已删除', ['announcements' => $data['announcements']]);
            break;

        case 'toggle_announcement':
            require_login();
            $id      = trim((string)($_POST['id'] ?? ''));
            $enabled = !empty($_POST['enabled']);
            if ($id === '') respond(false, '缺少公告 ID');
            $data = load_data();
            $found = false;
            foreach ($data['announcements'] as $k => $a) {
                if (($a['id'] ?? '') === $id) {
                    $data['announcements'][$k]['enabled'] = $enabled;
                    $data['announcements'][$k]['updated'] = date('Y-m-d H:i:s');
                    $found = true;
                    break;
                }
            }
            if (!$found) respond(false, '公告不存在');
            save_data($data);
            respond(true, $enabled ? '已启用' : '已禁用');
            break;

        case 'toggle_announcement_pin':
            require_login();
            $id     = trim((string)($_POST['id'] ?? ''));
            $pinned = !empty($_POST['pinned']);
            if ($id === '') respond(false, '缺少公告 ID');
            $data = load_data();
            $found = false;
            foreach ($data['announcements'] as $k => $a) {
                if (($a['id'] ?? '') === $id) {
                    $data['announcements'][$k]['pinned'] = $pinned;
                    $found = true;
                    break;
                }
            }
            if (!$found) respond(false, '公告不存在');
            save_data($data);
            respond(true, $pinned ? '已置顶' : '已取消置顶');
            break;

        /* ============================================================
           在线升级
           ============================================================ */
        case 'update_check':
            require_login();
            $env = update_env_check();
            $release = update_fetch_release(!empty($_POST['force']));
            if (!$release['ok']) {
                respond(false, $release['msg'], ['env' => $env]);
            }
            $data = $release['data'];
            $latestVersion = ltrim((string)$data['version'], 'vV');
            $hasUpdate = version_compare($latestVersion, NUOMI_VERSION, '>');
            respond(true, '检查完成', [
                'current_version' => NUOMI_VERSION,
                'latest_version' => $latestVersion,
                'has_update' => $hasUpdate,
                'release_url' => $data['html_url'] ?? '',
                'release_body' => $data['body'] ?? '',
                'release_published' => $data['published_at'] ?? '',
                'download_url' => $data['download_url'] ?? '',
                'sha256' => $data['sha256'] ?? '',
                'env' => $env,
                'cached' => !empty($release['cached']),
            ]);
            break;

        case 'update_download':
            require_login();
            $lock = update_acquire_lock();
            if ($lock === false) respond(false, '已有升级进程正在进行，请稍后再试');
            try {
                $env = update_env_check();
                if (!$env['writable']) {
                    respond(false, '环境不支持在线升级：' . $env['reason'], ['env' => $env]);
                }

                $release = update_fetch_release(false);
                if (!$release['ok']) {
                    respond(false, $release['msg']);
                }
                $data = $release['data'];
                $latestVersion = ltrim((string)$data['version'], 'vV');
                if (!version_compare($latestVersion, NUOMI_VERSION, '>')) {
                    respond(false, '当前已是最新版本 v' . NUOMI_VERSION);
                }
                $downloadUrl = (string)($data['download_url'] ?? '');
                if ($downloadUrl === '') respond(false, '更新包下载地址为空');

                update_write_state([
                    'status' => 'downloading',
                    'version' => $latestVersion,
                    'message' => '正在下载更新包…',
                    'started' => date('Y-m-d H:i:s'),
                    'finished' => '',
                    'backup_dir' => '',
                    'download_url' => $downloadUrl,
                ]);

                $dl = update_download_package($downloadUrl, (string)($data['sha256'] ?? ''));
                if (!$dl['ok']) {
                    update_write_state([
                        'status' => 'failed',
                        'version' => $latestVersion,
                        'message' => '下载失败：' . $dl['msg'],
                        'started' => date('Y-m-d H:i:s'),
                        'finished' => date('Y-m-d H:i:s'),
                        'backup_dir' => '',
                        'download_url' => $downloadUrl,
                    ]);
                    respond(false, '下载失败：' . $dl['msg']);
                }

                $extract = update_extract_package($dl['file']);
                if (!$extract['ok']) {
                    update_write_state([
                        'status' => 'failed',
                        'version' => $latestVersion,
                        'message' => '解压失败：' . $extract['msg'],
                        'started' => date('Y-m-d H:i:s'),
                        'finished' => date('Y-m-d H:i:s'),
                        'backup_dir' => '',
                        'download_url' => $downloadUrl,
                    ]);
                    respond(false, '解压失败：' . $extract['msg']);
                }

                update_write_state([
                    'status' => 'downloaded',
                    'version' => $latestVersion,
                    'message' => '更新包已下载并解压，可以应用升级',
                    'started' => date('Y-m-d H:i:s'),
                    'finished' => date('Y-m-d H:i:s'),
                    'backup_dir' => '',
                    'download_url' => $downloadUrl,
                ]);

                respond(true, '下载完成，可以应用升级', [
                    'version' => $latestVersion,
                    'size' => $dl['size'],
                    'count' => $extract['count'],
                ]);
            } finally {
                update_release_lock($lock);
            }
            break;

        case 'update_apply':
            require_login();
            $lock = update_acquire_lock();
            if ($lock === false) respond(false, '已有升级进程正在进行，请稍后再试');
            try {
                $state = update_read_state();
                if (($state['status'] ?? '') !== 'downloaded') {
                    respond(false, '当前状态不允许应用升级（需要先下载）');
                }

                $extractDir = UPDATE_DIR . '/package';
                if (!is_dir($extractDir)) {
                    respond(false, '更新包已丢失，请重新下载');
                }

                update_write_state([
                    'status' => 'applying',
                    'version' => $state['version'] ?? '',
                    'message' => '正在应用升级…',
                    'started' => $state['started'] ?? date('Y-m-d H:i:s'),
                    'finished' => '',
                    'backup_dir' => '',
                    'download_url' => $state['download_url'] ?? '',
                ]);

                $apply = update_apply_package();
                if (!$apply['ok']) {
                    update_write_state([
                        'status' => 'failed',
                        'version' => $state['version'] ?? '',
                        'message' => $apply['msg'],
                        'started' => $state['started'] ?? '',
                        'finished' => date('Y-m-d H:i:s'),
                        'backup_dir' => '',
                        'download_url' => $state['download_url'] ?? '',
                    ]);
                    respond(false, $apply['msg']);
                }

                update_write_state([
                    'status' => 'success',
                    'version' => $state['version'] ?? '',
                    'message' => '升级成功，已应用 ' . $apply['applied'] . ' 个文件',
                    'started' => $state['started'] ?? '',
                    'finished' => date('Y-m-d H:i:s'),
                    'backup_dir' => $apply['backup_dir'] ?? '',
                    'download_url' => $state['download_url'] ?? '',
                ]);

                update_append_history([
                    'time' => date('Y-m-d H:i:s'),
                    'from_version' => NUOMI_VERSION,
                    'to_version' => $state['version'] ?? '',
                    'status' => 'success',
                    'applied' => $apply['applied'],
                    'backup_dir' => $apply['backup_dir'] ?? '',
                ]);

                respond(true, '升级成功', [
                    'version' => $state['version'] ?? '',
                    'applied' => $apply['applied'],
                    'backup_dir' => $apply['backup_dir'] ?? '',
                ]);
            } finally {
                update_release_lock($lock);
            }
            break;

        case 'update_rollback':
            require_login();
            $lock = update_acquire_lock();
            if ($lock === false) respond(false, '已有升级进程正在进行，请稍后再试');
            try {
                $latest = update_get_latest_backup();
                if ($latest === null) {
                    respond(false, '没有可用的备份');
                }
                $ok = update_restore_from_backup($latest);
                if (!$ok) {
                    respond(false, '回滚失败');
                }

                $state = update_read_state();
                update_write_state([
                    'status' => 'rolled_back',
                    'version' => $state['version'] ?? '',
                    'message' => '已回滚到备份：' . basename($latest),
                    'started' => $state['started'] ?? '',
                    'finished' => date('Y-m-d H:i:s'),
                    'backup_dir' => $latest,
                    'download_url' => $state['download_url'] ?? '',
                ]);

                update_append_history([
                    'time' => date('Y-m-d H:i:s'),
                    'from_version' => NUOMI_VERSION,
                    'to_version' => '',
                    'status' => 'rolled_back',
                    'backup_dir' => $latest,
                ]);

                respond(true, '回滚成功', ['backup_dir' => $latest]);
            } finally {
                update_release_lock($lock);
            }
            break;

        case 'update_status':
            require_login();
            $state = update_read_state();
            $latestBackup = update_get_latest_backup();
            respond(true, '', [
                'state' => $state,
                'can_rollback' => $latestBackup !== null,
                'current_version' => NUOMI_VERSION,
            ]);
            break;

        default:
            respond(false, '未知操作：' . $action);
    }
    exit;
}

/* ==========================================================================
   7. 页面渲染准备
   ========================================================================== */
$isLogin  = is_login();
$needInit = !has_conf();
$photos   = [];

function e($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

$siteCfg = site_defaults();
$tipsCfg = tips_defaults();
$rawData = file_exists(DATA_FILE) ? @file_get_contents(DATA_FILE) : false;
$rawJson = $rawData !== false ? json_decode($rawData, true) : null;

if (is_array($rawJson)) {
    if (isset($rawJson['site']) && is_array($rawJson['site'])) {
        foreach (site_defaults() as $k => $v) {
            if (isset($rawJson['site'][$k]) && trim((string)$rawJson['site'][$k]) !== '') {
                $siteCfg[$k] = $rawJson['site'][$k];
            }
        }
    }
    if (isset($rawJson['tips']) && is_array($rawJson['tips'])) {
        $tipsCfg = array_merge($tipsCfg, $rawJson['tips']);
        if (!isset($tipsCfg['periodTexts']) || !is_array($tipsCfg['periodTexts'])) {
            $tipsCfg['periodTexts'] = tips_defaults()['periodTexts'];
        } else {
            foreach (['night','morning','noon','afternoon','evening'] as $__p) {
                if (!isset($tipsCfg['periodTexts'][$__p]) || !is_array($tipsCfg['periodTexts'][$__p])) {
                    $tipsCfg['periodTexts'][$__p] = tips_defaults()['periodTexts'][$__p];
                }
            }
        }
        if (!isset($tipsCfg['texts']) || !is_array($tipsCfg['texts'])) {
            $tipsCfg['texts'] = tips_defaults()['texts'];
        }
    }
}

$adminPreloaders = [];
$defaultPreloaderKey = '';
if (is_array($rawJson) && isset($rawJson['preloaders']) && is_array($rawJson['preloaders'])) {
    foreach ($rawJson['preloaders'] as $p) {
        if (!is_array($p) || empty($p['enabled'])) continue;
        $key  = (string)($p['key'] ?? '');
        $file = (string)($p['file'] ?? '');
        if ($key === '' || $file === '') continue;
        $fp = is_file(__DIR__ . '/' . $file) ? __DIR__ . '/' . $file : __DIR__ . '/../' . $file;
        if (!is_file($fp)) continue;
        $css = @file_get_contents($fp);
        if ($css === false) continue;
        if (stripos($css, '<?php') !== false || stripos($css, '<?=') !== false) continue;
        if (stripos($css, '</style') !== false || stripos($css, '<script') !== false) continue;
        if (stripos($css, '</script') !== false) continue;
        $adminPreloaders[$key] = $css;
    }
    if (!empty($adminPreloaders)) {
        $defaultPreloaderKey = (string)($siteCfg['preloaderStyle'] ?? '');
        if ($defaultPreloaderKey === '' || !isset($adminPreloaders[$defaultPreloaderKey])) {
            $defaultPreloaderKey = array_key_first($adminPreloaders);
        }
    }
}

if (empty($adminPreloaders)) {
    foreach (factory_defaults()['preloaders'] as $p) {
        $fp = is_file(__DIR__ . '/' . $p['file']) ? __DIR__ . '/' . $p['file'] : __DIR__ . '/../' . $p['file'];
        if (!is_file($fp)) continue;
        $css = @file_get_contents($fp);
        if ($css === false) continue;
        $adminPreloaders[$p['key']] = $css;
    }
    $defaultPreloaderKey = 'elegant';
    $siteCfg['preloaderStyle'] = 'elegant';
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1,user-scalable=no">

    <title><?= e($siteCfg['adminPageTitle']) ?></title>

    <link rel="stylesheet" href="../assets/admin.css">

    <?php if ($needInit || !$isLogin): ?>
    <?php if (!empty($adminPreloaders)): ?>
    <script>
    window.ADMIN_PRELOADERS = <?= json_encode($adminPreloaders, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) ?>;
    window.ADMIN_DEFAULT_PRELOADER_KEY = <?= json_encode($defaultPreloaderKey, JSON_HEX_TAG) ?>;
    </script>
    <script>
    (function() {
        var pref = localStorage.getItem('preloader_style_pref');
        var cssMap = window.ADMIN_PRELOADERS;
        var key = (pref && cssMap[pref]) ? pref : window.ADMIN_DEFAULT_PRELOADER_KEY;
        if (cssMap[key]) {
            var style = document.createElement('style');
            style.id = 'admin-preloader-style';
            style.textContent = cssMap[key];
            document.head.appendChild(style);
        }
    })();
    </script>
    <?php endif; ?>
    <?php endif; ?>

    <script src="../assets/admin.js"></script>
</head>
<body>
<?php if ($needInit || !$isLogin): ?>
<?php if (!empty($adminPreloaders)): ?>
<div id="preloader">
    <div class="preloader-content">
        <div class="preloader-avatar">
            <img src="<?= e(admin_img_src($siteCfg['adminAvatar'])) ?>" alt="" decoding="async">
        </div>
        <div class="preloader-title"><?= e($needInit ? '安装向导' : $siteCfg['adminTitle']) ?></div>
        <div class="preloader-progress">
            <div class="preloader-progress-bar" id="preloaderBar"></div>
        </div>
    </div>
</div>
<?php endif; ?>

<div class="auth-bg" aria-hidden="true">
    <div class="auth-stars" id="authStars"></div>
    <div class="auth-sparkles" id="authSparkles"></div>
    <div class="auth-cloud auth-cloud-1">
        <svg viewBox="0 0 40 28">
            <ellipse cx="12" cy="18" rx="12" ry="10" opacity="0.8"/>
            <ellipse cx="28" cy="18" rx="12" ry="10" opacity="0.8"/>
            <ellipse cx="20" cy="12" rx="14" ry="12" opacity="0.9"/>
        </svg>
    </div>
    <div class="auth-cloud auth-cloud-2">
        <svg viewBox="0 0 40 28">
            <ellipse cx="12" cy="18" rx="12" ry="10" opacity="0.7"/>
            <ellipse cx="28" cy="18" rx="12" ry="10" opacity="0.7"/>
            <ellipse cx="20" cy="12" rx="14" ry="12" opacity="0.8"/>
        </svg>
    </div>
</div>
<?php endif; ?>

<div class="admin-app">
<?php if ($needInit): ?>
    <div class="auth-card auth-card-init">
        <div class="init-steps" id="initSteps">
            <div class="init-step-dot active" data-step="1"><span>1</span></div>
            <div class="init-step-line"></div>
            <div class="init-step-dot" data-step="2"><span>2</span></div>
            <div class="init-step-line"></div>
            <div class="init-step-dot" data-step="3"><span>3</span></div>
            <div class="init-step-line"></div>
            <div class="init-step-dot" data-step="4"><span>4</span></div>
        </div>

        <div class="auth-logo">
            <img src="<?= e(admin_img_src($siteCfg['adminAvatar'])) ?>" alt="头像" decoding="async">
        </div>
        <h1 class="auth-title">安装向导</h1>
        <p class="auth-sub" id="initSubtitle">欢迎使用糯米の引导页，请跟随向导完成安装</p>

        <form id="initForm" class="auth-form init-form" autocomplete="off">
            <div class="init-step-panel active" data-step="1">
                <label>管理员用户名
                    <input type="text" name="username" placeholder="例如 admin" required>
                </label>
                <label>设置密码（至少 6 位）
                    <input type="password" name="password" placeholder="输入密码" required>
                </label>
                <label>再次输入密码
                    <input type="password" name="password2" placeholder="再次输入密码确认" required>
                </label>
            </div>

            <div class="init-step-panel" data-step="2">
                <label>浏览器标签标题
                    <input type="text" name="pageTitle" value="<?= e($siteCfg['pageTitle']) ?>" maxlength="60">
                </label>
                <label>主标题
                    <input type="text" name="title" value="<?= e($siteCfg['title']) ?>" maxlength="40">
                </label>
                <label>副标题
                    <input type="text" name="subtitle" value="<?= e($siteCfg['subtitle']) ?>" maxlength="80">
                </label>
                <label>头像图片
                    <input type="text" name="avatar" value="<?= e($siteCfg['avatar']) ?>" maxlength="300" placeholder="图片路径或 URL">
                </label>
                <label>网站图标 favicon
                    <input type="text" name="favicon" value="<?= e($siteCfg['favicon']) ?>" maxlength="300">
                </label>
                <label>背景轮播 API
                    <input type="text" name="bgApi" value="<?= e($siteCfg['bgApi']) ?>" maxlength="300" placeholder="留空则用默认 API">
                    <span class="small muted">留空将回退到默认值。</span>
                </label>
                <label>Banner 轮播 API
                    <input type="text" name="bannerApi" value="<?= e($siteCfg['bannerApi']) ?>" maxlength="300" placeholder="留空则用默认 API">
                    <span class="small muted">留空将回退到默认值。</span>
                </label>
                <label>页脚版权信息
                    <input type="text" name="copyright" value="<?= e($siteCfg['copyright'] ?? '') ?>" maxlength="120">
                </label>
                <label>页脚 GitHub 链接
                    <input type="text" name="githubUrl" value="<?= e($siteCfg['githubUrl'] ?? '') ?>" maxlength="300" placeholder="https://github.com/xxx/xxx">
                </label>
                <div class="footer-switch-row">
                    <div class="footer-switch-text">
                        <div class="footer-switch-title">显示 GitHub 链接</div>
                        <div class="small muted">关闭后页脚不显示 GitHub，地址仍保留</div>
                    </div>
                    <input type="hidden" name="githubEnabled" value="0">
                    <label class="switch-mini" style="flex-shrink:0;">
                        <input type="checkbox" name="githubEnabled" value="1" <?= (($siteCfg['githubEnabled'] ?? '1') === '1') ? 'checked' : '' ?>>
                        <span class="slider-mini"></span>
                    </label>
                </div>
                <label>页脚 Powered by 项目名
                    <input type="text" name="poweredBy" value="<?= e($siteCfg['poweredBy'] ?? '') ?>" maxlength="60">
                </label>
                <label>页脚 Powered by 链接
                    <input type="text" name="poweredByUrl" value="<?= e($siteCfg['poweredByUrl'] ?? '') ?>" maxlength="300">
                </label>
            </div>

            <div class="init-step-panel" data-step="3">
                <label>背景轮播间隔（秒，3~60）
                    <input type="number" name="bgInterval" value="<?= e($siteCfg['bgInterval']) ?>" min="3" max="60" step="1">
                    <span class="small muted">默认 10 秒。</span>
                </label>
                <label>Banner 轮播间隔（秒，3~60）
                    <input type="number" name="bannerInterval" value="<?= e($siteCfg['bannerInterval']) ?>" min="3" max="60" step="1">
                    <span class="small muted">默认 6 秒。</span>
                </label>
                <label>缓存图片数量（2~20）
                    <input type="number" name="cacheLimit" value="<?= e($siteCfg['cacheLimit']) ?>" min="2" max="20" step="1">
                    <span class="small muted">默认 8。</span>
                </label>
                <label>头像连点次数（3~30）
                    <input type="number" name="adminClickCount" value="<?= e($siteCfg['adminClickCount']) ?>" min="3" max="30" step="1">
                    <span class="small muted">默认 10 次。</span>
                </label>
                <label>点击时间窗口（秒，1~60）
                    <input type="number" name="adminClickWindow" value="<?= e($siteCfg['adminClickWindow']) ?>" min="1" max="60" step="0.5">
                    <span class="small muted">默认 10 秒。</span>
                </label>
            </div>

            <div class="init-step-panel" data-step="4">
                <div class="init-summary">
                    <p class="small muted" style="margin-bottom:12px;">确认以下信息无误后点击「完成安装并进入后台」。</p>
                    <div class="init-summary-list">
                        <div class="init-summary-row">
                            <span class="init-summary-label">管理员账号</span>
                            <span class="init-summary-value" id="sumUser">—</span>
                        </div>
                        <div class="init-summary-row">
                            <span class="init-summary-label">前台标题</span>
                            <span class="init-summary-value" id="sumTitle">—</span>
                        </div>
                        <div class="init-summary-row">
                            <span class="init-summary-label">轮播 API</span>
                            <span class="init-summary-value" id="sumApi">—</span>
                        </div>
                        <div class="init-summary-row">
                            <span class="init-summary-label">页脚 GitHub</span>
                            <span class="init-summary-value" id="sumGithubOn">—</span>
                        </div>
                        <div class="init-summary-row">
                            <span class="init-summary-label">轮播间隔</span>
                            <span class="init-summary-value" id="sumInterval">—</span>
                        </div>
                        <div class="init-summary-row">
                            <span class="init-summary-label">进后台方式</span>
                            <span class="init-summary-value" id="sumClick">—</span>
                        </div>
                    </div>
                    <p class="init-summary-hint">📋 已预置 2 个默认分类（<b>导航 / 联系</b>）与 2 个加载页主题（优雅 / 苹果），可在后台随时修改或删除。</p>
                </div>
            </div>

            <div class="init-nav">
                <button type="button" class="btn btn-ghost init-prev" id="initPrevBtn" style="display:none">← 上一步</button>
                <button type="button" class="btn btn-primary init-next" id="initNextBtn">下一步 →</button>
                <button type="submit" class="btn btn-primary init-submit" id="initSubmitBtn" style="display:none">完成安装并进入后台</button>
            </div>
        </form>
        <div class="auth-actions">
            <a href="../" class="auth-act-btn">🏠 返回首页</a>
            <button type="button" class="auth-act-btn theme-toggle-btn" id="themeToggleBtn" title="切换主题" aria-label="切换主题">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 2a10 10 0 0 1 0 20z" fill="currentColor" stroke="none"/></svg>
            </button>
        </div>
    </div>

<?php elseif (!$isLogin): ?>
    <div class="auth-card">
        <div class="auth-logo">
            <img src="<?= e(admin_img_src($siteCfg['adminAvatar'])) ?>" alt="头像" decoding="async">
        </div>
        <h1 class="auth-title"><?= e($siteCfg['adminTitle']) ?></h1>
        <p class="auth-sub"><?= e($siteCfg['adminSubtitle']) ?></p>
        <form id="loginForm" class="auth-form" autocomplete="off">
            <label>用户名
                <input type="text" name="username" placeholder="管理员用户名" required>
            </label>
            <label>密码
                <input type="password" name="password" placeholder="密码" required>
            </label>
            <button type="submit" class="btn btn-primary btn-block">登 录</button>
        </form>
        <div class="auth-actions">
            <a href="../" class="auth-act-btn">🏠 返回首页</a>
            <button type="button" class="auth-act-btn theme-toggle-btn" id="themeToggleBtn" title="切换主题" aria-label="切换主题">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 2a10 10 0 0 1 0 20z" fill="currentColor" stroke="none"/></svg>
            </button>
        </div>
    </div>

<?php else:
    $data = load_data();
    $needSave = false;
    if (sync_js_effects($data))  $needSave = true;
    if (sync_board_girls($data)) $needSave = true;
    if (sync_preloaders($data))  $needSave = true;
    if (sync_local_images($data, 'bg'))     $needSave = true;
    if (sync_local_images($data, 'banner')) $needSave = true;
    if ($needSave) save_data($data);
    $photos = list_photos($data);

    $data['localBg']['images']     = decorate_images($data['localBg']['images'] ?? []);
    $data['localBanner']['images'] = decorate_images($data['localBanner']['images'] ?? []);
    $data['boardGirl']['girls']    = decorate_images($data['boardGirl']['girls'] ?? []);

    $uaTemplates = $data['uaTemplates'];
    $bgUaTpl   = find_ua_template($uaTemplates, $data['site']['localBgUaId'] ?? '');
    $bannerUaTpl = find_ua_template($uaTemplates, $data['site']['localBannerUaId'] ?? '');
    if (!$bgUaTpl)     $bgUaTpl     = find_ua_template($uaTemplates, 'ua_mobile_default');
    if (!$bannerUaTpl) $bannerUaTpl = find_ua_template($uaTemplates, 'ua_desktop_default');
?>

    <header class="admin-header">
        <div class="admin-header-left">
            <span class="header-title"><?= e($siteCfg['adminHeaderTitle']) ?></span>
            <!-- 把版本号变成可点击的更新入口，保留原来的胶囊样式，并加入小图标提示 -->
            <a class="header-version" href="update.php" title="点击检查在线更新">
                v<?= NUOMI_VERSION ?> 
                <svg viewBox="0 0 24 24" width="11" height="11" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="margin-left:3px;opacity:0.8"><path d="M21 2v6h-6M3 12a9 9 0 1 0 2.6-6.4L21 8"/></svg>
            </a>
        </div>
        <div class="admin-header-right">
            <button class="btn btn-ghost theme-toggle-btn" id="themeToggleBtn" title="切换主题" aria-label="切换主题">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 2a10 10 0 0 1 0 20z" fill="currentColor" stroke="none"/></svg>
            </button>
            <!-- 增加 btn-view-home 类名，方便在手机端隐藏 -->
            <a class="btn btn-ghost btn-view-home" href="../">查看首页</a>
            <button class="btn btn-ghost" id="logoutBtn">退出登录</button>
        </div>
    </header>

    <nav class="admin-tabs">
        <button class="tab-btn active" data-tab="cards">📋 卡片管理</button>
        <button class="tab-btn" data-tab="announcements">📢 公告管理</button>
        <button class="tab-btn" data-tab="photos">🖼 图片管理</button>
        <button class="tab-btn" data-tab="boardgirl">👘 看板娘</button>
        <button class="tab-btn" data-tab="effects">✨ JS特效</button>
        <button class="tab-btn" data-tab="preloaders">🎨 加载页</button>
        <button class="tab-btn" data-tab="localbg">🖼 本地背景</button>
        <button class="tab-btn" data-tab="uaprofiles">🎭 UA 伪装</button>
        <button class="tab-btn" data-tab="analytics">🌐 统计代码</button>
        <button class="tab-btn" data-tab="site">⚙ 站点设置</button>
        <button class="tab-btn" data-tab="backup">💾 备份恢复</button>
        <button class="tab-btn" data-tab="account">🔐 账号设置</button>
        <button class="tab-btn" data-tab="help">📖 使用说明</button>
    </nav>

    <section class="tab-panel" id="panel-announcements">
        <div class="panel-head">
            <h2>📢 公告管理</h2>
            <button class="btn btn-primary" id="addAnnouncementBtn">＋ 新增公告</button>
        </div>
        <div class="effect-hint">
            <p>📌 公告会显示在站内公告页 <code>/announcement.php</code>，404 页的「查看公告」按钮会跳转到该页。</p>
            <p style="margin-top:6px">💡 置顶的公告会排在最上方，禁用的公告不会显示在公告页。</p>
            <p style="margin-top:6px">⚠️ 公告内容支持 HTML 标签（如 <code>&lt;p&gt;</code> / <code>&lt;strong&gt;</code> / <code>&lt;a&gt;</code>），但不允许包含 <code>&lt;script&gt;</code>。</p>
        </div>
        <div class="announcement-rows" id="announcementRows">
            <?php foreach (($data['announcements'] ?? []) as $ann):
                $typeLabel = ['info' => 'ℹ️ 通知', 'success' => '✅ 成功', 'warning' => '⚠️ 警告', 'error' => '🚫 错误'][$ann['type']] ?? 'ℹ️ 通知';
            ?>
                <div class="announcement-row"
                     data-id="<?= e($ann['id']) ?>"
                     data-title="<?= e($ann['title']) ?>"
                     data-content="<?= e($ann['content']) ?>"
                     data-type="<?= e($ann['type']) ?>"
                     data-pinned="<?= !empty($ann['pinned']) ? '1' : '0' ?>"
                     data-enabled="<?= !empty($ann['enabled']) ? '1' : '0' ?>">
                    <div class="announcement-row-header">
                        <span class="announcement-type-badge announcement-type-<?= e($ann['type']) ?>"><?= $typeLabel ?></span>
                        <?php if (!empty($ann['pinned'])): ?>
                            <span class="effect-badge badge-toggle">置顶</span>
                        <?php endif; ?>
                        <span class="announcement-title"><?= e($ann['title']) ?></span>
                    </div>
                    <div class="announcement-row-meta">
                        <span class="muted small">创建于 <?= e($ann['created']) ?></span>
                        <?php if ($ann['updated'] !== $ann['created']): ?>
                            <span class="muted small">· 更新于 <?= e($ann['updated']) ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="announcement-row-actions">
                        <label class="switch-mini" title="启用 / 禁用">
                            <input type="checkbox" class="announcement-toggle" data-id="<?= e($ann['id']) ?>" <?= !empty($ann['enabled']) ? 'checked' : '' ?>>
                            <span class="slider-mini"></span>
                        </label>
                        <button type="button" class="row-btn announcement-pin" data-id="<?= e($ann['id']) ?>" title="<?= !empty($ann['pinned']) ? '取消置顶' : '置顶' ?>"><?= !empty($ann['pinned']) ? '📌' : '📍' ?></button>
                        <button type="button" class="row-btn edit-announcement" title="编辑">✎</button>
                        <button type="button" class="row-btn del-announcement" title="删除">🗑</button>
                    </div>
                </div>
            <?php endforeach; ?>
            <?php if (count($data['announcements'] ?? []) === 0): ?>
                <div class="empty-tip" id="emptyAnnouncements">还没有公告，点击右上角「新增公告」开始。</div>
            <?php endif; ?>
        </div>
    </section>

    <section class="tab-panel active" id="panel-cards">
        <div class="panel-head">
            <h2>卡片管理</h2>
            <button class="btn btn-primary" id="addCardBtn">＋ 新增卡片</button>
        </div>
        <div class="cat-bar">
            <span class="cat-bar-label">分类：</span>
            <div class="cat-chips" id="catChips">
                <?php foreach ($data['categories'] as $cat): ?>
                    <span class="cat-chip" data-name="<?= e($cat['name']) ?>" data-icon="<?= e($cat['icon']) ?>">
                        <span class="cat-chip-name"><?= e($cat['name']) ?></span>
                        <button type="button" class="cat-edit" title="重命名/修改图标">✎</button>
                        <button type="button" class="cat-del" title="删除">✕</button>
                    </span>
                <?php endforeach; ?>
            </div>
            <div class="cat-add">
                <button class="btn btn-sm" id="openAddCatModalBtn">＋ 添加分类</button>
            </div>
        </div>
        <p class="cat-tip">💡 长按分类标签 <b>350ms</b> 即可拖动排序，松手自动保存，前台分类顺序会同步更新。</p>
        <div class="sort-bar">
            <span>拖动 ⋮⋮ 手柄（按住 200ms）调整顺序</span>
            <button class="btn btn-sm btn-primary" id="saveSortBtn" style="display:none">💾 保存排序</button>
            <button class="btn btn-sm" id="cancelSortBtn" style="display:none">取消</button>
        </div>
        <div class="card-rows" id="cardRows">
            <?php foreach ($data['cards'] as $card): ?>
                <div class="card-row" data-id="<?= e($card['id']) ?>" data-card="<?= e(json_encode($card)) ?>" draggable="false">
                    <div class="drag-handle" title="按住拖动排序">⋮⋮</div>
                    <div class="row-icon">
                        <?php if ($card['icon'] !== ''): ?>
                            <img src="<?= e(admin_img_src($card['icon'])) ?>" alt="" loading="lazy" decoding="async">
                        <?php else: ?>
                            <span class="row-icon-empty"></span>
                        <?php endif; ?>
                    </div>
                    <div class="row-info">
                        <div class="row-name"><?= e($card['name']) ?></div>
                        <div class="row-sub"><?= e($card['sub'] !== '' ? $card['sub'] : ($card['type'] === 'image' ? '图片卡片 · 点击弹大图' : '链接卡片')) ?></div>
                    </div>
                    <span class="row-cat"><?= e($card['category']) ?></span>
                    <span class="row-type type-<?= e($card['type']) ?>"><?= $card['type'] === 'image' ? '图片' : '链接' ?></span>
                    <div class="row-actions">
                        <button type="button" class="row-btn edit-card" title="编辑">✎</button>
                        <button type="button" class="row-btn del-card" title="删除">🗑</button>
                    </div>
                </div>
            <?php endforeach; ?>
            <?php if (count($data['cards']) === 0): ?>
                <div class="empty-tip" id="emptyCards">还没有卡片，点击右上角「新增卡片」开始添加。</div>
            <?php endif; ?>
        </div>
    </section>

    <section class="tab-panel" id="panel-photos">
        <div class="panel-head">
            <h2>图片管理</h2>
        </div>
        <div class="upload-zone" id="uploadZone">
            <div class="upload-inner">
                <div class="upload-icon">⬆</div>
                <p>点击选择或拖拽图片到此处上传</p>
                <p class="muted small">支持 jpg / png / gif / webp，单个 ≤ 5MB</p>
                <input type="file" id="photoInput" accept=".jpg,.jpeg,.png,.gif,.webp" multiple hidden>
            </div>
        </div>
        <div class="photo-hint">
            <p>📌 点击上方虚线框选择或拖拽图片上传，也可以直接把图片丢进 <code>photos/</code> 目录，<b>刷新本页面</b>后自动出现在下方。</p>
            <p style="margin-top:6px">🖼 长按左上角 <b>⋮⋮</b> 手柄可拖动调整顺序（松手自动保存）。鼠标悬停图片可预览、重命名或删除。</p>
            <p style="margin-top:6px"><b>重命名会自动同步更新引用（含站点设置里的头像 / favicon / 后台头像）；删除时若图片正在被卡片、分类或站点设置使用，会先提示并列出所有引用位置，确认后才真正删除（引用会被自动清空）。</b></p>
        </div>
        <div class="sort-bar">
            <span>长按 ⋮⋮ 拖动可调顺序（松手自动保存）</span>
        </div>
        <div class="photo-grid" id="photoGrid">
            <?php if (count($photos) === 0): ?>
                <div class="empty-tip empty-photo-tip" id="emptyPhotos">
                    🖼 还没有图片，点击上方虚线框上传，或直接把图片放入 <code>photos/</code> 目录后刷新本页面。
                </div>
            <?php endif; ?>
            <?php foreach ($photos as $photoItem):
                $pFile  = is_array($photoItem) ? ($photoItem['file'] ?? '') : $photoItem;
                $pThumb = is_array($photoItem) ? ($photoItem['thumb'] ?? $pFile) : $photoItem;
                if ($pFile === '') continue;
            ?>
                <div class="photo-cell" data-path="<?= e($pFile) ?>">
                    <div class="drag-handle-photo" title="长按拖动排序">⋮⋮</div>
                    <button type="button" class="photo-edit" title="重命名">✎</button>
                    <div class="photo-cell-image">
                        <img src="../<?= e($pThumb) ?>" alt="" loading="lazy" decoding="async">
                    </div>
                    <div class="photo-cell-bar">
                        <button type="button" class="photo-preview">预览</button>
                        <button type="button" class="photo-del">✕</button>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </section>

    <section class="tab-panel" id="panel-boardgirl">
        <div class="panel-head">
            <h2>看板娘管理</h2>
            <div class="bg-toggle-wrap">
                <span class="bg-toggle-label">🎀看板娘</span>
                <label class="switch-mini">
                    <input type="checkbox" id="boardGirlEnabled" <?= !empty($data['boardGirl']['enabled']) ? 'checked' : '' ?>>
                    <span class="slider-mini"></span>
                </label>
            </div>
        </div>
        <div class="upload-zone" id="girlUploadZone">
            <div class="upload-inner">
                <div class="upload-icon">⬆</div>
                <p>点击选择或拖拽 GIF 到此处上传</p>
                <p class="muted small">支持 gif / png / webp，单个 ≤ 10MB</p>
                <input type="file" id="girlInput" accept=".gif,.png,.webp" multiple hidden>
            </div>
        </div>
        <div class="girl-hint">
            💡 直接往 <code>photos/kanban/</code> 目录丢 GIF，然后 <b>刷新本页面</b> 就会自动出现在下方列表里。<br>
            👆 鼠标悬停格子可显示拖动、重命名、预览、删除按钮。
        </div>
        <div class="sort-bar">
            <span>长按 ⋮⋮ 拖动可调顺序（松手自动保存）</span>
        </div>
        <div class="girl-grid" id="girlGrid">
            <?php foreach ($data['boardGirl']['girls'] as $g): ?>
                <div class="girl-cell" data-file="<?= e($g['file']) ?>" data-name="<?= e($g['name']) ?>">
                    <div class="drag-handle-girl" title="长按拖动排序">⋮⋮</div>
                    <button type="button" class="girl-rename" title="重命名文件">✎</button>
                    <div class="girl-cell-image">
                        <img src="../<?= e($g['thumb'] ?? $g['file']) ?>" alt="" loading="lazy" decoding="async">
                    </div>
                    <div class="girl-cell-name"><?= e($g['name']) ?></div>
                    <div class="girl-cell-bar">
                        <button type="button" class="girl-preview">预览</button>
                        <button type="button" class="girl-del">✕</button>
                    </div>
                </div>
            <?php endforeach; ?>
            <?php if (count($data['boardGirl']['girls']) === 0): ?>
                <div class="empty-tip" id="emptyGirls">还没有看板娘形象，上传 GIF 或直接丢到 photos/kanban/ 目录即可。</div>
            <?php endif; ?>
        </div>

        <div class="backup-card" style="margin-top:16px;">
            <div class="backup-title">💬 语录设置</div>
            <p class="muted small" style="margin-bottom:12px;">
                每行一条语录，空行会被自动忽略；某个分类留空时使用系统默认语录。
            </p>
            <form id="tipsForm" class="auth-form" autocomplete="off">
                <div class="tips-switch-row">
                    <label class="switch-mini">
                        <input type="checkbox" name="tips_enabled" <?= !empty($tipsCfg['enabled']) ? 'checked' : '' ?>>
                        <span class="slider-mini"></span>
                    </label>
                    <div class="tips-switch-text">
                        <div class="tips-switch-title">默认开启随机语录</div>
                        <div class="small muted">关闭后，用户仍可在看板娘面板里手动打开</div>
                    </div>
                </div>
                <div class="duration-row">
                    <span class="duration-label">语录显示时长</span>
                    <input type="number" name="tips_duration" value="<?= e($tipsCfg['duration'] / 1000) ?>" min="0.5" max="60" step="0.5">
                    <span class="duration-unit">秒</span>
                </div>
                <div class="small muted" style="margin-top:-8px;margin-bottom:12px;">范围 0.5 ~ 60 秒，支持小数</div>
                <label>通用语录（每行一条）
                    <textarea name="tips_texts" rows="6"><?= e(implode("\n", $tipsCfg['texts'])) ?></textarea>
                </label>
                <label>🌙 深夜语录（0:00 - 5:00，每行一条）
                    <textarea name="tips_period_night" rows="4"><?= e(implode("\n", $tipsCfg['periodTexts']['night'])) ?></textarea>
                </label>
                <label>☀️ 早上语录（5:00 - 11:00，每行一条）
                    <textarea name="tips_period_morning" rows="4"><?= e(implode("\n", $tipsCfg['periodTexts']['morning'])) ?></textarea>
                </label>
                <label>🍚 中午语录（11:00 - 13:00，每行一条）
                    <textarea name="tips_period_noon" rows="4"><?= e(implode("\n", $tipsCfg['periodTexts']['noon'])) ?></textarea>
                </label>
                <label>☕ 下午语录（13:00 - 17:00，每行一条）
                    <textarea name="tips_period_afternoon" rows="4"><?= e(implode("\n", $tipsCfg['periodTexts']['afternoon'])) ?></textarea>
                </label>
                <label>🌆 晚上语录（17:00 - 23:00，每行一条）
                    <textarea name="tips_period_evening" rows="4"><?= e(implode("\n", $tipsCfg['periodTexts']['evening'])) ?></textarea>
                </label>
                <label>远程语录 API 地址（可选）
                    <input type="text" name="tips_api" value="<?= e($tipsCfg['api']) ?>" placeholder="留空则只用本地语录；例如 https://example.com/api/tips">
                    <span class="small muted">GET 请求，返回 JSON；与本地语录会交替优先展示。</span>
                </label>
                <label>API 返回的字段名
                    <input type="text" name="tips_apiField" value="<?= e($tipsCfg['apiField']) ?>" placeholder="text">
                    <span class="small muted">例如接口返回 {"text":"你好"}，这里就填 text。</span>
                </label>
                <button type="submit" class="btn btn-primary">保存语录设置</button>
            </form>
        </div>
    </section>

    <section class="tab-panel" id="panel-effects">
        <div class="panel-head">
            <h2>JS 特效管理</h2>
            <div class="panel-head-actions">
                <button class="btn" id="addEffectUrlBtn" title="通过外链 URL 添加">🔗 URL 添加</button>
                <button class="btn btn-primary" id="uploadJsBtn">＋ 上传特效</button>
            </div>
            <input type="file" id="jsInput" accept=".js,application/javascript" hidden>
        </div>
        <div class="effect-hint">
            <p>📌 直接把 <code>.js</code> 丢进 <code>assets/js/</code>，<b>刷新本页面</b>就会自动出现。</p>
            <p style="margin-top:6px">🔗 也支持点 <b>URL 添加</b> 引用远程 JS（如 CDN 上的特效脚本）。</p>
            <p style="margin-top:6px"><b>「特效名称」默认取 JS 文件名，可以随便改</b>，只是前台面板上展示的文字。</p>
            <p style="margin-top:6px"><b>matchKey</b> 只在首次识别新特效时自动提取；手动修改后的值会永久保留，不会被刷新覆盖。</p>
        </div>
        <div class="sort-bar">
            <span>长按 ⋮⋮ 拖动可调顺序（松手自动保存）</span>
        </div>
        <div class="effect-rows" id="effectRows">
            <?php foreach ($data['jsEffects'] as $eff):
                $isUrl = is_remote_url($eff['file']);
                $canToggle = !empty($eff['autoToggle']);
            ?>
                <div class="effect-row" data-id="<?= e($eff['id']) ?>"
                     data-matchkey="<?= e($eff['matchKey'] ?? '') ?>"
                     data-name="<?= e($eff['name']) ?>"
                     data-icon="<?= e($eff['icon']) ?>"
                     data-file="<?= e($eff['file']) ?>"
                     data-remote="<?= $isUrl ? '1' : '0' ?>"
                     data-autotoggle="<?= $canToggle ? '1' : '0' ?>">
                    <div class="drag-handle-mini" title="长按拖动排序">⋮⋮</div>
                    <span class="effect-row-icon"><?= e($eff['icon'] ?: '✨') ?></span>
                    <div class="effect-row-info">
                        <div class="effect-row-name">
                            <span class="effect-row-name-text"><?= e($eff['name']) ?></span>
                            <?php if ($isUrl): ?>
                                <span class="effect-badge badge-url">URL</span>
                            <?php endif; ?>
                            <?php if ($canToggle): ?>
                                <span class="effect-badge badge-toggle" title="支持 start / stop，前台面板开关即时生效">可控</span>
                            <?php else: ?>
                                <span class="effect-badge badge-fixed" title="未检测到 start / stop，前台面板开关会刷新页面">固定</span>
                            <?php endif; ?>
                        </div>
                        <div class="effect-row-file">
                            <span class="effect-key"><?= e($eff['matchKey'] ?? '') ?></span>
                            · <?= e($eff['file']) ?>
                        </div>
                    </div>
                    <label class="switch-mini" title="启用 / 禁用">
                        <input type="checkbox" class="effect-toggle" data-id="<?= e($eff['id']) ?>" <?= $eff['enabled'] ? 'checked' : '' ?>>
                        <span class="slider-mini"></span>
                    </label>
                    <div class="row-actions">
                        <button type="button" class="row-btn edit-effect" title="编辑">✎</button>
                        <button type="button" class="row-btn del-effect" title="删除">🗑</button>
                    </div>
                </div>
            <?php endforeach; ?>
            <?php if (count($data['jsEffects']) === 0): ?>
                <div class="empty-tip" id="emptyEffects">还没有上传任何 JS 特效，上传文件、通过 URL 添加，或直接丢到 assets/js/ 目录即可。</div>
            <?php endif; ?>
        </div>
    </section>

    <section class="tab-panel" id="panel-preloaders">
        <div class="panel-head">
            <h2>加载页主题</h2>
            <div class="bg-toggle-wrap">
                <span class="bg-toggle-label">🎨加载页</span>
                <label class="switch-mini">
                    <input type="checkbox" id="preloaderEnabledToggle" <?= (($siteCfg['preloader'] ?? '1') !== '0') ? 'checked' : '' ?>>
                    <span class="slider-mini"></span>
                </label>
            </div>
        </div>
        <div class="upload-zone" id="preloaderUploadZone">
            <div class="upload-inner">
                <div class="upload-icon">⬆</div>
                <p>点击选择或拖拽 .css 文件到此处上传</p>
                <p class="muted small">支持 .css 文件，单个 ≤ 2MB，可批量</p>
                <input type="file" id="preloaderInput" accept=".css" multiple hidden>
            </div>
        </div>
        <div class="effect-hint">
            <p>📌 上传 <code>.css</code> 文件到 <code>assets/preloaders/</code>，刷新本页面即可自动识别。</p>
            <p style="margin-top:6px">💡 CSS 文件顶部可写两行注释作为名称和描述，例如：<br>
            <code>/* @name 优雅 */</code><br>
            <code>/* @description 紫罗兰柔光 + 涟漪 */</code></p>
            <p style="margin-top:6px"><b>拖动排序后，排在首位的主题将自动成为默认主题。</b></p>
        </div>
        <div class="sort-bar">
            <span>长按 ⋮⋮ 拖动可调顺序（松手自动保存）</span>
        </div>
        <div class="effect-rows" id="preloaderRows">
            <?php foreach ($data['preloaders'] as $pre):
                $isDefault = ($siteCfg['preloaderStyle'] ?? '') === $pre['key'];
            ?>
                <div class="effect-row" data-key="<?= e($pre['key']) ?>"
                     data-name="<?= e($pre['name']) ?>"
                     data-desc="<?= e($pre['description']) ?>"
                     data-file="<?= e($pre['file']) ?>"
                     data-default="<?= $isDefault ? '1' : '0' ?>">
                    <div class="drag-handle-mini" title="长按拖动排序">⋮⋮</div>
                    <span class="effect-row-icon">🎨</span>
                    <div class="effect-row-info">
                        <div class="effect-row-name">
                            <span class="effect-row-name-text"><?= e($pre['name']) ?></span>
                            <?php if ($isDefault): ?>
                                <span class="effect-badge badge-url">默认</span>
                            <?php endif; ?>
                        </div>
                        <div class="effect-row-file">
                            <span class="effect-key"><?= e($pre['key']) ?></span>
                            · <?= e($pre['description'] ?: $pre['file']) ?>
                        </div>
                    </div>
                    <label class="switch-mini" title="启用 / 禁用">
                        <input type="checkbox" class="preloader-toggle" data-key="<?= e($pre['key']) ?>" <?= $pre['enabled'] ? 'checked' : '' ?>>
                        <span class="slider-mini"></span>
                    </label>
                    <div class="row-actions">
                        <button type="button" class="row-btn edit-preloader" title="编辑">✎</button>
                        <button type="button" class="row-btn del-preloader" title="删除">🗑</button>
                    </div>
                </div>
            <?php endforeach; ?>
            <?php if (count($data['preloaders']) === 0): ?>
                <div class="empty-tip" id="emptyPreloaders">还没有加载页主题，上传 .css 或直接丢到 assets/preloaders/ 目录即可。</div>
            <?php endif; ?>
        </div>
    </section>

    <section class="tab-panel" id="panel-localbg">
        <div class="panel-head">
            <h2>🖼 本地背景兜底</h2>
        </div>
        <div class="effect-hint">
            <p>📌 本地背景是<b>兜底方案</b>：前台优先从轮播 API 拉图，只有 API 重试全部失败后才会用本地图片。</p>
            <p style="margin-top:6px">💡 本地图片会写入浏览器 IndexedDB 缓存（独立键前缀，不占用 API 池），首次加载后回访<b>秒显、零流量</b>。</p>
            <p style="margin-top:6px">🎞 背景与 Banner 完全独立存储、独立开关，可上传或一键从 API 抓取。上传时会自动按内容去重。</p>
            <p style="margin-top:6px">📱 <b>设备方向全自动识别</b>：系统会根据图片分辨率自动分拣横屏（桌面端）或竖屏（移动端），如需微调可在图片的"重命名/编辑"中修改。</p>
            <p style="margin-top:6px">🎭 <b>抓取会使用 UA 伪装页里选定的模板</b>：默认背景走移动端 UA（抓竖图）、Banner 走桌面端 UA（抓横图）；想换方向去「🎭 UA 伪装」页把对应模板切换一下即可。</p>
        </div>

        <div class="backup-card" style="margin-bottom:12px;">
            <div class="backup-title" style="display:flex;align-items:center;gap:10px;">
                <span>🏠 背景兜底</span>
                <button type="button" class="btn btn-sm btn-primary ua-pick-btn" data-local-type="bg" data-current-ua="<?= e($bgUaTpl['id']) ?>" title="当前抓取 UA：<?= e($bgUaTpl['name']) ?>">UA</button>
                <button type="button" class="btn btn-sm auto-detect-btn" data-local-type="bg" title="根据图片横竖比例自动分类为桌面端/移动端">自动分类</button>
                <span style="flex:1;"></span>
                <label class="switch-mini" style="flex-shrink:0;">
                    <input type="checkbox" id="localBgEnabled" <?= !empty($data['localBg']['enabled']) ? 'checked' : '' ?>>
                    <span class="slider-mini"></span>
                </label>
            </div>
            <div class="local-bg-priority-row">
                <span class="local-bg-priority-label">取图优先级</span>
                <label class="radio-inline">
                    <input type="radio" name="localBgPriority" value="api"
                        <?= (($siteCfg['localBgPriority'] ?? 'api') === 'api') ? 'checked' : '' ?>>
                    <span>API 优先</span>
                </label>
                <label class="radio-inline">
                    <input type="radio" name="localBgPriority" value="local"
                        <?= (($siteCfg['localBgPriority'] ?? 'api') === 'local') ? 'checked' : '' ?>>
                    <span>本地优先</span>
                </label>
            </div>
            <p class="muted small" style="margin-bottom:10px;">页面背景在 API 挂掉时的兜底图片。</p>
            <div class="upload-zone local-bg-zone" data-local-type="bg" style="padding:16px;margin-bottom:10px;">
                <div class="upload-inner">
                    <div class="upload-icon">⬆</div>
                    <p>点击或拖拽图片到此处上传</p>
                    <p class="muted small">支持 jpg / png / gif / webp，单个 ≤ 5MB</p>
                    <input type="file" class="local-bg-input" data-local-type="bg" accept=".jpg,.jpeg,.png,.gif,.webp" multiple hidden>
                </div>
            </div>
            <div class="local-bg-toolbar">
                <span class="muted small">从当前背景 API 抓取</span>
                <input type="number" class="local-bg-fetch-count" data-local-type="bg" value="5" min="1" max="20" step="1">
                <button type="button" class="btn btn-sm btn-primary fetch-local-bg-btn" data-local-type="bg">立即抓取</button>
            </div>
            <div class="local-bg-grid photo-grid" data-local-type="bg" style="margin-top:12px;">
                <?php foreach ($data['localBg']['images'] as $item):
                    $__thumb = $item['thumb'] ?? $item['file'];
                ?>
                    <div class="photo-cell local-bg-cell" data-type="bg" data-file="<?= e($item['file']) ?>" data-thumb="<?= e($__thumb) ?>" data-name="<?= e($item['name']) ?>" data-target="<?= e($item['target'] ?? 'both') ?>">
                        <div class="drag-handle-photo" title="长按拖动排序">⋮⋮</div>
                        <button type="button" class="photo-edit" title="编辑">✎</button>
                        <div class="photo-cell-image"><img data-src="../<?= e($__thumb) ?>" alt="" loading="lazy" decoding="async"></div>
                        <div class="photo-cell-bar">
                            <button type="button" class="photo-preview">预览</button>
                            <button type="button" class="photo-del">✕</button>
                        </div>
                    </div>
                <?php endforeach; ?>
                <?php if (count($data['localBg']['images']) === 0): ?>
                    <div class="empty-tip local-bg-empty" style="grid-column:1/-1;padding:16px;font-size:12px;">暂无本地兜底图片。</div>
                <?php endif; ?>
            </div>
        </div>

        <div class="backup-card">
            <div class="backup-title" style="display:flex;align-items:center;gap:10px;">
                <span>🎞 Banner 兜底</span>
                <button type="button" class="btn btn-sm btn-primary ua-pick-btn" data-local-type="banner" data-current-ua="<?= e($bannerUaTpl['id']) ?>" title="当前抓取 UA：<?= e($bannerUaTpl['name']) ?>">UA</button>
                <button type="button" class="btn btn-sm auto-detect-btn" data-local-type="banner" title="根据图片横竖比例自动分类为桌面端/移动端">自动分类</button>
                <span style="flex:1;"></span>
                <label class="switch-mini" style="flex-shrink:0;">
                    <input type="checkbox" id="localBannerEnabled" <?= !empty($data['localBanner']['enabled']) ? 'checked' : '' ?>>
                    <span class="slider-mini"></span>
                </label>
            </div>
            <div class="local-bg-priority-row">
                <span class="local-bg-priority-label">取图优先级</span>
                <label class="radio-inline">
                    <input type="radio" name="localBannerPriority" value="api"
                        <?= (($siteCfg['localBannerPriority'] ?? 'api') === 'api') ? 'checked' : '' ?>>
                    <span>API 优先</span>
                </label>
                <label class="radio-inline">
                    <input type="radio" name="localBannerPriority" value="local"
                        <?= (($siteCfg['localBannerPriority'] ?? 'api') === 'local') ? 'checked' : '' ?>>
                    <span>本地优先</span>
                </label>
            </div>
            <p class="muted small" style="margin-bottom:10px;">顶部 Banner 在 API 挂掉时的兜底图片（建议用横图）。</p>
            <div class="upload-zone local-bg-zone" data-local-type="banner" style="padding:16px;margin-bottom:10px;">
                <div class="upload-inner">
                    <div class="upload-icon">⬆</div>
                    <p>点击或拖拽图片到此处上传</p>
                    <p class="muted small">支持 jpg / png / gif / webp，单个 ≤ 5MB</p>
                    <input type="file" class="local-bg-input" data-local-type="banner" accept=".jpg,.jpeg,.png,.gif,.webp" multiple hidden>
                </div>
            </div>
            <div class="local-bg-toolbar">
                <span class="muted small">从当前 Banner API 抓取</span>
                <input type="number" class="local-bg-fetch-count" data-local-type="banner" value="5" min="1" max="20" step="1">
                <button type="button" class="btn btn-sm btn-primary fetch-local-bg-btn" data-local-type="banner">立即抓取</button>
            </div>
            <div class="local-bg-grid photo-grid" data-local-type="banner" style="margin-top:12px;">
                <?php foreach ($data['localBanner']['images'] as $item):
                    $__thumb = $item['thumb'] ?? $item['file'];
                ?>
                    <div class="photo-cell local-bg-cell" data-type="banner" data-file="<?= e($item['file']) ?>" data-thumb="<?= e($__thumb) ?>" data-name="<?= e($item['name']) ?>" data-target="<?= e($item['target'] ?? 'both') ?>">
                        <div class="drag-handle-photo" title="长按拖动排序">⋮⋮</div>
                        <button type="button" class="photo-edit" title="编辑">✎</button>
                        <div class="photo-cell-image"><img data-src="../<?= e($__thumb) ?>" alt="" loading="lazy" decoding="async"></div>
                        <div class="photo-cell-bar">
                            <button type="button" class="photo-preview">预览</button>
                            <button type="button" class="photo-del">✕</button>
                        </div>
                    </div>
                <?php endforeach; ?>
                <?php if (count($data['localBanner']['images']) === 0): ?>
                    <div class="empty-tip local-bg-empty" style="grid-column:1/-1;padding:16px;font-size:12px;">暂无本地兜底图片。</div>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <section class="tab-panel" id="panel-uaprofiles">
        <div class="panel-head">
            <h2>🎭 UA 伪装模板</h2>
            <button class="btn btn-primary" id="addUaBtn">＋ 新增模板</button>
        </div>
        <div class="effect-hint">
            <p>📌 抓取本地背景时，服务器会带着这里配置的 User-Agent 等请求头去访问 API。</p>
            <p style="margin-top:6px">💡 部分 API 会根据 UA 判断设备类型，返回不同方向的图片（例如移动端 UA → 竖图、桌面端 UA → 横图）。</p>
            <p style="margin-top:6px">🎯 背景 / Banner 抓取各自独立选择模板，也可在「🖼 本地背景」页面里用「UA」按钮快速切换。</p>
            <p style="margin-top:6px">⚡ 这里的模板<b>就是「立即抓取」实际使用的请求头</b>。默认背景走移动端模板（抓竖图）、Banner 走桌面端模板（抓横图）。</p>
        </div>
        <div class="ua-rows" id="uaRows">
            <?php foreach ($data['uaTemplates'] as $tpl):
                $isBgUsing     = ($data['site']['localBgUaId'] ?? '') === $tpl['id'];
                $isBannerUsing = ($data['site']['localBannerUaId'] ?? '') === $tpl['id'];
            ?>
                <div class="ua-row"
                     data-id="<?= e($tpl['id']) ?>"
                     data-name="<?= e($tpl['name']) ?>"
                     data-ua="<?= e($tpl['ua']) ?>"
                     data-accept="<?= e($tpl['accept']) ?>"
                     data-lang="<?= e($tpl['acceptLanguage']) ?>"
                     data-enc="<?= e($tpl['acceptEncoding']) ?>"
                     data-extra="<?= e($tpl['extraHeaders']) ?>"
                     data-builtin="<?= !empty($tpl['builtin']) ? '1' : '0' ?>">
                    <span class="effect-row-icon">🎭</span>
                    <div class="effect-row-info">
                        <div class="effect-row-name">
                            <span class="effect-row-name-text"><?= e($tpl['name']) ?></span>
                            <?php if (!empty($tpl['builtin'])): ?>
                                <span class="effect-badge badge-fixed">内置</span>
                            <?php endif; ?>
                            <?php if ($isBgUsing): ?>
                                <span class="effect-badge badge-toggle badge-usage">背景使用中</span>
                            <?php endif; ?>
                            <?php if ($isBannerUsing): ?>
                                <span class="effect-badge badge-url badge-usage">Banner 使用中</span>
                            <?php endif; ?>
                        </div>
                        <div class="effect-row-file">
                            <span class="effect-key"><?= e($tpl['id']) ?></span>
                            · <?= e(mb_strimwidth($tpl['ua'], 0, 80, '…')) ?>
                        </div>
                    </div>
                    <div class="row-actions">
                        <button type="button" class="row-btn edit-ua" title="编辑">✎</button>
                        <?php if (empty($tpl['builtin'])): ?>
                            <button type="button" class="row-btn del-ua" title="删除">🗑</button>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </section>

    <section class="tab-panel" id="panel-analytics">
        <div class="panel-head">
            <h2>🌐 全站统计代码</h2>
        </div>
        <div class="effect-hint">
            <p>📌 这里填写的统计代码会在 <b>全站所有页面</b>（前台、后台）底部原样输出，适合放置百度统计、Google Analytics、自建统计等。</p>
            <p style="margin-top:6px">💡 支持任意 HTML 片段（含 <code>&lt;script&gt;</code> / <code>&lt;noscript&gt;</code> / <code>&lt;img&gt;</code> 等），但必须包含 HTML 标签，否则保存会被拒绝。</p>
            <p style="margin-top:6px">⚠️ 如果同一统计服务商已由 Cloudflare 等 CDN 自动注入，请勿重复填写，避免重复统计。</p>
        </div>
        <div class="backup-cards">
            <div class="backup-card">
                <div class="backup-title">📝 统计代码</div>
                <form id="analyticsForm" class="auth-form" autocomplete="off">
                    <label>统计代码（原样输出到所有页面底部）
                        <textarea name="analyticsCode" id="analyticsCodeInput" rows="6"
                            placeholder="粘贴统计服务商提供的完整代码，例如：&#10;&lt;script src=&quot;https://example.com/track.js&quot; defer&gt;&lt;/script&gt;"
                            spellcheck="false"><?= e($siteCfg['analyticsCode'] ?? '') ?></textarea>
                        <span class="small muted">留空则不输出任何统计代码。</span>
                    </label>
                    <button type="submit" class="btn btn-primary">保存统计代码</button>
                </form>
            </div>
        </div>
    </section>

    <section class="tab-panel" id="panel-site">
        <div class="panel-head">
            <h2>站点设置</h2>
            <div class="sub-tabs">
                <button type="button" class="btn sub-tab-btn is-active" data-subtab="site-front">🏠 前台</button>
                <button type="button" class="btn sub-tab-btn" data-subtab="site-admin">🛠 后台</button>
            </div>
        </div>

        <div class="sub-tab-panel active" id="subtab-site-front">
            <div class="backup-cards">
                <div class="backup-card">
                    <div class="backup-title">⚙️ 基础设置</div>
                    <form id="siteForm" class="auth-form" autocomplete="off">
                        <label>网站图标 favicon
                            <div class="field-row">
                                <input type="text" name="favicon" id="siteFaviconInput" value="<?= e($siteCfg['favicon']) ?>" maxlength="300" placeholder="例如 photos/favicon.ico 或 https://...">
                                <button type="button" class="btn btn-sm pick-site-favicon">图片库</button>
                            </div>
                            <div id="siteFaviconPreviewWrap" class="site-favicon-preview" style="display:none;">
                                <img id="siteFaviconPreviewImg" src="" alt="favicon 预览">
                            </div>
                        </label>
                        <label>浏览器标签标题
                            <input type="text" name="pageTitle" value="<?= e($siteCfg['pageTitle']) ?>" maxlength="60" placeholder="例如 我的导航页">
                        </label>
                        <label>主标题（头像下方大字）
                            <input type="text" name="title" value="<?= e($siteCfg['title']) ?>" maxlength="40" placeholder="例如 欢迎来到我的小站">
                        </label>
                        <label>副标题（主标题下方小字）
                            <input type="text" name="subtitle" value="<?= e($siteCfg['subtitle']) ?>" maxlength="80" placeholder="例如 一个简单的导航页">
                        </label>
                        <label>头像图片
                            <div class="field-row">
                                <input type="text" name="avatar" id="siteAvatarInput" value="<?= e($siteCfg['avatar']) ?>" maxlength="300" placeholder="例如 photos/avatar.jpg 或 https://...">
                                <button type="button" class="btn btn-sm pick-site-avatar">图片库</button>
                            </div>
                            <div id="siteAvatarPreviewWrap" style="display:none;">
                                <img id="siteAvatarPreviewImg" src="" alt="头像预览">
                            </div>
                        </label>
                        <label>进入后台：头像连点次数
                            <input type="number" name="adminClickCount" value="<?= e($siteCfg['adminClickCount']) ?>" min="3" max="30" step="1" placeholder="默认 10，范围 3~30">
                            <span class="small muted">在时间窗口内连续点击头像达到该次数即进入后台，默认 10 次。</span>
                        </label>
                        <label>进入后台：时间窗口（秒）
                            <input type="number" name="adminClickWindow" value="<?= e($siteCfg['adminClickWindow']) ?>" min="1" max="60" step="0.5" placeholder="默认 10，范围 1~60">
                            <span class="small muted">只统计该时间窗口内的点击，超时自动清零，默认 10 秒。</span>
                        </label>
                        <label>页脚版权信息
                            <input type="text" name="copyright" value="<?= e($siteCfg['copyright'] ?? '') ?>" maxlength="120" placeholder="例如 © 2026 糯米の引导页">
                            <span class="small muted">显示在页面底部居中位置。留空则不显示。</span>
                        </label>
                        <label>页脚 GitHub 链接
                            <input type="text" name="githubUrl" value="<?= e($siteCfg['githubUrl'] ?? '') ?>" maxlength="300" placeholder="例如 https://github.com/你的用户名/你的仓库名">
                            <span class="small muted">留空则不显示 GitHub 链接。必须以 http:// 或 https:// 开头。</span>
                        </label>
                        <div class="footer-switch-row">
                            <div class="footer-switch-text">
                                <div class="footer-switch-title">显示 GitHub 链接</div>
                                <div class="small muted">关闭后页脚第二行只显示 Powered by，不显示 GitHub 链接（保留地址）</div>
                            </div>
                            <input type="hidden" name="githubEnabled" value="0">
                            <label class="switch-mini" style="flex-shrink:0;">
                                <input type="checkbox" name="githubEnabled" value="1" <?= (($siteCfg['githubEnabled'] ?? '1') === '1') ? 'checked' : '' ?>>
                                <span class="slider-mini"></span>
                            </label>
                        </div>
                        <label>页脚：Powered by 项目名
                            <input type="text" name="poweredBy" value="<?= e($siteCfg['poweredBy'] ?? '') ?>" maxlength="60" placeholder="例如 糯米の引导页">
                            <span class="small muted">显示在页脚第二行，前面自动加「Powered by 」。留空则不显示该行。</span>
                        </label>
                        <label>页脚：Powered by 链接
                            <input type="text" name="poweredByUrl" value="<?= e($siteCfg['poweredByUrl'] ?? '') ?>" maxlength="300" placeholder="例如 https://github.com/你的用户名/你的仓库名">
                            <span class="small muted">留空则 Powered by 只显示为纯文本，不可点击。</span>
                        </label>
                        <button type="submit" class="btn btn-primary">保存前台设置</button>
                    </form>
                </div>
                <div class="backup-card">
                    <div class="backup-title">🎞 轮播接口</div>
                    <form id="siteApiForm" class="auth-form" autocomplete="off">
                        <label>背景轮播 API 接口
                            <input type="text" name="bgApi" value="<?= e($siteCfg['bgApi']) ?>" maxlength="300" placeholder="例如 https://www.loliapi.com/acg/">
                        </label>
                        <label>Banner 轮播 API 接口
                            <input type="text" name="bannerApi" value="<?= e($siteCfg['bannerApi']) ?>" maxlength="300" placeholder="例如 https://www.loliapi.com/acg/pc/">
                        </label>
                        <label>背景轮播间隔（秒）
                            <input type="number" name="bgInterval" value="<?= e($siteCfg['bgInterval']) ?>" min="3" max="60" step="1">
                            <span class="small muted">背景图每隔多少秒换一张，默认 10 秒。</span>
                        </label>
                        <label>Banner 轮播间隔（秒）
                            <input type="number" name="bannerInterval" value="<?= e($siteCfg['bannerInterval']) ?>" min="3" max="60" step="1">
                            <span class="small muted">Banner 图每隔多少秒换一张，默认 6 秒。</span>
                        </label>
                        <label>缓存图片数量（每个池）
                            <input type="number" name="cacheLimit" value="<?= e($siteCfg['cacheLimit']) ?>" min="2" max="20" step="1">
                            <span class="small muted">背景与 Banner 各自维护一个缓存池，上限即此值。</span>
                        </label>
                        <button type="submit" class="btn btn-primary">保存接口设置</button>
                    </form>
                </div>
            </div>
        </div>

        <div class="sub-tab-panel" id="subtab-site-admin">
            <div class="backup-cards">
                <div class="backup-card">
                    <div class="backup-title">⚙️ 后台界面</div>
                    <form id="adminSiteForm" class="auth-form" autocomplete="off">
                        <label>登录页头像
                            <div class="field-row">
                                <input type="text" name="adminAvatar" id="adminAvatarInput" value="<?= e($siteCfg['adminAvatar']) ?>" maxlength="300" placeholder="例如 photos/admin.jpg">
                                <button type="button" class="btn btn-sm pick-admin-avatar">图片库</button>
                            </div>
                            <div id="adminAvatarPreviewWrap" style="display:none;">
                                <img id="adminAvatarPreviewImg" src="" alt="头像预览">
                            </div>
                        </label>
                        <label>登录页标题
                            <input type="text" name="adminTitle" value="<?= e($siteCfg['adminTitle']) ?>" maxlength="40">
                        </label>
                        <label>登录页副标题
                            <input type="text" name="adminSubtitle" value="<?= e($siteCfg['adminSubtitle']) ?>" maxlength="80">
                        </label>
                        <label>浏览器标签标题（登录后进入后台时显示）
                            <input type="text" name="adminPageTitle" value="<?= e($siteCfg['adminPageTitle']) ?>" maxlength="60">
                        </label>
                        <label>后台顶部栏文字
                            <input type="text" name="adminHeaderTitle" value="<?= e($siteCfg['adminHeaderTitle']) ?>" maxlength="30">
                        </label>
                        <button type="submit" class="btn btn-primary">保存后台设置</button>
                    </form>
                </div>
            </div>
        </div>
    </section>

    <section class="tab-panel" id="panel-backup">
        <div class="panel-head"><h2>备份与恢复</h2></div>
        <div class="backup-cards">
            <div class="backup-card">
                <div class="backup-title">📤 导出备份</div>
                <p class="muted">一键下载完整的 data.json 备份文件。</p>
                <a class="btn btn-primary" href="?action=export">下载备份 JSON</a>
            </div>
            <div class="backup-card">
                <div class="backup-title">📥 导入恢复</div>
                <p class="muted">上传本地备份 JSON，<b class="warn">将完整覆盖当前全部数据</b>，操作前请先导出备份。</p>
                <form id="importForm" enctype="multipart/form-data">
                    <input type="file" name="backup" id="importFile" accept=".json,application/json" required>
                    <button type="submit" class="btn btn-primary">导入并覆盖</button>
                </form>
            </div>
        </div>

        <div class="danger-zone">
            <div class="backup-card danger-card">
                <div class="backup-title">⚠️ 危险操作</div>
                <p class="muted">
                    <b class="danger-text">清除数据</b>：三种模式——"指定内容"仅清除勾选的模块、"清除全部"完整恢复出厂（保留图片与账号）、"完全清空"将系统恢复到全新安装状态（含图片与账号）。<br>
                </p>
                <button type="button" class="btn btn-danger" id="resetDataBtn">🗑 清除数据</button>
            </div>
        </div>
    </section>

    <section class="tab-panel" id="panel-account">
        <div class="panel-head"><h2>账号设置</h2></div>
        <div class="backup-cards">
            <div class="backup-card">
                <div class="backup-title">👤 修改管理员用户名</div>
                <form id="usernameForm" class="auth-form" autocomplete="off">
                    <label>当前密码
                        <input type="password" name="password" placeholder="输入当前密码以验证" required>
                    </label>
                    <label>新用户名
                        <input type="text" name="new_username" placeholder="输入新的管理员用户名" maxlength="30" required>
                    </label>
                    <button type="submit" class="btn btn-primary">修改用户名</button>
                </form>
            </div>
            <div class="backup-card">
                <div class="backup-title">🔑 修改管理员密码</div>
                <form id="pwdForm" class="auth-form" autocomplete="off">
                    <label>当前密码
                        <input type="password" name="old_password" placeholder="输入当前密码" required>
                    </label>
                    <label>新密码（至少 6 位）
                        <input type="password" name="new_password" placeholder="输入新密码" required>
                    </label>
                    <label>确认新密码
                        <input type="password" name="new_password2" placeholder="再次输入新密码" required>
                    </label>
                    <button type="submit" class="btn btn-primary">修改密码</button>
                </form>
            </div>
        </div>
    </section>

    <section class="tab-panel" id="panel-help">
        <div class="panel-head"><h2>📖 使用说明</h2></div>

        <div class="backup-cards">
            <div class="backup-card">
                <div class="backup-title">🚪 进入后台</div>
                <ul class="help-list">
                    <li>前台首页：<b>连续点击头像</b>（默认 10 次 / 10 秒内）即可进入后台。</li>
                    <li>连点次数与时间窗口可在 <code>站点设置 → 前台 → 基础设置</code> 中调整。</li>
                    <li>忘记管理员账号？删除 <code>admin/.admin.conf</code> 后重新初始化即可（会丢失账号，其他数据保留）。</li>
                </ul>
            </div>

            <div class="backup-card">
                <div class="backup-title">📋 卡片管理</div>
                <ul class="help-list">
                    <li><b>链接卡片</b>：点击跳转到指定网址；<b>图片卡片</b>：点击弹出大图。</li>
                    <li>每张卡片必须归属一个分类，分类可以重命名、换图标、拖拽排序。</li>
                    <li>长按分类标签 <b>350ms</b> 拖动排序；卡片拖动后需要点"保存排序"。</li>
                </ul>
            </div>

            <div class="backup-card">
                <div class="backup-title">🖼 图片管理</div>
                <ul class="help-list">
                    <li>支持 jpg / png / gif / webp，单张 ≤ 5MB；可拖拽批量上传。</li>
                    <li>也可以直接把图片放进 <code>photos/</code> 目录，刷新本页自动识别。</li>
                    <li>鼠标悬停图片出现"拖动 / 重命名 / 预览 / 删除"按钮。</li>
                    <li>重命名会自动同步更新引用了该图片的卡片、分类图标，以及站点设置里的头像 / favicon / 后台头像。</li>
                    <li><b>删除时</b>如果图片正在被卡片、分类或站点设置使用，会先弹窗列出所有引用位置，确认后才真正删除，同时清空对应引用（头像 / 网站图标回退到默认值）。</li>
                </ul>
            </div>

            <div class="backup-card">
                <div class="backup-title">👘 看板娘</div>
                <ul class="help-list">
                    <li>支持 gif / png / webp，单个 ≤ 10MB；也可以放进 <code>photos/kanban/</code>。</li>
                    <li>开启开关后，前台右下角会出现看板娘，可点击、拖动、切换形象。</li>
                    <li>语录显示时长按<b>秒</b>设置，可分别配置通用、深夜、早上、中午、下午、晚上。</li>
                </ul>
            </div>

            <div class="backup-card">
                <div class="backup-title">✨ JS 特效</div>
                <ul class="help-list">
                    <li>上传 <code>.js</code> 文件（≤ 2MB），或通过 URL 引用远程特效。</li>
                    <li><b>可控</b>特效（同时有 start / stop）：前台面板开关即时生效，不刷新页面。</li>
                    <li><b>固定</b>特效（无成对 start / stop）：前台面板<b>同样可以开关</b>，但每次开关会<b>刷新页面</b> —— 先把状态写进 cookie，再刷新让 <code>index.php</code> 决定是否注入脚本。后台禁用固定特效后，该特效会从前台面板彻底移除，访客无法自行启用。</li>
                    <li>特效名称、图标可自由修改；拖拽手柄可调整顺序。</li>
                    <li>matchKey 只在首次识别新特效时自动提取；手动修改后的值会永久保留，不会被刷新覆盖。觉得当前值不对时，在编辑弹窗里点「🔄 自动读取」从脚本内容重新提取。</li>
                </ul>
            </div>

            <div class="backup-card">
                <div class="backup-title">🎨 加载页</div>
                <ul class="help-list">
                    <li>上传 <code>.css</code> 文件到 <code>assets/preloaders/</code>，首行注释可写名称和描述：</li>
                    <li><code>/* @name 优雅 */</code> 与 <code>/* @description 紫罗兰柔光 */</code></li>
                    <li>拖动排序后，<b>排首位的主题自动成为默认</b>。</li>
                    <li>用户可在前台控制面板自行切换加载动画，选择会记录在本地。</li>
                </ul>
            </div>

            <div class="backup-card">
                <div class="backup-title">🖼 本地背景兜底</div>
                <ul class="help-list">
                    <li>本地兜底图片是 API 挂掉时的后备，支持上传或一键从 API 抓取。</li>
                    <li>背景与 Banner 各自独立开关，独立优先级（API 优先 / 本地优先）。</li>
                    <li>本地图会写入浏览器缓存，回访秒显；与 API 池的缓存隔离，不会互相挤占。</li>
                    <li>前台每张图切换前会做解码验证，解码失败的图不会进入轮播。</li>
                    <li><b>上传 / 抓取时按内容 MD5 去重</b>，同一张图不会存两份。</li>
                    <li>缩略图会自动生成到 <code>_thumbs</code> 目录，列表显示缩略图、预览才用原图。</li>
                    <li><b>自动识别横竖屏</b>：系统读取图片宽高，宽大于高即为"桌面端"，高大于宽即为"移动端"。如需强制修改，点击图片的"编辑"按钮进行微调。</li>
                    <li><b>抓取会使用 UA 伪装页里选定的模板</b>：默认背景走移动端模板（抓竖图）、Banner 走桌面端模板（抓横图）；想换方向去「🎭 UA 伪装」页切换即可。</li>
                </ul>
            </div>

            <div class="backup-card">
                <div class="backup-title">🎭 UA 伪装</div>
                <ul class="help-list">
                    <li>抓取本地背景时携带的 User-Agent 等请求头在这里维护。</li>
                    <li>内置两个模板：移动端 / 桌面端，可编辑内容但不可删除。</li>
                    <li>可以「＋ 新增模板」创建自定义 UA（例如模拟 iPad、Chrome OS 等）。</li>
                    <li>背景 / Banner 抓取各自选择一个模板，可在本地背景页「UA」按钮快速切换。</li>
                    <li><b>这里的模板就是「立即抓取」实际使用的请求头</b>：默认背景走移动端模板（抓竖图）、Banner 走桌面端模板（抓横图）。</li>
                </ul>
            </div>

            <div class="backup-card">
                <div class="backup-title">📢 公告管理</div>
                <ul class="help-list">
                    <li>公告会显示在站内公告页 <code>/announcement.php</code>，404 页的「查看公告」按钮会跳转到该页。</li>
                    <li>置顶的公告会排在最上方，禁用的公告不会显示在公告页。</li>
                    <li>公告内容支持 HTML 标签，但不允许包含 <code>&lt;script&gt;</code>。</li>
                    <li>四种公告类型：通知 / 成功 / 警告 / 错误，分别对应蓝 / 绿 / 黄 / 红四种配色。</li>
                </ul>
            </div>

            <div class="backup-card">
                <div class="backup-title">🌐 统计代码</div>
                <ul class="help-list">
                    <li>独立的「🌐 统计代码」标签页，粘贴后全站所有页面（前台 + 后台）自动生效。</li>
                    <li>支持任意 HTML 片段，但必须包含 HTML 标签，否则保存会被拒绝。</li>
                    <li>如果同一服务商已由 Cloudflare 等 CDN 自动注入，请勿重复填写。</li>
                    <li>留空则不输出任何统计代码。</li>
                </ul>
            </div>

            <div class="backup-card">
                <div class="backup-title">⚙ 站点设置</div>
                <ul class="help-list">
                    <li><b>前台</b>：标题、头像、favicon、进入后台的连点次数/时间窗口。</li>
                    <li><b>前台</b>：背景与 Banner 的 API、轮播间隔、缓存池上限。</li>
                    <li><b>前台</b>：页脚版权信息、GitHub 链接（含开关）、Powered by 项目名与链接。</li>
                    <li><b>后台</b>：登录页头像、标题、副标题、标签标题、顶部栏文字。</li>
                </ul>
            </div>

            <div class="backup-card">
                <div class="backup-title">💾 备份恢复</div>
                <ul class="help-list">
                    <li><b>导出</b>：下载完整 data.json 备份（含卡片、分类、特效、看板娘、语录、站点设置、统计代码、公告）。</li>
                    <li><b>导入</b>：完整覆盖当前数据，操作前建议先导出。</li>
                    <li><b>清除数据</b>：
                        <br>① 指定内容：只清勾选的模块；
                        <br>② 清除全部：恢复出厂设置（保留账号与图片）；
                        <br>③ 完全清空：删除所有数据与账号，恢复到全新安装。</li>
                    <li>清除后系统会重新加载并展示加载动画。</li>
                </ul>
            </div>
        </div>
    </section>
<?php endif; ?>
</div>

<div class="modal-mask" id="announcementEditModal" style="display:none;z-index:650;">
    <div class="modal-box">
        <div class="modal-head">
            <h3 id="announcementEditTitle">新增公告</h3>
            <button type="button" class="modal-close" id="announcementEditClose">✕</button>
        </div>
        <input type="hidden" id="announcementEditId">
        <div class="form-group">
            <label class="form-label">公告标题 <span class="req">*</span></label>
            <input type="text" id="announcementEditName" maxlength="100" placeholder="例如：站点维护通知">
        </div>
        <div class="form-group">
            <label class="form-label">公告类型</label>
            <div class="announcement-type-switch">
                <button type="button" class="btn type-btn announcement-type-btn is-active" data-type="info">ℹ️ 通知</button>
                <button type="button" class="btn type-btn announcement-type-btn" data-type="success">✅ 成功</button>
                <button type="button" class="btn type-btn announcement-type-btn" data-type="warning">⚠️ 警告</button>
                <button type="button" class="btn type-btn announcement-type-btn" data-type="error">🚫 错误</button>
            </div>
        </div>
        <div class="form-group">
            <label class="form-label">公告内容</label>
            <textarea id="announcementEditContent" rows="6" placeholder="支持 HTML 标签，不允许包含 &lt;script&gt;"></textarea>
            <span class="small muted">留空则只显示标题。</span>
        </div>
        <div class="form-group">
            <div class="announcement-switch-row">
                <label class="switch-mini">
                    <input type="checkbox" id="announcementEditPinned">
                    <span class="slider-mini"></span>
                </label>
                <div class="announcement-switch-text">
                    <div class="announcement-switch-title">置顶</div>
                    <div class="small muted">置顶的公告会排在列表最上方</div>
                </div>
            </div>
        </div>
        <div class="form-group">
            <div class="announcement-switch-row">
                <label class="switch-mini">
                    <input type="checkbox" id="announcementEditEnabled" checked>
                    <span class="slider-mini"></span>
                </label>
                <div class="announcement-switch-text">
                    <div class="announcement-switch-title">启用</div>
                    <div class="small muted">禁用的公告不会显示在公告页</div>
                </div>
            </div>
        </div>
        <div class="modal-foot">
            <button type="button" class="btn btn-ghost" id="announcementEditCancel">取消</button>
            <button type="button" class="btn btn-primary" id="announcementEditSave">保存</button>
        </div>
    </div>
</div>

<div class="modal-mask" id="cardModal" style="display:none">
    <div class="modal-box">
        <div class="modal-head">
            <h3 id="cardModalTitle">新增卡片</h3>
            <button type="button" class="modal-close" id="cardModalClose">✕</button>
        </div>
        <form id="cardForm" autocomplete="off">
            <input type="hidden" name="id" id="f_id">
            <div class="form-group">
                <label class="form-label">卡片类型</label>
                <div class="type-switch">
                    <button type="button" class="btn type-btn is-active" data-type="link">🔗 链接卡片</button>
                    <button type="button" class="btn type-btn" data-type="image">🖼 图片卡片</button>
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">所属分类</label>
                <select name="category" id="f_category"></select>
            </div>
            <div class="form-group">
                <label class="form-label">名称 <span class="req">*</span></label>
                <input type="text" name="name" id="f_name" placeholder="卡片名称" maxlength="30" required>
            </div>
            <div class="form-group">
                <label class="form-label">小字副标题</label>
                <input type="text" name="sub" id="f_sub" placeholder="可留空" maxlength="40">
            </div>
            <div class="form-group">
                <label class="form-label">列表小图标</label>
                <div class="field-row">
                    <input type="text" name="icon" id="f_icon" placeholder="图片路径 或 URL">
                    <button type="button" class="btn btn-sm pick-photo-icon">图片库</button>
                </div>
                <div id="cardIconPreviewWrap" style="display:none;">
                    <img id="cardIconPreviewImg" src="" alt="图标预览">
                </div>
            </div>
            <div class="form-group" id="group_href">
                <label class="form-label">跳转网址（href）<span class="req">*</span></label>
                <input type="text" name="href" id="f_href" placeholder="https://example.com">
            </div>
            <div class="form-group" id="group_image" style="display:none">
                <label class="form-label">弹窗大图 <span class="req">*</span></label>
                <div class="field-row">
                    <input type="text" name="image" id="f_image" placeholder="图片路径 或 URL">
                    <button type="button" class="btn btn-sm pick-photo-image">图片库</button>
                </div>
                <div class="card-image-preview" id="cardImagePreview" style="display:none;">
                    <img src="" alt="" class="edit-card-big-preview">
                </div>
            </div>
            <div class="modal-foot">
                <button type="button" class="btn" id="cardCancel">取消</button>
                <button type="submit" class="btn btn-primary" id="cardSave">保存</button>
            </div>
        </form>
    </div>
</div>

<div class="modal-mask" id="catEditModal" style="display:none;z-index:650;">
    <div class="modal-box modal-sm">
        <div class="modal-head">
            <h3 id="catModalTitle">新增分类</h3>
            <button type="button" class="modal-close" id="catModalClose">✕</button>
        </div>
        <div style="display:flex;flex-direction:column;gap:12px;">
            <div>
                <label style="display:block;margin-bottom:4px;">分类名称 <span style="color:red;">*</span></label>
                <input type="text" id="catModalName" maxlength="12" style="width:100%;padding:8px;border-radius:8px;border:1px solid #ddd;">
            </div>
            <div>
                <label style="display:block;margin-bottom:4px;">分类图标（emoji / 图片路径）</label>
                <div style="display:flex;gap:6px;">
                    <input type="text" id="catModalIcon" maxlength="120" style="flex:1;padding:8px;border-radius:8px;border:1px solid #ddd;">
                    <button type="button" class="btn btn-sm pick-cat-icon-modal">选择图片</button>
                </div>
            </div>
            <div id="catPreviewWrap" style="display:none;">
                <label style="display:block;margin-bottom:4px;">图标预览</label>
                <div style="display:flex;justify-content:center;align-items:center;min-height:50px;padding:4px 0;">
                    <img id="catModalIconPreview" src="" alt="预览" style="max-height:42px;max-width:100%;object-fit:contain;display:none;">
                </div>
            </div>
        </div>
        <div class="modal-foot">
            <button type="button" class="btn btn-ghost" id="catModalCancel">取消</button>
            <button type="button" class="btn btn-primary" id="catModalSave">保存</button>
        </div>
    </div>
</div>

<div class="modal-mask" id="effectEditModal" style="display:none;z-index:650;">
    <div class="modal-box modal-sm">
        <div class="modal-head">
            <h3>编辑 JS 特效</h3>
            <button type="button" class="modal-close" id="effectEditClose">✕</button>
        </div>
        <input type="hidden" id="effectEditId">
        <div class="form-group">
            <label class="form-label">特效名称 <span class="req">*</span></label>
            <input type="text" id="effectEditName" maxlength="30" placeholder="例如：雪花飘落">
        </div>
        <div class="form-group">
            <label class="form-label">图标（emoji 或文字）</label>
            <input type="text" id="effectEditIcon" maxlength="4" placeholder="例如：❄️">
        </div>
        <div class="form-group">
            <label class="form-label">匹配标识 matchKey</label>
            <div class="field-row">
                <input type="text" id="effectEditMatchKey" maxlength="60" placeholder="与脚本 EffectsRegistry.push 里的 name 保持一致">
                <button type="button" class="btn btn-sm" id="effectReadMatchKeyBtn" title="从 JS 文件内容重新读取 matchKey">🔄 自动读取</button>
            </div>
            <p class="small muted" style="margin-top:4px;">后台只在首次识别新特效时自动提取；手动修改后的值会永久保留，刷新不会被覆盖。觉得当前值不对时点「自动读取」重新提取。</p>
        </div>
        <div class="form-group">
            <label class="form-label">文件名（不含 .js）</label>
            <input type="text" id="effectEditFilename" maxlength="60" placeholder="本地特效可修改文件名">
        </div>
        <div class="form-group">
            <label class="form-label">文件路径 / URL</label>
            <input type="text" id="effectEditFile" disabled style="opacity:.6;cursor:not-allowed;">
        </div>
        <div class="modal-foot">
            <button type="button" class="btn btn-ghost" id="effectEditCancel">取消</button>
            <button type="button" class="btn btn-primary" id="effectEditSave">保存</button>
        </div>
    </div>
</div>

<div class="modal-mask" id="effectUrlModal" style="display:none;z-index:650;">
    <div class="modal-box modal-sm">
        <div class="modal-head">
            <h3>通过 URL 添加 JS 特效</h3>
            <button type="button" class="modal-close" id="effectUrlClose">✕</button>
        </div>
        <div class="form-group">
            <label class="form-label">JS 文件 URL <span class="req">*</span></label>
            <input type="text" id="effectUrlInput" placeholder="https://example.com/effect.js" autocomplete="off">
        </div>
        <div class="form-group">
            <label class="form-label">特效名称</label>
            <input type="text" id="effectUrlName" maxlength="30" placeholder="留空则取 URL 文件名">
        </div>
        <div class="form-group">
            <label class="form-label">图标（emoji 或文字）</label>
            <input type="text" id="effectUrlIcon" maxlength="4" placeholder="✨">
        </div>
        <div class="form-group">
            <label class="form-label">匹配标识 matchKey（可选）</label>
            <input type="text" id="effectUrlMatchKey" placeholder="留空则服务端自动尝试从 JS 内容或文件名提取">
        </div>
        <div class="modal-foot">
            <button type="button" class="btn btn-ghost" id="effectUrlCancel">取消</button>
            <button type="button" class="btn btn-primary" id="effectUrlSave">添加</button>
        </div>
    </div>
</div>

<div class="modal-mask" id="preloaderEditModal" style="display:none;z-index:650;">
    <div class="modal-box modal-sm">
        <div class="modal-head">
            <h3>编辑加载页主题</h3>
            <button type="button" class="modal-close" id="preloaderEditClose">✕</button>
        </div>
        <input type="hidden" id="preloaderEditKey">
        <div class="form-group">
            <label class="form-label">显示名称 <span class="req">*</span></label>
            <input type="text" id="preloaderEditName" maxlength="30" placeholder="例如：优雅">
        </div>
        <div class="form-group">
            <label class="form-label">描述</label>
            <input type="text" id="preloaderEditDesc" maxlength="60" placeholder="一句话描述">
        </div>
        <div class="form-group">
            <label class="form-label">文件名（不含 .css）</label>
            <input type="text" id="preloaderEditFilename" maxlength="60" placeholder="例如：elegant">
            <p class="small muted" style="margin-top:4px;">修改后磁盘上的 .css 会同步重命名，文件名同时作为主题的 key。</p>
        </div>
        <div class="modal-foot">
            <button type="button" class="btn btn-ghost" id="preloaderEditCancel">取消</button>
            <button type="button" class="btn btn-primary" id="preloaderEditSave">保存</button>
        </div>
    </div>
</div>

<div class="modal-mask" id="girlRenameModal" style="display:none;z-index:650;">
    <div class="modal-box modal-sm">
        <div class="modal-head">
            <h3>重命名看板娘文件</h3>
            <button type="button" class="modal-close" id="girlRenameClose">✕</button>
        </div>
        <input type="hidden" id="girlRenameFile">
        <div class="form-group">
            <label class="form-label">当前文件</label>
            <div class="photo-edit-preview">
                <img id="girlRenamePreviewImg" src="" alt="">
            </div>
        </div>
        <div class="form-group">
            <label class="form-label">新文件名 <span class="req">*</span></label>
            <input type="text" id="girlRenameName" maxlength="60" placeholder="不含扩展名">
        </div>
        <div class="modal-foot">
            <button type="button" class="btn btn-ghost" id="girlRenameCancel">取消</button>
            <button type="button" class="btn btn-primary" id="girlRenameSave">保存</button>
        </div>
    </div>
</div>

<div class="modal-mask" id="photoEditModal" style="display:none;z-index:650;">
    <div class="modal-box modal-sm">
        <div class="modal-head">
            <h3>重命名图片</h3>
            <button type="button" class="modal-close" id="photoEditClose">✕</button>
        </div>
        <input type="hidden" id="photoEditPath">
        <div class="form-group">
            <label class="form-label">当前图片</label>
            <div class="photo-edit-preview">
                <img id="photoEditPreviewImg" src="" alt="">
            </div>
        </div>
        <div class="form-group">
            <label class="form-label">新文件名 <span class="req">*</span></label>
            <input type="text" id="photoEditName" maxlength="60" placeholder="不含扩展名">
        </div>
        <div class="modal-foot">
            <button type="button" class="btn btn-ghost" id="photoEditCancel">取消</button>
            <button type="button" class="btn btn-primary" id="photoEditSave">保存</button>
        </div>
    </div>
</div>

<div class="modal-mask" id="localRenameModal" style="display:none;z-index:650;">
    <div class="modal-box modal-sm">
        <div class="modal-head">
            <h3>编辑本地图片</h3>
            <button type="button" class="modal-close" id="localRenameClose">✕</button>
        </div>
        <input type="hidden" id="localRenameType">
        <input type="hidden" id="localRenameFile">
        <div class="form-group">
            <label class="form-label">当前图片</label>
            <div class="photo-edit-preview">
                <img id="localRenamePreviewImg" src="" alt="">
            </div>
        </div>
        
        <div class="form-group">
            <label class="form-label">目标设备</label>
            <div class="local-target-switch">
                <button type="button" class="btn target-btn" data-target="auto">✨ 自动识别</button>
                <button type="button" class="btn target-btn" data-target="desktop">🖥 桌面端</button>
                <button type="button" class="btn target-btn" data-target="mobile">📱 移动端</button>
                <button type="button" class="btn target-btn" data-target="both">🌐 通用</button>
            </div>
        </div>
        
        <div class="form-group">
            <label class="form-label">新文件名 <span class="req">*</span></label>
            <input type="text" id="localRenameName" maxlength="60" placeholder="不含扩展名">
        </div>
        
        <div class="modal-foot">
            <button type="button" class="btn btn-ghost" id="localRenameCancel">取消</button>
            <button type="button" class="btn btn-primary" id="localRenameSave">保存</button>
        </div>
    </div>
</div>

<div class="modal-mask" id="uaEditModal" style="display:none;z-index:650;">
    <div class="modal-box">
        <div class="modal-head">
            <h3 id="uaEditTitle">新增 UA 模板</h3>
            <button type="button" class="modal-close" id="uaEditClose">✕</button>
        </div>
        <input type="hidden" id="uaEditId">
        <div class="form-group">
            <label class="form-label">模板名称 <span class="req">*</span></label>
            <input type="text" id="uaEditName" maxlength="50" placeholder="例如：iOS 18 Safari（移动端）">
        </div>
        <div class="form-group">
            <label class="form-label">User-Agent <span class="req">*</span></label>
            <textarea id="uaEditUa" rows="3" spellcheck="false" placeholder="Mozilla/5.0 ..."></textarea>
        </div>
        <div class="form-group">
            <label class="form-label">Accept</label>
            <textarea id="uaEditAccept" rows="2" spellcheck="false" placeholder="text/html,application/xhtml+xml,..."></textarea>
        </div>
        <div class="form-group">
            <label class="form-label">Accept-Language</label>
            <input type="text" id="uaEditLang" placeholder="zh-CN,zh;q=0.9">
        </div>
        <div class="form-group">
            <label class="form-label">Accept-Encoding</label>
            <input type="text" id="uaEditEnc" placeholder="gzip, deflate, br">
        </div>
        <div class="form-group">
            <label class="form-label">额外请求头（每行一个，格式 <code>Name: value</code>）</label>
            <textarea id="uaEditExtra" rows="3" spellcheck="false" placeholder="Sec-Fetch-Mode: navigate"></textarea>
        </div>
        <div class="modal-foot">
            <button type="button" class="btn btn-ghost" id="uaEditCancel">取消</button>
            <button type="button" class="btn btn-primary" id="uaEditSave">保存</button>
        </div>
    </div>
</div>

<div class="modal-mask" id="uaPickModal" style="display:none;z-index:650;">
    <div class="modal-box modal-sm">
        <div class="modal-head">
            <h3 id="uaPickTitle">选择 UA 模板</h3>
            <button type="button" class="modal-close" id="uaPickClose">✕</button>
        </div>
        <p class="small muted" style="margin-bottom:10px;">点击选中即可切换，下次抓取立即生效。</p>
        <div class="ua-pick-list" id="uaPickList"></div>
        <div class="modal-foot">
            <button type="button" class="btn btn-ghost" id="uaPickCancel">取消</button>
        </div>
    </div>
</div>

<div class="modal-mask" id="photoModal" style="display:none">
    <div class="modal-box modal-lg">
        <div class="modal-head">
            <h3>选择图片</h3>
            <button type="button" class="modal-close" id="photoModalClose">✕</button>
        </div>
        <div class="photo-modal-toolbar">
            <button type="button" class="btn btn-sm btn-primary" id="photoModalUploadBtn">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                上传新图片
            </button>
            <span class="photo-modal-hint">支持 jpg / png / gif / webp，单个 ≤ 5MB</span>
            <input type="file" id="photoModalInput" accept=".jpg,.jpeg,.png,.gif,.webp" multiple hidden>
        </div>
        <div class="photo-grid" id="photoModalGrid">
            <?php foreach ($photos as $photoItem):
                $pFile  = is_array($photoItem) ? ($photoItem['file'] ?? '') : $photoItem;
                $pThumb = is_array($photoItem) ? ($photoItem['thumb'] ?? $pFile) : $photoItem;
                if ($pFile === '') continue;
            ?>
                <div class="photo-cell" data-path="<?= e($pFile) ?>">
                    <div class="photo-cell-image">
                        <img src="../<?= e($pThumb) ?>" alt="" loading="lazy" decoding="async">
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<div class="modal-mask" id="confirmModal" style="display:none">
    <div class="modal-box modal-sm">
        <div class="modal-head">
            <h3 id="confirmTitle">确认操作</h3>
            <button type="button" class="modal-close" id="confirmCloseOnly">✕</button>
        </div>
        <p class="confirm-msg" id="confirmMsg"></p>
        <div class="modal-foot">
            <button type="button" class="btn btn-danger" id="confirmOk">确定</button>
        </div>
    </div>
</div>

<div class="modal-mask" id="resetDataModal" style="display:none;z-index:660;">
    <div class="modal-box">
        <div class="modal-head">
            <h3>🗑 清除数据</h3>
            <button type="button" class="modal-close" id="resetDataClose">✕</button>
        </div>
        <form id="resetDataForm" autocomplete="off">
            <div class="form-group">
                <label class="form-label">清除方式</label>
                <div class="type-switch type-switch-3">
                    <button type="button" class="btn type-btn is-active" data-reset-mode="partial">📦 指定内容</button>
                    <button type="button" class="btn type-btn" data-reset-mode="all">🧹 清除全部</button>
                    <button type="button" class="btn type-btn" data-reset-mode="full">💥 完全清空</button>
                </div>
            </div>
            <div class="form-group" id="resetPartialGroup">
                <label class="form-label">选择要清除的内容（可多选）</label>
                <div class="reset-module-list" id="resetModuleList"></div>
            </div>
            <div class="form-group hidden" id="resetAllHint">
                <div class="reset-hint reset-hint-danger">
                    <p><b>⚠️ 完整恢复出厂设置</b></p>
                    <p>将清空所有卡片、分类、JS 特效、看板娘配置、语录设置、站点设置、加载页主题、本地背景兜底、UA 模板、公告，并删除 <code>assets/js/</code>、<code>assets/preloaders/</code>、<code>photos/kanban/</code>、<code>photos/localbg/</code>、<code>photos/localbanner/</code> 下的物理文件。</p>
                    <p class="small muted">📌 不会删除 <code>photos/</code> 里的图片，也不会清除管理员账号与密码。</p>
                </div>
            </div>
            <div class="form-group hidden" id="resetFullHint">
                <div class="reset-hint reset-hint-danger reset-hint-full">
                    <p><b>💥 完全清空所有配置</b></p>
                    <p>将删除所有数据，包括：卡片、分类、JS 特效、看板娘、语录设置、站点设置、加载页主题、本地背景兜底、UA 模板、公告、<code>photos/</code> 里全部图片、管理员账号与密码。</p>
                    <p class="small"><b class="warn">⚠️ 此操作会把系统恢复到全新安装状态，所有上传的图片都无法恢复！</b></p>
                    <p class="small"><b class="warn">操作完成后会自动登出，需要重新初始化管理员账号。</b></p>
                </div>
            </div>
            <div class="form-group">
                <label class="form-label" id="resetConfirmLabel">二次确认：请输入 <code>RESET</code></label>
                <input type="text" id="resetConfirmInput" placeholder="请输入 RESET" autocomplete="off">
            </div>
            <div class="modal-foot">
                <button type="button" class="btn" id="resetDataCancel">取消</button>
                <button type="submit" class="btn btn-danger" id="resetDataSubmit" disabled>确认清除</button>
            </div>
        </form>
    </div>
</div>

<div id="toast" class="toast"></div>

<?php include __DIR__ . '/../analytics.php'; ?>
</body>
<?php ob_end_flush(); ?>