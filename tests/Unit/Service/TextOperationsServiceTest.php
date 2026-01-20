<?php

declare(strict_types=1);

use EtfsCodingAgent\Service\FileOperationsService;
use EtfsCodingAgent\Service\TextOperationsService;

function createTextTestDir(): string
{
    $tempDir = sys_get_temp_dir() . '/text_ops_test_' . uniqid();
    mkdir($tempDir, 0755, true);

    return $tempDir;
}

function removeTextTestDir(string $dir): void
{
    if (!is_dir($dir)) {
        return;
    }

    $files = scandir($dir);
    if ($files === false) {
        return;
    }

    foreach ($files as $file) {
        if ($file === '.' || $file === '..') {
            continue;
        }
        unlink($dir . '/' . $file);
    }

    rmdir($dir);
}

function createTextTestFile(string $tempDir, string $filename, string $content): string
{
    $path = $tempDir . '/' . $filename;
    file_put_contents($path, $content);

    return $path;
}

it('applies simple addition diff to file content', function () {
    $fileOps = new FileOperationsService();
    $service = new TextOperationsService($fileOps);
    $tempDir = createTextTestDir();

    try {
        $originalContent = "line 1\nline 2\nline 3";
        $path            = createTextTestFile($tempDir, 'test.txt', $originalContent);

        $diff = " line 1\n line 2\n+new line\n line 3";

        $result = $service->applyDiffToFile($path, $diff);

        expect($result)->toContain('line 1');
        expect($result)->toContain('line 2');
        expect($result)->toContain('new line');
        expect($result)->toContain('line 3');
    } finally {
        removeTextTestDir($tempDir);
    }
});

it('applies deletion diff to file content', function () {
    $fileOps = new FileOperationsService();
    $service = new TextOperationsService($fileOps);
    $tempDir = createTextTestDir();

    try {
        $originalContent = "line 1\nline 2\nline 3";
        $path            = createTextTestFile($tempDir, 'test.txt', $originalContent);

        $diff = " line 1\n-line 2\n line 3";

        $result = $service->applyDiffToFile($path, $diff);

        expect($result)->toContain('line 1');
        expect($result)->not->toContain('line 2');
        expect($result)->toContain('line 3');
    } finally {
        removeTextTestDir($tempDir);
    }
});

it('applies replacement diff with context', function () {
    $fileOps = new FileOperationsService();
    $service = new TextOperationsService($fileOps);
    $tempDir = createTextTestDir();

    try {
        $originalContent = "line 1\nold line\nline 3";
        $path            = createTextTestFile($tempDir, 'test.txt', $originalContent);

        $diff = " line 1\n-old line\n+new line\n line 3";

        $result = $service->applyDiffToFile($path, $diff);

        expect($result)->toContain('line 1');
        expect($result)->not->toContain('old line');
        expect($result)->toContain('new line');
        expect($result)->toContain('line 3');
    } finally {
        removeTextTestDir($tempDir);
    }
});
