<?php

namespace Cerberus;

class CerberusService
{
    protected CerberusManager $manager;

    /**
     * CerberusService constructor.
     *
     * Initializes the CerberusManager instance.
     */
    public function __construct()
    {
        $this->manager = new CerberusManager();
    }

    /**
     * Get the CerberusManager instance.
     *
     * This method provides access to the CerberusManager, which handles session management,
     * token generation, and other core functionalities of the Cerberus package.
     *
     * @return CerberusManager
     */
    public function manager(): CerberusManager
    {
        return $this->manager;
    }
}
