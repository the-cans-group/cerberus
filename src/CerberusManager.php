<?php

namespace Cerberus;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Carbon;
use Random\RandomException;

/**
 * CerberusManager handles the creation, management, and revocation of user sessions.
 * It generates access tokens, tracks device information, and manages session lifetimes.
 */
class CerberusManager
{
    /**
     * Generates a token based on the provided payload.
     *
     * @param array $payload The data to encode in the token.
     * @return string The generated token.
     */
    public function generateToken(array $payload): string
    {
        $encoding = config('cerberus.token.encoding', 'base64url');
        $prefix = config('cerberus.token.prefix', 'cerberus');

        $encoded = match ($encoding) {
            'base64url' => rtrim(strtr(base64_encode(json_encode($payload)), '+/', '-_'), '='),
            'base64'    => base64_encode(json_encode($payload)),
            'hex'       => bin2hex(json_encode($payload)),
            default     => base64_encode(json_encode($payload)),
        };

        return $prefix . $encoded;
    }

    /**
     * Creates a new session for the given user, tracking device information and generating an access token.
     *
     * @param Authenticatable $user The user for whom the session is being created.
     * @return string The generated access token.
     * @throws RandomException
     */
    public function createSession(Authenticatable $user): string
    {
        $request = Request::instance();

        $fingerprint = $request->header(config('cerberus.headers.device_fingerprint'));
        $deviceType  = $request->header(config('cerberus.headers.device_type'));
        $appVersion  = $request->header(config('cerberus.headers.app_version'));
        $osVersion   = $request->header(config('cerberus.headers.os_version'));
        $ip          = $request->ip();
        $userAgent   = $request->userAgent();

        if (config('cerberus.track.device_type') && ! $deviceType)        abort(400, 'Missing device type');
        if (config('cerberus.track.app_version') && ! $appVersion)        abort(400, 'Missing app version');
        if (config('cerberus.track.os_version') && ! $osVersion)          abort(400, 'Missing OS version');
        if (config('cerberus.track.ip') && ! $ip)                         abort(400, 'Missing IP address');
        if (config('cerberus.track.user_agent') && ! $userAgent)         abort(400, 'Missing User-Agent header');
        if (config('cerberus.track.device_fingerprint') && ! $fingerprint) abort(400, 'Missing device fingerprint');

        $device = DB::table('cerberus_user_devices')
            ->where('authenticatable_type', get_class($user))
            ->where('authenticatable_id', $user->getAuthIdentifier())
            ->where('device_fingerprint', $fingerprint)
            ->first();

        if (! $device) {
            $deviceId = (string) Str::uuid();
            DB::table('cerberus_user_devices')->insert([
                'id' => $deviceId,
                'authenticatable_type' => get_class($user),
                'authenticatable_id'   => $user->getAuthIdentifier(),
                'device_fingerprint'   => config('cerberus.track.device_fingerprint') ? $fingerprint: null,
                'device_type'          => config('cerberus.track.device_type') ? $deviceType : null,
                'app_version'          => config('cerberus.track.app_version') ? $appVersion : null,
                'os_version'           => config('cerberus.track.os_version') ? $osVersion : null,
                'ip'                   => config('cerberus.track.ip') ? $ip : null,
                'user_agent'           => config('cerberus.track.user_agent') ? $userAgent : null,
                'is_trusted'           => false,
                'created_at'           => now(),
                'updated_at'           => now(),
            ]);
        } else {
            $deviceId = $device->id;
        }

        $payload = [
            'uid' => (string) Str::uuid(),
            'fp'  => $fingerprint,
            'ts'  => now()->timestamp,
            'rnd' => bin2hex(random_bytes(config('cerberus.token.rounds', 16))),
        ];

        $accessToken  = $this->generateToken($payload);
        $tokenToStore = config('cerberus.token.hash_enabled')
            ? Hash::make($accessToken, ['driver' => config('cerberus.token.hash_driver')])
            : $accessToken;

        DB::table('cerberus_user_device_sessions')->insert([
            'id' => (string) Str::uuid(),
            'device_id' => $deviceId,
            'access_token' => $tokenToStore,
            'is_active' => true,
            'expires_at' => now()->addMinutes(config('cerberus.lifetime.expires_in')),
            'ip' => $ip,
            'last_activity_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $accessToken;
    }

    /**
     * Finds a session by its access token.
     *
     * @param string $token The access token to search for.
     * @return object|null The session object if found, null otherwise.
     */
    public function findSessionByToken(string $token): ?object
    {
        if (! config('cerberus.token.hash_enabled')) {
            return DB::table('cerberus_user_device_sessions as s')
                ->join('cerberus_user_devices as d', 's.device_id', '=', 'd.id')
                ->select('s.*', 'd.device_fingerprint', 'd.authenticatable_type', 'd.authenticatable_id')
                ->where('s.access_token', $token)
                ->where('s.is_active', true)
                ->whereNull('s.revoked_at')
                ->first();
        }

        $sessions = DB::table('cerberus_user_device_sessions as s')
            ->join('cerberus_user_devices as d', 's.device_id', '=', 'd.id')
            ->select('s.*', 'd.device_fingerprint', 'd.authenticatable_type', 'd.authenticatable_id')
            ->where('s.is_active', true)
            ->whereNull('s.revoked_at')
            ->get();

        foreach ($sessions as $session) {
            if (Hash::check($token, $session->access_token)) {
                return $session;
            }
        }

        return null;
    }

    /**
     * Checks if a session is expired based on its expiration time.
     *
     * @param object $session The session object to check.
     * @return bool True if the session is expired, false otherwise.
     */
    public function isExpired(object $session): bool
    {
        return $session->expires_at && Carbon::parse($session->expires_at)->isPast();
    }

    /**
     * Revokes a session by its access token.
     *
     * @param string $token The access token of the session to revoke.
     * @return void
     */
    public function revokeSession(string $token): void
    {
        $query = DB::table('cerberus_user_device_sessions');

        if (config('cerberus.token.hash_enabled')) {
            $sessions = $query->get();
            foreach ($sessions as $session) {
                if (Hash::check($token, $session->access_token)) {
                    $query->where('id', $session->id)->delete();
                    return;
                }
            }
        } else {
            if (config('cerberus.revocation') === 'hard') {
                $query->where('access_token', $token)->delete();
            } else {
                $query->where('access_token', $token)->update([
                    'is_active' => false,
                    'revoked_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    /**
     * Updates the last activity timestamp and IP address of a session.
     *
     * @param string $token The access token of the session to update.
     * @param string $ip The IP address to set for the session.
     * @return void
     */
    public function updateSessionActivity(string $token, string $ip): void
    {
        DB::table('cerberus_user_device_sessions')
            ->where('access_token', $token)
            ->update([
                'last_activity_at' => now(),
                'ip' => $ip,
                'updated_at' => now(),
            ]);
    }

    /**
     * Retrieves all active sessions for a given user.
     *
     * @param Authenticatable $user The user for whom to retrieve active sessions.
     * @return array An array of active session objects.
     */
    public function getActiveSessionsForUser(Authenticatable $user): array
    {
        return DB::table('cerberus_user_device_sessions as s')
            ->join('cerberus_user_devices as d', 's.user_device_id', '=', 'd.id')
            ->select('s.*', 'd.device_fingerprint', 'd.device_type', 'd.app_version', 'd.os_version', 'd.ip', 'd.user_agent')
            ->where('d.authenticatable_type', get_class($user))
            ->where('d.authenticatable_id', $user->getAuthIdentifier())
            ->where('s.is_active', true)
            ->whereNull('s.revoked_at')
            ->get()
            ->toArray();
    }

    /**
     * Checks if the provided fingerprint matches the session's device fingerprint.
     *
     * @param object $session The session object to check.
     * @param string $fingerprint The device fingerprint to compare against.
     * @return bool True if the fingerprints match, false otherwise.
     */
    public function isFingerprintMatch(object $session, string $fingerprint): bool
    {
        return $session->device_fingerprint === $fingerprint;
    }

    /**
     * Finds a device by its ID.
     *
     * @param string $deviceId The ID of the device to find.
     * @return object|null The device object if found, null otherwise.
     */
    public function findDeviceById(string $deviceId): ?object
    {
        return DB::table('cerberus_user_devices')->where('id', $deviceId)->first();
    }
}
