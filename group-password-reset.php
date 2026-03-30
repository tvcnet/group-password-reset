<?php
/**
 * Plugin Name: The Hack Repair Guy's Group Password Reset
 * Plugin URI: https://hackrepair.com/plugins/group-password-reset
 * Description: Bulk reset WordPress user passwords by role, exclude selected users, and notify affected users with secure reset links.
 * Version: 3.0.0
 * Author: Jim Walker
 * Author URI: https://hackrepair.com
 * License: GPL-2.0+
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: group-password-reset
 * Domain Path: /languages
 *
 * @package GroupPasswordReset
 */

if (!defined('ABSPATH')) {
    exit;
}

define('GPR_VERSION', '3.0.0');
define('GPR_PLUGIN_FILE', __FILE__);
define('GPR_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('GPR_PLUGIN_URL', plugin_dir_url(__FILE__));
define('GPR_CHUNK_SIZE', 20);
define('GPR_RESULTS_TRANSIENT_TTL', 15 * MINUTE_IN_SECONDS);
define('GPR_JOB_TRANSIENT_TTL', 30 * MINUTE_IN_SECONDS);

require_once GPR_PLUGIN_DIR . 'includes/password-reset.php';
require_once GPR_PLUGIN_DIR . 'includes/admin-menu.php';

function gpr_load_textdomain() {
    load_plugin_textdomain('group-password-reset', false, dirname(plugin_basename(__FILE__)) . '/languages/');
}

add_action('plugins_loaded', 'gpr_load_textdomain');
