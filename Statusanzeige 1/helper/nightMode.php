<?php

/** @noinspection PhpUndefinedMethodInspection */
/** @noinspection PhpUnused */

declare(strict_types=1);

trait SA1_nightMode
{
    public function ToggleNightMode(bool $State): void
    {
        $this->SendDebug(__FUNCTION__, 'Die Methode wird ausgeführt. (' . microtime(true) . ')', 0);
        if ($this->CheckMaintenanceMode()) {
            return;
        }
        $use = $this->ReadPropertyBoolean('EnableNightMode');
        if ($use) {
            $this->SetValue('NightMode', $State);
        }
        if (!$State) {
            $this->UpdateState();
        }
        if ($State) {
            $use = $this->ReadPropertyBoolean('EnableSignalling');
            if ($use) {
                $this->SetValue('Signalling', false);
            }
            $this->TriggerSignalling(false);
            $this->TriggerInvertedSignalling(false);
        }
    }

    public function StartNightMode(): void
    {
        $this->WriteAttributeBoolean('NightModeTimer', true);
        $this->ToggleNightMode(true);
        $this->SetNightModeTimer();
    }

    public function StopNightMode(): void
    {
        $this->WriteAttributeBoolean('NightModeTimer', false);
        $this->ToggleNightMode(false);
        $this->SetNightModeTimer();
    }

    public function ShowNightModeState(): void
    {
        $state = 'Aus';
        $use = $this->ReadPropertyBoolean('EnableNightMode');
        if ($use) {
            if ($this->GetValue('NightMode')) {
                $state = 'An';
            }
        }
        $nightModeTimer = $this->ReadAttributeBoolean('NightModeTimer');
        if ($nightModeTimer) {
            $state = 'An';
        }
        echo 'Nachtmodus: ' . $state;
    }

    #################### Private

    private function SetNightModeTimer(): void
    {
        $use = $this->ReadPropertyBoolean('UseNightMode');
        //Start
        $milliseconds = 0;
        if ($use) {
            $milliseconds = $this->GetInterval('NightModeStartTime');
        }
        $this->SetTimerInterval('StartNightMode', $milliseconds);
        // End
        $milliseconds = 0;
        if ($use) {
            $milliseconds = $this->GetInterval('NightModeEndTime');
        }
        $this->SetTimerInterval('StopNightMode', $milliseconds);
    }

    private function GetInterval(string $TimerName): int
    {
        $timer = json_decode($this->ReadPropertyString($TimerName));
        $now = time();
        $hour = $timer->hour;
        $minute = $timer->minute;
        $second = $timer->second;
        $definedTime = $hour . ':' . $minute . ':' . $second;
        if (time() >= strtotime($definedTime)) {
            $timestamp = mktime($hour, $minute, $second, (int) date('n'), (int) date('j') + 1, (int) date('Y'));
        } else {
            $timestamp = mktime($hour, $minute, $second, (int) date('n'), (int) date('j'), (int) date('Y'));
        }
        return ($timestamp - $now) * 1000;
    }

    private function CheckNightModeTimer(): void
    {
        $start = $this->GetTimerInterval('StartNightMode');
        $stop = $this->GetTimerInterval('StopNightMode');
        if ($start > $stop) {
            $this->WriteAttributeBoolean('NightModeTimer', true);
            $this->ToggleNightMode(true);
        } else {
            $this->WriteAttributeBoolean('NightModeTimer', false);
            $this->ToggleNightMode(false);
        }
    }
}