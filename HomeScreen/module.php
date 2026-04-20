<?php

declare(strict_types=1);

class HomeScreen extends IPSModuleStrict
{
    public function Create(): void
    {
        parent::Create();

        $this->RegisterPropertyString('Raeume', '[]');

        $this->RegisterVariableString('Uebersicht', 'Raumübersicht', '~HTMLBox');

        $this->RegisterTimer('RefreshTimer', 0, 'HomeScreen_Update($_IPS[\'TARGET\']);');
    }

    public function ApplyChanges(): void
    {
        parent::ApplyChanges();

        // Alle bestehenden Nachrichten abmelden
        foreach ($this->GetMessageList() as $senderID => $messages) {
            foreach ($messages as $message) {
                $this->UnregisterMessage($senderID, $message);
            }
        }

        // Auf Änderungen aller konfigurierten Variablen reagieren
        $raeume = json_decode($this->ReadPropertyString('Raeume'), true) ?? [];
        $varIDs = [];
        foreach ($raeume as $raum) {
            foreach (['LichtID', 'FensterID', 'TempID', 'HumID', 'CO2ID'] as $key) {
                $id = (int)($raum[$key] ?? 0);
                if ($id > 0 && IPS_VariableExists($id)) {
                    $varIDs[] = $id;
                }
            }
        }
        foreach (array_unique($varIDs) as $id) {
            $this->RegisterMessage($id, VM_UPDATE);
        }

        // Alle 5 Minuten automatisch aktualisieren
        $this->SetTimerInterval('RefreshTimer', 5 * 60 * 1000);

        $this->Update();
    }

    public function MessageSink(int $TimeStamp, int $SenderID, int $Message, array $Data): void
    {
        if ($Message === VM_UPDATE) {
            $this->Update();
        }
    }

    public function Update(): void
    {
        $raeume = json_decode($this->ReadPropertyString('Raeume'), true) ?? [];
        $this->SetValue('Uebersicht', $this->BuildHTML($raeume));
    }

    public function GetConfigurationForm(): string
    {
        return json_encode([
            'elements' => [
                [
                    'type'    => 'List',
                    'name'    => 'Raeume',
                    'caption' => 'Räume',
                    'add'     => true,
                    'delete'  => true,
                    'columns' => [
                        [
                            'caption' => 'Raumname',
                            'name'    => 'Name',
                            'width'   => '160px',
                            'add'     => 'Neuer Raum',
                            'edit'    => ['type' => 'ValidationTextBox'],
                        ],
                        [
                            'caption' => 'Licht (Boolean: Ein = true)',
                            'name'    => 'LichtID',
                            'width'   => '220px',
                            'add'     => 0,
                            'edit'    => ['type' => 'SelectVariable'],
                        ],
                        [
                            'caption' => 'Fenster (Boolean: Offen = true)',
                            'name'    => 'FensterID',
                            'width'   => '240px',
                            'add'     => 0,
                            'edit'    => ['type' => 'SelectVariable'],
                        ],
                        [
                            'caption' => 'Temperatur (°C)',
                            'name'    => 'TempID',
                            'width'   => '180px',
                            'add'     => 0,
                            'edit'    => ['type' => 'SelectVariable'],
                        ],
                        [
                            'caption' => 'Luftfeuchtigkeit (%)',
                            'name'    => 'HumID',
                            'width'   => '180px',
                            'add'     => 0,
                            'edit'    => ['type' => 'SelectVariable'],
                        ],
                        [
                            'caption' => 'CO₂ (ppm)',
                            'name'    => 'CO2ID',
                            'width'   => '160px',
                            'add'     => 0,
                            'edit'    => ['type' => 'SelectVariable'],
                        ],
                    ],
                ],
            ],
            'actions' => [
                [
                    'type'    => 'Button',
                    'caption' => 'Jetzt aktualisieren',
                    'onClick' => 'HomeScreen_Update($id);',
                ],
            ],
        ]);
    }

    // -------------------------------------------------------------------------
    // HTML-Generierung
    // -------------------------------------------------------------------------

    private function BuildHTML(array $raeume): string
    {
        $cards = '';
        foreach ($raeume as $raum) {
            $cards .= $this->BuildRoomCard($raum);
        }

        if (empty($raeume)) {
            $cards = '<p class="empty">Keine Räume konfiguriert. Bitte in den Moduleinstellungen Räume hinzufügen.</p>';
        }

        $time = date('d.m.Y H:i:s');

        return <<<HTML
<!DOCTYPE html>
<html lang="de"><head><meta charset="UTF-8">
<style>
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body { background: #11111b; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; padding: 14px; }
  .grid { display: flex; flex-wrap: wrap; gap: 14px; }
  .card { background: #1e1e2e; border-radius: 14px; padding: 18px 20px; flex: 1 1 200px; max-width: 300px; color: #cdd6f4; box-shadow: 0 4px 16px rgba(0,0,0,0.5); }
  .card-title { font-size: 1.05em; font-weight: 700; color: #cba6f7; margin-bottom: 12px; padding-bottom: 10px; border-bottom: 1px solid #313244; }
  .row { display: flex; align-items: center; gap: 8px; padding: 5px 0; font-size: 0.88em; }
  .icon { font-size: 1.1em; width: 22px; text-align: center; flex-shrink: 0; }
  .label { color: #a6adc8; flex: 1; }
  .val { font-weight: 700; }
  .green  { color: #a6e3a1; }
  .yellow { color: #f9e2af; }
  .red    { color: #f38ba8; }
  .divider { height: 1px; background: #313244; margin: 6px 0; }
  .empty { color: #585b70; padding: 20px; font-size: 0.9em; }
  .footer { margin-top: 10px; font-size: 0.72em; color: #45475a; text-align: right; padding: 0 4px; }
</style></head>
<body>
<div class="grid">
{$cards}
</div>
<div class="footer">Aktualisiert: {$time}</div>
</body></html>
HTML;
    }

    private function BuildRoomCard(array $raum): string
    {
        $name      = htmlspecialchars($raum['Name'] ?? 'Unbenannt');
        $stateRows  = '';
        $sensorRows = '';

        // Licht
        $lichtID = (int)($raum['LichtID'] ?? 0);
        if ($lichtID > 0 && IPS_VariableExists($lichtID)) {
            $on     = (bool)GetValue($lichtID);
            $cls    = $on ? 'yellow' : 'green';
            $status = $on ? 'Eingeschaltet' : 'Aus';
            $stateRows .= "<div class='row'>"
                . "<span class='icon'>💡</span>"
                . "<span class='label'>Licht</span>"
                . "<span class='val {$cls}'>{$status}</span>"
                . "</div>";
        }

        // Fenster
        $fensterID = (int)($raum['FensterID'] ?? 0);
        if ($fensterID > 0 && IPS_VariableExists($fensterID)) {
            $open   = (bool)GetValue($fensterID);
            $cls    = $open ? 'red' : 'green';
            $status = $open ? 'Geöffnet' : 'Geschlossen';
            $stateRows .= "<div class='row'>"
                . "<span class='icon'>🪟</span>"
                . "<span class='label'>Fenster</span>"
                . "<span class='val {$cls}'>{$status}</span>"
                . "</div>";
        }

        // Temperatur
        $tempID = (int)($raum['TempID'] ?? 0);
        if ($tempID > 0 && IPS_VariableExists($tempID)) {
            $val = round((float)GetValue($tempID), 1);
            if ($val >= 19 && $val <= 24) {
                $cls = 'green';
            } elseif ($val < 17 || $val > 27) {
                $cls = 'red';
            } else {
                $cls = 'yellow';
            }
            $sensorRows .= "<div class='row'>"
                . "<span class='icon'>🌡️</span>"
                . "<span class='label'>Temperatur</span>"
                . "<span class='val {$cls}'>{$val} °C</span>"
                . "</div>";
        }

        // Luftfeuchtigkeit
        $humID = (int)($raum['HumID'] ?? 0);
        if ($humID > 0 && IPS_VariableExists($humID)) {
            $val = (int)round((float)GetValue($humID));
            if ($val >= 40 && $val <= 60) {
                $cls = 'green';
            } elseif ($val < 30 || $val > 70) {
                $cls = 'red';
            } else {
                $cls = 'yellow';
            }
            $sensorRows .= "<div class='row'>"
                . "<span class='icon'>💧</span>"
                . "<span class='label'>Luftfeuchte</span>"
                . "<span class='val {$cls}'>{$val} %</span>"
                . "</div>";
        }

        // CO₂
        $co2ID = (int)($raum['CO2ID'] ?? 0);
        if ($co2ID > 0 && IPS_VariableExists($co2ID)) {
            $val = (int)GetValue($co2ID);
            if ($val <= 800) {
                $cls = 'green';
            } elseif ($val <= 1200) {
                $cls = 'yellow';
            } else {
                $cls = 'red';
            }
            $sensorRows .= "<div class='row'>"
                . "<span class='icon'>💨</span>"
                . "<span class='label'>CO₂</span>"
                . "<span class='val {$cls}'>{$val} ppm</span>"
                . "</div>";
        }

        // Inhalt zusammensetzen
        $content = $stateRows;
        if ($stateRows !== '' && $sensorRows !== '') {
            $content .= "<div class='divider'></div>";
        }
        $content .= $sensorRows;

        if ($content === '') {
            $content = "<div style='color:#585b70;font-size:0.82em;padding:4px 0;'>Keine Variablen konfiguriert</div>";
        }

        return "<div class='card'><div class='card-title'>{$name}</div>{$content}</div>";
    }
}
