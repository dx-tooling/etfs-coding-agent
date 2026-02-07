<?php

declare(strict_types=1);

use EtfsCodingAgent\Agent\BaseCodingAgent;
use EtfsCodingAgent\Service\WorkspaceToolingServiceInterface;
use NeuronAI\Exceptions\ToolMaxTriesException;
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

it('throws ToolMaxTriesException when a tool exceeds max attempts', function () {
    $mockFacade = createMockFacade();
    $agent      = new BaseCodingAgent($mockFacade);

    // Default toolMaxTries is 5
    $tool = Tool::make('test_tool', 'A test tool')
        ->setCallable(fn (): string => 'ok');

    $reflection = new ReflectionMethod($agent, 'executeSingleTool');

    // Execute 5 times — should succeed
    for ($i = 0; $i < 5; $i++) {
        $reflection->invoke($agent, $tool);
    }

    expect($tool->getResult())->toBe('ok');

    // 6th attempt should throw
    $reflection->invoke($agent, $tool);
})->throws(ToolMaxTriesException::class, 'too many times');

it('tracks tool attempts per tool name independently', function () {
    $mockFacade = createMockFacade();
    $agent      = new BaseCodingAgent($mockFacade);

    $toolA = Tool::make('tool_a', 'Tool A')->setCallable(fn (): string => 'a');
    $toolB = Tool::make('tool_b', 'Tool B')->setCallable(fn (): string => 'b');

    $reflection = new ReflectionMethod($agent, 'executeSingleTool');

    // Execute tool_a 4 times (under limit of 5)
    for ($i = 0; $i < 4; $i++) {
        $reflection->invoke($agent, $toolA);
    }

    // Execute tool_b 4 times (under limit of 5) — should not be affected by tool_a's count
    for ($i = 0; $i < 4; $i++) {
        $reflection->invoke($agent, $toolB);
    }

    expect($toolA->getResult())->toBe('a');
    expect($toolB->getResult())->toBe('b');
});

it('catches tool execution errors and sets them as result without affecting attempt tracking', function () {
    $mockFacade = createMockFacade();
    $agent      = new BaseCodingAgent($mockFacade);

    $callCount = 0;
    $tool      = Tool::make('flaky_tool', 'A flaky tool')
        ->setCallable(function () use (&$callCount): string {
            $callCount++;
            throw new RuntimeException('Flaky error');
        });

    $reflection = new ReflectionMethod($agent, 'executeSingleTool');

    // Execute — should catch the error and set it as result
    $reflection->invoke($agent, $tool);

    expect($tool->getResult())->toContain('Flaky error');
    expect($callCount)->toBe(1);
});
