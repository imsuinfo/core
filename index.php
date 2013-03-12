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

function _drupal_root_db_prepare_() {
  drupal_bootstrap(DRUPAL_BOOTSTRAP_CONFIGURATION);

  require_once DRUPAL_ROOT . '/includes/database/database.inc';
  require_once DRUPAL_ROOT . '/includes/cache.inc';
  spl_autoload_register('drupal_autoload_class');
  spl_autoload_register('drupal_autoload_interface');

  require_once DRUPAL_ROOT . '/sites/all/modules/mcneese/mcneese_file_db/mcneese_file_db.module';
  require_once DRUPAL_ROOT . '/sites/all/modules/mcneese/mcneese_file_db/classes/mcneese_file_db_stream_wrapper.inc';
  require_once DRUPAL_ROOT . '/sites/all/modules/mcneese/mcneese_file_db/classes/mcneese_file_db_unrestricted_stream_wrapper.inc';
  //require_once DRUPAL_ROOT . '/sites/all/modules/mcneese/mcneese_file_db/classes/mcneese_file_db_restricted_stream_wrapper.inc';
}

/**
 * Root directory of Drupal installation.
 */
define('DRUPAL_ROOT', getcwd());

require_once DRUPAL_ROOT . '/includes/bootstrap.inc';

$uri = request_uri();
$arguments = explode('/', $uri);

if (isset($arguments[1]) && $arguments[1] == 'f') {
  try {
    _drupal_root_db_prepare_();
    mcneese_file_db_return_file($arguments);
  }
  catch (Exception $e) {
    drupal_bootstrap(DRUPAL_BOOTSTRAP_FULL);
    throw $e;
  }
}
else if (isset($arguments[1]) && $arguments[1] == 'files' && count($arguments) > 6 && $arguments[2] == 'styles') {
  drupal_bootstrap(DRUPAL_BOOTSTRAP_FULL);

  //if (($arguments[4] == mcneese_file_db_unrestricted_stream_wrapper::SCHEME || $arguments[4] == mcneese_file_db_restricted_stream_wrapper::SCHEME) && $arguments[5] == 'f') {
  if ($arguments[4] == mcneese_file_db_unrestricted_stream_wrapper::SCHEME && $arguments[5] == 'f') {
    $file_uri = DRUPAL_ROOT . $uri;

    if (!file_exists($file_uri) || empty($arguments[8])) {
      mcneese_file_db_generate_image_style($arguments);
      exit();
    }
  }

  menu_execute_active_handler();
}
else {
  drupal_bootstrap(DRUPAL_BOOTSTRAP_FULL);
  menu_execute_active_handler();
}
