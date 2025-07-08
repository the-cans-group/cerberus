<?php

namespace Cerberus;

use Cerberus\Facades\Cerberus;
use Illuminate\Contracts\Auth\Authenticatable;
use Random\RandomException;
use RuntimeException;

/**
 * Trait for authenticatable models to manage Cerberus device sessions and tokens.
 *
 * @mixin Authenticatable
 */
trait CerberusAuthenticatable
{
    /**
     * Generate a new Cerberus access token for the current user.
     *
     * @return string
     * @throws RandomException
     */
    public function createAccessToken(): string
    {
        /** @var Authenticatable $this */
        if (!method_exists($this, 'getAuthIdentifier')) {
            throw new RuntimeException('The model must implement getAuthIdentifier method.');
        }

        return Cerberus::manager()->createSession($this);
    }

    /**
     * Get all active Cerberus sessions for the user.
     *
     * @return array
     */
    public function sessions(): array
    {
        /** @var Authenticatable $this */
        if (!method_exists($this, 'getAuthIdentifier')) {
            throw new RuntimeException('The model must implement getAuthIdentifier method.');
        }

        return Cerberus::manager()->getActiveSessionsForUser($this);
    }

    /**
     * Check if a given token belongs to this user.
     *
     * @param string $token
     * @return bool
     */
    public function tokenBelongsToThisUser(string $token): bool
    {
        $session = Cerberus::manager()->findSessionByToken($token);

        return $session
            && $session->authenticatable_type === get_class($this)
            && $session->authenticatable_id == $this->getAuthIdentifier();
    }

    /**
     * Revoke a specific Cerberus token of this user.
     *
     * @param string $token
     * @return void
     */
    public function revokeToken(string $token): void
    {
        if ($this->tokenBelongsToThisUser($token)) {
            Cerberus::manager()->revokeSession($token);
        }
    }

    /**
     * Revoke all Cerberus tokens of this user except the given one.
     *
     * @param string $exceptToken
     * @return void
     */
    public function revokeOtherTokens(string $exceptToken): void
    {
        foreach ($this->sessions() as $session) {
            if ($session->access_token !== $exceptToken) {
                Cerberus::manager()->revokeSession($session->access_token);
            }
        }
    }

    /**
     * Revoke all Cerberus tokens of this user.
     *
     * @return void
     */
    public function revokeAllTokens(): void
    {
        foreach ($this->sessions() as $session) {
            Cerberus::manager()->revokeSession($session->access_token);
        }
    }
}
