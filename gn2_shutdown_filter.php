<?php
/*
Plugin Name:  gn2 Shutdown Filter
Plugin URI:   https://github.com/gn2netwerk/gn2_shutdown_filter
Description:  Provides the custom filter "gn2_shutdown"
Version:      1.0.2
Author:       gn2
Author URI:   https://www.gn2.de/
Update URI:   https://raw.githubusercontent.com/gn2netwerk/gn2_shutdown_filter/master/info.json
*/

defined('ABSPATH') || exit;

require 'vendor/autoload.php';

// enable updates
// hook name: update_plugins_ + host of Update URI
add_filter('update_plugins_raw.githubusercontent.com', function ($update, $plugin_data) {
    $res = wp_remote_get($plugin_data['UpdateURI'], ['sslverify' => false]);
    try {
        $json = json_decode($res['body']);
    } catch (Exception $ex) {
        return false;
    }

    return $json;
}, 10, 3);

// Level vor ob_start() merken, damit der Shutdown-Handler genau weiß, welcher Buffer gn2 gehört.
// Nötig seit WP 6.9.0: wp_start_template_enhancement_output_buffer() öffnet einen eigenen Buffer
// mit Callback (wp_finalize_template_enhancement_output_buffer), der Styles in <head> hoistet.
// Der frühere ob_get_clean()-Loop hat diesen Callback umgangen → leaflet-css und Block-Styles fehlten.
$gn2_ob_level = ob_get_level();
ob_start();
add_action('shutdown', function () use ($gn2_ob_level) {
    // Alle Buffer OBERHALB von gn2 mit ob_end_flush() schließen (nicht ob_get_clean!).
    // ob_end_flush() führt den Buffer-Callback aus → WP Enhancement Buffer hoistet Styles korrekt,
    // der Inhalt fließt dann in gn2s eigenen Buffer.
    while (ob_get_level() > $gn2_ob_level + 1) {
        ob_end_flush();
    }
    // Jetzt gn2s eigenen Buffer abgreifen – enthält fertiges HTML mit geholisteten Styles.
    $html = ob_get_clean() ?? '';
    echo apply_filters('gn2_shutdown', $html);
}, 0);
