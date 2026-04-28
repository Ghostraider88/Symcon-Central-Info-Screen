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
<meta name="viewport" content="width=device-width,initial-scale=1">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" crossorigin="anonymous">
<style>
  /* Poppins – Symcon Tile Assets */
  @font-face{font-family:'Poppins';src:url('/tile/assets/google_fonts/Poppins-Regular.ttf') format('truetype');font-weight:400;font-style:normal;}
  @font-face{font-family:'Poppins';src:url('/tile/assets/google_fonts/Poppins-Italic.ttf') format('truetype');font-weight:400;font-style:italic;}
  @font-face{font-family:'Poppins';src:url('/tile/assets/google_fonts/Poppins-Bold.ttf') format('truetype');font-weight:700;font-style:normal;}
  /* Symcon stellt automatisch bereit: --accent-color, --content-color, --card-color */
  :root{--text-muted:#999;--group-bg:rgba(0,0,0,0.04);--div-clr:rgba(0,0,0,0.08);--footer:#bbb;}
  *{box-sizing:border-box;margin:0;padding:0;}
  body{background:transparent;color:var(--content-color);font-family:'Poppins',-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;margin:0;padding:35px 8px 8px;font-size:13px;}
  .grp{margin-bottom:8px;}
  .grp+.grp{margin-top:10px;}
  .grp-hdr{display:flex;align-items:center;gap:6px;padding:4px 7px;background:var(--group-bg);border-radius:5px;margin-bottom:5px;border-left:3px solid var(--accent-color);}
  .grp-hdr.clickable{cursor:pointer;}
  .grp-hdr.clickable:hover{filter:brightness(0.95);}
  .grp-name{font-size:0.80em;font-weight:600;color:var(--content-color);text-transform:uppercase;letter-spacing:0.05em;flex:1;}
  .grp-chips{display:flex;gap:6px;align-items:center;flex-wrap:wrap;}
  .grp-stat{display:inline-flex;align-items:center;gap:2px;font-size:0.80em;}
  .chip-y{background:rgba(255,152,0,0.20);color:#c97000;}
  .chip-r{background:rgba(244,67,54,0.17);color:#c62828;}
  .grid{display:flex;flex-wrap:wrap;gap:6px;}
  .card{background:var(--card-color);border-radius:6px;padding:6px 9px;flex:0 1 auto;min-width:120px;max-width:170px;border:1px solid var(--accent-color);border-left:3px solid transparent;}
  .card.clickable{cursor:pointer;}
  .card.clickable:hover{opacity:0.88;}
  .s-alert{border-left-color:#f44336;}
  .s-warn{border-left-color:#ff9800;}
  .c-head{display:flex;justify-content:space-between;align-items:baseline;gap:4px;margin-bottom:4px;}
  .c-name{font-weight:500;color:var(--content-color);font-size:0.95em;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
  .c-temp{font-weight:500;font-size:0.90em;white-space:nowrap;flex-shrink:0;}
  .p-row{display:flex;gap:4px;margin-top:2px;min-height:1.4em;}
  .p-cell{display:flex;align-items:center;gap:2px;font-size:0.85em;flex:1;min-width:0;white-space:nowrap;overflow:hidden;}
  .p-ico{font-size:0.85em;flex-shrink:0;width:1.2em;text-align:center;}
  .p-none{color:var(--text-muted);font-size:0.82em;}
  .ico-muted{color:var(--text-muted);}
  .ico-on{color:#f5a623;}
  .ico-warn{color:#e65c00;}
  .ico-alert{color:#e53935;}
  .al-r{color:#e53935;}
  .al-y{color:#e65c00;}
  .co2dot{display:inline-block;width:7px;height:7px;border-radius:50%;vertical-align:middle;margin-left:2px;flex-shrink:0;}
  .dot-g{background:#4caf50;}.dot-y{background:#e65c00;}.dot-r{background:#e53935;}
  /* ── Wetter-Bar ──────────────────────────────────────────── */
  .out-bar{display:flex;align-items:center;flex-wrap:wrap;gap:0;padding:6px 12px;border-radius:6px;margin-bottom:10px;border:1px solid transparent;}
  .out-theme-freeze{background:linear-gradient(135deg,rgba(91,155,213,0.18),rgba(91,155,213,0.06));border-color:rgba(91,155,213,0.3);border-left:3px solid #5b9bd5;}
  .out-theme-cold{background:linear-gradient(135deg,rgba(130,190,220,0.15),rgba(130,190,220,0.05));border-color:rgba(130,190,220,0.25);border-left:3px solid #82bed4;}
  .out-theme-cool{background:linear-gradient(135deg,rgba(100,180,100,0.12),rgba(100,180,100,0.04));border-color:rgba(100,180,100,0.22);border-left:3px solid #64b464;}
  .out-theme-mild{background:linear-gradient(135deg,rgba(76,175,80,0.11),rgba(76,175,80,0.03));border-color:rgba(76,175,80,0.20);border-left:3px solid #4caf50;}
  .out-theme-warm{background:linear-gradient(135deg,rgba(255,167,38,0.14),rgba(255,167,38,0.04));border-color:rgba(255,167,38,0.25);border-left:3px solid #ffa726;}
  .out-theme-hot{background:linear-gradient(135deg,rgba(229,57,53,0.14),rgba(229,57,53,0.04));border-color:rgba(229,57,53,0.25);border-left:3px solid #e53935;}
  .out-icon{font-size:1.5em;flex-shrink:0;line-height:1;margin-right:10px;}
  .out-main{display:flex;align-items:baseline;gap:5px;flex-shrink:0;padding-right:14px;margin-right:14px;border-right:1px solid var(--div-clr);}
  .out-label{font-size:0.70em;font-weight:700;color:var(--content-color);text-transform:uppercase;letter-spacing:0.07em;}
  .out-temp{font-size:1.25em;font-weight:700;color:var(--content-color);line-height:1;}
  .out-cold{color:#5b9bd5;}.out-cool{color:#4a90b8;}.out-warm{color:#e65c00;}.out-hot{color:#e53935;}
  .out-seg{flex:1;display:flex;align-items:center;justify-content:center;padding:0 6px;font-size:0.82em;color:var(--text-muted);border-right:1px solid var(--div-clr);white-space:nowrap;}
  .out-seg:last-child{border-right:none;}
  .out-comfort{font-size:1em;}
  .out-range{display:flex;gap:6px;}
  .out-lo{color:#5b9bd5;font-weight:600;}.out-hi{color:#e53935;font-weight:600;}
  /* ── Mobile: Wetter-Bar 2-zeilig ──────────────────────────── */
  @media(max-width:520px){
    .out-bar{padding:6px 10px;}
    .out-icon{font-size:1.25em;margin-right:7px;}
    .out-main{flex-basis:100%;border-right:none;padding-right:0;margin-right:0;padding-bottom:5px;margin-bottom:4px;border-bottom:1px solid var(--div-clr);}
    .out-temp{font-size:1.15em;}
    .out-seg{flex:0 0 auto;border-right:none;padding:2px 8px 0;}
    .out-seg:not(:last-child){border-right:1px solid var(--div-clr);}
  }
  /* ── Status & Footer ─────────────────────────────────────── */
  .stat-bar{display:flex;gap:10px;align-items:center;padding:4px 2px;margin-bottom:6px;font-size:0.83em;flex-wrap:wrap;}
  .stat-ok{color:#4caf50;font-weight:600;}
  .stat-al{display:flex;align-items:center;gap:3px;}
  .grp-ok{color:#4caf50;font-size:0.80em;font-weight:600;}
  .empty{color:var(--text-muted);padding:10px;font-size:0.9em;}
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
        $hum     = null;

        if ($humID > 0 && IPS_VariableExists($humID)) {
            $hum = (int)round((float)GetValue($humID));
        }

        // Temperatur-Klasse + Wetter-Icon + Balken-Theme
        if ($temp <= 0)      { $tempCls = 'out-cold'; $icon = "<i class='fa-solid fa-snowflake'></i>";        $barTheme = 'out-theme-freeze'; }
        elseif ($temp <= 5)  { $tempCls = 'out-cold'; $icon = "<i class='fa-solid fa-cloud-snow'></i>";      $barTheme = 'out-theme-cold'; }
        elseif ($temp <= 10) { $tempCls = 'out-cool'; $icon = "<i class='fa-solid fa-cloud'></i>";            $barTheme = 'out-theme-cool'; }
        elseif ($temp <= 15) { $tempCls = 'out-cool'; $icon = "<i class='fa-solid fa-cloud-sun'></i>";       $barTheme = 'out-theme-cool'; }
        elseif ($temp <= 22) { $tempCls = '';          $icon = "<i class='fa-solid fa-cloud-sun'></i>";       $barTheme = 'out-theme-mild'; }
        elseif ($temp <= 28) { $tempCls = 'out-warm'; $icon = "<i class='fa-solid fa-sun'></i>";              $barTheme = 'out-theme-warm'; }
        else                 { $tempCls = 'out-hot';  $icon = "<i class='fa-solid fa-temperature-high'></i>"; $barTheme = 'out-theme-hot'; }

        // Komfort-Einschätzung aus Temperatur + Luftfeuchte
        $comfort = $this->OutdoorComfortLabel($temp, $hum);

        // Taupunkt (gibt Gefühl für „schwüle Luft")
        $dewPoint = '';
        if ($hum !== null && $temp > 10) {
            $dp  = round($temp - ((100 - $hum) / 5.0), 1);
            $dewPoint = "Taupunkt " . str_replace('.', ',', (string)$dp) . "°";
        }

        // Min/Max
        $minStr = '';
        $maxStr = '';
        if ($tempMinID > 0 && IPS_VariableExists($tempMinID)) {
            $minStr = str_replace('.', ',', (string)round((float)GetValue($tempMinID), 1)) . '°';
        }
        if ($tempMaxID > 0 && IPS_VariableExists($tempMaxID)) {
            $maxStr = str_replace('.', ',', (string)round((float)GetValue($tempMaxID), 1)) . '°';
        }

        // Einzeilig, Werte gleichmäßig in der Breite verteilt
        $html  = "<div class='out-bar {$barTheme}'>";
        $html .= "<span class='out-icon'>{$icon}</span>";
        $html .= "<div class='out-main'>";
        $html .= "<span class='out-label'>Außen</span>";
        $html .= "<span class='out-temp {$tempCls}'>{$tempStr}</span>";
        $html .= "</div>";

        $html .= "<div class='out-seg'><span class='out-comfort'>{$comfort}</span></div>";

        if ($minStr !== '' || $maxStr !== '') {
            $range  = $minStr !== '' ? "<span class='out-lo'>↓{$minStr}</span>" : '';
            $range .= $maxStr !== '' ? "<span class='out-hi'>↑{$maxStr}</span>" : '';
            $html  .= "<div class='out-seg out-range'>{$range}</div>";
        }
        if ($hum !== null) {
            $html .= "<div class='out-seg'><span class='out-hum'><i class='fa-solid fa-droplet'></i> {$hum}%</span></div>";
        }
        if ($dewPoint !== '') {
            $html .= "<div class='out-seg'><span class='out-dew'>{$dewPoint}</span></div>";
        }

        $html .= "</div>";
        return $html;
    }

    private function OutdoorComfortLabel(float $temp, ?int $hum): string
    {
        $isHumid = $hum !== null && $hum > 65;
        $isDry   = $hum !== null && $hum < 35;

        if ($temp > 30) { return $isHumid ? '🥵 Drückend' : '🔆 Sehr heiß'; }
        if ($temp > 25) { return $isHumid ? '😓 Schwül'   : '😎 Heiß'; }
        if ($temp > 20) { return $isDry   ? '😐 Trocken'  : '😊 Warm'; }
        if ($temp > 15) { return '🙂 Angenehm'; }
        if ($temp > 10) { return '🧥 Kühl'; }
        if ($temp > 5)  { return '🥶 Kalt'; }
        if ($temp > 0)  { return '🥶 Sehr kalt'; }
        return '❄️ Gefrierend';
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
            return "<div class='stat-bar'><span class='stat-ok'><i class='fa-solid fa-check'></i> Alles in Ordnung</span></div>";
        }

        $items = [];
        if ($lichterAn > 0)    { $items[] = "<span class='stat-al al-r'><i class='fa-solid fa-lightbulb'></i> {$lichterAn} an</span>"; }
        if ($fensterOffen > 0) { $items[] = "<span class='stat-al al-r'><i class='fa-solid fa-door-open'></i> {$fensterOffen} offen</span>"; }
        if ($tempWarn > 0)     { $items[] = "<span class='stat-al al-r'><i class='fa-solid fa-temperature-half'></i> {$tempWarn} Temp.</span>"; }
        if ($luftWarn > 0)     { $items[] = "<span class='stat-al al-y'><i class='fa-solid fa-wind'></i> {$luftWarn} Luft</span>"; }

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
                    $on   = (bool)$val;
                    $cls  = $on ? " class='al-r'" : '';
                    $text = $on ? 'an' : 'aus';
                } else {
                    $on   = $val > 0;
                    $cls  = $on ? " class='al-r'" : '';
                    $text = $on ? "{$val} an" : 'aus';
                }
                $icoL  = $on ? 'ico-on' : 'ico-muted';
                $stats .= "<span class='grp-stat'><i class='fa-solid fa-lightbulb {$icoL}'></i><span{$cls}>{$text}</span></span>";
            }

            // Fenster
            $fensterID = (int)($def['FensterID'] ?? 0);
            if ($fensterID > 0 && IPS_VariableExists($fensterID)) {
                $val     = GetValue($fensterID);
                $varType = IPS_GetVariable($fensterID)['VariableType'];
                if ($varType === 0) {
                    $open = (bool)$val;
                    $cls  = $open ? " class='al-r'" : '';
                    $text = $open ? 'offen' : 'zu';
                } else {
                    $open = $val > 0;
                    $cls  = $open ? " class='al-r'" : '';
                    $text = $open ? "{$val} offen" : 'alle zu';
                }
                $fenIcoH = $open ? 'fa-door-open' : 'fa-door-closed';
                $icoF    = $open ? 'ico-alert' : 'ico-muted';
                $stats  .= "<span class='grp-stat'><i class='fa-solid {$fenIcoH} {$icoF}'></i><span{$cls}>{$text}</span></span>";
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
                $stats .= "<span class='grp-stat'><i class='fa-solid fa-bars'></i><span{$cls}>{$text}</span></span>";
            }
        }

        // „✓ OK" wenn keine Alarme in diesem Bereich und auch keine Def-Stats vorhanden
        if ($stats === '' && !empty($raeume) && !$this->HasBereichAlarm($raeume)) {
            $stats = "<span class='grp-ok'><i class='fa-solid fa-check'></i> alles ok</span>";
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
            $isLichtAn  = $on;
            $cls        = $on ? " class='al-r'" : '';
            $text       = $on ? 'an' : 'aus';
            $icoLicht   = $on ? 'ico-on' : 'ico-muted';
            $lichtHTML  = "<span class='p-ico'><i class='fa-solid fa-lightbulb {$icoLicht}'></i></span><span{$cls}>{$text}</span>";
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
            $fenIco       = $open ? 'fa-door-open' : 'fa-door-closed';
            $icoFen       = $open ? 'ico-alert' : 'ico-muted';
            $fensterHTML  = "<span class='p-ico'><i class='fa-solid {$fenIco} {$icoFen}'></i></span><span{$cls}>{$text}</span>";
        }

        // ── Luftfeuchtigkeit ──────────────────────────────────────────
        $humID  = (int)($raum['HumID'] ?? 0);
        $hasHum = $humID > 0 && IPS_VariableExists($humID);
        $humHTML = '';
        if ($hasHum) {
            $val     = (int)round((float)GetValue($humID));
            $alarm   = ($val < 30 || $val > 60);
            $cls     = $alarm ? " class='al-r'" : '';
            $icoHum  = $alarm ? 'ico-alert' : 'ico-muted';
            $humHTML = "<span class='p-ico'><i class='fa-solid fa-droplet {$icoHum}'></i></span><span{$cls}>{$val}%</span>";
        }

        // ── CO₂ ───────────────────────────────────────────────────────
        $co2ID  = (int)($raum['CO2ID'] ?? 0);
        $hasCO2 = $co2ID > 0 && IPS_VariableExists($co2ID);
        $co2HTML = '';
        if ($hasCO2) {
            $val = (int)GetValue($co2ID);
            if ($val > 1400)      { $valCls = " class='al-r'"; $dotCls = 'dot-r'; $icoCO2 = 'ico-alert'; }
            elseif ($val >= 1000) { $valCls = " class='al-y'"; $dotCls = 'dot-y'; $icoCO2 = 'ico-warn'; }
            else                  { $valCls = '';               $dotCls = 'dot-g'; $icoCO2 = 'ico-muted'; }
            $co2HTML = "<span class='p-ico'><i class='fa-solid fa-wind {$icoCO2}'></i></span><span{$valCls}>{$val}</span><span class='co2dot {$dotCls}'></span>";
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
