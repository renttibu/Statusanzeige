<?php

declare(strict_types=1);

include_once __DIR__ . '/stubs/Validator.php';

class StatusanzeigeValidationTest extends TestCaseSymconValidation
{
    public function testValidateLibrary_Statusanzeige(): void
    {
        $this->validateLibrary(__DIR__ . '/..');
    }

    public function testValidateModule_HmIPBSL(): void
    {
        $this->validateModule(__DIR__ . '/../HmIP-BSL');
    }

    public function testValidateModule_HmIPMP3P(): void
    {
        $this->validateModule(__DIR__ . '/../HmIP-MP3P');
    }

    public function testValidateModule_Statusanzeige(): void
    {
        $this->validateModule(__DIR__ . '/../Statusanzeige');
    }
}