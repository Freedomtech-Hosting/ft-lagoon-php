<?php namespace FreedomtechHosting\FtLagoonPhp;

use Spatie\Ssh\Ssh as SpatieSsh;
use Symfony\Component\Process\Process;

class Ssh extends SpatieSsh {
    /**
     * @param string|array $command
     *
     * @return string
     **/
    public function executeLagoonGetToken(): string
    {
        $extraOptions = implode(' ', $this->getExtraOptions());
        $target = $this->getTargetForSsh();

        $sshCommand = "ssh {$extraOptions} {$target} token";

        $process = $this->run($sshCommand);

	    $token = $process->getOutput();
	
        return ltrim(rtrim($token));
    }
}
