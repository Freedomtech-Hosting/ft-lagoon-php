<?php namespace FreedomtechHosting\FtLagoonPhp\ClientTraits;

use FreedomtechHosting\FtLagoonPhp\LagoonClientInitializeRequiredToInteractException;

Trait GroupTrait {

    /**
     * Get all groups
     *
     * @return array
     * @throws LagoonClientInitializeRequiredToInteractException if client not initialized
     */
    public function getAllGroups() : array
    {
        if(empty($this->lagoonToken) || empty($this->graphqlClient)) {
            throw new LagoonClientInitializeRequiredToInteractException();
        }

        $query = <<<GQL
            query q {
                allGroups {
                    id
                    name
                    type
                    organization
                }   
            }
        GQL;

        $response = $this->graphqlClient->query($query);

        if($response->hasErrors()) {
            return ['error' => $response->getErrors()];
        }

        $data = $response->getData();
        return $data['allGroups'];
    }
    
    public function addGroupToProject(string $groupName, string $projectName) : array
    {
        if(empty($this->lagoonToken) || empty($this->graphqlClient)) {
            throw new LagoonClientInitializeRequiredToInteractException();
        }

        $mutation = <<<GQL
            mutation {
                addGroupsToProject (
                    input: {
                        project: {
                            name: "{$projectName}"
                        }
                        groups: {
                            name: "{$groupName}"
                        }
                    }
                ) {
                    id
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
