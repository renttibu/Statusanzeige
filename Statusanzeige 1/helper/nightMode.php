<?php

/** @noinspection PhpUnused */
/** @noinspection PhpUnusedPrivateMethodInspection */
/** @noinspection DuplicatedCode */

declare(strict_types=1);

trait SA1_nightMode
{
    /**
     * Toggles the night mode off or on.
     *
     * @param bool $State
     * false    = off
     * true     = on
     *
     * @return bool
     * false    = an error occurred
     * true     = successful
     */
    public function ToggleNightMode(bool $State): bool
    {
        $this->SendDebug(__FUNCTION__, 'Die Methode wird ausgeführt. (' . microtime(true) . ')', 0);
        if ($this->CheckMaintenanceMode()) {
            return false;
        }
        $actualNightMode = $this->GetValue('NightMode');
        $this->SetValue('NightMode', $State);
        $result = true;
        //Night mode off
        if (!$State) {
            $result = $this->UpdateState();
            if (!$result) {
                //Revert
                $this->SetValue('NightMode', $actualNightMode);
            }
        }
        //Night mode on
        if ($State) {
            $actualSignalling = $this->GetValue('Signalling');
            $this->SetValue('Signalling', false);
            $signalling = $this->TriggerSignalling(false);
            $invertedSignalling = $this->TriggerInvertedSignalling(false);
            if (!$signalling || !$invertedSignalling) {
                $result = false;
                //Revert value
                $this->SetValue('Signalling', $actualSignalling);
                $this->SetValue('NightMode', $actualNightMode);
            }
        }
        return $result;
    }

    /**
     * Starts the night mode, used by timer.
     */
    public function StartNightMode(): void
    {
        $this->ToggleNightMode(true);
        $this->SetNightModeTimer();
    }

    /**
     * Stops the night mode, used by timer.
     */
    public function StopNightMode(): void
    {
        $this->ToggleNightMode(false);
        $this->SetNightModeTimer();
    }

    #################### Private

    /**
     * Sets the timer interval for the automatic night mode.
     */
    private function SetNightModeTimer(): void
    {
        $use = $this->ReadPropertyBoolean('UseAutomaticNightMode');
        //Start
        $milliseconds = 0;
        if ($use) {
            $milliseconds = $this->GetInterval('NightModeStartTime');
        }
        $this->SetTimerInterval('StartNightMode', $milliseconds);
        //End
        $milliseconds = 0;
        if ($use) {
            $milliseconds = $this->GetInterval('NightModeEndTime');
        }
        $this->SetTimerInterval('StopNightMode', $milliseconds);
    }

    /**
     * Gets the interval for a timer.
     *
     * @param string $TimerName
     *
     * @return int
     */
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

    /**
     * Checks the state of the automatic night mode.
     */
    private function CheckNightModeTimer(): void
    {
        $start = $this->GetTimerInterval('StartNightMode');
        $stop = $this->GetTimerInterval('StopNightMode');
        if ($start > $stop) {
            $this->ToggleNightMode(true);
        } else {
            $this->ToggleNightMode(false);
        }
    }

    /**
     * Checks if the night mode is off or on.
     *
     * @return bool
     * false    = off
     * true     = on
     */
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