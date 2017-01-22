<?php
// +-----------------------------------------------------------------------+
// | Piwigo-Privacy - keep your piwigo content private                     |
// +-----------------------------------------------------------------------+
// | Copyright(C) 2015 Yonijah                                             |
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
ini_set("log_errors", 1);
define('PHPWG_ROOT_PATH','./');
session_cache_limiter('public');
include_once(PHPWG_ROOT_PATH.'include/common.inc.php');

// Check Access and exit when user status is not ok
check_status(ACCESS_GUEST);

function do_error( $code, $str ) {
  error_log($code . ' '.  $str . ' ' . filter_input(INPUT_SERVER, 'REMOTE_ADDR'));
  set_status_header( $code );
  echo $str ;
  exit();
}

function get_file_from_path($path) {
  $match = [];
  preg_match('/\d{4}\/\d{2}\/\d{2}\/(pwg_representative\/)?(\d{14}-[0-9a-f]{8})/', $path, $match);
  return isset($match[2]) ? $match[2] : null;
}

$path = filter_input(INPUT_SERVER, 'REQUEST_URI', FILTER_SANITIZE_URL);
$file_part = get_file_from_path($path);


if (!$file_part) {
  do_error(400, 'Invalid request - path');
}

$query = 'SELECT * FROM '. IMAGES_TABLE.' WHERE path LIKE \'%'.
        pwg_db_real_escape_string($file_part).
        '%\' LIMIT 1;';

$element_info = pwg_db_fetch_assoc(pwg_query($query));
if ( empty($element_info) ) { //make sure reply is the same for forbidden and nonexisiting files
  do_error(401, 'Access denied');
}

// $filter['visible_categories'] and $filter['visible_images']
// are not used because it's not necessary (filter <> restriction)
$query='
SELECT id
  FROM '.CATEGORIES_TABLE.'
    INNER JOIN '.IMAGE_CATEGORY_TABLE.' ON category_id = id
  WHERE image_id = '.$element_info['id'].'
'.get_sql_condition_FandF(
  array(
      'forbidden_categories' => 'category_id',
      'forbidden_images' => 'image_id',
    ),
  '    AND'
  ).'
  LIMIT 1
;';
if ( pwg_db_num_rows(pwg_query($query))<1 ) {
  do_error(401, 'Access denied');
}

include_once(PHPWG_ROOT_PATH.'include/functions_picture.inc.php');

if ( !$user['enabled_high'] &&  strpos($element_info['path'], $path) !== false) {
   do_error(401, 'Access denied');
}

echo 'OK';
