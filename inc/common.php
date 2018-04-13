<?php
function http_status ($code) {
    $http_codes = array(
        '200' => '200 OK',
        '201' => '201 Created',
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

function quit ($code = 400, $error = '', $description = 'An error occurred.') {
    $status = 'success';
    header("HTTP/1.1 " . http_status($code));
    if ( int($code) >= 400 ) {
        echo json_encode(['error' => $error, 'error_description' => $description]);
    }
    die();
}

function show_info() {
    echo '<p>This is a <a href="https://www.w3.org/TR/micropub/">micropub</a> endpoint.</p>';
    die();
}

/**
 * Validate incoming requests, using IndieAuth
 *
 * This section largely adopted from rhiaro
 *
 * @param array $token the authorization token to check
 * @param string $me the site to authorize
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
        quit(502, 'connection_problem', 'Unable to connect to indieauth service');
    }
    parse_str($curl_response, $response);
    curl_close($ch);
    if (empty($response) || ! isset($response['me']) || ! isset($response['scope']) ) {
        quit(401, 'insufficient_scope', 'The request lacks authentication credentials');
    } elseif ($response['me'] != $me) {
        quit(401, 'insufficient_scope', 'The request lacks valid authentication credentials');
    } elseif (!in_array('create', $scopes) && !in_array('post', $scopes)) {
        quit(403, 'forbidden', 'Client does not have access to this resource');
    }
    // we got here, so all checks passed. return true.
    return true;
}

# respond to queries about config and/or syndication
function show_config($show = 'all') {
    global $config;
    $syndicate_to = array();
    if ( ! empty($config['syndication'])) {
        foreach ($config['syndication'] as $k => $v) {
            $syndicate_to[] = array('uid' => $k, 'name' => $k);
        }
    }

    $conf = array("media-endpoint" => 'https://' . $config['base_url'] . '/micropub/media.php');
    if ( ! empty($syndicate_to) ) {
        $conf['syndicate_to'] = $syndicate_to;
    }

    header('Content-Type: application/json');
    if ($show == "syndicate-to") {
        echo json_encode(array('syndicate-to' => $syndicate_to), 32 | 64 | 128 | 256);
    } else {
        echo json_encode($conf, 32 | 64 | 128 | 256);
    }
    exit;
}

# ensure that this battle station is fully operational.
function check_target_dir($target_dir = '') {
 if ( empty($target_dir)) {
    quit(400, 'unknown_dir', 'Unspecified directory');
  }
  # make sure our upload directory exists
  if ( ! file_exists($target_dir) ) {
    # fail if we can't create the directory
    if ( FALSE === mkdir($target_dir, 0777, true) ) {
      quit(400, 'cannot_mkdir', 'The upload directory could not be created.');
    }
  }
}
?>
