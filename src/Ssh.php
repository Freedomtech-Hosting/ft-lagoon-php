<?php namespace FreedomtechHosting\FtLagoonPhp;

use Spatie\Ssh\Ssh as SpatieSsh;

/**
 * Class Ssh
 * 
 * Extends Spatie's SSH implementation to provide Lagoon-specific SSH functionality.
 * This class handles SSH connections and commands specifically for interacting with Lagoon services.
 */
class Ssh extends SpatieSsh {

    /**
     * Executes a command over SSH to retrieve a Lagoon API token
     * 
     * This method connects to the Lagoon server via SSH and executes the 'token' command
     * to obtain an authentication token for API access.
     *
     * @return string The cleaned Lagoon API token with leading/trailing whitespace removed
     */
    public function executeLagoonGetToken(): string
    {
        $extraOptions = implode(' ', $this->getExtraOptions());
        $target = $this->getTargetForSsh();

        $sshCommand = "ssh {$extraOptions} {$target} token";

        $process = $this->run($sshCommand);

	    $token = $process->getOutput();
	
        return ltrim(rtrim($token));
    }

    public static function createLagoonConfigured(string $user, string $server, string $port, string $privateKeyFile) : static 
    {
        return static::create($user, $server)
            ->usePort($port)
            ->usePrivateKey($privateKeyFile) 
            ->disableStrictHostKeyChecking()
            ->removeBash()
            ->enableQuietMode();
    }
}
