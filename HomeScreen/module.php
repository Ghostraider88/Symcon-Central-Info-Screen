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
  :root {
    --bg:transparent;--card-bg:#f0f0f0;--text:#333;--text-muted:#888;
    --title:#111;--border:rgba(0,0,0,0.10);--divider:rgba(0,0,0,0.08);
    --group-clr:#555;--group-bg:rgba(0,0,0,0.04);--empty:#999;--footer:#bbb;
  }
  @media(prefers-color-scheme:dark){
    :root{
      --bg:transparent;--card-bg:#2d2d2d;--text:#e0e0e0;--text-muted:#888;
      --title:#fff;--border:rgba(255,255,255,0.08);--divider:rgba(255,255,255,0.08);
      --group-clr:#aaa;--group-bg:rgba(255,255,255,0.04);--empty:#555;--footer:#555;
    }
  }
  *{box-sizing:border-box;margin:0;padding:0;}
  body{background:var(--bg);color:var(--text);font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;padding:6px;font-size:11px;}
  .grp{margin-bottom:6px;}
  .grp+.grp{margin-top:8px;}
  .grp-hdr{display:flex;align-items:center;gap:6px;padding:3px 4px;background:var(--group-bg);border-radius:4px;margin-bottom:4px;}
  .grp-hdr.clickable{cursor:pointer;}
  .grp-hdr.clickable:hover{filter:brightness(0.95);}
  .grp-name{font-size:0.82em;font-weight:700;color:var(--group-clr);text-transform:uppercase;letter-spacing:0.05em;flex:1;}
  .grp-stats{display:flex;gap:6px;align-items:center;}
  .grp-stat{font-size:0.78em;color:var(--text-muted);display:flex;align-items:center;gap:2px;white-space:nowrap;}
  .grid{display:flex;flex-wrap:wrap;gap:4px;}
  .card{background:var(--card-bg);border-radius:5px;padding:5px 6px;flex:0 1 auto;min-width:90px;max-width:160px;border:1px solid var(--border);}
  .card.clickable{cursor:pointer;}
  .card.clickable:hover{filter:brightness(0.97);border-color:rgba(100,100,100,0.3);}
  .c-head{display:flex;justify-content:space-between;align-items:baseline;gap:4px;margin-bottom:3px;}
  .c-name{font-weight:600;color:var(--title);font-size:1.0em;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
  .c-temp{font-weight:600;font-size:0.95em;white-space:nowrap;flex-shrink:0;}
  .c-rows{display:flex;flex-direction:column;gap:1px;}
  .c-row{display:flex;gap:6px;}
  .c-cell{display:flex;align-items:center;gap:2px;white-space:nowrap;flex:1;}
  .ico{font-size:0.9em;flex-shrink:0;}
  .v{font-size:0.88em;}
  .green{color:#4caf50;}.yellow{color:#ff9800;}.red{color:#f44336;}
  .empty{color:var(--empty);padding:10px;font-size:0.9em;}
  .footer{margin-top:6px;font-size:0.65em;color:var(--footer);text-align:right;}
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
        return json_encode([
            'elements' => [
                [
                    'type'    => 'ExpansionPanel',
                    'caption' => 'Bereiche / Stockwerke',
                    'items'   => [[
                        'type'    => 'List',
                        'name'    => 'Bereiche',
                        'caption' => 'Bereiche',
                        'add'     => true,
                        'delete'  => true,
                        'columns' => [
                            [
                                'caption' => 'Name',
                                'name'    => 'Name',
                                'width'   => '160px',
                                'add'     => 'Neuer Bereich',
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
                                'caption' => 'Licht (Anzahl/Bool)',
                                'name'    => 'LichtID',
                                'width'   => '180px',
                                'add'     => 0,
                                'edit'    => ['type' => 'SelectVariable'],
                            ],
                            [
                                'caption' => 'Fenster (Anzahl/Bool)',
                                'name'    => 'FensterID',
                                'width'   => '180px',
                                'add'     => 0,
                                'edit'    => ['type' => 'SelectVariable'],
                            ],
                            [
                                'caption' => 'Rolladen (Anzahl/Bool)',
                                'name'    => 'RolladenID',
                                'width'   => '180px',
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
                        'caption' => 'Räume',
                        'add'     => true,
                        'delete'  => true,
                        'columns' => [
                            [
                                'caption' => 'Bereich',
                                'name'    => 'Bereich',
                                'width'   => '140px',
                                'add'     => '',
                                'edit'    => ['type' => 'ValidationTextBox'],
                            ],
                            [
                                'caption' => 'Raumname',
                                'name'    => 'Name',
                                'width'   => '130px',
                                'add'     => 'Neuer Raum',
                                'edit'    => ['type' => 'ValidationTextBox'],
                            ],
                            [
                                'caption' => 'Navigation (Klick)',
                                'name'    => 'LinkID',
                                'width'   => '180px',
                                'add'     => 0,
                                'edit'    => ['type' => 'SelectObject'],
                            ],
                            [
                                'caption' => 'Licht',
                                'name'    => 'LichtID',
                                'width'   => '150px',
                                'add'     => 0,
                                'edit'    => ['type' => 'SelectVariable'],
                            ],
                            [
                                'caption' => 'Inv.',
                                'name'    => 'LichtInvert',
                                'width'   => '50px',
                                'add'     => false,
                                'edit'    => ['type' => 'CheckBox'],
                            ],
                            [
                                'caption' => 'Fenster',
                                'name'    => 'FensterID',
                                'width'   => '150px',
                                'add'     => 0,
                                'edit'    => ['type' => 'SelectVariable'],
                            ],
                            [
                                'caption' => 'Inv.',
                                'name'    => 'FensterInvert',
                                'width'   => '50px',
                                'add'     => false,
                                'edit'    => ['type' => 'CheckBox'],
                            ],
                            [
                                'caption' => 'Temperatur (°C)',
                                'name'    => 'TempID',
                                'width'   => '150px',
                                'add'     => 0,
                                'edit'    => ['type' => 'SelectVariable'],
                            ],
                            [
                                'caption' => 'Luftfeuchte (%)',
                                'name'    => 'HumID',
                                'width'   => '150px',
                                'add'     => 0,
                                'edit'    => ['type' => 'SelectVariable'],
                            ],
                            [
                                'caption' => 'CO₂ (ppm)',
                                'name'    => 'CO2ID',
                                'width'   => '130px',
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

    private function BuildContent(array $bereiche, array $raeume): string
    {
        if (empty($bereiche) && empty($raeume)) {
            return '<p class="empty">Keine Räume konfiguriert.</p>';
        }

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

        $html = '';
        foreach ($ausgabeReihenfolge as $bereichName) {
            $raeumeListe = $raumGruppen[$bereichName] ?? [];
            $bereichDef  = $bereichIndex[$bereichName] ?? null;

            // Konfigurierte Bereiche auch ohne Räume anzeigen
            if (empty($raeumeListe) && $bereichDef === null) {
                continue;
            }

            $html .= "<div class='grp'>";
            $html .= $this->BuildBereichHeader($bereichName, $bereichDef);

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

    private function BuildBereichHeader(string $name, ?array $def): string
    {
        if ($name === '' && $def === null) {
            return '';
        }

        $stats = '';

        if ($def !== null) {
            // Licht
            $lichtID = (int)($def['LichtID'] ?? 0);
            if ($lichtID > 0 && IPS_VariableExists($lichtID)) {
                $val = GetValue($lichtID);
                $varType = IPS_GetVariable($lichtID)['VariableType'];
                if ($varType === 0) { // Boolean
                    $cls  = $val ? 'yellow' : 'green';
                    $text = $val ? 'an' : 'aus';
                } else {
                    $cls  = $val > 0 ? 'yellow' : 'green';
                    $text = $val > 0 ? "{$val} an" : 'aus';
                }
                $stats .= "<span class='grp-stat'><span class='ico'>💡</span><span class='v {$cls}'>{$text}</span></span>";
            }

            // Fenster
            $fensterID = (int)($def['FensterID'] ?? 0);
            if ($fensterID > 0 && IPS_VariableExists($fensterID)) {
                $val = GetValue($fensterID);
                $varType = IPS_GetVariable($fensterID)['VariableType'];
                if ($varType === 0) {
                    $cls  = $val ? 'red' : 'green';
                    $text = $val ? 'offen' : 'zu';
                } else {
                    $cls  = $val > 0 ? 'red' : 'green';
                    $text = $val > 0 ? "{$val} offen" : 'alle zu';
                }
                $stats .= "<span class='grp-stat'><span class='ico'>🪟</span><span class='v {$cls}'>{$text}</span></span>";
            }

            // Rolladen
            $rolladenID = (int)($def['RolladenID'] ?? 0);
            if ($rolladenID > 0 && IPS_VariableExists($rolladenID)) {
                $val = GetValue($rolladenID);
                $varType = IPS_GetVariable($rolladenID)['VariableType'];
                if ($varType === 0) {
                    $cls  = $val ? 'yellow' : 'green';
                    $text = $val ? 'offen' : 'zu';
                } else {
                    $formatted = GetValueFormatted($rolladenID);
                    $cls  = $val > 0 ? 'yellow' : 'green';
                    $text = $formatted;
                }
                $stats .= "<span class='grp-stat'><span class='ico'>⬜</span><span class='v {$cls}'>{$text}</span></span>";
            }
        }

        $linkID = (int)(($def ?? [])['LinkID'] ?? 0);
        $clickable = $linkID > 0 ? " clickable' onclick='openObject({$linkID})" : '';
        $displayName = $name !== '' ? htmlspecialchars($name) : 'Ohne Bereich';

        return "<div class='grp-hdr{$clickable}'>"
            . "<span class='grp-name'>{$displayName}</span>"
            . ($stats !== '' ? "<span class='grp-stats'>{$stats}</span>" : '')
            . "</div>";
    }

    private function BuildRoomCard(array $raum): string
    {
        $name = htmlspecialchars($raum['Name'] ?? 'Unbenannt');

        // Temperatur (für Header-Zeile)
        $tempStr = '';
        $tempCls = '';
        $tempID  = (int)($raum['TempID'] ?? 0);
        if ($tempID > 0 && IPS_VariableExists($tempID)) {
            $val = round((float)GetValue($tempID), 1);
            if ($val >= 19 && $val <= 24) {
                $tempCls = 'green';
            } elseif ($val < 17 || $val > 27) {
                $tempCls = 'red';
            } else {
                $tempCls = 'yellow';
            }
            $tempStr = str_replace('.', ',', (string)$val) . '°';
        }

        // Licht
        $lichtCell = '';
        $lichtID   = (int)($raum['LichtID'] ?? 0);
        if ($lichtID > 0 && IPS_VariableExists($lichtID)) {
            $on = (bool)GetValue($lichtID);
            if ((bool)($raum['LichtInvert'] ?? false)) {
                $on = !$on;
            }
            $cls  = $on ? 'yellow' : 'green';
            $text = $on ? 'an' : 'aus';
            $lichtCell = "<span class='c-cell'><span class='ico'>💡</span><span class='v {$cls}'>{$text}</span></span>";
        }

        // Fenster
        $fensterCell = '';
        $fensterID   = (int)($raum['FensterID'] ?? 0);
        if ($fensterID > 0 && IPS_VariableExists($fensterID)) {
            $open = (bool)GetValue($fensterID);
            if ((bool)($raum['FensterInvert'] ?? false)) {
                $open = !$open;
            }
            $cls  = $open ? 'red' : 'green';
            $text = $open ? 'offen' : 'gesch';
            $fensterCell = "<span class='c-cell'><span class='ico'>🪟</span><span class='v {$cls}'>{$text}</span></span>";
        }

        // Luftfeuchtigkeit
        $humCell = '';
        $humID   = (int)($raum['HumID'] ?? 0);
        if ($humID > 0 && IPS_VariableExists($humID)) {
            $val = (int)round((float)GetValue($humID));
            if ($val >= 40 && $val <= 60) {
                $cls = 'green';
            } elseif ($val < 30 || $val > 70) {
                $cls = 'red';
            } else {
                $cls = 'yellow';
            }
            $humCell = "<span class='c-cell'><span class='ico'>💧</span><span class='v {$cls}'>{$val}%</span></span>";
        }

        // CO₂
        $co2Cell = '';
        $co2ID   = (int)($raum['CO2ID'] ?? 0);
        if ($co2ID > 0 && IPS_VariableExists($co2ID)) {
            $val = (int)GetValue($co2ID);
            if ($val <= 800) {
                $cls = 'green';
            } elseif ($val <= 1200) {
                $cls = 'yellow';
            } else {
                $cls = 'red';
            }
            $co2Cell = "<span class='c-cell'><span class='ico'>💨</span><span class='v {$cls}'>{$val}</span></span>";
        }

        // Kopfzeile: Name + Temp
        $head = "<div class='c-head'><span class='c-name'>{$name}</span>"
            . ($tempStr !== '' ? "<span class='c-temp {$tempCls}'>{$tempStr}</span>" : '')
            . "</div>";

        // Status-Zeile: Licht + Fenster
        $row1 = '';
        if ($lichtCell !== '' || $fensterCell !== '') {
            $row1 = "<div class='c-row'>{$lichtCell}{$fensterCell}</div>";
        }

        // Sensor-Zeile: Feuchte + CO2
        $row2 = '';
        if ($humCell !== '' || $co2Cell !== '') {
            $row2 = "<div class='c-row'>{$humCell}{$co2Cell}</div>";
        }

        $rows = "<div class='c-rows'>{$row1}{$row2}</div>";

        if ($row1 === '' && $row2 === '' && $tempStr === '') {
            $rows = "<div style='color:var(--empty);font-size:0.82em;'>–</div>";
        }

        $linkID = (int)($raum['LinkID'] ?? 0);
        if ($linkID > 0) {
            $cardAttr = "class='card clickable' onclick='openObject({$linkID})'";
        } else {
            $cardAttr = "class='card'";
        }

        return "<div {$cardAttr}>{$head}{$rows}</div>";
    }
}
