<?php namespace FreedomtechHosting\FtLagoonPhp\ClientTraits;

use FreedomtechHosting\FtLagoonPhp\Ssh;
use FreedomtechHosting\FtLagoonPhp\LagoonClientInitializeRequiredToInteractException;
use FreedomtechHosting\FtLagoonPhp\LagoonClientTokenRequiredToInitializeException;

Trait AuthTrait {
    public function getLagoonTokenOverSSH($refresh = false)
    {
        if($this->lagoonToken && !$refresh) {
            return $this->lagoonToken;
        }

        $ssh = Ssh::create($this->lagoonSshUser, $this->lagoonSshServer)
            ->usePort($this->lagoonSshPort)
            ->usePrivateKey($this->sshPrivateKeyFile)
            ->disableStrictHostKeyChecking()
            ->removeBash()
            ->enableQuietMode();

        $token = $ssh->executeLagoonGetToken();
        $this->setLagoonToken($token);

        return $token;
    }
    
    public function pingLagoonAPI() : bool
    {
        if(empty($this->lagoonToken) || empty($this->graphqlClient)) {
            throw new LagoonClientInitializeRequiredToInteractException();
        }

        /**
         * Query Example
         */
        $query = "
          query q {
            lagoonVersion
            me {
              id
            }
          }";

        $response = $this->graphqlClient->query($query);

        if($response->hasErrors()) {
            return false;
        }
        else {
            // Returns an array with all the data returned by the GraphQL server.
            $data = $response->getData();

            return isset($data['lagoonVersion']) && isset($data['me']['id']);
        }

        return true;
    }


    public function whoAmI() : array
    {
        if(empty($this->lagoonToken) || empty($this->graphqlClient)) {
            throw new LagoonClientInitializeRequiredToInteractException();
        }

        /**
         * Query Example
         */
        $query = "
          query q {
            lagoonVersion
            me {
	      id,
	      email
            }
          }";

        $response = $this->graphqlClient->query($query);

        if($response->hasErrors()) {
            return false;
        }
        else {
            // Returns an array with all the data returned by the GraphQL server.
            $data = $response->getData();
            return $data;
        }
    }
}