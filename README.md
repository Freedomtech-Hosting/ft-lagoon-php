# FT Lagoon PHP Client

> **⚠️ Warning: Experimental Project**  
> FT-Lagoon-PHP is currently in active development and has not yet reached a stable production release. This project should be considered experimental.
>
> If you are interested in using FT-Lagoon-PHP in a production setting, please contact Bryan Gruneberg (bryan@workshoporange.co) from [Workshop Orange](https://www.workshoporange.co), one of the sponsoring organizations.


A PHP client library for interacting with the Lagoon API.

## Requirements

- PHP 8.0+
- Composer
- Access to a Lagoon (https://lagoon.sh) instance. Shameless plug: https://amazee.io has cloud and dedicated solutions.
 
## Installation

```bash
composer require freedomtechhosting/ft-lagoon-php
```

## Usage

```php
$lagoon = new \FreedomtechHosting\FtLagoonPhp\Client(
    [
        'ssh_user' => 'lagoon',
        'ssh_server' => 'ssh.lagoon.amazeeio.cloud',
        'ssh_port' => '32222',
        'endpoint' => 'https://api.lagoon.amazeeio.cloud/graphql',
        'ssh_private_key_file' => getenv('HOME') . '/.ssh/id_rsa'
    ]
);
```

Table of supported API functions:

| Function | Description |
|----------|-------------|
| **Project Management** ||
| `createLagoonProject` | Create a new Lagoon project |
| `createLagoonProjectInOrganization` | Create a new Lagoon project within an organization |
| `getAllProjects` | Get all projects from the API |
| `getProjectByName` | Get a project by name |
| **Project Variables** ||
| `getProjectVariablesByName` | Get all variables for a project |
| `getProjectVariableByName` | Get a specific variable for a project |
| `addOrUpdateScopedVariableForProject` | Add/update a variable with specific scope for project |
| `addOrUpdateGlobalVariableForProject` | Add/update a global variable for project |
| `deleteProjectVariableByName` | Delete a variable from project |
| **Environment Variables** ||
| `addOrUpdateScopedVariableForProjectEnvironment` | Add/update scoped variable for environment |
| `getProjectVariablesByNameForEnvironment` | Get all variables for environment |
| `getProjectVariableByNameForEnvironment` | Get specific variable for environment |
| `deleteProjectVariableByNameForEnvironment` | Delete variable from environment |
| **Environment Management** ||
| `deleteProjectEnvironmentByName` | Delete a project environment |

## Examples
For examples of how to use the client, see the demo implementation at https://github.com/Freedomtech-Hosting/ft-lagoon-php-cli


## Sponsoring Organizations

- [Workshop Orange](https://www.workshoporange.co) - Project Delivery Professionals
- [amazee.io](https://www.amazee.io) - Enterprise Hosting  
- [Freedomtech Hosting](https://www.freedomtech.hosting) - Decentralized Hosting

## Contributing
Contributions are welcome! Please feel free to submit an issue or a PR.

## License
This project is licensed under the MIT License - see the LICENSE file for details.
