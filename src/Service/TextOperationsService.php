<?php

declare(strict_types=1);

namespace EtfsCodingAgent\Service;

use V4AFileEdit\ApplyDiff;

final readonly class TextOperationsService
{
    public function __construct(
        private FileOperationsServiceInterface $fileOperationsService
    ) {
    }

    public function applyDiffToFile(
        string $pathToFile,
        string $diff
    ): string {
        $originalContent = $this->fileOperationsService->getFileContent($pathToFile);
        $applyDiff       = new ApplyDiff();

        return $applyDiff->applyDiff($originalContent, $diff);
    }
}
