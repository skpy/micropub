<?php
function http_status ($code) {
    $http_codes = array(
        '200' => '200 OK',
        '201' => '201 Created',
        '202' => '202 Accepted',
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

function quit ($code = 400, $error = '', $description = 'An error occurred.', $location = '') {
    $code = (int) $code;
    header("HTTP/1.1 " . http_status($code));
    if ( $code >= 400 ) {
        echo json_encode(['error' => $error, 'error_description' => $description]);
    } elseif ($code == 200 || $code == 201 || $code == 202) {
        if (!empty($location)) {
            header('Location: ' . $location);
            echo $location;
        }
    }
    die();
}

function show_info() {
    echo '<p>This is a <a href="https://www.w3.org/TR/micropub/">micropub</a> endpoint.</p>';
    die();
}

function parse_request() {
    if ( strtolower($_SERVER['CONTENT_TYPE']) == 'application/json' || strtolower($_SERVER['HTTP_CONTENT_TYPE']) == 'application/json' ) {
        $request = \p3k\Micropub\Request::createFromJSONObject(json_decode(file_get_contents('php://input'), true));
    } else {
        $request = \p3k\Micropub\Request::createFromPostArray($_POST);
    }
    if($request->error) {
        quit(400, $request->error_property, $request->error_description);
    }
    return $request;
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
 * @param string $me the site to authorize
 *
 * @return boolean true if authorised
 */
function indieAuth($endpoint, $token, $me = '') {
    /**
     * Check token is valid
     */
    if ( $me == '' ) { $me = $_SERVER['HTTP_HOST']; }
    $ch = curl_init($endpoint);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_HTTPHEADER, 
        Array("Accept: application/json","Authorization: $token"));
    $token_response = strval(curl_exec($ch));
    curl_close($ch);
    if (empty($token_response)) {
        # strval(FALSE) is an empty string
        quit(502, 'connection_problem', 'Unable to connect to token service');
    }
    $response = json_decode($token_response, true, 2);
    if (!is_array($response) || json_last_error() !== \JSON_ERROR_NONE) {
        parse_str($token_response, $response);
    }
    if (empty($response) || isset($response['error']) || ! isset($response['me']) || ! isset($response['scope']) ) {
        quit(401, 'insufficient_scope', 'The request lacks authentication credentials');
    } elseif ($response['me'] != $me) {
        quit(401, 'insufficient_scope', 'The request lacks valid authentication credentials');
    } elseif (is_array($response['scope']) && !in_array('create', $response['scope']) && !in_array('post', $response['scope'])) {
        quit(403, 'forbidden', 'Client does not have access to this resource');
    } elseif (FALSE === stripos($response['scope'], 'create')) {
        quit(403, 'Forbidden', 'Client does not have access to this resource');
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

    $media_endpoint = isset($config['media_endpoint']) ?
	$config['media_endpoint'] :
	($config['base_url'] . 'micropub/index.php');
    $conf = array("media-endpoint" => $media_endpoint);
    if ( ! empty($syndicate_to) ) {
        $conf['syndicate-to'] = $syndicate_to;
    }

    header('Content-Type: application/json');
    if ($show == "syndicate-to") {
        echo json_encode(array('syndicate-to' => $syndicate_to), 32 | 64 | 128 | 256);
    } else {
        echo json_encode($conf, 32 | 64 | 128 | 256);
    }
    exit;
}

function build_site() {
    global $config;
    exec( $config['command']);
}

# PHP handles arrays of file uploads differently from a single file upload.
# So we need to normalize this into a common structure upon which we can act.
function normalize_files_array($files) {
    $result = [];
    if (isset($files['tmp_name']) && is_array($files['tmp_name'])) {
        # we have an array of one or more elements.
        foreach (array_keys($files['tmp_name']) as $key) {
            $result[] = [
                'tmp_name' => $files['tmp_name'][$key],
                'size' => $files['size'][$key],
                'error' => $files['error'][$key],
                'name' => $files['name'][$key],
                'type' => $files['type'][$key],
            ];
        }
    } else {
        # make sure we return an array, so we can iterate over it
        $result = [ $files ];
    }
    return $result;
}

function check_target_dir($target_dir) {
    if ( empty($target_dir)) {
        quit(400, 'unknown_dir', 'Unspecified directory');
    }
    # make sure our upload directory exists
    if ( ! file_exists($target_dir) ) {
        # fail if we can't create the directory
        if ( FALSE === mkdir($target_dir, 0755, true) ) {
            quit(400, 'cannot_mkdir', 'The directory could not be created.');
        }
    }
}
?>
