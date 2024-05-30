<?php namespace FreedomtechHosting\FtLagoonPhp;

use Softonic\GraphQL\ClientBuilder;
use FreedomtechHosting\FtLagoonPhp\ClientTraits\AuthTrait;
use Softonic\GraphQL\Mutation;

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

    public function __construct(array $config = [])
    {
        $this->config = $config;
	
	    $this->lagoonSshUser = $config['ssh_user'] ?? 'lagoon';
        $this->lagoonSshServer = $config['ssh_server'] ?? 'ssh.lagoon.amazeeio.cloud';
        $this->lagoonSshPort = $config['ssh_port'] ?? '32222';
        $this->lagoonApiEndpoint = $config['endpoint'] ?? 'https://api.lagoon.amazeeio.cloud/graphql';
        $this->sshPrivateKeyFile = $config['ssh_private_key_file'] ?? '~/.ssh/id_rsa';
    }

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

    public function setLagoonToken($token)
    {
        $this->lagoonToken = $token;
    }

    public function getLagoonToken()
    {
        return $this->lagoonToken;
    }


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

    public function createLagoonProjectInOrganization(
        string $projectName,
        string $gitUrl,
        string $deployBranch,
        int $clusterId,
//        string $privateKey,
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
        ];

        return $this->addProjectMutation($projectInput);
    }

    /**
     * Provides a generic runner for explicit addProject implementations
     *
     * @param array $addProjectInput
     * @return array
     * @throws LagoonClientInitializeRequiredToInteractException
     */
    protected function addProjectMutation(array $addProjectInput)
    {

        if(empty($this->lagoonToken) || empty($this->graphqlClient)) {
            throw new LagoonClientInitializeRequiredToInteractException();
        }

        $mutation = 'mutation ($projectInput: AddProjectInput!) {
            addProject(input: $projectInput) {
                id
                name
                gitUrl
                branches
                productionEnvironment
            }
        }';

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


    public function addOrUpdateGlobalVariableForProject(
        string $projectName,
        string $key,
        string $value
    )
    {
        if(empty($this->lagoonToken) || empty($this->graphqlClient)) {
            throw new LagoonClientInitializeRequiredToInteractException();
        }

        $mutation = "
        mutation m {
            addOrUpdateEnvVariableByName(input: {
                project: \"{$projectName}\"
                name: \"{$key}\"
                scope: GLOBAL
                value: \"{$value}\"
            }) {
              id
              name
              value
              scope
            }
          }
        ";

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

    public function projectExistsByName(string $projectName) : bool
    {
        $data = $this->getProjectByName($projectName);
        return(isset($data['projectByName']['id']));
    }

    public function projectEnvironmentExistsByName(string $projectName, $environmentName) : bool
    {
        $data = $this->getProjectEnvironmentsByName($projectName);
        return(isset($data[$environmentName]));
    }

    public function getProjectEnvironmentByName(string $projectName, $environmentName) : array
    {
        $data = $this->getProjectEnvironmentsByName($projectName);

        return($data[$environmentName] ?? []);
    }

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

    public function getProjectByName(string $projectName) : array
    {
        if(empty($this->lagoonToken) || empty($this->graphqlClient)) {
            throw new LagoonClientInitializeRequiredToInteractException();
        }

        /**
         * Query Example
         */
        $query = "
          query q {
            projectByName(name: \"$projectName\") {
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
          }";

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

    public function deployProjectEnvironmentByName(
        string $projectName,
        string $deployBranch,
    )
    {
        if(empty($this->lagoonToken) || empty($this->graphqlClient)) {
            throw new LagoonClientInitializeRequiredToInteractException();
        }

        $mutation = "
        mutation m {
            deployEnvironmentBranch(input: {
                project: {name: \"{$projectName}\"}
                branchName: \"{$deployBranch}\"
                returnData: true
            })
        }
        ";

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

    public function getProjectDeploymentByProjectIdDeploymentName(string $projectId, string $environmentName, string $deploymentName)  : array
    {
        if(empty($this->lagoonToken) || empty($this->graphqlClient)) {
            throw new LagoonClientInitializeRequiredToInteractException();
        }

        /**
         * Query Example
         */
        $query = "
        query q {
            environmentByName(project: {$projectId}, name: \"{$environmentName}\") {
              deployments(name: \"{$deploymentName}\") {
                id
                remoteId
                name
                status
                created
                started
                completed
              }
            }
          }";

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

    public function deleteProjectEnvironmentByName(
        string $projectName,
        string $environmentName,
    )
    {
        if(empty($this->lagoonToken) || empty($this->graphqlClient)) {
            throw new LagoonClientInitializeRequiredToInteractException();
        }

        $mutation = "
        mutation m {
            deleteEnvironment(input: {
                    project: \"{$projectName}\",
                    name: \"{$environmentName}\",
                    execute: true
                }
            )
          }
        ";

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

