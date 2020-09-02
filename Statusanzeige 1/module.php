<?php

/** @noinspection PhpUnused */

/*
 * @module      Statusanzeige 1
 *
 * @prefix      SA1
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
 *              Statusanzeige 1
 *              {946453D8-5CCD-ABE8-B0C5-414CA4E0428A}
 */

declare(strict_types=1);
include_once __DIR__ . '/helper/autoload.php';

class Statusanzeige1 extends IPSModule
{
    //Helper
    use SA1_backupRestore;
    use SA1_control;
    use SA1_nightMode;

    //Constants
    private const STATUSANZEIGE_LIBRARY_GUID = '{0EA1B1BE-8B7C-9C22-3EC0-1F023AD8F542}';
    private const STATUSANZEIGE1_MODULE_GUID = '{946453D8-5CCD-ABE8-B0C5-414CA4E0428A}';
    private const DELAY_MILLISECONDS = 100;

    public function Create()
    {
        //Never delete this line!
        parent::Create();
        $this->RegisterProperties();
        $this->RegisterTimer('StartNightMode', 0, 'SA1_StartNightMode(' . $this->InstanceID . ');');
        $this->RegisterTimer('StopNightMode', 0, 'SA1_StopNightMode(' . $this->InstanceID . ',);');
        $this->RegisterAttributeBoolean('NightModeTimer', false);
    }

    public function Destroy()
    {
        //Never delete this line!
        parent::Destroy();
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
                    $scriptText = 'SA1_UpdateState(' . $this->InstanceID . ');';
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
        $module = IPS_GetModule(self::STATUSANZEIGE1_MODULE_GUID);
        $moduleInfo['name'] = $module['ModuleName'];
        $moduleInfo['version'] = $library['Version'] . '-' . $library['Build'];
        $moduleInfo['date'] = date('d.m.Y', $library['Date']);
        $moduleInfo['time'] = date('H:i', $library['Date']);
        $moduleInfo['developer'] = $library['Author'];
        $formData['elements'][0]['items'][1]['caption'] = "Instanz ID:\t\t" . $this->InstanceID;
        $formData['elements'][0]['items'][2]['caption'] = "Modul:\t\t\t" . $moduleInfo['name'];
        $formData['elements'][0]['items'][3]['caption'] = "Version:\t\t\t" . $moduleInfo['version'];
        $formData['elements'][0]['items'][4]['caption'] = "Datum:\t\t\t" . $moduleInfo['date'];
        $formData['elements'][0]['items'][5]['caption'] = "Uhrzeit:\t\t\t" . $moduleInfo['time'];
        $formData['elements'][0]['items'][6]['caption'] = "Entwickler:\t\t" . $moduleInfo['developer'];
        $formData['elements'][0]['items'][7]['caption'] = "Präfix:\t\t\tSA1";
        //States
        $states = json_decode($this->ReadPropertyString('States'));
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
                $formData['elements'][2]['items'][1]['values'][] = [
                    'Use'      => $use,
                    'ID'       => $id,
                    'rowColor' => $rowColor];
            }
        }
        //Signalling
        $signalling = json_decode($this->ReadPropertyString('Signalling'));
        if (!empty($signalling)) {
            foreach ($signalling as $signal) {
                $rowColor = '#C0FFC0'; //light green
                $use = $signal->Use;
                if (!$use) {
                    $rowColor = '';
                }
                $id = $signal->ID;
                if ($id == 0 || @!IPS_ObjectExists($id)) {
                    $rowColor = '#FFC0C0'; //light red
                }
                $formData['elements'][3]['items'][1]['values'][] = [
                    'Use'      => $use,
                    'ID'       => $id,
                    'Delay'    => $signal->Delay,
                    'rowColor' => $rowColor];
            }
        }
        //Inverted signalling
        $invertedSignalling = json_decode($this->ReadPropertyString('InvertedSignalling'));
        if (!empty($invertedSignalling)) {
            foreach ($invertedSignalling as $invertedSignal) {
                $rowColor = '#C0FFC0'; //light green
                $use = $invertedSignal->Use;
                if (!$use) {
                    $rowColor = '';
                }
                $id = $invertedSignal->ID;
                if ($id == 0 || @!IPS_ObjectExists($id)) {
                    $rowColor = '#FFC0C0'; //light red
                }
                $formData['elements'][3]['items'][3]['values'][] = [
                    'Use'      => $use,
                    'ID'       => $id,
                    'Delay'    => $invertedSignal->Delay,
                    'rowColor' => $rowColor];
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
                'SenderID'           => $senderID,
                'SenderName'         => $senderName,
                'MessageID'          => $messageID,
                'MessageDescription' => $messageDescription,
                'rowColor'           => $rowColor];
        }
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
            case 'Signalling':
                $this->ToggleSignalling($Value);
                break;

            case 'NightMode':
                $this->ToggleNightMode($Value);
                break;

        }
    }

    ###################### Private

    private function KernelReady(): void
    {
        $this->ApplyChanges();
    }

    private function RegisterProperties(): void
    {
        //Info
        $this->RegisterPropertyString('Note', '');
        $this->RegisterPropertyBoolean('MaintenanceMode', false);
        //Functions
        $this->RegisterPropertyBoolean('EnableSignalling', true);
        $this->RegisterPropertyBoolean('EnableState', false);
        $this->RegisterPropertyBoolean('EnableNightMode', false);
        //States
        $this->RegisterPropertyString('States', '[]');
        //Signalling
        $this->RegisterPropertyString('Signalling', '[]');
        $this->RegisterPropertyString('InvertedSignalling', '[]');
        $this->RegisterPropertyBoolean('UseScript', false);
        $this->RegisterPropertyInteger('Script', 0);
        //Night mode
        $this->RegisterPropertyBoolean('UseNightMode', false);
        $this->RegisterPropertyString('NightModeStartTime', '{"hour":22,"minute":0,"second":0}');
        $this->RegisterPropertyString('NightModeEndTime', '{"hour":6,"minute":0,"second":0}');
    }

    private function MaintainVariables(): void
    {
        //Signalling
        $keep = $this->ReadPropertyBoolean('EnableSignalling');
        $this->MaintainVariable('Signalling', 'Anzeige', 0, '~Switch', 10, $keep);
        if ($keep) {
            $this->EnableAction('Signalling');
            IPS_SetIcon($this->GetIDForIdent('Signalling'), 'Bulb');
        }
        //State
        $keep = $this->ReadPropertyBoolean('EnableState');
        $this->MaintainVariable('State', 'Status', 0, '~Switch', 20, $keep);
        if ($keep) {
            IPS_SetIcon($this->GetIDForIdent('State'), 'Information');
        }
        //Night mode
        $keep = $this->ReadPropertyBoolean('EnableNightMode');
        $this->MaintainVariable('NightMode', 'Nachtmodus', 0, '~Switch', 30, $keep);
        if ($keep) {
            $this->EnableAction('NightMode');
            IPS_SetIcon($this->GetIDForIdent('NightMode'), 'Moon');
        }
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
        $variables = json_decode($this->ReadPropertyString('States'));
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