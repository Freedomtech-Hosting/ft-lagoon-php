<?php namespace FreedomtechHosting\FtLagoonPhp\ClientTraits;

use FreedomtechHosting\FtLagoonPhp\LagoonClientInitializeRequiredToInteractException;
use FreedomtechHosting\FtLagoonPhp\LagoonVariableScopeInvalidException;

Trait ProjectTrait {

    /**
     * Creates a new Lagoon project
     *
     * @param string $projectName The name of the project
     * @param string $gitUrl The Git repository URL
     * @param string $branches The branches to deploy
     * @param string $productionEnvironment The production environment
     * @param int $clusterId The Kubernetes cluster ID
     * @param string|null $privateKey The private key for Git access
     * @return array Response from the API
     */
    public function createLagoonProject(
        string $projectName,
        string $gitUrl,
        string $branches,
        string $productionEnvironment,
        int $clusterId,
        ?string $privateKey = null)
    {

        $projectInput = [
            'name' => $projectName,
            'gitUrl' => $gitUrl,
            'kubernetes' => $clusterId,
            'branches' => $branches,
            'productionEnvironment' => $productionEnvironment,
        ] + (!empty($privateKey) ? ['privateKey' => $privateKey] : []);

        
        return $this->addProjectMutation($projectInput);
    }

    /**
     * Creates a new Lagoon project within an organization
     *
     * @param string $projectName The name of the project
     * @param string $gitUrl The Git repository URL
     * @param string $branches The branches to deploy
     * @param string $productionEnvironment The production environment
     * @param int $clusterId The Kubernetes cluster ID
     * @param string|null $privateKey The private key for Git access
     * @param int $orgId The organization ID
     * @param bool $addOrgOwnerToProject Whether to add organization owner to project
     * @return array Response from the API
     */
    public function createLagoonProjectInOrganization(
        string $projectName,
        string $gitUrl,
        string $branches,
        string $productionEnvironment,
        int $clusterId,
        ?string $privateKey = null,
        int $orgId,
        bool $addOrgOwnerToProject)
    {

        $projectInput = [
              'name' => $projectName,
              'gitUrl' => $gitUrl,
              'kubernetes' => $clusterId,
              'branches' => $branches,
              'productionEnvironment' => $productionEnvironment,
              'organization' => $orgId,
              'addOrgOwner' => $addOrgOwnerToProject,
        ];

        if (!empty($privateKey)) {
            $projectInput['privateKey'] = $privateKey;
        }

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
                        envVariables {
                          id
                          name
                          value
                          scope
                        }
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
     * Gets a specific variable for a project by variable name
     *
     * @param string $projectName The name of the project
     * @param string $variableName The name of the variable to retrieve
     * @return array Variable data including value and scope, or empty array if not found
     */
    public function getProjectVariableByName(string $projectName, string $variableName) : array
    {
        $variables = $this->getProjectVariablesByName($projectName);
        return $variables[$variableName] ?? [];
    }

    /**
     * Adds or updates a variable with a specific scope for a project
     *
     * @param string $projectName The name of the project
     * @param string $key The variable key/name
     * @param string $value The variable value
     * @param string $scope The scope of the variable (GLOBAL, RUNTIME, BUILD, CONTAINER_REGISTRY)
     * @param string|null $environment Optional environment name
     * @return array Response from the API
     * @throws LagoonClientInitializeRequiredToInteractException if client not initialized
     * @throws LagoonVariableScopeInvalid if scope is invalid
     */
    public function addOrUpdateScopedVariableForProject(
        string $projectName,
        string $key,
        string $value,
        string $scope,
        ?string $environment = null
    )
    {
        if(empty($this->lagoonToken) || empty($this->graphqlClient)) {
            throw new LagoonClientInitializeRequiredToInteractException();
        }

        $validScopes = ['RUNTIME', 'BUILD', 'CONTAINER_REGISTRY', 'GLOBAL'];
        if (!in_array($scope, $validScopes)) {
            throw new LagoonVariableScopeInvalidException();
        }

        $environmentArg = !empty($environment) ? "environment: \"{$environment}\"," : "";

        $mutation = <<<GQL
            mutation m {
                addOrUpdateEnvVariableByName(input: {
                    project: "{$projectName}"
                    name: "{$key}"
                    scope: {$scope}
                    value: "{$value}"
                    {$environmentArg}
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
        return $this->addOrUpdateScopedVariableForProject($projectName, $key, $value, 'GLOBAL');
    }

    /**
     * Gets all projects from the API
     *
     * @return array Array of all projects and their details
     * @throws LagoonClientInitializeRequiredToInteractException if client not initialized
     */
    public function getAllProjects() : array
    {
        if(empty($this->lagoonToken) || empty($this->graphqlClient)) {
            throw new LagoonClientInitializeRequiredToInteractException();
        }

        $query = <<<GQL
            query q {
                allProjects {
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
            $data = $response->getData();
            return $data;
        }
    }

    /**
     * Deletes a variable from a project or project environment
     *
     * @param string $projectName The name of the project
     * @param string $variableName The name of the variable to delete
     * @param string|null $environment Optional environment name
     * @return array Response from the API
     * @throws LagoonClientInitializeRequiredToInteractException if client not initialized
     */
    public function deleteProjectVariableByName(
        string $projectName,
        string $variableName,
        ?string $environment = null
    )
    {
        if(empty($this->lagoonToken) || empty($this->graphqlClient)) {
            throw new LagoonClientInitializeRequiredToInteractException();
        }

        $input = [
            'project' => $projectName,
            'name' => $variableName
        ];

        if (!empty($environment)) {
            $input['environment'] = $environment;
        }

        $environmentArg = !empty($environment) ? "environment: \"{$environment}\"," : "";

        $mutation = <<<GQL
            mutation m {
                deleteEnvVariableByName(input: {
                    project: "{$projectName}",
                    name: "{$variableName}"
                    {$environmentArg}
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
