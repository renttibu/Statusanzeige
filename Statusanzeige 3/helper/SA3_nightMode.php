<?php

/*
 * @author      Ulrich Bittner
 * @copyright   (c) 2020, 2021
 * @license    	CC BY-NC-SA 4.0
 * @see         https://github.com/ubittner/Statusanzeige/tree/master/Statusanzeige%203
 */

/** @noinspection PhpUnusedPrivateMethodInspection */
/** @noinspection DuplicatedCode */
/** @noinspection PhpUnused */

declare(strict_types=1);

trait SA3_nightMode
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
            if ($this->CheckExistingTrigger()) {
                $this->UpdateLightUnit();
            } else {
                // Light unit, last color and brightness
                $this->SetColor($this->ReadAttributeInteger('LightUnitLastColor'));
                $this->SetBrightness($this->ReadAttributeInteger('LightUnitLastBrightness'), true);
            }
        }

        // On
        else {
            if (!$this->CheckExistingTrigger()) {
                $this->SendDebug(__FUNCTION__, 'Leuchteinheit Attribute letzte Farbe und Helligkeit gesetzt!', 0);
                $this->WriteAttributeInteger('LightUnitLastColor', $this->GetValue('LightUnitColor'));
                $this->WriteAttributeInteger('LightUnitLastBrightness', $this->GetValue('LightUnitBrightness'));
            }
            if ($this->ReadPropertyBoolean('ChangeNightModeColor')) {
                $this->SetDeviceColor($this->ReadPropertyInteger('NightModeColor'));
            }
            if ($this->ReadPropertyBoolean('ChangeNightModeBrightness')) {
                $this->SetDeviceBrightness($this->ReadPropertyInteger('NightModeBrightness'), true);
            }
        }
    }

    public function StartNightMode(): void
    {
        $this->SendDebug(__FUNCTION__, 'Die Methode wird ausgeführt', 0);
        $this->ToggleNightMode(true);
        $this->SetNightModeTimer();
    }

    public function StopNightMode(): void
    {
        $this->SendDebug(__FUNCTION__, 'Die Methode wird ausgeführt', 0);
        $this->ToggleNightMode(false);
        $this->SetNightModeTimer();
    }

    #################### Private

    private function SetNightModeTimer(): void
    {
        $this->SendDebug(__FUNCTION__, 'Die Methode wird ausgeführt', 0);
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
        $this->SendDebug(__FUNCTION__, 'Die Methode wird ausgeführt', 0);
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
        $this->SendDebug(__FUNCTION__, 'Die Methode wird ausgeführt', 0);
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
        $this->SendDebug(__FUNCTION__, 'Die Methode wird ausgeführt', 0);
        $nightMode = boolval($this->GetValue('NightMode'));
        if ($nightMode) {
            $message = 'Abbruch, der Nachtmodus ist aktiv!';
            $this->SendDebug(__FUNCTION__, $message, 0);
        }
        return $nightMode;
    }
}