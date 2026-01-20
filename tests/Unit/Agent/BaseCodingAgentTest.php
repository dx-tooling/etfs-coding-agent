<?php

declare(strict_types=1);

use EtfsCodingAgent\Agent\BaseCodingAgent;
use EtfsCodingAgent\Service\WorkspaceToolingServiceInterface;
use NeuronAI\Tools\PropertyType;
use NeuronAI\Tools\Tool;
use NeuronAI\Tools\ToolProperty;

function createMockFacade(): WorkspaceToolingServiceInterface
{
    return new class implements WorkspaceToolingServiceInterface {
        public function getFolderContent(string $pathToFolder): string
        {
            return '';
        }

        public function getFileContent(string $pathToFile): string
        {
            return '';
        }

        public function getFileLines(string $pathToFile, int $startLine, int $endLine): string
        {
            return '';
        }

        public function getFileInfo(string $pathToFile): string
        {
            return '';
        }

        public function searchInFile(string $pathToFile, string $searchPattern, int $contextLines = 3): string
        {
            return '';
        }

        public function replaceInFile(string $pathToFile, string $oldString, string $newString): string
        {
            return '';
        }

        public function applyV4aDiffToFile(string $pathToFile, string $v4aDiff): string
        {
            return '';
        }

        public function createDirectory(string $pathToDirectory): string
        {
            return '';
        }

        public function runShellCommand(string $workingDirectory, string $command): string
        {
            return '';
        }
    };
}

it('registers all expected tools', function () {
    $mockFacade = createMockFacade();
    $agent      = new BaseCodingAgent($mockFacade);

    $reflection = new ReflectionMethod($agent, 'tools');

    /** @var list<Tool> $tools */
    $tools = $reflection->invoke($agent);

    expect($tools)->toBeArray();
    expect($tools)->toHaveCount(9);

    $toolNames = array_map(fn (Tool $tool): string => $tool->getName(), $tools);

    expect($toolNames)->toContain('get_folder_content');
    expect($toolNames)->toContain('get_file_content');
    expect($toolNames)->toContain('get_file_info');
    expect($toolNames)->toContain('get_file_lines');
    expect($toolNames)->toContain('search_in_file');
    expect($toolNames)->toContain('replace_in_file');
    expect($toolNames)->toContain('apply_diff_to_file');
    expect($toolNames)->toContain('create_directory');
    expect($toolNames)->toContain('run_shell_command');
});

it('generates non-empty instructions', function () {
    $mockFacade = createMockFacade();
    $agent      = new BaseCodingAgent($mockFacade);

    $instructions = $agent->instructions();

    expect($instructions)->toBeString();
    expect($instructions)->not->toBeEmpty();
    expect($instructions)->toContain('file');
});

it('formats tool error message correctly', function () {
    $mockFacade = createMockFacade();
    $agent      = new BaseCodingAgent($mockFacade);

    // Create a tool with properties
    $tool = Tool::make('test_tool', 'A test tool')
        ->addProperty(new ToolProperty('required_param', PropertyType::STRING, 'Required', true))
        ->addProperty(new ToolProperty('optional_param', PropertyType::STRING, 'Optional', false));

    // Simulate setting inputs on the tool
    $inputsProperty = new ReflectionProperty($tool, 'inputs');
    $inputsProperty->setValue($tool, ['wrong_param' => 'value']);

    // Create an exception
    $exception = new RuntimeException('Something went wrong');

    // Call the private method
    $reflection = new ReflectionMethod($agent, 'formatToolErrorMessage');
    $result     = $reflection->invoke($agent, $tool, $exception);

    expect($result)->toBeString();
    expect($result)->toContain('Error:');
    expect($result)->toContain('Something went wrong');
    expect($result)->toContain('Tool:');
    expect($result)->toContain('test_tool');
    expect($result)->toContain('Parameters you provided:');
    expect($result)->toContain('wrong_param');
    expect($result)->toContain('Parameters this tool expects:');
    expect($result)->toContain('required_param');
    expect($result)->toContain('optional_param');
    expect($result)->toContain('required');
    expect($result)->toContain('optional');
});
