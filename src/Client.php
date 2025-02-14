<?php namespace FreedomtechHosting\FtLagoonPhp;

use Softonic\GraphQL\ClientBuilder;
use FreedomtechHosting\FtLagoonPhp\ClientTraits\AuthTrait;
use Softonic\GraphQL\Mutation;

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

    use AuthTrait;

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
        $this->sshPrivateKeyFile = $config['ssh_private_key_file'] ?? '~/.ssh/id_rsa';
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

    /**
     * Creates a new Lagoon project
     *
     * @param string $projectName The name of the project
     * @param string $gitUrl The Git repository URL
     * @param string $deployBranch The branch to deploy
     * @param string $clusterId The Kubernetes cluster ID
     * @param string $privateKey The private key for Git access
     * @return array Response from the API
     */
    public function createLagoonProject(
        string $projectName,
        string $gitUrl,
        string $deployBranch,
        string $clusterId,
        string $privateKey)
    {

        $projectInput = [
            'name' => $projectName,
            'gitUrl' => $gitUrl,
            'kubernetes' => $clusterId,
            'branches' => $deployBranch,
            'productionEnvironment' => $deployBranch,
            'privateKey' => $privateKey,
        ];

        return $this->addProjectMutation($projectInput);
    }

    /**
     * Creates a new Lagoon project within an organization
     *
     * @param string $projectName The name of the project
     * @param string $gitUrl The Git repository URL
     * @param string $deployBranch The branch to deploy
     * @param int $clusterId The Kubernetes cluster ID
     * @param int $orgId The organization ID
     * @param bool $addOrgOwnerToProject Whether to add organization owner to project
     * @return array Response from the API
     */
    public function createLagoonProjectInOrganization(
        string $projectName,
        string $gitUrl,
        string $deployBranch,
        int $clusterId,
        string $privateKey,
        int $orgId,
        bool $addOrgOwnerToProject)
    {

        $projectInput = [
              'name' => $projectName,
              'gitUrl' => $gitUrl,
              'kubernetes' => $clusterId,
              'branches' => $deployBranch,
              'productionEnvironment' => $deployBranch,
              'organization' => $orgId,
              'addOrgOwner' => $addOrgOwnerToProject,
              'privateKey' => $privateKey,
        ];

        return $this->addProjectMutation($projectInput);
    }

    /**
     * Provides a generic runner for explicit addProject implementations
     *
     * @param array $addProjectInput Project configuration array
     * @return array Response from the API
     * @throws LagoonClientInitializeRequiredToInteractException if client not initialized
     */
    protected function addProjectMutation(array $addProjectInput)
    {

        if(empty($this->lagoonToken) || empty($this->graphqlClient)) {
            throw new LagoonClientInitializeRequiredToInteractException();
        }

        $mutation = <<<GQL
            mutation (\$projectInput: AddProjectInput!) {
                addProject(input: \$projectInput) {
                    id
                    name
                    gitUrl
                    branches
                    productionEnvironment
                }
            }
        GQL;

        $projectInput = [
            'projectInput' => $addProjectInput
        ];

        $response = $this->graphqlClient->query($mutation, $projectInput);

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
     * Adds or updates a global variable for a project
     *
     * @param string $projectName The name of the project
     * @param string $key The variable key/name
     * @param string $value The variable value
     * @return array Response from the API
     * @throws LagoonClientInitializeRequiredToInteractException if client not initialized
     */
    public function addOrUpdateGlobalVariableForProject(
        string $projectName,
        string $key,
        string $value
    )
    {
        if(empty($this->lagoonToken) || empty($this->graphqlClient)) {
            throw new LagoonClientInitializeRequiredToInteractException();
        }

        $mutation = <<<GQL
            mutation m {
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
     * Checks if a project exists by name
     *
     * @param string $projectName The name of the project to check
     * @return bool True if project exists, false otherwise
     */
    public function projectExistsByName(string $projectName) : bool
    {
        $data = $this->getProjectByName($projectName);
        return(isset($data['projectByName']['id']));
    }

    /**
     * Checks if a project environment exists by name
     *
     * @param string $projectName The name of the project
     * @param string $environmentName The name of the environment
     * @return bool True if environment exists, false otherwise
     */
    public function projectEnvironmentExistsByName(string $projectName, $environmentName) : bool
    {
        $data = $this->getProjectEnvironmentsByName($projectName);
        return(isset($data[$environmentName]));
    }

    /**
     * Gets a specific project environment by name
     *
     * @param string $projectName The name of the project
     * @param string $environmentName The name of the environment
     * @return array Environment data or empty array if not found
     */
    public function getProjectEnvironmentByName(string $projectName, $environmentName) : array
    {
        $data = $this->getProjectEnvironmentsByName($projectName);

        return($data[$environmentName] ?? []);
    }

    /**
     * Gets all environments for a project
     *
     * @param string $projectName The name of the project
     * @return array Associative array of environments keyed by name
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
     * Gets all variables for a project
     *
     * @param string $projectName The name of the project
     * @return array Associative array of variables with their values and scopes
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

    /**
     * Gets detailed information about a project
     *
     * @param string $projectName The name of the project
     * @return array Project data including environments, variables, and metadata
     * @throws LagoonClientInitializeRequiredToInteractException if client not initialized
     */
    public function getProjectByName(string $projectName) : array
    {
        if(empty($this->lagoonToken) || empty($this->graphqlClient)) {
            throw new LagoonClientInitializeRequiredToInteractException();
        }

        /**
         * Query Example
         */
        $query = <<<GQL
            query q {
                projectByName(name: "{$projectName}") {
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

        if($response->hasErrors()) {
            return ['error' => $response->getErrors()];
        }
        else {
            // Returns an array with all the data returned by the GraphQL server.
            $data = $response->getData();
            return $data;
        }

        return true;
    }

    /**
     * Triggers a deployment for a project environment
     *
     * @param string $projectName The name of the project
     * @param string $deployBranch The branch to deploy
     * @return array Response from the API
     * @throws LagoonClientInitializeRequiredToInteractException if client not initialized
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
     * Gets deployment information for a specific deployment
     *
     * @param string $projectId The project ID
     * @param string $environmentName The environment name
     * @param string $deploymentName The deployment name
     * @return array Deployment information or error details
     * @throws LagoonClientInitializeRequiredToInteractException if client not initialized
     */
    public function getProjectDeploymentByProjectIdDeploymentName(string $projectId, string $environmentName, string $deploymentName)  : array
    {
        if(empty($this->lagoonToken) || empty($this->graphqlClient)) {
            throw new LagoonClientInitializeRequiredToInteractException();
        }

        /**
         * Query Example
         */
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

        if($response->hasErrors()) {
            return ['error' => $response->getErrors()];
        }
        else {
            // Returns an array with all the data returned by the GraphQL server.
            $data = $response->getData();
            if(isset($data['environmentByName']['deployments'][0])) {
                return $data['environmentByName']['deployments'][0];
            }

            return ['error' => 'Deployment not found: ' . $deploymentName, 'errorData' => $data];
        }

        return true;
    }

    /**
     * Deletes a project environment
     *
     * @param string $projectName The name of the project
     * @param string $environmentName The name of the environment to delete
     * @return array Response from the API
     * @throws LagoonClientInitializeRequiredToInteractException if client not initialized
     */
    public function deleteProjectEnvironmentByName(
        string $projectName,
        string $environmentName,
    )
    {
        if(empty($this->lagoonToken) || empty($this->graphqlClient)) {
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

        if($response->hasErrors()) {
            return ['error' => $response->getErrors()];
        }
        else {
            // Returns an array with all the data returned by the GraphQL server.
            $data = $response->getData();
            return $data;
        }
    }
}
