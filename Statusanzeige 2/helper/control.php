<?php

/** @noinspection DuplicatedCode */
/** @noinspection PhpUnused */

declare(strict_types=1);

trait SA2_control
{
    /**
     * Sets the color.
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
     * @param bool $UseSwitchingDelay
     * false    = no delay
     * true     = use delay
     *
     * @return bool
     * false    = an error occurred
     * true     = successful
     *
     * @throws Exception
     */
    public function SetColor(int $LightUnit, int $Color, bool $UseSwitchingDelay = false): bool
    {
        $this->SendDebug(__FUNCTION__, 'Die Methode wird ausgeführt. (' . microtime(true) . ')', 0);
        if ($this->CheckMaintenanceMode()) {
            return false;
        }
        if ($this->CheckNightMode()) {
            return false;
        }
        $setColor = $this->SetDeviceColor($LightUnit, $Color, $UseSwitchingDelay);
        if ($setColor) {
            $attribute = 'UpperLightUnitLastColor';
            if ($LightUnit == 1) {
                $attribute = 'LowerLightUnitLastColor';
            }
            $this->WriteAttributeInteger($attribute, $Color);
            $this->UpdateParameter();
        }
        return $setColor;
    }

    /**
     * Sets the brightness.
     *
     * @param int $Brightness
     *
     * @param bool $UseSwitchingDelay
     * false    = no delay
     * true     = use delay
     *
     * @return bool
     * false    = an error occurred
     * true     = successful
     *
     * @throws Exception
     */
    public function SetBrightness(int $Brightness, bool $UseSwitchingDelay = false): bool
    {
        $this->SendDebug(__FUNCTION__, 'Die Methode wird ausgeführt. (' . microtime(true) . ')', 0);
        if ($this->CheckMaintenanceMode()) {
            return false;
        }
        if ($this->CheckNightMode()) {
            return false;
        }
        $setBrightness = $this->SetDeviceBrightness($Brightness, $UseSwitchingDelay);
        if ($setBrightness) {
            $this->WriteAttributeInteger('LastBrightness', $Brightness);
            $this->UpdateParameter();
        }
        return $setBrightness;
    }

    /**
     * Updates both light units.
     *
     * @return bool
     * false    = an error occurred
     * true     = successful
     *
     * @throws Exception
     */
    public function UpdateLightUnits(): bool
    {
        $result = false;
        $this->SendDebug(__FUNCTION__, 'Die Methode wird ausgeführt. (' . microtime(true) . ')', 0);
        if ($this->CheckMaintenanceMode()) {
            return $result;
        }
        if ($this->CheckNightMode()) {
            return $result;
        }
        $upperLightUnit = $this->UpdateLightUnit(0);
        $lowerLightUnit = $this->UpdateLightUnit(1);
        if ($upperLightUnit && $lowerLightUnit) {
            $result = true;
        }
        return $result;
    }

    /**
     * Updates the color of the light unit.
     *
     * @param int $LightUnit
     * 0    = upper light unit
     * 1    = lower light unit
     *
     * @return bool
     * false    = an error occurred
     * true     = successful
     *
     * @throws Exception
     */
    public function UpdateLightUnit(int $LightUnit): bool
    {
        $this->SendDebug(__FUNCTION__, 'Die Methode wird ausgeführt. (' . microtime(true) . ')', 0);
        if ($this->CheckMaintenanceMode()) {
            return false;
        }
        if ($this->CheckNightMode()) {
            return false;
        }
        $unit = 'UpperLightUnit';
        if ($LightUnit == 1) {
            $unit = 'LowerLightUnit';
        }
        $groups = [];
        $variables = json_decode($this->ReadPropertyString($unit . 'TriggerVariables'));
        if (!empty($variables)) {
            foreach ($variables as $variable) {
                if ($variable->Use) {
                    $id = $variable->ID;
                    if ($id != 0 && @IPS_ObjectExists($id)) {
                        $actualValue = intval(GetValue($id));
                        if ($actualValue == $variable->TriggerValue) {
                            $group = $variable->Group;
                            $color = $variable->Color;
                            $brightness = $variable->Brightness;
                            array_push($groups, ['group' => $group, 'color' => $color, 'brightness' => $brightness]);
                        }
                    }
                }
            }
        }
        if (!empty($groups)) {
            $this->SendDebug(__FUNCTION__, 'Array: ' . json_encode($groups), 0);
            $colorList = [0 => 'Aus', 1 => 'Blau', 2 => 'Grün', 3 => 'Türkis', 4 => 'Rot', 5 => 'Violett', 6 => 'Gelb', 7 => 'Weiß'];
            $colorName = 'Wert nicht vorhanden!';
            $lastBrightness = $this->ReadAttributeInteger('LastBrightness');
            //Group 0
            $key = array_search(0, array_column($groups, 'group'));
            if (is_int($key)) {
                $validate = true;
                $values = [1, 2, 3, 4, 5, 6, 7];
                foreach ($values as $value) {
                    if (in_array($value, array_column($groups, 'group'))) {
                        $validate = false;
                    }
                }
                if ($validate) {
                    $color = $groups[$key]['color'];
                    if (array_key_exists($color, $colorList)) {
                        $colorName = $colorList[$color];
                    }
                    $brightness = $groups[$key]['brightness'];
                    $this->SendDebug(__FUNCTION__, 'Gruppe: 0, Farbe: ' . $color . ' - ' . $colorName . ', Helligkeit: ' . $brightness . '%', 0);
                    $setColor = $this->SetColor($LightUnit, $color, true);
                    if ($brightness != -1) {
                        $setBrightness = $this->SetBrightness($brightness, true);
                    } else {
                        $setBrightness = $this->SetBrightness($lastBrightness, true);
                    }
                    if ($setColor || $setBrightness) {
                        return true;
                    } else {
                        return false;
                    }
                }
            }
            //Group 1
            $key = array_search(1, array_column($groups, 'group'));
            if (is_int($key)) {
                $validate = true;
                $values = [2, 3, 4, 5, 6, 7];
                foreach ($values as $value) {
                    if (in_array($value, array_column($groups, 'group'))) {
                        $validate = false;
                    }
                }
                if ($validate) {
                    $color = $groups[$key]['color'];
                    if (array_key_exists($color, $colorList)) {
                        $colorName = $colorList[$color];
                    }
                    $brightness = $groups[$key]['brightness'];
                    $this->SendDebug(__FUNCTION__, 'Gruppe: 1, Farbe: ' . $color . ' - ' . $colorName . ', Helligkeit: ' . $brightness . '%', 0);
                    $setColor = $this->SetColor($LightUnit, $color, true);
                    if ($brightness != -1) {
                        $setBrightness = $this->SetBrightness($brightness, true);
                    } else {
                        $setBrightness = $this->SetBrightness($lastBrightness, true);
                    }
                    if ($setColor || $setBrightness) {
                        return true;
                    } else {
                        return false;
                    }
                }
            }
            //Group 2
            $key = array_search(2, array_column($groups, 'group'));
            if (is_int($key)) {
                $validate = true;
                $values = [3, 4, 5, 6, 7];
                foreach ($values as $value) {
                    if (in_array($value, array_column($groups, 'group'))) {
                        $validate = false;
                    }
                }
                if ($validate) {
                    $color = $groups[$key]['color'];
                    if (array_key_exists($color, $colorList)) {
                        $colorName = $colorList[$color];
                    }
                    $brightness = $groups[$key]['brightness'];
                    $this->SendDebug(__FUNCTION__, 'Gruppe: 2, Farbe: ' . $color . ' - ' . $colorName . ', Helligkeit: ' . $brightness . '%', 0);
                    $setColor = $this->SetColor($LightUnit, $color, true);
                    if ($brightness != -1) {
                        $setBrightness = $this->SetBrightness($brightness, true);
                    } else {
                        $setBrightness = $this->SetBrightness($lastBrightness, true);
                    }
                    if ($setColor || $setBrightness) {
                        return true;
                    } else {
                        return false;
                    }
                }
            }
            //Group 3
            $key = array_search(3, array_column($groups, 'group'));
            if (is_int($key)) {
                $validate = true;
                $values = [4, 5, 6, 7];
                foreach ($values as $value) {
                    if (in_array($value, array_column($groups, 'group'))) {
                        $validate = false;
                    }
                }
                if ($validate) {
                    $color = $groups[$key]['color'];
                    if (array_key_exists($color, $colorList)) {
                        $colorName = $colorList[$color];
                    }
                    $brightness = $groups[$key]['brightness'];
                    $this->SendDebug(__FUNCTION__, 'Gruppe: 3, Farbe: ' . $color . ' - ' . $colorName . ', Helligkeit: ' . $brightness . '%', 0);
                    $setColor = $this->SetColor($LightUnit, $color, true);
                    if ($brightness != -1) {
                        $setBrightness = $this->SetBrightness($brightness, true);
                    } else {
                        $setBrightness = $this->SetBrightness($lastBrightness, true);
                    }
                    if ($setColor || $setBrightness) {
                        return true;
                    } else {
                        return false;
                    }
                }
            }
            //Group 4
            $key = array_search(4, array_column($groups, 'group'));
            if (is_int($key)) {
                $validate = true;
                $values = [5, 6, 7];
                foreach ($values as $value) {
                    if (in_array($value, array_column($groups, 'group'))) {
                        $validate = false;
                    }
                }
                if ($validate) {
                    $color = $groups[$key]['color'];
                    if (array_key_exists($color, $colorList)) {
                        $colorName = $colorList[$color];
                    }
                    $brightness = $groups[$key]['brightness'];
                    $this->SendDebug(__FUNCTION__, 'Gruppe: 4, Farbe: ' . $color . ' - ' . $colorName . ', Helligkeit: ' . $brightness . '%', 0);
                    $setColor = $this->SetColor($LightUnit, $color, true);
                    if ($brightness != -1) {
                        $setBrightness = $this->SetBrightness($brightness, true);
                    } else {
                        $setBrightness = $this->SetBrightness($lastBrightness, true);
                    }
                    if ($setColor || $setBrightness) {
                        return true;
                    } else {
                        return false;
                    }
                }
            }
            //Group 5
            $key = array_search(5, array_column($groups, 'group'));
            if (is_int($key)) {
                $validate = true;
                $values = [6, 7];
                foreach ($values as $value) {
                    if (in_array($value, array_column($groups, 'group'))) {
                        $validate = false;
                    }
                }
                if ($validate) {
                    $color = $groups[$key]['color'];
                    if (array_key_exists($color, $colorList)) {
                        $colorName = $colorList[$color];
                    }
                    $brightness = $groups[$key]['brightness'];
                    $this->SendDebug(__FUNCTION__, 'Gruppe: 5, Farbe: ' . $color . ' - ' . $colorName . ', Helligkeit: ' . $brightness . '%', 0);
                    $setColor = $this->SetColor($LightUnit, $color, true);
                    if ($brightness != -1) {
                        $setBrightness = $this->SetBrightness($brightness, true);
                    } else {
                        $setBrightness = $this->SetBrightness($lastBrightness, true);
                    }
                    if ($setColor || $setBrightness) {
                        return true;
                    } else {
                        return false;
                    }
                }
            }
            //Group 6
            $key = array_search(6, array_column($groups, 'group'));
            if (is_int($key)) {
                $validate = true;
                $values = [7];
                foreach ($values as $value) {
                    if (in_array($value, array_column($groups, 'group'))) {
                        $validate = false;
                    }
                }
                if ($validate) {
                    $color = $groups[$key]['color'];
                    if (array_key_exists($color, $colorList)) {
                        $colorName = $colorList[$color];
                    }
                    $brightness = $groups[$key]['brightness'];
                    $this->SendDebug(__FUNCTION__, 'Gruppe: 6, Farbe: ' . $color . ' - ' . $colorName . ', Helligkeit: ' . $brightness . '%', 0);
                    $setColor = $this->SetColor($LightUnit, $color, true);
                    if ($brightness != -1) {
                        $setBrightness = $this->SetBrightness($brightness, true);
                    } else {
                        $setBrightness = $this->SetBrightness($lastBrightness, true);
                    }
                    if ($setColor || $setBrightness) {
                        return true;
                    } else {
                        return false;
                    }
                }
            }
            //Group 7
            $key = array_search(6, array_column($groups, 'group'));
            if (is_int($key)) {
                $color = $groups[$key]['color'];
                if (array_key_exists($color, $colorList)) {
                    $colorName = $colorList[$color];
                }
                $brightness = $groups[$key]['brightness'];
                $this->SendDebug(__FUNCTION__, 'Gruppe: 7, Farbe: ' . $color . ' - ' . $colorName . ', Helligkeit: ' . $brightness . '%', 0);
                $setColor = $this->SetColor($LightUnit, $color, true);
                if ($brightness != -1) {
                    $setBrightness = $this->SetBrightness($brightness, true);
                } else {
                    $setBrightness = $this->SetBrightness($lastBrightness, true);
                }
                if ($setColor || $setBrightness) {
                    return true;
                } else {
                    return false;
                }
            }
        }
        return false;
    }

    #################### Private

    /**
     * Sets the color of the device.
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
     * @param bool $UseSwitchingDelay
     * false    = no delay
     * true     = use delay
     *
     * @return bool
     * false    = an error occurred
     * true     = successful
     *
     * @throws Exception
     */
    private function SetDeviceColor(int $LightUnit, int $Color, bool $UseSwitchingDelay = false): bool
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
        $unit = 'UpperLightUnit';
        if ($LightUnit == 1) {
            $unit = 'LowerLightUnit';
        }
        $actualColor = $this->GetValue($unit . 'Color');
        $this->SetValue($unit . 'Color', $Color);
        $id = $this->ReadPropertyInteger($unit);
        if ($id != 0 && @IPS_ObjectExists($id)) {
            $colorDifference = $this->CheckColorDifference($id, $Color);
            if ($colorDifference) {
                if ($UseSwitchingDelay) {
                    IPS_Sleep($this->ReadPropertyInteger($unit . 'SwitchingDelay'));
                }
                $setColor = @HM_WriteValueInteger($id, 'COLOR', $Color);
                if (!$setColor) {
                    IPS_Sleep(self::DELAY_MILLISECONDS);
                    $setColorAgain = @HM_WriteValueInteger($id, 'COLOR', $Color);
                    if (!$setColorAgain) {
                        //Revert color
                        $this->SetValue($unit . 'Color', $actualColor);
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
     * Sets the brightness of the device.
     *
     * @param int $Brightness
     *
     * @param bool $UseSwitchingDelay
     * false    = no delay
     * true     = use delay
     *
     * @return bool
     * false    = an error occurred
     * true     = successful
     *
     * @throws Exception
     */
    private function SetDeviceBrightness(int $Brightness, bool $UseSwitchingDelay = false): bool
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
        $upperLightUnit = true;
        $id = $this->ReadPropertyInteger('UpperLightUnit');
        if ($id != 0 && @IPS_ObjectExists($id)) {
            $BrightnessDifference = $this->CheckBrightnessDifference($id, $Brightness);
            if ($BrightnessDifference) {
                if ($UseSwitchingDelay) {
                    IPS_Sleep($this->ReadPropertyInteger('UpperLightUnitSwitchingDelay'));
                }
                $setBrightness = @HM_WriteValueFloat($id, 'LEVEL', $Brightness);
                if (!$setBrightness) {
                    IPS_Sleep(self::DELAY_MILLISECONDS);
                    $setBrightnessAgain = @HM_WriteValueFloat($id, 'LEVEL', $Brightness);
                    if (!$setBrightnessAgain) {
                        $upperLightUnit = false;
                        //Revert brightness
                        $this->SetValue('Brightness', $actualBrightness);
                        $errorMessage = 'Helligkeit ' . $Brightness . ' konnte nicht gesetzt werden!';
                        $this->SendDebug(__FUNCTION__, $errorMessage, 0);
                        $errorMessage = 'ID ' . $id . ' , ' . $errorMessage;
                        $this->LogMessage($errorMessage, KL_ERROR);
                    }
                }
            }
        }
        //Lower light unit
        $lowerLightUnit = true;
        $id = $this->ReadPropertyInteger('LowerLightUnit');
        if ($id != 0 && @IPS_ObjectExists($id)) {
            $BrightnessDifference = $this->CheckBrightnessDifference($id, $Brightness);
            if ($BrightnessDifference) {
                if ($UseSwitchingDelay) {
                    IPS_Sleep($this->ReadPropertyInteger('LowerLightUnitSwitchingDelay'));
                }
                $setBrightness = @HM_WriteValueFloat($id, 'LEVEL', $Brightness);
                if (!$setBrightness) {
                    IPS_Sleep(self::DELAY_MILLISECONDS);
                    $setBrightnessAgain = @HM_WriteValueFloat($id, 'LEVEL', $Brightness);
                    if (!$setBrightnessAgain) {
                        $lowerLightUnit = false;
                        //Revert brightness
                        $this->SetValue('Brightness', $actualBrightness);
                        $errorMessage = 'Helligkeit ' . $Brightness . ' konnte nicht gesetzt werden!';
                        $this->SendDebug(__FUNCTION__, $errorMessage, 0);
                        $errorMessage = 'ID ' . $id . ' , ' . $errorMessage;
                        $this->LogMessage($errorMessage, KL_ERROR);
                    }
                }
            }
        }
        //Semaphore leave
        IPS_SemaphoreLeave($this->InstanceID . '.SetBrightness');
        if ($upperLightUnit && $lowerLightUnit) {
            $result = true;
        }
        return $result;
    }

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