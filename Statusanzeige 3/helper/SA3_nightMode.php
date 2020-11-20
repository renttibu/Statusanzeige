<?php

/** @noinspection PhpUnusedPrivateMethodInspection */
/** @noinspection DuplicatedCode */
/** @noinspection PhpUnused */

declare(strict_types=1);

trait SA3_nightMode
{
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
        $this->SetValue('NightMode', $State);
        //Night mode off
        if (!$State) {
            $triggerVariables = false;
            $variables = json_decode($this->ReadPropertyString('TriggerVariables'));
            if (!empty($variables)) {
                foreach ($variables as $variable) {
                    $id = $variable->ID;
                    if ($id != 0 && @IPS_ObjectExists($id)) {
                        $use = $variable->Use;
                        if ($use) {
                            $triggerVariables = true;
                        }
                    }
                }
                if ($triggerVariables) {
                    $result = $this->UpdateLightUnit();
                }
            }
            if (!$triggerVariables) {
                $lastColor = $this->ReadAttributeInteger('LastColor');
                $resultColor = $this->SetColor($lastColor);
                $lastBrightness = $this->ReadAttributeInteger('LastBrightness');
                $resultBrightness = $this->SetBrightness($lastBrightness);
                if ($resultColor && $resultBrightness) {
                    $result = true;
                }
            }
        }
        //Night mode on
        if ($State) {
            // Color
            $actualColor = $this->GetValue('Color');
            $color = $this->ReadPropertyInteger('NightModeColor');
            $lightUnitNewColor = true;
            if ($color != -1) {
                $this->SetValue('Color', $color);
                $lightUnitNewColor = $this->SetDeviceColor($color);
                if (!$lightUnitNewColor) {
                    //Revert
                    $this->SetValue('Color', $actualColor);
                }
            }
            // Brightness
            $actualBrightness = $this->GetValue('Brightness');
            $brightness = $this->ReadPropertyInteger('NightModeBrightness');
            $this->SetValue('Brightness', $brightness);
            $lightUnitNewBrightness = $this->SetDeviceBrightness($brightness);
            if (!$lightUnitNewBrightness) {
                //Revert
                $this->SetValue('Brightness', $actualBrightness);
            }
            if ($lightUnitNewColor && $lightUnitNewBrightness) {
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