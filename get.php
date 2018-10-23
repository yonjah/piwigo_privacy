<?php
// +-----------------------------------------------------------------------+
// | Piwigo-Privacy - keep your piwigo content private                     |
// +-----------------------------------------------------------------------+
// | Copyright(C) 2017 Yoni jah                                             |
// | Based on action.php file from Piwigo gallery                          |
// +-----------------------------------------------------------------------+
// | This program is free software; you can redistribute it and/or modify  |
// | it under the terms of the GNU General Public License as published by  |
// | the Free Software Foundation                                          |
// |                                                                       |
// | This program is distributed in the hope that it will be useful, but   |
// | WITHOUT ANY WARRANTY; without even the implied warranty of            |
// | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU      |
// | General Public License for more details.                              |
// |                                                                       |
// | You should have received a copy of the GNU General Public License     |
// | along with this program; if not, write to the Free Software           |
// | Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA 02111-1307, |
// | USA.                                                                  |
// +-----------------------------------------------------------------------+

defined('PHPWG_ROOT_PATH') or define('PHPWG_ROOT_PATH','../../');
session_cache_limiter('public');
include_once(PHPWG_ROOT_PATH.'include/common.inc.php');
include_once(PHPWG_ROOT_PATH . 'admin/include/image.class.php');
include_once('./helper_funcs.inc.php');

defined('PWG_DERIVATIVE_DIR') or define('PWG_DERIVATIVE_DIR', $conf['data_location'].'i/');

global $conf;

if (isset($conf['piwigo_privacy_redirect_header'])) {
	$full_path = filter_input(INPUT_SERVER, 'REQUEST_URI', FILTER_SANITIZE_URL);
	if (!$full_path) {
		pwg_privacy_reject_access('Could not get url path');
	}
	list($img_id, $req_path) = explode('/', ltrim($full_path, '/'), 2);
	if (!is_numeric($img_id) && $img_id == (int)$img_id ) {
		pwg_privacy_reject_access("Image id '$img_id' is not numeric $full_path");
	}
	$req_path = urldecode($req_path);
} else {
	$img_id = filter_input(INPUT_GET, 'img_id', FILTER_VALIDATE_INT);
	$req_path = filter_input(INPUT_GET, 'file', FILTER_UNSAFE_RAW);
}

//reject access if no id or path
if ( !$img_id || !$req_path ) {
	pwg_privacy_reject_access('Could not find image id or path');
}

if (strpos($req_path, 'i.php?') === 0) {
	$req_path = PWG_DERIVATIVE_DIR . substr($req_path, 6);
}

$allow_whitespaces = isset($conf['piwigo_privacy_allow_whitespaces']) && $conf['piwigo_privacy_allow_whitespaces'] === true;
$allow_special_chars = isset($conf['piwigo_privacy_allow_special_chars']) && $conf['piwigo_privacy_allow_special_chars'] === true;

$req_path = pwg_privacy_sanitize_path($req_path, $allow_whitespaces, $allow_special_chars);
if (!$req_path) {
	pwg_privacy_reject_access('Could not sanitize path');
}

$path = pwg_privacy_verify_access($img_id, $req_path);

if (!$path) {
	pwg_privacy_reject_access('Access was rejected');
}

pwg_privacy_serve_file($path);
