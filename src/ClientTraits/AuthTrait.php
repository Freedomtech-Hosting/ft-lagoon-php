<?php namespace FreedomtechHosting\FtLagoonPhp\ClientTraits;

use FreedomtechHosting\FtLagoonPhp\Ssh;
use FreedomtechHosting\FtLagoonPhp\LagoonClientInitializeRequiredToInteractException;

/**
 * Trait AuthTrait
 * 
 * Provides authentication and API interaction methods for the Lagoon API client.
 */
Trait AuthTrait {
    public function getLagoonTokenOverSSH($refresh = false, $debug = false)
    {
        if($this->lagoonToken && !$refresh) {
            return $this->lagoonToken;
        }

        $ssh = Ssh::createLagoonConfigured($this->lagoonSshUser, $this->lagoonSshServer, $this->lagoonSshPort, $this->sshPrivateKeyFile);

        if($debug) {
            echo $ssh->getTokenCommand();
        }

        $token = $ssh->executeLagoonGetToken();
        $this->setLagoonToken($token);

        return $token;
    }
    
    /**
     * Pings the Lagoon API to verify connectivity and authentication
     *
     * @throws LagoonClientInitializeRequiredToInteractException If client is not properly initialized
     * @return bool True if connection is successful, false otherwise
     */
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

    /**
     * Retrieves information about the currently authenticated user
     *
     * @throws LagoonClientInitializeRequiredToInteractException If client is not properly initialized
     * @return array User information including ID and email
     */
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