<?php

/*
 * @author      Ulrich Bittner
 * @copyright   (c) 2020, 2021
 * @license     CC BY-NC-SA 4.0
 * @see         https://github.com/ubittner/Statusanzeige/tree/master/Statusanzeige
 */

declare(strict_types=1);

trait SA_control
{
    public function ToggleSignalling(bool $State, bool $UseSwitchingDelay = false): bool
    {
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
                $value = $var->TriggerValue;
                switch ($var->TriggerType) {
                    case 0: # on limit drop
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
                                    $state = true;
                                }
                                break;

                            case 2: # float
                                if (GetValueFloat($id) < floatval(str_replace(',', '.', $value))) {
                                    $this->SendDebug(__FUNCTION__, 'Bei Grenzunterschreitung (float)', 0);
                                    $state = true;
                                }
                                break;

                        }
                        break;

                    case 1: # on specific value
                        switch ($type) {
                            case 0: # bool
                                if ($value == 'false') {
                                    $value = '0';
                                }
                                if (GetValueBoolean($id) == boolval($value)) {
                                    $this->SendDebug(__FUNCTION__, 'Bei bestimmten Wert (bool)', 0);
                                    $state = true;
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
                                    $state = true;
                                }
                                break;

                            case 2: # float
                                if (GetValueFloat($id) == floatval(str_replace(',', '.', $value))) {
                                    $this->SendDebug(__FUNCTION__, 'Bei bestimmten Wert (float)', 0);
                                    $state = true;
                                }
                                break;

                            case 3: # string
                                if (GetValueString($id) == (string) $value) {
                                    $this->SendDebug(__FUNCTION__, 'Bei bestimmten Wert (string)', 0);
                                    $state = true;
                                }
                                break;

                        }
                        break;

                    case 2: # on limit exceed
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
                                    $state = true;
                                }
                                break;

                            case 2: # float
                                if (GetValueFloat($id) > floatval(str_replace(',', '.', $value))) {
                                    $this->SendDebug(__FUNCTION__, 'Bei Grenzunterschreitung (float)', 0);
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

    #################### Private

    private function ExecuteSignalling(bool $State, bool $UseSwitchingDelay = false): bool
    {
        $result = false;
        if ($this->CheckMaintenanceMode()) {
            return $result;
        }
        $id = $this->ReadPropertyInteger('SignallingVariable');
        if ($id != 0 && @IPS_ObjectExists($id)) {
            if ($UseSwitchingDelay) {
                IPS_Sleep($this->ReadPropertyInteger('SignallingSwitchingDelay'));
            }
            $result = true;
            $toggle = @RequestAction($id, $State);
            if (!$toggle) {
                IPS_Sleep(self::DELAY_MILLISECONDS);
                $toggleAgain = @RequestAction($id, $State);
                if (!$toggleAgain) {
                    $result = false;
                    $this->SendDebug(__FUNCTION__, 'Die Anzeige ' . $id . ' konnte nicht geschaltet werden!', 0);
                    $this->LogMessage('Instanz ' . $this->InstanceID . ', die Anzeige ' . $id . ' konnte nicht geschaltet werden!', KL_ERROR);
                }
            }
            if ($result) {
                $stateText = 'ausgeschaltet.';
                if ($State) {
                    $stateText = 'eingeschaltet.';
                }
                $this->SendDebug(__FUNCTION__, 'Die Anzeige ' . $id . ' wurde ' . $stateText, 0);
            }
        }
        return $result;
    }

    private function ExecuteInvertedSignalling(bool $State, bool $UseSwitchingDelay = false): bool
    {
        $result = false;
        if ($this->CheckMaintenanceMode()) {
            return $result;
        }
        $id = $this->ReadPropertyInteger('InvertedSignallingVariable');
        if ($id != 0 && @IPS_ObjectExists($id)) {
            if ($UseSwitchingDelay) {
                IPS_Sleep($this->ReadPropertyInteger('InvertedSignallingSwitchingDelay'));
            }
            $result = true;
            $toggle = @RequestAction($id, $State);
            if (!$toggle) {
                IPS_Sleep(self::DELAY_MILLISECONDS);
                $toggleAgain = @RequestAction($id, $State);
                if (!$toggleAgain) {
                    $result = false;
                    $this->SendDebug(__FUNCTION__, 'Die invertierte Anzeige ' . $id . ' konnte nicht geschaltet werden!', 0);
                    $this->LogMessage('Instanz ' . $this->InstanceID . ', die invertierte Anzeige ' . $id . ' konnte nicht geschaltet werden!', KL_ERROR);
                }
            }
            if ($result) {
                $stateText = 'ausgeschaltet.';
                if ($State) {
                    $stateText = 'eingeschaltet.';
                }
                $this->SendDebug(__FUNCTION__, 'Die invertierte Anzeige ' . $id . ' wurde ' . $stateText, 0);
            }
        }
        return $result;
    }
}