<?php
/**
 * Plugin Name: Background Video Delay for Elementor Improve LCP
 * Description: Add delayed YouTube background videos to Elementor sections. Improve LCP scores and loading performance, defer video loading for increase page speed.
 * Version: 1.0.1
 * Author: David Kioshi Leite Kinjo
 * Author URI: https://renovesp.wordpress.com/
 * Plugin URI: https://wordpress.org/plugins/background-video-delay-elementor
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Requires PHP: 7.4
 * Text Domain: background-video-delay-elementor
 */

if (!defined('ABSPATH')) exit;

class EBVCF_Plugin {
    private $option_name = 'ebvcf_rules';

    public function __construct() {
        add_action('admin_menu', [$this, 'add_admin_page']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('wp_ajax_ebvcf_remove_rule', [$this, 'ajax_remove_rule']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_frontend_assets']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
    }

    public function add_admin_page() {
        add_menu_page(
            'Background Video Rules',
            'BG Video Rules',
            'manage_options',
            'ebvcf-admin',
            [$this, 'admin_page_html'],
            'dashicons-format-video',
            60
        );
    }

    public function register_settings() {
        register_setting('ebvcf_group', $this->option_name, [$this, 'sanitize_rules']);
    }

    public function ajax_remove_rule() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permission denied');
        }
        $rules = isset($_POST['ebvcf_rules']) ? (array) $_POST['ebvcf_rules'] : [];
        update_option($this->option_name, $this->sanitize_rules($rules));
        wp_send_json_success();
    }

    private function youtube_id_from_url($url) {
        if (preg_match('/^[A-Za-z0-9_-]{11}$/', $url)) {
            return $url;
        }
        if (preg_match('~(?:youtube\.com/(?:watch\?v=|embed/|v/)|youtu\.be/)([A-Za-z0-9_-]{11})~', $url, $m)) {
            return $m[1];
        }
        $parts = wp_parse_url($url);
        if (!empty($parts['query'])) {
            parse_str($parts['query'], $qs);
            if (!empty($qs['v']) && preg_match('/^[A-Za-z0-9_-]{11}$/', $qs['v'])) {
                return $qs['v'];
            }
        }
        return sanitize_text_field($url);
    }

    public function sanitize_rules($input) {
        if (!is_array($input)) return [];
        $clean = [];
        foreach ($input as $rule) {
            $c = [];
            $c['selector'] = isset($rule['selector']) ? sanitize_text_field($rule['selector']) : '';
            $c['video_id'] = $this->youtube_id_from_url($rule['video_id'] ?? '');
            $c['delay'] = isset($rule['delay']) ? floatval($rule['delay']) : 0;
            $c['scope'] = in_array($rule['scope'] ?? '', ['all','home','page']) ? $rule['scope'] : 'all';
            $c['page_id'] = isset($rule['page_id']) ? absint($rule['page_id']) : 0;
            $c['privacy'] = !empty($rule['privacy']) ? '1' : '';
            $c['fallback_image_id'] = isset($rule['fallback_image_id']) ? absint($rule['fallback_image_id']) : 0;
            if ($c['fallback_image_id']) {
                $url = wp_get_attachment_image_url($c['fallback_image_id'], 'full');
                $c['fallback_image_url'] = $url ? esc_url_raw($url) : '';
            } else {
                $c['fallback_image_url'] = '';
            }
            $c['overlay_color'] = isset($rule['overlay_color']) ? sanitize_hex_color($rule['overlay_color']) : '#000000';
            $c['overlay_opacity'] = isset($rule['overlay_opacity']) ? floatval($rule['overlay_opacity']) : 0.4;
            $clean[] = $c;
        }
        return array_values($clean);
    }

    public function enqueue_admin_assets($hook) {
        if ($hook !== 'toplevel_page_ebvcf-admin') return;
        wp_enqueue_media();
        wp_enqueue_script('ebvcf-admin-js', plugin_dir_url(__FILE__) . 'assets/js/ebvcf-admin.js', ['jquery'], '1.9', true);
        wp_localize_script('ebvcf-admin-js', 'ajaxurl', admin_url('admin-ajax.php'));
        wp_enqueue_style('ebvcf-admin-css', plugin_dir_url(__FILE__) . 'assets/css/ebvcf-admin.css');
    }

    public function enqueue_frontend_assets() {
        wp_enqueue_script('ebvcf-frontend', plugin_dir_url(__FILE__) . 'assets/js/ebvcf-script.js', ['jquery'], '1.9', true);
        $rules = get_option($this->option_name, []);
        wp_add_inline_script('ebvcf-frontend', 'const ebvcf_rules = ' . wp_json_encode(array_values($rules)) . ';');
        wp_enqueue_style('ebvcf-frontend-css', plugin_dir_url(__FILE__) . 'assets/css/ebvcf-frontend.css');
    }

    public function admin_page_html() {
        $rules = get_option($this->option_name, []);
        ?>
        <div class="wrap">
          <h1>Background Video Rules</h1>
          <form id="ebvcf-form" method="post" action="options.php">
            <?php settings_fields('ebvcf_group'); ?>
            <table class="widefat fixed" id="rules">
              <thead>
                <tr>
                  <th>Page ID CSS</th><th>Video ID / URL</th><th>Delay</th><th>Scope</th>
                  <th>ID from Page</th><th>Youtube Privacy</th><th>Overlay</th><th>Fallback</th><th>Action</th>
                </tr>
              </thead>
              <tbody>
              <?php foreach ($rules as $i => $rule): ?>
                <tr>
                  <td><input type="text" name="ebvcf_rules[<?php echo $i;?>][selector]" value="<?php echo esc_attr($rule['selector']);?>"></td>
                  <td><input type="text" name="ebvcf_rules[<?php echo $i;?>][video_id]" value="<?php echo esc_attr($rule['video_id']);?>"></td>
                  <td><input type="number" step="0.1" name="ebvcf_rules[<?php echo $i;?>][delay]" value="<?php echo esc_attr($rule['delay']);?>"></td>
                  <td>
                    <select name="ebvcf_rules[<?php echo $i;?>][scope]">
                      <option value="all" <?php selected($rule['scope'],'all');?>>Todos</option>
                      <option value="home" <?php selected($rule['scope'],'home');?>>Home</option>
                      <option value="page" <?php selected($rule['scope'],'page');?>>Page</option>
                    </select>
                  </td>
                  <td>
                    <select name="ebvcf_rules[<?php echo $i;?>][page_id]">
                      <option value="0" <?php selected($rule['page_id'],0);?>>— None </option>
                      <?php foreach(get_pages() as $p): ?>
                        <option value="<?php echo $p->ID;?>" <?php selected($rule['page_id'],$p->ID);?>><?php echo esc_html($p->post_title);?></option>
                      <?php endforeach;?>
                    </select>
                  </td>
                  <td><input type="checkbox" name="ebvcf_rules[<?php echo $i;?>][privacy]" value="1" <?php checked(!empty($rule['privacy']));?>></td>
                  <td>
                    <input type="color" name="ebvcf_rules[<?php echo $i;?>][overlay_color]" value="<?php echo esc_attr($rule['overlay_color']);?>">
                    <input type="number" step="0.05" min="0" max="1" name="ebvcf_rules[<?php echo $i;?>][overlay_opacity]" value="<?php echo esc_attr($rule['overlay_opacity']);?>" style="width:60px;">
                  </td>
                  <td>
                    <div class="fallback-image-preview" style="max-width:100px;">
                      <?php if(!empty($rule['fallback_image_url'])): ?>
                        <img src="<?php echo esc_url($rule['fallback_image_url']);?>" style="max-width:100%;display:block;">
                      <?php endif;?>
                    </div>
                    <input type="hidden" name="ebvcf_rules[<?php echo $i;?>][fallback_image_id]" class="fallback-image-id" value="<?php echo esc_attr($rule['fallback_image_id']);?>">
                    <button class="button select-fallback-image">Select</button>
                    <button class="button remove-fallback-image" style="<?php echo !empty($rule['fallback_image_url']) ? '' : 'display:none;';?>">Remover</button>
                  </td>
                  <td><button class="button remove-rule">Remove Rule</button></td>
                </tr>
              <?php endforeach; ?>
              </tbody>
            </table>
        <p><button id="add-rule" class="button button-primary">Add Rule</button></p>

            <?php submit_button('Save Changes'); ?>
          </form>
        </div>
        <?php
    }
}

new EBVCF_Plugin();