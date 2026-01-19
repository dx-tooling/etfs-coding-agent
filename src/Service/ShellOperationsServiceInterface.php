<?php

declare(strict_types=1);

namespace EtfsCodingAgent\Service;

interface ShellOperationsServiceInterface
{
    public function runCommand(string $workingDirectory, string $command): string;
}
