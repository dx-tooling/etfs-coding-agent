<?php

declare(strict_types=1);

use EtfsCodingAgent\Service\Dto\FileInfoDto;

it('formats toString correctly', function () {
    $dto = new FileInfoDto(
        '/path/to/file.txt',
        100,
        2048,
        'txt'
    );

    $result = $dto->toString();

    expect($result)->toBeString();
    expect($result)->toContain('File: /path/to/file.txt');
    expect($result)->toContain('Lines: 100');
    expect($result)->toContain('Size: 2048 bytes');
    expect($result)->toContain('Extension: txt');
});

it('handles file without extension', function () {
    $dto = new FileInfoDto(
        '/path/to/Makefile',
        50,
        1024,
        '(none)'
    );

    $result = $dto->toString();

    expect($result)->toContain('Extension: (none)');
});
