<?php namespace FreedomtechHosting\FtLagoonPhp;

use Softonic\GraphQL\ClientBuilder;
use FreedomtechHosting\FtLagoonPhp\ClientTraits\AuthTrait;
use FreedomtechHosting\FtLagoonPhp\ClientTraits\ProjectTrait;
use FreedomtechHosting\FtLagoonPhp\ClientTraits\ProjectEnvironmentTrait;
use FreedomtechHosting\FtLagoonPhp\ClientTraits\GroupTrait;
use FreedomtechHosting\FtLagoonPhp\ClientTraits\TaskTrait;

/**
 * Client class for interacting with the Lagoon API
 * 
 * This class provides methods to interact with Lagoon's GraphQL API, handling operations like:
 * - Project management (creation, deletion, deployment)
 * - Environment management
 * - Variable management
 * - Authentication
 *
 * It requires SSH key authentication and manages the GraphQL client connection.
 */
class Client {
    protected $config;

    /** @var \Softonic\GraphQL\Client */
    protected $graphqlClient;
    protected $sshPrivateKeyFile;
    protected $lagoonSshUser;
    protected $lagoonSshServer;
    protected $lagoonSshPort;
    protected $lagoonToken;
    protected $lagoonApiEndpoint;

    protected $debug = false;

    use AuthTrait;
    use ProjectTrait;
    use ProjectEnvironmentTrait;
    use GroupTrait;
    use TaskTrait;

    /**
     * Constructor for the Lagoon API client
     *
     * Initializes the client with configuration settings for SSH and API connectivity.
     * Uses default values for most settings if not explicitly provided.
     *
     * @param array $config Configuration array with optional keys:
     *                      - ssh_user: SSH username (default: 'lagoon')
     *                      - ssh_server: SSH server hostname (default: 'ssh.lagoon.amazeeio.cloud')
     *                      - ssh_port: SSH port (default: '32222')
     *                      - endpoint: API endpoint URL (default: 'https://api.lagoon.amazeeio.cloud/graphql')
     *                      - ssh_private_key_file: Path to SSH private key (default: '~/.ssh/id_rsa')
     */
    public function __construct(array $config = [])
    {
        $this->config = $config;
	
	    $this->lagoonSshUser = $config['ssh_user'] ?? 'lagoon';
        $this->lagoonSshServer = $config['ssh_server'] ?? 'ssh.lagoon.amazeeio.cloud';
        $this->lagoonSshPort = $config['ssh_port'] ?? '32222';
        $this->lagoonApiEndpoint = $config['endpoint'] ?? 'https://api.lagoon.amazeeio.cloud/graphql';
        $this->sshPrivateKeyFile = $config['ssh_private_key_file'] ?? getenv('HOME') . '/.ssh/id_rsa';
        
        if(! isset($config['debug'])) {
            $this->debug = false;
        } else {
            $this->debug = $config['debug'];
        }

        if (!file_exists($this->sshPrivateKeyFile)) {
            throw new LagoonClientPrivateKeyNotFoundException($this->sshPrivateKeyFile);
        }
    }

    /**
     * Set the debug mode
     *
     * @param bool $debug True to enable debug, false to disable
     */
    public function setDebug($debug)
    {
        $this->debug = (bool) $debug;
    }

    /**
     * Get the debug mode
     *
     * @return bool True if debug is enabled, false otherwise
     */
    public function getDebug() : bool
    {
        return $this->debug;
    }

    /**
     * Initializes the GraphQL client with authentication token
     *
     * @throws LagoonClientTokenRequiredToInitializeException if no token is set
     */
    public function initGraphqlClient()
    {
        if(empty($this->lagoonToken)) {
            throw new LagoonClientTokenRequiredToInitializeException();
        }

        $this->graphqlClient = ClientBuilder::build($this->lagoonApiEndpoint, [
            'headers' => [
                'Authorization'     => 'Bearer ' . $this->lagoonToken
            ]
        ]);
    }

    /**
     * Sets the Lagoon authentication token
     *
     * @param string $token The authentication token
     */
    public function setLagoonToken($token)
    {
        $this->lagoonToken = $token;
    }

    /**
     * Gets the current Lagoon authentication token
     *
     * @return string|null The current token or null if not set
     */
    public function getLagoonToken()
    {
        return $this->lagoonToken;
    }

}
