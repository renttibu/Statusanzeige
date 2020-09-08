<?php

/** @noinspection PhpUnused */
/** @noinspection DuplicatedCode */

declare(strict_types=1);

trait SA2_control
{
    #################### Public

    /**
     * Sets the color of a light unit.
     *
     * @param int $LightUnit
     * 0    = upper light unit
     * 1    = lower light unit
     *
     * @param int $Color
     * 0    = off
     * 1    = blue
     * 2    = green
     * 3    = turquoise
     * 4    = red
     * 5    = violet
     * 6    = yellow
     * 7    = white
     *
     * @return bool
     * false    = an error occurred
     * true     = successful
     *
     * @throws Exception
     */
    public function SetColor(int $LightUnit, int $Color): bool
    {
        $result = false;
        $this->SendDebug(__FUNCTION__, 'Die Methode wird ausgeführt. (' . microtime(true) . ')', 0);
        if ($this->CheckMaintenanceMode()) {
            return $result;
        }
        //Semaphore Enter
        if (!IPS_SemaphoreEnter($this->InstanceID . '.SetColor', 5000)) {
            return $result;
        }
        $name = 'UpperLightUnit';
        if ($LightUnit == 1) {
            $name = 'LowerLightUnit';
        }
        $actualColor = $this->GetValue($name);
        $this->SetValue($name, $Color);
        $id = $this->ReadPropertyInteger($name);
        if ($id != 0 && @IPS_ObjectExists($id)) {
            $colorDifference = $this->CheckColorDifference($id, $Color);
            if ($colorDifference) {
                IPS_Sleep($this->ReadPropertyInteger($name . 'SwitchingDelay'));
                $setColor = @HM_WriteValueInteger($id, 'COLOR', $Color);
                if (!$setColor) {
                    IPS_Sleep(self::DELAY_MILLISECONDS);
                    $setColorAgain = @HM_WriteValueInteger($id, 'COLOR', $Color);
                    if (!$setColorAgain) {
                        //Revert color
                        $this->SetValue($name, $actualColor);
                        $errorMessage = 'Farbwert ' . $Color . ' konnte nicht gesetzt werden!';
                        $this->SendDebug(__FUNCTION__, $errorMessage, 0);
                        $errorMessage = 'ID ' . $id . ' ,' . $errorMessage;
                        $this->LogMessage($errorMessage, KL_ERROR);
                    }
                }
                if ($setColor || $setColorAgain) {
                    $result = true;
                }
            }
        }
        //Semaphore leave
        IPS_SemaphoreLeave($this->InstanceID . '.SetColor');
        return $result;
    }

    /**
     * Sets the brightness of both light units.
     *
     * @param int $Brightness
     *
     * @return bool
     * false    = an error occurred
     * true     = successful
     *
     * @throws Exception
     */
    public function SetBrightness(int $Brightness): bool
    {
        $result = false;
        $this->SendDebug(__FUNCTION__, 'Die Methode wird ausgeführt. (' . microtime(true) . ')', 0);
        if ($this->CheckMaintenanceMode()) {
            return $result;
        }
        //Semaphore Enter
        if (!IPS_SemaphoreEnter($this->InstanceID . '.SetBrightness', 5000)) {
            return $result;
        }
        $actualBrightness = $this->GetValue('Brightness');
        $this->SetValue('Brightness', $Brightness);
        $Brightness = floatval($this->GetValue('Brightness') / 100);
        $this->SendDebug(__FUNCTION__, 'Helligkeitswert: ' . $Brightness, 0);
        //Upper light unit
        $unit1 = false;
        $upperLightUnit = $this->ReadPropertyInteger('UpperLightUnit');
        if ($upperLightUnit != 0 && @IPS_ObjectExists($upperLightUnit)) {
            $BrightnessDifference = $this->CheckBrightnessDifference($upperLightUnit, $Brightness);
            if ($BrightnessDifference) {
                IPS_Sleep($this->ReadPropertyInteger('UpperLightUnitSwitchingDelay'));
                $setBrightness = @HM_WriteValueFloat($upperLightUnit, 'LEVEL', $Brightness);
                if (!$setBrightness) {
                    IPS_Sleep(self::DELAY_MILLISECONDS);
                    $setBrightnessAgain = @HM_WriteValueFloat($upperLightUnit, 'LEVEL', $Brightness);
                    if (!$setBrightnessAgain) {
                        $this->SendDebug(__FUNCTION__, 'Helligkeit konnte nicht gesetzt werden!', 0);
                        $errorMessage = 'ID ' . $upperLightUnit . ' ,die Helligkeit konnte nicht auf den Wert ' . $Brightness . ' gesetzt werden!';
                        $this->LogMessage($errorMessage, KL_ERROR);
                    }
                }
                if ($setBrightness || $setBrightnessAgain) {
                    $unit1 = true;
                }
            }
        }
        //Lower light unit
        $unit2 = false;
        $lowerLightUnit = $this->ReadPropertyInteger('LowerLightUnit');
        if ($lowerLightUnit != 0 && @IPS_ObjectExists($lowerLightUnit)) {
            $BrightnessDifference = $this->CheckBrightnessDifference($lowerLightUnit, $Brightness);
            if ($BrightnessDifference) {
                IPS_Sleep($this->ReadPropertyInteger('LowerLightUnitSwitchingDelay'));
                $setBrightness = @HM_WriteValueFloat($lowerLightUnit, 'LEVEL', $Brightness);
                if (!$setBrightness) {
                    IPS_Sleep(self::DELAY_MILLISECONDS);
                    $setBrightnessAgain = @HM_WriteValueFloat($lowerLightUnit, 'LEVEL', $Brightness);
                    if (!$setBrightnessAgain) {
                        $errorMessage = 'ID ' . $lowerLightUnit . ' ,die Helligkeit konnte nicht auf den Wert ' . $Brightness . ' gesetzt werden!';
                        $this->SendDebug(__FUNCTION__, $errorMessage, 0);
                        $this->LogMessage($errorMessage, KL_ERROR);
                    }
                    if ($setBrightness || $setBrightnessAgain) {
                        $unit2 = true;
                    }
                }
            }
        }
        //Semaphore leave
        IPS_SemaphoreLeave($this->InstanceID . '.SetBrightness');
        if ($unit1 && $unit2) {
            $result = true;
        }
        if (!$unit1 && !$unit2) {
            //Revert brightness
            $this->SetValue('Brightness', $actualBrightness);
        }
        return $result;
    }

    /**
     * Updates the state for the upper light unit.
     *
     * @return bool
     * false    = an error occurred
     * true     = successful
     *
     * @throws Exception
     */
    public function UpdateUpperLightUnit(): bool
    {
        $this->SendDebug(__FUNCTION__, 'Die Methode wird ausgeführt. (' . microtime(true) . ')', 0);
        if ($this->CheckMaintenanceMode()) {
            return false;
        }
        if ($this->ReadAttributeBoolean('NightModeTimer')) {
            return false;
        }
        $use = $this->ReadPropertyBoolean('EnableNightMode');
        if ($use) {
            if ($this->GetValue('NightMode')) {
                return false;
            }
        }
        $states = [];
        $variables = json_decode($this->ReadPropertyString('UpperLightUnitStates'));
        if (!empty($variables)) {
            foreach ($variables as $variable) {
                if ($variable->Use) {
                    $id = $variable->ID;
                    if ($id != 0 && @IPS_ObjectExists($id)) {
                        $actualValue = intval(GetValue($id));
                        if ($actualValue == $variable->TriggerValueState0) {
                            array_push($states, 0);
                        }
                        if ($actualValue == $variable->TriggerValueState1) {
                            array_push($states, 1);
                        }
                        if ($actualValue == $variable->TriggerValueState2) {
                            array_push($states, 2);
                        }
                    }
                }
            }
        }
        if (!empty($states)) {
            $this->SendDebug(__FUNCTION__, 'Statusarray: ' . json_encode($states), 0);
            //State 0, low priority
            if (count(array_count_values($states)) == 1 && $states[0] == 0) {
                $this->SendDebug(__FUNCTION__, 'Aktueller Status: 0, niedrige Priorität', 0);
                $color = $this->ReadPropertyInteger('UpperLightUnitColorState0');
                if ($color == -1) { //Without function
                    return false;
                }
                return $this->SetColor(0, $color);
            }
            //State 1, medium priority
            if (in_array(1, $states)) {
                if (!in_array(2, $states)) {
                    $this->SendDebug(__FUNCTION__, 'Aktueller Status: 1, mittlere Priorität', 0);
                    $color = $this->ReadPropertyInteger('UpperLightUnitColorState1');
                    if ($color == -1) { //Without function
                        return false;
                    }
                    return $this->SetColor(0, $color);
                }
            }
            //State 2, high priority
            if (in_array(2, $states)) {
                $color = $this->ReadPropertyInteger('UpperLightUnitColorState2');
                $this->SendDebug(__FUNCTION__, 'Aktueller Status: 2, hohe Priorität', 0);
                if ($color == -1) { //Without function
                    return false;
                }
                return $this->SetColor(0, $color);
            }
        }
        return false;
    }

    /**
     * Updates the state for the lower light unit.
     *
     * @return bool
     * false    = an error occurred
     * true     = successful
     *
     * @throws Exception
     */
    public function UpdateLowerLightUnit(): bool
    {
        $this->SendDebug(__FUNCTION__, 'Die Methode wird ausgeführt. (' . microtime(true) . ')', 0);
        if ($this->CheckMaintenanceMode()) {
            return false;
        }
        if ($this->ReadAttributeBoolean('NightModeTimer')) {
            return false;
        }
        $use = $this->ReadPropertyBoolean('EnableNightMode');
        if ($use) {
            if ($this->GetValue('NightMode')) {
                return false;
            }
        }
        $states = [];
        $variables = json_decode($this->ReadPropertyString('LowerLightUnitStates'));
        if (!empty($variables)) {
            foreach ($variables as $variable) {
                if ($variable->Use) {
                    $id = $variable->ID;
                    $actualValue = intval(GetValue($id));
                    if ($actualValue == $variable->TriggerValueState0) {
                        array_push($states, 0);
                    }
                    if ($actualValue == $variable->TriggerValueState1) {
                        array_push($states, 1);
                    }
                    if ($actualValue == $variable->TriggerValueState2) {
                        array_push($states, 2);
                    }
                }
            }
        }
        if (!empty($states)) {
            $this->SendDebug(__FUNCTION__, 'Statusarray: ' . json_encode($states), 0);
            //State 0, low priority
            if (count(array_count_values($states)) == 1 && $states[0] == 0) {
                $this->SendDebug(__FUNCTION__, 'Aktueller Status: 0, niedrige Priorität', 0);
                $color = $this->ReadPropertyInteger('LowerLightUnitColorState0');
                if ($color == -1) { //Without function
                    return false;
                }
                return $this->SetColor(1, $color);
            }
            //State 1, medium priority
            if (in_array(1, $states)) {
                if (!in_array(2, $states)) {
                    $this->SendDebug(__FUNCTION__, 'Aktueller Status: 1, mittlere Priorität', 0);
                    $color = $this->ReadPropertyInteger('LowerLightUnitColorState1');
                    if ($color == -1) { //Without function
                        return false;
                    }
                    return $this->SetColor(1, $color);
                }
            }
            //State 2, high priority
            if (in_array(2, $states)) {
                $color = $this->ReadPropertyInteger('LowerLightUnitColorState2');
                $this->SendDebug(__FUNCTION__, 'Aktueller Status: 2, hohe Priorität', 0);
                if ($color == -1) { //Without function
                    return false;
                }
                return $this->SetColor(1, $color);
            }
        }
        return false;
    }

    #################### Private

    /**
     * Checks for a different color.
     *
     * @param int $Device
     *
     * @param int $Value
     *
     * @return bool
     * false    = same color
     * true     = different color
     */
    private function CheckColorDifference(int $Device, int $Value): bool
    {
        $this->SendDebug(__FUNCTION__, 'Angefragter Farbwert: ' . $Value, 0);
        $difference = true;
        $channelParameters = IPS_GetChildrenIDs($Device);
        if (!empty($channelParameters)) {
            foreach ($channelParameters as $channelParameter) {
                $ident = IPS_GetObject($channelParameter)['ObjectIdent'];
                if ($ident == 'COLOR') {
                    $actualValue = GetValueInteger($channelParameter);
                    $this->SendDebug(__FUNCTION__, 'Aktueller Farbwert: ' . $actualValue, 0);
                    if ($actualValue == $Value) {
                        $difference = false;
                    }
                }
            }
        }
        $this->SendDebug(__FUNCTION__, 'Unterschiedliche Farbwerte: ' . json_encode($difference), 0);
        return $difference;
    }

    /**
     * Checks for a different brightness.
     *
     * @param int $Device
     *
     * @param float $Value
     *
     * @return bool
     * false    = same brightness
     * true     = different brightness
     */
    private function CheckBrightnessDifference(int $Device, float $Value): bool
    {
        $this->SendDebug(__FUNCTION__, 'Angefragter Helligkeitswert: ' . $Value, 0);
        $difference = true;
        $channelParameters = IPS_GetChildrenIDs($Device);
        if (!empty($channelParameters)) {
            foreach ($channelParameters as $channelParameter) {
                $ident = IPS_GetObject($channelParameter)['ObjectIdent'];
                if ($ident == 'LEVEL') {
                    $actualValue = GetValueFloat($channelParameter);
                    $this->SendDebug(__FUNCTION__, 'Aktueller Helligkeitswert: ' . $actualValue, 0);
                    if ($actualValue == $Value) {
                        $difference = false;
                    }
                }
            }
        }
        $this->SendDebug(__FUNCTION__, 'Unterschiedliche Helligkeitswerte: ' . json_encode($difference), 0);
        return $difference;
    }
}