<?php
namespace HoseinMomeni\MahexWoo\Update;

use WP_Error;

/** Compatibility gate and versioned settings snapshots for the GitHub update channel. */
final class UpdateSafety {
    public static function register(): void {
        add_filter('upgrader_pre_install', [self::class, 'beforeInstall'], 10, 2);
        add_filter('upgrader_pre_download', [self::class, 'download'], 10, 4);
        add_action('admin_menu', [self::class, 'menu']);
        add_action('admin_post_hm_mahex_check_update', [self::class, 'check']);
    }

    public static function compatibility(array $manifest, string $php, string $wp, string $wc): array {
        $errors = [];
        foreach (['requires_php' => [$php, 'PHP'], 'requires' => [$wp, 'وردپرس'], 'requires_wc' => [$wc, 'ووکامرس']] as $key => $environment) {
            $required = (string) ($manifest[$key] ?? '');
            if ($required !== '' && ($environment[0] === '' || version_compare($environment[0], $required, '<'))) {
                $errors[] = $environment[1] . ' باید نسخه ' . $required . ' یا بالاتر باشد.';
            }
        }
        return $errors;
    }

    private static function own(array $extra): bool {
        return isset($extra['plugin']) && plugin_basename(HM_MAHEX_FILE) === $extra['plugin'];
    }

    public static function beforeInstall($response, array $extra) {
        if (is_wp_error($response) || !self::own($extra)) return $response;
        $manifest = GitHubUpdater::manifest();
        if ($manifest) {
            global $wp_version;
            $errors = self::compatibility($manifest, PHP_VERSION, (string) $wp_version, defined('WC_VERSION') ? WC_VERSION : '');
            if ($errors) return new WP_Error('hm_mahex_incompatible_update', implode(' ', $errors));
        }
        $options = [];
        // Settings only; order data and the full database require the site's backup system.
        foreach (['hm_mahex_settings','hm_mahex_pricing_settings','hm_mahex_rate_rules','hm_mahex_packaging_profiles','hm_mahex_pro_settings','hm_mahex_v31_operations','hm_mahex_v31_tariffs','hm_mahex_v31_invoice_brand','hm_mahex_v1_settings','hm_mahex_v1_rules','hm_mahex_v2_settings','hm_mahex_v25_settings','hm_mahex_v3_settings','hm_mahex_v32_label_layout','hm_mahex_v33_print_settings','hm_mahex_v33_notification_settings','hm_mahex_v33_rule_versions','hm_mahex_v33_board_settings'] as $key) {
            $options[$key] = get_option($key, []);
        }
        $history = get_option('hm_mahex_update_backups', []);
        $history = is_array($history) ? $history : [];
        array_unshift($history, ['created_at' => gmdate(DATE_ATOM), 'version' => HM_MAHEX_VERSION, 'options' => $options]);
        update_option('hm_mahex_update_backups', array_slice($history, 0, 5), false);
        return $response;
    }

    public static function download($reply, string $package, $upgrader, array $extra = []) {
        if (false !== $reply || !self::own($extra)) return $reply;
        $manifest = GitHubUpdater::manifest();
        if (!$manifest || ($manifest['download_url'] ?? '') !== $package) return $reply;
        global $wp_version;
        $errors = self::compatibility($manifest, PHP_VERSION, (string) $wp_version, defined('WC_VERSION') ? WC_VERSION : '');
        if ($errors) return new WP_Error('hm_mahex_incompatible_update', implode(' ', $errors));
        $checksum = (string) ($manifest['sha256'] ?? '');
        if ($checksum === '') return $reply; // Legacy 3.0.1 manifests have no checksum.
        if (!preg_match('/^[a-f0-9]{64}$/i', $checksum)) return new WP_Error('hm_mahex_invalid_checksum', 'شناسه صحت بسته آپدیت معتبر نیست.');
        if (!function_exists('download_url')) require_once ABSPATH . 'wp-admin/includes/file.php';
        $file = download_url($package, 300);
        if (is_wp_error($file)) return $file;
        if (!hash_equals(strtolower($checksum), (string) hash_file('sha256', $file))) {
            wp_delete_file($file);
            return new WP_Error('hm_mahex_checksum_mismatch', 'صحت فایل آپدیت تأیید نشد؛ نصب متوقف شد. دوباره بررسی کنید.');
        }
        return $file;
    }

    public static function menu(): void {
        add_submenu_page('hm-mahex', 'آپدیت و بازیابی', 'آپدیت و بازیابی', 'manage_woocommerce', 'hm-mahex-update-safety', [self::class, 'page']);
    }

    public static function check(): void {
        if (!current_user_can('manage_woocommerce')) wp_die('دسترسی مجاز نیست.', '', ['response' => 403]);
        check_admin_referer('hm_mahex_check_update');
        delete_site_transient('hm_mahex_github_update_manifest_v1');
        delete_site_transient('update_plugins');
        wp_update_plugins();
        wp_safe_redirect(admin_url('admin.php?page=hm-mahex-update-safety'));
        exit;
    }

    public static function page(): void {
        if (!current_user_can('manage_woocommerce')) return;
        $manifest = GitHubUpdater::manifest();
        global $wp_version;
        echo '<div class="wrap" dir="rtl"><h1>آپدیت و بازیابی ماهکس</h1><p>نسخه نصب‌شده: <strong>' . esc_html(HM_MAHEX_VERSION) . '</strong></p>';
        if ($manifest) {
            echo '<p>نسخه منتشرشده: <strong>' . esc_html((string) $manifest['version']) . '</strong></p>';
            $errors = self::compatibility($manifest, PHP_VERSION, (string) $wp_version, defined('WC_VERSION') ? WC_VERSION : '');
            echo '<p>' . esc_html($errors ? implode(' ', $errors) : 'شرایط نسخه PHP، وردپرس و ووکامرس برای این آپدیت مناسب است.') . '</p>';
            echo '<h2>تغییرات نسخه</h2>' . wp_kses_post((string) ($manifest['sections']['changelog'] ?? ''));
        } else echo '<p>اطلاعات نسخه در دسترس نیست؛ اتصال به گیت‌هاب و تنظیمات آپدیت را بررسی کنید.</p>';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '"><input type="hidden" name="action" value="hm_mahex_check_update">';
        wp_nonce_field('hm_mahex_check_update');
        submit_button('بررسی دوباره آپدیت');
        echo '</form><p><a href="' . esc_url(admin_url('update-core.php')) . '">بازکردن بروزرسانی‌های وردپرس</a> | <a href="https://github.com/anonyset/mahex-shipping-for-woocommerce/releases">دانلود نسخه‌های قبلی</a></p>';
        echo '<h2>راهنمای بازیابی</h2><ol><li>پیش از آپدیت، از پایگاه داده و فایل‌های سایت با ابزار بکاپ میزبان نسخه پشتیبان بگیرید.</li><li>پیش از نصب آپدیت این افزونه، پنج نسخه اخیر تنظیمات به‌صورت داخلی حفظ می‌شوند؛ این ذخیره شامل سفارش‌ها و کل پایگاه داده نیست.</li><li>در صورت خطا، نسخه پشتیبان کامل سایت را بازیابی کنید. نصب ZIP قدیمی به‌تنهایی تغییرات پایگاه داده را برنمی‌گرداند.</li></ol>';
        $history = get_option('hm_mahex_update_backups', []);
        echo '<h3>تنظیمات محفوظ قبل از آپدیت</h3><ul>';
        foreach (is_array($history) ? $history : [] as $entry) echo '<li>' . esc_html((string) ($entry['created_at'] ?? '') . ' — ' . (string) ($entry['version'] ?? '')) . '</li>';
        echo '</ul></div>';
    }
}
