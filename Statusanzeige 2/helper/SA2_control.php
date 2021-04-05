<?php

/*
 * @author      Ulrich Bittner
 * @copyright   (c) 2020, 2021
 * @license    	CC BY-NC-SA 4.0
 * @see         https://github.com/ubittner/Statusanzeige/tree/master/Statusanzeige%202
 */

/** @noinspection PhpUnusedPrivateMethodInspection */
/** @noinspection DuplicatedCode */
/** @noinspection PhpUnused */

declare(strict_types=1);

trait SA2_control
{
    public function SetColor(int $LightUnit, int $Color, bool $UseSwitchingDelay = false): bool
    {
        /*
         * $LightUnit
         * 0    = upper light unit
         * 1    = lower light unit
         *
         * $Color
         * 0    = off
         * 1    = blue
         * 2    = green
         * 3    = turquoise
         * 4    = red
         * 5    = violet
         * 6    = yellow
         * 7    = white
         */

        $this->SendDebug(__FUNCTION__, 'Die Methode wird ausgeführt.', 0);
        if ($this->CheckMaintenanceMode()) {
            return false;
        }
        if ($this->CheckNightMode()) {
            return false;
        }
        if ($LightUnit == 0) {
            if (!$this->CheckExistingTrigger(0)) {
                $this->WriteAttributeInteger('UpperLightUnitLastColor', $Color);
            }
        } else {
            if (!$this->CheckExistingTrigger(1)) {
                $this->WriteAttributeInteger('LowerLightUnitLastColor', $Color);
            }
        }
        return $this->SetDeviceColor($LightUnit, $Color, $UseSwitchingDelay);
    }

    public function SetBrightness(int $LightUnit, int $Brightness, bool $UseSwitchingDelay = false): bool
    {
        /*
         * $LightUnit
         * 0    = upper light unit
         * 1    = lower light unit
         */

        $this->SendDebug(__FUNCTION__, 'Die Methode wird ausgeführt.', 0);
        if ($this->CheckMaintenanceMode()) {
            return false;
        }
        if ($this->CheckNightMode()) {
            return false;
        }
        if ($LightUnit == 0) {
            if (!$this->CheckExistingTrigger(0)) {
                $this->WriteAttributeInteger('UpperLightUnitLastBrightness', $Brightness);
            }
        } else {
            if (!$this->CheckExistingTrigger(1)) {
                $this->WriteAttributeInteger('LowerLightUnitLastBrightness', $Brightness);
            }
        }
        return $this->SetDeviceBrightness($LightUnit, $Brightness, $UseSwitchingDelay);
    }

    public function CheckActualStatus(): void
    {
        $this->SendDebug(__FUNCTION__, 'Methode wird ausgeführt.', 0);
        if ($this->CheckMaintenanceMode()) {
            return;
        }
        if ($this->CheckNightMode()) {
            return;
        }
        $this->UpdateUpperLightUnit();
        $this->UpdateLowerLightUnit();
    }

    #################### Private

    private function UpdateUpperLightUnit(): void
    {
        $this->SendDebug(__FUNCTION__, 'Methode wird ausgeführt.', 0);
        if ($this->CheckMaintenanceMode()) {
            return;
        }
        if ($this->CheckNightMode()) {
            return;
        }
        $this->CheckTrigger(0);
    }

    private function UpdateLowerLightUnit(): void
    {
        $this->SendDebug(__FUNCTION__, 'Methode wird ausgeführt.', 0);
        if ($this->CheckMaintenanceMode()) {
            return;
        }
        if ($this->CheckNightMode()) {
            return;
        }
        $this->CheckTrigger(1);
    }

    private function CheckExistingTrigger(int $LightUnit): bool
    {
        $this->SendDebug(__FUNCTION__, 'Methode wird ausgeführt.', 0);
        $result = false;
        $propertyName = 'UpperLightUnitTriggerVariables';
        if ($LightUnit == 1) {
            $propertyName = 'LowerLightUnitTriggerVariables';
        }
        $variables = json_decode($this->ReadPropertyString($propertyName));
        if (!empty($variables)) {
            foreach ($variables as $variable) {
                $id = $variable->ID;
                if ($id != 0 && @IPS_ObjectExists($id)) {
                    $use = $variable->Use;
                    if ($use) {
                        $result = true;
                    }
                }
            }
        }
        return $result;
    }

    public function CheckTriggerUpdate(int $SenderID, bool $ValueChanged): void
    {
        $this->SendDebug(__FUNCTION__, 'Methode wird ausgeführt.', 0);
        $triggers = ['UpperLightUnitTriggerVariables', 'LowerLightUnitTriggerVariables'];
        foreach ($triggers as $trigger) {
            $lightUnit = 0;
            if ($trigger == 'LowerLightUnitTriggerVariables') {
                $lightUnit = 1;
            }
            $variables = json_decode($this->ReadPropertyString($trigger));
            if (!empty($variables)) {
                $update = false;
                foreach ($variables as $variable) {
                    $id = $variable->ID;
                    if ($SenderID == $id) {
                        if ($id != 0 && @IPS_ObjectExists($id)) {
                            if ($variable->Use) {
                                switch ($variable->Trigger) {
                                    // Once
                                    case 0:
                                    case 2:
                                    case 4:
                                        if ($ValueChanged) {
                                            $update = true;
                                        }
                                        break;

                                    default:
                                        $update = true;
                                }
                            }
                        }
                    }
                }
                if ($update) {
                    $this->CheckTrigger($lightUnit);
                }
            }
        }
    }

    private function CheckTrigger(int $LightUnit): void
    {
        $this->SendDebug(__FUNCTION__, 'Methode wird ausgeführt.', 0);
        if ($this->CheckMaintenanceMode()) {
            return;
        }
        if ($this->CheckNightMode()) {
            return;
        }
        $propertyName = 'UpperLightUnitTriggerVariables';
        if ($LightUnit == 1) {
            $propertyName = 'LowerLightUnitTriggerVariables';
        }
        $variables = json_decode($this->ReadPropertyString($propertyName));
        if (!empty($variables)) {
            // Sort descending
            array_multisort(array_column($variables, 'Group'), SORT_DESC, $variables);
            foreach ($variables as $variable) {
                $execute = false;
                $id = $variable->ID;
                if ($id != 0 && @IPS_ObjectExists($id)) {
                    if ($variable->Use) {
                        $this->SendDebug(__FUNCTION__, 'Variable: ' . $id . ' ist aktiviert', 0);
                        $type = IPS_GetVariable($id)['VariableType'];
                        $value = $variable->Value;
                        switch ($variable->Trigger) {
                            case 0: # on limit drop, once (integer, float)
                            case 1: # on limit drop, every time (integer, float)
                                switch ($type) {
                                    case 1: # integer
                                        $this->SendDebug(__FUNCTION__, 'Bei Grenzunterschreitung (integer)', 0);
                                        if ($value == 'false') {
                                            $value = '0';
                                        }
                                        if ($value == 'true') {
                                            $value = '1';
                                        }
                                        if (GetValueInteger($id) < intval($value)) {
                                            $execute = true;
                                        }
                                        break;

                                    case 2: # float
                                        $this->SendDebug(__FUNCTION__, 'Bei Grenzunterschreitung (float)', 0);
                                        if ($value == 'false') {
                                            $value = '0';
                                        }
                                        if ($value == 'true') {
                                            $value = '1';
                                        }
                                        if (GetValueFloat($id) < floatval(str_replace(',', '.', $value))) {
                                            $execute = true;
                                        }
                                        break;

                                }
                                break;

                            case 2: # on limit exceed, once (integer, float)
                            case 3: # on limit exceed, every time (integer, float)
                                switch ($type) {
                                    case 1: # integer
                                        $this->SendDebug(__FUNCTION__, 'Bei Grenzunterschreitung (integer)', 0);
                                        if ($value == 'false') {
                                            $value = '0';
                                        }
                                        if ($value == 'true') {
                                            $value = '1';
                                        }
                                        if (GetValueInteger($id) > intval($value)) {
                                            $execute = true;
                                        }
                                        break;

                                    case 2: # float
                                        $this->SendDebug(__FUNCTION__, 'Bei Grenzunterschreitung (float)', 0);
                                        if ($value == 'false') {
                                            $value = '0';
                                        }
                                        if ($value == 'true') {
                                            $value = '1';
                                        }
                                        if (GetValueFloat($id) > floatval(str_replace(',', '.', $value))) {
                                            $execute = true;
                                        }
                                        break;

                                }
                                break;

                            case 4: # on specific value, once (bool, integer, float, string)
                            case 5: # on specific value, every time (bool, integer, float, string)
                                switch ($type) {
                                    case 0: # bool
                                        $this->SendDebug(__FUNCTION__, 'Bei bestimmten Wert (bool)', 0);
                                        if ($value == 'false') {
                                            $value = '0';
                                        }
                                        if (GetValueBoolean($id) == boolval($value)) {
                                            $execute = true;
                                        }
                                        break;

                                    case 1: # integer
                                        $this->SendDebug(__FUNCTION__, 'Bei bestimmten Wert (integer)', 0);
                                        if ($value == 'false') {
                                            $value = '0';
                                        }
                                        if ($value == 'true') {
                                            $value = '1';
                                        }
                                        if (GetValueInteger($id) == intval($value)) {
                                            $execute = true;
                                        }
                                        break;

                                    case 2: # float
                                        $this->SendDebug(__FUNCTION__, 'Bei bestimmten Wert (float)', 0);
                                        if ($value == 'false') {
                                            $value = '0';
                                        }
                                        if ($value == 'true') {
                                            $value = '1';
                                        }
                                        if (GetValueFloat($id) == floatval(str_replace(',', '.', $value))) {
                                            $execute = true;
                                        }
                                        break;

                                    case 3: # string
                                        $this->SendDebug(__FUNCTION__, 'Bei bestimmten Wert (string)', 0);
                                        if (GetValueString($id) == (string) $value) {
                                            $execute = true;
                                        }
                                        break;

                                }
                                break;

                        }
                    }
                }
                if ($execute) {
                    // Color
                    $this->SetColor($LightUnit, $variable->Color, true);
                    // Brightness
                    $this->SetBrightness($LightUnit, $variable->Brightness, true);
                    break;
                }
            }
        }
    }

    private function SetDeviceColor(int $LightUnit, int $Color, bool $UseSwitchingDelay = false): bool
    {
        /*
         * $LightUnit
         * 0    = upper light unit
         * 1    = lower light unit
         *
         * $Color
         * 0    = off
         * 1    = blue
         * 2    = green
         * 3    = turquoise
         * 4    = red
         * 5    = violet
         * 6    = yellow
         * 7    = white
         */

        $this->SendDebug(__FUNCTION__, 'Die Methode wird ausgeführt.', 0);
        $result = false;
        if ($this->CheckMaintenanceMode()) {
            return $result;
        }
        // Semaphore Enter
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
            if ($this->CheckColorDifference($id, $Color)) {
                if ($UseSwitchingDelay) {
                    IPS_Sleep($this->ReadPropertyInteger($unit . 'SwitchingDelay'));
                }
                $setColor = @HM_WriteValueInteger($id, 'COLOR', $Color);
                if (!$setColor) {
                    IPS_Sleep(self::DELAY_MILLISECONDS);
                    $setColorAgain = @HM_WriteValueInteger($id, 'COLOR', $Color);
                    if (!$setColorAgain) {
                        // Revert color
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
        // Semaphore leave
        IPS_SemaphoreLeave($this->InstanceID . '.SetColor');
        return $result;
    }

    private function SetDeviceBrightness(int $LightUnit, int $Brightness, bool $UseSwitchingDelay = false): bool
    {
        /*
         * $LightUnit
         * 0    = upper light unit
         * 1    = lower light unit
         */

        $this->SendDebug(__FUNCTION__, 'Die Methode wird ausgeführt.', 0);
        $result = false;
        if ($this->CheckMaintenanceMode()) {
            return $result;
        }
        // Semaphore Enter
        if (!IPS_SemaphoreEnter($this->InstanceID . '.SetBrightness', 5000)) {
            return $result;
        }
        $unit = 'UpperLightUnit';
        if ($LightUnit == 1) {
            $unit = 'LowerLightUnit';
        }
        $actualBrightness = $this->GetValue($unit . 'Brightness');
        $this->SetValue($unit . 'Brightness', $Brightness);
        $id = $this->ReadPropertyInteger($unit);
        if ($id != 0 && @IPS_ObjectExists($id)) {
            $deviceBrightness = floatval($this->GetValue($unit . 'Brightness') / 100);
            if ($this->CheckBrightnessDifference($id, $deviceBrightness)) {
                if ($UseSwitchingDelay) {
                    IPS_Sleep($this->ReadPropertyInteger($unit . 'SwitchingDelay'));
                }
                $setBrightness = @HM_WriteValueFloat($id, 'LEVEL', $deviceBrightness);
                if (!$setBrightness) {
                    IPS_Sleep(self::DELAY_MILLISECONDS);
                    $setBrightnessAgain = @HM_WriteValueFloat($id, 'LEVEL', $deviceBrightness);
                    if (!$setBrightnessAgain) {
                        // Revert brightness
                        $this->SetValue($unit . 'Brightness', $actualBrightness);
                        $errorMessage = 'Helligkeit ' . $deviceBrightness . ' konnte nicht gesetzt werden!';
                        $this->SendDebug(__FUNCTION__, $errorMessage, 0);
                        $errorMessage = 'ID ' . $id . ' , ' . $errorMessage;
                        $this->LogMessage($errorMessage, KL_ERROR);
                    }
                }
                if ($setBrightness || $setBrightnessAgain) {
                    $result = true;
                }
            }
        }
        // Semaphore leave
        IPS_SemaphoreLeave($this->InstanceID . '.SetBrightness');
        return $result;
    }

    private function CheckColorDifference(int $Device, int $Value): bool
    {
        $this->SendDebug(__FUNCTION__, 'Die Methode wird ausgeführt.', 0);
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

    private function CheckBrightnessDifference(int $Device, float $Value): bool
    {
        $this->SendDebug(__FUNCTION__, 'Die Methode wird ausgeführt.', 0);
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