<?php
/**
 * GitHub Releases updater for LNKTA Admin Dark.
 * Public repo releases must contain a ZIP asset named lnkta-admin-dark.zip.
 */
if (!defined('ABSPATH')) { exit; }

final class LNKTA_Admin_Dark_GitHub_Updater {
    const REPO = 'pafmobile/lnkta-admin-dark';
    const SLUG = 'lnkta-admin-dark';
    const CACHE_KEY = 'lnkta_admin_dark_github_release_v1';
    const VERSION = '2.0.4';

    public static function init() {
        add_filter('pre_set_site_transient_update_plugins', array(__CLASS__, 'check_for_updates'));
        add_filter('plugins_api', array(__CLASS__, 'plugin_info'), 20, 3);
        // Respect the user's native Plugins > Enable/Disable auto-updates setting.
        // WordPress 5.8+ uses this dedicated filter for plugins with Update URI.
        add_filter('update_plugins_github.com', array(__CLASS__, 'update_via_uri'), 10, 4);
        add_filter('site_transient_update_plugins', array(__CLASS__, 'check_for_updates'));
        add_action('upgrader_process_complete', array(__CLASS__, 'flush_cache'), 10, 2);
    }

    private static function release() {
        $cached = get_site_transient(self::CACHE_KEY);
        if (false !== $cached) { return $cached === 'none' ? false : $cached; }
        $response = wp_remote_get('https://api.github.com/repos/' . self::REPO . '/releases/latest', array(
            'timeout' => 8,
            'headers' => array('Accept' => 'application/vnd.github+json', 'User-Agent' => 'LNKTA-Admin-Dark-WordPress'),
        ));
        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
            set_site_transient(self::CACHE_KEY, 'none', HOUR_IN_SECONDS);
            return false;
        }
        $data = json_decode(wp_remote_retrieve_body($response), true);
        if (!is_array($data) || !empty($data['draft']) || !empty($data['prerelease']) || empty($data['tag_name'])) {
            set_site_transient(self::CACHE_KEY, 'none', HOUR_IN_SECONDS);
            return false;
        }
        $version = ltrim((string) $data['tag_name'], 'vV');
        if (!preg_match('/^\d+\.\d+\.\d+$/', $version)) { return false; }
        $package = '';
        foreach ((array) ($data['assets'] ?? array()) as $asset) {
            if (($asset['name'] ?? '') === self::SLUG . '.zip' && !empty($asset['browser_download_url'])) {
                $package = esc_url_raw($asset['browser_download_url']);
                break;
            }
        }
        if (!$package || strpos($package, 'https://github.com/' . self::REPO . '/releases/download/') !== 0) {
            set_site_transient(self::CACHE_KEY, 'none', HOUR_IN_SECONDS);
            return false;
        }
        $release = array('version' => $version, 'package' => $package,
            'url' => 'https://github.com/' . self::REPO, 'description' => (string) ($data['body'] ?? ''));
        set_site_transient(self::CACHE_KEY, $release, 6 * HOUR_IN_SECONDS);
        return $release;
    }

    public static function check_for_updates($transient) {
        if (!is_object($transient)) { return $transient; }
        $file = plugin_basename(dirname(__DIR__) . '/lnkta-admin-dark.php');
        if (empty($transient->checked) || !is_array($transient->checked)) { return $transient; }
        $installed_version = isset($transient->checked[$file]) ? (string) $transient->checked[$file] : self::VERSION;
        $release = self::release();
        if (!$release) { return $transient; }
        if (version_compare($release['version'], $installed_version, '>')) {
            $transient->response[$file] = (object) array(
                'slug' => self::SLUG,
                'plugin' => $file,
                'new_version' => $release['version'],
                'url' => $release['url'],
                'package' => $release['package'],
            );
            unset($transient->no_update[$file]);
        } else {
            unset($transient->response[$file]);
        }
        return $transient;
    }

    public static function update_via_uri($update, $plugin_data, $plugin_file, $locales) {
        if ($plugin_file !== self::SLUG . '/lnkta-admin-dark.php') { return $update; }
        $release = self::release();
        if (!$release) { return $update; }
        // WordPress compares this version with the installed plugin itself.
        return array(
            'slug' => self::SLUG,
            'version' => $release['version'],
            'url' => $release['url'],
            'package' => $release['package'],
            'requires' => '5.8',
            'tested' => '6.9',
        );
    }

    public static function plugin_info($result, $action, $args) {
        if ($action !== 'plugin_information' || empty($args->slug) || $args->slug !== self::SLUG) { return $result; }
        $release = self::release();
        if (!$release) { return $result; }
        return (object) array(
            'name' => 'LNKTA Admin Dark — Lens Archive',
            'slug' => self::SLUG,
            'version' => $release['version'],
            'author' => 'LNKTA',
            'homepage' => $release['url'],
            'download_link' => $release['package'],
            'sections' => array('description' => 'Flat WordPress admin design with Lens Archive palette.',
                'changelog' => wp_kses_post($release['description'])),
        );
    }

    public static function flush_cache($upgrader, $options) {
        if (!empty($options['type']) && $options['type'] === 'plugin') {
            delete_site_transient(self::CACHE_KEY);
            delete_site_transient('update_plugins');
        }
    }
}
LNKTA_Admin_Dark_GitHub_Updater::init();
