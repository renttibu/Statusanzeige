<?php

/*
 * @author      Ulrich Bittner
 * @copyright   (c) 2020, 2021
 * @license     CC BY-NC-SA 4.0
 * @see         https://github.com/ubittner/Statusanzeige/tree/master/HmIP-BSL
 */

/** @noinspection DuplicatedCode */

declare(strict_types=1);

trait SAHMIPBSL_control
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

    private function CheckTrigger(int $LightUnit): void
    {
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
                if (@IPS_ObjectExists($id)) {
                    if ($variable->Use) {
                        if ($id != 0) {
                            $this->SendDebug(__FUNCTION__, 'Die Variable: ' . $id . ' ist aktiviert.', 0);
                            $type = IPS_GetVariable($id)['VariableType'];
                            $value = $variable->TriggerValue;
                            switch ($variable->TriggerType) {
                                case 0: # on limit drop (integer, float)
                                    switch ($type) {
                                        case 1: # integer
                                            if ($value == 'false') {
                                                $value = '0';
                                            }
                                            if ($value == 'true') {
                                                $value = '1';
                                            }
                                            if (GetValueInteger($id) < intval($value)) {
                                                $this->SendDebug(__FUNCTION__, 'Bei Grenzunterschreitung (integer)', 0);
                                                $execute = true;
                                            }
                                            break;

                                        case 2: # float
                                            if ($value == 'false') {
                                                $value = '0';
                                            }
                                            if ($value == 'true') {
                                                $value = '1';
                                            }
                                            if (GetValueFloat($id) < floatval(str_replace(',', '.', $value))) {
                                                $this->SendDebug(__FUNCTION__, 'Bei Grenzunterschreitung (float)', 0);
                                                $execute = true;
                                            }
                                            break;

                                    }
                                    break;

                                case 1: # on specific value (bool, integer, float, string)
                                    switch ($type) {
                                        case 0: # bool
                                            if ($value == 'false') {
                                                $value = '0';
                                            }
                                            if (GetValueBoolean($id) == boolval($value)) {
                                                $this->SendDebug(__FUNCTION__, 'Bei bestimmten Wert (bool)', 0);
                                                $execute = true;
                                            }
                                            break;

                                        case 1: # integer
                                            if ($value == 'false') {
                                                $value = '0';
                                            }
                                            if ($value == 'true') {
                                                $value = '1';
                                            }
                                            if (GetValueInteger($id) == intval($value)) {
                                                $this->SendDebug(__FUNCTION__, 'Bei bestimmten Wert (integer)', 0);
                                                $execute = true;
                                            }
                                            break;

                                        case 2: # float
                                            if ($value == 'false') {
                                                $value = '0';
                                            }
                                            if ($value == 'true') {
                                                $value = '1';
                                            }
                                            if (GetValueFloat($id) == floatval(str_replace(',', '.', $value))) {
                                                $this->SendDebug(__FUNCTION__, 'Bei bestimmten Wert (float)', 0);
                                                $execute = true;
                                            }
                                            break;

                                        case 3: # string
                                            if (GetValueString($id) == (string) $value) {
                                                $this->SendDebug(__FUNCTION__, 'Bei bestimmten Wert (string)', 0);
                                                $execute = true;
                                            }
                                            break;

                                    }
                                    break;

                                case 2: # on limit exceed (integer, float)
                                    switch ($type) {
                                        case 1: # integer
                                            if ($value == 'false') {
                                                $value = '0';
                                            }
                                            if ($value == 'true') {
                                                $value = '1';
                                            }
                                            if (GetValueInteger($id) > intval($value)) {
                                                $this->SendDebug(__FUNCTION__, 'Bei Grenzunterschreitung (integer)', 0);
                                                $execute = true;
                                            }
                                            break;

                                        case 2: # float
                                            if ($value == 'false') {
                                                $value = '0';
                                            }
                                            if ($value == 'true') {
                                                $value = '1';
                                            }
                                            if (GetValueFloat($id) > floatval(str_replace(',', '.', $value))) {
                                                $this->SendDebug(__FUNCTION__, 'Bei Grenzunterschreitung (float)', 0);
                                                $execute = true;
                                            }
                                            break;

                                    }
                                    break;

                            }
                        } else {
                            $execute = true;
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

        $result = false;
        if ($this->CheckMaintenanceMode()) {
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
            $result = true;
            if ($UseSwitchingDelay) {
                IPS_Sleep($this->ReadPropertyInteger($unit . 'SwitchingDelay'));
            }
            $setColor = @HM_WriteValueInteger($id, 'COLOR', $Color);
            if (!$setColor) {
                IPS_Sleep(self::DELAY_MILLISECONDS);
                $setColorAgain = @HM_WriteValueInteger($id, 'COLOR', $Color);
                if (!$setColorAgain) {
                    $result = false;
                    // Revert color
                    $this->SetValue($unit . 'Color', $actualColor);
                    $this->SendDebug(__FUNCTION__, 'Der Farbwert: ' . $Color . ' konnte für die Leuchteinheit: ' . $LightUnit . ' nicht gesetzt werden!', 0);
                    $this->LogMessage('Instanz ' . $this->InstanceID . 'Der Farbwert: ' . $Color . ' konnte für die Leuchteinheit: ' . $id . ' nicht gesetzt werden!', KL_ERROR);
                }
            }
            if ($result) {
                $this->SendDebug(__FUNCTION__, 'Der Farbwert: ' . $Color . ' wurde für die Leuchteinheit: ' . $LightUnit . ' gesetzt.', 0);
            }
        }
        return $result;
    }

    private function SetDeviceBrightness(int $LightUnit, int $Brightness, bool $UseSwitchingDelay = false): bool
    {
        /*
         * $LightUnit
         * 0    = upper light unit
         * 1    = lower light unit
         */

        $result = false;
        if ($this->CheckMaintenanceMode()) {
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
            $result = true;
            $deviceBrightness = floatval($this->GetValue($unit . 'Brightness') / 100);
            if ($UseSwitchingDelay) {
                IPS_Sleep($this->ReadPropertyInteger($unit . 'SwitchingDelay'));
            }
            $setBrightness = @HM_WriteValueFloat($id, 'LEVEL', $deviceBrightness);
            if (!$setBrightness) {
                IPS_Sleep(self::DELAY_MILLISECONDS);
                $setBrightnessAgain = @HM_WriteValueFloat($id, 'LEVEL', $deviceBrightness);
                if (!$setBrightnessAgain) {
                    $result = false;
                    // Revert brightness
                    $this->SetValue($unit . 'Brightness', $actualBrightness);
                    $this->SendDebug(__FUNCTION__, 'Der Helligkeitswert: ' . $deviceBrightness . ' konnte für die Leuchteinheit: ' . $LightUnit . ' nicht gesetzt werden!', 0);
                    $this->LogMessage('Instanz ' . $this->InstanceID . 'der Helligkeitswert: ' . $deviceBrightness . ' konnte für die Leuchteinheit: ' . $id . ' nicht gesetzt werden!', KL_ERROR);
                }
            }
            if ($result) {
                $this->SendDebug(__FUNCTION__, 'Der Helligkeitswert: ' . $Brightness . ' wurde für die Leuchteinheit: ' . $LightUnit . ' gesetzt.', 0);
            }
        }
        return $result;
    }
}