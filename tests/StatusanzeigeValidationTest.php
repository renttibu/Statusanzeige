<?php

declare(strict_types=1);
include_once __DIR__ . '/stubs/Validator.php';
class StatusanzeigeValidationTest extends TestCaseSymconValidation
{
    public function testValidateStatusanzeige(): void
    {
        $this->validateLibrary(__DIR__ . '/..');
    }
    public function testValidateStatusanzeigeModule(): void
    {
        $this->validateModule(__DIR__ . '/../Statusanzeige 1');
        $this->validateModule(__DIR__ . '/../Statusanzeige 2');
    }
}