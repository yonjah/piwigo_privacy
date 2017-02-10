<?php
// +-----------------------------------------------------------------------+
// | Piwigo-Privacy - keep your piwigo content private                     |
// +-----------------------------------------------------------------------+
// | Copyright(C) 2017 Yoni jah                                             |
// | Based on action.php and i.php file from Piwigo gallery                          |
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
function pwg_privacy_error($msg) {
	global $conf;
	if (isset($conf['piwigo_privacy_debug'])) {
		throw new Exception($msg);
	}
	return false;
}

function pwg_privacy_do_error( $code, $str ) {
	error_log($code . ' '.  $str . ' ' . filter_input(INPUT_SERVER, 'REMOTE_ADDR'));
	set_status_header( $code );
	echo $str ;
	exit();
}

function pwg_privacy_reject_access ($msg) {
	global $conf;
	// throw new Exception('No Access');
	pwg_privacy_error($msg);
	if ( isset($conf['piwigo_privacy_default_redirect']) && $conf['piwigo_privacy_default_redirect'] !== 'no' ) {
		set_status_header(301);
		if ($conf['piwigo_privacy_default_image']) {
			redirect($conf['piwigo_privacy_default_image']);
		} else {
			redirect(PHPWG_PLUGINS_PATH.basename(dirname(__FILE__)).'/default.png');
		}
	} else {
		pwg_privacy_do_error(401, 'Access Denied');
	}
	exit();
}

function pwg_privacy_serve_file ($path) {
	global $conf;

	$ext = get_extension($path);

	if (!in_array($ext, $conf['file_ext'])) {
		pwg_privacy_reject_access('File extension is not allowed');
	}

	$range_support = false;

	$ext = strtolower($ext);
	switch ($ext) {
		case "jpe": case "jpeg": case "jpg":
			$mime="image/jpeg";
			break;
		case "gif": case "png":
			$mime="image/"+$ext;
			break;
		case 'wmv': case 'mov': case 'mkv': case 'mp4': case 'mpg': case 'flv': case 'asf':
		case 'xvid': case 'divx': case 'mpeg': case 'avi': case 'rm': case 'm4v': case 'ogg':
		case 'ogv': case 'webm': case 'webmv':
			$mime="video/"+$ext;
			$range_support=true;
			break;
		default: $mime="application/octet-stream";
	}

	if (isset($conf['piwigo_privacy_redirect_header'])) {
		header('Content-type: '.$mime);
		header("{$conf['piwigo_privacy_redirect_header']}: /{$path}");
		return;
	}

	$path = PHPWG_ROOT_PATH.$path;

	if ($range_support) {
		return pwg_privacy_serve_range($path, $mime);
	}
	$fp = fopen($path, 'rb');

	$fstat = fstat($fp);
	header('Last-Modified: '.gmdate('D, d M Y H:i:s', $fstat['mtime']).' GMT');
	header('Content-length: '.$fstat['size']);
	header('Connection: close');
	header('Content-type: '.$mime);

	fpassthru($fp);
	fclose($fp);
	exit();
}


function pwg_privacy_serve_range($file, $mime) {
	//from https://gist.github.com/codler/3906826 with some minor modifications
	$fp = @fopen($file, 'rb');
	$fstat = fstat($fp);

	$size   = $fstat['size']; // File size
	$length = $size;           // Content length
	$start  = 0;               // Start byte
	$end    = $size - 1;       // End byte

	header('Last-Modified: '.gmdate('D, d M Y H:i:s', $fstat['mtime']).' GMT');
	header('Content-type: '.$mime);
	header("Accept-Ranges: bytes");

	if ( isset($_SERVER['HTTP_RANGE']) ) {
		$c_start = $start;
		$c_end   = $end;

		list(, $range) = explode('=', $_SERVER['HTTP_RANGE'], 2);
		if (strpos($range, ',') !== false) {
			header('HTTP/1.1 416 Requested Range Not Satisfiable');
			header("Content-Range: bytes $start-$end/$size");
			exit;
		}
		if ($range == '-') {
			$c_start = $size;
		} else {
			$range  = explode('-', $range);
			$c_start = is_numeric($range[0]) ? $range[0] : 0;
			$c_end   = (isset($range[1]) && is_numeric($range[1])) ? $range[1] : $size;
		}
		$c_end = ($c_end > $end) ? $end : $c_end;
		if ($c_start > $c_end || $c_start > $size - 1 || $c_end >= $size) {
			header('HTTP/1.1 416 Requested Range Not Satisfiable');
			header("Content-Range: bytes $start-$end/$size");
			exit;
		}
		$start  = $c_start;
		$end    = $c_end;
		$length = $end - $start + 1;
		fseek($fp, $start);
		header('HTTP/1.1 206 Partial Content');
	}

	header("Content-Range: bytes $start-$end/$size");
	header("Content-Length: ".$length);


	// set_time_limit(0);
	$buffer = 1024 * 8;
	while(!feof($fp) && ($p = ftell($fp)) <= $end) {

		if ($p + $buffer > $end) {
			$buffer = $end - $p + 1;
		}
		echo fread($fp, $buffer);
		flush();
	}

	fclose($fp);
	exit();
}


function pwg_privacy_verify_access ($img_id, $req_path) {
	check_status(ACCESS_GUEST);

	$query='
	SELECT `'.IMAGES_TABLE.'`.*
		FROM `'.CATEGORIES_TABLE.'`
		INNER JOIN `'.IMAGE_CATEGORY_TABLE.'` ON `category_id` = `'.CATEGORIES_TABLE.'`.`id`
		INNER JOIN `'.IMAGES_TABLE.'` ON image_id = `'.IMAGES_TABLE.'`.`id`
		WHERE image_id = '.$img_id.'
		'.get_sql_condition_FandF(
		array(
			'forbidden_categories' => 'category_id',
			'forbidden_images' => 'image_id',
		),
		'    AND'
		).'
		LIMIT 1
	;';

	$element_info = pwg_db_fetch_assoc(pwg_query($query));

	//file id does not exist or user has no rights to access it
	if ( empty($element_info) || !$element_info['path'] ) {
		return pwg_privacy_error('User has no access to image ' . $img_id);
	}

	if ( is_admin() ) {
		$is_admin_download = true;
		$user['enabled_high'] = true;
	}

	$path = $element_info['path'];
	if (strpos($path, './') !== 0) {
		$path = './' . ltrim($path, '/');
	}
	include_once(PHPWG_ROOT_PATH.'include/functions_picture.inc.php');

	//file is original uploaded image
	if ( strpos($req_path, $path) === 0 ) {
		if ($user['enabled_high']) {
			return $path;
		}
		$deriv = new DerivativeImage(IMG_XXLARGE, new SrcImage($element_info));
		if ( $deriv->same_as_source() ) {
			return $path;
		}
		return pwg_privacy_error('User has no high res access ' . $img_id);
	}

	//file is a representative
	if ( strpos($req_path, original_to_representative($path, '')) === 0 ) {
		return $req_path;
	}


	$base_file = embellish_url(get_filename_wo_extension(PWG_DERIVATIVE_DIR.$path));
	if (strpos($base_file, './') !== 0) {
		$base_file = './' . ltrim($base_file, '/');
	}
	//file is a derivative of original image
	if (strpos($req_path, $base_file) === 0) {
		return pwg_privacy_generate_derivative($element_info, $req_path);
	}

	$base_file = dirname($base_file).'/pwg_representative/'.basename($base_file);
	//file is a representative
	if (strpos($req_path, $base_file) === 0) {
		$element_info['path'] = get_filename_wo_extension(dirname($path).'/pwg_representative/'.basename($path)) . '.'. $element_info['representative_ext'];
		return pwg_privacy_generate_derivative($element_info, $req_path);
	}

	return pwg_privacy_error("Could not validate path ($req_path) actually belong to image ($img_id)");
}


/**
 * Make sure derivative exists and return it's path
 * Based on code from piwigo i.php
 */
function pwg_privacy_generate_derivative ($element_info, $req_path) {
	global $conf;
	global $prefixeTable;

	$derivative = pwg_privacy_parse_derivative($req_path);
	if (!$derivative) {
		return pwg_privacy_error('Could not parse derivative ' . $req_path);
	}

	$src_rel_path = $element_info['path'];
	$ext = get_extension($src_rel_path);
	$src_path = PHPWG_ROOT_PATH.$src_rel_path;
	$derivative_rel_path = get_filename_wo_extension(PWG_DERIVATIVE_DIR.$src_rel_path);
	$derivative_rel_path .= '-'.$derivative[0].'.'.$ext;
	$derivative_path = PHPWG_ROOT_PATH.$derivative_rel_path;

	$need_generate = false;
	$src_mtime = @filemtime($src_path);
	if (!$src_mtime) {
		return pwg_privacy_error('Could not find src image for derivative ' . $src_path);
	}
	$type = $derivative[1];
	$params = $derivative[2];

	$derivative_mtime = @filemtime($derivative_path);
	if ($derivative_mtime !== false && $derivative_mtime >= $src_mtime && $derivative_mtime >= $params->last_mod_time) {
		return $derivative_rel_path;
	}

	$coi = null;
	if (isset($element_info['width'])) {
		$src_size = array($element_info['width'], $element_info['height']);
	}
	$coi = $element_info['coi'];


	if (!isset($element_info['rotation'])) {
		$src_rotation_angle = pwg_image::get_rotation_angle($src_path);

		single_update(
			$prefixeTable.'images',
			array('rotation' => pwg_image::get_rotation_code_from_angle($src_rotation_angle)),
			array('id' => $element_info['id'])
		);
	} else {
		$src_rotation_angle = pwg_image::get_rotation_angle_from_code($element_info['rotation']);
	}

	if (!mkgetdir(dirname($derivative_path))) {
		return pwg_privacy_error('Could not create derivative path ' . $derivative_path);
	}

	ignore_user_abort(true);
	@set_time_limit(0);

	$image = new pwg_image($src_path);

	$changes = 0;

	// rotate
	if ($src_rotation_angle){
		$image->rotate($src_rotation_angle);
	}

	// Crop & scale
	$o_size = $d_size = array($image->get_width(),$image->get_height());
	$params->sizing->compute($o_size , $coi, $crop_rect, $scaled_size );
	if ($crop_rect) {
		$image->crop( $crop_rect->width(), $crop_rect->height(), $crop_rect->l, $crop_rect->t);
	}

	if ($scaled_size) {
		$image->resize( $scaled_size[0], $scaled_size[1] );
		$d_size = $scaled_size;
	}

	if ($params->sharpen) {
		$changes += $image->sharpen( $params->sharpen );
	}

	if ($params->will_watermark($d_size)) {
		$wm = ImageStdParams::get_watermark();
		$wm_image = new pwg_image(PHPWG_ROOT_PATH.$wm->file);
		$wm_size = array($wm_image->get_width(),$wm_image->get_height());
		if ($d_size[0]<$wm_size[0] or $d_size[1]<$wm_size[1])
		{
			$wm_scaling_params = SizingParams::classic($d_size[0], $d_size[1]);
			$wm_scaling_params->compute($wm_size, null, $tmp, $wm_scaled_size);
			$wm_size = $wm_scaled_size;
			$wm_image->resize( $wm_scaled_size[0], $wm_scaled_size[1] );
		}
		$x = round( ($wm->xpos/100)*($d_size[0]-$wm_size[0]) );
		$y = round( ($wm->ypos/100)*($d_size[1]-$wm_size[1]) );
		if ($image->compose($wm_image, $x, $y, $wm->opacity))
		{
			if ($wm->xrepeat || $wm->yrepeat)
			{
				$xpad = $wm_size[0] + max(30, round($wm_size[0]/4));
				$ypad = $wm_size[1] + max(30, round($wm_size[1]/4));

				for($i=-$wm->xrepeat; $i<=$wm->xrepeat; $i++)
				{
					for($j=-$wm->yrepeat; $j<=$wm->yrepeat; $j++)
					{
						if (!$i && !$j) continue;
						$x2 = $x + $i * $xpad;
						$y2 = $y + $j * $ypad;
						if ($x2>=0 && $x2+$wm_size[0]<$d_size[0] &&
								$y2>=0 && $y2+$wm_size[1]<$d_size[1] )
							if (!$image->compose($wm_image, $x2, $y2, $wm->opacity))
								break;
					}
				}
			}
		}
		$wm_image->destroy();
	}

	if ($d_size[0]*$d_size[1] < $conf['derivatives_strip_metadata_threshold']) {
		// strip metadata for small images
		$image->strip();
	}

	$image->set_compression_quality( ImageStdParams::$quality );
	$image->write( $derivative_path );
	$image->destroy();
	@chmod($derivative_path, 0644);
	return $derivative_rel_path;
}


/**
 * Parse derivative path
 * Based on code from piwigo i.php
 */
function pwg_privacy_parse_derivative ($req_path) {
	$pos = strrpos($req_path, '.');
	if ($pos === false) {
		return pwg_privacy_error('Could not parse derivative extension ' . $req_path);
	}
	$ext = substr($req_path, $pos);
	$req_path = substr($req_path, 0, $pos);
	$pos = strrpos($req_path, '-');
	if ($pos === false) {
		return pwg_privacy_error('Could not parse derivative path ' . $req_path);
	}
	$deriv = substr($req_path, $pos+1);
	$req_path = substr($req_path, 0, $pos);

	$deriv = explode('_', $deriv);
	foreach (ImageStdParams::get_defined_type_map() as $type => $params) {
		if ( derivative_to_url($type) == $deriv[0]) {
			$derivative_type = $type;
			$derivative_params = $params;
			break;
		}
	}

	if (!isset($derivative_type)) {
		if (derivative_to_url(IMG_CUSTOM) == $deriv[0]) {
			$derivative_type = IMG_CUSTOM;
		} else {
			return pwg_privacy_error('Could not find derivative ' . $req_path);
		}
	}

	$derivative_postfix = array_shift($deriv);

	if ($derivative_type == IMG_CUSTOM) {
		if (count($deriv)<1) {
			return pwg_privacy_error('Empty custom params ' . $req_path);
		}
		$derivative_params = pwg_privacy_parse_custom_params($deriv);
		if (!$derivative_params) {
			return pwg_privacy_error('Error parsing custom params ' . $req_path);
		}
		ImageStdParams::apply_global($derivative_params);

		if ($derivative_params->sizing->ideal_size[0] < 20 or $derivative_params->sizing->ideal_size[1] < 20) {
			return pwg_privacy_error('Invalid derivative custom size ' . $req_path);
		}
		if ($derivative_params->sizing->max_crop < 0 or $derivative_params->sizing->max_crop > 1) {
			return pwg_privacy_error('Invalid derivative crop size ' . $req_path);
		}
		$greatest = ImageStdParams::get_by_type(IMG_XXLARGE);

		$key = array();
		$derivative_params->add_url_tokens($key);
		$path_params = implode('_', $key);
		if (!isset(ImageStdParams::$custom[$path_params])) {
			return pwg_privacy_error('Derivative custom size not allowed' . $req_path);
		}
		$derivative_postfix .= '_'. $path_params;
	}

	return Array($derivative_postfix, $derivative_type, $derivative_params);
}

/**
 * Parse custom derivative params
 * Based on code from piwigo i.php
 */
function pwg_privacy_parse_custom_params($tokens) {
	$crop = 0;
	$min_size = null;

	$token = array_shift($tokens);
	if ($token[0]=='s') {
		$size = pwg_privacy_url_to_size(substr($token,1));
	}
	elseif ($token[0]=='e') {
		$crop = 1;
		$size = $min_size = pwg_privacy_url_to_size(substr($token,1));
	}
	else {
		if (count($tokens)<2) {
			return pwg_privacy_error('Custom params Sizing arr '. implode('_', $tokens));
		}
		$size = pwg_privacy_url_to_size($token);

		$token = array_shift($tokens);
		$crop = char_to_fraction($token);

		$token = array_shift($tokens);
		$min_size = pwg_privacy_url_to_size($token);
	}
	return new DerivativeParams( new SizingParams($size, $crop, $min_size) );
}

function pwg_privacy_url_to_size($s) {
	$pos = strpos($s, 'x');
	if ($pos === false) {
		return array((int)$s, (int)$s);
	}
	return array((int)substr($s,0,$pos), (int)substr($s,$pos+1));
}

function pwg_privacy_sanitize_path($path) {
	if ( preg_match('/\s|\.\./', $path) ) {
		return pwg_privacy_error('Path cannot contain .. or white spaces ' . $path);
	}
	$path = str_replace('//', '/', $path);
	$path = str_replace('/./', '/', $path);
	$path = ltrim($path, '/');
	if ( substr($path, 0, 2) !== './') {
		$path = './' . $path;
	}
	return $path;
}