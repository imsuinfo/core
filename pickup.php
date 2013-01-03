<?php
  $debug = false;
  $cookie = '';
  $destination = '';
  $session = '';
  $target = '';
  $cookie_has_been_set = FALSE;
  $error = NULL;

  if (!empty($_GET['dest'])) {
    $destination = $_GET['dest'];
  }

  if (!empty($_GET['cookie'])) {
    $cookie = $_GET['cookie'];
  }

  if (!$debug) {
    $debug = isset($_GET['debug']);
  }

  // drupal-specific deconstruction of the url parameter
  $session = array();
  $expire = array();
  $matched_session = preg_match('/(\bSSESS\w*\b)=([^;]*)(;|$)/i', $cookie, $session);
  $matched_expire = preg_match('/\bexpires=([^;]*)(;|$)/i', $cookie, $expire);


  // the following are custom parameters defined on a per-system basis.
  $path = '/';
  $secure = TRUE;
  $httponly = TRUE;

  if (empty($_SERVER["SERVER_NAME"])) {
    $domain = '.www.mcneese.edu';
  }
  else {
    $domain = '.' . $_SERVER["SERVER_NAME"];
  }

  if ($matched_session > 0 && $matched_expire > 0) {
    $expires_on = strtotime($expire[1]);
    $cookie_has_been_set = setcookie($session[1], $session[2], $expires_on, $path, $domain, $secure, $httponly);

    if (!$cookie_has_been_set) {
      $error = "Error: Failed to set cookie.";
    }
  }
  else {
    $error = "Error: Failed to process/find the required cookie parameters.";
  }

  // force redirect when not debugging
  if (!$debug) {
    if (!empty($cookie) && !empty($destination)) {
      Header("HTTP/1.1 303 See other");
      Header("Location: " . $destination);
    }
    else {
      $error = "Unable to redirect because either the cookie or the destination have not been properly defined.";
    }
  }
?><!DOCTYPE html>
<html>
<head>
  <title>SSO Login</title>
  <meta http-equiv="cache-control" content="no-cache">
  <meta http-equiv="content-type" content="text/html; charset=UTF-8">
  <meta charset="UTF-8">
  <meta name="robots" content="NONE">
  <meta name="googlebot" content="noarchive,nofollow,noindex,nosnippet">
</head>

<body><?php
  if ($debug) {
    print("<strong>Destination</strong> = " . $destination . "<br><br>");
    print("<strong>Cookie</strong> = " . $cookie . "<br><br>");

    if ($matched_session > 0) {
      print("<strong>Session Name</strong> = " . $session[1] . "<br><br>");
      print("<strong>Session Value</strong> = " . $session[2] . "<br><br>");
    }
    else {
      print("Did not find session name or value<br><br>");
    }

    if ($matched_expire > 0) {
      print("<strong>Expires On</strong> = " . $expire[1] . "<br><br>");
    }
    else {
      print("Did not find an expire value<br><br>");
    }

    if ($cookie_has_been_set && isset($_COOKIE[$session[1]])) {
      print("The cookie has been set.<br><br>");
    }
    else {
      print("The cookie has not been set.<br><br>");
    }
  }

  if (!empty($error)) {
    print('<h1>SSO Login Error</h1>');
    print('<div>An error occured while trying to log you in to <em>' . $domain . '</em> at <em>' . $destination . '</em>.</div>');

    print('<h2>Reason</h2>');
    print('<div>' . $error . '</div>');
  }
?></body>
</html>
