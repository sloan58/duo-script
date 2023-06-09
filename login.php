<?php

// https://github.com/duosecurity/duo_universal_php/blob/main/example/index.php

use Duo\DuoUniversal\Client;
use Duo\DuoUniversal\DuoException;

require __DIR__ . '/vendor/autoload.php';

ldap_set_option(NULL, LDAP_OPT_DEBUG_LEVEL, 7);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

// Load the configuration file
$config = require 'config.php'; // Marty's local dev env
//require '/etc/apache2/config.php';
//require_once('duo_universal_php.php');

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

// Get the form data
$username = $_POST['username'];
$password = $_POST['password'];


// Connect to the LDAP server
$ldapconn = ldap_connect($config['ldap']['host']);
ldap_set_option($ldapconn, LDAP_OPT_PROTOCOL_VERSION, 3);
ldap_set_option($ldapconn, LDAP_OPT_REFERRALS, 0);



if (! $ldapconn) {
    // Could not connect to the LDAP server
    die('Could not connect to the LDAP server. Please check the host and port.');
}

// Try to bind to the LDAP server with the bind DN and password
$ldapbind = ldap_bind($ldapconn, $config['ldap']['bind_dn'], $config['ldap']['password']);

if (! $ldapbind) {
    // Could not bind to the LDAP server (invalid bind DN or password)
    die('Could not bind to the LDAP server. Please check the bind DN and password.');
}

// Prepare a filter to search for the user
$filter = "(sAMAccountName=$username)";
$result = ldap_search($ldapconn, $config['ldap']['search_base'], $filter);

if (!$result) {
    die('Error in search query: '.ldap_error($ldapconn));
}

// Check if the user was found
$entries = ldap_count_entries($ldapconn, $result);

if ($entries === false) {
    die('Error in count_entries: '.ldap_error($ldapconn));
}

if ($entries < 0) {
    // The user was not found
    header('Location: failure.html');
    exit();
}

// Get the first entry from the search result
$entry = ldap_first_entry($ldapconn, $result);

if (!$entry) {
    die('Error in first_entry: '.ldap_error($ldapconn));
}

// Get the DN of the user's entry
$userdn = ldap_get_dn($ldapconn, $entry);

if (!$userdn) {
    die('Error getting DN: '.ldap_error($ldapconn));
}

// Try to bind again using the user's DN and password
$userBind = ldap_bind($ldapconn, $userdn, $password);

if ($userBind) {
    // The user's username and password are correct
    try {
        $duo_client->healthCheck();
    } catch (DuoException $e) {
        die("*** Duo health check failed ***\n" . $e->getMessage());
    }

    $state = $duo_client->generateState();

    $_SESSION["state"] = $state;
    $_SESSION["username"] = $username;

    # Redirect to prompt URI which will redirect to the client's redirect URI after 2FA
    $prompt_uri = $duo_client->createAuthUrl($username, $state);
    header("Location: $prompt_uri");
} else {
    // The user's password is incorrect
    header('Location: failure.html');
}
exit();
