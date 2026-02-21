<?php
declare(strict_types=1);

function app_key_bytes(): string
{
    $key = (string) config('app_key');
    if ($key === '') {
        throw new RuntimeException('APP_KEY is not configured.');
    }
    return hash('sha256', $key, true);
}

function encrypt_secret(string $plaintext): string
{
    if ($plaintext === '') {
        return '';
    }

    $nonce = random_bytes(12);
    $tag = '';
    $ciphertext = openssl_encrypt(
        $plaintext,
        'aes-256-gcm',
        app_key_bytes(),
        OPENSSL_RAW_DATA,
        $nonce,
        $tag
    );

    if ($ciphertext === false || $tag === '') {
        throw new RuntimeException('Encryption failed.');
    }

    return base64_encode($nonce . $tag . $ciphertext);
}

function decrypt_secret(string $encoded): string
{
    if ($encoded === '') {
        return '';
    }

    $data = base64_decode($encoded, true);
    if ($data === false || strlen($data) < 28) {
        return '';
    }

    $nonce = substr($data, 0, 12);
    $tag = substr($data, 12, 16);
    $ciphertext = substr($data, 28);

    $plaintext = openssl_decrypt(
        $ciphertext,
        'aes-256-gcm',
        app_key_bytes(),
        OPENSSL_RAW_DATA,
        $nonce,
        $tag
    );

    return $plaintext === false ? '' : $plaintext;
}
