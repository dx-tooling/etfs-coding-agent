<?php

declare(strict_types=1);

namespace EtfsCodingAgent\Agent;

use EtfsCodingAgent\Service\WorkspaceToolingServiceInterface;
use NeuronAI\Agent;
use NeuronAI\Exceptions\ToolMaxTriesException;
use NeuronAI\Observability\Events\AgentError;
use NeuronAI\Observability\Events\ToolCalled;
use NeuronAI\Observability\Events\ToolCalling;
use NeuronAI\Providers\AIProviderInterface;
use NeuronAI\Providers\OpenAI\OpenAI;
use NeuronAI\SystemPrompt;
use NeuronAI\Tools\PropertyType;
use NeuronAI\Tools\Tool;
use NeuronAI\Tools\ToolInterface;
use NeuronAI\Tools\ToolProperty;
use NeuronAI\Tools\ToolPropertyInterface;
use Throwable;

class BaseCodingAgent extends Agent
{
    private const int MAX_CONSECUTIVE_IDENTICAL_CALLS = 3;

    /**
     * Fingerprint of the last tool call (tool name + serialized inputs).
     * Used to detect consecutive identical calls — the signature of an
     * infinite loop (e.g. the agent calling get_workspace_rules with the
     * same arguments over and over after context loss).
     *
     * @see https://github.com/dx-tooling/sitebuilder-webapp/issues/75
     */
    private string $lastToolCallFingerprint = '';

    private int $consecutiveIdenticalCalls = 0;

    public function __construct(
        protected readonly WorkspaceToolingServiceInterface $workspaceToolingFacade
    ) {
    }

    protected function provider(): AIProviderInterface
    {
        /** @var string $apiKey */
        $apiKey = $_ENV['CODING_AGENT_OPENAI_API_KEY'] ?? $_ENV['OPENAI_API_KEY'] ?? '';

        return new OpenAI(
            $apiKey,
            'gpt-4.1',
        );
    }

    public function instructions(): string
    {
        return (string) new SystemPrompt(
            $this->getBackgroundInstructions(),
            $this->getStepInstructions(),
            $this->getOutputInstructions(),
        );
    }

    /**
     * Override this method to customize the background/context instructions.
     *
     * @return list<string>
     */
    protected function getBackgroundInstructions(): array
    {
        return [
            'You are a friendly AI Agent that helps the user to work with files in a workspace.',
            'You have access to tools for exploring folders, reading files, applying edits, and running shell commands.',
            '',
            'EFFICIENT FILE READING:',
            '- Use get_file_info first to check file size before reading',
            '- For large files (>100 lines), use search_in_file to find relevant sections',
            '- Use get_file_lines to read only the lines you need',
            '- Only use get_file_content for small files or when you need the entire content',
            '',
            'EFFICIENT FILE EDITING:',
            '- Use replace_in_file for simple, targeted edits (preferred for single changes)',
            '- The old_string must be unique - include surrounding context if needed',
            '- Use apply_diff_to_file only for complex multi-location edits',
            '- Always search or read the relevant section before editing to ensure accuracy',
            '',
            'DISCOVERY IS KEY:',
            '- Always explore the workspace structure before making changes',
            '- Examine existing files to understand patterns before creating new ones',
        ];
    }

    /**
     * Override this method to customize the step-by-step instructions.
     *
     * @return list<string>
     */
    protected function getStepInstructions(): array
    {
        return [
            '1. EXPLORE: List the workspace root folder to understand its structure.',
            '2. INVESTIGATE: Use get_file_info + search_in_file to efficiently explore files.',
            '3. PLAN: Understand what files need to be created or modified.',
            '4. EDIT: Use replace_in_file for targeted edits, apply_diff_to_file for complex changes.',
            '5. VERIFY: Verify your changes are correct.',
        ];
    }

    /**
     * Override this method to customize the output instructions.
     *
     * @return list<string>
     */
    protected function getOutputInstructions(): array
    {
        return [
            'Summarize what changes were made and why.',
            'If any operations fail, analyze the errors and fix them.',
        ];
    }

    /**
     * Returns the core tools available to the agent.
     * Override this method to add additional tools.
     *
     * @return list<ToolInterface>
     */
    protected function tools(): array
    {
        return [
            Tool::make(
                'get_folder_content',
                'List the files and directories in a folder. Returns a newline-separated list of file names.',
            )->addProperty(
                new ToolProperty(
                    'path',
                    PropertyType::STRING,
                    'The absolute path to the folder whose contents shall be listed.',
                    true
                )
            )->setCallable(fn (string $path): string => $this->workspaceToolingFacade->getFolderContent($path)),

            Tool::make(
                'get_file_content',
                'Read and return the full content of a file. For large files, prefer get_file_info + get_file_lines or search_in_file.',
            )->addProperty(
                new ToolProperty(
                    'path',
                    PropertyType::STRING,
                    'The absolute path to the file to read.',
                    true
                )
            )->setCallable(fn (string $path): string => $this->workspaceToolingFacade->getFileContent($path)),

            Tool::make(
                'get_file_info',
                'Get file metadata (line count, size, extension) without reading the full content. Use this first to decide whether to read the whole file or specific lines.',
            )->addProperty(
                new ToolProperty(
                    'path',
                    PropertyType::STRING,
                    'The absolute path to the file.',
                    true
                )
            )->setCallable(fn (string $path): string => $this->workspaceToolingFacade->getFileInfo($path)),

            Tool::make(
                'get_file_lines',
                'Read specific lines from a file. Lines are 1-indexed. Returns lines with line numbers prefixed.',
            )->addProperty(
                new ToolProperty(
                    'path',
                    PropertyType::STRING,
                    'The absolute path to the file.',
                    true
                )
            )->addProperty(
                new ToolProperty(
                    'start_line',
                    PropertyType::INTEGER,
                    'The first line to read (1-indexed).',
                    true
                )
            )->addProperty(
                new ToolProperty(
                    'end_line',
                    PropertyType::INTEGER,
                    'The last line to read (inclusive).',
                    true
                )
            )->setCallable(fn (string $path, int $start_line, int $end_line): string => $this->workspaceToolingFacade->getFileLines($path, $start_line, $end_line)),

            Tool::make(
                'search_in_file',
                'Search for a text pattern in a file. Returns matching lines with surrounding context. Use this to find where to make edits.',
            )->addProperty(
                new ToolProperty(
                    'path',
                    PropertyType::STRING,
                    'The absolute path to the file to search.',
                    true
                )
            )->addProperty(
                new ToolProperty(
                    'pattern',
                    PropertyType::STRING,
                    'The text to search for (case-insensitive).',
                    true
                )
            )->addProperty(
                new ToolProperty(
                    'context_lines',
                    PropertyType::INTEGER,
                    'Number of lines to show before and after each match (default: 3).',
                    false
                )
            )->setCallable(fn (string $path, string $pattern, ?int $context_lines = null): string => $this->workspaceToolingFacade->searchInFile($path, $pattern, $context_lines ?? 3)),

            Tool::make(
                'replace_in_file',
                'Replace a specific string in a file. The old_string must be unique in the file. Include enough context (surrounding lines) to make it unique. This is simpler than apply_diff_to_file for targeted edits.',
            )->addProperty(
                new ToolProperty(
                    'path',
                    PropertyType::STRING,
                    'The absolute path to the file to modify.',
                    true
                )
            )->addProperty(
                new ToolProperty(
                    'old_string',
                    PropertyType::STRING,
                    'The exact text to find and replace. Must be unique in the file. Include surrounding lines if needed for uniqueness.',
                    true
                )
            )->addProperty(
                new ToolProperty(
                    'new_string',
                    PropertyType::STRING,
                    'The text to replace it with.',
                    true
                )
            )->setCallable(fn (string $path, string $old_string, string $new_string): string => $this->workspaceToolingFacade->replaceInFile($path, $old_string, $new_string)),

            Tool::make(
                'apply_diff_to_file',
                'Apply a unified diff (v4a format) to modify a file. The diff should use the standard unified diff format with @@ line markers, context lines (space prefix), removed lines (- prefix), and added lines (+ prefix). Example: @@ -1,3 +1,4 @@\n line1\n line2\n+new line\n line3',
            )->addProperty(
                new ToolProperty(
                    'path',
                    PropertyType::STRING,
                    'The absolute path to the file to modify.',
                    true
                )
            )->addProperty(
                new ToolProperty(
                    'diff',
                    PropertyType::STRING,
                    'The unified diff to apply. Use @@ -start,count +start,count @@ header, space-prefixed context lines, minus-prefixed lines to remove, and plus-prefixed lines to add.',
                    true
                )
            )->setCallable(fn (string $path, string $diff): string => $this->workspaceToolingFacade->applyV4aDiffToFile($path, $diff)),

            Tool::make(
                'create_directory',
                'Create a new directory (and any necessary parent directories). Returns success message or indicates if directory already exists.',
            )->addProperty(
                new ToolProperty(
                    'path',
                    PropertyType::STRING,
                    'The absolute path to the directory to create.',
                    true
                )
            )->setCallable(fn (string $path): string => $this->workspaceToolingFacade->createDirectory($path)),

            Tool::make(
                'run_shell_command',
                'Run a shell command in a specified working directory. Returns the command output or error message.',
            )->addProperty(
                new ToolProperty(
                    'working_directory',
                    PropertyType::STRING,
                    'The absolute path to the directory where the command should run.',
                    true
                )
            )->addProperty(
                new ToolProperty(
                    'command',
                    PropertyType::STRING,
                    'The shell command to execute.',
                    true
                )
            )->setCallable(fn (string $working_directory, string $command): string => $this->workspaceToolingFacade->runShellCommand($working_directory, $command)),
        ];
    }

    /**
     * Override to catch tool execution errors and return them as results instead of crashing.
     * This allows the agent to learn from its mistakes and retry with correct parameters.
     *
     * Also detects infinite tool-call loops by tracking consecutive identical
     * calls (same tool name + same arguments). A coding agent legitimately
     * calls the same tool many times with different arguments (e.g. reading
     * dozens of files), so a simple per-tool-name counter is too restrictive.
     * Instead, we only flag when the exact same call repeats consecutively,
     * which is the signature of a context-loss loop.
     *
     * @see https://github.com/dx-tooling/sitebuilder-webapp/issues/75
     *
     * @throws ToolMaxTriesException
     */
    protected function executeSingleTool(ToolInterface $tool): void
    {
        $this->detectInfiniteLoop($tool);

        $this->notify('tool-calling', new ToolCalling($tool));

        try {
            $tool->execute();
        } catch (Throwable $exception) {
            $this->notify('error', new AgentError($exception));

            // Instead of re-throwing, set the error as the tool result so the agent can learn
            if ($tool instanceof Tool) {
                $errorMessage = $this->formatToolErrorMessage($tool, $exception);
                $tool->setResult($errorMessage);
            }
            // If not a Tool instance, we can't set result, so just continue
            // The tool result will be empty, which is still better than crashing
        }

        $this->notify('tool-called', new ToolCalled($tool));
    }

    /**
     * Detect infinite tool-call loops by tracking consecutive identical calls.
     *
     * A "fingerprint" is the combination of tool name and serialized inputs.
     * When the same fingerprint appears consecutively more than the threshold,
     * the agent is likely stuck in a loop and we throw to break out.
     *
     * @throws ToolMaxTriesException
     */
    private function detectInfiniteLoop(ToolInterface $tool): void
    {
        $fingerprint = $tool->getName() . ':' . json_encode($tool->getInputs(), JSON_THROW_ON_ERROR);

        if ($fingerprint === $this->lastToolCallFingerprint) {
            ++$this->consecutiveIdenticalCalls;
        } else {
            $this->lastToolCallFingerprint   = $fingerprint;
            $this->consecutiveIdenticalCalls = 1;
        }

        if ($this->consecutiveIdenticalCalls > self::MAX_CONSECUTIVE_IDENTICAL_CALLS) {
            $exception = new ToolMaxTriesException(
                "Tool {$tool->getName()} has been called {$this->consecutiveIdenticalCalls} times consecutively with identical arguments. This looks like an infinite loop."
            );
            $this->notify('error', new AgentError($exception));

            throw $exception;
        }
    }

    private function formatToolErrorMessage(Tool $tool, Throwable $exception): string
    {
        $message   = $exception->getMessage();
        $toolName  = $tool->getName();
        $inputs    = $tool->getInputs();
        $inputKeys = array_keys($inputs);
        $inputList = $inputKeys !== [] ? implode(', ', $inputKeys) : '(none provided)';

        /** @var ToolPropertyInterface[] $properties */
        $properties     = $tool->getProperties();
        $expectedParams = [];

        foreach ($properties as $property) {
            $required         = $property->isRequired() ? 'required' : 'optional';
            $expectedParams[] = "{$property->getName()} ({$required})";
        }

        $expectedList = implode(', ', $expectedParams);

        return <<<ERROR
            Error: {$message}

            Tool: {$toolName}
            Parameters you provided: {$inputList}
            Parameters this tool expects: {$expectedList}

            Please check the tool definition and provide the correct parameters.
            ERROR;
    }
}
