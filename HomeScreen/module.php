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
                            'caption' => 'Bereich / Stockwerk',
                            'name'    => 'Bereich',
                            'width'   => '180px',
                            'add'     => '',
                            'edit'    => ['type' => 'ValidationTextBox'],
                        ],
                        [
                            'caption' => 'Raumname',
                            'name'    => 'Name',
                            'width'   => '150px',
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
        $content = '';

        if (empty($raeume)) {
            $content = '<p class="empty">Keine Räume konfiguriert. Bitte in den Moduleinstellungen Räume hinzufügen.</p>';
        } else {
            // Räume nach Bereich gruppieren, Reihenfolge des ersten Auftretens erhalten
            $gruppen = [];
            $reihenfolge = [];
            foreach ($raeume as $raum) {
                $bereich = trim($raum['Bereich'] ?? '');
                if (!isset($gruppen[$bereich])) {
                    $reihenfolge[] = $bereich;
                    $gruppen[$bereich] = [];
                }
                $gruppen[$bereich][] = $raum;
            }

            foreach ($reihenfolge as $bereich) {
                if ($bereich !== '') {
                    $content .= "<div class='group-title'>" . htmlspecialchars($bereich) . "</div>";
                }
                $content .= "<div class='grid'>";
                foreach ($gruppen[$bereich] as $raum) {
                    $content .= $this->BuildRoomCard($raum);
                }
                $content .= "</div>";
            }
        }

        $time = date('d.m.Y H:i:s');

        return <<<HTML
<!DOCTYPE html>
<html lang="de"><head><meta charset="UTF-8">
<style>
  :root {
    --bg: #ffffff; --card-bg: #f5f5f5; --text: #333333; --text-muted: #777777;
    --title: #111111; --border: rgba(0,0,0,0.10); --divider: rgba(0,0,0,0.08);
    --group-clr: #666666; --empty: #999999; --footer: #bbbbbb;
  }
  @media (prefers-color-scheme: dark) {
    :root {
      --bg: #1a1a1a; --card-bg: #2d2d2d; --text: #e0e0e0; --text-muted: #9e9e9e;
      --title: #ffffff; --border: rgba(255,255,255,0.08); --divider: rgba(255,255,255,0.08);
      --group-clr: #aaaaaa; --empty: #616161; --footer: #616161;
    }
  }
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body { background: var(--bg); color: var(--text); font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; padding: 10px; }
  .group-title { font-size: 0.75em; font-weight: 700; color: var(--group-clr); text-transform: uppercase; letter-spacing: 0.06em; margin: 10px 0 5px 2px; }
  .group-title:first-child { margin-top: 2px; }
  .grid { display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 2px; }
  .card { background: var(--card-bg); border-radius: 7px; padding: 9px 11px; flex: 1 1 140px; max-width: 210px; border: 1px solid var(--border); }
  .card-title { font-size: 0.85em; font-weight: 600; color: var(--title); margin-bottom: 5px; padding-bottom: 5px; border-bottom: 1px solid var(--divider); }
  .row { display: flex; align-items: center; gap: 5px; padding: 2px 0; font-size: 0.78em; }
  .icon { font-size: 0.95em; width: 17px; text-align: center; flex-shrink: 0; }
  .label { color: var(--text-muted); flex: 1; }
  .val { font-weight: 600; }
  .green  { color: #4caf50; }
  .yellow { color: #ff9800; }
  .red    { color: #f44336; }
  .divider { height: 1px; background: var(--divider); margin: 3px 0; }
  .empty { color: var(--empty); padding: 20px; font-size: 0.9em; }
  .footer { margin-top: 8px; font-size: 0.68em; color: var(--footer); text-align: right; }
</style></head>
<body>
{$content}
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
            $content = "<div style='color:var(--empty);font-size:0.82em;padding:4px 0;'>Keine Variablen konfiguriert</div>";
        }

        return "<div class='card'><div class='card-title'>{$name}</div>{$content}</div>";
    }
}
