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
 * Build a string of css class names based on agent information.
 */
function _drupal_root_determine_user_agent_classes() {
  if (empty($_SERVER['HTTP_USER_AGENT'])) {
    return FALSE;
  }

  $raw = $_SERVER['HTTP_USER_AGENT'];

  $agent_matches = array();
  $agent_matched = preg_match('/^[^(]*\(([^)]*)\)(.*)$/i', $raw, $agent_matches);

  $engine = NULL;
  $engine_version = NULL;
  $machine_name = NULL;
  $major_version = NULL;
  $is_ie_edge = NULL;
  $ie_compatibility = FALSE;

  if (isset($agent_matches[1])) {
    $agent_pieces = explode(';', $agent_matches[1]);

    if (!empty($agent_pieces)) {
      foreach ($agent_pieces as $agent_piece) {
        $pieces = explode('/', $agent_piece);

        // ignore unknown structure.
        if (count($pieces) > 2) {
          continue;
        }

        if (isset($pieces[1])) {
          $lower_piece_1 = trim(strtolower($pieces[0]));
          $lower_piece_2 = trim(strtolower($pieces[1]));

          if ($lower_piece_1 == 'trident') {
            $engine = 'trident';
            $engine_version = (int) preg_replace('/\..*$/i', '', $lower_piece_2);

            $machine_name = 'ie';
          }
          elseif ($lower_piece_1 == 'gecko') {
            $engine = 'gecko';
            $engine_version = (int) preg_replace('/\..*$/i', '', $lower_piece_2);
          }
          elseif ($lower_piece_1 == 'presto') {
            $engine = 'presto';
            $engine_version = (int) preg_replace('/\..*$/i', '', $lower_piece_2);
          }
        }
        elseif (isset($pieces[0])) {
          $lower_piece_1 = trim(strtolower($pieces[0]));

          if (!empty($lower_piece_1)) {
            if (preg_match('/^msie \d/i', $lower_piece_1)) {
              $lower_piece_2 = preg_replace('/^msie /i', '', $lower_piece_1);

              $machine_name = 'ie';
              $major_version = (int) preg_replace('/\..*$/i', '', $lower_piece_2);
            }
            elseif (strpos($lower_piece_1, 'midori')) {
              $machine_name = 'midori';
              $engine = 'webkit';
            }
          }
        }
      }
    }
  }

  if (isset($agent_matches[2])) {
    $agent_pieces = explode(' ', $agent_matches[2]);

    if (!empty($agent_pieces)) {
      foreach ($agent_pieces as $agent_piece) {
        $pieces = explode('/', $agent_piece);

        // ignore unknown structure.
        if (count($pieces) > 3) {
          continue;
        }

        if (isset($pieces[1])) {
          $lower_piece_1 = trim(strtolower($pieces[0]));
          $lower_piece_2 = trim(strtolower($pieces[1]));

          if ($lower_piece_1 == 'applewebkit') {
            $engine = 'webkit';
            $engine_version = $lower_piece_2;
            $major_version = (int) preg_replace('/\..*$/i', '', $lower_piece_2);
          }
          elseif ($lower_piece_1 == 'safari') {
            // safari is used in a lot of places that is not safari, so use safari only if it is the only agent detected.
            if ($machine_name == 'unknown') {
              $machine_name = 'safari';
              $major_version = (int) preg_replace('/\..*$/i', '', $lower_piece_2);

              if ($engine == 'unknown') {
                $engine = 'webkit';
              }
            }
          }
          elseif ($lower_piece_1 == 'firefox') {
            $machine_name = 'firefox';
            $major_version = (int) preg_replace('/\..*$/i', '', $lower_piece_2);
            $engine = 'gecko';
          }
          elseif ($lower_piece_1 == 'seamonkey') {
            $machine_name = 'seamonkey';
            $major_version = (int) preg_replace('/\..*$/i', '', $lower_piece_2);
            $engine = 'gecko';
          }
          elseif ($lower_piece_1 == 'chrome') {
            // the newer internet explorer uses safari/webkit based agent names, assign chrome conditionally.
            if ($machine_name == 'unknown' || $machine_name == 'safari') {
              $machine_name = 'chrome';
              $major_version = (int) preg_replace('/\..*$/i', '', $lower_piece_2);
            }
          }
          elseif ($lower_piece_1 == 'chromium') {
            $machine_name = 'chrome';
            $major_version = (int) preg_replace('/\..*$/i', '', $lower_piece_2);
          }
          elseif ($lower_piece_1 == 'epiphany') {
            $machine_name = 'epiphany';
            $major_version = (int) preg_replace('/\..*$/i', '', $lower_piece_2);

            if ($engine == 'unknown') {
              $engine = 'gecko';
            }
          }
          elseif ($lower_piece_1 == 'konqueror') {
            $machine_name = 'konqueror';
            $major_version = (int) preg_replace('/\..*$/i', '', $lower_piece_2);

            if ($engine == 'unknown') {
              $engine = 'gecko';
            }
          }
          elseif ($lower_piece_1 == 'khtml') {
            $machine_name = 'konqueror';
            $major_version = (int) preg_replace('/\..*$/i', '', $lower_piece_2);
          }
          elseif ($lower_piece_1 == 'opr') {
            $machine_name = 'opera';
            $major_version = (int) preg_replace('/\..*$/i', '', $lower_piece_2);

            if ($engine == 'unknown') {
              $engine = 'presto';
            }
          }
          elseif ($lower_piece_1 == 'edge') {
            $machine_name = 'ie';
            $major_version = (int) preg_replace('/\..*$/i', '', $lower_piece_2);
            $is_ie_edge = TRUE;
          }
          elseif ($lower_piece_1 == 'midori') {
            $machine_name = 'midori';
            $major_version = (int) preg_replace('/\..*$/i', '', $lower_piece_2);
          }
        }
        elseif (isset($pieces[0])) {
          $lower_piece_1 = trim(strtolower($pieces[0]));

          if ($lower_piece_1 == 'opera') {
            $machine_name = 'opera';

            if ($engine == 'unknown') {
              $engine = 'presto';
            }
          }
          elseif ($lower_piece_1 == '(khtml,') {
            // khtml is used in a lot of places that is not safari, so use only when necessary.
            if ($engine == 'unknown' || $machine_name == 'epiphany' || $machine_name == 'konqueror') {
              $engine = 'webkit';
            }

            if ($machine_name == 'unknown') {
              $machine_name = 'safari';
            }
          }
        }
      }
    }
  }

  // attempt to determine internet explorer versions if not already found.
  if ($engine == 'trident' && ($machine_name == 'unknown' || ($machine_name == 'ie' && $major_version == 'unknown'))) {
    $machine_name = 'ie';

    if (isset($is_ie_edge)) {
      $major_version = 12;
    }
    elseif ($engine_version == 7) {
      $major_version = 11;
    }
    elseif ($engine_version == 6) {
      $major_version = 10;
    }
    elseif ($engine_version == 5) {
      $major_version = 9;
    }
    elseif ($engine_version == 4) {
      $major_version = 8;
    }
  }

  // detect internet explorers compatibility mode (for old versions) where possible to allow clients to better handle.
  if ($machine_name == 'ie') {
    $ie_compatibility = FALSE;

    if ($major_version <= 8) {
      if ($major_version == 7) {
        if ($engine == 'trident') {
          $ie_compatibility = TRUE;
        }
      }
    }

    // alter the (faked) agent version to properly reflect the current browser.
    if ($ie_compatibility && isset($engine_version)) {
      if (isset($is_ie_edge)) {
        $major_version = 12;
      }
      elseif ($engine_version == 7) {
        $major_version = 11;
      }
      elseif ($engine_version == 6) {
        $major_version = 10;
      }
      elseif ($engine_version == 5) {
        $major_version = 9;
      }
      elseif ($engine_version == 4) {
        $major_version = 8;
      }
      elseif (preg_match("/; EIE10;/i", $raw) > 0) {
        $major_version = 10;
      }
    }

    // added later on to allow for compatibility mode tests to be properly processed.
    $engine = 'trident';
  }

  $classes = '';

  if (!is_null($machine_name)) {
    $classes .= ' agent-name-' . $machine_name;
  }

  if (!is_null($engine)) {
    $classes .= ' agent-engine-' . $engine;
  }

  if (!is_null($major_version)) {
    $classes .= ' agent-major_version-' . $major_version;
  }

  if ($ie_compatibility) {
    $classes .= ' is-in_ie_compatibility_mode';
  }

  return $classes;
}

/**
 * Builds/Populates the static front page file.
 */
function _drupal_root_build_static_front_page($file_name) {
  drupal_bootstrap(DRUPAL_BOOTSTRAP_FULL);

  $router_item = menu_get_item();
  if (empty($router_item)) {
    return FALSE;
  }
  else {
    if ($router_item['access']) {
      if ($router_item['include_file']) {
        require_once DRUPAL_ROOT . '/' . $router_item['include_file'];
      }
      $page_callback_result = call_user_func_array($router_item['page_callback'], $router_item['page_arguments']);
      if (is_int($page_callback_result) || is_null($page_callback_result)) {
        return FALSE;
      }
    }
    else {
      return FALSE;
    }
  }

  $renderred = drupal_deliver_html_page($page_callback_result, TRUE);

  $matches = array();
  $matched = preg_match('/<body id="mcneese-body" class="([^"]*)"/i', $renderred, $matches);

  if ($matched && isset($matches[1])) {
    $fixed_css = preg_replace('/ agent-name-\w+\b/i', '', $matches[1]);
    $fixed_css = preg_replace('/ agent-engine-\w+\b/i', '', $fixed_css);
    $fixed_css = preg_replace('/ agent-major_version-\w+\b/i', '', $fixed_css);
    $fixed_css = preg_replace('/ date-year-\d+\b/i', '', $fixed_css);
    $fixed_css = preg_replace('/ date-month-\d+\b/i', '', $fixed_css);
    $fixed_css = preg_replace('/ date-week-\d+\b/i', '', $fixed_css);
    $fixed_css = preg_replace('/ date-day-\d+\b/i', '', $fixed_css);
    $fixed_css = preg_replace('/ date-hour-\d+\b/i', '', $fixed_css);
    $fixed_css = preg_replace('/ date-minute-\d+\b/i', '', $fixed_css);
    $fixed_css = preg_replace('/ is-unsupported\b/i', '', $fixed_css);
    $fixed_css = preg_replace('/ is-legacy\b/i', '', $fixed_css);

    $renderred = preg_replace('/<body id="mcneese-body" class="([^"]*)"/i', '<body id="mcneese-body" class="' . $fixed_css . '"', $renderred);
  }

  $directory_name = dirname($file_name);
  if (!is_dir($directory_name) && !file_exists($directory_name)) {
    mkdir($directory_name, 0775, TRUE);
    chmod($directory_name, 0775);
  }

  $file = fopen($file_name, 'c');
  if (!is_resource($file)) {
    return FALSE;
  }

  $locked = flock($file, LOCK_EX);
  if (!$locked) {
    fclose($file);
    return FALSE;
  }

  if (!ftruncate($file, 0)) {
    flock($file, LOCK_UN);
    fclose($file);
    return FALSE;
  }

  if (!fwrite($file, $renderred)) {
    flock($file, LOCK_UN);
    fclose($file);

    // file was truncated, so delete it because it is empty on error.
    unlink($file);
    return FALSE;
  }

  chmod($file_name, 0664);

  flock($file, LOCK_UN);
  fclose($file);

  return TRUE;
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

drupal_bootstrap(DRUPAL_BOOTSTRAP_SESSION);

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
  drupal_bootstrap(DRUPAL_BOOTSTRAP_SESSION);

  // conditionally load the front page from a static file.
  global $user;
  if (isset($user->uid) && $user->uid == 0 && strlen($uri) == 0) {
    global $is_https;

    // do not cache maintenance mode pages.
    $maintenance_mode = 0;
    $maintenance_mode = variable_get('maintenance_mode', 0);

    if ($is_https) {
      $static_file_frontpage = variable_get('mcneese_static_file_frontpage-https', FALSE);
    }
    else {
      $static_file_frontpage = variable_get('mcneese_static_file_frontpage', FALSE);
    }

    if ($maintenance_mode == 0 && !defined('MAINTENANCE_MODE') && is_string($static_file_frontpage) && !empty($static_file_frontpage)) {
      $timezone = date_default_timezone_get();
      date_default_timezone_set('UTC');
      if (defined('REQUEST_TIME')) {
        $instance = REQUEST_TIME;
      }
      else {
        $instance = microtime();
      }

      $render_static_frontpage = FALSE;
      $static_file_frontpage_expires = variable_get('mcneese_static_file_frontpage-expires', NULL);
      if ($static_file_frontpage_expires !== FALSE && (!is_string($static_file_frontpage_expires) || empty($static_file_frontpage_expires))) {
        $static_file_frontpage_expires = '+3 hours';
      }

      if (file_exists($static_file_frontpage)) {
        if ($static_file_frontpage_expires !== FALSE) {
          $file_modified = filemtime($static_file_frontpage);
          $file_modified_expires = strtotime($static_file_frontpage_expires, $file_modified);

          if ($file_modified_expires < $instance) {
            $render_static_frontpage = _drupal_root_build_static_front_page($static_file_frontpage);
          }
          else {
            $render_static_frontpage = TRUE;
          }

          unset($file_modified);
          unset($file_modified_expires);
        }
      }
      else {
        $render_static_frontpage = _drupal_root_build_static_front_page($static_file_frontpage);
      }

      if ($render_static_frontpage) {
        $css_body = '';
        $css_body .= _drupal_root_determine_user_agent_classes();
        $css_body .= ' date-year-' . date('Y', $instance);
        $css_body .= ' date-month-' . date('m', $instance);
        $css_body .= ' date-day-' . date('d', $instance);
        $css_body .= ' date-week-' . date('W', $instance);
        $css_body .= ' date-hour-' . date('H', $instance);
        $css_body .= ' date-minute-' . date('i', $instance);
        unset($instance);

        date_default_timezone_set($timezone);
        unset($timezone);

        $static_frontpage = file_get_contents($static_file_frontpage);

        $matches = array();
        $matched = preg_match('/<body id="mcneese-body" class="([^"]*)"/i', $static_frontpage, $matches);
        if ($matched && isset($matches[1])) {
          $static_frontpage = preg_replace('/<body id="mcneese-body" class="([^"]*)"/i', '<body id="mcneese-body" class="' . $matches[1] . $css_body . '"', $static_frontpage);
        }
        unset($css_body);

        drupal_send_headers();
        print($static_frontpage);
        // drupal_exit() is not called because it is causing static error pages to be renderred following the normal output.
        drupal_session_commit();
        exit();
      }
      unset($maintenance_mode);
      unset($instance);
      unset($render_static_frontpage);
      unset($static_file_frontpage_expires);
    }
  }
  unset($uri);
  unset($arguments);

  drupal_bootstrap(DRUPAL_BOOTSTRAP_FULL);
  _drupal_root_execute_handler();
}
