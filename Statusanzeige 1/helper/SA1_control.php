<?php

/** @noinspection DuplicatedCode */
/** @noinspection PhpUnused */

declare(strict_types=1);

trait SA1_control
{
    public function ToggleSignalling(bool $State, bool $UseSwitchingDelay = false): bool
    {
        $this->SendDebug(__FUNCTION__, 'Die Methode wird ausgeführt.', 0);
        if ($this->CheckMaintenanceMode()) {
            return false;
        }
        if ($this->CheckNightMode()) {
            return false;
        }
        $actualValue = $this->GetValue('Signalling');
        $this->SetValue('Signalling', $State);
        if ($State) {
            $invertedSignalling = $this->ExecuteInvertedSignalling(!$State, $UseSwitchingDelay);
            $signalling = $this->ExecuteSignalling($State, $UseSwitchingDelay);
        } else {
            $signalling = $this->ExecuteSignalling($State, $UseSwitchingDelay);
            $invertedSignalling = $this->ExecuteInvertedSignalling(!$State, $UseSwitchingDelay);
        }
        $result = true;
        if (!$signalling || !$invertedSignalling) {
            $result = false;
            // Revert value
            $this->SetValue('Signalling', $actualValue);
        }
        return $result;
    }

    public function UpdateState(): bool
    {
        $this->SendDebug(__FUNCTION__, 'Die Methode wird ausgeführt.', 0);
        if ($this->CheckMaintenanceMode()) {
            return false;
        }
        if ($this->CheckNightMode()) {
            return false;
        }
        $state = false;
        $vars = json_decode($this->ReadPropertyString('TriggerVariables'));
        if (!empty($vars)) {
            foreach ($vars as $var) {
                if (!$var->Use) {
                    continue;
                }
                $id = $var->ID;
                if ($id == 0 || @!IPS_ObjectExists($id)) {
                    continue;
                }
                $type = IPS_GetVariable($id)['VariableType'];
                $value = $var->Value;
                switch ($var->Trigger) {
                    case 0: # on change (bool, integer, float, string)
                    case 1: # on update (bool, integer, float, string)
                        $this->SendDebug(__FUNCTION__, 'Bei Änderung und bei Aktualisierung wird nicht berücksichtigt!', 0);
                        break;

                    case 2: # on limit drop, once (integer, float)
                    case 3: # on limit drop, every time (integer, float)
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
                                    $state = true;
                                }
                                break;

                            case 2: # float
                                $this->SendDebug(__FUNCTION__, 'Bei Grenzunterschreitung (float)', 0);
                                if (GetValueFloat($id) < floatval(str_replace(',', '.', $value))) {
                                    $state = true;
                                }
                                break;

                        }
                        break;

                    case 4: # on limit exceed, once (integer, float)
                    case 5: # on limit exceed, every time (integer, float)
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
                                    $state = true;
                                }
                                break;

                            case 2: # float
                                $this->SendDebug(__FUNCTION__, 'Bei Grenzunterschreitung (float)', 0);
                                if (GetValueFloat($id) > floatval(str_replace(',', '.', $value))) {
                                    $state = true;
                                }
                                break;

                        }
                        break;

                    case 6: # on specific value, once (bool, integer, float, string)
                    case 7: # on specific value, every time (bool, integer, float, string)
                        switch ($type) {
                            case 0: # bool
                                $this->SendDebug(__FUNCTION__, 'Bei bestimmten Wert (bool)', 0);
                                if ($value == 'false') {
                                    $value = '0';
                                }
                                if (GetValueBoolean($id) == boolval($value)) {
                                    $state = true;
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
                                    $state = true;
                                }
                                break;

                            case 2: # float
                                $this->SendDebug(__FUNCTION__, 'Bei bestimmten Wert (float)', 0);
                                if (GetValueFloat($id) == floatval(str_replace(',', '.', $value))) {
                                    $state = true;
                                }
                                break;

                            case 3: # string
                                $this->SendDebug(__FUNCTION__, 'Bei bestimmten Wert (string)', 0);
                                if (GetValueString($id) == (string) $value) {
                                    $state = true;
                                }
                                break;

                        }
                        break;

                }
            }
        }
        return $this->ToggleSignalling($state, true);
    }

    public function CheckTrigger(int $SenderID, bool $ValueChanged): bool
    {
        $this->SendDebug(__FUNCTION__, 'Die Methode wird ausgeführt.', 0);
        $this->SendDebug(__FUNCTION__, 'Sender: ' . $SenderID . ', Wert hat sich geändert: ' . json_encode($ValueChanged), 0);
        if ($this->CheckMaintenanceMode()) {
            return false;
        }
        $vars = json_decode($this->ReadPropertyString('TriggerVariables'), true);
        if (empty($vars)) {
            return false;
        }
        $key = array_search($SenderID, array_column($vars, 'ID'));
        if (!is_int($key)) {
            return false;
        }
        if (!$vars[$key]['Use']) {
            return false;
        }
        $execute = false;
        $state = false;
        $type = IPS_GetVariable($SenderID)['VariableType'];
        $value = $vars[$key]['Value'];
        switch ($vars[$key]['Trigger']) {
            case 0: # on change (bool, integer, float, string)
                $this->SendDebug(__FUNCTION__, 'Bei Änderung (bool, integer, float, string)', 0);
                if ($ValueChanged) {
                    $execute = true;
                    $state = true;
                }
                break;

            case 1: # on update (bool, integer, float, string)
                $this->SendDebug(__FUNCTION__, 'Bei Aktualisierung (bool, integer, float, string)', 0);
                $execute = true;
                $state = true;
                break;

            case 2: # on limit drop, once (integer, float)
                switch ($type) {
                    case 1: # integer
                        $this->SendDebug(__FUNCTION__, 'Bei Grenzunterschreitung, einmalig (integer)', 0);
                        if ($ValueChanged) {
                            $execute = true;
                            if ($value == 'false') {
                                $value = '0';
                            }
                            if ($value == 'true') {
                                $value = '1';
                            }
                            if (GetValueInteger($SenderID) < intval($value)) {
                                $state = true;
                            }
                        }
                        break;

                    case 2: # float
                        $this->SendDebug(__FUNCTION__, 'Bei Grenzunterschreitung, einmalig (float)', 0);
                        if ($ValueChanged) {
                            $execute = true;
                            if (GetValueFloat($SenderID) < floatval(str_replace(',', '.', $value))) {
                                $state = true;
                            }
                        }
                        break;

                }
                break;

            case 3: # on limit drop, every time (integer, float)
                switch ($type) {
                    case 1: # integer
                        $this->SendDebug(__FUNCTION__, 'Bei Grenzunterschreitung, mehrmalig (integer)', 0);
                        $execute = true;
                        if ($value == 'false') {
                            $value = '0';
                        }
                        if ($value == 'true') {
                            $value = '1';
                        }
                        if (GetValueInteger($SenderID) < intval($value)) {
                            $state = true;
                        }
                        break;

                    case 2: # float
                        $this->SendDebug(__FUNCTION__, 'Bei Grenzunterschreitung, mehrmalig (float)', 0);
                        $execute = true;
                        if (GetValueFloat($SenderID) < floatval(str_replace(',', '.', $value))) {
                            $state = true;
                        }
                        break;

                }
                break;

            case 4: # on limit exceed, once (integer, float)
                switch ($type) {
                    case 1: # integer
                        $this->SendDebug(__FUNCTION__, 'Bei Grenzunterschreitung, einmalig (integer)', 0);
                        if ($ValueChanged) {
                            $execute = true;
                            if ($value == 'false') {
                                $value = '0';
                            }
                            if ($value == 'true') {
                                $value = '1';
                            }
                            if (GetValueInteger($SenderID) > intval($value)) {
                                $state = true;
                            }
                        }
                        break;

                    case 2: # float
                        $this->SendDebug(__FUNCTION__, 'Bei Grenzunterschreitung, einmalig (float)', 0);
                        if ($ValueChanged) {
                            $execute = true;
                            if (GetValueFloat($SenderID) > floatval(str_replace(',', '.', $value))) {
                                $state = true;
                            }
                        }
                        break;

                }
                break;

            case 5: # on limit exceed, every time (integer, float)
                switch ($type) {
                    case 1: # integer
                        $this->SendDebug(__FUNCTION__, 'Bei Grenzunterschreitung, mehrmalig (integer)', 0);
                        $execute = true;
                        if ($value == 'false') {
                            $value = '0';
                        }
                        if ($value == 'true') {
                            $value = '1';
                        }
                        if (GetValueInteger($SenderID) > intval($value)) {
                            $state = true;
                        }
                        break;

                    case 2: # float
                        $this->SendDebug(__FUNCTION__, 'Bei Grenzunterschreitung, mehrmalig (float)', 0);
                        $execute = true;
                        if (GetValueFloat($SenderID) > floatval(str_replace(',', '.', $value))) {
                            $state = true;
                        }
                        break;

                }
                break;

            case 6: # on specific value, once (bool, integer, float, string)
                switch ($type) {
                    case 0: # bool
                        $this->SendDebug(__FUNCTION__, 'Bei bestimmten Wert, einmalig (bool)', 0);
                        if ($ValueChanged) {
                            $execute = true;
                            if ($value == 'false') {
                                $value = '0';
                            }
                            if (GetValueBoolean($SenderID) == boolval($value)) {
                                $state = true;
                            }
                        }
                        break;

                    case 1: # integer
                        $this->SendDebug(__FUNCTION__, 'Bei bestimmten Wert, einmalig (integer)', 0);
                        if ($ValueChanged) {
                            $execute = true;
                            if ($value == 'false') {
                                $value = '0';
                            }
                            if ($value == 'true') {
                                $value = '1';
                            }
                            if (GetValueInteger($SenderID) == intval($value)) {
                                $state = true;
                            }
                        }
                        break;

                    case 2: # float
                        $this->SendDebug(__FUNCTION__, 'Bei bestimmten Wert, einmalig (float)', 0);
                        if ($ValueChanged) {
                            $execute = true;
                            if (GetValueFloat($SenderID) == floatval(str_replace(',', '.', $value))) {
                                $state = true;
                            }
                        }
                        break;

                    case 3: # string
                        $this->SendDebug(__FUNCTION__, 'Bei bestimmten Wert, einmalig (string)', 0);
                        if ($ValueChanged) {
                            $execute = true;
                            if (GetValueString($SenderID) == (string) $value) {
                                $state = true;
                            }
                        }
                        break;

                }
                break;

            case 7: # on specific value, every time (bool, integer, float, string)
                switch ($type) {
                    case 0: # bool
                        $this->SendDebug(__FUNCTION__, 'Bei bestimmten Wert, mehrmalig (bool)', 0);
                        $execute = true;
                        if ($value == 'false') {
                            $value = '0';
                        }
                        if (GetValueBoolean($SenderID) == boolval($value)) {
                            $state = true;
                        }
                        break;

                    case 1: # integer
                        $this->SendDebug(__FUNCTION__, 'Bei bestimmten Wert, mehrmalig (integer)', 0);
                        $execute = true;
                        if ($value == 'false') {
                            $value = '0';
                        }
                        if ($value == 'true') {
                            $value = '1';
                        }
                        if (GetValueInteger($SenderID) == intval($value)) {
                            $state = true;
                        }
                        break;

                    case 2: # float
                        $this->SendDebug(__FUNCTION__, 'Bei bestimmten Wert, mehrmalig (float)', 0);
                        $execute = true;
                        if (GetValueFloat($SenderID) == floatval(str_replace(',', '.', $value))) {
                            $state = true;
                        }
                        break;

                    case 3: # string
                        $this->SendDebug(__FUNCTION__, 'Bei bestimmten Wert, mehrmalig (string)', 0);
                        $execute = true;
                        if (GetValueString($SenderID) == (string) $value) {
                            $state = true;
                        }
                        break;

                }
                break;

        }
        $this->SendDebug(__FUNCTION__, 'Bedingung erfüllt: ' . json_encode($execute), 0);
        $result = false;
        if ($execute) {
            $result = $this->ToggleSignalling($state, true);
        }
        return $result;
    }

    #################### Private

    private function ExecuteSignalling(bool $State, bool $UseSwitchingDelay = false): bool
    {
        $this->SendDebug(__FUNCTION__, 'Die Methode wird ausgeführt.', 0);
        if ($this->CheckMaintenanceMode()) {
            return false;
        }
        $result = true;
        $id = $this->ReadPropertyInteger('SignallingVariable');
        if ($id != 0 && @IPS_ObjectExists($id)) {
            if ($UseSwitchingDelay) {
                IPS_Sleep($this->ReadPropertyInteger('SignallingSwitchingDelay'));
            }
            $result = @RequestAction($id, $State);
            if (!$result) {
                IPS_Sleep(self::DELAY_MILLISECONDS);
                $toggleAgain = @RequestAction($id, $State);
                if (!$toggleAgain) {
                    $result = false;
                    $this->SendDebug(__FUNCTION__, 'Variable ' . $id . ' konnte nicht geschaltet werden!', 0);
                    $this->LogMessage('Instanz ' . $this->InstanceID . ', Variable ' . $id . ' konnte nicht geschaltet werden!', KL_ERROR);
                } else {
                    $result = true;
                }
            }
        }
        return $result;
    }

    private function ExecuteInvertedSignalling(bool $State, bool $UseSwitchingDelay = false): bool
    {
        $this->SendDebug(__FUNCTION__, 'Die Methode wird ausgeführt. (' . microtime(true) . ')', 0);
        if ($this->CheckMaintenanceMode()) {
            return false;
        }
        $result = true;
        $id = $this->ReadPropertyInteger('InvertedSignallingVariable');
        if ($id != 0 && @IPS_ObjectExists($id)) {
            if ($UseSwitchingDelay) {
                IPS_Sleep($this->ReadPropertyInteger('InvertedSignallingSwitchingDelay'));
            }
            $result = @RequestAction($id, $State);
            if (!$result) {
                IPS_Sleep(self::DELAY_MILLISECONDS);
                $toggleAgain = @RequestAction($id, $State);
                if (!$toggleAgain) {
                    $result = false;
                    $this->SendDebug(__FUNCTION__, 'Variable ' . $id . ' konnte nicht geschaltet werden!', 0);
                    $this->LogMessage('Instanz ' . $this->InstanceID . ', Variable ' . $id . ' konnte nicht geschaltet werden!', KL_ERROR);
                } else {
                    $result = true;
                }
            }
        }
        return $result;
    }
}