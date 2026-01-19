<?php

declare(strict_types=1);

namespace EtfsCodingAgent\Facade;

interface WorkspaceToolingFacadeInterface
{
    public function getFolderContent(string $pathToFolder): string;

    public function getFileContent(string $pathToFile): string;

    public function getFileLines(string $pathToFile, int $startLine, int $endLine): string;

    public function getFileInfo(string $pathToFile): string;

    public function searchInFile(string $pathToFile, string $searchPattern, int $contextLines = 3): string;

    public function replaceInFile(string $pathToFile, string $oldString, string $newString): string;

    public function applyV4aDiffToFile(string $pathToFile, string $v4aDiff): string;

    public function createDirectory(string $pathToDirectory): string;

    public function runShellCommand(string $workingDirectory, string $command): string;
}
