<?php

declare(strict_types=1);

use EtfsCodingAgent\Service\FileOperationsService;

beforeEach(function () {
    $this->service = new FileOperationsService();
    $this->tempDir = sys_get_temp_dir() . '/file_ops_test_' . uniqid();
    mkdir($this->tempDir, 0755, true);
});

afterEach(function () {
    removeDirectory($this->tempDir);
});

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
    $result = $this->service->listFolderContent($this->tempDir . '/nonexistent');

    expect($result)->toContain('Error:');
    expect($result)->toContain('does not exist');
    expect($result)->toContain('create_directory');
});

it('returns error when getting content of nonexistent file', function () {
    $result = $this->service->getFileContent($this->tempDir . '/nonexistent.txt');

    expect($result)->toContain('Error:');
    expect($result)->toContain('does not exist');
});

it('returns error when getting lines of nonexistent file', function () {
    $result = $this->service->getFileLines($this->tempDir . '/nonexistent.txt', 1, 10);

    expect($result)->toContain('Error:');
    expect($result)->toContain('does not exist');
});

it('returns error when searching in nonexistent file', function () {
    $result = $this->service->searchInFile($this->tempDir . '/nonexistent.txt', 'pattern');

    expect($result)->toContain('Error:');
    expect($result)->toContain('does not exist');
});

it('returns specific lines from file', function () {
    $content = "Line 1\nLine 2\nLine 3\nLine 4\nLine 5";
    $path    = createTestFile($this->tempDir, 'test.txt', $content);

    $result = $this->service->getFileLines($path, 2, 4);

    expect($result)->toContain('Line 2');
    expect($result)->toContain('Line 3');
    expect($result)->toContain('Line 4');
    expect($result)->not->toContain('Line 1');
    expect($result)->not->toContain('Line 5');
});

it('includes line numbers when getting file lines', function () {
    $content = "Line 1\nLine 2\nLine 3";
    $path    = createTestFile($this->tempDir, 'test.txt', $content);

    $result = $this->service->getFileLines($path, 1, 3);

    expect($result)->toContain('1 |');
    expect($result)->toContain('2 |');
    expect($result)->toContain('3 |');
});

it('handles out of bounds line numbers gracefully', function () {
    $content = "Line 1\nLine 2\nLine 3";
    $path    = createTestFile($this->tempDir, 'test.txt', $content);

    $result = $this->service->getFileLines($path, 1, 100);

    expect($result)->toContain('Line 1');
    expect($result)->toContain('Line 3');
});

it('returns message when start line is beyond file length', function () {
    $content = "Line 1\nLine 2";
    $path    = createTestFile($this->tempDir, 'test.txt', $content);

    $result = $this->service->getFileLines($path, 100, 200);

    expect($result)->toContain('only');
    expect($result)->toContain('lines');
});

it('returns correct file metadata', function () {
    $content = "Line 1\nLine 2\nLine 3";
    $path    = createTestFile($this->tempDir, 'test.txt', $content);

    $result = $this->service->getFileInfo($path);

    expect($result->path)->toBe($path);
    expect($result->lineCount)->toBe(3);
    expect($result->extension)->toBe('txt');
    expect($result->sizeBytes)->toBeGreaterThan(0);
});

it('handles files without extension', function () {
    $content = 'Some content';
    $path    = createTestFile($this->tempDir, 'noextension', $content);

    $result = $this->service->getFileInfo($path);

    expect($result->extension)->toBe('(none)');
});

it('finds matches in file', function () {
    $content = "First line\nSearchable content here\nLast line";
    $path    = createTestFile($this->tempDir, 'test.txt', $content);

    $result = $this->service->searchInFile($path, 'Searchable');

    expect($result)->toContain('Found 1 match');
    expect($result)->toContain('Searchable content here');
    expect($result)->toContain('>>>');
});

it('includes context lines when searching', function () {
    $content = "Line 1\nLine 2\nTarget line\nLine 4\nLine 5";
    $path    = createTestFile($this->tempDir, 'test.txt', $content);

    $result = $this->service->searchInFile($path, 'Target', 2);

    expect($result)->toContain('Line 2');
    expect($result)->toContain('Target line');
    expect($result)->toContain('Line 4');
});

it('returns no matches message when pattern not found', function () {
    $content = 'Some content here';
    $path    = createTestFile($this->tempDir, 'test.txt', $content);

    $result = $this->service->searchInFile($path, 'nonexistent');

    expect($result)->toContain('No matches found');
});

it('finds multiple matches in file', function () {
    $content = "Match one\nOther line\nMatch two\nAnother line\nMatch three";
    $path    = createTestFile($this->tempDir, 'test.txt', $content);

    $result = $this->service->searchInFile($path, 'Match');

    expect($result)->toContain('Found 3 match');
});

it('replaces unique string in file', function () {
    $content = 'Hello World';
    $path    = createTestFile($this->tempDir, 'test.txt', $content);

    $this->service->replaceInFile($path, 'World', 'Universe');

    $newContent = file_get_contents($path);
    expect($newContent)->toBe('Hello Universe');
});

it('throws when string not found during replace', function () {
    $content = 'Hello World';
    $path    = createTestFile($this->tempDir, 'test.txt', $content);

    expect(fn () => $this->service->replaceInFile($path, 'nonexistent', 'replacement'))
        ->toThrow(RuntimeException::class, 'not found');
});

it('throws when multiple occurrences found during replace', function () {
    $content = 'Hello Hello World';
    $path    = createTestFile($this->tempDir, 'test.txt', $content);

    expect(fn () => $this->service->replaceInFile($path, 'Hello', 'Hi'))
        ->toThrow(RuntimeException::class, '2 times');
});

it('replaces multiline content in file', function () {
    $content = "Line 1\nLine 2\nLine 3";
    $path    = createTestFile($this->tempDir, 'test.txt', $content);

    $this->service->replaceInFile($path, "Line 2\nLine 3", "Modified 2\nModified 3");

    $newContent = file_get_contents($path);
    expect($newContent)->toBe("Line 1\nModified 2\nModified 3");
});

it('preserves whitespace during replace', function () {
    $content = '    indented line';
    $path    = createTestFile($this->tempDir, 'test.txt', $content);

    $this->service->replaceInFile($path, '    indented', '        double-indented');

    $newContent = file_get_contents($path);
    expect($newContent)->toBe('        double-indented line');
});

it('creates new directory', function () {
    $dirPath = $this->tempDir . '/new_directory';

    $result = $this->service->createDirectory($dirPath);

    expect(is_dir($dirPath))->toBeTrue();
    expect($result)->toContain('Successfully created');
});

it('creates nested directories', function () {
    $dirPath = $this->tempDir . '/parent/child/grandchild';

    $result = $this->service->createDirectory($dirPath);

    expect(is_dir($dirPath))->toBeTrue();
    expect($result)->toContain('Successfully created');
});

it('returns message when directory already exists', function () {
    $dirPath = $this->tempDir . '/existing_directory';
    mkdir($dirPath, 0755, true);

    $result = $this->service->createDirectory($dirPath);

    expect($result)->toContain('already exists');
});
