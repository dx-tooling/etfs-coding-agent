# etfs-coding-agent

A PHP library providing a general-purpose LLM coding agent with workspace tooling capabilities.

## Features

- **File Operations**: Read, write, search, and replace file contents
- **Diff Application**: Apply unified diffs (V4A format) to files
- **Shell Operations**: Execute shell commands in workspace directories
- **Extensible Agent**: Base coding agent with core tools that can be extended

## Installation

```bash
composer require enterprise-tooling-for-symfony/coding-agent
```

## Usage

### Basic Usage

```php
use EtfsCodingAgent\Agent\BaseCodingAgent;
use EtfsCodingAgent\Facade\WorkspaceToolingFacade;
use EtfsCodingAgent\Service\FileOperationsService;
use EtfsCodingAgent\Service\ShellOperationsService;
use EtfsCodingAgent\Service\TextOperationsService;

// Create services
$fileOps = new FileOperationsService();
$shellOps = new ShellOperationsService();
$textOps = new TextOperationsService($fileOps);

// Create facade
$facade = new WorkspaceToolingFacade($fileOps, $textOps, $shellOps);

// Create and use the agent
$agent = new BaseCodingAgent($facade);
```

### Extending the Agent

To add custom tools, extend the `BaseCodingAgent` class:

```php
use EtfsCodingAgent\Agent\BaseCodingAgent;
use EtfsCodingAgent\Facade\WorkspaceToolingFacadeInterface;
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
use EtfsCodingAgent\Facade\WorkspaceToolingFacadeInterface;
use EtfsCodingAgent\Facade\WorkspaceToolingFacade;

interface MyCustomFacadeInterface extends WorkspaceToolingFacadeInterface
{
    public function myCustomMethod(string $param): string;
}

class MyCustomFacade extends WorkspaceToolingFacade implements MyCustomFacadeInterface
{
    public function myCustomMethod(string $param): string
    {
        // Custom implementation
    }
}
```

## Core Tools

The `BaseCodingAgent` provides these core tools:

| Tool | Description |
|------|-------------|
| `get_folder_content` | List files and directories in a folder |
| `get_file_content` | Read full file content |
| `get_file_info` | Get file metadata (lines, size, extension) |
| `get_file_lines` | Read specific lines from a file |
| `search_in_file` | Search for patterns with context |
| `replace_in_file` | Replace unique strings in a file |
| `apply_diff_to_file` | Apply unified diffs to a file |
| `create_directory` | Create directories |
| `run_shell_command` | Execute shell commands |

## Requirements

- PHP 8.4+
- neuron-core/neuron-ai ^2.11
- symfony/process ^7.0
- enterprise-tooling-for-symfony/v4a-fileedit

## License

MIT
