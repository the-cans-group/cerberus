<?php

return [
    'token' => [
        'rounds' => 16, // hash rounds
        'prefix' => 'cerberus', // Token prefix
        'encoding' => 'base64url', // base64 / base64url / hex
        'hash_enabled' => true, // Token hashing
        'hash_driver' => 'argon2id', // Hashing driver: bcrypt, argon2i, argon2id
    ],

    'lifetime' => [
        'expires_in' => 60 * 24 * 7, // 7 days in minutes
        'prune_schedule' => '0 3 * * *', // Daily at 3 AM
    ],

    'headers' => [
        'device_type'  => 'X-Device-Type', // Device type header
        'app_version'  => 'X-App-Version', // App version header
        'os_version'   => 'X-OS-Version', // OS version header
        'device_fingerprint' => 'X-Device-Fingerprint', // Device fingerprint header
    ],

    'track' => [
        'ip' => true, // Track IP address
        'user_agent' => true, // Track User-Agent header
        'app_version' => true, // Track app version
        'os_version' => true, // Track OS version
        'device_type' => true, // Track device type
        'device_fingerprint' => true, // Track device fingerprint
    ],

    'revocation' => 'soft', // soft | hard
];
