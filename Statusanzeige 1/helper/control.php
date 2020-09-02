<?php

/** @noinspection DuplicatedCode */
/** @noinspection PhpUnused */

declare(strict_types=1);

trait SA1_control
{
    #################### Public

    public function ToggleSignalling(bool $State): void
    {
        $this->SendDebug(__FUNCTION__, 'Die Methode wird ausgeführt. (' . microtime(true) . ')', 0);
        if ($this->CheckMaintenanceMode()) {
            return;
        }
        $use = $this->ReadPropertyBoolean('EnableSignalling');
        if ($use) {
            $this->SetValue('Signalling', $State);
        }
        if ($State) {
            $this->TriggerInvertedSignalling(!$State);
            $this->TriggerSignalling($State);
        } else {
            $this->TriggerSignalling($State);
            $this->TriggerInvertedSignalling(!$State);
        }
    }

    public function UpdateState(): void
    {
        $this->SendDebug(__FUNCTION__, 'Die Methode wird ausgeführt. (' . microtime(true) . ')', 0);
        if ($this->CheckMaintenanceMode()) {
            return;
        }
        $state = $this->GetState();
        $use = $this->ReadPropertyBoolean('EnableState');
        if ($use) {
            $this->SetValue('State', $state);
        }
        $use = $this->ReadPropertyBoolean('EnableNightMode');
        if ($use) {
            if ($this->GetValue('NightMode')) {
                return;
            }
        }
        $nightModeTimer = $this->ReadAttributeBoolean('NightModeTimer');
        if ($nightModeTimer) {
            return;
        }
        $this->ToggleSignalling($state);
        $this->TriggerScript($state);
    }

    public function CreateScript(): void
    {
        $scriptID = IPS_CreateScript(0);
        IPS_SetName($scriptID, 'Statusanzeige (#' . $this->InstanceID . ')');
        $scriptContent = "<?php\n\n//Status wird übergeben in \$_IPS['State']\n\$state = \$_IPS['State'];\nIPS_LogMessage('Statusanzeige', 'ID: ' . \$_IPS['SELF'] . ', Status: ' . json_encode(\$state));";
        IPS_SetScriptContent($scriptID, $scriptContent);
        IPS_SetParent($scriptID, $this->InstanceID);
        IPS_SetPosition($scriptID, 100);
        IPS_SetHidden($scriptID, true);
        if ($scriptID != 0) {
            echo 'Skript wurde erfolgreich erstellt!';
        }
    }

    #################### Private

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
                        $actualState = (intval(GetValue($id)));
                        $triggerValue = $variable->TriggerValue;
                        switch ($triggerValue) {
                            case 0:
                                //0 or false
                                if ($actualState == $triggerValue) {
                                    $state = true;
                                }
                                break;

                            case 1:
                                //1 or true
                                if ($actualState == $triggerValue) {
                                    $state = true;
                                }
                                break;

                            case 2:
                                //2
                                if ($actualState == $triggerValue) {
                                    $state = true;
                                }
                                break;

                            case 3:
                                //0 or 1
                                if ($actualState == 0 || $actualState == 1) {
                                    $state = true;
                                }
                                break;

                            case 4:
                                //0 or 2
                                if ($actualState == 0 || $actualState == 2) {
                                    $state = true;
                                }
                                break;

                            case 5:
                                //1 or 2
                                if ($actualState == 1 || $actualState == 2) {
                                    $state = true;
                                }
                                break;
                        }
                    }
                }
            }
        }
        return $state;
    }

    private function TriggerSignalling(bool $State): void
    {
        $this->SendDebug(__FUNCTION__, 'Die Methode wird ausgeführt. (' . microtime(true) . ')', 0);
        $variables = json_decode($this->ReadPropertyString('Signalling'));
        if (!empty($variables)) {
            foreach ($variables as $variable) {
                if ($variable->Use) {
                    $id = $variable->ID;
                    if ($id != 0 && @IPS_ObjectExists($id)) {
                        $actualValue = boolval(GetValue($id));
                        if ($actualValue == $State) {
                            continue;
                        }
                        IPS_Sleep($variable->Delay);
                        $toggle = @RequestAction($id, $State);
                        if (!$toggle) {
                            IPS_Sleep(self::DELAY_MILLISECONDS);
                            $toggleAgain = @RequestAction($id, $State);
                            if (!$toggleAgain) {
                                $this->SendDebug(__FUNCTION__, 'Variable ' . $id . ' konnte nicht geschaltet werden!', 0);
                                $this->LogMessage('Instanz ' . $this->InstanceID . ', Variable ' . $id . ' konnte nicht geschaltet werden!', KL_ERROR);
                            }
                        }
                    }
                }
            }
        }
    }

    private function TriggerInvertedSignalling(bool $State): void
    {
        $this->SendDebug(__FUNCTION__, 'Die Methode wird ausgeführt. (' . microtime(true) . ')', 0);
        $variables = json_decode($this->ReadPropertyString('InvertedSignalling'));
        if (!empty($variables)) {
            foreach ($variables as $variable) {
                if ($variable->Use) {
                    $id = $variable->ID;
                    if ($id != 0 && @IPS_ObjectExists($id)) {
                        $actualValue = boolval(GetValue($id));
                        if ($actualValue == $State) {
                            continue;
                        }
                        IPS_Sleep($variable->Delay);
                        $toggle = @RequestAction($id, $State);
                        if (!$toggle) {
                            IPS_Sleep(self::DELAY_MILLISECONDS);
                            $toggleAgain = @RequestAction($id, $State);
                            if (!$toggleAgain) {
                                $this->SendDebug(__FUNCTION__, 'Variable ' . $id . ' konnte nicht geschaltet werden!', 0);
                                $this->LogMessage('Instanz ' . $this->InstanceID . ', Variable ' . $id . ' konnte nicht geschaltet werden!', KL_ERROR);
                            }
                        }
                    }
                }
            }
        }
    }

    private function TriggerScript(bool $State): void
    {
        $this->SendDebug(__FUNCTION__, 'Die Methode wird ausgeführt. (' . microtime(true) . ')', 0);
        $use = $this->ReadPropertyBoolean('UseScript');
        if (!$use) {
            return;
        }
        $id = $this->ReadPropertyInteger('Script');
        if ($id != 0 && @IPS_ObjectExists($id)) {
            IPS_RunScriptEx($id, ['State' => $State]);
        }
    }
}