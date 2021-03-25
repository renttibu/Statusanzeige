<?php

/** @noinspection PhpUnusedPrivateMethodInspection */
/** @noinspection DuplicatedCode */
/** @noinspection PhpUnused */

declare(strict_types=1);

trait SA2_nightMode
{
    public function ToggleNightMode(bool $State): void
    {
        $this->SendDebug(__FUNCTION__, 'Die Methode wird ausgeführt', 0);
        if ($this->CheckMaintenanceMode()) {
            return;
        }
        $this->SetValue('NightMode', $State);

        // Off
        if (!$State) {
            if ($this->CheckExistingTrigger(0)) {
                $this->UpdateUpperLightUnit();
            } else {
                // Upper light unit, last color and brightness
                $this->SetColor(0, $this->ReadAttributeInteger('UpperLightUnitLastColor'));
                $this->SetBrightness(0, $this->ReadAttributeInteger('UpperLightUnitLastBrightness'), true);
            }
            if ($this->CheckExistingTrigger(1)) {
                $this->UpdateLowerLightUnit();
            } else {
                // Lower light unit, last color and brightness
                $this->SetColor(1, $this->ReadAttributeInteger('LowerLightUnitLastColor'), true);
                $this->SetBrightness(1, $this->ReadAttributeInteger('LowerLightUnitLastBrightness'), true);
            }
        }

        // On
        else {
            if (!$this->CheckExistingTrigger(0)) {
                $this->SendDebug(__FUNCTION__, 'Obere Leuchteinheit Attribute letzte Farbe und Helligkeit gesetzt!', 0);
                $this->WriteAttributeInteger('UpperLightUnitLastColor', $this->GetValue('UpperLightUnitColor'));
                $this->WriteAttributeInteger('UpperLightUnitLastBrightness', $this->GetValue('UpperLightUnitBrightness'));
            }
            if (!$this->CheckExistingTrigger(1)) {
                $this->SendDebug(__FUNCTION__, 'Untere Leuchteinheit Attribute letzte Farbe und Helligkeit gesetzt!', 0);
                $this->WriteAttributeInteger('LowerLightUnitLastColor', $this->GetValue('LowerLightUnitColor'));
                $this->WriteAttributeInteger('LowerLightUnitLastBrightness', $this->GetValue('LowerLightUnitBrightness'));
            }
            $this->SetDeviceColor(0, $this->ReadPropertyInteger('NightModeColorUpperLightUnit'));
            $this->SetDeviceBrightness(0, $this->ReadPropertyInteger('NightModeBrightnessUpperLightUnit'), true);
            $this->SetDeviceColor(1, $this->ReadPropertyInteger('NightModeColorLowerLightUnit'), true);
            $this->SetDeviceBrightness(1, $this->ReadPropertyInteger('NightModeBrightnessLowerLightUnit'), true);
        }
    }

    public function StartNightMode(): void
    {
        $this->ToggleNightMode(true);
        $this->SetNightModeTimer();
    }

    public function StopNightMode(): void
    {
        $this->ToggleNightMode(false);
        $this->SetNightModeTimer();
    }

    #################### Private

    private function SetNightModeTimer(): void
    {
        $use = $this->ReadPropertyBoolean('UseAutomaticNightMode');
        // Start
        $milliseconds = 0;
        if ($use) {
            $milliseconds = $this->GetInterval('NightModeStartTime');
        }
        $this->SetTimerInterval('StartNightMode', $milliseconds);
        // End
        $milliseconds = 0;
        if ($use) {
            $milliseconds = $this->GetInterval('NightModeEndTime');
        }
        $this->SetTimerInterval('StopNightMode', $milliseconds);
    }

    private function GetInterval(string $TimerName): int
    {
        $timer = json_decode($this->ReadPropertyString($TimerName));
        $now = time();
        $hour = $timer->hour;
        $minute = $timer->minute;
        $second = $timer->second;
        $definedTime = $hour . ':' . $minute . ':' . $second;
        if (time() >= strtotime($definedTime)) {
            $timestamp = mktime($hour, $minute, $second, (int) date('n'), (int) date('j') + 1, (int) date('Y'));
        } else {
            $timestamp = mktime($hour, $minute, $second, (int) date('n'), (int) date('j'), (int) date('Y'));
        }
        return ($timestamp - $now) * 1000;
    }

    private function CheckNightModeTimer(): bool
    {
        if (!$this->ReadPropertyBoolean('UseAutomaticNightMode')) {
            return false;
        }
        $start = $this->GetTimerInterval('StartNightMode');
        $stop = $this->GetTimerInterval('StopNightMode');
        if ($start > $stop) {
            $this->ToggleNightMode(true);
            return true;
        } else {
            $this->ToggleNightMode(false);
            return false;
        }
    }

    private function CheckNightMode(): bool
    {
        $nightMode = boolval($this->GetValue('NightMode'));
        if ($nightMode) {
            $message = 'Abbruch, der Nachtmodus ist aktiv!';
            $this->SendDebug(__FUNCTION__, $message, 0);
        }
        return $nightMode;
    }
}