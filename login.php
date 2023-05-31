<?php


ldap_set_option(NULL, LDAP_OPT_DEBUG_LEVEL, 7);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);


// Load the configuration file
require '/etc/apache2/config.php';
//require_once('duo_universal_php.php');


// Get the form data
$username = $_POST['username'];
$password = $_POST['password'];



// Connect to the LDAP server
$ldapconn = ldap_connect($ldaphost);
ldap_set_option($ldapconn, LDAP_OPT_PROTOCOL_VERSION, 3);
ldap_set_option($ldapconn, LDAP_OPT_REFERRALS, 0);



if ($ldapconn) {
    // Try to bind to the LDAP server with the bind DN and password
    $ldapbind = ldap_bind($ldapconn, $ldapbinddn, $ldappass);

    if ($ldapbind) {
        // Prepare a filter to search for the user
        $filter = "(sAMAccountName=$username)";
        $result = ldap_search($ldapconn, $ldapdn, $filter);

        if (!$result) {
            die('Error in search query: '.ldap_error($ldapconn));
        }

        // Check if the user was found
        $entries = ldap_count_entries($ldapconn, $result);

        if ($entries === false) {
            die('Error in count_entries: '.ldap_error($ldapconn));
        }

        if ($entries > 0) {
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
            $userbind = ldap_bind($ldapconn, $userdn, $password);

            if ($userbind) {
                // The user's username and password are correct
                header('Location: success.html');
                exit();
            } else {
                // The user's password is incorrect
                header('Location: failure.html');
                exit();
            }
        } else {
            // The user was not found
            header('Location: failure.html');
            exit();
        }
    } else {
        // Could not bind to the LDAP server (invalid bind DN or password)
        die('Could not bind to the LDAP server. Please check the bind DN and password.');
    }
} else {
    // Could not connect to the LDAP server
    die('Could not connect to the LDAP server. Please check the host and port.');
}

?>

