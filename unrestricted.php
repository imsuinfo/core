<?php

// when defined, the drupal settings file should know better to not load passwords of any kind.
define('DO_NOT_INCLUDE_PASSWORDS', TRUE);

// load the drupal settings file.
if (!file_exists('sites/default/settings.php')) {
  unrestricted_service_unavailable();
}
require_once('sites/default/settings.php');

// these defines come from mcneese_file_db.module and should match.
if (!file_exists('sites/all/modules/mcneese/mcneese_file_db/mcneese_file_db.module')) {
  unrestricted_service_unavailable();
}
require_once('sites/all/modules/mcneese/mcneese_file_db/mcneese_file_db.module');

/**
 * Main Function
 */
function unrestricted_main() {
  $settings = unrestricted_get_settings();

  $uri = unrestricted_get_uri($settings['uri_unshift']);

  $information = unrestricted_get_file_information($uri, $settings);

  if ($information['hash_only']) {
    unrestricted_get_file_hash($information, $settings);
  }
  else {
    unrestricted_get_file_data($information, $settings);
  }

  // make sure connection is closed when finished.
  if ($information['connection'] !== FALSE) {
    pg_close($information['connection']);
    $information['connection'] = FALSE;
  }
}

/**
 * Sends headers to client.
 *
 * @param array $headers
 *   An array containing the header name as the key and the value is an array:
 *     'value': A string representing the header information for that key.
 *     'status_code': status code number for the key called 'status'.
 */
function unrestricted_send_headers($headers) {
  if (empty($headers) || !is_array($headers)) {
    unrestricted_not_found();
  }

  foreach ($headers as $name => $header) {
    if (empty($name) || !is_array($header) || !array_key_exists('value', $header)) continue;

    if ($name == 'status') {
      if (!isset($header['status_code'])) continue;

      if (empty($_SERVER['SERVER_PROTOCOL'])) {
        header('HTTP/1.1 ' . $header['value'], TRUE, $header['status_code']);
      }
      else {
        header($_SERVER['SERVER_PROTOCOL'] . ' ' . $header['value'], TRUE, $header['status_code']);
      }
    }
    // Skip headers that have been unset.
    elseif ($header['value'] !== FALSE) {
      header($name . ': ' . $header['value']);
    }
  }
}

/**
 * Exits with 404.
 */
function unrestricted_not_found() {
  $instance = unrestricted_get_instance();

  $headers = array();
  $headers['status'] = array('value' => '404 Not Found', 'status_code' => 404);
  $headers['Content-Type'] = array('value' => 'text/html');
  $headers['Date'] = array('value' => date('r', $instance));

  unrestricted_send_headers($headers);

  # load the not found page, if it exists.
  $file = getcwd() . '/not_found/index.html';
  if (file_exists($file)) {
    print(file_get_contents($file));
  }
  else {
    print("<h1>Not Found (404)</h1>\n");
  }

  exit();
}

/**
 * Exits with 503.
 *
 * @param string $message
 *   (optional) Additional information to display..
 */
function unrestricted_service_unavailable($message = NULL) {
  $instance = unrestricted_get_instance();

  $headers = array();
  $headers['status'] = array('value' => '503 Service Unavailable', 'status_code' => 503);
  $headers['Content-Type'] = array('value' => 'text/html');
  $headers['Date'] = array('value' => date('r', $instance));

  unrestricted_send_headers($headers);

  # load the not found page, if it exists.
  $file = getcwd() . '/service_unavailable/index.html';
  if (file_exists($file)) {
    print(file_get_contents($file));
  }
  else {
    print("<h1>Service Unavailable (503)</h1>\n");

    if (is_string($message)) {
      print($message . "<br>\n");
    }
  }

  exit();
}

/**
 * Exits with 406.
 */
function unrestricted_not_acceptable() {
  $instance = unrestricted_get_instance();

  $headers = array();
  $headers['status'] = array('value' => '406 Not Acceptable', 'status_code' => 406);
  $headers['Content-Type'] = array('value' => 'text/html');
  $headers['Date'] = array('value' => date('r', $instance));

  unrestricted_send_headers($headers);

  print("<h1>Not Acceptable (406)</h1>\n");

  exit();
}

/**
 * Exits with 412.
 */
function unrestricted_precondition_failed() {
  $instance = unrestricted_get_instance();

  $headers = array();
  $headers['status'] = array('value' => '412 Pre-Condition Failed', 'status_code' => 412);
  $headers['Content-Type'] = array('value' => 'text/html');
  $headers['Date'] = array('value' => date('r', $instance));

  unrestricted_send_headers($headers);

  print("<h1>Pre-Condition Failed (412)</h1>\n");

  exit();
}

/**
 * Parses the request uri.
 *
 * @param int $uri_unshift
 *   (optional) Number representing how many arguments to unshift from the uri.
 *   The default is 1 because the initial / is considered an argument.
 *   A website sitting at: www.example.com/mysite will need an unshift value of
 *   2.
 *
 * @return array
 *   An array containing the uri which each key representing an argument
 *   between the / in the uri.
 */
function unrestricted_get_uri($uri_unshift = 1) {
  if (empty($_SERVER['REQUEST_URI'])) {
    unrestricted_not_found();
  }

  $uri_parts = explode('/', $_SERVER['REQUEST_URI']);
  if (!is_array($uri_parts)) {
    unrestricted_not_found();
  }

  for ($i = 0; $i < $uri_unshift; $i++) {
    array_shift($uri_parts);
  }
  unset($i);

  if ($uri_parts[0] != MCNEESE_FILE_DB_FILE_PATH) {
    unrestricted_not_found();
  }

  $uri_parts_total = count($uri_parts);
  if ($uri_parts_total < 3 || $uri_parts_total > 4) {
    unrestricted_not_found();
  }

  $uri = array();
  $uri[0] = $uri_parts[1];
  $uri[1] = $uri_parts[2];

  if (array_key_exists(3, $uri_parts)) {
    $uri[2] = $uri_parts[3];
  }

  return $uri;
}

/**
 * Loads the default settings such as database connection information.
 *
 * @return array
 *   The populated settings array.
 */
function unrestricted_get_settings() {
  global $databases;
  global $conf;

  $settings = array();
  $settings['uri_unshift'] = 1;
  $settings['database'] = array(
    'name' => 'web_files',
    'host' => NULL,
    'port' => NULL,
    'username' => 'public_user',
    'password' => NULL,
    'connect_type' => NULL, # PGSQL_CONNECT_FORCE_NEW, PGSQL_CONNECT_ASYNC
    'connect_timeout' => 6,
    'options' => NULL,
    'sslmode' => 'disable', # disable, allow, prefer, require
    'service' => NULL,
  );
  $settings['database_drupal'] = array(
    'name' => NULL,
    'host' => NULL,
    'port' => '5092',
    'username' => 'public_user',
    'password' => NULL,
    'connect_type' => NULL, # PGSQL_CONNECT_FORCE_NEW, PGSQL_CONNECT_ASYNC
    'connect_timeout' => 6,
    'options' => NULL,
    'sslmode' => NULL, # disable, allow, prefer, require
    'service' => NULL,
  );
  $settings['base_path'] = '/';
  $settings['http'] = array(
    'if_match' => FALSE,
    'if_none_match' => FALSE,
    'if_modified_since' => FALSE,
    'if_unmodified_since' => FALSE,
    'range' => FALSE,
  );

  foreach (array('database', 'host', 'port') as $value) {
    if (!empty($databases['file_db']['default'][$value])) {
      if ($value == 'database') {
        $settings['database']['name'] = $databases['file_db']['default'][$value];
        continue;
      }

      $settings['database'][$value] = $databases['file_db']['default'][$value];
    }
  }

  foreach (array('database', 'host', 'port') as $value) {
    if (!empty($databases['default']['default'][$value])) {
      if ($value == 'database') {
        $settings['database_drupal']['name'] = $databases['default']['default'][$value];
        continue;
      }

      $settings['database_drupal'][$value] = $databases['default']['default'][$value];
    }
  }


  // load the base_url to determine the path following.
  global $base_url;
  if (!empty($base_url)) {
    $base_url_parts = explode('://', $base_url, 2);

    if (!empty($base_url_parts[1])) {
      $base_url_parts = explode('/', $base_url_parts[1]);

      if (!empty($base_url_parts)) {
        array_shift($base_url_parts);

        if (!empty($base_url_parts)) {
          $settings['uri_unshift'] = count($base_url_parts) + 1;
          $settings['base_path'] = '/' . implode('/', $base_url_parts);
        }
      }
    }
  }


  // load the serer id.
  $settings['server_id'] = (int) mcneese_file_db_get_server_id();
  if (empty($settings['server_id'])) {
    unrestricted_service_unavailable();
  }


  // load additional HTTP request optimizations.
  if (isset($_SERVER['HTTP_IF_NONE_MATCH'])) {
    $settings['http']['if_none_match'] = preg_replace('/^"/i', '', $_SERVER['HTTP_IF_NONE_MATCH']);
    $settings['http']['if_none_match'] = preg_replace('/"$/i', '', $settings['http']['if_none_match']);
  }

  if (isset($_SERVER['HTTP_IF_MATCH'])) {
    $settings['http']['if_match'] = preg_replace('/^"/i', '', $_SERVER['HTTP_IF_MATCH']);
    $settings['http']['if_match'] = preg_replace('/"$/i', '', $settings['http']['if_match']);
  }

  if (!empty($_SERVER['HTTP_IF_MODIFIED_SINCE'])) {
    $settings['http']['if_modified_since'] = preg_replace('/^"/i', '', $_SERVER['HTTP_IF_MODIFIED_SINCE']);
    $settings['http']['if_modified_since'] = preg_replace('/"$/i', '', $settings['http']['if_modified_since']);
  }

  if (!empty($_SERVER['HTTP_IF_UNMODIFIED_SINCE'])) {
    $settings['http']['if_unmodified_since'] = preg_replace('/^"/i', '', $_SERVER['HTTP_IF_UNMODIFIED_SINCE']);
    $settings['http']['if_unmodified_since'] = preg_replace('/"$/i', '', $settings['http']['if_unmodified_since']);
  }

  if (!empty($_SERVER['HTTP_RANGE'])) {
    $matches = array();
    $matched = preg_match('/^bytes=(\d+)-(\d+)$/i', $_SERVER['HTTP_RANGE'], $matches);

    if ($matched) {
      $request_range_begin = $matches[1];
      $request_range_end = $matches[2];
      $request_range_length = ($request_range_end - $request_range_begin) + 1;

      // @todo: not yet implemented.
      #$settings['request_range'] = array(
      #  'begin' => $request_range_begin,
      #  'end' => $request_range_end,
      #  'length' => $request_range_length,
      #);
    }
  }

  return $settings;
}

/**
 * Connects to the database and loads file information.
 *
 * This is expected to be the first function to access the database.
 * The database connection is created by this function.
 *
 * @param array $uri
 *   An already processed uri array.
 * @param array $settings
 *   An already loaded settings array.
 *
 * @return array
 *   An array of file information loaded from the database.
 *
 * @see: unrestricted_get_uri()
 * @see: unrestricted_get_settings()
 * @see: unrestricted_get_file_data()
 * @see: unrestricted_get_file_hash()
 */
function unrestricted_get_file_information($uri, $settings) {
  $information = array(
    'connection' => FALSE,
    'file' => array(),
    'not_modified' => FALSE,
    'hash_only' => FALSE,
  );

  if ($uri[0] == MCNEESE_FILE_DB_PATH_BY_HASH || $uri[0] == MCNEESE_FILE_DB_PATH_BY_HASH_SUM) {
    // do not allow checksums smaller than MCNEESE_FILE_DB_SHORT_SUM_SIZE.
    if (strlen($uri[1]) < MCNEESE_FILE_DB_SHORT_SUM_SIZE) {
      unrestricted_not_found();
    }

    // only allow hexidecimal in the checksum argument.
    if (preg_match('/^(\d|a|b|c|d|e|f)+$/i', $uri[1]) != 1) {
      unrestricted_not_found();
    }

    if ($uri[0] == MCNEESE_FILE_DB_PATH_BY_HASH_SUM) {
      $information['hash_only'] = TRUE;
    }

    $checksum = $uri[1] . '%';
    $condition = 'checksum like ';
    $literal = $checksum;
  }
  elseif ($uri[0] == MCNEESE_FILE_DB_PATH_BY_ID) {
    if (preg_match('/^\d+$/i', $uri[1]) != 1) {
      unrestricted_not_found();
    }

    $condition = 'mfdf.id = ';
    $literal = $uri[1];
  }
  elseif ($uri[0] == MCNEESE_FILE_DB_PATH_BY_FID) {
    if (preg_match('/^\d+$/i', $uri[1]) != 1) {
      unrestricted_not_found();
    }

    // establish a connection to the drupal database to translate the drupal file id to the web_files checksum.
    $connect_string = "host=" . $settings['database_drupal']['host'];
    $connect_string .= " port=" . $settings['database_drupal']['port'];
    $connect_string .= " user=" . $settings['database_drupal']['username'];
    $connect_string .= " dbname=" . $settings['database_drupal']['name'];

    foreach (array('password', 'connect_timeout', 'options', 'sslmode', 'service') as $option_name) {
      if (array_key_exists($option_name, $settings['database_drupal']) && !is_null($settings['database_drupal'][$option_name])) {
        $connect_string .= ' ' . $option_name . "=" . $settings['database_drupal'][$option_name];
      }
    }

    if (!array_key_exists('connect_type', $settings['database_drupal']) || is_null($settings['database_drupal']['connect_type'])) {
      $drupal_connection = pg_connect($connect_string);
    }
    else {
      $drupal_connection = pg_connect($connect_string, $settings['database_drupal']['connect_type']);
    }

    if ($drupal_connection === FALSE) {
      unrestricted_service_unavailable();
    }

    $query = 'select uri from file_managed fm';
    $query .= ' where fm.fid = ' . pg_escape_literal($uri[1]);
    $query .= ' and uri like ' . pg_escape_literal('dbu://c/%');
    $query .= ' and not status = 0';
    $results = pg_query($drupal_connection, $query);

    if ($results === FALSE) {
      $error_message = pg_last_error($drupal_connection);
      pg_close($drupal_connection);
      unrestricted_service_unavailable($error_message);
    }

    // check to see if the file exists at all, and if so, switch to a normal drupal load.
    $row = pg_fetch_array($results, NULL, PGSQL_ASSOC);
    pg_free_result($results);
    if (!is_array($row)) {
      pg_close($drupal_connection);

      if ($settings['http']['if_match']) {
        unrestricted_precondition_failed();
      }

      unrestricted_not_found();
    }

    $uri_parts = explode('://c/', $row['uri'], 2);
    $uri_parts = explode('/', $uri_parts[1]);

    if (preg_match('/^(\d|a|b|c|d|e|f)+$/i', $uri_parts[0]) != 1) {
      unrestricted_not_found();
    }

    // now use the checksum to load the file extension.
    $checksum = $uri_parts[0] . '%';
    $condition = 'checksum like ';
    $literal = $checksum;
  }
  else {
    unrestricted_not_found();
  }

  // establish the connection and execute the query.
  $connect_string = "host=" . $settings['database']['host'];
  $connect_string .= " port=" . $settings['database']['port'];
  $connect_string .= " user=" . $settings['database']['username'];
  $connect_string .= " dbname=" . $settings['database']['name'];

  foreach (array('password', 'connect_timeout', 'options', 'sslmode', 'service') as $option_name) {
    if (array_key_exists($option_name, $settings['database']) && !is_null($settings['database'][$option_name])) {
      $connect_string .= ' ' . $option_name . "=" . $settings['database'][$option_name];
    }
  }

  if (!array_key_exists('connect_type', $settings['database']) || is_null($settings['database']['connect_type'])) {
    $information['connection'] = pg_connect($connect_string);
  }
  else {
    $information['connection'] = pg_connect($connect_string, $settings['database']['connect_type']);
  }

  if ($information['connection'] === FALSE) {
    unrestricted_service_unavailable();
  }

  $checksum = $uri[1] . '%';
  $query = 'select mfdf.id, mfdf.filename, mfdf.extension, mfdf.mimetype, mfdf.checksum, mfdf.size, mfdf.timestamp from mcneese_file_db_files mfdf';
  $query .= ' inner join mcneese_file_db_unrestricted mfdu on mfdf.id = mfdu.file_id';
  $query .= ' where mfdu.server_id = ' . pg_escape_literal($settings['server_id']);
  $query .= ' and ' . $condition . pg_escape_literal($literal);
  $results = pg_query($information['connection'], $query);

  if ($results === FALSE) {
    $error_message = pg_last_error($information['connection']);
    pg_close($information['connection']);
    unrestricted_service_unavailable($error_message);
  }

  $results_status = pg_result_status($results);
  if ($results_status != PGSQL_COMMAND_OK && $results_status != PGSQL_TUPLES_OK) {
    pg_free_result($results);
    pg_close($information['connection']);
    unrestricted_not_found();
  }

  $row = pg_fetch_array($results, NULL, PGSQL_ASSOC);
  pg_free_result($results);
  if (!is_array($row)) {
    $error_message = pg_last_error($information['connection']);
    pg_close($information['connection']);
    unrestricted_not_found($error_message);
  }

  $information['file'] = $row;
  unset($row);

  if (isset($information['file']['checksum']) && is_string($information['file']['checksum']) && strlen($information['file']['checksum']) > 0) {
    $information['file']['shortsum'] = substr($information['file']['checksum'], 0, MCNEESE_FILE_DB_SHORT_SUM_SIZE);
  }
  else {
    pg_close($information['connection']);
    unrestricted_not_found();
  }

  if (isset($settings['http']['if_none_match']) && is_string($settings['http']['if_none_match']) && strlen($settings['http']['if_none_match']) > 0) {
    $string_to_match = MCNEESE_FILE_DB_PATH_BY_HASH_ALGORITHM . '://' . $information['file']['checksum'];
    if ($uri[0] == MCNEESE_FILE_DB_PATH_BY_HASH_SUM) {
      $string_to_match .= '.' . MCNEESE_FILE_DB_PATH_BY_HASH_SUM_EXTENSION;
    }

    if (strcmp($settings['http']['if_none_match'], $string_to_match) == 0) {
      $information['not_modified'] = TRUE;
    }
    else {
      // the requested match is invalid, so report as invalid
      pg_close($information['connection']);
      unrestricted_not_acceptable();
    }
  }

  // timestamp is currently not stored as a unix timestamp, so convert it to one.
  $information['file']['timestamp'] = strtotime($information['file']['timestamp']);

  if (isset($settings['http']['if_unmodified_since']) && is_string($settings['http']['if_unmodified_since']) && strlen($settings['http']['if_unmodified_since']) > 0) {
    $since = strtotime($settings['http']['if_unmodified_since']);

    if ($since === FALSE) {
      // the requested match is invalid, so report as invalid
      pg_close($information['connection']);
      unrestricted_not_acceptable();
    }
    elseif ($information['file']['timestamp'] <= $since) {
      pg_close($information['connection']);
      unrestricted_precondition_failed();
    }
  }
  elseif (isset($settings['http']['if_modified_since']) && is_string($settings['http']['if_modified_since']) && strlen($settings['http']['if_modified_since']) > 0) {
    $since = strtotime($settings['http']['if_modified_since']);

    if ($since === FALSE) {
      // the requested match is invalid, so report as invalid
      pg_close($information['connection']);
      unrestricted_not_acceptable();
    }
    elseif ($information['file']['timestamp'] > $since) {
      $information['not_modified'] = TRUE;
    }
  }

  return $information;
}

/**
 * Connects to the database and prints the file data.
 *
 * This is expects the database connection to already be open.
 *
 * @param array $information
 *   An already processed file information array.
 * @param array $settings
 *   An already loaded settings array.
 *
 * @see: unrestricted_get_file_information()
 */
function unrestricted_get_file_data(&$information, $settings) {
  if ($information['connection'] === FALSE) {
    unrestricted_service_unavailable();
  }

  if (empty($information['file']['id']) || !is_numeric($information['file']['id'])) {
    unrestricted_not_found();
  }

  $instance = unrestricted_get_instance();

  // Only process the query when there are no changes because there is no reason to transmit the file (this is a client-side caching optimization).
  if (!$information['not_modified']) {
    $query = "select data from mcneese_file_db_file_data";
    $query .= " where file_id = " . pg_escape_literal($information['connection'], $information['file']['id']);
    $query .= " order by block asc";
    $results = pg_query($information['connection'], $query);

    if ($results === FALSE) {
      $error_message = pg_last_error($information['connection']);
      pg_close($information['connection']);
      unrestricted_service_unavailable($error_message);
    }

    $results_status = pg_result_status($results);
    if ($results_status != PGSQL_COMMAND_OK && $results_status != PGSQL_TUPLES_OK) {
      pg_free_result($results);
      pg_close($information['connection']);

      if ($settings['http']['if_match']) {
        unrestricted_precondition_failed();
      }
      unrestricted_not_found();
    }
  }

  // to prevent caching the entire file in memory, build the headers, send the headers, and then send the file data as it arrives from the database.
  // this essentially puts the memory usage to around
  // make sure to perform output buffer (if possible) to reduce chances of "headers already sent" issues.
  $ob_level = ob_get_level();
  for ($i = 0; $i < $ob_level; $i++) {
    ob_clean();
  }
  ob_start();

  $headers = array();

  if ($information['not_modified']) {
    $headers['status'] = array('value' => '304 Not Modified', 'status_code' => 304);
  }
  else {
    $headers['status'] = array('value' => '200 OK', 'status_code' => 200);
  }

  $filename = NULL;
  if (!empty($information['file']['filename'])) {
    $filename = trim($information['file']['filename']);
  }
  else {
    if (!empty($information['file']['shortsum'])) {
      $filename = $information['file']['shortsum'];
    }
    else {
      $filename = 'download';
    }
  }

  $extension = NULL;
  if (!empty($information['file']['extension'])) {
    $extension = '.' . trim($information['file']['extension']);
  }

  $headers['Accept-Ranges'] = array('value' => 'File Transfer');
  $headers['Content-Description'] = array('value' => 'File Transfer');
  $headers['Content-Disposition'] = array('value' => 'inline; filename="' . $filename . $extension . '"');
  $headers['Content-Length'] = array('value' => $information['file']['size']);
  $headers['Content-Location'] = array('value' => $settings['base_path'] . MCNEESE_FILE_DB_FILE_PATH . '/' . MCNEESE_FILE_DB_PATH_BY_HASH . '/' . $information['file']['shortsum'] . '/' . $filename . $extension);
  $headers['Content-Type'] = array('value' => $information['file']['mimetype']);
  $headers['Date'] = array('value' => date('r', $instance));
  $headers['Etag'] = array('value' => '"' . MCNEESE_FILE_DB_PATH_BY_HASH_ALGORITHM . '://' . $information['file']['checksum'] . '"');
  $headers['Last-Modified'] = array('value' => date('r', $information['file']['timestamp']));
  $headers['content-transfer-encoding'] = array('value' => 'binary');
  unrestricted_send_headers($headers);

  // when there are no changes, there is no reason to transmit the file (this is a client-side caching optimization).
  if ($information['not_modified']) {
    ob_end_flush();
    pg_close($information['connection']);
    $information['connection'] = FALSE;
    return;
  }

  if (isset($results)) {
    while ($row = pg_fetch_row($results)) {
      print(pg_unescape_bytea($row[0]));
    }

    pg_free_result($results);
  }

  ob_end_flush();
  pg_close($information['connection']);
  $information['connection'] = FALSE;
}

/**
 * Prints the file hash data in a format that programs like sha256 support.
 *
 * @param array $information
 *   An already processed file information array.
 * @param array $settings
 *   An already loaded settings array.
 *
 * @see: unrestricted_get_file_information()
 */
function unrestricted_get_file_hash(&$information, $settings) {
  // connection is no longer needed by this function.
  if ($information['connection'] !== FALSE) {
    pg_close($information['connection']);
    $information['connection'] = FALSE;
  }

  if (empty($information['file']['id']) || !is_numeric($information['file']['id'])) {
    unrestricted_not_found();
  }

  $instance = unrestricted_get_instance();

  $headers = array();

  if ($information['not_modified']) {
    $headers['status'] = array('value' => '304 Not Modified', 'status_code' => 304);
  }
  else {
    $headers['status'] = array('value' => '200 OK', 'status_code' => 200);
  }

  $data = $information['file']['checksum'] . '  ' . $information['file']['filename'] . '.' . $information['file']['extension'];

  $headers['Accept-Ranges'] = array('value' => 'File Transfer');
  $headers['Content-Description'] = array('value' => 'File Transfer');
  $headers['Content-Disposition'] = array('value' => 'inline; filename="' . $information['file']['filename'] . '.' . MCNEESE_FILE_DB_PATH_BY_HASH_SUM_EXTENSION . '"');
  $headers['Content-Length'] = array('value' => strlen($data));
  $headers['Content-Location'] = array('value' => $settings['base_path'] . MCNEESE_FILE_DB_FILE_PATH . '/' . MCNEESE_FILE_DB_PATH_BY_HASH_SUM . '/' . $information['file']['shortsum'] . '/' . $information['file']['filename'] . '.' . $information['file']['extension'] . '.' . MCNEESE_FILE_DB_PATH_BY_HASH_SUM_EXTENSION);
  $headers['Content-Type'] = array('value' => 'text/plain');
  $headers['Date'] = array('value' => date('r', $instance));
  $headers['Etag'] = array('value' => '"' . MCNEESE_FILE_DB_PATH_BY_HASH_ALGORITHM . '://' . $information['file']['checksum'] . '.' . MCNEESE_FILE_DB_PATH_BY_HASH_SUM_EXTENSION . '"');
  $headers['Last-Modified'] = array('value' => date('r', $information['file']['timestamp']));
  $headers['content-transfer-encoding'] = array('value' => 'binary');
  unrestricted_send_headers($headers);

  // when there are no changes, there is no reason to transmit the file (this is a client-side caching optimization).
  if ($information['not_modified']) {
    return;
  }

  print($data);
}

/**
 * Gets the current timestamp of the connection.
 *
 * If the server did not supply a timestamp, one will be generated.
 * This utilizes a static variable, so it will be queried only once.
 *
 * @param bool $reset
 *   (optional) If TRUE, then the instance timestamp will be re-generated.
 *
 * @return int
 *   Unix timestamp for the current time.
 */
function unrestricted_get_instance($reset = FALSE) {
  static $instance = NULL;

  if (!is_null($instance) && $reset === FALSE) {
    return $instance;
  }

  if ($reset === TRUE) {
    $instance = strtotime('now');
    return $instance;
  }

  if (array_key_exists('REQUEST_TIME', $_SERVER)) {
    $instance = (int) $_SERVER['REQUEST_TIME'];
  }

  if (empty($instance)) {
    $instance = strtotime('now');
  }

  return $instance;
}

unrestricted_main();
