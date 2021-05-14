<?php

/*
 * @author      Ulrich Bittner
 * @copyright   (c) 2020, 2021
 * @license     CC BY-NC-SA 4.0
 * @see         https://github.com/ubittner/Statusanzeige/tree/master/HmIP-MP3P
 */

/** @noinspection DuplicatedCode */

declare(strict_types=1);

trait SAHMIPMP3P_control
{
    public function SetColor(int $Color, bool $UseSwitchingDelay = false): bool
    {
        /*
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
        if (!$this->CheckExistingTrigger()) {
            $this->WriteAttributeInteger('LightUnitLastColor', $Color);
        }
        return $this->SetDeviceColor($Color, $UseSwitchingDelay);
    }

    public function SetBrightness(int $Brightness, bool $UseSwitchingDelay = false): bool
    {
        if ($this->CheckMaintenanceMode()) {
            return false;
        }
        if ($this->CheckNightMode()) {
            return false;
        }
        if (!$this->CheckExistingTrigger()) {
            $this->WriteAttributeInteger('LightUnitLastBrightness', $Brightness);
        }
        return $this->SetDeviceBrightness($Brightness, $UseSwitchingDelay);
    }

    public function CheckActualStatus(): void
    {
        if ($this->CheckMaintenanceMode()) {
            return;
        }
        if ($this->CheckNightMode()) {
            return;
        }
        $this->UpdateLightUnit();
    }

    #################### Private

    private function UpdateLightUnit(): void
    {
        if ($this->CheckMaintenanceMode()) {
            return;
        }
        if ($this->CheckNightMode()) {
            return;
        }
        $this->CheckTrigger();
    }

    private function CheckExistingTrigger(): bool
    {
        $result = false;
        $variables = json_decode($this->ReadPropertyString('TriggerVariables'));
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

    private function CheckTrigger(): void
    {
        if ($this->CheckMaintenanceMode()) {
            return;
        }
        if ($this->CheckNightMode()) {
            return;
        }
        $variables = json_decode($this->ReadPropertyString('TriggerVariables'));
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
                    $this->SetColor($variable->Color, true);
                    // Brightness
                    $this->SetBrightness($variable->Brightness, true);
                    break;
                }
            }
        }
    }

    private function SetDeviceColor(int $Color, bool $UseSwitchingDelay = false): bool
    {
        /*
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
        $actualColor = $this->GetValue('LightUnitColor');
        $this->SetValue('LightUnitColor', $Color);
        $id = $this->ReadPropertyInteger('LightUnit');
        if ($id != 0 && @IPS_ObjectExists($id)) {
            $result = true;
            if ($UseSwitchingDelay) {
                IPS_Sleep($this->ReadPropertyInteger('LightUnitSwitchingDelay'));
            }
            $setColor = @HM_WriteValueInteger($id, 'COLOR', $Color);
            if (!$setColor) {
                IPS_Sleep(self::DELAY_MILLISECONDS);
                $setColorAgain = @HM_WriteValueInteger($id, 'COLOR', $Color);
                if (!$setColorAgain) {
                    $result = false;
                    // Revert color
                    $this->SetValue('LightUnitColor', $actualColor);
                    $this->SendDebug(__FUNCTION__, 'Der Farbwert: ' . $Color . ' konnte für die Leuchteinheit: ' . $id . ' nicht gesetzt werden!', 0);
                    $this->LogMessage('Instanz ' . $this->InstanceID . ', der Farbwert: ' . $Color . ' konnte für die Leuchteinheit: ' . $id . ' nicht gesetzt werden!', KL_ERROR);
                }
            }
            if ($result) {
                $this->SendDebug(__FUNCTION__, 'Der Farbwert: ' . $Color . ' wurde für die Leuchteinheit: ' . $id . ' gesetzt.', 0);
            }
        }
        return $result;
    }

    private function SetDeviceBrightness(int $Brightness, bool $UseSwitchingDelay = false): bool
    {
        $result = false;
        if ($this->CheckMaintenanceMode()) {
            return $result;
        }
        $actualBrightness = $this->GetValue('LightUnitBrightness');
        $this->SetValue('LightUnitBrightness', $Brightness);
        $id = $this->ReadPropertyInteger('LightUnit');
        if ($id != 0 && @IPS_ObjectExists($id)) {
            $result = true;
            $deviceBrightness = floatval($this->GetValue('LightUnitBrightness') / 100);
            if ($UseSwitchingDelay) {
                IPS_Sleep($this->ReadPropertyInteger('LightUnitSwitchingDelay'));
            }
            $setBrightness = @HM_WriteValueFloat($id, 'LEVEL', $deviceBrightness);
            if (!$setBrightness) {
                IPS_Sleep(self::DELAY_MILLISECONDS);
                $setBrightnessAgain = @HM_WriteValueFloat($id, 'LEVEL', $deviceBrightness);
                if (!$setBrightnessAgain) {
                    $result = false;
                    // Revert brightness
                    $this->SetValue('LightUnitBrightness', $actualBrightness);
                    $this->SendDebug(__FUNCTION__, 'Der Helligkeitswert: ' . $deviceBrightness . ' konnte für die Leuchteinheit: ' . $id . ' nicht gesetzt werden!', 0);
                    $this->LogMessage('Instanz ' . $this->InstanceID . ', der Helligkeitswert: ' . $deviceBrightness . ' konnte für die Leuchteinheit: ' . $id . ' nicht gesetzt werden!', KL_ERROR);
                }
            }
            if ($result) {
                $this->SendDebug(__FUNCTION__, 'Der Helligkeitswert: ' . $Brightness . ' wurde für die Leuchteinheit: ' . $id . ' gesetzt.', 0);
            }
        }
        return $result;
    }
}