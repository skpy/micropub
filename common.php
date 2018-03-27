<?php
function http_status ($code) {
  $http_codes = array(
    '200' => '200 OK',
    '400' => '400 Bad Request',
    '401' => '401 Unauthorized',
    '403' => '403 Forbidden',
    '409' => '409 Conflict',
    '413' => '413 Payload Too Large',
    '415' => '415 Unsupported Media Type',
    '502' => '502 Bad Gateway',
   );
  return $http_codes[$code];
}

function quit ($error, $description = 'An error occurred.', $code = '400') {
  header("HTTP/1.1 " . http_status($code));
  echo json_encode(['error' => $error, 'error_description' => $description]);
  die();
}

/**
 * API call function. This could easily be used for any modern writable API
 *
 * @param $url    adressable url of the external API
 * @param $auth   authorisation header for the API
 * @param $adata  php array of the data to be sent
 *
 * @return HTTP response from API
 */
function post_to_api($url, $auth, $adata)
{
    $fields = '';
    foreach ($adata as $key => $value) {
        $fields .= $key . '=' . $value . '&';
    }
    rtrim($fields, '&');
    $post = curl_init();
    curl_setopt($post, CURLOPT_URL, $url);
    curl_setopt($post, CURLOPT_POST, count($adata));
    curl_setopt($post, CURLOPT_POSTFIELDS, $fields);
    curl_setopt($post, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt(
        $post, CURLOPT_HTTPHEADER, array(
        'Content-Type: application/x-www-form-urlencoded',
        'Authorization: '.$auth
        )
    );
    $result = curl_exec($post);
    curl_close($post);
    return $result;
}

/**
 * getallheaders() replacement for nginx
 *
 * Replaces the getallheaders function which relies on Apache
 *
 * @return array incoming headers from _POST
 */
if (!function_exists('getallheaders')) {
  function getallheaders() {
    $headers = [];
    foreach ($_SERVER as $name => $value) {
      if (substr($name, 0, 5) == 'HTTP_') {
        $headers[str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))))] = $value;
      }
    }
    return $headers;
  }
}

/**
 * Validate incoming requests, using IndieAuth
 *
 * This section largely adopted from rhiaro
 *
 * @param array $token the authorization token to check
 *
 * @return boolean true if authorised
 */
function indieAuth($token, $me = '') {
  /**
   * Check token is valid
   */
  if ( $me == '' ) { $me = $_SERVER['HTTP_HOST']; }
  $ch = curl_init("https://tokens.indieauth.com/token");
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
  curl_setopt($ch, CURLOPT_HTTPHEADER, Array("Authorization: $token"));
  $response = Array();
  $curl_response = curl_exec($ch);
  if (false === $curl_response) {
    quit('noauth', 'Unable to connect to indiauth service', '502');
  }
  parse_str($curl_response, $response);
  curl_close($ch);
  if (! isset($response['me']) && ! isset($response['scope']) ) {
  }
  //$me = $response['me'];
  //$scopes = explode(' ', $response['scope']);
  if (empty($response) || ! isset($response['me']) || ! isset($response['scope']) ) {
    quit('unauthorized', 'The request lacks authentication credentials', '401');
  } elseif ($response['me'] != $me) {
    quit('unauthorized', 'The request lacks valid authentication credentials', '401');
  } elseif (!in_array('create', $scopes) && !in_array('post', $scopes)) {
    quit('denied', 'Client does not have access to this resource', '403');
  }
  // we got here, so all checks passed. return true.
  return true;
}

# respond to queries about config and syndication
function what_can_i_do() {
  if ($_GET['q'] == "syndicate-to") {
  $array = array(
    "syndicate-to" => array(
      0 => array(
        "uid" => "https://twitter.com",
        "name" => "Twitter"
      ),
    )
  );
  header('Content-Type: application/json');
  echo json_encode($array, 32 | 64 | 128 | 256);
  exit;
  }

  if ($_GET['q'] == "config") {
    $array = array(
      "media-endpoint" => 'https://' . $_SERVER['HTTP_HOST'] . '/micropub/media.php',
      "syndicate-to" => array(
        0 => array(
          "uid" => "https://twitter.com",
          "name" => "Twitter"
        ),
      )
    );
    header('Content-Type: application/json');
    echo json_encode($array, 32 | 64 | 128 | 256);
    exit;
  }
}

# ensure that this battle station is fully operational.
function check_target_dir($target_dir = '') {
 if ( empty($target_dir)) {
    quit('unknown_dir', 'Unspecified directory', 400);
  }
  # make sure our upload directory exists
  if ( ! file_exists($target_dir) ) {
    # fail if we can't create the directory
    if ( FALSE === mkdir($target_dir, 0777, true) ) {
      quit('cannot_mkdir', 'The upload directory could not be created.', '400');
    }
  }
}
?>
