<?php

declare(strict_types=1);

use EtfsCodingAgent\Service\ShellOperationsService;

function createShellTestDir(): string
{
    $tempDir = sys_get_temp_dir() . '/shell_ops_test_' . uniqid();
    mkdir($tempDir, 0755, true);

    return $tempDir;
}

function removeShellTestDir(string $dir): void
{
    if (is_dir($dir)) {
        rmdir($dir);
    }
}

it('throws when directory does not exist', function () {
    $service = new ShellOperationsService();

    expect(fn () => $service->runCommand('/nonexistent_directory_' . uniqid(), 'ls'))
        ->toThrow(RuntimeException::class, 'does not exist');
});

it('returns output on successful command', function () {
    $service = new ShellOperationsService();
    $tempDir = createShellTestDir();

    try {
        $result = $service->runCommand($tempDir, 'echo hello');

        expect($result)->toContain('hello');
    } finally {
        removeShellTestDir($tempDir);
    }
});

it('returns error message with exit code on failed command', function () {
    $service = new ShellOperationsService();
    $tempDir = createShellTestDir();

    try {
        $result = $service->runCommand($tempDir, 'exit 1');

        expect($result)->toContain('failed');
        expect($result)->toContain('exit code');
        expect($result)->toContain('1');
    } finally {
        removeShellTestDir($tempDir);
    }
});

it('combines stdout and stderr', function () {
    $service = new ShellOperationsService();
    $tempDir = createShellTestDir();

    try {
        $result = $service->runCommand($tempDir, 'echo stdout && echo stderr >&2');

        expect($result)->toContain('stdout');
        expect($result)->toContain('stderr');
    } finally {
        removeShellTestDir($tempDir);
    }
});
