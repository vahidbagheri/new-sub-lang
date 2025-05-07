<?php
/*
Plugin Name: Subdomain Language Switcher
Description: مدیریت زبان‌ها و ریدایرکت بین ساب‌دامین‌ها بر اساس انتخاب زبان کاربر
Version: 1.8
Author: Vahid Bagheri
*/

if (!defined('ABSPATH')) exit;

class Subdomain_Language_Switcher {
    private $option_name = 'sls_languages';

    public function __construct() {
        add_action('admin_menu', [$this, 'admin_menu']);
        add_action('admin_init', [$this, 'register_settings']);
        add_shortcode('language_switcher', [$this, 'render_switcher']);
        add_action('admin_enqueue_scripts', [$this, 'admin_styles']);
        add_action('template_redirect', [$this, 'handle_redirect']);
        add_action('wp_enqueue_scripts', [$this, 'my_plugin_enqueue_language_switcher_style']);
    }

    public function my_plugin_enqueue_language_switcher_style() {
        $file = plugin_dir_path(__FILE__) . 'style.css';
        $ver  = file_exists($file) ? filemtime($file) : '1.0';
    
        wp_enqueue_style(
            'my-plugin-language-switcher',
            plugin_dir_url(__FILE__) . 'style.css',
            array(),
            $ver
        );
    }
   
    

    public function admin_styles($hook) {
        if ($hook !== 'toplevel_page_sls-language-switcher') return;
        wp_add_inline_style('wp-admin', '
            #language-table { width: 100%; border-collapse: collapse; margin-top: 20px; }
            #language-table th, #language-table td { padding: 10px; border: 1px solid #ccd0d4; text-align: center; }
            #language-table input[type="text"], #language-table input[type="url"] { width: 100%; padding: 6px 8px; border: 1px solid #ccd0d4; border-radius: 6px; box-sizing: border-box; }
            #language-table th { background-color: #f1f1f1; font-weight: bold; }
            .wrap h1 { margin-bottom: 20px; font-size: 24px; }
            .button.add-row { background-color: #46b450; color: white; border: none; }
            .button.add-row:hover { background-color: #3da141; }
            .button.remove-row { background-color: #dc3232; color: white; border: none; }
            .button.remove-row:hover { background-color: #b52727; }
            .form-table td button { border-radius: 6px; }
            .form-table td input { text-align: center; }
            .form-table td img { max-height: 20px; }
        ');
    }

    public function admin_menu() {
        add_menu_page('مدیریت زبان‌ها', 'زبان‌ها', 'manage_options', 'sls-language-switcher', [$this, 'settings_page']);
    }

    public function register_settings() {
        register_setting('sls_settings_group', $this->option_name, [$this, 'sanitize_languages']);
    }

    public function sanitize_languages($input) {
        $cleaned = [];
        foreach ($input as $lang) {
            if (!empty($lang['name']) || !empty($lang['url']) || !empty($lang['flag'])) {
                $cleaned[] = [
                    'name' => sanitize_text_field($lang['name']),
                    'url'  => esc_url_raw($lang['url']),
                   'flag' => sanitize_text_field($lang['flag'] ?? ''),
                ];
            }
        }
        return $cleaned;
    }

    public function settings_page() {
        $languages = get_option($this->option_name, []);
        ?>
        <div class="wrap">
            <h1>مدیریت زبان‌ها</h1>
            <form method="post" action="options.php">
                <?php settings_fields('sls_settings_group'); ?>
                <table class="form-table" id="language-table">
                    <thead>
                        <tr><th>نام زبان</th><th>لینک</th><th>آدرس پرچم</th><th>عملیات</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($languages as $index => $lang): ?>
                            <tr>
                                <td><input name="<?= $this->option_name ?>[<?= $index ?>][name]" value="<?= esc_attr($lang['name']) ?>" /></td>
                                <td><input name="<?= $this->option_name ?>[<?= $index ?>][url]" value="<?= esc_attr($lang['url']) ?>" /></td>
                                <td>
                                    <select name="<?= $this->option_name ?>[<?= $index ?>][flag]" class="flag-select">
                                        <option value="">انتخاب پرچم</option>
                                        <?php
                                        $flag_dir = plugin_dir_path(__FILE__) . 'flags/';
                                        $flag_url = plugin_dir_url(__FILE__) . 'flags/';
                                        foreach (glob($flag_dir . '*.png') as $flag_file) {
                                            $code = basename($flag_file, '.png');
                                            $selected = ($lang['flag'] ?? '') === $code ? 'selected' : '';
                                            echo "<option value='$code' $selected data-custom-properties='{\"img\":\"{$flag_url}{$code}.png\"}'>".strtoupper($code)."</option>";

                                        }
                                        ?>
                                    </select>
                                </td>

                                <td><button class="remove-row button">حذف</button></td>
                            </tr>
                        <?php endforeach; ?>
                        <tr class="empty-row screen-reader-text">
                            <td><input name="<?= $this->option_name ?>[][name]" /></td>
                            <td><input name="<?= $this->option_name ?>[][url]" /></td>
                            <td>
                                <select name="<?= $this->option_name ?>[][flag]" class="flag-select">
                                    <option value="">انتخاب پرچم</option>
                                    <?php
                                    $flag_dir = plugin_dir_path(__FILE__) . 'flags/';
                                    $flag_url = plugin_dir_url(__FILE__) . 'flags/';
                                    foreach (glob($flag_dir . '*.png') as $flag_file) {
                                        $code = basename($flag_file, '.png');
                                        echo "<option value='$code' data-img='{$flag_url}{$code}.png'>".strtoupper($code)."</option>";
                                    }
                                    ?>
                                </select>
                            </td>
                            <td><button class="remove-row button">حذف</button></td>
                        </tr>

                    </tbody>
                </table>
                <p><button type="button" class="button add-row">افزودن زبان</button></p>
                <?php submit_button(); ?>
            </form>
        </div>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                document.querySelector('.add-row').addEventListener('click', function () {
                    var table = document.getElementById('language-table').querySelector('tbody');
                    var newRow = document.querySelector('.empty-row').cloneNode(true);
                    newRow.classList.remove('empty-row', 'screen-reader-text');
                    table.appendChild(newRow);
                });
                document.addEventListener('click', function (e) {
                    if (e.target.classList.contains('remove-row')) {
                        e.preventDefault();
                        var row = e.target.closest('tr');
                        if (row && !row.classList.contains('empty-row')) row.remove();
                    }
                });
            });
        </script>
        <?php
    }

    public function render_switcher() {
        $languages = get_option($this->option_name, []);
        if (empty($languages)) return '';
    
        $current_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://" . $_SERVER['HTTP_HOST'];
        $flag_base_url = plugin_dir_url(__FILE__) . 'flags/';
    
        ob_start();
        ?>
        <div class="lang<?= is_rtl() ? '-fa' : '-en'; ?>">
            <span class="lang-title <?= is_rtl() ? 'ml-5' : ' '; ?>">
                <span class="icon-chevron-down lang-icon "></span>
                <span class="title">Language</span>
                <span class="icon-global"></span>
            </span>
            <div class="lang-box d-none d-none">
                <div class="wpml-ls-statics-shortcode_actions wpml-ls wpml-ls-rtl wpml-ls-legacy-list-horizontal">
                <?php
$current_locale = substr(get_locale(), 0, 2); 
 // دریافت زبان فعلی سایت
?>

<ul>
    <?php foreach ($languages as $index => $lang): ?>
        <?php
            // بررسی اینکه آیا زبان فعلی با زبان موجود در لیست برابر است
            if ($current_locale === $lang['flag']) {
                continue; // اگر برابر بود، این زبان را نمایش نده
            }

            $li_class = "wpml-ls-slot-shortcode_actions wpml-ls-item wpml-ls-item-" . strtolower($lang['flag']) . " wpml-ls-item-legacy-list-horizontal";
            if ($index === 0) $li_class .= " wpml-ls-first-item";
            if ($index === count($languages) - 1) $li_class .= " wpml-ls-last-item";

            $flag_url = $flag_base_url . $lang['flag'] . '.png';
        ?>
        <li class="<?= esc_attr($li_class) ?>">
            <a href="#" class="wpml-ls-link" data-lang-url="<?= esc_url($lang['url']) ?>">
                <?php if (!empty($lang['flag'])): ?>
                    <img src="<?= esc_url($flag_url) ?>" alt="<?= esc_attr($lang['name']) ?>" style="width: 18px; height: auto; margin-left: 6px;">
                <?php endif; ?>
                <span class="wpml-ls-native" lang="<?= esc_attr($lang['flag']) ?>">
                    <?= esc_html($lang['name']) ?>
                </span>
               
            </a>
        </li>
    <?php endforeach; ?>
</ul>
                </div>
            </div>
        </div>
    
        <script>
            document.addEventListener("DOMContentLoaded", function () {
                const langTitle = document.querySelector(".lang-title");
                const langBox = document.querySelector(".lang-box");
    
                langTitle.addEventListener("click", function () {
                    langBox.classList.toggle("d-none");
                });
    
                const links = document.querySelectorAll(".wpml-ls-link");
                links.forEach(link => {
                    link.addEventListener("click", function (e) {
                        e.preventDefault();
                        const targetUrl = this.dataset.langUrl;
                        const currentUrl = window.location.protocol + "//" + window.location.host;
                        if (targetUrl && targetUrl !== currentUrl) {
                            const hostnameParts = window.location.hostname.split('.');
                            let rootDomain = '.' + hostnameParts.slice(-2).join('.');
                            if (hostnameParts.slice(-2).join('.') === 'co.uk' && hostnameParts.length >= 3) {
                                rootDomain = '.' + hostnameParts.slice(-3).join('.');
                            }
                            document.cookie = "preferred_lang=; path=/; domain=" + rootDomain + "; max-age=0";
                            document.cookie = "preferred_lang=" + encodeURIComponent(targetUrl) + "; path=/; domain=" + rootDomain + "; max-age=" + (60*60*24*30);
                            window.location.href = targetUrl;
                        }
                    });
                });
            });

            document.addEventListener("DOMContentLoaded", function () {
    const links = document.querySelectorAll(".wpml-ls-link");
    links.forEach(link => {
        link.addEventListener("click", function (e) {
            e.preventDefault();
            const targetUrl = this.dataset.langUrl;
            const hostnameParts = window.location.hostname.split('.');
            let rootDomain = '.' + hostnameParts.slice(-2).join('.');
            if (hostnameParts.slice(-2).join('.') === 'co.uk' && hostnameParts.length >= 3) {
                rootDomain = '.' + hostnameParts.slice(-3).join('.');
            }
            document.cookie = "preferred_lang=" + encodeURIComponent(targetUrl) + "; path=/; domain=" + rootDomain + "; max-age=" + (60 * 60 * 24 * 30);
            window.location.href = targetUrl;
        });
    });
});
        </script>
        <?php
        return ob_get_clean();
    }
    
    
    // public function handle_redirect() {
    //     if (is_admin() || wp_doing_ajax()) {
    //         return; // جلوگیری از اجرای ریدایرکت در بخش مدیریت یا درخواست‌های AJAX
    //     }
    
    //     // فقط وقتی کاربر در صفحه اصلی (homepage) است
    //     if ($_SERVER['REQUEST_URI'] === '/' || $_SERVER['REQUEST_URI'] === '') {
    //         if (isset($_COOKIE['preferred_lang'])) {
    //             $preferred_lang = urldecode($_COOKIE['preferred_lang']); // دیکد کردن مقدار کوکی
    //             $preferred_lang = rtrim($preferred_lang, '/');
    
    //             // اگر آدرس فعلی با آدرس زبان متفاوت است، ریدایرکت کن
    //             $current_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http")
    //                          . "://" . $_SERVER['HTTP_HOST'];
    
    //             if ($current_url !== $preferred_lang) {
    //                 wp_redirect($preferred_lang, 301);
    //                 exit;
    //             }
    //         }
    //     }
    // }


    public function handle_redirect() {
        if (is_admin() || wp_doing_ajax()) {
            return; // جلوگیری از اجرای ریدایرکت در بخش مدیریت یا درخواست‌های AJAX
        }
    
        if (isset($_COOKIE['preferred_lang'])) {
            $preferred_lang = urldecode($_COOKIE['preferred_lang']); // دیکد کردن مقدار کوکی
            $preferred_lang = rtrim($preferred_lang, '/'); // حذف اسلش اضافی آخر
    
            // آدرس فعلی رو با پروتکل و هاست و URI کامل می‌سازیم
            $current_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http")
                         . "://" . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
    
            // بررسی می‌کنیم آیا current_url با preferred_lang شروع نمی‌شه
            if (strpos($current_url, $preferred_lang) !== 0) {
                // مسیر فعلی رو جدا می‌کنیم
                $request_uri = ltrim($_SERVER['REQUEST_URI'], '/');
    
                // ساختن آدرس مقصد بر اساس زبان ترجیحی و URI
                $redirect_url = $preferred_lang . '/' . $request_uri;
                $redirect_url = rtrim($redirect_url, '/'); // حذف اسلش آخر در صورت نیاز
    
                wp_redirect($redirect_url, 301);
                exit;
            }
        }
    }
    
    
   


}

new Subdomain_Language_Switcher();









