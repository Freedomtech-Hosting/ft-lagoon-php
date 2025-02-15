<?php namespace FreedomtechHosting\FtLagoonPhp\ClientTraits;

use FreedomtechHosting\FtLagoonPhp\LagoonClientInitializeRequiredToInteractException;

Trait ProjectTrait {

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

}
