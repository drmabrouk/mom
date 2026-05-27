<?php
if (!defined('ABSPATH')) exit;

function tm_get_encryption_key() {
    $key = get_option('tm_encryption_key');
    if (!$key) {
        $key = bin2hex(openssl_random_pseudo_bytes(32));
        update_option('tm_encryption_key', $key);
    }
    return hex2bin($key);
}

function tm_encrypt_data($data) {
    if (empty($data)) return $data;
    $key = tm_get_encryption_key();
    $iv_length = openssl_cipher_iv_length('aes-256-cbc');
    $iv = openssl_random_pseudo_bytes($iv_length);
    $encrypted = openssl_encrypt($data, 'aes-256-cbc', $key, 0, $iv);
    return base64_encode($iv . $encrypted);
}

function tm_decrypt_data($data) {
    if (empty($data)) return $data;
    $key = tm_get_encryption_key();
    $data = base64_decode($data);
    $iv_length = openssl_cipher_iv_length('aes-256-cbc');
    $iv = substr($data, 0, $iv_length);
    $encrypted = substr($data, $iv_length);
    return openssl_decrypt($encrypted, 'aes-256-cbc', $key, 0, $iv);
}
