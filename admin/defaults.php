<?php
/* ==========================================================================
   糯米の引导页 - 默认值唯一权威来源
   
   前台 index.php / announcement.php 和后台 admin/index.php 都 require_once 本文件。
   首次安装时这些默认值写入 data.json；之后前台只读 data.json，
   仅当某字段被用户手动删空时，用这里的值兜底。
   ========================================================================== */

/* ==========================================================================
   版本号
   升级时只改这一行，后台顶部栏会自动显示。
   ========================================================================== */
if (!defined('NUOMI_VERSION')) {
    define('NUOMI_VERSION', '1.1.0');
}

if (!defined('NUOMI_DEFAULTS_LOADED')) {
    define('NUOMI_DEFAULTS_LOADED', true);

    /* ==========================================================================
       站点配置默认值
       ========================================================================== */
    if (!function_exists('site_defaults')) {
        function site_defaults() {
            return [
                'pageTitle'        => '糯米の引导页',
                'title'            => '糯米の引导页',
                'subtitle'         => '又一个引导页',
                'avatar'           => 'https://q1.qlogo.cn/g?b=qq&nk=1498583218&s=640',
                'favicon'          => 'photos/QQ.jpg',
                'bgApi'            => 'https://www.loliapi.com/acg/',
                'bannerApi'        => 'https://www.loliapi.com/acg/pc/',
                'bgInterval'       => '10',
                'bannerInterval'   => '6',
                'cacheLimit'       => '8',
                'adminAvatar'      => 'photos/QQ.jpg',
                'adminTitle'       => '后台登录',
                'adminSubtitle'    => '糯米の引导页管理',
                'adminPageTitle'   => '糯米の引导页 · 后台管理',
                'adminHeaderTitle' => '管理后台',
                'adminClickCount'  => '10',
                'adminClickWindow' => '10',
                'preloader'        => '1',
                'preloaderStyle'   => 'elegant',
                'analyticsCode'    => '',
                'localBgPriority'     => 'api',
                'localBannerPriority' => 'api',
                'localBgUaId'         => 'ua_mobile_default',
                'localBannerUaId'     => 'ua_desktop_default',
                /* 页脚配置 */
                'copyright'           => '© ' . date('Y') . ' 糯米の引导页',
                'githubUrl'           => 'https://github.com/nmrt0316',
                'githubEnabled'       => '1',
                'poweredBy'           => '糯米の引导页',
                'poweredByUrl'        => 'https://github.com/nmrt0316/MochiNav',
            ];
        }
    }

    /* ==========================================================================
       看板娘语录默认值
       ========================================================================== */
    if (!function_exists('tips_defaults')) {
        function tips_defaults() {
            return [
                'enabled'  => true,
                'duration' => 3000,
                'texts'    => [
                    '你好呀，我是这里的小助手～', '欢迎来到糯米の引导页！', '希望你会喜欢这里～',
                    '我会一直在这里陪着你', '有事没事都可以来找我玩', '这里是你的小天地哦',
                    '今天也要开开心心呀', '需要我时随时点我就好', '又见面啦～', '很高兴遇见你',
                    '摸鱼的时候记得看看我 👀', '别卷了，休息一下', '点我，可以切换下一句。',
                    '气泡右上角的小齿轮，点击可以进入设置。', '你知道吗？在设置里面可以更换形象。',
                    '特效太杂？设置里面可以单独控制特效。', '长按我，可以随意拖动位置。',
                    '记得多喝水哦 💧', '久坐的话，起来活动一下吧', '眼睛累了就看看远处～',
                    '伸个懒腰放松一下', '深呼吸，让心情放个假'
                ],
                'periodTexts' => [
                    'night' => [ '夜深了，早点休息哦 🌙', '夜色温柔，早点休息鸭', '月亮挂得好高啦，睡个好觉吧', '凌晨好呀，愿你有个好梦', '深夜安静得刚刚好', '熬夜冠军，是你吗 🏆' ],
                    'morning' => [ '早上好呀，今天也是新的一天 ☀️', '早安～今天也要加油鸭', '起床啦？愿你今天心情愉快', '早上好，记得吃早餐哦 🍳', '清晨的空气很舒服呢', '早安，今天也是清爽的一天' ],
                    'noon' => [ '中午好，记得吃午饭哦 🍚', '午安～吃饱了才有力气呀', '到饭点啦，去吃点好吃的吧', '中午歇一会儿也不错', '干饭时间到！🍚' ],
                    'afternoon' => [ '下午好呀，喝杯水休息一下～', '下午茶时间到了 ☕', '下午时光，慢慢来也很好', '下午的节奏，刚刚好', '要不要来杯下午茶？🍵' ],
                    'evening' => [ '晚上好呀，辛苦一天啦～', '天色暗下来啦，灯光调柔和些吧', '晚饭吃了吗？', '今晚也要好好休息哦', '夜色正好，放放松松', '下班了吗？辛苦啦' ]
                ],
                'api'      => '',
                'apiField' => 'text'
            ];
        }
    }

    /* ==========================================================================
       出厂默认数据（首次安装 / 恢复出厂）
       ========================================================================== */
    if (!function_exists('factory_defaults')) {
        function factory_defaults() {
            return [
                'categories' => [
                    ['name' => '导航', 'icon' => 'photos/zy.png'],
                    ['name' => '联系', 'icon' => 'photos/lx.png']
                ],
                'cards'      => [],
                'jsEffects'  => [],
                'boardGirl'  => ['enabled' => true, 'girls' => []],
                'photoOrder' => [],
                'preloaders' => [
                    ['key' => 'elegant', 'name' => '优雅', 'description' => '紫罗兰柔光 + 涟漪 + 光痕进度条', 'file' => 'assets/preloaders/elegant.css', 'enabled' => true],
                    ['key' => 'apple',   'name' => '苹果',   'description' => '纯色背景 + 干净进度条 + 呼吸光晕',   'file' => 'assets/preloaders/apple.css',   'enabled' => true],
                ],
                'site'       => site_defaults(),
                'tips'       => tips_defaults(),
                'localBg'     => ['enabled' => true, 'images' => []],
                'localBanner' => ['enabled' => true, 'images' => []],
                'uaTemplates' => [],
                'announcements' => [],
            ];
        }
    }

    /* ==========================================================================
       内置 UA 伪装模板
       ========================================================================== */
    if (!function_exists('ua_builtin_templates')) {
        function ua_builtin_templates() {
            return [
                [
                    'id'             => 'ua_mobile_default',
                    'name'           => 'iOS 18 Safari（移动端）',
                    'ua'             => 'Mozilla/5.0 (iPhone17,1; CPU iPhone OS 18_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.0 Mobile Safari/605.1.15',
                    'accept'         => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,image/png,image/jpeg,*/*;q=0.8',
                    'acceptLanguage' => 'zh-CN,zh;q=0.9',
                    'acceptEncoding' => 'gzip, deflate, br',
                    'extraHeaders'   => 'Sec-Fetch-Mode: navigate',
                    'builtin'        => true,
                ],
                [
                    'id'             => 'ua_desktop_default',
                    'name'           => 'Windows 11 Chrome（桌面端）',
                    'ua'             => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36',
                    'accept'         => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8,application/signed-exchange;v=b3;q=0.7',
                    'acceptLanguage' => 'zh-CN,zh;q=0.9,en;q=0.8',
                    'acceptEncoding' => 'gzip, deflate, br',
                    'extraHeaders'   => 'Sec-Fetch-Mode: navigate',
                    'builtin'        => true,
                ],
            ];
        }
    }
}