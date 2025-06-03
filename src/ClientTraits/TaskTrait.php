<?php namespace FreedomtechHosting\FtLagoonPhp\ClientTraits;

use FreedomtechHosting\FtLagoonPhp\LagoonClientInitializeRequiredToInteractException;
use FreedomtechHosting\FtLagoonPhp\Ssh;

Trait TaskTrait {

    /**
     * Gets a list of tasks for a specific environment.
     * 
     */
    public function getTasksForProjectEnvironment(string $projectName, string $environment): array {
        $data = $this->getProjectEnvironmentByName($projectName, $environment);
        if(empty($this->lagoonToken) || empty($this->graphqlClient)) {
            throw new LagoonClientInitializeRequiredToInteractException();
        }

        if(empty($data)) {
             return ['error' => 'Environment not found: ' . $environment. ' for project: ' . $projectName]; 
        }

        $envId = $data['id'];

        $advancedTaskDefArgumentsFragment = "
        advancedTaskDefinitionArguments {
            id
            name
            displayName
            type
            defaultValue
            optional
            range
        }
        ";

        $query = <<<GQL
            query getTasks {
            advancedTasksForEnvironment(environment: $envId) {
                ... on AdvancedTaskDefinitionCommand {
                id
                name
                description
                type
                $advancedTaskDefArgumentsFragment
                }
                ... on AdvancedTaskDefinitionImage {
                id
                name
                description
                type
                $advancedTaskDefArgumentsFragment
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
            return $data['advancedTasksForEnvironment'] ?? [];
        }
    }

}