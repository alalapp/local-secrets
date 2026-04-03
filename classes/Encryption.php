<?php
/**
 * AES-256-CBC шифрование/дешифрование значений секретов
 */
class Encryption {
    private const METHOD = 'aes-256-cbc';

    /**
     * Зашифровать строку
     */
    public static function encrypt(string $plaintext): string {
        if ($plaintext === '') {
            return '';
        }
        $key = hex2bin(ENCRYPTION_KEY);
        $ivLength = openssl_cipher_iv_length(self::METHOD);
        $iv = openssl_random_pseudo_bytes($ivLength);
        $encrypted = openssl_encrypt($plaintext, self::METHOD, $key, OPENSSL_RAW_DATA, $iv);
        // Формат: base64(iv + encrypted)
        return base64_encode($iv . $encrypted);
    }

    /**
     * Расшифровать строку
     */
    public static function decrypt(string $ciphertext): string {
        if ($ciphertext === '') {
            return '';
        }
        $key = hex2bin(ENCRYPTION_KEY);
        $data = base64_decode($ciphertext);
        $ivLength = openssl_cipher_iv_length(self::METHOD);
        $iv = substr($data, 0, $ivLength);
        $encrypted = substr($data, $ivLength);
        $decrypted = openssl_decrypt($encrypted, self::METHOD, $key, OPENSSL_RAW_DATA, $iv);
        return $decrypted !== false ? $decrypted : '';
    }
}
