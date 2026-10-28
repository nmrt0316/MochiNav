<?php
/* ==========================================================================
   糯米の引导页 - 在线升级模块 admin/updater.php
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
    $fp = @fopen(update_lock_path(), 'c');
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
            $result['reason'] .= ' 文件所有者是「' . $result['owner'] . '」。建议把项目目录所有者改为 PHP 运行用户，或设置 775 权限并加入同一用户组。';
        } else {
            $result['reason'] .= ' 建议检查项目根目录权限（一般 755，文件 644）。';
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

/* 从 data.json 读取 site 中与升级相关的配置 */
function update_read_site_cfg() {
    $cfg = ['githubToken' => '', 'updateChannel' => 'stable'];
    if (defined('DATA_FILE') && file_exists(DATA_FILE)) {
        $raw = @file_get_contents(DATA_FILE);
        $data = json_decode($raw, true);
        if (is_array($data) && isset($data['site']) && is_array($data['site'])) {
            $cfg['githubToken']   = trim((string)($data['site']['githubToken'] ?? ''));
            $cfg['updateChannel'] = strtolower(trim((string)($data['site']['updateChannel'] ?? 'stable')));
        }
    }
    if (!in_array($cfg['updateChannel'], ['stable', 'beta'], true)) {
        $cfg['updateChannel'] = 'stable';
    }
    return $cfg;
}

/* 拉取 GitHub Release：
   - stable 走 /releases/latest，取最新正式版
   - beta   走 /releases，取数组第一个（最新发布，可能是预发布）
   - 若配置了 githubToken，自动带 Authorization 头 */
function update_fetch_release($force = false) {
    update_ensure_dir();
    $cacheFile = UPDATE_DIR . '/github_release_cache.json';
    $siteCfg = update_read_site_cfg();
    $channel = $siteCfg['updateChannel'];
    $token   = $siteCfg['githubToken'];

    if (!$force && file_exists($cacheFile)) {
        $mtime = @filemtime($cacheFile);
        if ($mtime !== false && (time() - $mtime) < 300) {
            $raw = @file_get_contents($cacheFile);
            $cached = json_decode($raw, true);
            if (is_array($cached) && isset($cached['data']) && ($cached['channel'] ?? 'stable') === $channel) {
                return ['ok' => true, 'data' => $cached['data'], 'cached' => true];
            }
        }
    }

    $repo = UPDATE_GITHUB_REPO;
    $apiPath = ($channel === 'beta') ? '/releases' : '/releases/latest';
    $url = 'https://api.github.com/repos/' . $repo . $apiPath;

    $headerLines = "User-Agent: NuomiNav-Updater/1.0\r\nAccept: application/vnd.github+json\r\n";
    if ($token !== '') {
        $headerLines .= 'Authorization: Bearer ' . $token . "\r\n";
    }

    $ctx = stream_context_create([
        'http' => [
            'method' => 'GET',
            'header' => $headerLines,
            'timeout' => 15,
            'ignore_errors' => true,
        ],
        'https' => [
            'method' => 'GET',
            'header' => $headerLines,
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

    if ($channel === 'beta') {
        if (isset($json['message'])) {
            return ['ok' => false, 'msg' => 'GitHub API 错误：' . (string)$json['message']];
        }
        if (count($json) === 0) {
            return ['ok' => false, 'msg' => 'GitHub 仓库还没有任何 Release 记录。'];
        }
        if (!isset($json[0]) || !is_array($json[0])) {
            return ['ok' => false, 'msg' => 'GitHub Releases 返回格式异常。'];
        }
        $releaseData = $json[0];
    } else {
        if (isset($json['message'])) {
            return ['ok' => false, 'msg' => 'GitHub API 错误：' . (string)$json['message']];
        }
        $releaseData = $json;
    }

    $tag = (string)($releaseData['tag_name'] ?? '');
    if ($tag === '') {
        return ['ok' => false, 'msg' => 'GitHub Release 缺少 tag_name。'];
    }
    $version = ltrim($tag, 'vV');

    $downloadUrl = '';
    $sha256 = '';
    if (isset($releaseData['assets']) && is_array($releaseData['assets'])) {
        foreach ($releaseData['assets'] as $asset) {
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
        'name' => (string)($releaseData['name'] ?? ''),
        'body' => (string)($releaseData['body'] ?? ''),
        'published_at' => (string)($releaseData['published_at'] ?? ''),
        'html_url' => (string)($releaseData['html_url'] ?? ''),
        'download_url' => $downloadUrl,
        'sha256' => $sha256,
        'prerelease' => !empty($releaseData['prerelease']),
    ];

    update_atomic_write_json($cacheFile, ['ts' => time(), 'channel' => $channel, 'data' => $data]);
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

/* 绝对保护：无论升级包带没带，都不覆盖
   - admin/data.json       用户数据
   - admin/.admin.conf     管理员账号
   - admin/.update/        升级工作目录
   - photos/             （用户上传的图片）
   - LICENSE              协议文件 */
function update_is_preserved($relPath) {
    $rel = ltrim(str_replace('\\', '/', (string)$relPath), '/');

    $preserved = ['admin/data.json', 'admin/.admin.conf', 'admin/.update'];
    foreach ($preserved as $p) {
        if ($rel === $p) return true;
        if (strpos($rel, $p . '/') === 0) return true;
    }

    if ($rel === 'photos' || strpos($rel, 'photos/') === 0) {
        return true;
    }

    if ($rel === 'LICENSE') return true;

    return false;
}

/* 允许覆盖的白名单：
   - 根目录代码/文档：index.php / 404.php / announcement.php / analytics.php / README.md / CHANGELOG.md
   - admin/ 下 php/css/js/md/txt/html/htm
   - assets/js/ 下 .js，assets/preloaders/ 下 .css
   - assets/ 下其它静态资源按扩展名放行
   用户自己添加的其它文件（同目录不同名）不会被删除，只会覆盖同名文件。 */
function update_is_allowed_overwrite($relPath) {
    $rel = ltrim(str_replace('\\', '/', (string)$relPath), '/');
    if ($rel === '') return false;
    if (update_is_preserved($rel)) return false;

    $rootWhitelist = [
        'index.php', '404.php', 'announcement.php', 'analytics.php',
        'README.md', 'CHANGELOG.md',
    ];
    if (in_array($rel, $rootWhitelist, true)) return true;

    if (strpos($rel, 'admin/') === 0) {
        $ext = strtolower(pathinfo($rel, PATHINFO_EXTENSION));
        return in_array($ext, ['php', 'css', 'js', 'md', 'txt', 'html', 'htm'], true);
    }

    if (strpos($rel, 'assets/js/') === 0) {
        return strtolower(pathinfo($rel, PATHINFO_EXTENSION)) === 'js';
    }
    if (strpos($rel, 'assets/preloaders/') === 0) {
        return strtolower(pathinfo($rel, PATHINFO_EXTENSION)) === 'css';
    }
    if (strpos($rel, 'assets/') === 0) {
        $ext = strtolower(pathinfo($rel, PATHINFO_EXTENSION));
        return in_array($ext, ['php', 'css', 'js', 'html', 'htm', 'json', 'txt'], true);
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

/* 5 个在线升级 API 的统一入口。
   admin/index.php 的 5 个 update_* case 会直接调用本函数。
   鉴权由调用方负责（调用前已 require_login()）。 */
function updater_handle_action($action) {
    switch ($action) {
        case 'update_check':
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
            $state = update_read_state();
            $latestBackup = update_get_latest_backup();
            respond(true, '', [
                'state' => $state,
                'can_rollback' => $latestBackup !== null,
                'current_version' => NUOMI_VERSION,
            ]);
            break;

        default:
            respond(false, '未知升级操作：' . $action);
    }
}