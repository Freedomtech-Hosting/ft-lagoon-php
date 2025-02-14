<?php namespace FreedomtechHosting\FtLagoonPhp;

use Softonic\GraphQL\ClientBuilder;
use FreedomtechHosting\FtLagoonPhp\ClientTraits\AuthTrait;

class Client {
    protected $config;
    protected $graphqlClient;
    protected $sshPrivateKeyFile;
    protected $lagoonSshUser;
    protected $lagoonSshServer;
    protected $lagoonSshPort;
    protected $lagoonToken;
    protected $lagoonApiEndpoint;

    use AuthTrait;
    
    /**
     * Initialize a new Lagoon API client
     *
     * @param array $config Configuration array with the following optional keys:
     *                      - ssh_user: SSH username (default: 'lagoon')
     *                      - ssh_server: SSH server hostname (default: 'ssh.lagoon.amazeeio.cloud') 
     *                      - ssh_port: SSH port number (default: '32222')
     *                      - endpoint: GraphQL API endpoint (default: 'https://api.lagoon.amazeeio.cloud/graphql')
     *                      - ssh_private_key_file: Path to SSH private key (default: '~/.ssh/id_rsa')
     */
    public function __construct(array $config = [])
    {
        $this->config = $config;
	
	    $this->lagoonSshUser = $config['ssh_user'] ?? 'lagoon';
        $this->lagoonSshServer = $config['ssh_server'] ?? 'ssh.lagoon.amazeeio.cloud';
        $this->lagoonSshPort = $config['ssh_port'] ?? '32222';
        $this->lagoonApiEndpoint = $config['endpoint'] ?? 'https://api.lagoon.amazeeio.cloud/graphql';
        $this->sshPrivateKeyFile = $config['ssh_private_key_file'] ?? '~/.ssh/id_rsa';
    }

    /**
     * Initialize the GraphQL client
     *
     * @throws LagoonClientTokenRequiredToInitializeException If the Lagoon token is not set
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
     * Set the Lagoon token
     *
     * @param string $token The Lagoon token
     */
    public function setLagoonToken($token)
    {
        $this->lagoonToken = $token;
    }

    /**
     * Get the Lagoon token
     *
     * @return string|null The Lagoon token if set, null otherwise
     */
    public function getLagoonToken()
    {
        return $this->lagoonToken;
    }


    /**
     * Create a new Lagoon project
     *
     * @param string $projectName Name of the project
     * @param string $gitUrl Git repository URL
     * @param string $deployBranch Branch to deploy
     * @param string $clusterId Kubernetes cluster ID
     * @param string $privateKey SSH private key
     * @return array Response data from Lagoon API
     * @throws LagoonClientInitializeRequiredToInteractException If client is not initialized
     */
    public function createLagoonProject(
        string $projectName,
        string $gitUrl, 
        string $deployBranch,
        string $clusterId,
        string $privateKey
    ) {
        if (empty($this->lagoonToken) || empty($this->graphqlClient)) {
            throw new LagoonClientInitializeRequiredToInteractException();
        }

        $mutation = <<<GQL
            mutation {
                addProject(input: {
                    name: "{$projectName}"
                    gitUrl: "{$gitUrl}"
                    kubernetes: {$clusterId}
                    branches: "{$deployBranch}"
                    productionEnvironment: "{$deployBranch}"
                    privateKey: "{$privateKey}"
                }) {
                    id
                    name
                    gitUrl
                    branches
                    productionEnvironment
                }
            }
        GQL;

        $response = $this->graphqlClient->query($mutation);

        if ($response->hasErrors()) {
            return ['error' => $response->getErrors()];
        }

        return $response->getData();
    }

    /**
     * Add or update a global environment variable for a Lagoon project
     *
     * @param string $projectName Name of the project
     * @param string $key Environment variable key/name
     * @param string $value Environment variable value
     * @return array Response data from Lagoon API
     * @throws LagoonClientInitializeRequiredToInteractException If client is not initialized
     */
    public function addOrUpdateGlobalVariableForProject(
        string $projectName,
        string $key,
        string $value
    ) {
        if (empty($this->lagoonToken) || empty($this->graphqlClient)) {
            throw new LagoonClientInitializeRequiredToInteractException();
        }

        $mutation = <<<GQL
            mutation {
                addOrUpdateEnvVariableByName(input: {
                    project: "{$projectName}"
                    name: "{$key}"
                    scope: GLOBAL
                    value: "{$value}"
                }) {
                    id
                    name
                    value
                    scope
                }
            }
        GQL;

        $response = $this->graphqlClient->query($mutation);

        if ($response->hasErrors()) {
            return ['error' => $response->getErrors()];
        }

        return $response->getData();
    }

    /**
     * Check if a project exists by name
     *
     * @param string $projectName Name of the project
     * @return bool True if the project exists, false otherwise
     * @throws LagoonClientInitializeRequiredToInteractException If client is not initialized
     */
    public function projectExistsByName(string $projectName) : bool
    {
        $data = $this->getProjectByName($projectName);
        return(isset($data['projectByName']['id']));
    }

    /**
     * Check if a project environment exists by name
     *
     * @param string $projectName Name of the project
     * @param string $environmentName Name of the environment
     * @return bool True if the environment exists in the project, false otherwise
     * @throws LagoonClientInitializeRequiredToInteractException If client is not initialized
     */
    public function projectEnvironmentExistsByName(string $projectName, $environmentName) : bool
    {
        $data = $this->getProjectEnvironmentsByName($projectName);
        return(isset($data[$environmentName]));
    }

    /**
     * Get a specific environment from a project by name
     *
     * @param string $projectName Name of the project
     * @param string $environmentName Name of the environment
     * @return array Environment data if found, empty array otherwise
     * @throws LagoonClientInitializeRequiredToInteractException If client is not initialized
     */
    public function getProjectEnvironmentByName(string $projectName, $environmentName) : array
    {
        $data = $this->getProjectEnvironmentsByName($projectName);

        return($data[$environmentName] ?? []);
    }

    /**
     * Get all environments for a project by name
     *
     * @param string $projectName Name of the project
     * @return array Associative array of environments keyed by environment name
     * @throws LagoonClientInitializeRequiredToInteractException If client is not initialized
     */
    public function getProjectEnvironmentsByName(string $projectName) : array
    {
        $data = $this->getProjectByName($projectName);
        $environment = $data['projectByName']['environments'];
        $retenvs = [];

        foreach($environment as $environment) {
            $retenvs[$environment['name']] = $environment;
        }

        return($retenvs);
    }

    /**
     * Get all environment variables for a project by name
     *
     * @param string $projectName Name of the project
     * @return array Associative array of environment variables keyed by variable name, containing value and scope
     * @throws LagoonClientInitializeRequiredToInteractException If client is not initialized
     */
    public function getProjectVariablesByName(string $projectName) : array
    {
        $data = $this->getProjectByName($projectName);
        $lagoonVars = $data['projectByName']['envVariables'] ?? [];
        $retvars = [];

        foreach($lagoonVars as $lagoonVar) {
            $retvars[$lagoonVar['name']] = [
                'value' => $lagoonVar['value'],
                'scope' => $lagoonVar['scope']
            ];
        }

        return $retvars;
    }

    public function getProjectByName(string $projectName): array
    {
        if (empty($this->lagoonToken) || empty($this->graphqlClient)) {
            throw new LagoonClientInitializeRequiredToInteractException();
        }

        $query = <<<GQL
            query q {
                projectByName(name: "$projectName") {
                    id
                    name
                    productionEnvironment
                    branches
                    gitUrl
                    openshift {
                        id
                        name
                        cloudProvider
                        cloudRegion
                    }
                    created
                    metadata
                    envVariables {
                        id
                        name
                        value
                        scope
                    }
                    publicKey
                    privateKey
                    availability
                    environments {
                        id
                        name
                        created
                        updated
                        deleted
                        environmentType
                        route
                        routes
                    }
                }
            }
        GQL;

        $response = $this->graphqlClient->query($query);

        if ($response->hasErrors()) {
            return ['error' => $response->getErrors()];
        }

        return $response->getData();
    }

    /**
     * Deploy a project environment by project name and branch
     *
     * @param string $projectName The name of the project to deploy
     * @param string $deployBranch The git branch name to deploy
     * @return array Returns deployment data on success, or error details on failure
     * @throws LagoonClientInitializeRequiredToInteractException If client is not properly initialized
     */
    public function deployProjectEnvironmentByName(
        string $projectName,
        string $deployBranch,
    )
    {
        if(empty($this->lagoonToken) || empty($this->graphqlClient)) {
            throw new LagoonClientInitializeRequiredToInteractException();
        }

        $mutation = <<<GQL
            mutation m {
                deployEnvironmentBranch(input: {
                    project: {name: "{$projectName}"}
                    branchName: "{$deployBranch}"
                    returnData: true
                })
            }
        GQL;

        $response = $this->graphqlClient->query($mutation);

        if($response->hasErrors()) {
            return ['error' => $response->getErrors()];
        }
        else {
            // Returns an array with all the data returned by the GraphQL server.
            $data = $response->getData();
            return $data;
        }
    }

    /**
     * Get deployment details for a specific project environment and deployment name
     *
     * @param string $projectId The ID of the project
     * @param string $environmentName The name of the environment
     * @param string $deploymentName The name of the deployment to retrieve
     * @return array Returns deployment data on success, or error details on failure
     * @throws LagoonClientInitializeRequiredToInteractException If client is not properly initialized
     */
    public function getProjectDeploymentByProjectIdDeploymentName(string $projectId, string $environmentName, string $deploymentName): array
    {
        if (empty($this->lagoonToken) || empty($this->graphqlClient)) {
            throw new LagoonClientInitializeRequiredToInteractException();
        }

        $query = <<<GQL
            query q {
                environmentByName(project: {$projectId}, name: "{$environmentName}") {
                    deployments(name: "{$deploymentName}") {
                        id
                        remoteId
                        name
                        status
                        created
                        started
                        completed
                    }
                }
            }
        GQL;

        $response = $this->graphqlClient->query($query);

        if ($response->hasErrors()) {
            return ['error' => $response->getErrors()];
        }

        $data = $response->getData();
        if (isset($data['environmentByName']['deployments'][0])) {
            return $data['environmentByName']['deployments'][0];
        }

        return ['error' => 'Deployment not found: ' . $deploymentName, 'errorData' => $data];
    }

    /**
     * Delete a project environment by name
     *
     * @param string $projectName The name of the project
     * @param string $environmentName The name of the environment to delete
     * @return array Returns success data on success, or error details on failure
     * @throws LagoonClientInitializeRequiredToInteractException If client is not properly initialized
     */
    public function deleteProjectEnvironmentByName(
        string $projectName,
        string $environmentName,
    ): array
    {
        if (empty($this->lagoonToken) || empty($this->graphqlClient)) {
            throw new LagoonClientInitializeRequiredToInteractException();
        }

        $mutation = <<<GQL
            mutation m {
                deleteEnvironment(input: {
                    project: "{$projectName}",
                    name: "{$environmentName}",
                    execute: true
                })
            }
        GQL;

        $response = $this->graphqlClient->query($mutation);

        if ($response->hasErrors()) {
            return ['error' => $response->getErrors()];
        }

        $data = $response->getData();
        return $data;
    }
}

