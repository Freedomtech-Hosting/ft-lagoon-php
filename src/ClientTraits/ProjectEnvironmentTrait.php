<?php namespace FreedomtechHosting\FtLagoonPhp\ClientTraits;

use FreedomtechHosting\FtLagoonPhp\LagoonClientInitializeRequiredToInteractException;

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

