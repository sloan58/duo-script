<?php

use Duo\DuoUniversal\Client;
use Duo\DuoUniversal\DuoException;

require __DIR__ . '/vendor/autoload.php';

session_start();

$config = require 'config.php'; // Marty's local dev env
//require '/etc/apache2/config.php';

try {
    $duo_client = new Client(
        client_id: $config['duo']['client_id'],
        client_secret: $config['duo']['client_secret'],
        api_host: $config['duo']['api_hostname'],
        redirect_url: $config['duo']['redirect_uri'],
        use_duo_code_attribute: true,
        http_proxy: '',
    );
} catch (DuoException $e) {
    die("*** Duo config error. Verify the values in duo.conf are correct ***\n" . $e->getMessage());
}

$query_params = $_GET;

# Check for errors from the Duo authentication
if (isset($query_params["error"])) {
    $error_msg = $query_params["error"] . ":" . $query_params["error_description"];
    die($error_msg);
}

# Get authorization token to trade for 2FA
$code = $query_params["duo_code"];

# Get state to verify consistency and originality
$state = $query_params["state"];

# Retrieve the previously stored state and username from the session
$saved_state = $_SESSION["state"];
$username = $_SESSION["username"];

if (empty($saved_state) || empty($username)) {
    # If the URL used to get to login.php is not localhost, (e.g. 127.0.0.1), then the sessions will be different
    # and the localhost session will not have the state.
    $args["message"] = "No saved state please login again";
    return die($args["message"]);
}

# Ensure nonce matches from initial request
if ($state != $saved_state) {
    $args["message"] = "Duo state does not match saved state";
    die($args["message"]);
}

try {
    $decoded_token = $duo_client->exchangeAuthorizationCodeFor2FAResult($code, $username);
} catch (DuoException $e) {
    $args["message"] = "Error decoding Duo result. Confirm device clock is correct.";
    die($args["message"]);
}

# Exchange happened successfully so render success page
$args["message"] = json_encode($decoded_token, JSON_PRETTY_PRINT);

//var_dump($args);
header('Location: success.html');
