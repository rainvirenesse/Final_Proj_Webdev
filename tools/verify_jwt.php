<?php

declare(strict_types=1);

function b64url_decode(string $s): string
{
    $s = strtr($s, '-_', '+/');
    $pad = (4 - (strlen($s) % 4)) % 4;
    if ($pad) {
        $s .= str_repeat('=', $pad);
    }
    $decoded = base64_decode($s, true);
    if ($decoded === false) {
        throw new RuntimeException('Base64 decode failed');
    }
    return $decoded;
}

if ($argc < 2) {
    fwrite(STDERR, "Usage: php tools/verify_jwt.php <token>\n");
    exit(2);
}

$token = trim((string) $argv[1]);
$parts = explode('.', $token);
if (count($parts) !== 3) {
    fwrite(STDERR, "Token must have 3 parts\n");
    exit(2);
}

[$h, $p, $s] = $parts;
$data = $h . '.' . $p;
$sig = b64url_decode($s);
$pubPem = file_get_contents(__DIR__ . '/../config/jwt/public.pem');
if ($pubPem === false) {
    fwrite(STDERR, "Could not read public key\n");
    exit(2);
}

$key = openssl_pkey_get_public($pubPem);
if ($key === false) {
    fwrite(STDERR, "Could not parse public key\n");
    exit(2);
}

$ok = openssl_verify($data, $sig, $key, OPENSSL_ALGO_SHA256);
echo "verify={$ok}\n";
echo "header=" . b64url_decode($h) . "\n";
echo "payload=" . b64url_decode($p) . "\n";

