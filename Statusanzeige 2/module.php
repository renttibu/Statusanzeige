<?php

/** @noinspection PhpUnused */

/*
 * @module      Statusanzeige 2 (HmIP-BSL)
 *
 * @prefix      SA2
 *
 * @file        module.php
 *
 * @author      Ulrich Bittner
 * @copyright   (c) 2020
 * @license    	CC BY-NC-SA 4.0
 *              https://creativecommons.org/licenses/by-nc-sa/4.0/
 *
 * @see         https://github.com/ubittner/Statusanzeige
 *
 * @guids       Library
 *              {0EA1B1BE-8B7C-9C22-3EC0-1F023AD8F542}
 *
 *              Statusanzeige 2
 *              {DA434C88-0460-59A2-5048-0C1724AD9698}
 */

declare(strict_types=1);
include_once __DIR__ . '/helper/autoload.php';

class Statusanzeige2 extends IPSModule
{
    //Helper
    use SA2_backupRestore;
    use SA2_control;
    use SA2_nightMode;

    //Constants
    private const STATUSANZEIGE_LIBRARY_GUID = '{0EA1B1BE-8B7C-9C22-3EC0-1F023AD8F542}';
    private const STATUSANZEIGE2_MODULE_GUID = '{DA434C88-0460-59A2-5048-0C1724AD9698}';
    private const DELAY_MILLISECONDS = 100;

    public function Create()
    {
        //Never delete this line!
        parent::Create();
        $this->RegisterProperties();
        $this->CreateProfiles();
        $this->RegisterTimer('StartNightMode', 0, 'SA2_StartNightMode(' . $this->InstanceID . ');');
        $this->RegisterTimer('StopNightMode', 0, 'SA2_StopNightMode(' . $this->InstanceID . ',);');
        $this->RegisterAttributeBoolean('NightModeTimer', false);
    }

    public function Destroy()
    {
        //Never delete this line!
        parent::Destroy();
        $this->DeleteProfiles();
    }

    public function ApplyChanges()
    {
        //Wait until IP-Symcon is started
        $this->RegisterMessage(0, IPS_KERNELSTARTED);
        //Never delete this line!
        parent::ApplyChanges();
        //Check runlevel
        if (IPS_GetKernelRunlevel() != KR_READY) {
            return;
        }
        $this->MaintainVariables();
        $this->SetOptions();
        $this->RegisterMessages();
        if ($this->CheckMaintenanceMode()) {
            return;
        }
        $this->SetNightModeTimer();
        $this->CheckNightModeTimer();
    }

    public function MessageSink($TimeStamp, $SenderID, $Message, $Data)
    {
        $this->SendDebug(__FUNCTION__, $TimeStamp . ', SenderID: ' . $SenderID . ', Message: ' . $Message . ', Data: ' . print_r($Data, true), 0);
        if (!empty($Data)) {
            foreach ($Data as $key => $value) {
                $this->SendDebug(__FUNCTION__, 'Data[' . $key . '] = ' . json_encode($value), 0);
            }
        }
        switch ($Message) {
            case IPS_KERNELSTARTED:
                $this->KernelReady();
                break;

            case VM_UPDATE:
                //$Data[0] = actual value
                //$Data[1] = value changed
                //$Data[2] = last value
                //$Data[3] = timestamp actual value
                //$Data[4] = timestamp value changed
                //$Data[5] = timestamp last value
                if ($this->CheckMaintenanceMode()) {
                    return;
                }
                //Trigger action
                if ($Data[1]) {
                    //Upper light unit states
                    $upperLightUnitStates = json_decode($this->ReadPropertyString('UpperLightUnitStates'), true);
                    if (!empty($upperLightUnitStates)) {
                        if (array_search($SenderID, array_column($upperLightUnitStates, 'ID')) !== false) {
                            $scriptText = 'SA2_UpdateUpperLightUnit(' . $this->InstanceID . ');';
                            IPS_RunScriptText($scriptText);
                        }
                    }
                    //Lower light unit states
                    $lowerLightUnitStates = json_decode($this->ReadPropertyString('LowerLightUnitStates'), true);
                    if (!empty($lowerLightUnitStates)) {
                        if (array_search($SenderID, array_column($lowerLightUnitStates, 'ID')) !== false) {
                            $scriptText = 'SA2_UpdateLowerLightUnit(' . $this->InstanceID . ');';
                            IPS_RunScriptText($scriptText);
                        }
                    }
                }
                break;

        }
    }

    public function GetConfigurationForm()
    {
        $formData = json_decode(file_get_contents(__DIR__ . '/form.json'), true);
        //Info
        $moduleInfo = [];
        $library = IPS_GetLibrary(self::STATUSANZEIGE_LIBRARY_GUID);
        $module = IPS_GetModule(self::STATUSANZEIGE2_MODULE_GUID);
        $moduleInfo['name'] = $module['ModuleName'];
        $moduleInfo['version'] = $library['Version'] . '-' . $library['Build'];
        $moduleInfo['date'] = date('d.m.Y', $library['Date']);
        $moduleInfo['time'] = date('H:i', $library['Date']);
        $moduleInfo['developer'] = $library['Author'];
        $formData['elements'][0]['items'][1]['caption'] = "ID:\t\t\t\t" . $this->InstanceID;
        $formData['elements'][0]['items'][2]['caption'] = "Modul:\t\t\t" . $moduleInfo['name'];
        $formData['elements'][0]['items'][3]['caption'] = "Version:\t\t\t" . $moduleInfo['version'];
        $formData['elements'][0]['items'][4]['caption'] = "Datum:\t\t\t" . $moduleInfo['date'];
        $formData['elements'][0]['items'][5]['caption'] = "Uhrzeit:\t\t\t" . $moduleInfo['time'];
        $formData['elements'][0]['items'][6]['caption'] = "Entwickler:\t\t" . $moduleInfo['developer'];
        $formData['elements'][0]['items'][7]['caption'] = "Präfix:\t\t\tSA2";
        //Upper light unit
        $states = json_decode($this->ReadPropertyString('UpperLightUnitStates'));
        if (!empty($states)) {
            foreach ($states as $state) {
                $rowColor = '#C0FFC0'; //light green
                $use = $state->Use;
                if (!$use) {
                    $rowColor = '';
                }
                $id = $state->ID;
                if ($id == 0 || @!IPS_ObjectExists($id)) {
                    $rowColor = '#FFC0C0'; //light red
                }
                $formData['elements'][2]['items'][11]['values'][] = [
                    'Use'                                           => $use,
                    'ID'                                            => $id,
                    'TriggerValueState0'                            => $state->TriggerValueState0,
                    'TriggerValueState1'                            => $state->TriggerValueState1,
                    'TriggerValueState2'                            => $state->TriggerValueState2,
                    'rowColor'                                      => $rowColor];
            }
        }
        //Lower light unit
        $states = json_decode($this->ReadPropertyString('LowerLightUnitStates'));
        if (!empty($states)) {
            foreach ($states as $state) {
                $rowColor = '#C0FFC0'; //light green
                $use = $state->Use;
                if (!$use) {
                    $rowColor = '';
                }
                $id = $state->ID;
                if ($id == 0 || @!IPS_ObjectExists($id)) {
                    $rowColor = '#FFC0C0'; //light red
                }
                $formData['elements'][3]['items'][11]['values'][] = [
                    'Use'                                           => $use,
                    'ID'                                            => $id,
                    'TriggerValueState0'                            => $state->TriggerValueState0,
                    'TriggerValueState1'                            => $state->TriggerValueState1,
                    'TriggerValueState2'                            => $state->TriggerValueState2,
                    'rowColor'                                      => $rowColor];
            }
        }
        //Registered messages
        $messages = $this->GetMessageList();
        foreach ($messages as $senderID => $messageID) {
            $senderName = 'Objekt #' . $senderID . ' existiert nicht';
            $rowColor = '#FFC0C0'; //light red
            if (@IPS_ObjectExists($senderID)) {
                $senderName = IPS_GetName($senderID);
                $rowColor = '#C0FFC0'; //light green
            }
            switch ($messageID) {
                case [10001]:
                    $messageDescription = 'IPS_KERNELSTARTED';
                    break;

                case [10603]:
                    $messageDescription = 'VM_UPDATE';
                    break;

                default:
                    $messageDescription = 'keine Bezeichnung';
            }
            $formData['actions'][1]['items'][0]['values'][] = [
                'SenderID'                                              => $senderID,
                'SenderName'                                            => $senderName,
                'MessageID'                                             => $messageID,
                'MessageDescription'                                    => $messageDescription,
                'rowColor'                                              => $rowColor];
        }
        //Night mode
        $nightModeState = 'Aus';
        $use = $this->ReadPropertyBoolean('EnableNightMode');
        if ($use) {
            if ($this->GetValue('NightMode')) {
                $nightModeState = 'An';
            }
        }
        $nightModeTimer = $this->ReadAttributeBoolean('NightModeTimer');
        if ($nightModeTimer) {
            $nightModeState = 'An';
        }
        $formData['actions'][2]['items'][0]['caption'] = $nightModeState;
        return json_encode($formData);
    }

    public function ReloadConfiguration()
    {
        $this->ReloadForm();
    }

    #################### Request Action

    public function RequestAction($Ident, $Value)
    {
        switch ($Ident) {
            case 'UpperLightUnit':
                $this->SetColor(0, $Value);
                break;

            case 'LowerLightUnit':
                $this->SetColor(1, $Value);
                break;

            case 'Brightness':
                $this->SetBrightness($Value);
                break;

            case 'NightMode':
                $this->ToggleNightMode($Value);
                break;

        }
    }

    #################### Private

    private function KernelReady()
    {
        $this->ApplyChanges();
    }

    private function RegisterProperties(): void
    {
        //Info
        $this->RegisterPropertyString('Note', '');
        $this->RegisterPropertyBoolean('MaintenanceMode', false);
        //Functions
        $this->RegisterPropertyBoolean('EnableUpperLightUnit', true);
        $this->RegisterPropertyBoolean('EnableLowerLightUnit', true);
        $this->RegisterPropertyBoolean('EnableBrightness', true);
        $this->RegisterPropertyBoolean('EnableNightMode', false);
        //Upper light unit
        $this->RegisterPropertyInteger('UpperLightUnit', 0);
        $this->RegisterPropertyInteger('UpperLightUnitSwitchingDelay', 0);
        $this->RegisterPropertyInteger('UpperLightUnitColorState0', -1);
        $this->RegisterPropertyInteger('UpperLightUnitColorState1', -1);
        $this->RegisterPropertyInteger('UpperLightUnitColorState2', -1);
        $this->RegisterPropertyString('UpperLightUnitStates', '[]');
        // Lower light unit
        $this->RegisterPropertyInteger('LowerLightUnit', 0);
        $this->RegisterPropertyInteger('LowerLightUnitSwitchingDelay', 0);
        $this->RegisterPropertyInteger('LowerLightUnitColorState0', -1);
        $this->RegisterPropertyInteger('LowerLightUnitColorState1', -1);
        $this->RegisterPropertyInteger('LowerLightUnitColorState2', -1);
        $this->RegisterPropertyString('LowerLightUnitStates', '[]');
        //Night mode
        $this->RegisterPropertyBoolean('UseNightMode', false);
        $this->RegisterPropertyString('NightModeStartTime', '{"hour":22,"minute":0,"second":0}');
        $this->RegisterPropertyString('NightModeEndTime', '{"hour":6,"minute":0,"second":0}');
    }

    private function CreateProfiles(): void
    {
        //Color
        $profile = 'SA2.' . $this->InstanceID . '.Color';
        if (!IPS_VariableProfileExists($profile)) {
            IPS_CreateVariableProfile($profile, 1);
        }
        IPS_SetVariableProfileIcon($profile, '');
        IPS_SetVariableProfileAssociation($profile, 0, 'Aus', 'Bulb', 0);
        IPS_SetVariableProfileAssociation($profile, 1, 'Blau', 'Bulb', 0x0000FF);
        IPS_SetVariableProfileAssociation($profile, 2, 'Grün', 'Bulb', 0x00FF00);
        IPS_SetVariableProfileAssociation($profile, 3, 'Türkis', 'Bulb', 0x01DFD7);
        IPS_SetVariableProfileAssociation($profile, 4, 'Rot', 'Bulb', 0xFF0000);
        IPS_SetVariableProfileAssociation($profile, 5, 'Violett', 'Bulb', 0xB40486);
        IPS_SetVariableProfileAssociation($profile, 6, 'Gelb', 'Bulb', 0xFFFF00);
        IPS_SetVariableProfileAssociation($profile, 7, 'Weiß', 'Bulb', 0xFFFFFF);
    }

    private function DeleteProfiles(): void
    {
        $profiles = ['Color'];
        if (!empty($profiles)) {
            foreach ($profiles as $profile) {
                $profileName = 'SA2.' . $this->InstanceID . '.' . $profile;
                if (IPS_VariableProfileExists($profileName)) {
                    IPS_DeleteVariableProfile($profileName);
                }
            }
        }
    }

    private function MaintainVariables(): void
    {
        //Upper light unit
        $profile = 'SA2.' . $this->InstanceID . '.Color';
        $this->MaintainVariable('UpperLightUnit', 'Obere Leuchteinheit', 1, $profile, 10, true);
        $this->EnableAction('UpperLightUnit');
        IPS_SetIcon($this->GetIDForIdent('UpperLightUnit'), 'Bulb');
        //Lower light unit
        $profile = 'SA2.' . $this->InstanceID . '.Color';
        $this->MaintainVariable('LowerLightUnit', 'Untere Leuchteinheit', 1, $profile, 20, true);
        $this->EnableAction('LowerLightUnit');
        IPS_SetIcon($this->GetIDForIdent('LowerLightUnit'), 'Bulb');
        //Brightness
        $this->MaintainVariable('Brightness', 'Helligkeit', 1, '~Intensity.100', 30, true);
        $this->EnableAction('Brightness');
        //Night mode
        $keep = $this->ReadPropertyBoolean('EnableNightMode');
        $this->MaintainVariable('NightMode', 'Nachtmodus', 0, '~Switch', 40, $keep);
        if ($keep) {
            $this->EnableAction('NightMode');
            IPS_SetIcon($this->GetIDForIdent('NightMode'), 'Moon');
        }
    }

    private function SetOptions(): void
    {
        IPS_SetHidden($this->GetIDForIdent('UpperLightUnit'), !$this->ReadPropertyBoolean('EnableUpperLightUnit'));
        IPS_SetHidden($this->GetIDForIdent('LowerLightUnit'), !$this->ReadPropertyBoolean('EnableLowerLightUnit'));
        IPS_SetHidden($this->GetIDForIdent('Brightness'), !$this->ReadPropertyBoolean('EnableBrightness'));
    }

    private function CheckMaintenanceMode(): bool
    {
        $result = false;
        $status = 102;
        if ($this->ReadPropertyBoolean('MaintenanceMode')) {
            $result = true;
            $status = 104;
            $this->SendDebug(__FUNCTION__, 'Abbruch, der Wartungsmodus ist aktiv!', 0);
            $this->LogMessage('ID ' . $this->InstanceID . ', ' . __FUNCTION__ . ', Abbruch, der Wartungsmodus ist aktiv!', KL_WARNING);
        }
        $this->SetStatus($status);
        IPS_SetDisabled($this->InstanceID, $result);
        return $result;
    }

    private function RegisterMessages(): void
    {
        //Unregister
        $messages = $this->GetMessageList();
        if (!empty($messages)) {
            foreach ($messages as $id => $message) {
                foreach ($message as $messageType) {
                    if ($messageType == VM_UPDATE) {
                        $this->UnregisterMessage($id, VM_UPDATE);
                    }
                }
            }
        }
        //Register
        $variables = json_decode($this->ReadPropertyString('UpperLightUnitStates'));
        if (!empty($variables)) {
            foreach ($variables as $variable) {
                if ($variable->Use) {
                    if ($variable->ID != 0 && @IPS_ObjectExists($variable->ID)) {
                        $this->RegisterMessage($variable->ID, VM_UPDATE);
                    }
                }
            }
        }
        $variables = json_decode($this->ReadPropertyString('LowerLightUnitStates'));
        if (!empty($variables)) {
            foreach ($variables as $variable) {
                if ($variable->Use) {
                    if ($variable->ID != 0 && @IPS_ObjectExists($variable->ID)) {
                        $this->RegisterMessage($variable->ID, VM_UPDATE);
                    }
                }
            }
        }
    }
}