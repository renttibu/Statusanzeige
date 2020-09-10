<?php

/** @noinspection PhpUnused */
/** @noinspection DuplicatedCode */

/*
 * @module      Statusanzeige 3 (HmIP-MP3P)
 *
 * @prefix      SA3
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
 *              Statusanzeige 3
 *              {6FC92B2D-827D-284D-06BE-5DC7A966607A}
 */

declare(strict_types=1);
include_once __DIR__ . '/helper/autoload.php';

class Statusanzeige3 extends IPSModule
{
    //Helper
    use SA3_control;
    use SA3_nightMode;

    //Constants
    private const STATUSANZEIGE_LIBRARY_GUID = '{0EA1B1BE-8B7C-9C22-3EC0-1F023AD8F542}';
    private const STATUSANZEIGE3_MODULE_GUID = '{6FC92B2D-827D-284D-06BE-5DC7A966607A}';
    private const DELAY_MILLISECONDS = 100;

    public function Create()
    {
        //Never delete this line!
        parent::Create();
        $this->RegisterProperties();
        $this->CreateProfiles();
        $this->RegisterVariables();
        $this->RegisterTimers();
        $this->RegisterAttributes();
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
        $this->SetOptions();
        if ($this->CheckMaintenanceMode()) {
            return;
        }
        $this->RegisterMessages();
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
                    //Light unit states
                    $scriptText = 'SA3_UpdateColor(' . $this->InstanceID . ');';
                    IPS_RunScriptText($scriptText);
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
        $module = IPS_GetModule(self::STATUSANZEIGE3_MODULE_GUID);
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
        $formData['elements'][0]['items'][7]['caption'] = "Präfix:\t\t\tSA3";
        //Trigger variables
        $variables = json_decode($this->ReadPropertyString('TriggerVariables'));
        if (!empty($variables)) {
            foreach ($variables as $variable) {
                $rowColor = '#C0FFC0'; //light green
                $use = $variable->Use;
                if (!$use) {
                    $rowColor = '';
                }
                $id = $variable->ID;
                if ($id == 0 || @!IPS_ObjectExists($id)) {
                    $rowColor = '#FFC0C0'; //light red
                }
                $formData['elements'][3]['items'][1]['values'][] = [
                    'Use'                                           => $use,
                    'Group'                                         => $variable->Group,
                    'Color'                                         => $variable->Color,
                    'ID'                                            => $id,
                    'TriggerValue'                                  => $variable->TriggerValue,
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
            $formData['actions'][0]['items'][0]['values'][] = [
                'SenderID'                                              => $senderID,
                'SenderName'                                            => $senderName,
                'MessageID'                                             => $messageID,
                'MessageDescription'                                    => $messageDescription,
                'rowColor'                                              => $rowColor];
        }
        //Attributes
        $lastColor = $this->ReadAttributeInteger('LastColor');
        $colorList = [0 => 'Aus', 1 => 'Blau', 2 => 'Grün', 3 => 'Türkis', 4 => 'Rot', 5 => 'Violett', 6 => 'Gelb', 7 => 'Weiß'];
        $colorName = 'Wert nicht vorhanden!';
        if (array_key_exists($lastColor, $colorList)) {
            $colorName = $colorList[$lastColor];
        }
        $formData['actions'][1]['items'][0]['caption'] = 'Letzte Farbe: ' . $lastColor . ', ' . $colorName;
        $lastBrightness = $this->ReadAttributeInteger('LastBrightness');
        $formData['actions'][1]['items'][1]['caption'] = 'Letzte Helligkeit: ' . $lastBrightness . ' %';
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
            case 'Color':
                $this->SetColor($Value);
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
        $this->RegisterPropertyBoolean('EnableColor', true);
        $this->RegisterPropertyBoolean('EnableBrightness', true);
        $this->RegisterPropertyBoolean('EnableNightMode', true);
        //Light unit
        $this->RegisterPropertyInteger('LightUnit', 0);
        $this->RegisterPropertyInteger('LightUnitSwitchingDelay', 0);
        $this->RegisterPropertyString('TriggerVariables', '[]');
        //Night mode
        $this->RegisterPropertyInteger('NightModeColor', -1);
        $this->RegisterPropertyInteger('NightModeBrightness', 0);
        $this->RegisterPropertyBoolean('UseAutomaticNightMode', false);
        $this->RegisterPropertyString('NightModeStartTime', '{"hour":22,"minute":0,"second":0}');
        $this->RegisterPropertyString('NightModeEndTime', '{"hour":6,"minute":0,"second":0}');
    }

    private function CreateProfiles(): void
    {
        //Color
        $profile = 'SA3.' . $this->InstanceID . '.Color';
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
                $profileName = 'SA3.' . $this->InstanceID . '.' . $profile;
                if (IPS_VariableProfileExists($profileName)) {
                    IPS_DeleteVariableProfile($profileName);
                }
            }
        }
    }

    private function RegisterVariables(): void
    {
        //Color
        $profile = 'SA3.' . $this->InstanceID . '.Color';
        $this->RegisterVariableInteger('Color', 'Farbe', $profile, 10);
        $this->EnableAction('Color');
        IPS_SetIcon($this->GetIDForIdent('Color'), 'Bulb');
        //Brightness
        $this->RegisterVariableInteger('Brightness', 'Helligkeit', '~Intensity.100', 20);
        $this->EnableAction('Brightness');
        //Night mode
        $this->RegisterVariableBoolean('NightMode', 'Nachtmodus', '~Switch', 30);
        $this->EnableAction('NightMode');
        IPS_SetIcon($this->GetIDForIdent('NightMode'), 'Moon');
    }

    private function SetOptions(): void
    {
        IPS_SetHidden($this->GetIDForIdent('Color'), !$this->ReadPropertyBoolean('EnableColor'));
        IPS_SetHidden($this->GetIDForIdent('Brightness'), !$this->ReadPropertyBoolean('EnableBrightness'));
        IPS_SetHidden($this->GetIDForIdent('NightMode'), !$this->ReadPropertyBoolean('EnableNightMode'));
    }

    private function RegisterAttributes(): void
    {
        $this->RegisterAttributeBoolean('NightModeTimer', false);
        $this->RegisterAttributeInteger('LastColor', 0);
        $this->RegisterAttributeInteger('LastBrightness', 0);
    }

    private function RegisterTimers(): void
    {
        $this->RegisterTimer('StartNightMode', 0, 'SA3_StartNightMode(' . $this->InstanceID . ');');
        $this->RegisterTimer('StopNightMode', 0, 'SA3_StopNightMode(' . $this->InstanceID . ',);');
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
        $variables = json_decode($this->ReadPropertyString('TriggerVariables'));
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

    private function UpdateParameter(): void
    {
        $lastColor = $this->ReadAttributeInteger('LastColor');
        $colorList = [0 => 'Aus', 1 => 'Blau', 2 => 'Grün', 3 => 'Türkis', 4 => 'Rot', 5 => 'Violett', 6 => 'Gelb', 7 => 'Weiß'];
        $colorName = 'Wert nicht vorhanden!';
        if (array_key_exists($lastColor, $colorList)) {
            $colorName = $colorList[$lastColor];
        }
        $caption = 'Letzte Farbe: ' . $lastColor . ', ' . $colorName;
        $this->UpdateFormField('AttributeLastColor', 'caption', $caption);
        $lastBrightness = $this->ReadAttributeInteger('LastBrightness');
        $caption = 'Letzte Helligkeit: ' . $lastBrightness . ' %';
        $this->UpdateFormField('AttributeLastBrightness', 'caption', $caption);
    }

    private function CheckMaintenanceMode(): bool
    {
        $result = false;
        $status = 102;
        if ($this->ReadPropertyBoolean('MaintenanceMode')) {
            $result = true;
            $status = 104;
            $message = 'Abbruch, der Wartungsmodus ist aktiv!';
            $this->SendDebug(__FUNCTION__, $message, 0);
            $this->LogMessage('ID ' . $this->InstanceID . ', ' . __FUNCTION__ . ', ' . $message, KL_WARNING);
        }
        $this->SetStatus($status);
        IPS_SetDisabled($this->InstanceID, $result);
        return $result;
    }
}