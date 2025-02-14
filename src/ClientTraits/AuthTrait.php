<?php namespace FreedomtechHosting\FtLagoonPhp\ClientTraits;

use FreedomtechHosting\FtLagoonPhp\Ssh;
use FreedomtechHosting\FtLagoonPhp\LagoonClientInitializeRequiredToInteractException;

/**
 * Trait AuthTrait
 * 
 * Provides authentication and API interaction methods for the Lagoon API client.
 */
Trait AuthTrait {
    /**
     * Gets a Lagoon API token via SSH connection
     *
     * @param bool $refresh Whether to force refresh the token even if one exists
     * @return string The Lagoon API token
     */
    public function getLagoonTokenOverSSH($refresh = false)
    {
        if($this->lagoonToken && !$refresh) {
            return $this->lagoonToken;
        }

        $ssh = Ssh::createLagoonConfigured($this->lagoonSshUser, $this->lagoonSshServer, $this->lagoonSshPort, $this->sshPrivateKeyFile);

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