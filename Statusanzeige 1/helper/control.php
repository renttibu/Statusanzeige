<?php

/** @noinspection DuplicatedCode */
/** @noinspection PhpUnused */

declare(strict_types=1);

trait SA1_control
{
    #################### Public

    /**
     * Toggles the signalling off or on.
     *
     * @param bool $State
     * false    = off
     * true     = on
     *
     * @return bool
     * false    = an error occurred
     * true     = successful
     *
     * @throws Exception
     */
    public function ToggleSignalling(bool $State): bool
    {
        $this->SendDebug(__FUNCTION__, 'Die Methode wird ausgeführt. (' . microtime(true) . ')', 0);
        if ($this->CheckMaintenanceMode()) {
            return false;
        }
        $actualValue = $this->GetValue('Signalling');
        $this->SetValue('Signalling', $State);
        if ($State) {
            $invertedSignalling = $this->TriggerInvertedSignalling(!$State);
            $signalling = $this->TriggerSignalling($State);
        } else {
            $signalling = $this->TriggerSignalling($State);
            $invertedSignalling = $this->TriggerInvertedSignalling(!$State);
        }
        $result = true;
        if (!$signalling || !$invertedSignalling) {
            $result = false;
            //Revert value
            $this->SetValue('Signalling', $actualValue);
        }
        return $result;
    }

    /**
     * Updates the state.
     *
     * @return bool
     * false    = an error occurred
     * true     = successful
     *
     * @throws Exception
     */
    public function UpdateState(): bool
    {
        $this->SendDebug(__FUNCTION__, 'Die Methode wird ausgeführt. (' . microtime(true) . ')', 0);
        if ($this->CheckMaintenanceMode()) {
            return false;
        }
        if ($this->GetValue('NightMode')) {
            return false;
        }
        $state = $this->GetState();
        return $this->ToggleSignalling($state);
    }

    #################### Private

    /**
     * Gets the actual state.
     *
     * @return bool
     * false    = off
     * true     = on
     *
     * @throws Exception
     */
    private function GetState(): bool
    {
        $this->SendDebug(__FUNCTION__, 'Die Methode wird ausgeführt. (' . microtime(true) . ')', 0);
        $state = false;
        $variables = json_decode($this->ReadPropertyString('States'));
        if (!empty($variables)) {
            foreach ($variables as $variable) {
                if ($variable->Use) {
                    $id = $variable->ID;
                    if ($id != 0 && @IPS_ObjectExists($id)) {
                        $actualValue = intval(GetValue($id));
                        if ($actualValue == $variable->TriggerValue) {
                            $state = true;
                        }
                    }
                }
            }
        }
        $this->SendDebug(__FUNCTION__, 'Status: ' . json_encode($state), 0);
        return $state;
    }

    /**
     * Toggles the signalling off or on.
     *
     * @param bool $State
     * false    = off
     * true     = on
     *
     * @return bool
     * false    = an error occurred
     * true     = successful
     *
     * @throws Exception
     */
    private function TriggerSignalling(bool $State): bool
    {
        $this->SendDebug(__FUNCTION__, 'Die Methode wird ausgeführt. (' . microtime(true) . ')', 0);
        if ($this->CheckMaintenanceMode()) {
            return false;
        }
        $result = true;
        $id = $this->ReadPropertyInteger('Signalling');
        if ($id != 0 && @IPS_ObjectExists($id)) {
            IPS_Sleep($this->ReadPropertyInteger('SignallingSwitchingDelay'));
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

    /**
     * Toggles the inverted signalling off or on.
     *
     * @param bool $State
     * false    = off
     * true     = on
     *
     * @return bool
     * false    = an error occurred
     * true     = successful
     *
     * @throws Exception
     */
    private function TriggerInvertedSignalling(bool $State): bool
    {
        $this->SendDebug(__FUNCTION__, 'Die Methode wird ausgeführt. (' . microtime(true) . ')', 0);
        if ($this->CheckMaintenanceMode()) {
            return false;
        }
        $result = true;
        $id = $this->ReadPropertyInteger('InvertedSignalling');
        if ($id != 0 && @IPS_ObjectExists($id)) {
            IPS_Sleep($this->ReadPropertyInteger('InvertedSignallingSwitchingDelay'));
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