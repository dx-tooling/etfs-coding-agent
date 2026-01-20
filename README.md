# Enterprise Tooling for Symfony — Coding Agent library

[![Quality](https://github.com/dx-tooling/etfs-coding-agent/actions/workflows/quality.yml/badge.svg)](https://github.com/dx-tooling/etfs-coding-agent/actions/workflows/quality.yml)
[![Tests](https://github.com/dx-tooling/etfs-coding-agent/actions/workflows/tests.yml/badge.svg)](https://github.com/dx-tooling/etfs-coding-agent/actions/workflows/tests.yml)

A PHP library providing a general-purpose LLM coding agent with workspace tooling capabilities.

## Features

- **File Operations**: Read, write, search, and replace file contents
- **Diff Application**: Apply unified diffs (V4A format) to files
- **Shell Operations**: Execute shell commands in workspace directories
- **Extensible Agent**: Base coding agent with core tools that can be extended

## Requirements

- PHP 8.4 or higher
- Composer

## Installation

```bash
composer require enterprise-tooling-for-symfony/coding-agent
```

## Usage

### Basic Usage

```php
use EtfsCodingAgent\Agent\BaseCodingAgent;
use EtfsCodingAgent\Service\WorkspaceToolingService;
use EtfsCodingAgent\Service\FileOperationsService;
use EtfsCodingAgent\Service\ShellOperationsService;
use EtfsCodingAgent\Service\TextOperationsService;

// Create services
$fileOps = new FileOperationsService();
$shellOps = new ShellOperationsService();
$textOps = new TextOperationsService($fileOps);

// Create facade
$facade = new WorkspaceToolingService($fileOps, $textOps, $shellOps);

// Create and use the agent
$agent = new BaseCodingAgent($facade);
```

### Extending the Agent

To add custom tools, extend the `BaseCodingAgent` class:

```php
use EtfsCodingAgent\Agent\BaseCodingAgent;
use EtfsCodingAgent\Service\WorkspaceToolingServiceInterface;
use NeuronAI\Tools\Tool;
use NeuronAI\Tools\ToolProperty;
use NeuronAI\Tools\PropertyType;

class MyCustomAgent extends BaseCodingAgent
{
    public function __construct(
        private readonly MyCustomFacadeInterface $customFacade
    ) {
        parent::__construct($customFacade);
    }

    protected function tools(): array
    {
        return array_merge(parent::tools(), [
            Tool::make(
                'my_custom_tool',
                'Description of my custom tool',
            )->addProperty(
                new ToolProperty('param', PropertyType::STRING, 'Parameter description', true)
            )->setCallable(fn (string $param): string => $this->customFacade->myCustomMethod($param)),
        ]);
    }

    public function instructions(): string
    {
        // Return custom instructions or extend parent
        return parent::instructions();
    }
}
```

### Extending the Facade

To add custom operations, extend the facade interface and implementation:

```php
use EtfsCodingAgent\Service\WorkspaceToolingServiceInterface;
use EtfsCodingAgent\Service\WorkspaceToolingService;

interface MyCustomFacadeInterface extends WorkspaceToolingServiceInterface
{
    public function myCustomMethod(string $param): string;
}

class MyCustomFacade extends WorkspaceToolingService implements MyCustomFacadeInterface
{
    public function myCustomMethod(string $param): string
    {
        // Custom implementation
    }
}
```

## Core Tools

The `BaseCodingAgent` provides these core tools:

| Tool                 | Description                                |
| -------------------- | ------------------------------------------ |
| `get_folder_content` | List files and directories in a folder     |
| `get_file_content`   | Read full file content                     |
| `get_file_info`      | Get file metadata (lines, size, extension) |
| `get_file_lines`     | Read specific lines from a file            |
| `search_in_file`     | Search for patterns with context           |
| `replace_in_file`    | Replace unique strings in a file           |
| `apply_diff_to_file` | Apply unified diffs to a file              |
| `create_directory`   | Create directories                         |
| `run_shell_command`  | Execute shell commands                     |

## Development Setup

This project uses mise-en-place for tool management and Docker Compose for a deterministic development environment.

### Prerequisites

- Docker Desktop
- mise-en-place (https://mise.jdx.dev)

**Note**: You only need mise and Docker on your host machine. PHP, Node.js, and all other tools run inside the Docker container.

### Setup

1. Clone the repository:

    ```bash
    git clone <repository-url>
    cd etfs-coding-agent
    ```

2. Trust the mise configuration:

    ```bash
    mise trust
    ```

3. Install dependencies (runs in an ephemeral container):

    ```bash
    mise run in-app-container composer install
    mise run in-app-container mise trust
    ```

4. Run quality checks:

    ```bash
    mise run quality
    ```

5. Run tests:
    ```bash
    mise run tests
    ```

**Note**: Containers are created ephemerally for each command. There's no need to start or stop containers manually - they're created on-demand and automatically removed after execution.

### Available Commands

- `mise run quality` - Run all quality tools (PHP CS Fixer, Prettier, PHPStan)
- `mise run quality --check-violations` - Check for violations without fixing
- `mise run tests` - Run the test suite

### Docker Container

The Docker container provides a consistent PHP 8.4 CLI environment with:

- PHP 8.4 with required extensions (mbstring, pcntl, bcmath, intl, zip)
- Composer
- mise-en-place (for managing tools inside the container)
- Node.js 24 (pre-installed via mise during Docker build to avoid download overhead)

Containers are created **ephemerally** for each command execution - they're created on-demand, run the command, and are automatically removed afterward. This ensures a clean, consistent environment for every execution without needing to manage container lifecycle.

All development commands run inside ephemeral containers via mise tasks. To execute commands directly in an ephemeral container:

```bash
mise run in-app-container <command>
```

## Testing

The library includes comprehensive unit tests using Pest. Run tests with:

```bash
mise run tests
```

Or directly:

```bash
php vendor/bin/pest
```

## Code Quality

The project enforces strict code quality standards:

- **PHP CS Fixer**: Symfony coding standards with custom rules
- **PHPStan**: Level 10 (maximum strictness) with 100% type coverage (return, param, property, constant)
- **Prettier**: Code formatting for JSON, YAML, Markdown files

Run all quality checks:

```bash
mise run quality
```

## License

MIT

## Contributing

Contributions are welcome! Please ensure that:

1. All tests pass (`mise run tests`)
2. Code quality checks pass (`mise run quality`)
3. Code follows the project's coding standards
