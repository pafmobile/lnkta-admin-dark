<?php
/**
 * LNKTA Admin Dark - WordPress/GitHub Releases updater.
 * Respects the native WordPress automatic-update setting.
 */
if (!defined('ABSPATH')) { exit; }

final class LNKTA_Admin_Dark_GitHub_Updater {
    const VERSION = '2.0.6';
    const REPO = 'pafmobile/lnkta-admin-dark';
    const SLUG = 'lnkta-admin-dark';
    const FILE = 'lnkta-admin-dark/lnkta-admin-dark.php';
    const CACHE = 'lnkta_admin_dark_release_v2';
    const ERROR_CACHE = 'lnkta_admin_dark_release_error_v2';

    public static function init() {
        add_filter('update_plugins_github.com', array(__CLASS__, 'uri_update'), 10, 4);
        add_filter('pre_set_site_transient_update_plugins', array(__CLASS__, 'inject_update'), 20);
        add_filter('site_transient_update_plugins', array(__CLASS__, 'inject_update'), 20);
        add_filter('plugins_api', array(__CLASS__, 'plugin_info'), 20, 3);
        add_filter('plugin_row_meta', array(__CLASS__, 'row_meta'), 10, 2);
        add_action('admin_menu', array(__CLASS__, 'admin_menu'), 999);
        add_action('admin_post_lnkta_admin_dark_refresh_updates', array(__CLASS__, 'refresh'));
        add_action('upgrader_process_complete', array(__CLASS__, 'flush_after_upgrade'), 10, 2);
        // Do NOT hook auto_update_plugin: the owner's preference controls it.
    }

    private static function installed_version() {
        if (!function_exists('get_plugins')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        $plugins = get_plugins();
        return isset($plugins[self::FILE]['Version']) ? (string) $plugins[self::FILE]['Version'] : '0.0.0';
    }

    private static function release() {
        $cached = get_site_transient(self::CACHE);
        if (is_array($cached) && !empty($cached['version'])) { return $cached; }
        $response = wp_remote_get('https://api.github.com/repos/' . self::REPO . '/releases/latest', array(
            'timeout' => 12,
            'headers' => array(
                'Accept' => 'application/vnd.github+json',
                'User-Agent' => 'LNKTA-Admin-Dark-WordPress',
            ),
        ));
        $error = '';
        if (is_wp_error($response)) {
            $error = $response->get_error_message();
        } elseif (200 !== (int) wp_remote_retrieve_response_code($response)) {
            $error = 'GitHub HTTP ' . (int) wp_remote_retrieve_response_code($response);
        } else {
            $json = json_decode(wp_remote_retrieve_body($response), true);
            if (!is_array($json) || empty($json['tag_name']) || !empty($json['draft']) || !empty($json['prerelease'])) {
                $error = 'Invalid or unavailable GitHub Release.';
            } else {
                $version = ltrim((string) $json['tag_name'], 'vV');
                $package = '';
                foreach ((array) ($json['assets'] ?? array()) as $asset) {
                    if (($asset['name'] ?? '') === 'lnkta-admin-dark.zip') {
                        $package = (string) ($asset['browser_download_url'] ?? '');
                        break;
                    }
                }
                $prefix = 'https://github.com/' . self::REPO . '/releases/download/';
                if (preg_match('/^\d+\.\d+\.\d+$/', $version) && 0 === strpos($package, $prefix)) {
                    $data = array(
                        'version' => $version,
                        'package' => esc_url_raw($package),
                        'url' => 'https://github.com/' . self::REPO,
                        'body' => (string) ($json['body'] ?? ''),
                    );
                    set_site_transient(self::CACHE, $data, 15 * MINUTE_IN_SECONDS);
                    delete_site_transient(self::ERROR_CACHE);
                    return $data;
                }
                $error = 'Release version or ZIP asset is invalid.';
            }
        }
        set_site_transient(self::ERROR_CACHE, $error, 5 * MINUTE_IN_SECONDS);
        return false;
    }

    private static function payload($release) {
        return (object) array(
            'id' => 'https://github.com/' . self::REPO,
            'slug' => self::SLUG,
            'plugin' => self::FILE,
            'version' => $release['version'],
            'new_version' => $release['version'],
            'url' => $release['url'],
            'package' => $release['package'],
        );
    }

    public static function uri_update($update, $plugin_data, $plugin_file, $locales) {
        if (self::FILE !== $plugin_file) { return $update; }
        $release = self::release();
        if (!$release) { return $update; }
        return (array) self::payload($release);
    }

    public static function inject_update($transient) {
        if (!is_object($transient)) { return $transient; }
        $release = self::release();
        if (!$release) { return $transient; }
        $installed = isset($transient->checked[self::FILE])
            ? (string) $transient->checked[self::FILE] : self::installed_version();
        if (!isset($transient->response) || !is_array($transient->response)) { $transient->response = array(); }
        if (!isset($transient->no_update) || !is_array($transient->no_update)) { $transient->no_update = array(); }
        $payload = self::payload($release);
        if (version_compare($release['version'], $installed, '>')) {
            $transient->response[self::FILE] = $payload;
            unset($transient->no_update[self::FILE]);
        } else {
            unset($transient->response[self::FILE]);
            $transient->no_update[self::FILE] = $payload;
        }
        return $transient;
    }

    public static function plugin_info($result, $action, $args) {
        if ('plugin_information' !== $action || empty($args->slug) || self::SLUG !== $args->slug) { return $result; }
        $release = self::release();
        if (!$release) { return $result; }
        return (object) array(
            'name' => 'LNKTA Admin Dark — Lens Archive',
            'slug' => self::SLUG,
            'version' => $release['version'],
            'author' => 'LNKTA',
            'homepage' => $release['url'],
            'download_link' => $release['package'],
            'sections' => array(
                'description' => 'Lens Archive styling for the WordPress administration interface.',
                'changelog' => wp_kses_post($release['body']),
            ),
        );
    }

    public static function row_meta($links, $file) {
        if (self::FILE === $file && current_user_can('update_plugins')) {
            $links[] = '<a href="' . esc_url(admin_url('tools.php?page=lnkta-admin-dark-updates')) . '">GitHub update status</a>';
        }
        return $links;
    }

    public static function admin_menu() {
        add_management_page('LNKTA Admin Dark Updates', 'LNKTA Admin Dark Updates',
            'update_plugins', 'lnkta-admin-dark-updates', array(__CLASS__, 'status_page'));
    }

    public static function status_page() {
        if (!current_user_can('update_plugins')) { wp_die('Insufficient permissions.'); }
        $installed = self::installed_version();
        $release = self::release();
        $updates = get_site_transient('update_plugins');
        $registered = is_object($updates) && !empty($updates->response[self::FILE]);
        $auto = (array) get_site_option('auto_update_plugins', array());
        $enabled = in_array(self::FILE, $auto, true);
        $error = get_site_transient(self::ERROR_CACHE);
        echo '<div class="wrap"><h1>LNKTA Admin Dark — GitHub updates</h1><table class="widefat striped" style="max-width:850px"><tbody>';
        $rows = array(
            'Installed version' => $installed,
            'GitHub latest release' => $release ? $release['version'] : 'Not available',
            'WordPress update registered' => $registered ? 'Yes' : 'No',
            'Automatic updates preference' => $enabled ? 'Enabled' : 'Disabled',
            'GitHub connection' => $release ? 'OK' : ($error ?: 'Unknown error'),
        );
        foreach ($rows as $label => $value) {
            echo '<tr><th style="width:260px">' . esc_html($label) . '</th><td>' . esc_html($value) . '</td></tr>';
        }
        echo '</tbody></table><p>Automatic updates follow your preference in Plugins → Installed Plugins. An update link only appears when GitHub has a newer version than the one installed.</p>';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        wp_nonce_field('lnkta_admin_dark_refresh_updates');
        echo '<input type="hidden" name="action" value="lnkta_admin_dark_refresh_updates">';
        submit_button('Check GitHub and WordPress updates now');
        echo '</form></div>';
    }

    public static function refresh() {
        if (!current_user_can('update_plugins')) { wp_die('Insufficient permissions.'); }
        check_admin_referer('lnkta_admin_dark_refresh_updates');
        delete_site_transient(self::CACHE);
        delete_site_transient(self::ERROR_CACHE);
        delete_site_transient('update_plugins');
        wp_update_plugins();
        wp_safe_redirect(admin_url('tools.php?page=lnkta-admin-dark-updates'));
        exit;
    }

    public static function flush_after_upgrade($upgrader, $options) {
        if (($options['type'] ?? '') === 'plugin') {
            delete_site_transient(self::CACHE);
            delete_site_transient(self::ERROR_CACHE);
        }
    }
}
LNKTA_Admin_Dark_GitHub_Updater::init();
