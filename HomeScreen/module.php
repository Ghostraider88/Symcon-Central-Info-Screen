<?php

declare(strict_types=1);

class HomeScreen extends IPSModuleStrict
{
    // -------------------------------------------------------------------------
    // Lifecycle
    // -------------------------------------------------------------------------

    public function Create(): void
    {
        parent::Create();

        $this->RegisterPropertyString('Raeume', '[]');

        // Modul stellt eine eigene Visualisierung bereit (wie Da8ter-Stil)
        $this->SetVisualizationType(1);

        $this->RegisterTimer('RefreshTimer', 0, 'HomeScreen_Update($_IPS[\'TARGET\']);');
    }

    public function ApplyChanges(): void
    {
        parent::ApplyChanges();

        // Referenzen zurücksetzen
        foreach ($this->GetReferenceList() as $ref) {
            $this->UnregisterReference($ref);
        }

        // Nachrichten abmelden
        foreach ($this->GetMessageList() as $senderID => $messages) {
            foreach ($messages as $message) {
                $this->UnregisterMessage($senderID, $message);
            }
        }

        // Auf Änderungen aller konfigurierten Variablen reagieren + Referenzen registrieren
        $raeume = json_decode($this->ReadPropertyString('Raeume'), true) ?? [];
        $varIDs = [];
        foreach ($raeume as $raum) {
            $linkID = (int)($raum['LinkID'] ?? 0);
            if ($linkID > 0) {
                $this->RegisterReference($linkID);
            }
            foreach (['LichtID', 'FensterID', 'TempID', 'HumID', 'CO2ID'] as $key) {
                $id = (int)($raum[$key] ?? 0);
                if ($id > 0 && IPS_VariableExists($id)) {
                    $varIDs[] = $id;
                    $this->RegisterReference($id);
                }
            }
        }
        foreach (array_unique($varIDs) as $id) {
            $this->RegisterMessage($id, VM_UPDATE);
        }

        $this->SetTimerInterval('RefreshTimer', 5 * 60 * 1000);

        $this->UpdateVisualizationValue($this->GetUpdatePayload());
    }

    public function MessageSink(int $TimeStamp, int $SenderID, int $Message, array $Data): void
    {
        if ($Message === VM_UPDATE) {
            $this->UpdateVisualizationValue($this->GetUpdatePayload());
        }
    }

    public function Update(): void
    {
        $this->UpdateVisualizationValue($this->GetUpdatePayload());
    }

    // -------------------------------------------------------------------------
    // Visualization
    // -------------------------------------------------------------------------

    public function GetVisualizationTile(): string
    {
        $initialHandling = '<script>handleMessage(' . $this->GetUpdatePayload() . ');</script>';
        return $this->GetTileTemplate() . $initialHandling;
    }

    private function GetUpdatePayload(): string
    {
        $raeume = json_decode($this->ReadPropertyString('Raeume'), true) ?? [];
        return json_encode([
            'content' => $this->BuildContent($raeume),
            'footer'  => 'Aktualisiert: ' . date('d.m.Y H:i:s'),
        ]);
    }

    private function GetTileTemplate(): string
    {
        return <<<'HTML'
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
  .card.clickable { cursor: pointer; transition: filter 0.15s, border-color 0.15s; }
  .card.clickable:hover { border-color: rgba(128,128,128,0.4); filter: brightness(1.05); }
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
</style>
<div id="cis-content"></div>
<div id="cis-footer" class="footer"></div>
<script>
function handleMessage(data) {
    const d = JSON.parse(data);
    if (d.content !== undefined) {
        document.getElementById('cis-content').innerHTML = d.content;
    }
    if (d.footer !== undefined) {
        document.getElementById('cis-footer').textContent = d.footer;
    }
}
</script>
HTML;
    }

    // -------------------------------------------------------------------------
    // Konfigurationsformular
    // -------------------------------------------------------------------------

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
                    'sort'    => ['column' => 'Bereich', 'direction' => 'ascending'],
                    'columns' => [
                        [
                            'caption' => 'Bereich / Stockwerk',
                            'name'    => 'Bereich',
                            'width'   => '160px',
                            'add'     => '',
                            'edit'    => ['type' => 'ValidationTextBox'],
                        ],
                        [
                            'caption' => 'Raumname',
                            'name'    => 'Name',
                            'width'   => '140px',
                            'add'     => 'Neuer Raum',
                            'edit'    => ['type' => 'ValidationTextBox'],
                        ],
                        [
                            'caption' => 'Navigation (Klick)',
                            'name'    => 'LinkID',
                            'width'   => '200px',
                            'add'     => 0,
                            'edit'    => ['type' => 'SelectObject'],
                        ],
                        [
                            'caption' => 'Licht',
                            'name'    => 'LichtID',
                            'width'   => '160px',
                            'add'     => 0,
                            'edit'    => ['type' => 'SelectVariable'],
                        ],
                        [
                            'caption' => 'Licht inv.',
                            'name'    => 'LichtInvert',
                            'width'   => '80px',
                            'add'     => false,
                            'edit'    => ['type' => 'CheckBox'],
                        ],
                        [
                            'caption' => 'Fenster',
                            'name'    => 'FensterID',
                            'width'   => '160px',
                            'add'     => 0,
                            'edit'    => ['type' => 'SelectVariable'],
                        ],
                        [
                            'caption' => 'Fenster inv.',
                            'name'    => 'FensterInvert',
                            'width'   => '90px',
                            'add'     => false,
                            'edit'    => ['type' => 'CheckBox'],
                        ],
                        [
                            'caption' => 'Temperatur (°C)',
                            'name'    => 'TempID',
                            'width'   => '160px',
                            'add'     => 0,
                            'edit'    => ['type' => 'SelectVariable'],
                        ],
                        [
                            'caption' => 'Luftfeuchte (%)',
                            'name'    => 'HumID',
                            'width'   => '160px',
                            'add'     => 0,
                            'edit'    => ['type' => 'SelectVariable'],
                        ],
                        [
                            'caption' => 'CO₂ (ppm)',
                            'name'    => 'CO2ID',
                            'width'   => '140px',
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

    private function BuildContent(array $raeume): string
    {
        if (empty($raeume)) {
            return '<p class="empty">Keine Räume konfiguriert. Bitte in den Moduleinstellungen Räume hinzufügen.</p>';
        }

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

        $html = '';
        foreach ($reihenfolge as $bereich) {
            if ($bereich !== '') {
                $html .= "<div class='group-title'>" . htmlspecialchars($bereich) . "</div>";
            }
            $html .= "<div class='grid'>";
            foreach ($gruppen[$bereich] as $raum) {
                $html .= $this->BuildRoomCard($raum);
            }
            $html .= "</div>";
        }

        return $html;
    }

    private function BuildRoomCard(array $raum): string
    {
        $name       = htmlspecialchars($raum['Name'] ?? 'Unbenannt');
        $stateRows  = '';
        $sensorRows = '';

        // Licht
        $lichtID = (int)($raum['LichtID'] ?? 0);
        if ($lichtID > 0 && IPS_VariableExists($lichtID)) {
            $on = (bool)GetValue($lichtID);
            if ((bool)($raum['LichtInvert'] ?? false)) {
                $on = !$on;
            }
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
            $open = (bool)GetValue($fensterID);
            if ((bool)($raum['FensterInvert'] ?? false)) {
                $open = !$open;
            }
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

        // Navigation via openObject (funktioniert nativ im TileVisu-Kontext)
        $linkID = (int)($raum['LinkID'] ?? 0);
        if ($linkID > 0) {
            $cardAttr = "class='card clickable' onclick='openObject({$linkID})'";
        } else {
            $cardAttr = "class='card'";
        }

        return "<div {$cardAttr}><div class='card-title'>{$name}</div>{$content}</div>";
    }
}
