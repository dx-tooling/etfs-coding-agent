<?php

declare(strict_types=1);

use EtfsCodingAgent\Service\FileOperationsService;

function createTempDir(): string
{
    $tempDir = sys_get_temp_dir() . '/file_ops_test_' . uniqid();
    mkdir($tempDir, 0755, true);

    return $tempDir;
}

function removeDirectory(string $dir): void
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

        $path = $dir . '/' . $file;
        if (is_dir($path)) {
            removeDirectory($path);
        } else {
            unlink($path);
        }
    }

    rmdir($dir);
}

function createTestFile(string $tempDir, string $filename, string $content): string
{
    $path = $tempDir . '/' . $filename;
    file_put_contents($path, $content);

    return $path;
}

it('returns error when listing nonexistent directory', function () {
    $service = new FileOperationsService();
    $tempDir = createTempDir();

    try {
        $result = $service->listFolderContent($tempDir . '/nonexistent');

        expect($result)->toContain('Error:');
        expect($result)->toContain('does not exist');
        expect($result)->toContain('create_directory');
    } finally {
        removeDirectory($tempDir);
    }
});

it('returns error when getting content of nonexistent file', function () {
    $service = new FileOperationsService();
    $tempDir = createTempDir();

    try {
        $result = $service->getFileContent($tempDir . '/nonexistent.txt');

        expect($result)->toContain('Error:');
        expect($result)->toContain('does not exist');
    } finally {
        removeDirectory($tempDir);
    }
});

it('returns error when getting lines of nonexistent file', function () {
    $service = new FileOperationsService();
    $tempDir = createTempDir();

    try {
        $result = $service->getFileLines($tempDir . '/nonexistent.txt', 1, 10);

        expect($result)->toContain('Error:');
        expect($result)->toContain('does not exist');
    } finally {
        removeDirectory($tempDir);
    }
});

it('returns error when searching in nonexistent file', function () {
    $service = new FileOperationsService();
    $tempDir = createTempDir();

    try {
        $result = $service->searchInFile($tempDir . '/nonexistent.txt', 'pattern');

        expect($result)->toContain('Error:');
        expect($result)->toContain('does not exist');
    } finally {
        removeDirectory($tempDir);
    }
});

it('returns specific lines from file', function () {
    $service = new FileOperationsService();
    $tempDir = createTempDir();

    try {
        $content = "Line 1\nLine 2\nLine 3\nLine 4\nLine 5";
        $path    = createTestFile($tempDir, 'test.txt', $content);

        $result = $service->getFileLines($path, 2, 4);

        expect($result)->toContain('Line 2');
        expect($result)->toContain('Line 3');
        expect($result)->toContain('Line 4');
        expect($result)->not->toContain('Line 1');
        expect($result)->not->toContain('Line 5');
    } finally {
        removeDirectory($tempDir);
    }
});

it('includes line numbers when getting file lines', function () {
    $service = new FileOperationsService();
    $tempDir = createTempDir();

    try {
        $content = "Line 1\nLine 2\nLine 3";
        $path    = createTestFile($tempDir, 'test.txt', $content);

        $result = $service->getFileLines($path, 1, 3);

        expect($result)->toContain('1 |');
        expect($result)->toContain('2 |');
        expect($result)->toContain('3 |');
    } finally {
        removeDirectory($tempDir);
    }
});

it('handles out of bounds line numbers gracefully', function () {
    $service = new FileOperationsService();
    $tempDir = createTempDir();

    try {
        $content = "Line 1\nLine 2\nLine 3";
        $path    = createTestFile($tempDir, 'test.txt', $content);

        $result = $service->getFileLines($path, 1, 100);

        expect($result)->toContain('Line 1');
        expect($result)->toContain('Line 3');
    } finally {
        removeDirectory($tempDir);
    }
});

it('returns message when start line is beyond file length', function () {
    $service = new FileOperationsService();
    $tempDir = createTempDir();

    try {
        $content = "Line 1\nLine 2";
        $path    = createTestFile($tempDir, 'test.txt', $content);

        $result = $service->getFileLines($path, 100, 200);

        expect($result)->toContain('only');
        expect($result)->toContain('lines');
    } finally {
        removeDirectory($tempDir);
    }
});

it('returns correct file metadata', function () {
    $service = new FileOperationsService();
    $tempDir = createTempDir();

    try {
        $content = "Line 1\nLine 2\nLine 3";
        $path    = createTestFile($tempDir, 'test.txt', $content);

        $result = $service->getFileInfo($path);

        expect($result->path)->toBe($path);
        expect($result->lineCount)->toBe(3);
        expect($result->extension)->toBe('txt');
        expect($result->sizeBytes)->toBeGreaterThan(0);
    } finally {
        removeDirectory($tempDir);
    }
});

it('handles files without extension', function () {
    $service = new FileOperationsService();
    $tempDir = createTempDir();

    try {
        $content = 'Some content';
        $path    = createTestFile($tempDir, 'noextension', $content);

        $result = $service->getFileInfo($path);

        expect($result->extension)->toBe('(none)');
    } finally {
        removeDirectory($tempDir);
    }
});

it('finds matches in file', function () {
    $service = new FileOperationsService();
    $tempDir = createTempDir();

    try {
        $content = "First line\nSearchable content here\nLast line";
        $path    = createTestFile($tempDir, 'test.txt', $content);

        $result = $service->searchInFile($path, 'Searchable');

        expect($result)->toContain('Found 1 match');
        expect($result)->toContain('Searchable content here');
        expect($result)->toContain('>>>');
    } finally {
        removeDirectory($tempDir);
    }
});

it('includes context lines when searching', function () {
    $service = new FileOperationsService();
    $tempDir = createTempDir();

    try {
        $content = "Line 1\nLine 2\nTarget line\nLine 4\nLine 5";
        $path    = createTestFile($tempDir, 'test.txt', $content);

        $result = $service->searchInFile($path, 'Target', 2);

        expect($result)->toContain('Line 2');
        expect($result)->toContain('Target line');
        expect($result)->toContain('Line 4');
    } finally {
        removeDirectory($tempDir);
    }
});

it('returns no matches message when pattern not found', function () {
    $service = new FileOperationsService();
    $tempDir = createTempDir();

    try {
        $content = 'Some content here';
        $path    = createTestFile($tempDir, 'test.txt', $content);

        $result = $service->searchInFile($path, 'nonexistent');

        expect($result)->toContain('No matches found');
    } finally {
        removeDirectory($tempDir);
    }
});

it('finds multiple matches in file', function () {
    $service = new FileOperationsService();
    $tempDir = createTempDir();

    try {
        $content = "Match one\nOther line\nMatch two\nAnother line\nMatch three";
        $path    = createTestFile($tempDir, 'test.txt', $content);

        $result = $service->searchInFile($path, 'Match');

        expect($result)->toContain('Found 3 match');
    } finally {
        removeDirectory($tempDir);
    }
});

it('replaces unique string in file', function () {
    $service = new FileOperationsService();
    $tempDir = createTempDir();

    try {
        $content = 'Hello World';
        $path    = createTestFile($tempDir, 'test.txt', $content);

        $service->replaceInFile($path, 'World', 'Universe');

        $newContent = file_get_contents($path);
        expect($newContent)->toBe('Hello Universe');
    } finally {
        removeDirectory($tempDir);
    }
});

it('throws when string not found during replace', function () {
    $service = new FileOperationsService();
    $tempDir = createTempDir();

    try {
        $content = 'Hello World';
        $path    = createTestFile($tempDir, 'test.txt', $content);

        expect(fn () => $service->replaceInFile($path, 'nonexistent', 'replacement'))
            ->toThrow(RuntimeException::class, 'not found');
    } finally {
        removeDirectory($tempDir);
    }
});

it('throws when multiple occurrences found during replace', function () {
    $service = new FileOperationsService();
    $tempDir = createTempDir();

    try {
        $content = 'Hello Hello World';
        $path    = createTestFile($tempDir, 'test.txt', $content);

        expect(fn () => $service->replaceInFile($path, 'Hello', 'Hi'))
            ->toThrow(RuntimeException::class, '2 times');
    } finally {
        removeDirectory($tempDir);
    }
});

it('replaces multiline content in file', function () {
    $service = new FileOperationsService();
    $tempDir = createTempDir();

    try {
        $content = "Line 1\nLine 2\nLine 3";
        $path    = createTestFile($tempDir, 'test.txt', $content);

        $service->replaceInFile($path, "Line 2\nLine 3", "Modified 2\nModified 3");

        $newContent = file_get_contents($path);
        expect($newContent)->toBe("Line 1\nModified 2\nModified 3");
    } finally {
        removeDirectory($tempDir);
    }
});

it('preserves whitespace during replace', function () {
    $service = new FileOperationsService();
    $tempDir = createTempDir();

    try {
        $content = '    indented line';
        $path    = createTestFile($tempDir, 'test.txt', $content);

        $service->replaceInFile($path, '    indented', '        double-indented');

        $newContent = file_get_contents($path);
        expect($newContent)->toBe('        double-indented line');
    } finally {
        removeDirectory($tempDir);
    }
});

it('creates new directory', function () {
    $service = new FileOperationsService();
    $tempDir = createTempDir();

    try {
        $dirPath = $tempDir . '/new_directory';

        $result = $service->createDirectory($dirPath);

        expect(is_dir($dirPath))->toBeTrue();
        expect($result)->toContain('Successfully created');
    } finally {
        removeDirectory($tempDir);
    }
});

it('creates nested directories', function () {
    $service = new FileOperationsService();
    $tempDir = createTempDir();

    try {
        $dirPath = $tempDir . '/parent/child/grandchild';

        $result = $service->createDirectory($dirPath);

        expect(is_dir($dirPath))->toBeTrue();
        expect($result)->toContain('Successfully created');
    } finally {
        removeDirectory($tempDir);
    }
});

it('returns message when directory already exists', function () {
    $service = new FileOperationsService();
    $tempDir = createTempDir();

    try {
        $dirPath = $tempDir . '/existing_directory';
        mkdir($dirPath, 0755, true);

        $result = $service->createDirectory($dirPath);

        expect($result)->toContain('already exists');
    } finally {
        removeDirectory($tempDir);
    }
});
