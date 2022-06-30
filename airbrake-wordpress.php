<?php

/*
Plugin Name: airbrake-wordpress
Description: Airbrake Wordpress

Author: Airbrake.io
Author URI: https://github.com/airbrake/airbrake-wordpress

Description: Airbrake lets you discover errors and bugs in your Wordpress install.

Version: 0.2.1
License: GPL
*/

define('AW_TITLE', 'Airbrake Wordpress');
define('AW_SLUG', 'airbrake_wordpress');

if (!class_exists('Airbrake\Notifier')) {
    require_once __DIR__ . '/vendor/autoload.php';
}

//------------------------------------------------------------------------------


function airbrake_wordpress_add_settings_page() {
    add_options_page(AW_TITLE, 'Airbrake', 'administrator', 'airbrake-wordpress', 'airbrake_wordpress_render_settings_page');
}
add_action('admin_menu', 'airbrake_wordpress_add_settings_page');

function airbrake_wordpress_render_settings_page() {
    ?>
    <form action="options.php" method="post">
        <?php
        settings_fields( 'airbrake_wordpress_options' );
        do_settings_sections( 'airbrake_wordpress_settings_page' ); ?>
        <input name="submit" class="button button-primary" type="submit" value="<?php esc_attr_e( 'Save' ); ?>" />
    </form>
    <?php
}

function airbrake_wordpress_register_settings() {
    register_setting('airbrake_wordpress_options', 'airbrake_wordpress_options');

    add_settings_section(AW_SLUG, AW_TITLE, 'airbrake_wordpress_section_text', 'airbrake_wordpress_settings_page');

    add_settings_field('airbrake_wordpress_setting_disabled', 'Disabled', 'airbrake_wordpress_setting_disabled', 'airbrake_wordpress_settings_page', AW_SLUG);
    add_settings_field('airbrake_wordpress_setting_project_id', 'Project ID', 'airbrake_wordpress_setting_project_id', 'airbrake_wordpress_settings_page', AW_SLUG);
    add_settings_field('airbrake_wordpress_setting_project_key', 'Project Key', 'airbrake_wordpress_setting_project_key', 'airbrake_wordpress_settings_page', AW_SLUG);
    add_settings_field('airbrake_wordpress_setting_host', 'Host', 'airbrake_wordpress_setting_host', 'airbrake_wordpress_settings_page', AW_SLUG);
    add_settings_field('airbrake_wordpress_setting_suppress_warnings', 'Suppress Warnings and Notices', 'airbrake_wordpress_setting_suppress_warnings', 'airbrake_wordpress_settings_page', AW_SLUG);
}

add_action('admin_init', 'airbrake_wordpress_register_settings');


function airbrake_wordpress_section_text() {
    echo '<img style="float:left; padding:4px; padding-top:8px; padding-right:12px" src="<?php plugin_dir_url(__FILE__); ?>../plugin/images/icon.png"></img>';
    echo '<h2>Airbrake</h2>';
    echo '<p>Airbrake is a tool that collects and aggregates errors for webapps. This Plugin makes it simple to track PHP errors in your Wordpress install. Once installed it\'ll collect all errors with the Wordpress Core and Wordpress Plugins.</p>';
    echo '<p>This plugin requires an Airbrake account. Sign up for an <a href="https://airbrake.io/pricing">account</a>.</p>';
}

function airbrake_wordpress_setting_disabled() {
    $options = get_option( 'airbrake_wordpress_options' );
    $selected = $options['disabled'] ?? false;
    echo "<select name='airbrake_wordpress_options[disabled]' id='airbrake_wordpress_setting_disabled'>";
    echo "<option value='1'" . ($selected ? 'selected=\"selected\"' : '') . ">Yes</option>";
    echo "<option value='0'" . (!$selected ? 'selected=\"selected\"' : '') . ">No</option>";
    echo "</select>";
}

function airbrake_wordpress_setting_project_id() {
    $options = get_option( 'airbrake_wordpress_options' );
    echo "<input id='airbrake_wordpress_setting_project_id' name='airbrake_wordpress_options[project_id]' type='text' value='" . esc_attr( $options['project_id'] ?? 'FIXME' ) . "' />";
}

function airbrake_wordpress_setting_project_key() {
    $options = get_option( 'airbrake_wordpress_options' );
    echo "<input id='airbrake_wordpress_setting_project_key' name='airbrake_wordpress_options[project_key]' type='text' value='" . esc_attr( $options['project_key'] ?? 'FIXME' ) . "' />";
}

function airbrake_wordpress_setting_host() {
    $options = get_option( 'airbrake_wordpress_options' );
    echo "<input id=' airbrake_wordpress_setting_host' name='airbrake_wordpress_options[host]' type='text' value='" . esc_attr( $options['host'] ?? 'FIXME' ) . "' />";
}

function airbrake_wordpress_setting_suppress_warnings() {
    $options = get_option( 'airbrake_wordpress_options' );
    $selected = $options['suppress_warnings'] ?? false;
    echo "<select name='airbrake_wordpress_options[suppress_warnings]' id='airbrake_wordpress_setting_suppress_warnings'>";
    echo "<option value='1'" . ($selected ? 'selected=\"selected\"' : '') . ">Yes</option>";
    echo "<option value='0'" . (!$selected ? 'selected=\"selected\"' : '') . ">No</option>";
    echo "</select>";
}
//------------------------------------------------------------------------------


$options = get_option( 'airbrake_wordpress_options' );
if ($options &&
    $options['project_id'] &&
    $options['project_key'] &&
    $options['host'] &&
    !$options['disabled']) {
    $notifier = new Airbrake\Notifier([
        'host' => $options['host'],
        'projectId' => $options['project_id'],
        'projectKey' => $options['project_key'],
        'environment' => getenv('AIRBRAKE_ENVIRONMENT') ?: 'development',
    ]);
    $notifier->addFilter(function ($notice) {
      $ignore = array('E_WARNING', 'Airbrake\Errors\Warning', 'Airbrake\Errors\Notice');
      $options = get_option( 'airbrake_wordpress_options' );

      if ($options['suppress_warnings'] && in_array($notice['errors'][0]['type'], $ignore)) {
          return null;
        }

        $notice['params']['language'] = get_bloginfo('language');
        $notice['params']['wordpress'] = get_bloginfo('version');

        return $notice;
    });

    Airbrake\Instance::set($notifier);

    $handler = new Airbrake\ErrorHandler($notifier);
    $handler->register();
}
