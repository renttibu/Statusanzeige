<?php

/*
 * @author      Ulrich Bittner
 * @copyright   (c) 2020, 2021
 * @license    	CC BY-NC-SA 4.0
 * @see         https://github.com/ubittner/Statusanzeige
 */

/** @noinspection DuplicatedCode */
/** @noinspection PhpUnused */

declare(strict_types=1);
include_once __DIR__ . '/helper/autoload.php';

class Statusanzeige2 extends IPSModule
{
    //Helper
    use SA2_backupRestore;
    use SA2_control;
    use SA2_nightMode;

    //Constants
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
                    //Upper light unit
                    $upperLightUnitStates = json_decode($this->ReadPropertyString('UpperLightUnitTriggerVariables'));
                    if (!empty($upperLightUnitStates)) {
                        foreach ($upperLightUnitStates as $upperLightUnitState) {
                            $id = $upperLightUnitState->ID;
                            if ($SenderID == $id) {
                                if ($id != 0 && @IPS_ObjectExists($id)) {
                                    $use = $upperLightUnitState->Use;
                                    if ($use) {
                                        $scriptText = 'SA2_UpdateLightUnit(' . $this->InstanceID . ', 0);';
                                        IPS_RunScriptText($scriptText);
                                    }
                                }
                            }
                        }
                    }
                    //Lower light unit
                    $lowerLightUnitStates = json_decode($this->ReadPropertyString('LowerLightUnitTriggerVariables'));
                    if (!empty($lowerLightUnitStates)) {
                        foreach ($lowerLightUnitStates as $lowerLightUnitState) {
                            $id = $lowerLightUnitState->ID;
                            if ($SenderID == $id) {
                                if ($id != 0 && @IPS_ObjectExists($id)) {
                                    $use = $lowerLightUnitState->Use;
                                    if ($use) {
                                        $scriptText = 'SA2_UpdateLightUnit(' . $this->InstanceID . ', 1);';
                                        IPS_RunScriptText($scriptText);
                                    }
                                }
                            }
                        }
                    }
                }
                break;

        }
    }

    public function GetConfigurationForm()
    {
        $formData = json_decode(file_get_contents(__DIR__ . '/form.json'), true);
        //Trigger variables
        $variables = json_decode($this->ReadPropertyString('UpperLightUnitTriggerVariables'));
        if (!empty($variables)) {
            foreach ($variables as $variable) {
                $rowColor = '#C0FFC0'; # light green
                $use = $variable->Use;
                if (!$use) {
                    $rowColor = '';
                }
                $id = $variable->ID;
                if ($id == 0 || @!IPS_ObjectExists($id)) {
                    $rowColor = '#FFC0C0'; # red
                }
                $formData['elements'][1]['items'][0]['values'][] = [
                    'Use'                                           => $use,
                    'Group'                                         => $variable->Group,
                    'Color'                                         => $variable->Color,
                    'ID'                                            => $id,
                    'TriggerValue'                                  => $variable->TriggerValue,
                    'rowColor'                                      => $rowColor];
            }
        }
        $variables = json_decode($this->ReadPropertyString('LowerLightUnitTriggerVariables'));
        if (!empty($variables)) {
            foreach ($variables as $variable) {
                $rowColor = '#C0FFC0'; # light green
                $use = $variable->Use;
                if (!$use) {
                    $rowColor = '';
                }
                $id = $variable->ID;
                if ($id == 0 || @!IPS_ObjectExists($id)) {
                    $rowColor = '#FFC0C0'; # red
                }
                $formData['elements'][2]['items'][0]['values'][] = [
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
            $rowColor = '#FFC0C0'; # red
            if (@IPS_ObjectExists($senderID)) {
                $senderName = IPS_GetName($senderID);
                $rowColor = ''; # '#C0FFC0' # light green
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
        //Attributes
        $colorList = [0 => 'Aus', 1 => 'Blau', 2 => 'Grün', 3 => 'Türkis', 4 => 'Rot', 5 => 'Violett', 6 => 'Gelb', 7 => 'Weiß'];
        $colorName = 'Wert nicht vorhanden!';
        $lastColor = $this->ReadAttributeInteger('UpperLightUnitLastColor');
        if (array_key_exists($lastColor, $colorList)) {
            $colorName = $colorList[$lastColor];
        }
        $formData['actions'][2]['items'][0]['caption'] = 'Obere Leuchteinheit - Letzte Farbe: ' . $lastColor . ', ' . $colorName;
        $lastColor = $this->ReadAttributeInteger('LowerLightUnitLastColor');
        if (array_key_exists($lastColor, $colorList)) {
            $colorName = $colorList[$lastColor];
        }
        $formData['actions'][2]['items'][1]['caption'] = 'Untere Leuchteinheit - Letzte Farbe: ' . $lastColor . ', ' . $colorName;
        $lastBrightness = $this->ReadAttributeInteger('LastBrightness');
        $formData['actions'][2]['items'][2]['caption'] = 'Letzte Helligkeit: ' . $lastBrightness . ' %';
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
            case 'UpperLightUnitColor':
                $this->SetColor(0, $Value);
                break;

            case 'LowerLightUnitColor':
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
    {   //Functions
        $this->RegisterPropertyBoolean('MaintenanceMode', false);
        $this->RegisterPropertyBoolean('EnableUpperLightUnitColor', true);
        $this->RegisterPropertyBoolean('EnableLowerLightUnitColor', true);
        $this->RegisterPropertyBoolean('EnableBrightness', true);
        $this->RegisterPropertyBoolean('EnableNightMode', true);
        //Upper light unit
        $this->RegisterPropertyInteger('UpperLightUnit', 0);
        $this->RegisterPropertyInteger('UpperLightUnitSwitchingDelay', 0);
        $this->RegisterPropertyString('UpperLightUnitTriggerVariables', '[]');
        //Lower light unit
        $this->RegisterPropertyInteger('LowerLightUnit', 0);
        $this->RegisterPropertyInteger('LowerLightUnitSwitchingDelay', 0);
        $this->RegisterPropertyString('LowerLightUnitTriggerVariables', '[]');
        //Night mode
        $this->RegisterPropertyInteger('NightModeColorUpperLightUnit', -1);
        $this->RegisterPropertyInteger('NightModeColorLowerLightUnit', -1);
        $this->RegisterPropertyInteger('NightModeBrightness', 0);
        $this->RegisterPropertyBoolean('UseAutomaticNightMode', false);
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

    private function RegisterVariables(): void
    {
        $profile = 'SA2.' . $this->InstanceID . '.Color';
        //Upper light unit
        $this->RegisterVariableInteger('UpperLightUnitColor', 'Obere Leuchteinheit', $profile, 10);
        $this->EnableAction('UpperLightUnitColor');
        IPS_SetIcon($this->GetIDForIdent('UpperLightUnitColor'), 'Bulb');
        // Lower light unit
        $this->RegisterVariableInteger('LowerLightUnitColor', 'Untere Leuchteinheit', $profile, 20);
        $this->EnableAction('LowerLightUnitColor');
        IPS_SetIcon($this->GetIDForIdent('LowerLightUnitColor'), 'Bulb');
        //Brightness
        $this->RegisterVariableInteger('Brightness', 'Helligkeit', '~Intensity.100', 30);
        $this->EnableAction('Brightness');
        //Night mode
        $this->RegisterVariableBoolean('NightMode', 'Nachtmodus', '~Switch', 40);
        $this->EnableAction('NightMode');
        IPS_SetIcon($this->GetIDForIdent('NightMode'), 'Moon');
    }

    private function SetOptions(): void
    {
        IPS_SetHidden($this->GetIDForIdent('UpperLightUnitColor'), !$this->ReadPropertyBoolean('EnableUpperLightUnitColor'));
        IPS_SetHidden($this->GetIDForIdent('LowerLightUnitColor'), !$this->ReadPropertyBoolean('EnableLowerLightUnitColor'));
        IPS_SetHidden($this->GetIDForIdent('Brightness'), !$this->ReadPropertyBoolean('EnableBrightness'));
        IPS_SetHidden($this->GetIDForIdent('NightMode'), !$this->ReadPropertyBoolean('EnableNightMode'));
    }

    private function RegisterAttributes(): void
    {
        $this->RegisterAttributeInteger('UpperLightUnitLastColor', 0);
        $this->RegisterAttributeInteger('LowerLightUnitLastColor', 0);
        $this->RegisterAttributeInteger('LastBrightness', 0);
    }

    private function RegisterTimers(): void
    {
        $this->RegisterTimer('StartNightMode', 0, 'SA2_StartNightMode(' . $this->InstanceID . ');');
        $this->RegisterTimer('StopNightMode', 0, 'SA2_StopNightMode(' . $this->InstanceID . ',);');
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
        $variables = json_decode($this->ReadPropertyString('UpperLightUnitTriggerVariables'));
        if (!empty($variables)) {
            foreach ($variables as $variable) {
                if ($variable->Use) {
                    if ($variable->ID != 0 && @IPS_ObjectExists($variable->ID)) {
                        $this->RegisterMessage($variable->ID, VM_UPDATE);
                    }
                }
            }
        }
        $variables = json_decode($this->ReadPropertyString('LowerLightUnitTriggerVariables'));
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
        $colorList = [0 => 'Aus', 1 => 'Blau', 2 => 'Grün', 3 => 'Türkis', 4 => 'Rot', 5 => 'Violett', 6 => 'Gelb', 7 => 'Weiß'];
        $colorName = 'Wert nicht vorhanden!';
        //Upper light unit color
        $lastColor = $this->ReadAttributeInteger('UpperLightUnitLastColor');
        if (array_key_exists($lastColor, $colorList)) {
            $colorName = $colorList[$lastColor];
        }
        $caption = 'Obere Leuchteinheit - Letzte Farbe: ' . $lastColor . ', ' . $colorName;
        $this->UpdateFormField('AttributeUpperLightUnitLastColor', 'caption', $caption);
        //Lower light unit color
        $lastColor = $this->ReadAttributeInteger('LowerLightUnitLastColor');
        if (array_key_exists($lastColor, $colorList)) {
            $colorName = $colorList[$lastColor];
        }
        $caption = 'Untere Leuchteinheit - Letzte Farbe: ' . $lastColor . ', ' . $colorName;
        $this->UpdateFormField('AttributeLowerLightUnitLastColor', 'caption', $caption);
        //Brightness
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