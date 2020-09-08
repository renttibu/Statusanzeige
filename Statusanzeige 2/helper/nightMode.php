<?php

/** @noinspection PhpUnused */
/** @noinspection PhpUndefinedMethodInspection */
/** @noinspection DuplicatedCode */

declare(strict_types=1);

trait SA2_nightMode
{
    #################### Public

    /**
     * Toggles the night mode off or on.
     *
     * @param bool $State
     * false    = night mode off
     * true     = night mode on
     *
     * @return bool
     * false    = an error occurred
     * true     = successful
     */
    public function ToggleNightMode(bool $State): bool
    {
        $result = false;
        $this->SendDebug(__FUNCTION__, 'Die Methode wird ausgeführt. (' . microtime(true) . ')', 0);
        if ($this->CheckMaintenanceMode()) {
            return $result;
        }
        $use = $this->ReadPropertyBoolean('EnableNightMode');
        if ($use) {
            $this->SetValue('NightMode', $State);
        }
        //Night mode off
        if (!$State) {
            $this->WriteAttributeBoolean('NightModeTimer', false);
            $this->UpdateUpperLightUnit();
            $this->UpdateLowerLightUnit();
        }
        //Night mode on
        if ($State) {
            $upperLightUnitActualColor = $this->GetValue('UpperLightUnit');
            $this->SetValue('UpperLightUnit', 0);
            $upperLightUnitNewColor = $this->SetColor(0, 0);
            if (!$upperLightUnitNewColor) {
                //Revert
                $this->SetValue('UpperLightUnit', $upperLightUnitActualColor);
            }
            //Lower light unit
            $lowerLightUnitActualColor = $this->GetValue('LowerLightUnit');
            $this->SetValue('LowerLightUnit', 0);
            $lowerLightUnitNewColor = $this->SetColor(1, 0);
            if (!$lowerLightUnitNewColor) {
                //Revert
                $this->SetValue('LowerLightUnit', $lowerLightUnitActualColor);
            }
            if ($upperLightUnitNewColor && $lowerLightUnitNewColor) {
                $result = true;
            }
        }
        return $result;
    }

    /**
     * Starts the night mode, used by timer.
     */
    public function StartNightMode(): void
    {
        $this->WriteAttributeBoolean('NightModeTimer', true);
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
        $use = $this->ReadPropertyBoolean('UseNightMode');
        //Start
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
            $this->WriteAttributeBoolean('NightModeTimer', true);
            $this->ToggleNightMode(true);
        } else {
            $this->WriteAttributeBoolean('NightModeTimer', false);
            $this->ToggleNightMode(false);
        }
    }
}