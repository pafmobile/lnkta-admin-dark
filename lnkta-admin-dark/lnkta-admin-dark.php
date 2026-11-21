<?php
/**
 * Plugin Name: LNKTA Admin Dark — Lens Archive
 * Description: Flat dark WordPress admin UI with grouped Custom Posts and Plugins Options menus.
 * Version: 2.0.8
 * Author: LNKTA
 * License: GPL-2.0-or-later
 * Update URI: https://github.com/pafmobile/lnkta-admin-dark
 * Text Domain: lnkta-admin-dark
 */
if (!defined('ABSPATH')) { exit; }
require_once __DIR__ . '/includes/github-updater.php';

add_action('admin_enqueue_scripts', function () {
    wp_enqueue_style('lnkta-admin-dark', plugins_url('assets/admin.css', __FILE__), array(), '2.0.8');
}, 999);

add_filter('tiny_mce_before_init', function ($settings) {
    if (!is_admin()) { return $settings; }
    $css = 'body#tinymce{background:#1b1b1c;color:#ebe3d4;font-family:Arial,sans-serif}body#tinymce a{color:#e98a55}';
    $settings['content_style'] = (isset($settings['content_style']) ? $settings['content_style'] . ' ' : '') . $css;
    return $settings;
});

/** Menu presentation only: do not unregister or change the plugin's original page hooks. */
add_action('admin_menu', function () {
    if (!current_user_can('read')) { return; }
    global $menu, $submenu;
    if (!is_array($menu) || !is_array($submenu)) { return; }

    // Section groups appear near the end of the sidebar, above Settings.
    add_menu_page('Custom Posts', 'Custom Posts', 'read', 'lnkta-custom-posts', 'lnkta_admin_group_landing', 'dashicons-screenoptions', 73.7);
    add_menu_page('Plugins Options', 'Plugins Options', 'read', 'lnkta-plugin-options', 'lnkta_admin_group_landing', 'dashicons-admin-plugins', 73.8);
    $submenu['lnkta-custom-posts']  = array();
    $submenu['lnkta-plugin-options'] = array();

    $patterns = array(
        'Async ACF Forms' => array('async acf', 'async-acf', 'acf form', 'acf-form'),
        'Dynamic Content for Elementor' => array('dynamic content for elementor', 'dynamic-content-for-elementor', 'dynamic.ooo', 'dynamic content'),
        'WPVibe' => array('wpvibe', 'wp vibe'),
        'Dynamic Shortcodes' => array('dynamic shortcodes', 'dynamic-shortcodes'),
        'Astra' => array('astra', 'astra options'),
        'WP Mail SMTP' => array('wp mail smtp', 'wp-mail-smtp'),
        'Login Logo' => array('login logo', 'login-logo'),
        'Fluent Snippets' => array('fluent snippets', 'fluent-snippets'),
        'Capabilities' => array('capabilities', 'publishpress capabilities', 'capability manager'),
        'Future' => array('future actions', 'future-actions', 'future action', 'future'),
    );
    $matched = array();
    $links = array();
    $unique = array();
    $normalise = static function ($text) {
        $text = wp_strip_all_tags((string) $text);
        return strtolower(trim(html_entity_decode($text, ENT_QUOTES, 'UTF-8')));
    };
    $match_name = static function ($name, $slug) use ($patterns, $normalise) {
        $hay = $normalise($name) . ' ' . strtolower((string) $slug);
        foreach ($patterns as $label => $needles) {
            foreach ($needles as $needle) {
                if ($needle === 'future') {
                    if ($normalise($name) === 'future' || preg_match('~^(?:future|future-actions)(?:/|\.php|$)~', (string) $slug)) { return $label; }
                    continue;
                }
                if (strpos($hay, $needle) !== false) { return $label; }
            }
        }
        return null;
    };
    $add_link = static function ($group, $title, $cap, $slug) use (&$submenu, &$unique) {
        $key = $group . '|' . $slug;
        if (isset($unique[$key])) { return; }
        $unique[$key] = true;
        $submenu[$group][] = array($title, $cap, $slug);
    };

    // Gather visible native CPT menu entries. Their original URLs stay unchanged.
    $types = get_post_types(array('show_ui' => true, '_builtin' => false), 'objects');
    $cpt_slugs = array();
    foreach ($types as $type) {
        if (!empty($type->show_in_menu) && $type->show_in_menu === true) {
            $cpt_slugs['edit.php?post_type=' . $type->name] = $type;
        }
    }

    // Target only top-level plugin menus the user named, plus native CPT entries.
    foreach ($menu as $index => $entry) {
        if (!is_array($entry) || !isset($entry[2])) { continue; }
        $slug = (string) $entry[2];
        if ($slug === 'lnkta-custom-posts' || $slug === 'lnkta-plugin-options') { continue; }
        $cap = isset($entry[1]) ? $entry[1] : 'manage_options';
        if (!current_user_can($cap)) { continue; }
        if (isset($cpt_slugs[$slug])) {
            $obj = $cpt_slugs[$slug];
            $add_link('lnkta-custom-posts', $obj->labels->menu_name, $cap, $slug);
            // CPT taxonomy menus were children of the original top-level entry.
            // Preserve their destinations in the new group before hiding that entry.
            // Otherwise Categories / Tags disappear from navigation.
            if (!empty($submenu[$slug]) && is_array($submenu[$slug])) {
                foreach ($submenu[$slug] as $cpt_child) {
                    if (!isset($cpt_child[2])) { continue; }
                    $child_cap = isset($cpt_child[1]) ? $cpt_child[1] : $cap;
                    if (!current_user_can($child_cap)) { continue; }
                    $child_slug = (string) $cpt_child[2];
                    // Add only taxonomy screens, not duplicate list/new-post actions.
                    if (strpos($child_slug, 'edit-tags.php?') !== 0) { continue; }
                    $child_label = wp_strip_all_tags((string) $cpt_child[0]);
                    $add_link('lnkta-custom-posts', $obj->labels->menu_name . ' — ' . $child_label, $child_cap, $child_slug);
                }
            }
            // Also discover registered taxonomies when another plugin has not
            // populated the CPT submenu at the time this callback runs.
            foreach (get_object_taxonomies($obj->name, 'objects') as $tax) {
                if (empty($tax->show_ui) || empty($tax->show_in_menu)) { continue; }
                $tax_cap = !empty($tax->cap->manage_terms) ? $tax->cap->manage_terms : $cap;
                if (!current_user_can($tax_cap)) { continue; }
                $tax_slug = 'edit-tags.php?taxonomy=' . rawurlencode($tax->name) . '&post_type=' . rawurlencode($obj->name);
                $tax_label = !empty($tax->labels->menu_name) ? $tax->labels->menu_name : $tax->label;
                if (empty($submenu[$slug]) || !preg_grep('/^edit-tags\.php\?taxonomy=' . preg_quote($tax->name, '/') . '(?:&|$)/', array_column($submenu[$slug], 2))) {
                    $add_link('lnkta-custom-posts', $obj->labels->menu_name . ' — ' . $tax_label, $tax_cap, $tax_slug);
                }
            }
            unset($menu[$index]);
            continue;
        }
        $label = $match_name($entry[0], $slug);
        if ($label) {
            $add_link('lnkta-plugin-options', $label, $cap, $slug);
            $matched[$label] = true;
            // The registered page remains intact; only its sidebar entry is relocated.
            unset($menu[$index]);
        }
    }

    // Some plugins place settings under Settings, Tools, Appearance or another menu.
    // Add links to these pages without moving the originals or their parent registrations.
    foreach ($patterns as $label => $_) {
        if (isset($matched[$label])) { continue; }
        foreach ($submenu as $parent => $items) {
            if ($parent === 'lnkta-plugin-options' || $parent === 'lnkta-custom-posts') { continue; }
            if (!is_array($items)) { continue; }
            foreach ($items as $item) {
                if (!isset($item[2])) { continue; }
                $cap = isset($item[1]) ? $item[1] : 'manage_options';
                if (!current_user_can($cap)) { continue; }
                if ($match_name($item[0], $item[2]) !== $label) { continue; }
                $slug = (string) $item[2];
                // Keep the original parent route for submenu settings pages.
                if (strpos($slug, '.php') === false && strpos($slug, '?') === false && strpos($slug, 'http') !== 0) {
                    $slug = $parent . (strpos($parent, '?') === false ? '?page=' : '&page=') . $slug;
                }
                $add_link('lnkta-plugin-options', $label, $cap, $slug);
                $matched[$label] = true;
                break 2;
            }
        }
    }

    // No placeholders: installed menu items only.
    if (empty($submenu['lnkta-custom-posts'])) {
        $submenu['lnkta-custom-posts'][] = array('No custom post types', 'read', 'lnkta-custom-posts');
    }
    if (empty($submenu['lnkta-plugin-options'])) {
        $submenu['lnkta-plugin-options'][] = array('No matching plugin menus', 'read', 'lnkta-plugin-options');
    }
}, 99999);

function lnkta_admin_group_landing() {
    if (!current_user_can('read')) { wp_die(esc_html__('Sorry, you are not allowed to access this page.')); }
    echo '<div class="wrap"><h1>Lens Archive — Admin</h1><p>Select an item from the sidebar section.</p></div>';
}

/**
 * Presentation-only hierarchy for the grouped Custom Posts submenu.
 * Parent CPT links remain the real WordPress URLs; taxonomy links are visual children.
 */
add_action('admin_footer', function () {
    if (!current_user_can('read')) { return; }
    ?>
    <script>
    (function () {
        var root = document.querySelector('#toplevel_page_lnkta-custom-posts .wp-submenu');
        if (!root) return;

        var links = Array.prototype.slice.call(root.querySelectorAll('a'));
        var currentUrl = new URL(window.location.href);
        var currentPostType = currentUrl.searchParams.get('post_type') || '';
        var lastParent = null;

        links.forEach(function (link) {
            var li = link.closest('li');
            if (!li) return;

            var url;
            try { url = new URL(link.href, window.location.origin); }
            catch (e) { return; }

            var isParent = url.pathname.endsWith('/edit.php') && url.searchParams.get('post_type');
            var isTaxonomy = url.pathname.endsWith('/edit-tags.php') &&
                url.searchParams.get('taxonomy') &&
                url.searchParams.get('post_type');

            if (isParent) {
                lastParent = li;
                li.classList.add('lnkta-cpt-parent-item');
                link.classList.add('lnkta-cpt-parent-link');
                li.dataset.lnktaPostType = url.searchParams.get('post_type') || '';
            } else if (isTaxonomy) {
                li.classList.add('lnkta-cpt-child-item');
                link.classList.add('lnkta-cpt-child-link');
                li.dataset.lnktaPostType = url.searchParams.get('post_type') || '';
                if (lastParent && lastParent.dataset.lnktaPostType === li.dataset.lnktaPostType) {
                    li.classList.add('lnkta-cpt-child-of-current-group');
                }
            }
        });

        if (currentPostType) {
            var activeParent = root.querySelector(
                '.lnkta-cpt-parent-item[data-lnkta-post-type="' +
                CSS.escape(currentPostType) + '"]'
            );
            if (activeParent) {
                activeParent.classList.add('lnkta-cpt-parent-active');
            }
        }
    }());
    </script>
    <?php
}, 999);
