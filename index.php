<?php

/**
 * @file
 * The PHP page that serves all page requests on a Drupal installation.
 *
 * The routines here dispatch control to the appropriate handler, which then
 * prints the appropriate page.
 *
 * All Drupal code is released under the GNU General Public License.
 * See COPYRIGHT.txt and LICENSE.txt.
 */

if (!function_exists('_drupal_root_db_prepare_')) {
  function _drupal_root_db_prepare_() {
    drupal_bootstrap(DRUPAL_BOOTSTRAP_CONFIGURATION);

    require_once DRUPAL_ROOT . '/includes/database/database.inc';
    require_once DRUPAL_ROOT . '/includes/stream_wrappers.inc';

    require_once DRUPAL_ROOT . '/sites/all/modules/mcneese/mcneese_file_db/mcneese_file_db.module';
    require_once DRUPAL_ROOT . '/sites/all/modules/mcneese/mcneese_file_db/classes/mcneese_file_db_stream_wrapper.inc';
    require_once DRUPAL_ROOT . '/sites/all/modules/mcneese/mcneese_file_db/classes/mcneese_file_db_unrestricted_stream_wrapper.inc';
    //require_once DRUPAL_ROOT . '/sites/all/modules/mcneese/mcneese_file_db/classes/mcneese_file_db_restricted_stream_wrapper.inc';
  }
}

if (!function_exists('_drupal_root_get_uri')) {
  function _drupal_root_get_uri() {
    global $base_path;

    $uri = request_uri();

    return substr($uri, strlen($base_path));
  }
}

/**
 * Root directory of Drupal installation.
 */
define('DRUPAL_ROOT', getcwd());

require_once DRUPAL_ROOT . '/includes/bootstrap.inc';
drupal_bootstrap(DRUPAL_BOOTSTRAP_CONFIGURATION);

$uri = _drupal_root_get_uri();
$arguments = (array) explode('/', $uri);

if (isset($arguments[0]) && $arguments[0] == 'f') {
  try {
    _drupal_root_db_prepare_();

    if (function_exists('mcneese_file_db_return_file')) {
      mcneese_file_db_return_file($arguments);
    } else {
      unset($uri);
      unset($arguments);
      drupal_bootstrap(DRUPAL_BOOTSTRAP_FULL);
      drupal_not_found();
      drupal_exit();
    }
  }
  catch (Exception $e) {
    drupal_bootstrap(DRUPAL_BOOTSTRAP_FULL);
    throw $e;
  }
}
else if (count($arguments) > 5 && $arguments[0] == 'files' && $arguments[1] == 'styles') {
  drupal_bootstrap(DRUPAL_BOOTSTRAP_FULL);

  if (module_exists('mcneese_file_db')) {
    //if (($arguments[3] == mcneese_file_db_unrestricted_stream_wrapper::SCHEME || $arguments[3] == mcneese_file_db_restricted_stream_wrapper::SCHEME) && ($arguments[4] == MCNEESE_FILE_DB_FILE_PATH || $arguments[5] == MCNEESE_FILE_DB_PATH_BY_HASH)) {
    if ($arguments[3] == mcneese_file_db_unrestricted_stream_wrapper::SCHEME && ($arguments[4] == MCNEESE_FILE_DB_FILE_PATH || $arguments[5] == MCNEESE_FILE_DB_PATH_BY_HASH)) {
      mcneese_file_db_generate_image_style($arguments);
    }
  }

  unset($uri);
  unset($arguments);
  menu_execute_active_handler();
}
else if ((isset($arguments[0]) && $arguments[0] == 'files' || isset($arguments[3]) && $arguments[3] == 'files')) {
  drupal_bootstrap(DRUPAL_BOOTSTRAP_DATABASE);

  $query = db_query('select ua.source from {url_alias} ua where ua.alias = :alias', array(':alias' => $uri));
  $result = $query->fetchField();

  if (empty($result)) {
    unset($uri);
    unset($arguments);
    drupal_bootstrap(DRUPAL_BOOTSTRAP_FULL);
    menu_execute_active_handler();
  }
  else {
    $uri = $result;
    $arguments = (array) explode('/', $uri);

    try {
      _drupal_root_db_prepare_();

      if (function_exists('mcneese_file_db_return_file')) {
        mcneese_file_db_return_file($arguments, FALSE);
      } else {
        unset($uri);
        unset($arguments);
        drupal_bootstrap(DRUPAL_BOOTSTRAP_FULL);
        drupal_not_found();
        drupal_exit();
      }
    }
    catch (Exception $e) {
      drupal_bootstrap(DRUPAL_BOOTSTRAP_FULL);
      throw $e;
    }
  }
}
else {
  unset($uri);
  unset($arguments);
  drupal_bootstrap(DRUPAL_BOOTSTRAP_FULL);
  menu_execute_active_handler();
}
