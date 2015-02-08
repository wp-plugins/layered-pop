<?php
/*
Plugin Name: Layered Pop
Plugin URI: https://layeredpopups.com/
Description: Create multi-layers animated popup. Get more advantages with premium <a href="https://layeredpopups.com/" target="_blank"><strong>Layered Popups</strong></a> plugin.
Version: 0.11
Author: Ivan Churakov
Author URI: https://layeredpopups.com/
*/
define('LPL_RECORDS_PER_PAGE', '20');
define('LPL_VERSION', 0.11);
define('LPL_AWEBER_APPID', '0e193739');
define('LPL_EXPORT_VERSION', '0001');

register_activation_hook(__FILE__, array("lpl_class", "install"));

class lpl_class {
	var $options;
	var $error;
	var $info;
	var $front_header = '';
	var $front_footer = '';
	var $local_fonts = array(
		'arial' => 'Arial',
		'verdana' => 'Verdana'
	);
	var $alignments = array(
		'left' => 'Left',
		'right' => 'Right',
		'center' => 'Center',
		'justify' => 'Justify'
	);
	var $display_modes = array(
		'none' => 'Disable popup',
		'every-time' => 'Every time', 
		'once-session' => 'Once per session',
		'once-only' => 'Only once'
	);
	var $appearances = array(
		'fade-in' => 'Fade In',
		'slide-up' => 'Slide Up',
		'slide-down' => 'Slide Down',
		'slide-left' => 'Slide Left',
		'slide-right' => 'Slide Right'
	);
	var $font_weights = array(
		'100' => 'Thin',
		'200' => 'Extra-light',
		'300' => 'Light',
		'400' => 'Normal',
		'500' => 'Medium',
		'600' => 'Demi-bold',
		'700' => 'Bold',
		'800' => 'Heavy',
		'900' => 'Black'
	);
	var $default_popup_options = array(
		"width" => "640",
		"height" => "400",
		"overlay_color" => "#333333",
		"overlay_opacity" => 0.8,
		"enable_close" => "on"
	);
	var $default_layer_options = array(
		"title" => "",
		"content" => "",
		"width" => "",
		"height" => "",
		"left" => 20,
		"top" => 20,
		"background_color" => "",
		"background_opacity" => 0.9,
		"background_image" => "",
		"content_align" => "left",
		"index" => 5,
		"appearance" => "fade-in",
		"appearance_delay" => "200",
		"appearance_speed" => "1000",
		"font" => "arial",
		"font_color" => "#000000",
		"font_weight" => "400",
		"font_size" => 14,
		"text_shadow_size" => 0,
		"text_shadow_color" => "#000000",
		"style" => ""
	);

	function __construct() {
		if (function_exists('load_plugin_textdomain')) {
			load_plugin_textdomain('lpl', false, dirname(plugin_basename(__FILE__)).'/languages/');
		}
		$this->options = array (
			"version" => LPL_VERSION,
			"cookie_value" => 'ilovelencha',
			"popup" => serialize(array(
				'width' => 640,
				'height' => 400,
				'options' => serialize($this->default_layer_options))
			),
			"onload_mode" => 'none',
			"onload_delay" => 0,
			"onload_close_delay" => 0
		);

		if (defined('WP_ALLOW_MULTISITE')) $this->install();
		$this->get_options();

		if (!empty($_COOKIE["lpl_error"])) {
			$this->error = stripslashes($_COOKIE["lpl_error"]);
			setcookie("lpl_error", "", time()+30, "/", ".".str_replace("www.", "", $_SERVER["SERVER_NAME"]));
		}
		if (!empty($_COOKIE["lpl_info"])) {
			$this->info = stripslashes($_COOKIE["lpl_info"]);
			setcookie("lpl_info", "", time()+30, "/", ".".str_replace("www.", "", $_SERVER["SERVER_NAME"]));
		}

		if (is_admin()) {
			add_action('admin_notices', array(&$this, 'admin_warning'));
			add_action('admin_enqueue_scripts', array(&$this, 'admin_enqueue_scripts'));
			add_action('admin_menu', array(&$this, 'admin_menu'));
			add_action('init', array(&$this, 'admin_request_handler'));
			add_action('wp_ajax_lpl_save_layer', array(&$this, "save_layer"));
			add_action('wp_ajax_lpl_copy_layer', array(&$this, "copy_layer"));
			add_action('wp_ajax_lpl_save_popup', array(&$this, "save_popup"));
			add_action('wp_ajax_lpl_delete_layer', array(&$this, "delete_layer"));
			add_action('wp_ajax_lpl_reset_cookie', array(&$this, "reset_cookie"));
			add_action('wp_ajax_lpl_save_settings', array(&$this, "save_settings"));
		} else {
			add_action('wp', array(&$this, 'front_init'), 15);
		}
	}

	function admin_warning() {
		if (class_exists('ulp_class')) {
			echo '
		<div class="updated"><p>'.__('You activated <strong>Layered Popups</strong> plugin. Do not forget to disable <strong>Layered Pop</strong> plugin.', 'lpl').'</p></div>';
		}
	}
	
	function admin_enqueue_scripts() {
		wp_enqueue_script("jquery");
		wp_enqueue_style('lpl', plugins_url('/css/admin.css', __FILE__), array(), LPL_VERSION);
		if (isset($_GET['page']) && $_GET['page'] == 'lpl-edit') {
			wp_enqueue_style('wp-color-picker');
			wp_enqueue_script('wp-color-picker');
		}
	}

	static function install() {
		global $wpdb;
		$add_default = false;
		$table_name = $wpdb->prefix."lpl_layers";
		if($wpdb->get_var("SHOW TABLES LIKE '".$table_name."'") != $table_name) {
			$sql = "CREATE TABLE ".$table_name." (
				id int(11) NOT NULL auto_increment,
				title varchar(255) collate utf8_unicode_ci NULL,
				content longtext collate utf8_unicode_ci NULL,
				zindex int(11) NULL default '5',
				details longtext collate utf8_unicode_ci NULL,
				created int(11) NULL,
				deleted int(11) NULL default '0',
				UNIQUE KEY  id (id)
			);";
			require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
			dbDelta($sql);
			$add_default = true;
		}
		if ($add_default) {
			if (file_exists(dirname(__FILE__).'/default.txt') && is_file(dirname(__FILE__).'/default.txt')) {
				$popup_data = file_get_contents(dirname(__FILE__).'/default.txt');
				$popup = unserialize($popup_data);
				$popup_details = $popup['popup'];
				$layers = $popup['layers'];
				if (sizeof($layers) > 0) {
					foreach ($layers as $layer) {
						$sql = "INSERT INTO ".$wpdb->prefix."lpl_layers (
							title, content, zindex, details, created, deleted) VALUES (
							'".mysql_real_escape_string($layer['title'])."',
							'".mysql_real_escape_string($layer['content'])."',
							'".mysql_real_escape_string($layer['zindex'])."',
							'".mysql_real_escape_string($layer['details'])."',
							'".time()."', '0')";
						$wpdb->query($sql);
					}
				}
			}
		}
	}

	function get_options() {
		$exists = get_option('lpl_version');
		if ($exists) {
			foreach ($this->options as $key => $value) {
				$this->options[$key] = get_option('lpl_'.$key);
			}
		}
	}

	function update_options() {
		if (current_user_can('manage_options')) {
			foreach ($this->options as $key => $value) {
				update_option('lpl_'.$key, $value);
			}
		}
	}

	function populate_options() {
		foreach ($this->options as $key => $value) {
			if (isset($_POST['lpl_'.$key])) {
				$this->options[$key] = trim(stripslashes($_POST['lpl_'.$key]));
			}
		}
	}

	function admin_menu() {
		add_menu_page(
			"Layered Pop"
			, "Layered Pop"
			, "add_users"
			, "lpl"
			, array(&$this, 'admin_settings')
		);
		add_submenu_page(
			"lpl"
			, __('Settings', 'lpl')
			, __('Settings', 'lpl')
			, "add_users"
			, "lpl"
			, array(&$this, 'admin_settings')
		);
		add_submenu_page(
			"lpl"
			, __('Edit Popup', 'lpl')
			, __('Edit Popup', 'lpl')
			, "add_users"
			, "lpl-edit"
			, array(&$this, 'admin_edit_popup')
		);
		add_submenu_page(
			"lpl"
			, __('FAQ', 'lpl')
			, __('FAQ', 'lpl')
			, "add_users"
			, "lpl-faq"
			, array(&$this, 'admin_faq')
		);
	}

		function admin_faq() {
		global $wpdb;

		echo '
		<div class="wrap lpl">
			<div id="icon-edit-pages" class="icon32"><br /></div><h2>'.__('Layered Pop - FAQ', 'lpl').'</h2>
			<div class="lpl-options" style="width: 100%; position: relative;">
				<h3>'.__('How can I raise a popup?', 'lpl').'</h3>
				<p>There are two ways to raise popup: by clicking certain element, on every page load.</p>
				<ol>
					<li>
						If you want to raise popup by clicking certain element, add the following <code>onclick</code> handler to the element:
						<br /><code>onclick="return lpl_open();"</code>
						<br />Example: <code>&lt;a href="#" onclick="return lpl_open();"&gt;Raise the popup&lt;/a&gt;</code>
					</li>
					<li>
						To raise popup on page load, go to <a href="'.admin_url('admin.php').'?page=lpl">Settings</a> page and set OnLoad parameters.
					</li>
				</ol>
				<h3>'.__('How can I create two or more popups?', 'lpl').'</h3>
				<p>There is no such feature, but you can download premium <a href="http://layeredpopups.com/" target="_blank">Layered Popups</a> plugin and create unlimited number of popups.</p>
				<h3>'.__('How can I add "close" icon to popup?', 'lpl').'</h3>
				<p>
					You can add and customize "close" icon as you wish. Create new layer with content like that:
					<br /><code>&lt;a href="#" onclick="return lpl_self_close();"&gt;&lt;img src="http://url-to-my-wonderful-close-icon" alt=""&gt;&lt;/a&gt;</code>
					<br />The important part of the this string is <code>onclick</code> handler: <code>onclick="return lpl_self_close();"</code>. It runs JavaScript
					function called <code>lpl_self_close()</code> which closes popup.
				</p>
				<h3>'.__('I inserted &lt;IMG&gt; tag, but image is not responsive.', 'lpl').'</h3>
				<p>Make sure that image tag does not have <code>width</code> and <code>height</code> attributes.</p>
				<h3>'.__('I want more features', 'lpl').'</h3>
				<p>Download premium <a href="http://layeredpopups.com/" target="_blank">Layered Popups</a> plugin with tons of features and settings. </p>
			</div>
		</div>';
	}

	function admin_settings() {
		global $wpdb;

		if (!empty($this->error)) $message = "<div class='error'><p>".$this->error."</p></div>";
		else if (!empty($this->info)) $message = "<div class='updated'><p>".$this->info."</p></div>";
		else $message = '';
		
		echo '
		<div class="wrap lpl">
			<div id="icon-options-general" class="icon32"><br /></div><h2>'.__('Layered Pop - Settings', 'lpl').'</h2>
			'.$message.'
			<form class="lpl-popup-form" enctype="multipart/form-data" method="post" style="margin: 0px" action="'.admin_url('admin.php').'">
			<div class="lpl-options" style="width: 100%; position: relative;">
				<h3>'.__('Live Preview', 'lpl').'</h3>
				<table class="lpl_useroptions">
					<tr>
						<th>'.__('Preview', 'lpl').':</th>
						<td>
							<a target="_blank" class="lpl_button button-secondary" title="'.__('Live Preview', 'lpl').'" href="'.get_bloginfo('wpurl').'?lpl=1">'.__('Live Preview', 'lpl').'</a>
						</td>
					</tr>
				</table>
				<hr>
				<h3>'.__('OnLoad Settings', 'lpl').'</h3>
				<table class="lpl_useroptions">
					<tr>
						<th>'.__('Display mode', 'lpl').':</th>
						<td style="line-height: 1.6;">';
		foreach ($this->display_modes as $key => $value) {
			echo '
							<input type="radio" name="lpl_onload_mode" id="lpl_onload_mode" value="'.$key.'"'.($this->options['onload_mode'] == $key ? ' checked="checked"' : '').'> '.$value.'<br />';
		}
		echo '
						</td>
					</tr>
					<tr>
						<th>'.__('Reset cookie', 'lpl').':</th>
						<td>
							<input type="button" class="lpl_button button-secondary" value="'.__('Reset cookie', 'lpl').'" onclick="return lpl_reset_cookie();" >
							<img id="lpl-reset-loading" src="'.plugins_url('/images/loading.gif', __FILE__).'">
							<br /><em>'.__('Click button to reset cookie. Popup will appear for all users. Do this operation if you changed content in popup and want to display it for returning visitors.', 'lpl').'</em>
						</td>
					</tr>
					<tr>
						<th>'.__('Start delay', 'lpl').':</th>
						<td style="vertical-align: middle;">
							<input type="text" name="lpl_onload_delay" value="'.esc_attr($this->options['onload_delay']).'" class="ic_input_number" placeholder="Delay"> '.__('seconds', 'lpl').'
							<br /><em>'.__('Popup appears with this delay after page loaded. Set "0" for immediate start.', 'lpl').'</em>
						</td>
					</tr>
					<tr>
						<th>'.__('Autoclose delay', 'lpl').':</th>
						<td style="vertical-align: middle;">
							<input type="text" name="lpl_onload_close_delay" value="'.esc_attr($this->options['onload_close_delay']).'" class="ic_input_number" placeholder="Autoclose delay"> '.__('seconds', 'lpl').'
							<br /><em>'.__('Popup is automatically closed after this period of time. Set "0", if you do not need autoclosing.', 'lpl').'</em>
						</td>
					</tr>
				</table>
				<hr>
				<div style="text-align: right; margin-bottom: 5px; margin-top: 20px;">
					<input type="hidden" name="action" value="lpl_save_settings" />
					<img class="lpl-loading" src="'.plugins_url('/images/loading.gif', __FILE__).'">
					<input type="submit" class="button-primary lpl-button" name="submit" value="'.__('Save Settings', 'lpl').'" onclick="return lpl_save_settings();">
				</div>
				<div class="lpl-message"></div>
			</div>
			</form>
			<script type="text/javascript">
				function lpl_reset_cookie() {
					jQuery("#lpl-reset-loading").fadeIn(350);
					var data = {action: "lpl_reset_cookie"};
					jQuery.post("'.admin_url('admin-ajax.php').'", data, function(data) {
						jQuery("#lpl-reset-loading").fadeOut(350);
					});
					return false;
				}
				function lpl_save_settings() {
					jQuery(".lpl-popup-form").find(".lpl-loading").fadeIn(350);
					jQuery(".lpl-popup-form").find(".lpl-message").slideUp(350);
					jQuery(".lpl-popup-form").find(".lpl-button").attr("disabled", "disabled");
					jQuery.post("'.admin_url('admin-ajax.php').'", 
						jQuery(".lpl-popup-form").serialize(),
						function(return_data) {
							//alert(return_data);
							jQuery(".lpl-popup-form").find(".lpl-loading").fadeOut(350);
							jQuery(".lpl-popup-form").find(".lpl-button").removeAttr("disabled");
							var data;
							try {
								var data = jQuery.parseJSON(return_data);
								var status = data.status;
								if (status == "OK") {
									location.href = data.return_url;
								} else if (status == "ERROR") {
									jQuery(".lpl-popup-form").find(".lpl-message").html(data.message);
									jQuery(".lpl-popup-form").find(".lpl-message").slideDown(350);
								} else {
									jQuery(".lpl-popup-form").find(".lpl-message").html("Service is not available.");
									jQuery(".lpl-popup-form").find(".lpl-message").slideDown(350);
								}
							} catch(error) {
								jQuery(".lpl-popup-form").find(".lpl-message").html("Service is not available.");
								jQuery(".lpl-popup-form").find(".lpl-message").slideDown(350);
							}
						}
					);
					return false;
				}
			</script>
		</div>';
	}
	
	function reset_cookie() {
		if (current_user_can('manage_options')) {
			$this->options["cookie_value"] = time();
			update_option('lpl_cookie_value', $this->options["cookie_value"]);
			echo 'OK';
		}
		exit;
	}

	function save_settings() {
		global $wpdb;
		$popup_options = array();
		if (current_user_can('manage_options')) {
			$this->populate_options();
			$errors = array();
			if (strlen($this->options['onload_delay']) > 0 && $this->options['onload_delay'] != preg_replace('/[^0-9]/', '', $this->options['onload_delay'])) $errors[] = __('Invalid OnLoad delay value.', 'lpl');
			if (strlen($this->options['onload_close_delay']) > 0 && $this->options['onload_close_delay'] != preg_replace('/[^0-9]/', '', $this->options['onload_close_delay'])) $errors[] = __('Invalid OnLoad autoclosing delay value.', 'lpl');

			if (!empty($errors)) {
				$return_object = array();
				$return_object['status'] = 'ERROR';
				$return_object['message'] = __('Attention! Please correct the errors below and try again.', 'lpl').'<ul><li>'.implode('</li><li>', $errors).'</li></ul>';
				echo json_encode($return_object);
				exit;
			}
			$this->update_options();
			
			setcookie("lpl_info", __('Settings successfully <strong>saved</strong>.', 'lpl'), time()+30, "/", ".".str_replace("www.", "", $_SERVER["SERVER_NAME"]));
			
			$return_object = array();
			$return_object['status'] = 'OK';
			$return_object['return_url'] = admin_url('admin.php').'?page=lpl';
			echo json_encode($return_object);
			exit;
		}
	}
	
	function admin_edit_popup() {
		global $wpdb;

		$popup_details = unserialize($this->options['popup']);
		if (!empty($popup_details)) {
			$popup_options = unserialize($popup_details['options']);
			$popup_options = array_merge($this->default_popup_options, $popup_options);
		} else {
			$popup_options = $this->default_popup_options;
		}
		
		$errors = true;
		if (!empty($this->error)) $message = "<div class='error'><p>".$this->error."</p></div>";
		else if (!empty($this->info)) $message = "<div class='updated'><p>".$this->info."</p></div>";
		else $message = '';
		
		echo '
		<div class="wrap lpl">
			<div id="icon-edit-pages" class="icon32"><br /></div><h2>'.__('Layered Pop - Edit Popup', 'lpl').'</h2>
			'.$message.'
			<form class="lpl-popup-form" enctype="multipart/form-data" method="post" style="margin: 0px" action="'.admin_url('admin.php').'">
			<div class="lpl-options" style="width: 100%; position: relative;">
				<h3>'.__('General Parameters', 'lpl').'</h3>
				<table class="lpl_useroptions">
					<tr>
						<th>'.__('Basic size', 'lpl').':</th>
						<td style="vertical-align: middle;">
							<input type="text" name="lpl_width" value="'.(!empty($popup_details['width']) ? esc_attr($popup_details['width']) : esc_attr($this->default_popup_options['width'])).'" class="ic_input_number" placeholder="Width" onblur="lpl_build_preview();" onchange="lpl_build_preview();"> x
							<input type="text" name="lpl_height" value="'.(!empty($popup_details['height']) ? esc_attr($popup_details['height']) : esc_attr($this->default_popup_options['height'])).'" class="ic_input_number" placeholder="Height" onblur="lpl_build_preview();" onchange="lpl_build_preview();"> pixels
							<br /><em>'.__('Enter the size of basic frame. This frame will be centered and all layers will be placed relative to the top-left corner of this frame.', 'lpl').'</em>
						</td>
					</tr>
					<tr>
						<th>'.__('Overlay color', 'lpl').':</th>
						<td>
							<input type="text" class="lpl-color ic_input_number" name="lpl_overlay_color" value="'.(!empty($popup_options['overlay_color']) ? esc_attr($popup_options['overlay_color']) : esc_attr($this->default_popup_options['overlay_color'])).'" placeholder="">
							<br /><em>'.__('Set the overlay color.', 'lpl').'</em>
						</td>
					</tr>
					<tr>
						<th>'.__('Overlay opacity', 'lpl').':</th>
						<td>
							<input type="text" name="lpl_overlay_opacity" value="'.(!empty($popup_options['overlay_opacity']) ? esc_attr($popup_options['overlay_opacity']) : esc_attr($this->default_popup_options['overlay_opacity'])).'" class="ic_input_number" placeholder="Opacity">
							<br /><em>'.__('Set the overlay opacity. The value must be in a range [0...1].', 'lpl').'</em>
						</td>
					</tr>
					<tr>
						<th>'.__('Extended closing', 'lpl').':</th>
						<td>
							<input type="checkbox" id="lpl_enable_close" name="lpl_enable_close" '.($popup_options['enable_close'] == "on" ? 'checked="checked"' : '').'"> '.__('Close popup window on ESC-button click and overlay click', 'lpl').'
							<br /><em>'.__('Please tick checkbox to enable popup closing on ESC-button click and overlay click.', 'lpl').'</em>
						</td>
					</tr>
				</table>
				<h3>'.__('Layers', 'lpl').'</h3>
				<div id="lpl-layers-data">';
		$sql = "SELECT * FROM ".$wpdb->prefix."lpl_layers WHERE deleted = '0' ORDER BY created ASC";
		$layers = $wpdb->get_results($sql, ARRAY_A);
		if (sizeof($layers) > 0) {
			foreach ($layers as $layer) {
				$layer_options = unserialize($layer['details']);
				if (strlen($layer_options['content']) == 0) $content = 'No content...';
				else if (strlen($layer_options['content']) > 192) $content = substr($layer_options['content'], 0, 180).'...';
				else $content = $layer_options['content'];
				$layer_options_html = '';
				foreach ($layer_options as $key => $value) {
					$layer_options_html .= '<input type="hidden" id="lpl_layer_'.$layer['id'].'_'.$key.'" name="lpl_layer_'.$layer['id'].'_'.$key.'" value="'.esc_attr($value).'">';
				}
				echo '
					<div class="lpl-layers-item" id="lpl-layer-'.$layer['id'].'">
						<div class="lpl-layers-item-cell lpl-layers-item-cell-info">
							<h4>'.esc_attr($layer_options['title']).'</h4>
							<p>'.esc_attr($content).'</p>
						</div>
						<div class="lpl-layers-item-cell" style="width: 70px;">
							<a href="#" title="'.__('Edit layer details', 'lpl').'" onclick="return lpl_edit_layer(this);"><img src="'.plugins_url('/images/edit.png', __FILE__).'" alt="'.__('Edit layer details', 'lpl').'" border="0"></a>
							<a href="#" title="'.__('Duplicate layer', 'lpl').'" onclick="return lpl_copy_layer(this);"><img src="'.plugins_url('/images/copy.png', __FILE__).'" alt="'.__('Duplicate details', 'lpl').'" border="0"></a>
							<a href="#" title="'.__('Delete layer', 'lpl').'" onclick="return lpl_delete_layer(this);"><img src="'.plugins_url('/images/delete.png', __FILE__).'" alt="'.__('Delete layer', 'lpl').'" border="0"></a>
						</div>
						'.$layer_options_html.'
					</div>
					<div class="lpl-edit-layer" id="lpl-edit-layer-'.$layer['id'].'"></div>';
			}
		}
		echo '									
				</div>
				<div id="lpl-new-layer"></div>
				<input type="button" class="button-secondary" onclick="return lpl_add_layer();" value="'.__('Add New Layer', 'lpl').'">
				<h3>'.__('Live Preview', 'lpl').'</h3>
				<div class="lpl-preview-container">
					<div class="lpl-preview-window">
						<div class="lpl-preview-content">
						</div>
					</div>
				</div>
				<hr>
				<div style="text-align: right; margin-bottom: 5px; margin-top: 20px;">
					<input type="hidden" name="action" value="lpl_save_popup" />
					<img class="lpl-loading" src="'.plugins_url('/images/loading.gif', __FILE__).'">
					<input type="submit" class="button-primary lpl-button" name="submit" value="'.__('Save Popup Details', 'lpl').'" onclick="return lpl_save_popup();">
				</div>
				<div class="lpl-message"></div>
				<div id="lpl-overlay"></div>
			</div>
			</form>
			<script type="text/javascript">
				var lpl_local_fonts = new Array("'.strtolower(implode('","', $this->local_fonts)).'");
				var lpl_active_layer = -1;
				var lpl_default_layer_options = {';
		foreach ($this->default_layer_options as $key => $value) {
			echo '
					"'.$key.'" : "'.esc_attr($value).'",';
		}
		echo '
					"a" : ""
				};
				function lpl_save_popup() {
					jQuery(".lpl-popup-form").find(".lpl-loading").fadeIn(350);
					jQuery(".lpl-popup-form").find(".lpl-message").slideUp(350);
					jQuery(".lpl-popup-form").find(".lpl-button").attr("disabled", "disabled");
					jQuery.post("'.admin_url('admin-ajax.php').'", 
						jQuery(".lpl-popup-form").serialize(),
						function(return_data) {
							//alert(return_data);
							jQuery(".lpl-popup-form").find(".lpl-loading").fadeOut(350);
							jQuery(".lpl-popup-form").find(".lpl-button").removeAttr("disabled");
							var data;
							try {
								var data = jQuery.parseJSON(return_data);
								var status = data.status;
								if (status == "OK") {
									location.href = data.return_url;
								} else if (status == "ERROR") {
									jQuery(".lpl-popup-form").find(".lpl-message").html(data.message);
									jQuery(".lpl-popup-form").find(".lpl-message").slideDown(350);
								} else {
									jQuery(".lpl-popup-form").find(".lpl-message").html("Service is not available.");
									jQuery(".lpl-popup-form").find(".lpl-message").slideDown(350);
								}
							} catch(error) {
								jQuery(".lpl-popup-form").find(".lpl-message").html("Service is not available.");
								jQuery(".lpl-popup-form").find(".lpl-message").slideDown(350);
							}
						}
					);
					return false;
				}
				function lpl_add_layer() {
					jQuery("#lpl-overlay").fadeIn(350);
					jQuery("#lpl-new-layer").append(jQuery(".lpl-layer-options"));
					jQuery.each(lpl_default_layer_options, function(key, value) {
						if (key == "scrollbar") {
							if (value == "on") jQuery("[name=\'lpl_layer_"+key+"\']").attr("checked", "checked");
							else jQuery("[name=\'lpl_layer_"+key+"\']").removeAttr("checked");
						} else jQuery("[name=\'lpl_layer_"+key+"\']").val(value);
					});
					jQuery("[name=\'lpl_layer_id\']").val("0");
					lpl_active_layer = 0;
					jQuery("#lpl-new-layer").slideDown(350);
					return false;
				}
				function lpl_edit_layer(object) {
					var layer_item_id = jQuery(object).parentsUntil(".lpl-layers-item").parent().attr("id");
					layer_item_id = layer_item_id.replace("lpl-layer-", "");
					jQuery("#lpl-overlay").fadeIn(350);
					jQuery("#lpl-edit-layer-"+layer_item_id).append(jQuery(".lpl-layer-options"));
					jQuery.each(lpl_default_layer_options, function(key, value) {
						if (key == "scrollbar") {
							if (jQuery("[name=\'lpl_layer_"+layer_item_id+"_"+key+"\']").val() == "on") jQuery("[name=\'lpl_layer_"+key+"\']").attr("checked", "checked");
							else jQuery("[name=\'lpl_layer_"+key+"\']").removeAttr("checked");
						} else jQuery("[name=\'lpl_layer_"+key+"\']").val(jQuery("[name=\'lpl_layer_"+layer_item_id+"_"+key+"\']").val());
					});
					jQuery("[name=\'lpl_layer_id\']").val(layer_item_id);
					lpl_active_layer = layer_item_id;
					jQuery("#lpl-preview-layer-"+layer_item_id).addClass("lpl-preview-layer-active");
					jQuery("#lpl-edit-layer-"+layer_item_id).slideDown(350);
					return false;
				}
				function lpl_delete_layer(object) {
					var answer = confirm("Do you really want to remove this layer?")
					if (answer) {
						var layer_item_id = jQuery(object).parentsUntil(".lpl-layers-item").parent().attr("id");
						layer_item_id = layer_item_id.replace("lpl-layer-", "");
						jQuery("#lpl-edit-layer-"+layer_item_id).remove();
						jQuery("#lpl-layer-"+layer_item_id).fadeOut(350, function() {
							jQuery("#lpl-layer-"+layer_item_id).remove();
							jQuery.post("'.admin_url('admin-ajax.php').'", 
								"action=lpl_delete_layer&lpl_layer_id="+layer_item_id,
								function(return_data) {
									lpl_build_preview();
								}
							);
						});
					}
					return false;
				}
				function lpl_copy_layer(object) {
					var answer = confirm("Do you really want to duplicate this layer?")
					if (answer) {
						var layer_item_id = jQuery(object).parentsUntil(".lpl-layers-item").parent().attr("id");
						layer_item_id = layer_item_id.replace("lpl-layer-", "");
						jQuery.post("'.admin_url('admin-ajax.php').'", 
							"action=lpl_copy_layer&lpl_layer_id="+layer_item_id,
							function(return_data) {
								var data = jQuery.parseJSON(return_data);
								var status = data.status;
								if (status == "OK") {
									jQuery("#lpl-layers-data").append("<div class=\'lpl-layers-item\' id=\'lpl-layer-"+data.layer_id+"\' style=\'display: none;\'></div><div class=\'lpl-edit-layer\' id=\'lpl-edit-layer-"+data.layer_id+"\'></div>");
									jQuery("#lpl-layer-"+data.layer_id).html(jQuery("#lpl-layers-item-container").html());
									jQuery("#lpl-layer-"+data.layer_id).find("h4").html(data.title);
									jQuery("#lpl-layer-"+data.layer_id).find("p").html(data.content);
									jQuery("#lpl-layer-"+data.layer_id).append(data.options_html);
									jQuery("#lpl-layer-"+data.layer_id).slideDown(350);
									lpl_build_preview();
								}
							}
						);
					}
					return false;
				}
				function lpl_cancel_layer(object) {
					jQuery("#lpl-overlay").fadeOut(350);
					var container = jQuery(object).parentsUntil(".lpl-layer-options").parent().parent();
					jQuery("#"+jQuery(container).attr("id")).slideUp(350, function() {
						jQuery("#lpl-layer-options-container").append(jQuery(".lpl-layer-options"));
						jQuery(".lpl-preview-layer-active").removeClass(".lpl-preview-layer-active");
						lpl_active_layer = -1;
						lpl_build_preview();
					});
					return false;
				}
				function lpl_save_layer() {
					jQuery(".lpl-layer-options").find(".lpl-loading").fadeIn(350);
					jQuery(".lpl-layer-options").find(".lpl-message").slideUp(350);
					jQuery(".lpl-layer-options").find(".lpl-button").attr("disabled", "disabled");
					jQuery.post("'.admin_url('admin-ajax.php').'", 
						jQuery(".lpl-layer-options input, .lpl-layer-options select, .lpl-layer-options textarea").serialize(),
						function(return_data) {
							//alert(return_data);
							jQuery(".lpl-layer-options").find(".lpl-loading").fadeOut(350);
							jQuery(".lpl-layer-options").find(".lpl-button").removeAttr("disabled");
							var data;
							try {
								var data = jQuery.parseJSON(return_data);
								var status = data.status;
								if (status == "OK") {
									jQuery("#lpl-overlay").fadeOut(350);
									if(jQuery("#lpl-layers-data").find("#lpl-layer-"+data.layer_id).length == 0) {
										jQuery("#lpl-new-layer").slideUp(350, function() {
											jQuery("#lpl-layer-options-container").append(jQuery(".lpl-layer-options"));
										});
										jQuery("#lpl-layers-data").append("<div class=\'lpl-layers-item\' id=\'lpl-layer-"+data.layer_id+"\' style=\'display: none;\'></div><div class=\'lpl-edit-layer\' id=\'lpl-edit-layer-"+data.layer_id+"\'></div>");
										jQuery("#lpl-layer-"+data.layer_id).html(jQuery("#lpl-layers-item-container").html());
										jQuery("#lpl-layer-"+data.layer_id).find("h4").html(data.title);
										jQuery("#lpl-layer-"+data.layer_id).find("p").html(data.content);
										jQuery("#lpl-layer-"+data.layer_id).append(data.options_html);
										jQuery("#lpl-layer-"+data.layer_id).slideDown(350);
										lpl_active_layer = -1;
										jQuery(".lpl-preview-layer-active").removeClass(".lpl-preview-layer-active");
										lpl_build_preview();
									} else {
										jQuery("#lpl-edit-layer-"+data.layer_id).slideUp(350, function() {
											jQuery("#lpl-layer-options-container").append(jQuery(".lpl-layer-options"));
										});
										jQuery("#lpl-layer-"+data.layer_id).fadeOut(350, function() {
											jQuery("#lpl-layer-"+data.layer_id).html(jQuery("#lpl-layers-item-container").html());
											jQuery("#lpl-layer-"+data.layer_id).find("h4").html(data.title);
											jQuery("#lpl-layer-"+data.layer_id).find("p").html(data.content);
											jQuery("#lpl-layer-"+data.layer_id).append(data.options_html);
											jQuery("#lpl-layer-"+data.layer_id).fadeIn(350);
											lpl_active_layer = -1;
											jQuery(".lpl-preview-layer-active").removeClass(".lpl-preview-layer-active");
											lpl_build_preview();
										});
									}
								} else if (status == "ERROR") {
									jQuery(".lpl-layer-options").find(".lpl-message").html(data.message);
									jQuery(".lpl-layer-options").find(".lpl-message").slideDown(350);
								} else {
									jQuery(".lpl-layer-options").find(".lpl-message").html("Service is not available.");
									jQuery(".lpl-layer-options").find(".lpl-message").slideDown(350);
								}
							} catch(error) {
								jQuery(".lpl-layer-options").find(".lpl-message").html("Service is not available.");
								jQuery(".lpl-layer-options").find(".lpl-message").slideDown(350);
							}
						}
					);
					return false;
				}
				function lpl_build_preview() {
					//jQuery(".lpl-preview-container").css({
					//	"background" : jQuery("[name=\'lpl_overlay_color\']").val()
					//});
					jQuery(".lpl-preview-window").css({
						"width" : parseInt(jQuery("[name=\'lpl_width\']").val(), 10) + "px",
						"height" : parseInt(jQuery("[name=\'lpl_height\']").val(), 10) + "px"
					});
					
					var popup_style = "";
					jQuery(".lpl-layers-item").each(function() {
						var layer_id = jQuery(this).attr("id").replace("lpl-layer-", "");
						if (lpl_active_layer == layer_id) {
							var content = jQuery("#lpl_layer_content").val();
							var style = "#lpl-preview-layer-"+layer_id+" {left:" + parseInt(jQuery("#lpl_layer_left").val(), 10) + "px;top:" + parseInt(jQuery("#lpl_layer_top").val(), 10) + "px;}";
							if (jQuery("#lpl_layer_width").val() != "") style += "#lpl-preview-layer-"+layer_id+" {width:"+parseInt(jQuery("#lpl_layer_width").val(), 10)+"px;}";
							if (jQuery("#lpl_layer_height").val() != "") style += "#lpl-preview-layer-"+layer_id+" {height:"+parseInt(jQuery("#lpl_layer_height").val(), 10)+"px;}";
							var background = "";		
							if (jQuery("#lpl_layer_background_color").val() != "") {
								var rgb = lpl_hex2rgb(jQuery("#lpl_layer_background_color").val());
								if (rgb != false) background = "background-color:"+jQuery("#lpl_layer_background_color").val()+";background-color:rgba("+rgb.r+","+rgb.g+","+rgb.b+","+jQuery("#lpl_layer_background_opacity").val()+");";
							} else $background = "";
							if (jQuery("#lpl_layer_background_image").val() != "") {
								background += "background-image:url("+jQuery("#lpl_layer_background_image").val()+");background-repeat:repeat;";
							}
							var font = "font-family:\'"+jQuery("#lpl_layer_font").val()+"\',arial;font-weight:"+jQuery("#lpl_layer_font_weight").val()+";color:"+jQuery("#lpl_layer_font_color").val()+";font-size:"+parseInt(jQuery("#lpl_layer_font_size").val(), 10)+"px;";
							if (parseInt(jQuery("#lpl_layer_text_shadow_size").val(), 10) != 0 && jQuery("#lpl_layer_text_shadow_color").val() != "") font += "text-shadow:"+jQuery("#lpl_layer_text_shadow_color").val()+" "+jQuery("#lpl_layer_text_shadow_size").val()+"px "+" "+jQuery("#lpl_layer_text_shadow_size").val()+"px "+" "+jQuery("#lpl_layer_text_shadow_size").val()+"px";
							style += "#lpl-preview-layer-"+layer_id+",#lpl-preview-layer-"+layer_id+" p,#lpl-preview-layer-"+layer_id+" a,#lpl-preview-layer-"+layer_id+" span,#lpl-preview-layer-"+layer_id+" li,#lpl-preview-layer-"+layer_id+" input,#lpl-preview-layer-"+layer_id+" button,#lpl-preview-layer-"+layer_id+" textarea {"+font+"}";
							style += "#lpl-preview-layer-"+layer_id+"{"+background+"z-index:"+parseInt(parseInt(jQuery("#lpl_layer_index").val(), 10)+1000, 10)+";text-align:"+jQuery("#lpl_layer_content_align").val()+"}";
							if (jQuery("#lpl_layer_style").val() != "") style += "#lpl-preview-layer-"+layer_id+"{"+jQuery("#lpl_layer_style").val()+"}";
							if (jQuery("#lpl_layer_scrollbar").is(":checked")) style += "#lpl-preview-layer-"+layer_id+"{overflow:hidden;}";
							var layer = "<style>"+style+"</style><div class=\'lpl-preview-layer lpl-preview-layer-active\' id=\'lpl-preview-layer-"+layer_id+"\'>"+content+"</div>";
						} else {
							var content = jQuery("#lpl_layer_"+layer_id+"_content").val();
							var style = "#lpl-preview-layer-"+layer_id+" {left:" + parseInt(jQuery("#lpl_layer_"+layer_id+"_left").val(), 10) + "px;top:" + parseInt(jQuery("#lpl_layer_"+layer_id+"_top").val(), 10) + "px;}";
							if (jQuery("#lpl_layer_"+layer_id+"_width").val() != "") style += "#lpl-preview-layer-"+layer_id+" {width:"+parseInt(jQuery("#lpl_layer_"+layer_id+"_width").val(), 10)+"px;}";
							if (jQuery("#lpl_layer_"+layer_id+"_height").val() != "") style += "#lpl-preview-layer-"+layer_id+" {height:"+parseInt(jQuery("#lpl_layer_"+layer_id+"_height").val(), 10)+"px;}";
							var background = "";		
							if (jQuery("#lpl_layer_"+layer_id+"_background_color").val() != "") {
								var rgb = lpl_hex2rgb(jQuery("#lpl_layer_"+layer_id+"_background_color").val());
								if (rgb != false) background = "background-color:"+jQuery("#lpl_layer_"+layer_id+"_background_color").val()+";background-color:rgba("+rgb.r+","+rgb.g+","+rgb.b+","+jQuery("#lpl_layer_"+layer_id+"_background_opacity").val()+");";
							} else $background = "";
							if (jQuery("#lpl_layer_"+layer_id+"_background_image").val() != "") {
								background += "background-image:url("+jQuery("#lpl_layer_"+layer_id+"_background_image").val()+");background-repeat:repeat;";
							}
							var font = "font-family:\'"+jQuery("#lpl_layer_"+layer_id+"_font").val()+"\',arial;font-weight:"+jQuery("#lpl_layer_"+layer_id+"_font_weight").val()+";color:"+jQuery("#lpl_layer_"+layer_id+"_font_color").val()+";font-size:"+parseInt(jQuery("#lpl_layer_"+layer_id+"_font_size").val(), 10)+"px;";
							if (parseInt(jQuery("#lpl_layer_"+layer_id+"_text_shadow_size").val(), 10) != 0 && jQuery("#lpl_layer_"+layer_id+"_text_shadow_color").val() != "") font += "text-shadow:"+jQuery("#lpl_layer_"+layer_id+"_text_shadow_color").val()+" "+jQuery("#lpl_layer_"+layer_id+"_text_shadow_size").val()+"px "+" "+jQuery("#lpl_layer_"+layer_id+"_text_shadow_size").val()+"px "+" "+jQuery("#lpl_layer_"+layer_id+"_text_shadow_size").val()+"px";
							style += "#lpl-preview-layer-"+layer_id+",#lpl-preview-layer-"+layer_id+" p,#lpl-preview-layer-"+layer_id+" a,#lpl-preview-layer-"+layer_id+" span,#lpl-preview-layer-"+layer_id+" li,#lpl-preview-layer-"+layer_id+" input,#lpl-preview-layer-"+layer_id+" button,#lpl-preview-layer-"+layer_id+" textarea {"+font+"}";
							style += "#lpl-preview-layer-"+layer_id+"{"+background+"z-index:"+parseInt(parseInt(jQuery("#lpl_layer_"+layer_id+"_index").val(), 10)+1000, 10)+";text-align:"+jQuery("#lpl_layer_"+layer_id+"_content_align").val()+";}";
							if (jQuery("#lpl_layer_"+layer_id+"_style").val() != "") style += "#lpl-preview-layer-"+layer_id+"{"+jQuery("#lpl_layer_"+layer_id+"_style").val()+"}";
							if (jQuery("#lpl_layer_"+layer_id+"_scrollbar").val() == "on") style += "#lpl-preview-layer-"+layer_id+"{overflow:hidden;}";
							var layer = "<style>"+style+"</style><div class=\'lpl-preview-layer\' id=\'lpl-preview-layer-"+layer_id+"\'>"+content+"</div>";
						}
						jQuery(".lpl-preview-content").append(layer);
					});
					if (lpl_active_layer == 0) {
						layer_id = "0";
						var content = jQuery("#lpl_layer_content").val();
						var style = "#lpl-preview-layer-"+layer_id+" {left:" + parseInt(jQuery("#lpl_layer_left").val(), 10) + "px;top:" + parseInt(jQuery("#lpl_layer_top").val(), 10) + "px;}";
						if (jQuery("#lpl_layer_width").val() != "") style += "#lpl-preview-layer-"+layer_id+" {width:"+parseInt(jQuery("#lpl_layer_width").val(), 10)+"px;}";
						if (jQuery("#lpl_layer_height").val() != "") style += "#lpl-preview-layer-"+layer_id+" {height:"+parseInt(jQuery("#lpl_layer_height").val(), 10)+"px;}";
						var background = "";		
						if (jQuery("#lpl_layer_background_color").val() != "") {
							var rgb = lpl_hex2rgb(jQuery("#lpl_layer_background_color").val());
							if (rgb != false) background = "background-color:"+jQuery("#lpl_layer_background_color").val()+";background-color:rgba("+rgb.r+","+rgb.g+","+rgb.b+","+jQuery("#lpl_layer_background_opacity").val()+");";
						} else $background = "";
						if (jQuery("#lpl_layer_background_image").val() != "") {
							background += "background-image:url("+jQuery("#lpl_layer_background_image").val()+");background-repeat:repeat;";
						}
						var font = "font-family:\'"+jQuery("#lpl_layer_font").val()+"\',arial;font-weight:"+jQuery("#lpl_layer_font_weight").val()+";color:"+jQuery("#lpl_layer_font_color").val()+";font-size:"+parseInt(jQuery("#lpl_layer_font_size").val(), 10)+"px;";
						if (parseInt(jQuery("#lpl_layer_text_shadow_size").val(), 10) != 0 && jQuery("#lpl_layer_text_shadow_color").val() != "") font += "text-shadow:"+jQuery("#lpl_layer_text_shadow_color").val()+" "+jQuery("#lpl_layer_text_shadow_size").val()+"px "+" "+jQuery("#lpl_layer_text_shadow_size").val()+"px "+" "+jQuery("#lpl_layer_text_shadow_size").val()+"px;";
						style += "#lpl-preview-layer-"+layer_id+",#lpl-preview-layer-"+layer_id+" p,#lpl-preview-layer-"+layer_id+" a,#lpl-preview-layer-"+layer_id+" span,#lpl-preview-layer-"+layer_id+" li,#lpl-preview-layer-"+layer_id+" input,#lpl-preview-layer-"+layer_id+" button,#lpl-preview-layer-"+layer_id+" textarea {"+font+"}";
						style += "#lpl-preview-layer-"+layer_id+"{"+background+"z-index:"+parseInt(parseInt(jQuery("#lpl_layer_index").val(), 10)+1000, 10)+";text-align:"+jQuery("#lpl_layer_content_align").val()+";}";
						if (jQuery("#lpl_layer_style").val() != "") style += "#lpl-preview-layer-"+layer_id+"{"+jQuery("#lpl_layer_style").val()+"}";
						if (jQuery("#lpl_layer_scrollbar").is(":checked")) style += "#lpl-preview-layer-"+layer_id+"{overflow:hidden;}";
						var layer = "<style>"+style+"</style><div class=\'lpl-preview-layer lpl-preview-layer-active\' id=\'lpl-preview-layer-"+layer_id+"\'>"+content+"</div>";
						jQuery(".lpl-preview-content").append(layer);
					}
				}
				function lpl_2hex(c) {
					var hex = c.toString(16);
					return hex.length == 1 ? "0" + hex : hex;
				}
				function lpl_rgb2hex(r, g, b) {
					return "#" + lpl_2hex(r) + lpl_2hex(g) + lpl_2hex(b);
				}
				function lpl_hex2rgb(hex) {
					var shorthandRegex = /^#?([a-f\d])([a-f\d])([a-f\d])$/i;
					hex = hex.replace(shorthandRegex, function(m, r, g, b) {
						return r + r + g + g + b + b;
					});
					var result = /^#?([a-f\d]{2})([a-f\d]{2})([a-f\d]{2})$/i.exec(hex);
					return result ? {
						r: parseInt(result[1], 16),
						g: parseInt(result[2], 16),
						b: parseInt(result[3], 16)
					} : false;
				}
				function lpl_inarray(needle, haystack) {
					var length = haystack.length;
					for(var i = 0; i < length; i++) {
						if(haystack[i] == needle) return true;
					}
					return false;
				}
				function lpl_self_close() {
					return false;
				}
				lpl_build_preview();
				var lpl_keyuprefreshtimer;
				jQuery(document).ready(function(){
					jQuery(".lpl-color").wpColorPicker({
						change: function(event, ui) {
							setTimeout(function(){lpl_build_preview();}, 300);
						},
						clear: function() {lpl_build_preview();}
					});
					jQuery("input, select, textarea").bind("change", function() {
						clearTimeout(lpl_keyuprefreshtimer);
						lpl_build_preview();
					});
					jQuery(\'input[type="checkbox"]\').bind("click", function() {
						lpl_build_preview();
					});
					jQuery("input, select, textarea").bind("keyup", function() {
						clearTimeout(lpl_keyuprefreshtimer);
						lpl_keyuprefreshtimer = setTimeout(function(){lpl_build_preview();}, 1000);
					});
				});
			</script>
		</div>
		<div class="lpl_legend">
			<strong>Legend:</strong>
			<p><img src="'.plugins_url('/images/copy.png', __FILE__).'" alt="'.__('Duplicate layer', 'lpl').'" border="0"> '.__('Duplicate layer', 'lpl').'</p>
			<p><img src="'.plugins_url('/images/edit.png', __FILE__).'" alt="'.__('Edit layer details', 'lpl').'" border="0"> '.__('Edit layer details', 'lpl').'</p>
			<p><img src="'.plugins_url('/images/delete.png', __FILE__).'" alt="'.__('Delete layer', 'lpl').'" border="0"> '.__('Delete layer', 'lpl').'</p>
		</div>
<div id="lpl-layers-item-container" style="display: none;">
	<div class="lpl-layers-item-cell lpl-layers-item-cell-info">
		<h4></h4>
		<p></p>
	</div>
	<div class="lpl-layers-item-cell" style="width: 70px;">
		<a href="#" title="'.__('Edit layer details', 'lpl').'" onclick="return lpl_edit_layer(this);"><img src="'.plugins_url('/images/edit.png', __FILE__).'" alt="'.__('Edit layer details', 'lpl').'" border="0"></a>
		<a href="#" title="'.__('Duplicate layer', 'lpl').'" onclick="return lpl_copy_layer(this);"><img src="'.plugins_url('/images/copy.png', __FILE__).'" alt="'.__('Duplicate details', 'lpl').'" border="0"></a>
		<a href="#" title="'.__('Delete layer', 'lpl').'" onclick="return lpl_delete_layer(this);"><img src="'.plugins_url('/images/delete.png', __FILE__).'" alt="'.__('Delete layer', 'lpl').'" border="0"></a>
	</div>
</div>
<div id="lpl-layer-options-container" style="display: none;">
	<div class="lpl-layer-options">
		<div class="lpl-layer-row">
			<div class="lpl-layer-property">
				<label>'.__('Layer title', 'lpl').'</label>
				<input type="text" id="lpl_layer_title" name="lpl_layer_title" value="" class="widefat" placeholder="Enter the layer title...">
				<br /><em>'.__('Enter the layer title. It is used for your reference.', 'lpl').'</em>
			</div>
		</div>
		<div class="lpl-layer-row">
			<div class="lpl-layer-property">
				<label>'.__('Layer content', 'lpl').'</label>
				<textarea id="lpl_layer_content" name="lpl_layer_content" class="widefat" placeholder="Enter the layer content..."></textarea>
				<br /><em>'.__('Enter the layer content. HTML-code allowed.', 'lpl').'</em>
			</div>
		</div>
		<div class="lpl-layer-row">
			<div class="lpl-layer-property">
				<label>'.__('Layer size', 'lpl').'</label>
				<input type="text" id="lpl_layer_width" name="lpl_layer_width" value="" class="ic_input_number" placeholder="Width"> x
				<input type="text" id="lpl_layer_height" name="lpl_layer_height" value="" class="ic_input_number" placeholder="Height"> pixels
				<br /><em>'.__('Enter the layer size, width x height. Leave both or one field empty for auto calculation.', 'lpl').'</em>
			</div>
			<div class="lpl-layer-property">
				<label>'.__('Scrollbar', 'lpl').'</label>
				<input type="checkbox" id="lpl_layer_scrollbar" name="lpl_layer_scrollbar"> '.__('Add scrollbar', 'lpl').'
				<br /><em>'.__('Add scrollbar to the layer. Layer height must be set.', 'lpl').'</em>
			</div>
			<div class="lpl-layer-property">
				<label>'.__('Left position', 'lpl').'</label>
				<input type="text" id="lpl_layer_left" name="lpl_layer_left" value="" class="ic_input_number" placeholder="Left"> pixels
				<br /><em>'.__('Enter the layer left position relative basic frame left edge.', 'lpl').'</em>
			</div>
			<div class="lpl-layer-property">
				<label>'.__('Top position', 'lpl').'</label>
				<input type="text" id="lpl_layer_top" name="lpl_layer_top" value="" class="ic_input_number" placeholder="Top"> pixels
				<br /><em>'.__('Enter the layer top position relative basic frame top edge.', 'lpl').'</em>
			</div>
			<div class="lpl-layer-property">
				<label>'.__('Content alignment', 'lpl').'</label>
				<select class="ic_input_s" id="lpl_layer_content_align" name="lpl_layer_content_align">';
			foreach ($this->alignments as $key => $value) {
				echo '
					<option value="'.$key.'">'.esc_attr($value).'</option>';
			}
			echo '
				</select>
				<br /><em>'.__('Set the horizontal content alignment.', 'lpl').'</em>
			</div>
		</div>
		<div class="lpl-layer-row">
			<div class="lpl-layer-property" style="width: 25%;">
				<label>'.__('Appearance', 'lpl').'</label>
				<select class="ic_input_s" id="lpl_layer_appearance" name="lpl_layer_appearance">';
			foreach ($this->appearances as $key => $value) {
				echo '
					<option value="'.$key.'">'.esc_attr($value).'</option>';
			}
			echo '
				</select>
				<br /><em>'.__('Set the layer appearance.', 'lpl').'</em>
			</div>
			<div class="lpl-layer-property" style="width: 25%;">
				<label>'.__('Start delay', 'lpl').'</label>
				<input type="text" id="lpl_layer_appearance_delay" name="lpl_layer_appearance_delay" value="" class="ic_input_number" placeholder="[0...10000]"> milliseconds
				<br /><em>'.__('Set the appearance start delay. The value must be in a range [0...1].', 'lpl').'</em>
			</div>
			<div class="lpl-layer-property" style="width: 25%;">
				<label>'.__('Duration speed', 'lpl').'</label>
				<input type="text" id="lpl_layer_appearance_speed" name="lpl_layer_appearance_speed" value="" class="ic_input_number" placeholder="[0...10000]"> milliseconds
				<br /><em>'.__('Set the duration speed in milliseconds.', 'lpl').'</em>
			</div>
			<div class="lpl-layer-property" style="width: 25%;">
				<label>'.__('Layer index', 'lpl').'</label>
				<input type="text" id="lpl_layer_index" name="lpl_layer_index" value="" class="ic_input_number" placeholder="[0...100]">
				<br /><em>'.__('Set the stack order of the layer. A layer with greater stack order is always in front of a layer with a lower stack order.', 'lpl').'</em>
			</div>
		</div>
		<div class="lpl-layer-row">
			<div class="lpl-layer-property" style="width: 270px;">
				<label>'.__('Background color', 'lpl').'</label>
				<input type="text" class="lpl-color ic_input_number" id="lpl_layer_background_color" name="lpl_layer_background_color" value="" placeholder="">
				<br /><em>'.__('Set the background color. Leave empty for transparent background.', 'lpl').'</em>
			</div>
			<div class="lpl-layer-property" style="width: 200px;">
				<label>'.__('Background opacity', 'lpl').'</label>
				<input type="text" id="lpl_layer_background_opacity" name="lpl_layer_background_opacity" value="" class="ic_input_number" placeholder="[0...1]">
				<br /><em>'.__('Set the background opacity. The value must be in a range [0...1].', 'lpl').'</em>
			</div>
			<div class="lpl-layer-property">
				<label>'.__('Background image URL', 'lpl').'</label>
				<input type="text" id="lpl_layer_background_image" name="lpl_layer_background_image" value="" class="widefat" placeholder="Enter the background image URL...">
				<br /><em>'.__('Enter the background image URL.', 'lpl').'</em>
			</div>
		</div>
		<div class="lpl-layer-row">
			<div class="lpl-layer-property" style="width: 230px;">
				<label>'.__('Font', 'lpl').'</label>
				<select class="ic_input_m" id="lpl_layer_font" name="lpl_layer_font">
					<option disabled="disabled">------ LOCAL FONTS ------</option>';
			foreach ($this->local_fonts as $key => $value) {
				echo '
					<option value="'.$key.'">'.esc_attr($value).'</option>';
			}
			echo '
				</select>
				<br /><em>'.__('Select the font.', 'lpl').'</em>
			</div>
			<div class="lpl-layer-property" style="width: 270px;">
				<label>'.__('Font color', 'lpl').'</label>
				<input type="text" class="lpl-color ic_input_number" id="lpl_layer_font_color" name="lpl_layer_font_color" value="" placeholder="">
				<br /><em>'.__('Set the font color.', 'lpl').'</em>
			</div>
			<div class="lpl-layer-property" style="width: 25%;">
				<label>'.__('Font size', 'lpl').'</label>
				<input type="text" id="lpl_layer_font_size" name="lpl_layer_font_size" value="" class="ic_input_number" placeholder="Font size"> pixels
				<br /><em>'.__('Set the font size. The value must be in a range [10...64].', 'lpl').'</em>
			</div>
			<div class="lpl-layer-property" style="width: 25%;">
				<label>'.__('Font weight', 'lpl').'</label>
				<select class="ic_input_s" id="lpl_layer_font_weight" name="lpl_layer_font_weight">';
			foreach ($this->font_weights as $key => $value) {
				echo '
					<option value="'.$key.'">'.esc_attr($key.' - '.$value).'</option>';
			}
			echo '
				</select>
				<br /><em>'.__('Select the font weight. Some fonts may not support selected font weight.', 'lpl').'</em>
			</div>
		</div>
		<div class="lpl-layer-row">
			<div class="lpl-layer-property" style="width: 200px;">
				<label>'.__('Text shadow size', 'lpl').'</label>
				<input type="text" id="lpl_layer_text_shadow_size" name="lpl_layer_text_shadow_size" value="" class="ic_input_number" placeholder="Shadow size"> pixels
				<br /><em>'.__('Set the text shadow size.', 'lpl').'</em>
			</div>
			<div class="lpl-layer-property" style="width: 270px;">
				<label>'.__('Text shadow color', 'lpl').'</label>
				<input type="text" class="lpl-color ic_input_number" id="lpl_layer_text_shadow_color" name="lpl_layer_text_shadow_color" value="" placeholder="">
				<br /><em>'.__('Set the text shadow color.', 'lpl').'</em>
			</div>
			<div class="lpl-layer-property">
				<label>'.__('Custom style', 'lpl').'</label>
				<input type="text" id="lpl_layer_style" name="lpl_layer_style" value="" class="widefat" placeholder="Enter the custom style string...">
				<br /><em>'.__('Enter the custom style string. This value is added to layer <code>style</code> attribute.', 'lpl').'</em>
			</div>
		</div>
		<div class="lpl-layer-row">
			<div class="lpl-layer-property">
				<input type="hidden" name="action" value="lpl_save_layer">
				<input type="hidden" name="lpl_layer_id" value="0">
				<input type="button" class="lpl-button button-primary" name="submit" value="'.__('Save Layer', 'lpl').'" onclick="return lpl_save_layer();">
				<img class="lpl-loading" src="'.plugins_url('/images/loading.gif', __FILE__).'">
			</div>
			<div class="lpl-layer-property" style="text-align: right;">
				<input type="button" class="lpl-button button-secondary" name="submit" value="'.__('Cancel', 'lpl').'" onclick="return lpl_cancel_layer(this);">
			</div>
		</div>
		<div class="lpl-message"></div>
	</div>
</div>';
	}

	function save_popup() {
		global $wpdb;
		$popup_options = array();
		if (current_user_can('manage_options')) {
			foreach ($this->default_popup_options as $key => $value) {
				if (isset($_POST['lpl_'.$key])) {
					$popup_options[$key] = stripslashes(trim($_POST['lpl_'.$key]));
				}
			}
			if (isset($_POST["lpl_enable_close"])) $popup_options['enable_close'] = "on";
			else $popup_options['enable_close'] = "off";
			
			$popup_details = unserialize($this->options['popup']);

			$errors = array();
			
			$layers = $wpdb->get_row("SELECT * FROM ".$wpdb->prefix."lpl_layers WHERE deleted = '0'", ARRAY_A);
			if (!$layers) $errors[] = __('Create at least one layer.', 'lpl');
			if (strlen($popup_options['width']) > 0 && $popup_options['width'] != preg_replace('/[^0-9]/', '', $popup_options['width'])) $errors[] = __('Invalid popup basic width.', 'lpl');
			if (strlen($popup_options['height']) > 0 && $popup_options['height'] != preg_replace('/[^0-9]/', '', $popup_options['height'])) $errors[] = __('Invalid popup basic height.', 'lpl');
			if (strlen($popup_options['overlay_color']) > 0 && $this->get_rgb($popup_options['overlay_color']) === false) $errors[] = __('Ovarlay color must be a valid value.', 'lpl');
			if (floatval($popup_options['overlay_opacity']) < 0 || floatval($popup_options['overlay_opacity']) > 1) $errors[] = __('Overlay opacity must be in a range [0...1].', 'lpl');

			if (!empty($errors)) {
				$return_object = array();
				$return_object['status'] = 'ERROR';
				$return_object['message'] = __('Attention! Please correct the errors below and try again.', 'lpl').'<ul><li>'.implode('</li><li>', $errors).'</li></ul>';
				echo json_encode($return_object);
				exit;
			}
			
			$popup_details['width'] = intval($popup_options['width']);
			$popup_details['height'] = intval($popup_options['height']);
			$popup_details['options'] = serialize($popup_options);
			
			$this->options['popup'] = serialize($popup_details);
			$this->update_options();

			setcookie("lpl_info", __('Popup details successfully <strong>saved</strong>.', 'lpl'), time()+30, "/", ".".str_replace("www.", "", $_SERVER["SERVER_NAME"]));
			
			$return_object = array();
			$return_object['status'] = 'OK';
			$return_object['return_url'] = admin_url('admin.php').'?page=lpl-edit';
			echo json_encode($return_object);
			exit;
		}
	}
	
	function save_layer() {
		global $wpdb;
		$layer_options = array();
		if (current_user_can('manage_options')) {
			foreach ($this->default_layer_options as $key => $value) {
				if (isset($_POST['lpl_layer_'.$key])) {
					$layer_options[$key] = stripslashes(trim($_POST['lpl_layer_'.$key]));
				}
			}
			if (isset($_POST['lpl_layer_scrollbar'])) $layer_options['scrollbar'] = 'on';
			else $layer_options['scrollbar'] = 'off';
			if (isset($_POST['lpl_layer_id'])) $layer_id = intval($_POST['lpl_layer_id']);
			else $layer_id = 0;

			$popup_details = unserialize($this->options['popup']);
			
			$errors = array();
			if (strlen($layer_options['title']) < 1) $errors[] = __('Layer title is too short.', 'lpl');
			if (strlen($layer_options['width']) > 0 && $layer_options['width'] != preg_replace('/[^0-9]/', '', $layer_options['width'])) $errors[] = __('Invalid layer width.', 'lpl');
			if (strlen($layer_options['height']) > 0 && $layer_options['height'] != preg_replace('/[^0-9]/', '', $layer_options['height'])) $errors[] = __('Invalid layer height.', 'lpl');
			if (strlen($layer_options['left']) == 0 || $layer_options['left'] != preg_replace('/[^0-9\-]/', '', $layer_options['left'])) $errors[] = __('Invalid left position.', 'lpl');
			if (strlen($layer_options['top']) == 0 || $layer_options['top'] != preg_replace('/[^0-9\-]/', '', $layer_options['top'])) $errors[] = __('Invalid top position.', 'lpl');
			if (strlen($layer_options['background_color']) > 0 && $this->get_rgb($layer_options['background_color']) === false) $errors[] = __('Background color must be a valid value.', 'lpl');
			if (floatval($layer_options['background_opacity']) < 0 || floatval($layer_options['background_opacity']) > 1) $errors[] = __('Background opacity must be in a range [0...1].', 'lpl');
			if (strlen($layer_options['background_image']) > 0 && !preg_match('|^http(s)?://[a-z0-9-]+(.[a-z0-9-]+)*(:[0-9]+)?(/.*)?$|i', $layer_options['background_image'])) $errors[] = __('Background image URL must be a valid URL.', 'lpl');
			if (strlen($layer_options['index']) > 0 && $layer_options['index'] != preg_replace('/[^0-9]/', '', $layer_options['index']) && $layer_options['index'] > 100) $errors[] = __('Layer index must be in a range [0...100].', 'lpl');
			if (strlen($layer_options['appearance_delay']) > 0 && $layer_options['appearance_delay'] != preg_replace('/[^0-9]/', '', $layer_options['appearance_delay']) && $layer_options['appearance_delay'] > 10000) $errors[] = __('Appearance start delay must be in a range [0...10000].', 'lpl');
			if (strlen($layer_options['appearance_speed']) > 0 && $layer_options['appearance_speed'] != preg_replace('/[^0-9]/', '', $layer_options['appearance_speed']) && $layer_options['appearance_speed'] > 10000) $errors[] = __('Appearance duration speed must be in a range [0...10000].', 'lpl');
			if (strlen($layer_options['font_color']) > 0 && $this->get_rgb($layer_options['font_color']) === false) $errors[] = __('Font color must be a valid value.', 'lpl');
			if (strlen($layer_options['font_size']) > 0 && $layer_options['font_size'] != preg_replace('/[^0-9]/', '', $layer_options['font_size']) && ($layer_options['font_size'] > 72 || $layer_options['font_size'] < 10)) $errors[] = __('Font size must be in a range [10...72].', 'lpl');
			if (strlen($layer_options['text_shadow_color']) > 0 && $this->get_rgb($layer_options['text_shadow_color']) === false) $errors[] = __('Text shadow color must be a valid value.', 'lpl');
			if (strlen($layer_options['text_shadow_size']) > 0 && $layer_options['text_shadow_size'] != preg_replace('/[^0-9]/', '', $layer_options['text_shadow_size']) && $layer_options['text_shadow_size'] > 72) $errors[] = __('Text shadow size must be in a range [0...72].', 'lpl');

			if (!empty($errors)) {
				$return_object = array();
				$return_object['status'] = 'ERROR';
				$return_object['message'] = __('Attention! Please correct the errors below and try again.', 'lpl').'<ul><li>'.implode('</li><li>', $errors).'</li></ul>';
				echo json_encode($return_object);
				exit;
			}
			
			if ($layer_id > 0) $layer_details = $wpdb->get_row("SELECT * FROM ".$wpdb->prefix."lpl_layers WHERE id = '".$layer_id."' AND deleted = '0'", ARRAY_A);
			if (!empty($layer_details)) {
				$sql = "UPDATE ".$wpdb->prefix."lpl_layers SET
					title = '".mysql_real_escape_string($layer_options['title'])."',
					content = '".mysql_real_escape_string($layer_options['content'])."',
					zindex = '".mysql_real_escape_string($layer_options['index'])."',
					details = '".mysql_real_escape_string(serialize($layer_options))."'
					WHERE id = '".$layer_id."'";
				$wpdb->query($sql);
			} else {
				$sql = "INSERT INTO ".$wpdb->prefix."lpl_layers (
					title, content, zindex, details, created, deleted) VALUES (
					'".mysql_real_escape_string($layer_options['title'])."',
					'".mysql_real_escape_string($layer_options['content'])."',
					'".mysql_real_escape_string($layer_options['index'])."',
					'".mysql_real_escape_string(serialize($layer_options))."',
					'".time()."', '0')";
				$wpdb->query($sql);
				$layer_id = $wpdb->insert_id;
			}
			
			$return_object = array();
			$return_object['status'] = 'OK';
			$return_object['title'] = esc_attr($layer_options['title']);
			if (strlen($layer_options['content']) == 0) $content = 'No content...';
			else if (strlen($layer_options['content']) > 192) $content = substr($layer_options['content'], 0, 180).'...';
			else $content = $layer_options['content'];
			$return_object['content'] = esc_attr($content);
			$layer_options_html = '';
			foreach ($layer_options as $key => $value) {
				$layer_options_html .= '<input type="hidden" id="lpl_layer_'.$layer_id.'_'.$key.'" name="lpl_layer_'.$layer_id.'_'.$key.'" value="'.esc_attr($value).'">';
			}
			$return_object['options_html'] = $layer_options_html;
			$return_object['layer_id'] = $layer_id;
			echo json_encode($return_object);
			exit;
		}
	}

	function copy_layer() {
		global $wpdb;
		if (current_user_can('manage_options')) {
			if (isset($_POST['lpl_layer_id'])) $layer_id = intval($_POST['lpl_layer_id']);
			else $layer_id = 0;
			$layer_details = $wpdb->get_row("SELECT * FROM ".$wpdb->prefix."lpl_layers WHERE id = '".$layer_id."' AND deleted = '0'", ARRAY_A);
			if (empty($layer_details)) {
				$return_object = array();
				$return_object['status'] = 'ERROR';
				$return_object['message'] = __('Layer not found!', 'lpl');
				echo json_encode($return_object);
				exit;
			}
			$layer_options = unserialize($layer_details['details']);
			$sql = "INSERT INTO ".$wpdb->prefix."lpl_layers (
				title, content, zindex, details, created, deleted) VALUES (
				'".mysql_real_escape_string($layer_details['title'])."',
				'".mysql_real_escape_string($layer_details['content'])."',
				'".mysql_real_escape_string($layer_details['zindex'])."',
				'".mysql_real_escape_string($layer_details['details'])."',
				'".time()."', '0')";
			$wpdb->query($sql);
			$layer_id = $wpdb->insert_id;
			$return_object = array();
			$return_object['status'] = 'OK';
			$return_object['title'] = esc_attr($layer_options['title']);
			if (strlen($layer_options['content']) == 0) $content = 'No content...';
			else if (strlen($layer_options['content']) > 192) $content = substr($layer_options['content'], 0, 180).'...';
			else $content = $layer_options['content'];
			$return_object['content'] = esc_attr($content);
			$layer_options_html = '';
			foreach ($layer_options as $key => $value) {
				$layer_options_html .= '<input type="hidden" id="lpl_layer_'.$layer_id.'_'.$key.'" name="lpl_layer_'.$layer_id.'_'.$key.'" value="'.esc_attr($value).'">';
			}
			$return_object['options_html'] = $layer_options_html;
			$return_object['layer_id'] = $layer_id;
			echo json_encode($return_object);
			exit;
		}
		exit;
	}
	
	function delete_layer() {
		global $wpdb;
		if (current_user_can('manage_options')) {
			if (isset($_POST['lpl_layer_id'])) $layer_id = intval($_POST['lpl_layer_id']);
			else $layer_id = 0;
			$sql = "UPDATE ".$wpdb->prefix."lpl_layers SET deleted = '1' WHERE id = '".$layer_id."'";
			$wpdb->query($sql);
		}
		exit;
	}

	function admin_request_handler() {
		global $wpdb;
		if (!empty($_GET['action'])) {
			switch($_GET['action']) {
			
				default:
					break;
			}
		}
	}

	function front_init() {
		global $wpdb, $post;
		if (class_exists('ulp_class')) return;
		$style = '';
		if (isset($_GET['lpl'])) $preview = true;
		else $preview = false;
		$popup = unserialize($this->options['popup']);
		//foreach ($popups as $popup) {
			$popup_options = unserialize($popup['options']);
			$popup_options = array_merge($this->default_popup_options, $popup_options);
			$style .= '#lpl-overlay{background:'.(!empty($popup_options['overlay_color']) ? $popup_options['overlay_color'] : 'transparent').';opacity:'.$popup_options['overlay_opacity'].';-ms-filter:"progid:DXImageTransform.Microsoft.Alpha(Opacity=\''.intval(100*$popup_options['overlay_opacity']).'\')";filter:alpha(opacity="'.intval(100*$popup_options['overlay_opacity']).'");}';
			$this->front_footer .= '
				<div class="lpl-overlay" id="lpl-overlay"></div>
				<div class="lpl-window" id="lpl" data-width="'.$popup['width'].'" data-height="'.$popup['height'].'" data-close="'.$popup_options['enable_close'].'">
					<div class="lpl-content">';
			$layers = $wpdb->get_results("SELECT * FROM ".$wpdb->prefix."lpl_layers WHERE deleted = '0'", ARRAY_A);
			foreach ($layers as $layer) {
				$layer_options = unserialize($layer['details']);
				$content = $layer['content'];
				$content = do_shortcode($content);
				$base64 = false;
				if (strpos(strtolower($content), '<iframe') !== false) {
					$base64 = true;
					$content = base64_encode($content);
				}
				$this->front_footer .= '
						<div class="lpl-layer" id="lpl-layer-'.$layer['id'].'" data-left="'.$layer_options['left'].'" data-top="'.$layer_options['top'].'" data-appearance="'.$layer_options['appearance'].'" data-appearance-speed="'.$layer_options['appearance_speed'].'" data-appearance-delay="'.$layer_options['appearance_delay'].'"'.(!empty($layer_options['width']) ? ' data-width="'.$layer_options['width'].'"' : '').(!empty($layer_options['height']) ? ' data-height="'.$layer_options['height'].'"' : '').' data-font-size="'.$layer_options['font_size'].'"'.($base64 ? ' data-base64="yes"' : '').' '.(!empty($layer_options['scrollbar']) ? ' data-scrollbar="'.$layer_options['scrollbar'].'"' : ' data-scrollbar="off"').'>'.$content.'</div>';
				if (!empty($layer_options['background_color'])) {
					$rgb = $this->get_rgb($layer_options['background_color']);
					$background = 'background-color:'.$layer_options['background_color'].';background-color:rgba('.$rgb['r'].','.$rgb['g'].','.$rgb['b'].','.$layer_options['background_opacity'].');';
				} else $background = '';
				if (!empty($layer_options['background_image'])) {
					$background .= 'background-image:url('.$layer_options['background_image'].');background-repeat:repeat;';
				}
				$font = "font-family:'".$layer_options['font']."', arial;font-weight:".$layer_options['font_weight'].";color:".$layer_options['font_color'].";".($layer_options['text_shadow_size'] > 0 && !empty($layer_options['text_shadow_color']) ? "text-shadow: ".$layer_options['text_shadow_color']." ".$layer_options['text_shadow_size']."px ".$layer_options['text_shadow_size']."px ".$layer_options['text_shadow_size']."px;" : "");
				$style .= '#lpl-layer-'.$layer['id'].',#lpl-layer-'.$layer['id'].' p,#lpl-layer-'.$layer['id'].' a,#lpl-layer-'.$layer['id'].' span,#lpl-layer-'.$layer['id'].' li,#lpl-layer-'.$layer['id'].' input,#lpl-layer-'.$layer['id'].' button,#lpl-layer-'.$layer['id'].' textarea {'.$font.'}';
				$style .= '#lpl-layer-'.$layer['id'].'{'.$background.'z-index:'.($layer_options['index']+1000002).';text-align:'.$layer_options['content_align'].';'.$layer_options['style'].'}';
				if (!array_key_exists($layer_options['font'], $this->local_fonts)) $layer_webfonts[] = $layer_options['font'];
			}
			$this->front_footer .= '
					</div>
				</div>';
		
		if ($preview) {
			$this->front_footer .= '
				<script>lpl_open();</script>';
		} else {
			$this->front_footer .= '
				<script>lpl_init();</script>';
		}
		$this->front_header .= '<style>'.$style.'</style>
		<script>
			var lpl_cookie_value = "'.$this->options['cookie_value'].'";
			var lpl_onload_mode = "'.$this->options['onload_mode'].'";
			var lpl_onload_delay = "'.intval($this->options['onload_delay']).'";
			var lpl_onload_close_delay = "'.intval($this->options['onload_close_delay']).'";';
		
		$this->front_header .= '
		</script>';
		add_action('wp_enqueue_scripts', array(&$this, 'front_enqueue_scripts'));
		add_action('wp_head', array(&$this, 'front_header'), 15);
		add_action('wp_footer', array(&$this, 'front_footer'), 999);
	}

	function front_enqueue_scripts() {
		wp_enqueue_script("jquery");
		wp_enqueue_style('lpl', plugins_url('/css/style.css', __FILE__), array(), LPL_VERSION);
		wp_enqueue_script('lpl', plugins_url('/js/script.js', __FILE__), array(), LPL_VERSION, true);
		wp_enqueue_style('perfect-scrollbar', plugins_url('/css/perfect-scrollbar-0.4.6.min.css', __FILE__), array(), LPL_VERSION);
		wp_enqueue_script('perfect-scrollbar', plugins_url('/js/perfect-scrollbar-0.4.6.with-mousewheel.min.js', __FILE__), array(), LPL_VERSION);
	}
	
	function front_header() {
		global $wpdb;
		echo $this->front_header;
	}

	function front_footer() {
		global $wpdb;
		echo $this->front_footer;
	}

	function get_rgb($_color) {
		if (strlen($_color) != 7 && strlen($_color) != 4) return false;
		$color = preg_replace('/[^#a-fA-F0-9]/', '', $_color);
		if (strlen($color) != strlen($_color)) return false;
		if (strlen($color) == 7) list($r, $g, $b) = array($color[1].$color[2], $color[3].$color[4], $color[5].$color[6]);
		else list($r, $g, $b) = array($color[1].$color[1], $color[2].$color[2], $color[3].$color[3]);
		return array("r" => hexdec($r), "g" => hexdec($g), "b" => hexdec($b));
	}

	function random_string($_length = 16) {
		$symbols = '123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
		$string = "";
		for ($i=0; $i<$_length; $i++) {
			$string .= $symbols[rand(0, strlen($symbols)-1)];
		}
		return $string;
	}
}
$lpl = new lpl_class();
?>