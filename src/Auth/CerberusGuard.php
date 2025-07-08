<?php

namespace Cerberus\Auth;

use Illuminate\Contracts\Auth\Guard;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Cerberus\CerberusManager;

class CerberusGuard implements Guard
{
    protected ?Authenticatable $user = null;

    public function __construct(
        protected Request $request,
        protected CerberusManager $manager
    ) {}

    public function user(): ?Authenticatable
    {
        if (! $this->user) {
            $token = $this->request->bearerToken();

            if (! $token) {
                return null;
            }

            $session = $this->manager->findSessionByToken($token);
            if (! $session || $this->manager->isExpired($session)) {
                return null;
            }

            $fp = $this->request->header(config('cerberus.headers.device_fingerprint'));
            if (config('cerberus.track.device_fingerprint') && ! $this->manager->isFingerprintMatch($session, $fp)) {
                return null;
            }

            $userModel = config('auth.providers.users.model');
            $user = (new $userModel)->find($session->user_id);

            if ($user) {
                $this->manager->updateSessionActivity($token, $this->request->ip());
                $this->user = $user;
            }
        }

        return $this->user;
    }

    public function check(): bool
    {
        return (bool) $this->user();
    }

    public function guest(): bool
    {
        return ! $this->check();
    }

    public function id(): int|string|null
    {
        return $this->user()?->getAuthIdentifier();
    }

    public function validate(array $credentials = []): bool
    {
        return false; // Gerekirse eklersin
    }

    public function setUser(Authenticatable $user): void
    {
        $this->user = $user;
    }

    public function hasUser(): bool
    {
        return ! is_null($this->user);
    }
}
