<?php
/*
Version: 1.0.1
Plugin Name: piwigo_privacy
Plugin URI: http://piwigo.org/ext/extension_view.php?eid=849
Author: Yoni Jah
Description: Make sure only secure access is allowed to your gallery images
Author URI: https://github.com/yonjah/piwigo_privacy
*/

// Check whether we are indeed included by Piwigo.
if (!defined('PHPWG_ROOT_PATH')) die('Hacking attempt!');
global $conf;

if ($conf['derivative_url_style'] !== 1) {
	global $page;
	$page['errors'][] = 'Piwigo privacy requires config derivative_url_style to be set to 1 but it is set to '.$conf['derivative_url_style'].'. Plugin has been disabled';
} else {
	add_event_handler('get_derivative_url', 'pwg_privacy_plugin_replace_derivative_url');
	add_event_handler('get_src_image_url', 'pwg_privacy_plugin_replace_url');
	add_event_handler('picture_pictures_data', 'pwg_privacy_plugin_replace_picture');
}

/**
* Allows us to replace the url (by changing rel_path) of an image without changing it's path
**/
class SrcImageProxy {
	private $image;
	private $orig_path;

	public function __construct(SrcImage $image) {
	    $this->image = $image;
		$this->orig_path = $image->get_path();
	}

	public function __get($name) {
	    return $this->image->$name;
	}

	public function __set($name, $value) {
	    return $this->image->$name = $value;
	}

	public function __call($method, $args) {
		if ($method === 'get_path') {
			return $this->orig_path;
		}
		return call_user_func_array(array($this->image, $method), $args);
	}
}

function pwg_privacy_plugin_replace_picture ($picture) {
	foreach ($picture as $key => &$image) {
		if (isset($image['element_url'])) {
			$image['element_url'] = pwg_privacy_modify_url($image['element_url'], $image['id']);
		}
		if (isset($image['src_image'])) {
			$image['src_image'] = new SrcImageProxy($image['src_image']);
			$image['src_image']->rel_path = pwg_privacy_modify_url($image['src_image']->rel_path, $image['src_image']->id);
		}
	}
	return $picture;
}

function pwg_privacy_plugin_replace_derivative_url($url, $params, $image) {
	$url = pwg_privacy_modify_url($url, $image->id);
	return $url;
}

function pwg_privacy_plugin_replace_url($url, $image) {
	$url = pwg_privacy_modify_url($url, $image->id);
	return $url;
}


function pwg_privacy_modify_url($url, $id) {
	global $conf;
	$root_url = get_root_url();

	if (strpos($url, 'action.php') !== false) {
		//were not changing action.php urls
		return $url;
	}

	if (!isset($conf['piwigo_privacy_redirect_header'])) {
		$image_prefix = PHPWG_PLUGINS_PATH.basename(dirname(__FILE__)).'/get.php?img_id='.$id.'&file=';
	} else {
		$image_prefix = $id . '/';
	}
	if ($root_url && strpos($url, $root_url) === 0) {
		$url = substr($url, strlen($root_url));
		return trim($root_url, '/') . '/' . $image_prefix . trim($url, '/');
	}
	
	// we already have image_prefix at the beginning and will not add it a second time
	if (strpos($url, $image_prefix) === 0) {
		return trim($url, '/');
	}
	
	return $image_prefix . trim($url, '/');
}
