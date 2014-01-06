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

/**
 * Root directory of Drupal installation.
 */
define('DRUPAL_ROOT', getcwd());
require_once 'includes/bootstrap.inc';

function _drupal_root_db_prepare_() {
  drupal_bootstrap(DRUPAL_BOOTSTRAP_CONFIGURATION);

  require_once 'includes/database/database.inc';
  require_once 'includes/stream_wrappers.inc';

  if (file_exists('sites/all/modules/mcneese/mcneese_file_db/mcneese_file_db.module')) {
    require_once 'sites/all/modules/mcneese/mcneese_file_db/mcneese_file_db.module';
    require_once 'sites/all/modules/mcneese/mcneese_file_db/classes/mcneese_file_db_stream_wrapper.inc';
    require_once 'sites/all/modules/mcneese/mcneese_file_db/classes/mcneese_file_db_unrestricted_stream_wrapper.inc';
    require_once 'sites/all/modules/mcneese/mcneese_file_db/classes/mcneese_file_db_restricted_stream_wrapper.inc';
  }
}

function _drupal_root_get_uri() {
  global $base_path;

  $uri = request_uri();
  $parsed = parse_url($uri);

  return substr($parsed['path'], strlen($base_path));
}

/**
 * Provide a exception reporting via watchdog.
 */
function _drupal_root_exception_watchdog($ex, $severity = WATCHDOG_NOTICE) {
  $decoded = _drupal_decode_exception($ex);
  watchdog('php', '%type: !message in %function (line %line of %file).', $decoded, $severity);
}

/**
 * Wrap the main drupal execution function inside of try..catch statements to handle errors.
 *
 * @todo: it should be possible to load a static page or a fallback page at this point as a failsafe that looks better than Internal Server Error.
 */
function _drupal_root_execute_handler($not_found = FALSE) {
  try {
    menu_execute_active_handler();
  }
  catch (ParseError $ex) {
    _drupal_root_exception_watchdog($ex, WATCHDOG_CRITICAL);

    if ($not_found) {
      drupal_not_found();
      drupal_exit();
    }

    throw $ex;
  }
  catch (Error $ex) {
    _drupal_root_exception_watchdog($ex);

    if ($not_found) {
      drupal_not_found();
      drupal_exit();
    }

    throw $ex;
  }
  catch (Exception $ex) {
    _drupal_root_exception_watchdog($ex);

    if ($not_found) {
      drupal_not_found();
      drupal_exit();
    }

    throw $ex;
  }
}

drupal_bootstrap(DRUPAL_BOOTSTRAP_CONFIGURATION);

$uri = _drupal_root_get_uri();
$arguments = (array) explode('/', $uri);
$arguments_count = count($arguments);

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
  catch (Error $e) {
    drupal_bootstrap(DRUPAL_BOOTSTRAP_FULL);
    throw $e;
  }
  catch (Exception $e) {
    drupal_bootstrap(DRUPAL_BOOTSTRAP_FULL);
    throw $e;
  }
}
elseif ($arguments_count > 5 && $arguments[0] == 'files' && $arguments[1] == 'styles') {
  // if the count is greater than 8, then the url is not possibly valid.
  if ($arguments_count > 8) {
    unset($uri);
    unset($arguments);
    drupal_bootstrap(DRUPAL_BOOTSTRAP_FULL);
    drupal_not_found();
    drupal_exit();
  }

  drupal_bootstrap(DRUPAL_BOOTSTRAP_FULL);
  $dbu_or_dbr = FALSE;
  $association = NULL;

  if (function_exists('mcneese_file_db_generate_image_style')) {
    if (class_exists('mcneese_file_db_unrestricted_stream_wrapper') && $arguments[3] == mcneese_file_db_unrestricted_stream_wrapper::SCHEME) {
      $dbu_or_dbr = TRUE;
      $association = 'mcneese_file_db_unrestricted';
    }
    elseif (class_exists('mcneese_file_db_restricted_stream_wrapper') && $arguments[3] == mcneese_file_db_restricted_stream_wrapper::SCHEME) {
      $dbu_or_dbr = TRUE;
      $association = 'mcneese_file_db_restricted';
    }
  }

  if ($dbu_or_dbr) {
    if ($arguments[4] == MCNEESE_FILE_DB_FILE_PATH && ($arguments[5] == MCNEESE_FILE_DB_PATH_BY_HASH || $arguments[5] == MCNEESE_FILE_DB_PATH_BY_ID || $arguments[5] == MCNEESE_FILE_DB_PATH_BY_FID)) {
      $filename = mcneese_file_db_get_filename($arguments[6], $association, $arguments[5]);
      if (isset($filename[0])) {
        if (!empty($filename[0]->extension)) {
          $filename = $filename[0]->filename . '.' . $filename[0]->extension;
        }
        else {
          $filename = $filename[0]->filename;
        }

        if (array_key_exists(7, $arguments) && !empty($arguments[7])) {
          $argument_7 = rawurldecode($arguments[7]);

          if (strcasecmp($filename, $argument_7) !== 0) {
            // the filenames don't match, try checking drupals database for a match.
            try {
              $query = db_select('file_managed', 'fm');
              $query->fields('fm');
              $query->condition('fm.filename', db_like($argument_7), 'ILIKE');
              $found = (array) $query->execute()->fetchAssoc();
            }
            catch (Exception $ex) {
              drupal_bootstrap(DRUPAL_BOOTSTRAP_FULL);
              watchdog('File DB', "Failed to select on file_managed to match the internal name :name to the requested name :argument_7", array(':name' => $filename, ':argument_7' => $argument_7), WATCHDOG_ERROR);
              drupal_not_found();
              drupal_exit();
            }

            if (empty($found['uri'])) {
              $filename = FALSE;
            }
          }
          unset($argument_7);
        }
      }
      else {
        $filename = FALSE;
      }

      if (!$filename) {
        unset($filename);
        unset($uri);
        unset($arguments);
        unset($association);
        drupal_bootstrap(DRUPAL_BOOTSTRAP_FULL);
        drupal_not_found();
        drupal_exit();
      }
      unset($filename);

      mcneese_file_db_generate_image_style($arguments);
    }
    elseif ($arguments[3] == 'public') {
      $args1 = array_slice($arguments, 0, 3);
      $args2 = array_slice($arguments, 4);
      $auri = 'files/' . implode('/', $args2);
      $duri = rawurldecode($auri);

      $results = db_query('select ua.source from {url_alias} ua where (LOWER(ua.alias) = LOWER(:alias) or LOWER(ua.alias) = LOWER(:dalias)) and ua.source like :source', array(':alias' => $auri, ':dalias' => $duri, ':source' => 'f/%/%'));
      $result = $results->fetchField();

      if (empty($result)) {
        if (function_exists('redirect_load_by_source')) {
          $redirect = redirect_load_by_source($auri);

          if (is_object($redirect)) {
            $parts = explode('/', $redirect->redirect);

            if ($parts[0] == MCNEESE_FILE_DB_FILE_PATH && ($parts[1] == MCNEESE_FILE_DB_PATH_BY_HASH || $parts[1] == MCNEESE_FILE_DB_PATH_BY_ID || $parts[1] == MCNEESE_FILE_DB_PATH_BY_FID)) {
              $args1[] = mcneese_file_db_unrestricted_stream_wrapper::SCHEME;
              $args3 = array_merge($args1, $parts);
              $nuri = implode('/', $args3);

              // perform a redirect
              if (!headers_sent()) header('Location: /' . $nuri, TRUE, $redirect->status_code);
              drupal_exit($nuri);
            }
          }
        }
      }
      else {
        $args1[] = mcneese_file_db_unrestricted_stream_wrapper::SCHEME;
        $args3 = array_merge($args1, explode('/', $result));

        mcneese_file_db_generate_image_style($args3);
      }
    }
  }

  unset($uri);
  unset($arguments);
  unset($association);

  _drupal_root_execute_handler();
}
elseif (isset($arguments[0]) && $arguments[0] == 'files' || isset($arguments[3]) && $arguments[3] == 'files') {
  drupal_bootstrap(DRUPAL_BOOTSTRAP_DATABASE);
  $duri = rawurldecode($uri);

  $query = db_query('select ua.source from {url_alias} ua where LOWER(ua.alias) = LOWER(:alias) or LOWER(ua.alias) = LOWER(:dalias)', array(':alias' => $uri, ':dalias' => $duri));
  $result = $query->fetchField();

  if (empty($result)) {
    unset($uri);
    unset($arguments);

    drupal_bootstrap(DRUPAL_BOOTSTRAP_FULL);
    _drupal_root_execute_handler(TRUE);
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
    catch (Error $e) {
      drupal_bootstrap(DRUPAL_BOOTSTRAP_FULL);
      _drupal_root_exception_watchdog($ex, WATCHDOG_CRITICAL);
      drupal_not_found();
      drupal_exit();
    }
    catch (Exception $e) {
      drupal_bootstrap(DRUPAL_BOOTSTRAP_FULL);
      _drupal_root_exception_watchdog($ex, WATCHDOG_CRITICAL);
      drupal_not_found();
      drupal_exit();
    }
  }
}
else {
  unset($uri);
  unset($arguments);
  drupal_bootstrap(DRUPAL_BOOTSTRAP_FULL);
  _drupal_root_execute_handler();
}
