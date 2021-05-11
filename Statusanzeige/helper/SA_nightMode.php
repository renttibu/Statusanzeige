<?php

/*
 * @author      Ulrich Bittner
 * @copyright   (c) 2020, 2021
 * @license     CC BY-NC-SA 4.0
 * @see         https://github.com/ubittner/Statusanzeige/tree/master/Statusanzeige
 */

/** @noinspection PhpUnusedPrivateMethodInspection */
/** @noinspection DuplicatedCode */
/** @noinspection PhpUnused */

declare(strict_types=1);

trait SA_nightMode
{
    public function ToggleNightMode(bool $State): bool
    {
        if ($this->CheckMaintenanceMode()) {
            return false;
        }
        $actualNightMode = $this->GetValue('NightMode');
        $this->SetValue('NightMode', $State);
        $result = true;
        // Off
        if (!$State) {
            $result = $this->UpdateState();
            if (!$result) {
                //Revert
                $this->SetValue('NightMode', $actualNightMode);
            }
        }
        // On
        else {
            $actualSignalling = $this->GetValue('Signalling');
            $this->SetValue('Signalling', false);
            $signalling = $this->ExecuteSignalling(false);
            $invertedSignalling = $this->ExecuteInvertedSignalling(false);
            if (!$signalling || !$invertedSignalling) {
                $result = false;
                // Revert value
                $this->SetValue('Signalling', $actualSignalling);
                $this->SetValue('NightMode', $actualNightMode);
            }
        }
        return $result;
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
            $this->ToggleSignalling(false);
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