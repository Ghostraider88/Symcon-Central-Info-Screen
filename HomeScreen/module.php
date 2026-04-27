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

        $this->RegisterPropertyString('Bereiche', '[]');
        $this->RegisterPropertyString('Raeume', '[]');

        $this->RegisterPropertyInteger('AussenTempID', 0);
        $this->RegisterPropertyInteger('AussenTempMinID', 0);
        $this->RegisterPropertyInteger('AussenTempMaxID', 0);
        $this->RegisterPropertyInteger('AussenHumID', 0);

        $this->SetVisualizationType(1);

        $this->RegisterTimer('RefreshTimer', 0, 'HomeScreen_Update($_IPS[\'TARGET\']);');
    }

    public function ApplyChanges(): void
    {
        parent::ApplyChanges();

        foreach ($this->GetReferenceList() as $ref) {
            $this->UnregisterReference($ref);
        }
        foreach ($this->GetMessageList() as $senderID => $messages) {
            foreach ($messages as $message) {
                $this->UnregisterMessage($senderID, $message);
            }
        }

        $varIDs = [];

        $bereiche = json_decode($this->ReadPropertyString('Bereiche'), true) ?? [];
        foreach ($bereiche as $b) {
            $linkID = (int)($b['LinkID'] ?? 0);
            if ($linkID > 0) {
                $this->RegisterReference($linkID);
            }
            foreach (['LichtID', 'FensterID', 'RolladenID'] as $key) {
                $id = (int)($b[$key] ?? 0);
                if ($id > 0 && IPS_VariableExists($id)) {
                    $varIDs[] = $id;
                    $this->RegisterReference($id);
                }
            }
        }

        $raeume = json_decode($this->ReadPropertyString('Raeume'), true) ?? [];
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

        foreach (['AussenTempID', 'AussenTempMinID', 'AussenTempMaxID', 'AussenHumID'] as $key) {
            $id = (int)$this->ReadPropertyInteger($key);
            if ($id > 0 && IPS_VariableExists($id)) {
                $varIDs[] = $id;
                $this->RegisterReference($id);
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
        $bereiche = json_decode($this->ReadPropertyString('Bereiche'), true) ?? [];
        $raeume   = json_decode($this->ReadPropertyString('Raeume'), true) ?? [];

        // Inhalt direkt vorrendern → kein weißer Flash beim Laden
        $content = $this->BuildContent($bereiche, $raeume);
        $footer  = 'Aktualisiert: ' . date('d.m.Y H:i:s');

        return $this->RenderTile($content, $footer);
    }

    private function GetUpdatePayload(): string
    {
        $bereiche = json_decode($this->ReadPropertyString('Bereiche'), true) ?? [];
        $raeume   = json_decode($this->ReadPropertyString('Raeume'), true) ?? [];

        return json_encode([
            'content' => $this->BuildContent($bereiche, $raeume),
            'footer'  => 'Aktualisiert: ' . date('d.m.Y H:i:s'),
        ]);
    }

    private function RenderTile(string $content, string $footer): string
    {
        $safeContent = str_replace(['</script>', '<script'], ['<\/script>', '<scr\ipt'], $content);
        $safeFooter  = htmlspecialchars($footer);

        return <<<HTML
<style>
  :root{
    --bg:transparent;--card-bg:#ffffff;--text:#333;--text-muted:#aaa;
    --title:#111;--border:rgba(0,0,0,0.15);
    --group-clr:#555;--group-bg:rgba(0,0,0,0.05);--empty:#999;--footer:#bbb;
  }
  @media(prefers-color-scheme:dark){
    :root{
      --bg:transparent;--card-bg:#1e1e1e;--text:#ddd;--text-muted:#666;
      --title:#f0f0f0;--border:rgba(255,255,255,0.15);
      --group-clr:#aaa;--group-bg:rgba(255,255,255,0.05);--empty:#555;--footer:#555;
    }
  }
  *{box-sizing:border-box;margin:0;padding:0;}
  body{background:var(--bg);color:var(--text);font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;padding:10px;font-size:13px;}
  .grp{margin-bottom:8px;}
  .grp+.grp{margin-top:10px;}
  .grp-hdr{display:flex;align-items:center;gap:6px;padding:4px 7px;background:var(--group-bg);border-radius:5px;margin-bottom:5px;border-left:3px solid #bbb;}
  .grp-hdr.clickable{cursor:pointer;}
  .grp-hdr.clickable:hover{filter:brightness(0.95);}
  .grp-name{font-size:0.80em;font-weight:700;color:var(--group-clr);text-transform:uppercase;letter-spacing:0.05em;flex:1;}
  .grp-chips{display:flex;gap:6px;align-items:center;flex-wrap:wrap;}
  .grp-stat{display:inline-flex;align-items:center;gap:2px;font-size:0.80em;}
  .chip{display:inline-flex;align-items:center;gap:2px;padding:1px 6px;border-radius:8px;font-size:0.75em;font-weight:600;white-space:nowrap;}
  .chip-y{background:rgba(255,152,0,0.20);color:#c97000;}
  .chip-r{background:rgba(244,67,54,0.17);color:#c62828;}
  .chip-n{background:rgba(0,0,0,0.08);color:var(--text-muted);}
  @media(prefers-color-scheme:dark){
    .chip-y{background:rgba(255,152,0,0.20);color:#ffb74d;}
    .chip-r{background:rgba(244,67,54,0.22);color:#ef9a9a;}
    .chip-n{background:rgba(255,255,255,0.08);color:var(--text-muted);}
  }
  .grid{display:flex;flex-wrap:wrap;gap:6px;}
  .card{background:var(--card-bg);border-radius:6px;padding:6px 9px;flex:0 1 auto;min-width:120px;max-width:170px;border:1px solid var(--border);border-left:3px solid transparent;box-shadow:0 1px 2px rgba(0,0,0,0.06);}
  .card.clickable{cursor:pointer;}
  .card.clickable:hover{opacity:0.88;}
  .s-alert{border-left-color:#f44336;}
  .s-warn{border-left-color:#ff9800;}
  @media(prefers-color-scheme:dark){
    .card{box-shadow:0 1px 2px rgba(0,0,0,0.25);}
  }
  .c-head{display:flex;justify-content:space-between;align-items:baseline;gap:4px;margin-bottom:4px;}
  .c-name{font-weight:600;color:var(--title);font-size:0.95em;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
  .c-temp{font-weight:600;font-size:0.90em;white-space:nowrap;flex-shrink:0;}
  .p-row{display:flex;gap:4px;margin-top:2px;min-height:1.4em;}
  .p-cell{display:flex;align-items:center;gap:2px;font-size:0.85em;flex:1;min-width:0;white-space:nowrap;overflow:hidden;}
  .p-ico{font-size:0.85em;flex-shrink:0;}
  .p-none{color:var(--text-muted);font-size:0.82em;}
  .al-r{color:#e53935;}
  .al-y{color:#e65c00;}
  .co2dot{display:inline-block;width:7px;height:7px;border-radius:50%;vertical-align:middle;margin-left:2px;flex-shrink:0;}
  .dot-g{background:#4caf50;}.dot-y{background:#e65c00;}.dot-r{background:#e53935;}
  .out-bar{display:flex;align-items:center;gap:14px;padding:8px 12px;background:rgba(91,155,213,0.09);border-radius:6px;margin-bottom:10px;border-left:3px solid #5b9bd5;}
  .out-label{font-size:0.72em;font-weight:700;color:#5b9bd5;text-transform:uppercase;letter-spacing:0.06em;flex-shrink:0;}
  .out-temp{font-size:1.5em;font-weight:700;color:var(--title);flex-shrink:0;}
  .out-cold{color:#5b9bd5;}.out-cool{color:#82b4d4;}.out-warm{color:#e65c00;}.out-hot{color:#e53935;}
  .out-details{display:flex;gap:10px;align-items:center;flex:1;flex-wrap:wrap;}
  .out-range{display:flex;gap:6px;align-items:center;font-size:0.85em;}
  .out-lo{color:#5b9bd5;font-weight:600;}.out-hi{color:#e53935;font-weight:600;}
  .out-hum{font-size:0.85em;color:var(--text-muted);}
  .stat-bar{display:flex;gap:10px;align-items:center;padding:4px 2px;margin-bottom:6px;font-size:0.83em;flex-wrap:wrap;}
  .stat-ok{color:#4caf50;font-weight:600;}
  .stat-al{display:flex;align-items:center;gap:3px;}
  .grp-ok{color:#4caf50;font-size:0.80em;font-weight:600;}
  .empty{color:var(--empty);padding:10px;font-size:0.9em;}
  .footer{margin-top:8px;font-size:0.67em;color:var(--footer);text-align:right;}
</style>
<div id="cis-content">{$content}</div>
<div id="cis-footer" class="footer">{$footer}</div>
<script>
function handleMessage(data){
  var d=JSON.parse(data);
  if(d.content!==undefined)document.getElementById('cis-content').innerHTML=d.content;
  if(d.footer!==undefined)document.getElementById('cis-footer').textContent=d.footer;
}
</script>
HTML;
    }

    // -------------------------------------------------------------------------
    // Konfigurationsformular
    // -------------------------------------------------------------------------

    public function GetConfigurationForm(): string
    {
        // Bereiche für das Dropdown in der Räume-Liste vorbereiten
        $bereiche = json_decode($this->ReadPropertyString('Bereiche'), true) ?? [];
        $this->SortByPosition($bereiche);
        $bereichOptionen = [['caption' => '– kein Bereich –', 'value' => '']];
        foreach ($bereiche as $b) {
            $name = trim($b['Name'] ?? '');
            if ($name !== '') {
                $bereichOptionen[] = ['caption' => $name, 'value' => $name];
            }
        }

        return json_encode([
            'elements' => [
                [
                    'type'    => 'ExpansionPanel',
                    'caption' => 'Außen / Wetter',
                    'items'   => [
                        [
                            'type'    => 'SelectVariable',
                            'name'    => 'AussenTempID',
                            'caption' => 'Außentemperatur',
                        ],
                        [
                            'type'    => 'SelectVariable',
                            'name'    => 'AussenHumID',
                            'caption' => 'Außenluftfeuchtigkeit (optional)',
                        ],
                        [
                            'type'    => 'SelectVariable',
                            'name'    => 'AussenTempMinID',
                            'caption' => 'Tages-Tiefstwert (optional)',
                        ],
                        [
                            'type'    => 'SelectVariable',
                            'name'    => 'AussenTempMaxID',
                            'caption' => 'Tages-Höchstwert (optional)',
                        ],
                    ],
                ],
                [
                    'type'    => 'ExpansionPanel',
                    'caption' => 'Bereiche / Stockwerke',
                    'items'   => [[
                        'type'    => 'List',
                        'name'    => 'Bereiche',
                        'caption' => 'Bereiche (Reihenfolge über Pos.-Nummer)',
                        'add'     => true,
                        'delete'  => true,
                        'rowCount' => 8,
                        'columns' => [
                            [
                                'caption' => 'Pos.',
                                'name'    => 'Position',
                                'width'   => '50px',
                                'add'     => 0,
                                'edit'    => ['type' => 'NumberSpinner', 'minimum' => 0, 'maximum' => 999],
                            ],
                            [
                                'caption' => 'Name',
                                'name'    => 'Name',
                                'width'   => '120px',
                                'add'     => 'Neues Stockwerk',
                                'edit'    => ['type' => 'ValidationTextBox'],
                            ],
                            [
                                'caption' => 'Navigation (Klick)',
                                'name'    => 'LinkID',
                                'width'   => '150px',
                                'add'     => 0,
                                'edit'    => ['type' => 'SelectObject'],
                            ],
                            [
                                'caption' => 'Licht (Anzahl/Bool)',
                                'name'    => 'LichtID',
                                'width'   => '140px',
                                'add'     => 0,
                                'edit'    => ['type' => 'SelectVariable'],
                            ],
                            [
                                'caption' => 'Fenster (Anzahl/Bool)',
                                'name'    => 'FensterID',
                                'width'   => '140px',
                                'add'     => 0,
                                'edit'    => ['type' => 'SelectVariable'],
                            ],
                            [
                                'caption' => 'Rolladen (Anzahl/Bool)',
                                'name'    => 'RolladenID',
                                'width'   => '140px',
                                'add'     => 0,
                                'edit'    => ['type' => 'SelectVariable'],
                            ],
                        ],
                    ]],
                ],
                [
                    'type'    => 'ExpansionPanel',
                    'caption' => 'Räume',
                    'expanded' => true,
                    'items'   => [[
                        'type'    => 'List',
                        'name'    => 'Raeume',
                        'caption' => 'Räume (Reihenfolge über Pos.-Nummer)',
                        'add'     => true,
                        'delete'  => true,
                        'rowCount' => 8,
                        'columns' => [
                            [
                                'caption' => 'Pos.',
                                'name'    => 'Position',
                                'width'   => '50px',
                                'add'     => 0,
                                'edit'    => ['type' => 'NumberSpinner', 'minimum' => 0, 'maximum' => 999],
                            ],
                            [
                                'caption' => 'Stockwerk/Bereich',
                                'name'    => 'Bereich',
                                'width'   => '120px',
                                'add'     => '',
                                'edit'    => ['type' => 'Select', 'options' => $bereichOptionen],
                            ],
                            [
                                'caption' => 'Raumname',
                                'name'    => 'Name',
                                'width'   => '120px',
                                'add'     => 'Neuer Raum',
                                'edit'    => ['type' => 'ValidationTextBox'],
                            ],
                            [
                                'caption' => 'Navigation (Klick)',
                                'name'    => 'LinkID',
                                'width'   => '140px',
                                'add'     => 0,
                                'edit'    => ['type' => 'SelectObject'],
                            ],
                            [
                                'caption' => 'Licht',
                                'name'    => 'LichtID',
                                'width'   => '120px',
                                'add'     => 0,
                                'edit'    => ['type' => 'SelectVariable'],
                            ],
                            [
                                'caption' => 'Inv.',
                                'name'    => 'LichtInvert',
                                'width'   => '40px',
                                'add'     => false,
                                'edit'    => ['type' => 'CheckBox'],
                            ],
                            [
                                'caption' => 'Fenster',
                                'name'    => 'FensterID',
                                'width'   => '120px',
                                'add'     => 0,
                                'edit'    => ['type' => 'SelectVariable'],
                            ],
                            [
                                'caption' => 'Inv.',
                                'name'    => 'FensterInvert',
                                'width'   => '40px',
                                'add'     => false,
                                'edit'    => ['type' => 'CheckBox'],
                            ],
                            [
                                'caption' => 'Temperatur (°C)',
                                'name'    => 'TempID',
                                'width'   => '120px',
                                'add'     => 0,
                                'edit'    => ['type' => 'SelectVariable'],
                            ],
                            [
                                'caption' => 'Luftfeuchte (%)',
                                'name'    => 'HumID',
                                'width'   => '120px',
                                'add'     => 0,
                                'edit'    => ['type' => 'SelectVariable'],
                            ],
                            [
                                'caption' => 'CO₂ (ppm)',
                                'name'    => 'CO2ID',
                                'width'   => '110px',
                                'add'     => 0,
                                'edit'    => ['type' => 'SelectVariable'],
                            ],
                        ],
                    ]],
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

    private function SortByPosition(array &$items): void
    {
        foreach ($items as $i => &$item) {
            if (!isset($item['Position']) || (int)$item['Position'] === 0) {
                // Kein Pos.-Wert → Originalreihenfolge beibehalten (Index-basiert)
                $item['_sortKey'] = 10000 + $i;
            } else {
                $item['_sortKey'] = (int)$item['Position'];
            }
        }
        unset($item);
        usort($items, fn($a, $b) => $a['_sortKey'] - $b['_sortKey']);
    }

    private function BuildContent(array $bereiche, array $raeume): string
    {
        if (empty($bereiche) && empty($raeume)) {
            return '<p class="empty">Keine Räume konfiguriert.</p>';
        }

        // Reihenfolge per Positions-Nummer steuern
        $this->SortByPosition($bereiche);
        $this->SortByPosition($raeume);

        // Räume nach Bereich-Name gruppieren (Reihenfolge des ersten Auftretens)
        $raumGruppen = [];
        $raumReihenfolge = [];
        foreach ($raeume as $raum) {
            $b = trim($raum['Bereich'] ?? '');
            if (!isset($raumGruppen[$b])) {
                $raumReihenfolge[] = $b;
                $raumGruppen[$b] = [];
            }
            $raumGruppen[$b][] = $raum;
        }

        // Bereiche-Index für schnellen Zugriff
        $bereichIndex = [];
        foreach ($bereiche as $b) {
            $bereichIndex[trim($b['Name'] ?? '')] = $b;
        }

        // Reihenfolge: zuerst konfigurierte Bereiche (in ihrer Reihenfolge), dann unkonfigurierte
        $ausgabeReihenfolge = [];
        foreach ($bereiche as $b) {
            $ausgabeReihenfolge[] = trim($b['Name'] ?? '');
        }
        foreach ($raumReihenfolge as $name) {
            if (!in_array($name, $ausgabeReihenfolge, true)) {
                $ausgabeReihenfolge[] = $name;
            }
        }

        $html  = $this->BuildOutdoorBar();
        $html .= $this->BuildGlobalStatus($raeume);

        foreach ($ausgabeReihenfolge as $bereichName) {
            $raeumeListe = $raumGruppen[$bereichName] ?? [];
            $bereichDef  = $bereichIndex[$bereichName] ?? null;

            if (empty($raeumeListe) && $bereichDef === null) {
                continue;
            }

            $html .= "<div class='grp'>";
            $html .= $this->BuildBereichHeader($bereichName, $bereichDef, $raeumeListe);

            if (!empty($raeumeListe)) {
                $html .= "<div class='grid'>";
                foreach ($raeumeListe as $raum) {
                    $html .= $this->BuildRoomCard($raum);
                }
                $html .= "</div>";
            }

            $html .= "</div>";
        }

        return $html ?: '<p class="empty">Keine Räume konfiguriert.</p>';
    }

    private function BuildOutdoorBar(): string
    {
        $tempID    = (int)$this->ReadPropertyInteger('AussenTempID');
        $tempMinID = (int)$this->ReadPropertyInteger('AussenTempMinID');
        $tempMaxID = (int)$this->ReadPropertyInteger('AussenTempMaxID');
        $humID     = (int)$this->ReadPropertyInteger('AussenHumID');

        if ($tempID === 0 || !IPS_VariableExists($tempID)) {
            return '';
        }

        $temp    = round((float)GetValue($tempID), 1);
        $tempStr = str_replace('.', ',', (string)$temp) . '°';

        if ($temp <= 0)       { $tempCls = ' out-cold'; }
        elseif ($temp <= 10)  { $tempCls = ' out-cool'; }
        elseif ($temp >= 28)  { $tempCls = ' out-hot'; }
        elseif ($temp >= 22)  { $tempCls = ' out-warm'; }
        else                  { $tempCls = ''; }

        $details = '';

        if ($tempMinID > 0 && IPS_VariableExists($tempMinID)) {
            $min      = round((float)GetValue($tempMinID), 1);
            $details .= "<span class='out-lo'>↓ " . str_replace('.', ',', (string)$min) . "°</span>";
        }
        if ($tempMaxID > 0 && IPS_VariableExists($tempMaxID)) {
            $max      = round((float)GetValue($tempMaxID), 1);
            $details .= "<span class='out-hi'>↑ " . str_replace('.', ',', (string)$max) . "°</span>";
        }
        if ($humID > 0 && IPS_VariableExists($humID)) {
            $hum      = (int)round((float)GetValue($humID));
            $details .= "<span class='out-hum'>💧 {$hum}%</span>";
        }

        return "<div class='out-bar'>"
            . "<span class='out-label'>Außen</span>"
            . "<span class='out-temp{$tempCls}'>{$tempStr}</span>"
            . ($details !== '' ? "<span class='out-details'>{$details}</span>" : '')
            . "</div>";
    }

    private function BuildGlobalStatus(array $raeume): string
    {
        $lichterAn    = 0;
        $fensterOffen = 0;
        $tempWarn     = 0;
        $luftWarn     = 0;

        foreach ($raeume as $raum) {
            $lichtID = (int)($raum['LichtID'] ?? 0);
            if ($lichtID > 0 && IPS_VariableExists($lichtID)) {
                $on = (bool)GetValue($lichtID);
                if ((bool)($raum['LichtInvert'] ?? false)) $on = !$on;
                if ($on) $lichterAn++;
            }
            $fensterID = (int)($raum['FensterID'] ?? 0);
            if ($fensterID > 0 && IPS_VariableExists($fensterID)) {
                $open = (bool)GetValue($fensterID);
                if ((bool)($raum['FensterInvert'] ?? false)) $open = !$open;
                if ($open) $fensterOffen++;
            }
            $tempID = (int)($raum['TempID'] ?? 0);
            if ($tempID > 0 && IPS_VariableExists($tempID)) {
                $val = (float)GetValue($tempID);
                if ($val < 18 || $val > 25) $tempWarn++;
            }
            $humID = (int)($raum['HumID'] ?? 0);
            if ($humID > 0 && IPS_VariableExists($humID)) {
                $val = (int)GetValue($humID);
                if ($val < 30 || $val > 60) $luftWarn++;
            }
            $co2ID = (int)($raum['CO2ID'] ?? 0);
            if ($co2ID > 0 && IPS_VariableExists($co2ID)) {
                $val = (int)GetValue($co2ID);
                if ($val >= 1000) $luftWarn++;
            }
        }

        if ($lichterAn === 0 && $fensterOffen === 0 && $tempWarn === 0 && $luftWarn === 0) {
            return "<div class='stat-bar'><span class='stat-ok'>✓ Alles in Ordnung</span></div>";
        }

        $items = [];
        if ($lichterAn > 0)    { $items[] = "<span class='stat-al al-r'>💡 {$lichterAn} an</span>"; }
        if ($fensterOffen > 0) { $items[] = "<span class='stat-al al-r'>🪟 {$fensterOffen} offen</span>"; }
        if ($tempWarn > 0)     { $items[] = "<span class='stat-al al-r'>🌡️ {$tempWarn} Temp.</span>"; }
        if ($luftWarn > 0)     { $items[] = "<span class='stat-al al-y'>💨 {$luftWarn} Luft</span>"; }

        return "<div class='stat-bar'>" . implode('', $items) . "</div>";
    }

    private function HasBereichAlarm(array $raeume): bool
    {
        foreach ($raeume as $raum) {
            $lichtID = (int)($raum['LichtID'] ?? 0);
            if ($lichtID > 0 && IPS_VariableExists($lichtID)) {
                $on = (bool)GetValue($lichtID);
                if ((bool)($raum['LichtInvert'] ?? false)) $on = !$on;
                if ($on) return true;
            }
            $fensterID = (int)($raum['FensterID'] ?? 0);
            if ($fensterID > 0 && IPS_VariableExists($fensterID)) {
                $open = (bool)GetValue($fensterID);
                if ((bool)($raum['FensterInvert'] ?? false)) $open = !$open;
                if ($open) return true;
            }
            foreach (['TempID', 'HumID', 'CO2ID'] as $key) {
                $id = (int)($raum[$key] ?? 0);
                if ($id > 0 && IPS_VariableExists($id)) {
                    $val = (float)GetValue($id);
                    if ($key === 'TempID' && ($val < 18 || $val > 25)) return true;
                    if ($key === 'HumID'  && ($val < 30 || $val > 60)) return true;
                    if ($key === 'CO2ID'  && $val >= 1000)              return true;
                }
            }
        }
        return false;
    }

    private function BuildBereichHeader(string $name, ?array $def, array $raeume = []): string
    {
        if ($name === '' && $def === null) {
            return '';
        }

        $stats = '';

        if ($def !== null) {
            // Licht
            $lichtID = (int)($def['LichtID'] ?? 0);
            if ($lichtID > 0 && IPS_VariableExists($lichtID)) {
                $val     = GetValue($lichtID);
                $varType = IPS_GetVariable($lichtID)['VariableType'];
                if ($varType === 0) {
                    $cls  = $val ? " class='al-r'" : '';
                    $text = $val ? 'an' : 'aus';
                } else {
                    $cls  = $val > 0 ? " class='al-r'" : '';
                    $text = $val > 0 ? "{$val} an" : 'aus';
                }
                $stats .= "<span class='grp-stat'>💡<span{$cls}>{$text}</span></span>";
            }

            // Fenster
            $fensterID = (int)($def['FensterID'] ?? 0);
            if ($fensterID > 0 && IPS_VariableExists($fensterID)) {
                $val     = GetValue($fensterID);
                $varType = IPS_GetVariable($fensterID)['VariableType'];
                if ($varType === 0) {
                    $cls  = $val ? " class='al-r'" : '';
                    $text = $val ? 'offen' : 'zu';
                } else {
                    $cls  = $val > 0 ? " class='al-r'" : '';
                    $text = $val > 0 ? "{$val} offen" : 'alle zu';
                }
                $stats .= "<span class='grp-stat'>🪟<span{$cls}>{$text}</span></span>";
            }

            // Rolladen
            $rolladenID = (int)($def['RolladenID'] ?? 0);
            if ($rolladenID > 0 && IPS_VariableExists($rolladenID)) {
                $val     = GetValue($rolladenID);
                $varType = IPS_GetVariable($rolladenID)['VariableType'];
                if ($varType === 0) {
                    $cls  = $val ? " class='al-r'" : '';
                    $text = $val ? 'offen' : 'zu';
                } else {
                    $formatted = GetValueFormatted($rolladenID);
                    $cls  = $val > 0 ? " class='al-r'" : '';
                    $text = $formatted;
                }
                $stats .= "<span class='grp-stat'>⬜<span{$cls}>{$text}</span></span>";
            }
        }

        // „✓ OK" wenn keine Alarme in diesem Bereich und auch keine Def-Stats vorhanden
        if ($stats === '' && !empty($raeume) && !$this->HasBereichAlarm($raeume)) {
            $stats = "<span class='grp-ok'>✓ alles ok</span>";
        }

        $linkID      = (int)(($def ?? [])['LinkID'] ?? 0);
        $clickable   = $linkID > 0 ? " clickable' onclick='openObject({$linkID})" : '';
        $displayName = $name !== '' ? htmlspecialchars($name) : 'Ohne Bereich';

        return "<div class='grp-hdr{$clickable}'>"
            . "<span class='grp-name'>{$displayName}</span>"
            . ($stats !== '' ? "<span class='grp-chips'>{$stats}</span>" : '')
            . "</div>";
    }

    private function BuildRoomCard(array $raum): string
    {
        $name = htmlspecialchars($raum['Name'] ?? 'Unbenannt');

        // ── Temperatur ────────────────────────────────────────────────
        $tempStr = '';
        $tempCls = '';
        $tempID  = (int)($raum['TempID'] ?? 0);
        if ($tempID > 0 && IPS_VariableExists($tempID)) {
            $val     = round((float)GetValue($tempID), 1);
            $tempCls = ($val < 18 || $val > 25) ? ' al-r' : '';
            $tempStr = str_replace('.', ',', (string)$val) . '°';
        }

        // ── Licht ─────────────────────────────────────────────────────
        $lichtID  = (int)($raum['LichtID'] ?? 0);
        $hasLicht  = $lichtID > 0 && IPS_VariableExists($lichtID);
        $isLichtAn = false;
        $lichtHTML = '';
        if ($hasLicht) {
            $on = (bool)GetValue($lichtID);
            if ((bool)($raum['LichtInvert'] ?? false)) {
                $on = !$on;
            }
            $isLichtAn = $on;
            $cls       = $on ? " class='al-r'" : '';
            $text      = $on ? 'an' : 'aus';
            $lichtHTML = "<span class='p-ico'>💡</span><span{$cls}>{$text}</span>";
        }

        // ── Fenster ───────────────────────────────────────────────────
        $fensterID    = (int)($raum['FensterID'] ?? 0);
        $hasFenster   = $fensterID > 0 && IPS_VariableExists($fensterID);
        $isFensterAuf = false;
        $fensterHTML  = '';
        if ($hasFenster) {
            $open = (bool)GetValue($fensterID);
            if ((bool)($raum['FensterInvert'] ?? false)) {
                $open = !$open;
            }
            $isFensterAuf = $open;
            $cls          = $open ? " class='al-r'" : '';
            $text         = $open ? 'offen' : 'zu';
            $fensterHTML  = "<span class='p-ico'>🪟</span><span{$cls}>{$text}</span>";
        }

        // ── Luftfeuchtigkeit ──────────────────────────────────────────
        $humID  = (int)($raum['HumID'] ?? 0);
        $hasHum = $humID > 0 && IPS_VariableExists($humID);
        $humHTML = '';
        if ($hasHum) {
            $val     = (int)round((float)GetValue($humID));
            $cls     = ($val < 30 || $val > 60) ? " class='al-r'" : '';
            $humHTML = "<span class='p-ico'>💧</span><span{$cls}>{$val}%</span>";
        }

        // ── CO₂ ───────────────────────────────────────────────────────
        $co2ID  = (int)($raum['CO2ID'] ?? 0);
        $hasCO2 = $co2ID > 0 && IPS_VariableExists($co2ID);
        $co2HTML = '';
        if ($hasCO2) {
            $val = (int)GetValue($co2ID);
            if ($val > 1400)      { $valCls = " class='al-r'"; $dotCls = 'dot-r'; }
            elseif ($val >= 1000) { $valCls = " class='al-y'"; $dotCls = 'dot-y'; }
            else                  { $valCls = '';               $dotCls = 'dot-g'; }
            $co2HTML = "<span class='p-ico'>💨</span><span{$valCls}>{$val}</span><span class='co2dot {$dotCls}'></span>";
        }

        // ── Karten-Rand je nach Alarmzustand ─────────────────────────
        if ($isFensterAuf)    { $stateClass = ' s-alert'; }
        elseif ($isLichtAn)   { $stateClass = ' s-warn'; }
        else                  { $stateClass = ''; }

        // ── Zeilen immer rendern für feste Positionen ────────────────
        $hasAny = $hasLicht || $hasFenster || $hasHum || $hasCO2 || $tempStr !== '';

        $row1 = "<div class='p-row'>"
            . "<span class='p-cell'>{$lichtHTML}</span>"
            . "<span class='p-cell'>{$fensterHTML}</span>"
            . "</div>";

        $row2 = "<div class='p-row'>"
            . "<span class='p-cell'>{$humHTML}</span>"
            . "<span class='p-cell'>{$co2HTML}</span>"
            . "</div>";

        // ── Karte zusammenbauen ───────────────────────────────────────
        $head = "<div class='c-head'><span class='c-name'>{$name}</span>"
            . ($tempStr !== '' ? "<span class='c-temp{$tempCls}'>{$tempStr}</span>" : '')
            . "</div>";

        $body = $hasAny ? $row1 . $row2 : "<div class='p-none'>–</div>";

        $linkID   = (int)($raum['LinkID'] ?? 0);
        $cardAttr = $linkID > 0
            ? "class='card{$stateClass} clickable' onclick='openObject({$linkID})'"
            : "class='card{$stateClass}'";

        return "<div {$cardAttr}>{$head}{$body}</div>";
    }
}
