<?php

class VeiserKNXLichtmanager extends IPSModule
{
    public function Create()
    {
        parent::Create();
        $this->RegisterPropertyString('RoomName', '');
        $this->RegisterPropertyString('LightName', 'Licht');
        $this->RegisterPropertyBoolean('DryRun', true);
        $this->RegisterPropertyInteger('SwitchID', 0);
        $this->RegisterPropertyInteger('SwitchFeedbackID', 0);
        $this->RegisterPropertyBoolean('EnableDim', false);
        $this->RegisterPropertyInteger('DimID', 0);
        $this->RegisterPropertyInteger('DimFeedbackID', 0);
        $this->RegisterPropertyBoolean('EnableCCT', false);
        $this->RegisterPropertyInteger('CCTID', 0);
        $this->RegisterPropertyInteger('CCTFeedbackID', 0);
        $this->RegisterPropertyInteger('CCTMin', 2200);
        $this->RegisterPropertyInteger('CCTMax', 6500);
        $this->RegisterPropertyBoolean('EnableRGB', false);
        $this->RegisterPropertyInteger('RGBID', 0);
        $this->RegisterPropertyInteger('RGBFeedbackID', 0);

        $this->RegisterVariableBoolean('Power', 'Licht', '~Switch', 10);
        $this->EnableAction('Power');
        $this->RegisterVariableInteger('Brightness', 'Helligkeit', '~Intensity.100', 20);
        $this->EnableAction('Brightness');
        $this->RegisterVariableInteger('ColorTemperature', 'Farbtemperatur (K)', '', 30);
        $this->EnableAction('ColorTemperature');
        $this->RegisterVariableInteger('Color', 'RGB Farbe', '~HexColor', 40);
        $this->EnableAction('Color');
        $this->RegisterVariableString('Diagnostic', 'Status', '', 90);
    }

    public function ApplyChanges()
    {
        parent::ApplyChanges();

        // Instanzname entspricht dem eingetragenen Lichtgruppennamen.
        $lightName = trim($this->ReadPropertyString('LightName'));
        if ($lightName !== '' && IPS_GetName($this->InstanceID) !== $lightName) {
            IPS_SetName($this->InstanceID, $lightName);
        }

        $this->MaintainVariable('Power', 'Licht', VARIABLETYPE_BOOLEAN, '~Switch', 10, true);
        $this->MaintainVariable('Brightness', 'Helligkeit', VARIABLETYPE_INTEGER, '~Intensity.100', 20, $this->ReadPropertyBoolean('EnableDim'));
        $this->MaintainVariable('ColorTemperature', 'Farbtemperatur (K)', VARIABLETYPE_INTEGER, '', 30, $this->ReadPropertyBoolean('EnableCCT'));
        $this->MaintainVariable('Color', 'RGB Farbe', VARIABLETYPE_INTEGER, '~HexColor', 40, $this->ReadPropertyBoolean('EnableRGB'));
        $this->MaintainVariable('Diagnostic', 'Status', VARIABLETYPE_STRING, '', 90, true);

        // Native Symcon-Darstellungen: Voraussetzung fuer die kombinierte Lichtkachel.
        // Bestehende Variablen werden ebenfalls aktualisiert.
        $this->ApplyLightPresentations();
        IPS_SetHidden($this->GetIDForIdent('Diagnostic'), true);


        foreach (['SwitchID', 'SwitchFeedbackID', 'DimID', 'DimFeedbackID', 'CCTID', 'CCTFeedbackID', 'RGBID', 'RGBFeedbackID'] as $property) {
            $id = $this->ReadPropertyInteger($property);
            if ($id > 0 && @IPS_VariableExists($id)) {
                $this->RegisterMessage($id, VM_UPDATE);
            }
        }
        $this->SyncAll();
    }

    private function ApplyLightPresentations()
    {
        IPS_SetVariableCustomPresentation($this->GetIDForIdent('Power'), [
            'PRESENTATION' => VARIABLE_PRESENTATION_SWITCH,
            'USAGE_TYPE' => 0
        ]);

        if ($this->ReadPropertyBoolean('EnableDim')) {
            IPS_SetVariableCustomPresentation($this->GetIDForIdent('Brightness'), [
                'PRESENTATION' => VARIABLE_PRESENTATION_SLIDER,
                'MIN' => 0,
                'MAX' => 100,
                'STEP_SIZE' => 1,
                'USAGE_TYPE' => 2,
                'SUFFIX' => ' %'
            ]);
        }

        if ($this->ReadPropertyBoolean('EnableCCT')) {
            IPS_SetVariableCustomPresentation($this->GetIDForIdent('ColorTemperature'), [
                'PRESENTATION' => VARIABLE_PRESENTATION_SLIDER,
                'MIN' => $this->ReadPropertyInteger('CCTMin'),
                'MAX' => $this->ReadPropertyInteger('CCTMax'),
                'STEP_SIZE' => 1,
                'GRADIENT_TYPE' => 2,
                'USAGE_TYPE' => 1,
                'SUFFIX' => ' K'
            ]);
        }

        if ($this->ReadPropertyBoolean('EnableRGB')) {
            IPS_SetVariableCustomPresentation($this->GetIDForIdent('Color'), [
                'PRESENTATION' => VARIABLE_PRESENTATION_COLOR
            ]);
        }
    }

    public function RequestAction($Ident, $Value)
    {
        switch ($Ident) {
            case 'Power':
                $value = (bool)$Value;
                $this->SendKNX('SwitchID', $value);
                SetValueBoolean($this->GetIDForIdent('Power'), $value);
                break;
            case 'Brightness':
                if (!$this->ReadPropertyBoolean('EnableDim')) { throw new Exception('Dimmen ist deaktiviert'); }
                $value = max(0, min(100, (int)$Value));
                $this->SendKNX('DimID', $value);
                SetValueInteger($this->GetIDForIdent('Brightness'), $value);
                break;
            case 'ColorTemperature':
                if (!$this->ReadPropertyBoolean('EnableCCT')) { throw new Exception('Farbtemperatur ist deaktiviert'); }
                $min = $this->ReadPropertyInteger('CCTMin');
                $max = $this->ReadPropertyInteger('CCTMax');
                if ($min >= $max) { throw new Exception('Farbtemperatur-Grenzen ungueltig'); }
                $value = max($min, min($max, (int)$Value));
                $this->SendKNX('CCTID', $value);
                SetValueInteger($this->GetIDForIdent('ColorTemperature'), $value);
                break;
            case 'Color':
                if (!$this->ReadPropertyBoolean('EnableRGB')) { throw new Exception('RGB ist deaktiviert'); }
                $value = max(0, min(0xFFFFFF, (int)$Value));
                $this->SendKNX('RGBID', $value);
                SetValueInteger($this->GetIDForIdent('Color'), $value);
                break;
            default:
                throw new Exception('Unbekannte Aktion: ' . $Ident);
        }
    }

    private function SendKNX($property, $value)
    {
        if ($this->ReadPropertyBoolean('DryRun')) {
            $this->SetDiagnostic('TEST: ' . $property . ' = ' . json_encode($value));
            return;
        }
        $id = $this->ReadPropertyInteger($property);
        if ($id <= 0 || !@IPS_VariableExists($id)) {
            throw new Exception('KNX-Variable nicht konfiguriert: ' . $property);
        }
        IPS_RequestAction($id, $value);
        $this->SetDiagnostic('Befehl gesendet: ' . $property);
    }

    private function SetDiagnostic($message)
    {
        SetValueString($this->GetIDForIdent('Diagnostic'), $message);
    }

    private function ReadSource($primary, $fallback)
    {
        $id = $this->ReadPropertyInteger($primary);
        if ($id <= 0 || !@IPS_VariableExists($id)) {
            $id = $this->ReadPropertyInteger($fallback);
        }
        return ($id > 0 && @IPS_VariableExists($id)) ? GetValue($id) : null;
    }

    private function SyncAll()
    {
        $power = $this->ReadSource('SwitchFeedbackID', 'SwitchID');
        if ($power !== null) {
            SetValueBoolean($this->GetIDForIdent('Power'), (bool)$power);
        }
        if ($this->ReadPropertyBoolean('EnableDim')) {
            $dim = $this->ReadSource('DimFeedbackID', 'DimID');
            if ($dim !== null) {
                SetValueInteger($this->GetIDForIdent('Brightness'), max(0, min(100, (int)$dim)));
            }
        }
        if ($this->ReadPropertyBoolean('EnableCCT')) {
            $cct = $this->ReadSource('CCTFeedbackID', 'CCTID');
            if ($cct !== null) {
                SetValueInteger($this->GetIDForIdent('ColorTemperature'), (int)$cct);
            }
        }
        if ($this->ReadPropertyBoolean('EnableRGB')) {
            $rgb = $this->ReadSource('RGBFeedbackID', 'RGBID');
            if ($rgb !== null) {
                SetValueInteger($this->GetIDForIdent('Color'), (int)$rgb);
            }
        }
    }

    public function MessageSink($TimeStamp, $SenderID, $Message, $Data)
    {
        parent::MessageSink($TimeStamp, $SenderID, $Message, $Data);
        if ($Message === VM_UPDATE) {
            $this->SyncAll();
        }
    }
}
