<?php
/*
Plugin Name: Admin Disabler
Plugin URI: http://pospi.spadgos.com/libs/wp-admin-disabler
Description: A very simple plugin for disabling access to the Wordpress wp-admin backend on a per-role basis.
Version: 1.0
Author: pospi
Author URI: http://pospi.spadgos.com
License: MIT
*/

// :TODO: allow more granular configuration using capabilities

//------------------------------------------------------------------------------
//	main plugin logic
//------------------------------------------------------------------------------

function adm_disabler_check_admin_access()
{
	// check and make sure this isn't ajax, which is always processed through admin
	if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
		return;
	}
	// also check for CLI scripts, which should always run authenticated
	if (PHP_SAPI == 'cli' || (substr(PHP_SAPI, 0, 3) == 'cgi' && empty($_SERVER['REQUEST_URI']))) {
		return;
	}

	global $current_user;

	$backendRoles = get_option('adm_disabler_allowed_roles', array('administrator'));
	if (!$backendRoles || isset($current_user->caps['administrator'])) {
		return;		// nothing configured or a site admin, so behave as default.
	}

	$ok = false;
	foreach ($backendRoles as $role) {
		if (isset($current_user->caps[$role])) {
			$ok = true;
			break;
		}
	}

	if (!$ok) {
?>
<!DOCTYPE html>
<html>
	<head>
		<title><?php _e('Access Denied.', 'adminDisabler') ?></title>
		<link rel="stylesheet" href="<?php bloginfo('url'); ?>/wp-admin/css/install.css" type="text/css" />
	</head>
	<body id="error-page">
		<p><?php echo get_option('adm_disabler_disallow_msg', 'Please login as an administrator to access the site back-office.'); ?></p>
	</body>
</html>
<?php
		exit;
	}
}

add_action('admin_init', 'adm_disabler_check_admin_access', 1);

//------------------------------------------------------------------------------
//	configuration page
//------------------------------------------------------------------------------

function adm_disabler_settings_page()
{
	global $wp_roles;

	$allowedRoles = get_option('adm_disabler_allowed_roles', array('administrator'));

	// process submission from the below form
	if (isset($_POST['admd_settings'])) {
		if (empty($_POST['roles'])) {
			delete_option('adm_disabler_allowed_roles');
			$allowedRoles = array();
		} else {
			$allowedRoles = array_keys($_POST['roles']);
			if (update_option('adm_disabler_allowed_roles', $allowedRoles)) {
				$msg = "Settings updated.";
			}
		}

		if (!empty($_POST['adm_disabler_disallow_msg'])) {
			update_option('adm_disabler_disallow_msg', $_POST['adm_disabler_disallow_msg']);
		} else {
			delete_option('adm_disabler_disallow_msg');
		}
	}

	// build select options for role list
	$option = "<label><input type=\"checkbox\" name=\"roles[%s]\"%s /> %s</label> <br />";
	$optionsStr = '';
	foreach ($wp_roles->roles as $role => $rData) {
		if ($role == 'administrator') {
			// admins must always be selected
			$optionsStr .= sprintf($option, $role, ' checked="checked" disabled="disabled"', $rData['name'] . ' <em style="font-size: 0.8em; color: #888;">Administrators must always have access to the backend.</em>');
			continue;
		}
		$optionsStr .= sprintf($option, $role, in_array($role, $allowedRoles) ? ' checked="checked"' : '', $rData['name']);
	}

	// echo page
?>
<style type="text/css">
	.postbox textarea {
		vertical-align: top;
		width: 500px;
		height: 300px;
	}
</style>
<div class="wrap">
<h2>Admin Disabler</h2>
<div class="metabox-holder">
<form method="POST" id="admd_settings_frm">
<?php
	// messages
	if (isset($_GET['msg']) || $msg) {
		if (!isset($msg)) $msg = $_GET['msg'];
		if ($msg) {
			?><div id="message" class="updated below-h2"><p><?php echo esc_html($msg); ?></p></div><?php
		}
	}

	// options form
?>
	<div class="postbox">
		<h3><span>Permitted Roles</span></h3>
		<div class="inside">
			<p><?php echo $optionsStr; ?></p>
			<input type="submit" name="admd_settings" value="Update settings" />
		</div>
	</div>
	<div class="postbox">
		<h3><span>Messages</span></h3>
		<div class="inside">
			<label>Access denied message: <textarea name="adm_disabler_disallow_msg"><?php
				echo get_option('adm_disabler_disallow_msg', 'Please login as an administrator to access the site back-office.');
			?></textarea></label><br />
			<input type="submit" name="admd_settings" value="Update settings" />
		</div>
	</div>
</form>
</div>
</div>
<?php
}

function adm_disabler_setup_menus()
{
	add_submenu_page('options-general.php', 'Admin Disabler', 'Admin Disabler', 'manage_options', 'admin-disabler', 'adm_disabler_settings_page');
}
add_action('admin_menu', 'adm_disabler_setup_menus');

//------------------------------------------------------------------------------
//	plugin menu items
//------------------------------------------------------------------------------

function adm_disabler_plugin_links($links, $file)
{
	$base = basename(__FILE__);

	if (basename($file) == $base) {
		$links[] = '<a href="admin.php?page=admin-disabler">Settings</a>';
		$links[] = '<a href="https://github.com/pospi/wpadmindisabler">Github</a>';
	}

	return $links;
}
add_filter('plugin_row_meta', 'adm_disabler_plugin_links', 10, 2);
