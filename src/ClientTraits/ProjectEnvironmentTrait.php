<?php namespace FreedomtechHosting\FtLagoonPhp\ClientTraits;

use FreedomtechHosting\FtLagoonPhp\LagoonClientInitializeRequiredToInteractException;
use FreedomtechHosting\FtLagoonPhp\Ssh;

Trait ProjectEnvironmentTrait {
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
    }

    /**
     * Gets deployments for a project environment
     *
     * @param string $projectName The name of the project
     * @param string $environmentName The name of the environment
     * @return array Deployment information or error details
     * @throws LagoonClientInitializeRequiredToInteractException if client not initialized
     */
    public function getProjectEnvironmentDeployments(string $projectName, ?string $environmentName = null) : array
    {
        if(empty($this->lagoonToken) || empty($this->graphqlClient)) {
            throw new LagoonClientInitializeRequiredToInteractException();
        }

        $query = <<<GQL
            query q {
                projectByName(name: "{$projectName}") {
                    environments {
                        name
                        deployments {
                            id
                            name
                            priority
                            buildStep
                            status
                            started
                            completed
                        }
                    }
                }
            }
        GQL;

        $response = $this->graphqlClient->query($query);

        /***
         * Example Response
         * {
            "data": {
                "projectByName": {
                "environments": [
                    {
                    "name": "main",
                    "deployments": [
                        {
                        "id": 5269,
                        "name": "lagoon-build-u9izs5",
                        "priority": null,
                        "buildStep": null,
                        "status": "new",
                        "started": null,
                        "completed": null
                        }
                    ]
                    }
                ]
                }
            }
            }
         */

        if($response->hasErrors()) {
            return ['error' => $response->getErrors()];
        }

        $data = $response->getData();
        $deployments = [];

        if(isset($data['projectByName']['environments'])) {
            foreach($data['projectByName']['environments'] as $environment) {
                $envName = $environment['name'];
                if(!empty($environment['deployments'])) {
                    $deployments[$envName] = $environment['deployments'];
                }
            }
        }

        if(isset($environmentName) && !empty($environmentName)) {
            return isset($deployments[$environmentName]) ? [$environmentName => $deployments[$environmentName]] : [];
        }

        return $deployments;
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

    /**
     * Adds or updates a variable with a specific scope for a project environment
     *
     * @param string $projectName The name of the project
     * @param string $environmentName The name of the environment
     * @param string $key The variable key/name
     * @param string $value The variable value
     * @param string $scope The scope of the variable (GLOBAL, RUNTIME, BUILD, CONTAINER_REGISTRY)
     * @return array Response from the API
     * @throws LagoonClientInitializeRequiredToInteractException if client not initialized
     * @throws LagoonVariableScopeInvalidException if scope is invalid
     */
    public function addOrUpdateScopedVariableForProjectEnvironment(
        string $projectName,
        string $environmentName,
        string $key,
        string $value,
        string $scope
    )
    {
        return $this->addOrUpdateScopedVariableForProject(
            $projectName,
            $key,
            $value,
            $scope,
            $environmentName
        );
    }

    /**
     * Gets all variables for a specific project environment
     *
     * @param string $projectName The name of the project
     * @param string $environmentName The name of the environment
     * @return array Associative array of variables with their values and scopes
     */
    public function getProjectVariablesByNameForEnvironment(string $projectName, string $environmentName) : array
    {
        $data = $this->getProjectByName($projectName);
        $environments = $data['projectByName']['environments'] ?? [];
        $retvars = [];

        foreach ($environments as $environment) {
            if ($environment['name'] === $environmentName) {
                $lagoonVars = $environment['envVariables'] ?? [];
                foreach ($lagoonVars as $lagoonVar) {
                    $retvars[$lagoonVar['name']] = [
                        'value' => $lagoonVar['value'],
                        'scope' => $lagoonVar['scope']
                    ];
                }
                break;
            }
        }

        return $retvars;
    }

    /**
     * Gets a specific variable for a specific project environment
     *
     * @param string $projectName The name of the project
     * @param string $environmentName The name of the environment
     * @param string $variableName The name of the variable to retrieve
     * @return array Variable data including value and scope, or empty array if not found
     */
    public function getProjectVariableByNameForEnvironment(string $projectName, string $environmentName, string $variableName) : array
    {
        $variables = $this->getProjectVariablesByNameForEnvironment($projectName, $environmentName);
        return $variables[$variableName] ?? [];
    }

    /**
     * Deletes a variable from a project environment
     *
     * @param string $projectName The name of the project
     * @param string $variableName The name of the variable to delete
     * @param string $environmentName The name of the environment
     * @return array Response from the API
     * @throws LagoonClientInitializeRequiredToInteractException if client not initialized
     */
    public function deleteProjectVariableByNameForEnvironment(
        string $projectName,
        string $variableName,
        string $environmentName
        )
    {
        return $this->deleteProjectVariableByName(
            $projectName,
            $variableName,
            $environmentName
        );
    }

    public function executeCommandOnProjectEnvironment(
        string $projectName,
        string $environmentName,
        string $command,
        string $serviceName = "cli",
        string $containerName = "cli"
    ) : array
    { 
        if($this->getDebug()) {
		echo "Executing command on project environment: {$projectName} {$environmentName} {$command}\n";
	}

        $projectEnvironmentUser = $projectName. '-' . $environmentName;
        
        $ssh = Ssh::createLagoonConfigured(
            $projectEnvironmentUser, 
            $this->lagoonSshServer, 
            $this->lagoonSshPort, 
            $this->sshPrivateKeyFile
        );
        
        $result = $ssh->executeSShCommand($command, $serviceName, $containerName);
        
        if($this->getDebug()) {
            echo "Command Result:\n----\n";
            print_r($result);
            echo "\n----\n";
        }

        return $result;
    }
}

