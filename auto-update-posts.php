<?php
/**
 * Plugin Name: Auto Update Posts
 * Plugin URI: https://github.com/elizarov-n906/auto-update-posts
 * Description: Автоматически обновляет дату публикации и/или изменения записей, выбранных по ID. Поддерживает обновление в реальном времени и через серверный Cron.
 * Version: 1.1.3
 * Author: Elizarov_n906
 * Author URI: https://github.com/elizarov-n906
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: auto-update-posts
 */

// Запрет прямого вызова файла
if (!defined('ABSPATH')) {
    exit;
}

// Шаг 1: Создаем страницу настроек плагина
function auto_update_posts_settings_page() {
    add_options_page(
        esc_html__('Auto Update Posts', 'auto-update-posts'),
        esc_html__('Auto Update Posts', 'auto-update-posts'),
        'manage_options',
        'auto-update-posts',
        'auto_update_posts_settings_page_html'
    );
}
add_action('admin_menu', 'auto_update_posts_settings_page');

// HTML для страницы настроек
function auto_update_posts_settings_page_html() {
    if (!current_user_can('manage_options')) {
        return;
    }
    ?>
    <div class="wrap">
        <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
        <form method="post" action="options.php">
            <?php
            settings_fields('auto_update_posts_group');
            do_settings_sections('auto-update-posts');
            ?>
            <table class="form-table">
                <tr>
                    <th scope="row"><?php esc_html_e('Выбранные записи:', 'auto-update-posts'); ?></th>
                    <td>
                        <input type="text" name="auto_update_posts_ids" value="<?php echo esc_attr(get_option('auto_update_posts_ids', '')); ?>" placeholder="ID записей через запятую (например, 1,2,3)" class="regular-text" />
                        <p class="description"><?php esc_html_e('Укажите ID записей, даты которых нужно обновлять.', 'auto-update-posts'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e('Последние 10 записей:', 'auto-update-posts'); ?></th>
                    <td>
                        <div style="background-color: #f9f9f9; padding: 10px; border: 1px solid #ddd; border-radius: 4px; font-family: monospace; max-height: 200px; overflow-y: auto;">
                            <?php echo auto_update_posts_get_last_ten_posts(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                        </div>
                        <p class="description"><?php esc_html_e('Скопируйте нужные ID в поле выше.', 'auto-update-posts'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e('Тип обновления:', 'auto-update-posts'); ?></th>
                    <td>
                        <select name="auto_update_posts_type">
                            <option value="both" <?php selected(get_option('auto_update_posts_type', 'both'), 'both'); ?>><?php esc_html_e('Изменить даты публикации и изменения', 'auto-update-posts'); ?></option>
                            <option value="publish" <?php selected(get_option('auto_update_posts_type', 'both'), 'publish'); ?>><?php esc_html_e('Изменить только дату публикации', 'auto-update-posts'); ?></option>
                            <option value="modify" <?php selected(get_option('auto_update_posts_type', 'both'), 'modify'); ?>><?php esc_html_e('Изменить только дату изменения', 'auto-update-posts'); ?></option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e('Включить автообновление:', 'auto-update-posts'); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="auto_update_posts_enabled" value="1" <?php checked(get_option('auto_update_posts_enabled', 0), 1); ?> />
                            <?php esc_html_e('Включить скрипт при сохранении настроек', 'auto-update-posts'); ?>
                        </label>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e('Обновить записи в реальном времени:', 'auto-update-posts'); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="auto_update_posts_realtime" value="1" <?php checked(get_option('auto_update_posts_realtime', 0), 1); ?> />
                            <?php esc_html_e('Обновить выбранные записи сразу после сохранения настроек', 'auto-update-posts'); ?>
                        </label>
                    </td>
                </tr>
            </table>
            <?php submit_button(esc_html__('Сохранить настройки', 'auto-update-posts')); ?>
        </form>
    </div>
    <?php
}

// Функция для получения последних 10 записей
function auto_update_posts_get_last_ten_posts() {
    $args = array(
        'posts_per_page' => 10,
        'orderby'        => 'date',
        'order'          => 'DESC',
        'post_status'    => 'publish',
    );

    $last_posts = get_posts($args);
    $output     = '';

    foreach ($last_posts as $post) {
        $output .= '<span style="display: block; margin-bottom: 5px;">' . intval($post->ID) . ': ' . esc_html($post->post_title) . '</span>';
    }

    return $output;
}

// Регистрация настроек с санитизацией
function auto_update_posts_register_settings() {
    register_setting(
        'auto_update_posts_group',
        'auto_update_posts_ids',
        array('sanitize_callback' => 'sanitize_text_field')
    );
    register_setting(
        'auto_update_posts_group',
        'auto_update_posts_type',
        array('sanitize_callback' => 'sanitize_text_field')
    );
    register_setting(
        'auto_update_posts_group',
        'auto_update_posts_enabled',
        array('sanitize_callback' => 'intval')
    );
    register_setting(
        'auto_update_posts_group',
        'auto_update_posts_realtime',
        array('sanitize_callback' => 'intval')
    );
}
add_action('admin_init', 'auto_update_posts_register_settings');

// Обработчик для обновления записей
function auto_update_posts_handler() {
    $post_ids    = get_option('auto_update_posts_ids', '');
    $post_ids    = !empty($post_ids) ? explode(',', $post_ids) : array();
    $update_type = get_option('auto_update_posts_type', 'both');
    $current_time = current_time('mysql');

    foreach ($post_ids as $post_id) {
        $post_id = intval(trim($post_id));
        if (empty($post_id)) {
            continue;
        }

        $post_data = array('ID' => $post_id);

        if ($update_type === 'both') {
            $post_data['post_date']     = $current_time;
            $post_data['post_modified'] = $current_time;
        } elseif ($update_type === 'publish') {
            $post_data['post_date'] = $current_time;
        } elseif ($update_type === 'modify') {
            $post_data['post_modified'] = $current_time;
        }

        wp_update_post($post_data);
    }
}

// Обновление записей в реальном времени при сохранении настроек
function auto_update_posts_realtime_update() {
    if (get_option('auto_update_posts_realtime', 0) == 1) {
        auto_update_posts_handler();
        add_settings_error(
            'auto_update_posts_messages',
            'auto_update_posts_realtime_updated',
            esc_html__('Записи были успешно обновлены в реальном времени.', 'auto-update-posts'),
            'updated'
        );
        // Сбрасываем флаг, чтобы не обновлять при каждом сохранении
        update_option('auto_update_posts_realtime', 0);
    }
}
add_action('update_option_auto_update_posts_ids', 'auto_update_posts_realtime_update');
add_action('update_option_auto_update_posts_type', 'auto_update_posts_realtime_update');
add_action('update_option_auto_update_posts_realtime', 'auto_update_posts_realtime_update');

// Эндпоинт для серверного Cron (оставлен по запросу)
function auto_update_posts_cron_endpoint() {
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended
    if (isset($_GET['action']) && sanitize_text_field(wp_unslash($_GET['action'])) === 'auto_update_posts_cron') {
        if (get_option('auto_update_posts_enabled', 0) == 1) {
            auto_update_posts_handler();
            wp_die('Записи успешно обновлены.');
        } else {
            wp_die('Автообновление отключено в настройках плагина.');
        }
    }
}
add_action('init', 'auto_update_posts_cron_endpoint');