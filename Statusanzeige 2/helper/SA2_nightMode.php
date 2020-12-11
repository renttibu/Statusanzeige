<?php

/** @noinspection PhpUnusedPrivateMethodInspection */
/** @noinspection DuplicatedCode */
/** @noinspection PhpUnused */

declare(strict_types=1);

trait SA2_nightMode
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
            //Upper light unit
            $resultUpperLightUnit = true;
            $upperLightUnitTriggerVariables = false;
            $variables = json_decode($this->ReadPropertyString('UpperLightUnitTriggerVariables'));
            if (!empty($variables)) {
                foreach ($variables as $variable) {
                    $id = $variable->ID;
                    if ($id != 0 && @IPS_ObjectExists($id)) {
                        $use = $variable->Use;
                        if ($use) {
                            $upperLightUnitTriggerVariables = true;
                        }
                    }
                }
                if ($upperLightUnitTriggerVariables) {
                    $resultUpperLightUnit = $this->UpdateLightUnit(0);
                }
            }
            if (!$upperLightUnitTriggerVariables) {
                $lastColor = $this->ReadAttributeInteger('UpperLightUnitLastColor');
                $resultUpperLightUnit = $this->SetColor(0, $lastColor);
            }
            // Lower light unit
            $resultLowerLightUnit = true;
            $lowerLightUnitTriggerVariables = false;
            $variables = json_decode($this->ReadPropertyString('LowerLightUnitTriggerVariables'));
            if (!empty($variables)) {
                foreach ($variables as $variable) {
                    $id = $variable->ID;
                    if ($id != 0 && @IPS_ObjectExists($id)) {
                        $use = $variable->Use;
                        if ($use) {
                            $lowerLightUnitTriggerVariables = true;
                        }
                    }
                }
                if ($lowerLightUnitTriggerVariables) {
                    $resultLowerLightUnit = $this->UpdateLightUnit(1);
                }
            }
            if (!$lowerLightUnitTriggerVariables) {
                $lastColor = $this->ReadAttributeInteger('LowerLightUnitLastColor');
                $resultLowerLightUnit = $this->SetColor(1, $lastColor);
            }
            // Brightness
            $resultBrightness = true;
            if (!$upperLightUnitTriggerVariables && !$lowerLightUnitTriggerVariables) {
                $lastBrightness = $this->ReadAttributeInteger('LastBrightness');
                $resultBrightness = $this->SetBrightness($lastBrightness);
            }
            if ($resultUpperLightUnit && $resultLowerLightUnit && $resultBrightness) {
                $result = true;
            }
        }
        //Night mode on
        if ($State) {
            //Upper light unit
            $resultUpperLightUnit = true;
            $actualColor = $this->GetValue('UpperLightUnitColor');
            $newColor = $this->ReadPropertyInteger('NightModeColorUpperLightUnit');
            if ($newColor != -1) {
                $this->SetValue('UpperLightUnitColor', $newColor);
                $setDeviceColor = $this->SetDeviceColor(0, $newColor);
                if (!$setDeviceColor) {
                    $resultUpperLightUnit = false;
                    //Revert
                    $this->SetValue('UpperLightUnitColor', $actualColor);
                }
            }
            //Lower light unit
            $resultLowerLightUnit = true;
            $actualColor = $this->GetValue('LowerLightUnitColor');
            $newColor = $this->ReadPropertyInteger('NightModeColorLowerLightUnit');
            if ($newColor != -1) {
                $this->SetValue('LowerLightUnitColor', $newColor);
                $setDeviceColor = $this->SetDeviceColor(1, $newColor);
                if (!$setDeviceColor) {
                    $resultLowerLightUnit = false;
                    //Revert
                    $this->SetValue('LowerLightUnitColor', $actualColor);
                }
            }
            // Brightness
            $resultBrightness = true;
            $newBrightness = $this->ReadPropertyInteger('NightModeBrightness');
            if ($newBrightness != -1) {
                $actualBrightness = $this->GetValue('Brightness');
                $this->SetValue('Brightness', $newBrightness);
                $setBrightness = $this->SetDeviceBrightness($newBrightness);
                if (!$setBrightness) {
                    $resultBrightness = false;
                    //Revert
                    $this->SetValue('Brightness', $actualBrightness);
                }
            }
            if ($resultUpperLightUnit && $resultLowerLightUnit && $resultBrightness) {
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
        if (!$this->ReadPropertyBoolean('UseAutomaticNightMode')) {
            return;
        }
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