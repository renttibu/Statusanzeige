<?php

/*
 * @author      Ulrich Bittner
 * @copyright   (c) 2020, 2021
 * @license     CC BY-NC-SA 4.0
 * @see         https://github.com/ubittner/Statusanzeige/tree/master/HmIP-MP3P
 */

/** @noinspection DuplicatedCode */
/** @noinspection PhpUnused */

declare(strict_types=1);
include_once __DIR__ . '/helper/autoload.php';

class StatusanzeigeHmIPMP3P extends IPSModule
{
    // Helper
    use SAHMIPMP3P_backupRestore;
    use SAHMIPMP3P_control;
    use SAHMIPMP3P_nightMode;

    // Constants
    private const DELAY_MILLISECONDS = 100;

    public function Create()
    {
        // Never delete this line!
        parent::Create();

        // Properties
        // Functions
        $this->RegisterPropertyBoolean('MaintenanceMode', false);
        $this->RegisterPropertyBoolean('LightUnitColor', true);
        $this->RegisterPropertyBoolean('LightUnitBrightness', true);
        $this->RegisterPropertyBoolean('EnableNightMode', true);
        // Light unit
        $this->RegisterPropertyInteger('LightUnit', 0);
        $this->RegisterPropertyInteger('LightUnitSwitchingDelay', 0);
        $this->RegisterPropertyString('TriggerVariables', '[]');
        // Night mode
        $this->RegisterPropertyBoolean('ChangeNightModeColor', true);
        $this->RegisterPropertyInteger('NightModeColor', 0);
        $this->RegisterPropertyBoolean('ChangeNightModeBrightness', false);
        $this->RegisterPropertyInteger('NightModeBrightness', 0);
        $this->RegisterPropertyBoolean('UseAutomaticNightMode', false);
        $this->RegisterPropertyString('NightModeStartTime', '{"hour":22,"minute":0,"second":0}');
        $this->RegisterPropertyString('NightModeEndTime', '{"hour":6,"minute":0,"second":0}');

        // Variables
        // Light unit color
        $profile = 'SAHMIPMP3P.' . $this->InstanceID . '.Color';
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
        $id = @$this->GetIDForIdent('LightUnitColor');
        $this->RegisterVariableInteger('LightUnitColor', 'Leuchteinheit', $profile, 10);
        $this->EnableAction('LightUnitColor');
        if ($id == false) {
            IPS_SetIcon($this->GetIDForIdent('LightUnitColor'), 'Bulb');
        }
        // Light unit brightness
        $this->RegisterVariableInteger('LightUnitBrightness', 'Helligkeit', '~Intensity.100', 20);
        $this->EnableAction('LightUnitBrightness');
        // Night mode
        $id = @$this->GetIDForIdent('NightMode');
        $this->RegisterVariableBoolean('NightMode', 'Nachtmodus', '~Switch', 50);
        $this->EnableAction('NightMode');
        if ($id == false) {
            IPS_SetIcon($this->GetIDForIdent('NightMode'), 'Moon');
        }

        // Attributes
        $this->RegisterAttributeInteger('LightUnitLastColor', 0);
        $this->RegisterAttributeInteger('LightUnitLastBrightness', 0);

        // Timers
        $this->RegisterTimer('StartNightMode', 0, 'SAHMIPMP3P_StartNightMode(' . $this->InstanceID . ');');
        $this->RegisterTimer('StopNightMode', 0, 'SAHMIPMP3P_StopNightMode(' . $this->InstanceID . ',);');
    }

    public function ApplyChanges()
    {
        // Wait until IP-Symcon is started
        $this->RegisterMessage(0, IPS_KERNELSTARTED);

        // Never delete this line!
        parent::ApplyChanges();

        // Check runlevel
        if (IPS_GetKernelRunlevel() != KR_READY) {
            return;
        }

        // Options
        IPS_SetHidden($this->GetIDForIdent('LightUnitColor'), !$this->ReadPropertyBoolean('LightUnitColor'));
        IPS_SetHidden($this->GetIDForIdent('LightUnitBrightness'), !$this->ReadPropertyBoolean('LightUnitBrightness'));
        IPS_SetHidden($this->GetIDForIdent('NightMode'), !$this->ReadPropertyBoolean('EnableNightMode'));

        // Validation
        if (!$this->ValidateConfiguration()) {
            return;
        }

        $this->RegisterMessages();
        $this->SetNightModeTimer();
        if (!$this->CheckNightModeTimer()) {
            $this->WriteAttributeInteger('LightUnitLastColor', 0);
            $this->WriteAttributeInteger('LightUnitLastBrightness', 0);
            $this->CheckActualStatus();
        }
    }

    public function Destroy()
    {
        // Never delete this line!
        parent::Destroy();

        // Delete profiles
        $profiles = ['Color'];
        foreach ($profiles as $profile) {
            $profileName = 'SAHMIPMP3P.' . $this->InstanceID . '.' . $profile;
            if (IPS_VariableProfileExists($profileName)) {
                IPS_DeleteVariableProfile($profileName);
            }
        }
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

                // $Data[0] = actual value
                // $Data[1] = value changed
                // $Data[2] = last value
                // $Data[3] = timestamp actual value
                // $Data[4] = timestamp value changed
                // $Data[5] = timestamp last value

                if ($Data[1]) {
                    $scriptText = 'SAHMIPMP3P_CheckActualStatus(' . $this->InstanceID . ');';
                    IPS_RunScriptText($scriptText);
                }
                break;

        }
    }

    public function GetConfigurationForm()
    {
        $formData = json_decode(file_get_contents(__DIR__ . '/form.json'), true);
        // Upper light
        $id = $this->ReadPropertyInteger('LightUnit');
        $enabled = false;
        if ($id != 0 && @IPS_ObjectExists($id)) {
            $enabled = true;
        }
        $formData['elements'][1]['items'][0] = [
            'type'  => 'RowLayout',
            'items' => [$formData['elements'][1]['items'][0]['items'][0] = [
                'type'    => 'SelectInstance',
                'name'    => 'LightUnit',
                'caption' => 'HmIP-MP3P Instanz, Kanal 6',
                'width'   => '600px',
            ],
                $formData['elements'][1]['items'][0]['items'][1] = [
                    'type'    => 'Label',
                    'caption' => ' ',
                    'visible' => $enabled
                ],
                $formData['elements'][1]['items'][0]['items'][2] = [
                    'type'     => 'OpenObjectButton',
                    'caption'  => 'ID ' . $id . ' konfigurieren',
                    'visible'  => $enabled,
                    'objectID' => $id
                ]
            ]
        ];
        $formData['elements'][1]['items'][1] = [
            'type'    => 'NumberSpinner',
            'name'    => 'LightUnitSwitchingDelay',
            'caption' => 'Schaltverzögerung',
            'minimum' => 0,
            'suffix'  => 'Millisekunden'
        ];
        // Trigger variables
        $variables = json_decode($this->ReadPropertyString('TriggerVariables'));
        if (!empty($variables)) {
            foreach ($variables as $variable) {
                $rowColor = '#C0FFC0'; # light green
                $use = $variable->Use;
                if (!$use) {
                    $rowColor = '';
                }
                $id = $variable->ID;
                if (@!IPS_ObjectExists($id)) {
                    $rowColor = '#FFC0C0'; # red
                }
                $formData['elements'][2]['items'][0]['values'][] = [
                    'Use'           => $use,
                    'Group'         => $variable->Group,
                    'ID'            => $id,
                    'TriggerType'   => $variable->TriggerType,
                    'TriggerValue'  => $variable->TriggerValue,
                    'Color'         => $variable->Color,
                    'Brightness'    => $variable->Brightness,
                    'rowColor'      => $rowColor];
            }
        }
        // Registered messages
        $messages = $this->GetMessageList();
        foreach ($messages as $senderID => $messageID) {
            $senderName = 'Objekt #' . $senderID . ' existiert nicht';
            $rowColor = '#FFC0C0'; # red
            if (@IPS_ObjectExists($senderID)) {
                $senderName = IPS_GetName($senderID);
                $rowColor = '#C0FFC0'; # light green
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
                'SenderID'              => $senderID,
                'SenderName'            => $senderName,
                'MessageID'             => $messageID,
                'MessageDescription'    => $messageDescription,
                'rowColor'              => $rowColor];
        }
        // Status
        $formData['status'][0] = [
            'code'    => 101,
            'icon'    => 'active',
            'caption' => 'Statusanzeige HmIP-MP3P wird erstellt',
        ];
        $formData['status'][1] = [
            'code'    => 102,
            'icon'    => 'active',
            'caption' => 'Statusanzeige HmIP-MP3P ist aktiv (ID ' . $this->InstanceID . ')',
        ];
        $formData['status'][2] = [
            'code'    => 103,
            'icon'    => 'active',
            'caption' => 'Statusanzeige HmIP-MP3P wird gelöscht (ID ' . $this->InstanceID . ')',
        ];
        $formData['status'][3] = [
            'code'    => 104,
            'icon'    => 'inactive',
            'caption' => 'Statusanzeige HmIP-MP3P ist inaktiv (ID ' . $this->InstanceID . ')',
        ];
        $formData['status'][4] = [
            'code'    => 200,
            'icon'    => 'inactive',
            'caption' => 'Es ist Fehler aufgetreten, weitere Informationen unter Meldungen, im Log oder Debug! (ID ' . $this->InstanceID . ')',
        ];
        return json_encode($formData);
    }

    public function ReloadConfiguration()
    {
        $this->ReloadForm();
    }

    public function EnableTriggerVariableConfigurationButton(int $ObjectID): void
    {
        $this->UpdateFormField('TriggerVariableConfigurationButton', 'caption', 'Variable ' . $ObjectID . ' Bearbeiten');
        $this->UpdateFormField('TriggerVariableConfigurationButton', 'visible', true);
        $this->UpdateFormField('TriggerVariableConfigurationButton', 'enabled', true);
        $this->UpdateFormField('TriggerVariableConfigurationButton', 'objectID', $ObjectID);
    }

    public function ShowVariableDetails(int $VariableID): void
    {
        if ($VariableID == 0 || !@IPS_ObjectExists($VariableID)) {
            return;
        }
        if ($VariableID != 0) {
            // Variable
            echo 'ID: ' . $VariableID . "\n";
            echo 'Name: ' . IPS_GetName($VariableID) . "\n";
            $variable = IPS_GetVariable($VariableID);
            if (!empty($variable)) {
                $variableType = $variable['VariableType'];
                switch ($variableType) {
                    case 0:
                        $variableTypeName = 'Boolean';
                        break;

                    case 1:
                        $variableTypeName = 'Integer';
                        break;

                    case 2:
                        $variableTypeName = 'Float';
                        break;

                    case 3:
                        $variableTypeName = 'String';
                        break;

                    default:
                        $variableTypeName = 'Unbekannt';
                }
                echo 'Variablentyp: ' . $variableTypeName . "\n";
            }
            // Profile
            $profile = @IPS_GetVariableProfile($variable['VariableProfile']);
            if (empty($profile)) {
                $profile = @IPS_GetVariableProfile($variable['VariableCustomProfile']);
            }
            if (!empty($profile)) {
                $profileType = $variable['VariableType'];
                switch ($profileType) {
                    case 0:
                        $profileTypeName = 'Boolean';
                        break;

                    case 1:
                        $profileTypeName = 'Integer';
                        break;

                    case 2:
                        $profileTypeName = 'Float';
                        break;

                    case 3:
                        $profileTypeName = 'String';
                        break;

                    default:
                        $profileTypeName = 'Unbekannt';
                }
                echo 'Profilname: ' . $profile['ProfileName'] . "\n";
                echo 'Profiltyp: ' . $profileTypeName . "\n\n";
            }
            if (!empty($variable)) {
                echo "\nVariable:\n";
                print_r($variable);
            }
            if (!empty($profile)) {
                echo "\nVariablenprofil:\n";
                print_r($profile);
            }
        }
    }

    #################### Request Action

    public function RequestAction($Ident, $Value)
    {
        switch ($Ident) {
            case 'LightUnitColor':
                $this->SetColor($Value);
                break;

            case 'LightUnitBrightness':
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

    private function RegisterMessages(): void
    {
        // Unregister
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
        // Register
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

    private function ValidateConfiguration(): bool
    {
        $result = true;
        $status = 102;
        // Maintenance mode
        $maintenance = $this->CheckMaintenanceMode();
        if ($maintenance) {
            $result = false;
            $status = 104;
        }
        IPS_SetDisabled($this->InstanceID, $maintenance);
        $this->SetStatus($status);
        return $result;
    }

    private function CheckMaintenanceMode(): bool
    {
        $result = $this->ReadPropertyBoolean('MaintenanceMode');
        if ($result) {
            $this->SendDebug(__FUNCTION__, 'Abbruch, der Wartungsmodus ist aktiv!', 0);
            $this->LogMessage('ID ' . $this->InstanceID . ', ' . __FUNCTION__ . ', Abbruch, der Wartungsmodus ist aktiv!', KL_WARNING);
        }
        return $result;
    }
}