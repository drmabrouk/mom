<?php
if (!defined('ABSPATH')) exit;

class TM_Auth {
    private $audit;

    public function __construct($audit) {
        $this->audit = $audit;
    }

    public function login($username, $password) {
        if ($username === 'ahmed' && $password === '10111996') {
            $token = bin2hex(random_bytes(32));
            update_option('tm_auth_token', $token);
            setcookie('tm_auth_token', $token, time() + 86400, COOKIEPATH, COOKIE_DOMAIN, is_ssl(), true);

            $this->audit->log('ACCESS', 'LOGIN_SUCCESS', "User: $username");
            return true;
        }

        $this->audit->log('ACCESS', 'LOGIN_FAILURE', "Attempted: $username | PWD: $password");
        return false;
    }

    public function logout() {
        delete_option('tm_auth_token');
        setcookie('tm_auth_token', '', time() - 3600, COOKIEPATH, COOKIE_DOMAIN, is_ssl(), true);
        $this->audit->log('ACCESS', 'LOGOUT', 'User: ahmed');
    }

    public function is_authenticated() {
        $token = get_option('tm_auth_token');
        return $token && isset($_COOKIE['tm_auth_token']) && $_COOKIE['tm_auth_token'] === $token;
    }
}
