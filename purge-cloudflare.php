<?php

/**
 * @link              https://github.com/jaimelias/purge-cloudflare
 * @since             1.0.0
 * @package           purge-cloudflare
 *
 * @wordpress-plugin
 * Plugin Name:       Purge Cloudflare
 * Plugin URI:        https://github.com/jaimelias/purge-cloudflare
 * Description:       Speed-up your Wordpress site activating the CACHE EVERYTHING page rule in Cloudflare. With this plugin you can flush the content in Cloudflare directly from Wordpress each time you edit your pages, posts, categories, tags, taxonomies and custom post types.
 * Version:           1.0.0
 * Author:            jaimelias
 * Author URI:        https://jaimelias.com
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       purge-cloudflare
 * Domain Path:       /languages
 */

// If this file is called directly, abort.

if ( ! defined( 'WPINC' ) ) {
	die;
}
class Purge_Cloudflare
{
	function __construct()
	{
		if (isset($_SERVER['HTTP_CF_CONNECTING_IP']))
		{
			add_action('wp_update_nav_menu', array(&$this, 'purge_everything'), 10);
			add_action('save_post', array(&$this, 'save_post'), 10);
			add_action('edit_term', array(&$this, 'edit_term'), 10, 3);
			add_filter('wp_headers', array(&$this, 'headers'), 100);
			add_action('wp_ajax_is_user_logged_in', array(&$this, 'is_logged'));
			add_action('wp_ajax_nopriv_is_user_logged_in', array(&$this, 'is_logged'));
			add_action('wp_enqueue_scripts',  array(&$this, 'js'));
			add_action('admin_enqueue_scripts',  array(&$this, 'js'));
			add_action('wp_head', array(&$this, 'no_cache'), 1);
			add_filter('image_editor_save_pre', array(&$this, 'image'), 10, 3);
			add_action('admin_menu', array(&$this, 'add_menu'));
			add_action('admin_init', array(&$this, 'settings_page'));
			add_action('admin_notices', array(&$this, 'error_notice'));
		}
	}
	
	public static function error_notice()
	{
		if(isset($_GET['page']))
		{
			if($_GET['page'] == 'purge-cloudflare')
			{
				if(self::valid_credentials())
				{
					$valid = false;
					$zones = json_decode(self::getZones(), true);
					
					if(is_array($zones))
					{
						if(count($zones) > 0)
						{
							if(array_key_exists('success', $zones))
							{
								if($zones['success'] === true)
								{
									$valid = true;
								}
							}
						}
					}

					if($valid === false)
					{
						$class = 'notice notice-error';
						$message = 'Invalid Key or Global API Key';
						printf('<div class="%1$s"><p>%2$s</p></div>', esc_attr($class), esc_html($message)); 
					}
					else
					{
						if(get_option('cfp_debug') == 0)
						{
							file_put_contents(plugin_dir_path( __FILE__ ).'debug.log', '');	
						}
					}
				}
			}
		}
	}
	
	public static function valid_credentials()
	{
		global $cfp_valid_credentials;
		$output = false;
		
		if(isset($cfp_valid_credentials))
		{
			$output = $cfp_valid_credentials;
		}
		else
		{
			$email = get_option('cfp_email');
			$key = get_option('cfp_key');
			
			if($email != '' && $email != '')
			{
				$output = true;
				$GLOBALS['cfp_valid_credentials'] = $output;
			}			
		}
		
		return $output;
	}
	
	public static function settings_page()
	{
		
		add_settings_section(
			'cloudflare_purge_section', 
			esc_html('Cloudflare Purge'), 
			'', 
			'cfp_settings'
		);
		
		register_setting('cfp_settings', 'cfp_email', 'sanitize_email');
		register_setting('cfp_settings', 'cfp_key', 'sanitize_key');
		register_setting('cfp_settings', 'cfp_debug', 'sanitize_key');

		add_settings_field( 
			'cfp_email', 
			'Email', 
			array(&$this, 'input'), 
			'cfp_settings', 
			'cloudflare_purge_section', 
			array('email') 
		);	
		
		add_settings_field( 
			'cfp_key', 
			'Global API Key', 
			array(&$this, 'input'), 
			'cfp_settings', 
			'cloudflare_purge_section',
			array('key')
		);
		
		add_settings_field( 
			'cfp_debug', 
			'Debuggin', 
			array(&$this, 'select'), 
			'cfp_settings', 
			'cloudflare_purge_section',
			array('debug', esc_url(plugins_url('debug.log', __FILE__)))
		);
		
	}
	
	public static function input($arr)
	{
		$name = 'cfp_'.$arr[0];
		$value = get_option($name);
		$description = (isset($arr[1])) ? $arr[1] : null;
		?>
			<input name="<?php echo esc_html($name); ?>" id="<?php echo esc_html($name); ?>" value="<?php echo esc_html($value); ?>" />
			<?php if($description != null) : ?>
				<p class="description"><?php echo esc_html($description); ?></p>
			<?php endif; ?>		
		<?php
	}
	public static function select($arr)
	{
		$name = 'cfp_'.$arr[0];
		$value = get_option($name);
		$description = (isset($arr[1])) ? $arr[1] : null;

		?>
			<select name="<?php echo esc_html($name); ?>" id="<?php echo esc_html($name); ?>">
				<option value="0" <?php selected($value, "0"); ?> >No</option>
				<option value="1" <?php selected($value, "1"); ?> >Yes</option>
			</select> 
			<?php if($description != null) : ?>
				<p class="description"><?php echo esc_html($description); ?></p>
			<?php endif; ?>
		<?php		
	}
	
	public static function add_menu()
	{
        add_options_page(
            'Purge Cloudflare',
            'Purge Cloudflare',
            'manage_options',
            'purge-cloudflare',
            array(&$this, 'html_settings')
        );
	}
	
	public static function html_settings()
	{
		?>
		<div class="wrap">
		<form action='options.php' method='post'>
			
			<h1><?php esc_html('Purge Cloudflare'); ?></h1>	
			<?php
			settings_fields( 'cfp_settings' );
			do_settings_sections( 'cfp_settings' );
			submit_button();
			?>			
		</form>
		
		<?php
	}
	
	public static function no_cache()
	{
		if(!is_admin() && is_user_logged_in() && is_main_query() && !is_feed()):
		?>
		<meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate" />
		<meta http-equiv="Pragma" content="no-cache" />
		<meta http-equiv="Expires" content="0" />		
		<?php
		endif;
	}
	public static function headers($headers)
	{
		if(!is_admin() && is_user_logged_in() && is_main_query() && !is_feed())
		{
			$headers['Cache-Control'] = 'no-store, no-cache, must-revalidate, max-age=0';
			$headers['Pragma'] = 'no-cache';
		}
		return $headers;
	}
	
	public static function settings()
	{
		$output = array();
		$output['email'] = get_option('cfp_email');
		$output['key'] = get_option('cfp_key');
		return $output;
	}
	public static function edit_term($term_id, $tt_id, $taxonomy)
	{
		if(!current_user_can( 'edit_posts' )) return;
		
		global $wp_taxonomies;
		
		$excluded_tax = array('nav_menu', 'term_translations');
		
		if(isset($term_id) && isset($wp_taxonomies) && !in_array($taxonomy, $excluded_tax))
		{
			$queried_object = get_queried_object();
			$urls = array(get_term_link($term_id));
			$post_type = $wp_taxonomies[$taxonomy]->object_type;
			
			$args = array();
			$args['post_type'] = $post_type;
			$args['posts_per_page'] = 200;
			$args['status'] = 'publish';
			$args['tax_query'] = array();
			
			$tax_query = array(
				'taxonomy' => $taxonomy,
				'field' => 'term_id',
				'terms' => array($term_id)
				);
			
			array_push($args['tax_query'], $tax_query);
			
			$term_posts = get_posts($args);
			
			foreach($term_posts as $post)
			{
				array_push($urls, get_the_permalink($post->ID));
			}
			
			self::purge($urls);
			$GLOBALS['Purge_Cloudflare_O'] = true;
		}
	}
	public static function save_post($post_id)
	{
		if(defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
		if(! current_user_can( 'edit_post', $post_id ) ) return;
		//prevents to purge revisions during save_post
		if(wp_is_post_revision( $post_id )) return;
		//prevents duplicates
		if(isset($_GET['meta-box-loader'])) return;
		
		//prevent save from bulk
		if(isset($_GET['post_status'])) 
		{
			if($_GET['post_status'] != 'all')
			{
				return;
			}
		}
		
		if(isset($post_id))
		{
			self::purge(get_the_permalink($post_id));
			$GLOBALS['Purge_Cloudflare_O'] = true;
		}
	}
	
	public static function purge_everything()
	{
		self::purge();
		$GLOBALS['Purge_Cloudflare_O'] = true;
	}
	
	public static function purge($url = array())
	{
		global $Purge_Cloudflare_O;
		$cf_quota = 30;
		
		if(isset($Purge_Cloudflare_O)) return;
		
		$url = (is_string($url)) ? array($url) : $url;
		$zones = json_decode(self::getZones(1, 100), true);
		
		if(is_array($zones))
		{
			if(count($zones) > 0)
			{
				if(array_key_exists('success', $zones))
				{
					if($zones['success'] === true)
					{
						$result = $zones['result'];
						
						if(array_key_exists('result_info', $zones))
						{
							if(array_key_exists('total_pages', $zones['result_info']))
							{
								$z_pages  = $zones['result_info']['total_pages'];
								
								if($z_pages > 1)
								{
									for($zr = 0; $zr < $z_pages; $zr++)
									{
										$new_zones = json_decode(self::getZones(($zr+2), 100), true);
										
										if(array_key_exists('success', $new_zones))
										{
											if($new_zones['success'] === true)
											{
												$new_result = $new_zones['result'];
												
												if(is_array($new_result))
												{
													for($nr = 0; $nr < count($new_result); $nr++)
													{
														array_push($result, $new_result[$nr]);
													}													
												}
											}	
										}
									}
								}
							}
						}						

						if(is_array($result))
						{
							for($z = 0; $z < count($result); $z++)
							{
								if(array_key_exists('id', $result[$z]))
								{
									$zone_id = $result[$z]['id'];
									
									if(count($url) > 30)
									{
										$url = array_chunk($url, $cf_quota);
									}
									else
									{
										$url = array($url);
									}
									
									for($q = 0; $q < count($url); $q++)
									{
										$output = json_decode(self::curlPurge($url[$q], $zone_id), true);
										
										if(is_array($output))
										{
											if(count($output) > 0)
											{
												if(array_key_exists('success', $output))
												{
													if($output['success'] !== true)
													{
														self::debug('Response not success.');
													}
													else
													{
														if(is_array($url[$q]))
														{
															if(count($url[$q]) > 0)
															{
																self::debug(implode("\n", $url[$q]));
															}										
															else
															{
																self::debug('Purged Everthing');
															}	
														}
														else
														{
															self::debug('Purged Everthing');
														}
													}
												}
												else
												{
													self::debug('Response success key not found.');
												}
											}
											else
											{
												self::debug('Response is empty.');
											}
										}
										else
										{
											self::debug('Responce is not array.');
										}							
									}									
								}
								else
								{
									//error
									self::debug('Zone has no id.');
								}
							}						
						}
					}
				}				
			}
		}
	}
	
	public static function curlPurge($url = array(), $zone_id)
	{
		$settings = self::settings();
		$purge = 'https://api.cloudflare.com/client/v4/zones/'.$zone_id.'/purge_cache';
		$headers = array();
		
		array_push($headers, 'X-Auth-Email: '.$settings['email']);
		array_push($headers, 'X-Auth-Key: '.$settings['key']);
		array_push($headers, 'Content-Type: application/json');
		
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
		curl_setopt($ch, CURLOPT_URL, $purge);
		curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		
		$post_fields = array('purge_everything' => true);

		if(is_array($url))
		{
			if(count($url) > 0)
			{
				$post_fields = array('files' => $url);
			}
		}
		
		curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($post_fields));
		
		$output = curl_exec($ch);
		$info = curl_getinfo($ch);
		curl_close($ch);
		
		if($info['http_code'] == 200)
		{
			return $output;
		}
		
	}
	
	public static function getZones($page = 1, $per_page = 100)
	{
		if(self::valid_credentials())
		{
			$url = 'https://api.cloudflare.com/client/v4/zones?';
			$url_params = array();
			$url_params['name'] = $_SERVER['SERVER_NAME'];
			$url_params['status'] = 'active';
			$url_params['page'] = $page;
			$url_params['per_page'] = $per_page;
			$url_params['order'] = 'status';
			$url_params['direction'] = 'desc';
			$url_params['match'] = 'all';
			$url .= http_build_query($url_params);
			return self::curlGetZones($url);			
		}
	}
	public static function curlGetZones($url)
	{
		$settings = self::settings();
		$headers = array();
		array_push($headers, 'X-Auth-Email: '.$settings['email']);
		array_push($headers, 'X-Auth-Key: '.$settings['key']);
		array_push($headers, 'Content-Type: application/json');
		
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER , 1);
		$resp = curl_exec($ch);
		$info = curl_getinfo($ch);
		curl_close($ch);
		
		
		if($info['http_code'] == 200)
		{
			$resp = json_decode($resp, true);
			
			if(is_array($resp))
			{
				if(count($resp) > 0)
				{
					if(array_key_exists('success', $resp))
					{
						if($resp['success'] === true)
						{
							return json_encode($resp);
						}
					}
					
				}
			}			
		}
		
	}
	public static function date($format, $timestamp = null) {
		// This function behaves a bit like PHP's Date() function, but taking into account the Wordpress site's timezone
		// CAUTION: It will throw an exception when it receives invalid input - please catch it accordingly
		// From https://mediarealm.com.au/
		$tz_string = get_option('timezone_string');
		$tz_offset = get_option('gmt_offset', 0);
		if (!empty($tz_string)) {
		// If site timezone option string exists, use it
		$timezone = $tz_string;
		} elseif ($tz_offset == 0) {
		// get UTC offset, if it isn’t set then return UTC
		$timezone = 'UTC';
		} else {
		$timezone = $tz_offset;
		if(substr($tz_offset, 0, 1) != "-" && substr($tz_offset, 0, 1) != "+" && substr($tz_offset, 0, 1) != "U") {
		$timezone = "+" . $tz_offset;
		}
		}
		if($timestamp === null) {
		$timestamp = time();
		}
		$datetime = new DateTime();
		$datetime->setTimestamp($timestamp);
		$datetime->setTimezone(new DateTimeZone($timezone));
		return $datetime->format($format);
	}	
	public static function debug($log)
	{
		if(get_option('cfp_debug') == 1)
		{
			$output = "[".strval(self::date('Y-m-d H:i:s'))."] = ".esc_html(sanitize_text_field($_SERVER['REQUEST_URI']))."\n";
			$output .= $log."\n\n";
			file_put_contents(plugin_dir_path( __FILE__ ).'debug.log', $output, FILE_APPEND | LOCK_EX);			
		}
	}
	
	public static function js()
	{
		   wp_enqueue_script( 'ajax-script', plugin_dir_url(__FILE__) . 'history.js', array('jquery'), time());
		   
		   $args = array();
		   $args['ajax_url'] =  admin_url('admin-ajax.php');
		   
		   if(is_admin())
		   {
				$args['token'] =  wp_create_nonce('c_p_nonce');
		   }
		   
		   wp_localize_script( 'ajax-script', 'p_cf_ajax', $args);
	}
	
	public static function is_logged()
	{
		$output = array();
		
		$domain = substr(base64_encode($_SERVER['SERVER_NAME']), 0, 10);
		$token_name = 'p_cf_u_'. $domain;
		
		if(isset($_POST[$token_name]) && isset($_POST['url']))
		{
			$url = sanitize_text_field($_POST['url']);
			
			if(is_user_logged_in() && current_user_can('edit_posts') && wp_http_validate_url($url))
			{
				$output['is_logged'] = true;
				$output['nonce_verified'] = false;
				$output['valid_url'] = true; 
				
				if(wp_verify_nonce(sanitize_text_field($_POST[$token_name]), 'c_p_nonce'))
				{
					$url = esc_url($url);
					$hostname = parse_url($url);
					$hostname = $hostname['host'];
					
					if($hostname == $_SERVER['SERVER_NAME'])
					{
						$output['nonce_verified'] = true;
						self::purge($url);						
					}
					else
					{
						$output['valid_url'] = false; 
					}
				}
				else
				{
					$output['c_p_nonce_new'] = wp_create_nonce('c_p_nonce');
				}
			}			
		}

		wp_send_json($output);
	}
	
	public static function image($image, $image_id)
	{
		global $_wp_additional_image_sizes;

		if(isset($image_id))
		{
			if(intval($image_id) > 0)
			{
				$url = array();
				
				$image_sizes = get_intermediate_image_sizes();
				
				foreach($image_sizes as $k)
				{
					$image_url = wp_get_attachment_image_src($image_id, $k);
					array_push($url, $image_url[0]);
				}
				
				if(is_array($url))
				{
					if(count($url) > 0)
					{
						self::purge($url);
						$GLOBALS['Purge_Cloudflare_O'] = true;						
					}
				}
			}
		}
		
		return $image;
	}
}

$cloudflare_purge = new Purge_Cloudflare();

?>