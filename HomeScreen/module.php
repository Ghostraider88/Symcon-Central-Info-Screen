<?php

declare(strict_types=1);

class HomeScreen extends IPSModuleStrict
{
    private const MODULE_VERSION = '1.0.2';

    // -------------------------------------------------------------------------
    // Lifecycle
    // -------------------------------------------------------------------------

    public function Create(): void
    {
        parent::Create();

        $this->RegisterPropertyString('Bereiche',         '[]');
        $this->RegisterPropertyString('Raeume',           '[]');
        $this->RegisterPropertyString('Fahrzeuge',        '[]');
        $this->RegisterPropertyString('EnergieKacheln',   '[]');
        $this->RegisterPropertyString('KlimaGeraete',     '[]');
        $this->RegisterPropertyString('Bewaesserung',     '[]');
        $this->RegisterPropertyString('Lueftungsanlagen', '[]');
        $this->RegisterPropertyString('Waermepumpen',     '[]');

        $this->RegisterPropertyInteger('AussenTempID',    0);
        $this->RegisterPropertyInteger('AussenTempMinID', 0);
        $this->RegisterPropertyInteger('AussenTempMaxID', 0);
        $this->RegisterPropertyInteger('AussenHumID',     0);
        $this->RegisterPropertyInteger('WindRichtungID',  0);
        $this->RegisterPropertyInteger('WindBoenID',      0);
        $this->RegisterPropertyInteger('RegenRateID',     0);
        $this->RegisterPropertyInteger('RegenMenge24ID',  0);
        $this->RegisterPropertyInteger('TaupunktID',      0);
        $this->RegisterPropertyInteger('WetterwarnungID', 0);
        $this->RegisterPropertyInteger('UVID',           0);
        $this->RegisterPropertyInteger('OutdoorLinkID',   0);

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
            foreach (['LichtID', 'FensterID', 'TempID', 'HumID', 'CO2ID',
                      'Geraet1ID', 'Geraet2ID', 'Geraet3ID', 'Geraet4ID'] as $key) {
                $id = (int)($raum[$key] ?? 0);
                if ($id > 0 && IPS_VariableExists($id)) {
                    $varIDs[] = $id;
                    $this->RegisterReference($id);
                }
            }
        }

        foreach (['AussenTempID', 'AussenTempMinID', 'AussenTempMaxID', 'AussenHumID',
                  'WindRichtungID', 'WindBoenID', 'RegenRateID', 'RegenMenge24ID', 'TaupunktID', 'WetterwarnungID', 'UVID'] as $key) {
            $id = (int)$this->ReadPropertyInteger($key);
            if ($id > 0 && IPS_VariableExists($id)) {
                $varIDs[] = $id;
                $this->RegisterReference($id);
            }
        }
        $outdoorLinkID = (int)$this->ReadPropertyInteger('OutdoorLinkID');
        if ($outdoorLinkID > 0) {
            $this->RegisterReference($outdoorLinkID);
        }

        foreach (['Fahrzeuge', 'EnergieKacheln', 'KlimaGeraete', 'Bewaesserung', 'Lueftungsanlagen', 'Waermepumpen'] as $listKey) {
            $items = json_decode($this->ReadPropertyString($listKey), true) ?? [];
            foreach ($items as $item) {
                $linkID = (int)($item['LinkID'] ?? 0);
                if ($linkID > 0) {
                    $this->RegisterReference($linkID);
                }
                foreach (['SoCID', 'RangeID', 'ChargingID', 'ChargeMinID', 'ChargePowerID', 'StatusID',
                          'SolarID', 'VerbrauchID', 'NetzID', 'BatterieID',
                          'TempID', 'SollTempID', 'ModusID', 'VentilID',
                          'AktivID', 'NextStartID', 'LaufzeitID', 'BodenID', 'BedarfID', 'TagesRestID',
                          'LuefterID', 'LueftModusID', 'FrischluftID', 'ZuluftID', 'BetriebsartID',
                          'TempMitteID', 'TempObenID', 'KompressorID', 'HeizstabID'] as $key) {
                    $id = (int)($item[$key] ?? 0);
                    if ($id > 0 && IPS_VariableExists($id)) {
                        $varIDs[] = $id;
                        $this->RegisterReference($id);
                    }
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

        $content = $this->BuildContent($bereiche, $raeume);
        $footer  = 'v' . self::MODULE_VERSION . ' · Aktualisiert: ' . date('d.m.Y H:i:s');

        return $this->RenderTile($content, $footer);
    }

    private function GetUpdatePayload(): string
    {
        $bereiche = json_decode($this->ReadPropertyString('Bereiche'), true) ?? [];
        $raeume   = json_decode($this->ReadPropertyString('Raeume'), true) ?? [];

        return json_encode([
            'content' => $this->BuildContent($bereiche, $raeume),
            'footer'  => 'v' . self::MODULE_VERSION . ' · Aktualisiert: ' . date('d.m.Y H:i:s'),
        ]);
    }

    private function RenderTile(string $content, string $footer): string
    {
        $safeFooter = htmlspecialchars($footer);

        return <<<HTML
<meta name="viewport" content="width=device-width,initial-scale=1">
<script src="/icons.js"></script>
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
  .s-charging{border-left-color:#2196f3;}
  .s-active{border-left-color:#4caf50;}
  .s-dehumid{border-left-color:#00bcd4;}
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
  .ico-charging{color:#2196f3;}
  .ico-solar{color:#f5a623;}
  .ico-grid-in{color:#4caf50;}
  .ico-grid-out{color:#e53935;}
  .ico-active{color:#4caf50;}
  .al-r{color:#e53935;}
  .al-y{color:#e65c00;}
  .al-g{color:#4caf50;}
  .co2dot{display:inline-block;width:7px;height:7px;border-radius:50%;vertical-align:middle;margin-left:2px;flex-shrink:0;}
  .dot-g{background:#4caf50;}.dot-y{background:#e65c00;}.dot-r{background:#e53935;}
  .soc-track{background:rgba(0,0,0,0.10);border-radius:3px;height:4px;margin-top:4px;overflow:hidden;}
  .soc-fill{height:4px;border-radius:3px;transition:width 0.3s;}
  .soc-ok{background:#4caf50;}.soc-low{background:#ff9800;}.soc-crit{background:#f44336;}
  .trend-up{color:#e65c00;font-size:0.72em;vertical-align:middle;}
  .trend-dn{color:#5b9bd5;font-size:0.72em;vertical-align:middle;}
  .trend-st{color:var(--text-muted);font-size:0.72em;vertical-align:middle;}
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
  /* ── Mobile: Wetter-Bar 2-zeilig ────────────────────────── */
  @media(max-width:520px){
    .out-bar{padding:6px 10px;}
    .out-icon{font-size:1.25em;margin-right:7px;}
    .out-main{flex-basis:100%;border-right:none;padding-right:0;margin-right:0;padding-bottom:5px;margin-bottom:4px;border-bottom:1px solid var(--div-clr);}
    .out-temp{font-size:1.15em;}
    .out-seg{flex:0 0 auto;border-right:none;padding:2px 8px 0;}
    .out-seg:not(:last-child){border-right:1px solid var(--div-clr);}
  }
  /* ── Status & Footer ─────────────────────────────────────────────── */
  .stat-bar{display:flex;gap:10px;align-items:center;padding:4px 2px;margin-bottom:6px;font-size:0.83em;flex-wrap:wrap;}
  .stat-ok{color:#4caf50;font-weight:600;}
  .stat-al{display:flex;align-items:center;gap:3px;}
  .grp-ok{color:#4caf50;font-size:0.80em;font-weight:600;}
  .empty{color:var(--text-muted);padding:10px;font-size:0.9em;}
  .footer{margin-top:8px;font-size:0.67em;color:var(--footer);text-align:right;}
  .out-bar.clickable{cursor:pointer;}.out-bar.clickable:hover{filter:brightness(0.96);}
  .out-warn{display:inline-flex;align-items:center;gap:3px;padding:1px 6px;border-radius:3px;font-size:0.75em;font-weight:600;}
  .out-warn-0{background:#4caf50;color:#fff;}
  .out-warn-1{background:#f9a825;color:#333;}
  .out-warn-2{background:#e65c00;color:#fff;}
  .out-warn-3{background:#c62828;color:#fff;}
  .out-warn-4{background:#4a0000;color:#fff;}
  .out-warn-10{background:#e91e63;color:#fff;}
  .out-warn-11{background:#7b1fa2;color:#fff;}
  .out-warn-seg{flex-direction:column;align-items:flex-start;gap:3px;}
  .out-uv{display:inline-flex;align-items:center;gap:3px;padding:1px 6px;border-radius:3px;font-size:0.75em;font-weight:700;}
  .uv-low{background:#4caf50;color:#fff;}
  .uv-mid{background:#f9a825;color:#333;}
  .uv-high{background:#e65c00;color:#fff;}
  .uv-veryhigh{background:#e53935;color:#fff;}
  .uv-extreme{background:#7b1fa2;color:#fff;}
  .out-row2{display:flex;width:100%;margin-top:5px;padding-top:5px;border-top:1px solid var(--div-clr);}
  .out-row2 .out-seg{padding:0 4px;font-size:0.80em;}
  /* Icon-Symbole – kein externer CDN, kein Internet erforderlich */
  .fa-solid{font-style:normal;display:inline-block;line-height:1;}
  .fa-solid::before{font-family:system-ui,'Segoe UI Symbol','Apple Symbols','Noto Sans',sans-serif;}
  .fa-check::before{content:"✓";}
  .fa-lightbulb::before{content:"◉";}
  .fa-door-open::before{content:"⊏";}
  .fa-door-closed::before{content:"⊐";}
  .fa-temperature-half::before{content:"▾";}
  .fa-temperature-high::before{content:"▴";}
  .fa-wind::before{content:"≈";}
  .fa-bars::before{content:"≡";}
  .fa-plug::before{content:"⊓";}
  .fa-car::before{content:"▶";}
  .fa-bolt::before{content:"↯";}
  .fa-road::before{content:"↕";}
  .fa-sun::before{content:"✦";}
  .fa-house::before{content:"⌂";}
  .fa-plug-circle-bolt::before{content:"⊛";}
  .fa-battery-half::before{content:"▬";}
  .fa-sliders::before{content:"≣";}
  .fa-circle-half-stroke::before{content:"◑";}
  .fa-fan::before{content:"✧";}
  .fa-droplet::before{content:"◉";}
  .fa-clock::before{content:"◔";}
  .fa-hourglass-half::before{content:"▽";}
  .fa-chart-simple::before{content:"▲";}
  .fa-calendar::before{content:"⊟";}
  .fa-seedling::before{content:"✿";}
  .fa-arrow-right-to-bracket::before{content:"→";}
  .fa-arrow-right-from-bracket::before{content:"←";}
  .fa-gear::before{content:"⚙";font-variant-emoji:text;}
  .fa-compass::before{content:"⊕";}
  .fa-cloud-rain::before{content:"≈";}
  .fa-cloud-showers-heavy::before{content:"≋";}
  .fa-shield-halved::before{content:"◈";}
  .fa-triangle-exclamation::before{content:"△";}
  .fa-arrow-right::before{content:"→";}
  .fa-arrow-trend-up::before{content:"↗";}
  .fa-arrow-trend-down::before{content:"↘";}
</style>
<div id="cis-content">{$content}</div>
<div id="cis-footer" class="footer">{$safeFooter}</div>
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
        $bereiche = json_decode($this->ReadPropertyString('Bereiche'), true) ?? [];
        $this->SortByPosition($bereiche);
        $bereichOptionen = [['caption' => '– kein Bereich –', 'value' => '']];
        foreach ($bereiche as $b) {
            $name = trim($b['Name'] ?? '');
            if ($name !== '') {
                $bereichOptionen[] = ['caption' => $name, 'value' => $name];
            }
        }

        $slotOptionen = [
            ['caption' => '– leer –',   'value' => ''],
            ['caption' => 'Temperatur', 'value' => 'temp'],
            ['caption' => 'Licht',      'value' => 'licht'],
            ['caption' => 'Fenster',    'value' => 'fenster'],
            ['caption' => 'Luftfeuchte','value' => 'hum'],
            ['caption' => 'CO₂',        'value' => 'co2'],
            ['caption' => 'Gerät 1',    'value' => 'geraet1'],
            ['caption' => 'Gerät 2',    'value' => 'geraet2'],
            ['caption' => 'Gerät 3',    'value' => 'geraet3'],
            ['caption' => 'Gerät 4',    'value' => 'geraet4'],
        ];

        return json_encode([
            'elements' => [
                [
                    'type'    => 'ExpansionPanel',
                    'caption' => 'Außen / Wetter',
                    'items'   => [
                        ['type' => 'SelectVariable', 'name' => 'AussenTempID',    'caption' => 'Außentemperatur'],
                        ['type' => 'SelectVariable', 'name' => 'AussenHumID',     'caption' => 'Außenluftfeuchtigkeit (optional)'],
                        ['type' => 'SelectVariable', 'name' => 'AussenTempMinID', 'caption' => 'Tages-Tiefstwert (optional)'],
                        ['type' => 'SelectVariable', 'name' => 'AussenTempMaxID', 'caption' => 'Tages-Höchstwert (optional)'],
                        ['type' => 'SelectVariable', 'name' => 'WindRichtungID',  'caption' => 'Windrichtung (optional)'],
                        ['type' => 'SelectVariable', 'name' => 'WindBoenID',      'caption' => 'Windböen km/h (optional)'],
                        ['type' => 'SelectVariable', 'name' => 'RegenRateID',     'caption' => 'Regenrate mm/h (optional)'],
                        ['type' => 'SelectVariable', 'name' => 'RegenMenge24ID',  'caption' => 'Regenmenge 24h mm (optional)'],
                        ['type' => 'SelectVariable', 'name' => 'TaupunktID',      'caption' => 'Taupunkt °C (optional, sonst berechnet)'],
                        ['type' => 'SelectVariable', 'name' => 'WetterwarnungID', 'caption' => 'Wetterwarnung (Integer 0–13, optional)'],
                        ['type' => 'SelectVariable', 'name' => 'UVID',           'caption' => 'UV-Index (Integer 0–11+, optional)'],
                        ['type' => 'SelectObject',   'name' => 'OutdoorLinkID',   'caption' => 'Navigation (Klick, optional)'],
                    ],
                ],
                [
                    'type'    => 'ExpansionPanel',
                    'caption' => 'Bereiche / Stockwerke',
                    'items'   => [[
                        'type'     => 'List',
                        'name'     => 'Bereiche',
                        'caption'  => 'Bereiche (Reihenfolge über Pos.-Nummer)',
                        'add'      => true,
                        'delete'   => true,
                        'rowCount' => 8,
                        'columns'  => [
                            ['caption' => 'Pos.',                'name' => 'Position',  'width' => '50px',  'add' => 0,               'edit' => ['type' => 'NumberSpinner', 'minimum' => 0, 'maximum' => 999]],
                            ['caption' => 'Name',                'name' => 'Name',      'width' => '120px', 'add' => 'Neues Stockwerk','edit' => ['type' => 'ValidationTextBox']],
                            ['caption' => 'Navigation (Klick)',  'name' => 'LinkID',    'width' => '150px', 'add' => 0,               'edit' => ['type' => 'SelectObject']],
                            ['caption' => 'Licht (Anzahl/Bool)', 'name' => 'LichtID',   'width' => '140px', 'add' => 0,               'edit' => ['type' => 'SelectVariable']],
                            ['caption' => 'Fenster (Anzahl)',    'name' => 'FensterID', 'width' => '140px', 'add' => 0,               'edit' => ['type' => 'SelectVariable']],
                            ['caption' => 'Rolladen (Anzahl)',   'name' => 'RolladenID','width' => '140px', 'add' => 0,               'edit' => ['type' => 'SelectVariable']],
                        ],
                    ]],
                ],
                [
                    'type'     => 'ExpansionPanel',
                    'caption'  => 'Räume',
                    'expanded' => true,
                    'items'    => [[
                        'type'     => 'List',
                        'name'     => 'Raeume',
                        'caption'  => 'Räume (Reihenfolge über Pos.-Nummer)',
                        'add'      => true,
                        'delete'   => true,
                        'rowCount' => 8,
                        'columns'  => [
                            ['caption' => 'Pos.',             'name' => 'Position',    'width' => '50px',  'add' => 0,          'edit' => ['type' => 'NumberSpinner', 'minimum' => 0, 'maximum' => 999]],
                            ['caption' => 'Bereich',          'name' => 'Bereich',     'width' => '110px', 'add' => '',         'edit' => ['type' => 'Select', 'options' => $bereichOptionen]],
                            ['caption' => 'Raumname',         'name' => 'Name',        'width' => '110px', 'add' => 'Neuer Raum','edit' => ['type' => 'ValidationTextBox']],
                            ['caption' => 'Navigation',       'name' => 'LinkID',      'width' => '120px', 'add' => 0,          'edit' => ['type' => 'SelectObject']],
                            ['caption' => 'Licht',            'name' => 'LichtID',     'width' => '110px', 'add' => 0,          'edit' => ['type' => 'SelectVariable']],
                            ['caption' => 'Inv.',             'name' => 'LichtInvert', 'width' => '40px',  'add' => false,      'edit' => ['type' => 'CheckBox']],
                            ['caption' => 'Fenster',          'name' => 'FensterID',   'width' => '110px', 'add' => 0,          'edit' => ['type' => 'SelectVariable']],
                            ['caption' => 'Inv.',             'name' => 'FensterInvert','width'=> '40px',  'add' => false,      'edit' => ['type' => 'CheckBox']],
                            ['caption' => 'Temperatur (°C)',  'name' => 'TempID',      'width' => '110px', 'add' => 0,          'edit' => ['type' => 'SelectVariable']],
                            ['caption' => 'Luftfeuchte (%)',  'name' => 'HumID',       'width' => '110px', 'add' => 0,          'edit' => ['type' => 'SelectVariable']],
                            ['caption' => 'CO₂ (ppm)',        'name' => 'CO2ID',       'width' => '100px', 'add' => 0,          'edit' => ['type' => 'SelectVariable']],
                            ['caption' => 'Slot 1',           'name' => 'Slot1',       'width' => '90px',  'add' => 'licht',    'edit' => ['type' => 'Select', 'options' => $slotOptionen]],
                            ['caption' => 'Slot 2',           'name' => 'Slot2',       'width' => '90px',  'add' => 'fenster',  'edit' => ['type' => 'Select', 'options' => $slotOptionen]],
                            ['caption' => 'Slot 3',           'name' => 'Slot3',       'width' => '90px',  'add' => 'hum',      'edit' => ['type' => 'Select', 'options' => $slotOptionen]],
                            ['caption' => 'Slot 4',           'name' => 'Slot4',       'width' => '90px',  'add' => 'co2',      'edit' => ['type' => 'Select', 'options' => $slotOptionen]],
                            ['caption' => 'Gerät 1',          'name' => 'Geraet1ID',   'width' => '110px', 'add' => 0,          'edit' => ['type' => 'SelectVariable']],
                            ['caption' => 'Gerät 1 Label',    'name' => 'Geraet1Name', 'width' => '100px', 'add' => '',         'edit' => ['type' => 'ValidationTextBox']],
                            ['caption' => 'Gerät 2',          'name' => 'Geraet2ID',   'width' => '110px', 'add' => 0,          'edit' => ['type' => 'SelectVariable']],
                            ['caption' => 'Gerät 2 Label',    'name' => 'Geraet2Name', 'width' => '100px', 'add' => '',         'edit' => ['type' => 'ValidationTextBox']],
                            ['caption' => 'Gerät 3',          'name' => 'Geraet3ID',   'width' => '110px', 'add' => 0,          'edit' => ['type' => 'SelectVariable']],
                            ['caption' => 'Gerät 3 Label',    'name' => 'Geraet3Name', 'width' => '100px', 'add' => '',         'edit' => ['type' => 'ValidationTextBox']],
                            ['caption' => 'Gerät 4',          'name' => 'Geraet4ID',   'width' => '110px', 'add' => 0,          'edit' => ['type' => 'SelectVariable']],
                            ['caption' => 'Gerät 4 Label',    'name' => 'Geraet4Name', 'width' => '100px', 'add' => '',         'edit' => ['type' => 'ValidationTextBox']],
                        ],
                    ]],
                ],
                [
                    'type'    => 'ExpansionPanel',
                    'caption' => 'Fahrzeuge (E-Auto)',
                    'items'   => [[
                        'type'     => 'List',
                        'name'     => 'Fahrzeuge',
                        'caption'  => 'Fahrzeuge',
                        'add'      => true,
                        'delete'   => true,
                        'rowCount' => 6,
                        'columns'  => [
                            ['caption' => 'Pos.',           'name' => 'Position',    'width' => '50px',  'add' => 0,           'edit' => ['type' => 'NumberSpinner', 'minimum' => 0, 'maximum' => 999]],
                            ['caption' => 'Bereich',        'name' => 'Bereich',     'width' => '110px', 'add' => '',          'edit' => ['type' => 'Select', 'options' => $bereichOptionen]],
                            ['caption' => 'Name',           'name' => 'Name',        'width' => '110px', 'add' => 'Fahrzeug',  'edit' => ['type' => 'ValidationTextBox']],
                            ['caption' => 'Navigation',     'name' => 'LinkID',      'width' => '120px', 'add' => 0,           'edit' => ['type' => 'SelectObject']],
                            ['caption' => 'Batteriestand%', 'name' => 'SoCID',       'width' => '120px', 'add' => 0,           'edit' => ['type' => 'SelectVariable']],
                            ['caption' => 'Reichweite (km)','name' => 'RangeID',     'width' => '120px', 'add' => 0,           'edit' => ['type' => 'SelectVariable']],
                            ['caption' => 'Status (Text)',  'name' => 'StatusID',    'width' => '120px', 'add' => 0,           'edit' => ['type' => 'SelectVariable']],
                            ['caption' => 'Lädt (Bool)',       'name' => 'ChargingID',    'width' => '110px', 'add' => 0, 'edit' => ['type' => 'SelectVariable']],
                            ['caption' => 'Restladezeit (Sek)','name'=> 'ChargeMinID',  'width' => '130px', 'add' => 0, 'edit' => ['type' => 'SelectVariable']],
                            ['caption' => 'Ladeleistung (W)',  'name'=> 'ChargePowerID','width' => '130px', 'add' => 0, 'edit' => ['type' => 'SelectVariable']],
                        ],
                    ]],
                ],
                [
                    'type'    => 'ExpansionPanel',
                    'caption' => 'Energie / Solar',
                    'items'   => [[
                        'type'     => 'List',
                        'name'     => 'EnergieKacheln',
                        'caption'  => 'Energie-Kacheln',
                        'add'      => true,
                        'delete'   => true,
                        'rowCount' => 6,
                        'columns'  => [
                            ['caption' => 'Pos.',            'name' => 'Position',   'width' => '50px',  'add' => 0,       'edit' => ['type' => 'NumberSpinner', 'minimum' => 0, 'maximum' => 999]],
                            ['caption' => 'Bereich',         'name' => 'Bereich',    'width' => '110px', 'add' => '',      'edit' => ['type' => 'Select', 'options' => $bereichOptionen]],
                            ['caption' => 'Name',            'name' => 'Name',       'width' => '110px', 'add' => 'Solar', 'edit' => ['type' => 'ValidationTextBox']],
                            ['caption' => 'Navigation',      'name' => 'LinkID',     'width' => '120px', 'add' => 0,       'edit' => ['type' => 'SelectObject']],
                            ['caption' => 'Solar (W)',       'name' => 'SolarID',    'width' => '110px', 'add' => 0,       'edit' => ['type' => 'SelectVariable']],
                            ['caption' => 'Verbrauch (W)',   'name' => 'VerbrauchID','width' => '110px', 'add' => 0,       'edit' => ['type' => 'SelectVariable']],
                            ['caption' => 'Netz (W)',        'name' => 'NetzID',     'width' => '110px', 'add' => 0,       'edit' => ['type' => 'SelectVariable']],
                            ['caption' => 'Batterie (%)',    'name' => 'BatterieID', 'width' => '110px', 'add' => 0,       'edit' => ['type' => 'SelectVariable']],
                        ],
                    ]],
                ],
                [
                    'type'    => 'ExpansionPanel',
                    'caption' => 'Klima / Thermostat',
                    'items'   => [[
                        'type'     => 'List',
                        'name'     => 'KlimaGeraete',
                        'caption'  => 'Klima-Geräte',
                        'add'      => true,
                        'delete'   => true,
                        'rowCount' => 6,
                        'columns'  => [
                            ['caption' => 'Pos.',            'name' => 'Position',  'width' => '50px',  'add' => 0,       'edit' => ['type' => 'NumberSpinner', 'minimum' => 0, 'maximum' => 999]],
                            ['caption' => 'Bereich',         'name' => 'Bereich',   'width' => '110px', 'add' => '',      'edit' => ['type' => 'Select', 'options' => $bereichOptionen]],
                            ['caption' => 'Name',            'name' => 'Name',      'width' => '110px', 'add' => 'Klima', 'edit' => ['type' => 'ValidationTextBox']],
                            ['caption' => 'Navigation',      'name' => 'LinkID',    'width' => '120px', 'add' => 0,       'edit' => ['type' => 'SelectObject']],
                            ['caption' => 'Ist-Temp (°C)',   'name' => 'TempID',    'width' => '110px', 'add' => 0,       'edit' => ['type' => 'SelectVariable']],
                            ['caption' => 'Soll-Temp (°C)',  'name' => 'SollTempID','width' => '110px', 'add' => 0,       'edit' => ['type' => 'SelectVariable']],
                            ['caption' => 'Modus (Text)',         'name' => 'ModusID',   'width' => '110px', 'add' => 0, 'edit' => ['type' => 'SelectVariable']],
                            ['caption' => 'Status (String)',      'name' => 'StatusID',  'width' => '110px', 'add' => 0, 'edit' => ['type' => 'SelectVariable']],
                            ['caption' => 'Lüftermodus (String)', 'name' => 'VentilID',  'width' => '110px', 'add' => 0, 'edit' => ['type' => 'SelectVariable']],
                        ],
                    ]],
                ],
                [
                    'type'    => 'ExpansionPanel',
                    'caption' => 'Bewässerung',
                    'items'   => [[
                        'type'     => 'List',
                        'name'     => 'Bewaesserung',
                        'caption'  => 'Bewässerungs-Zonen',
                        'add'      => true,
                        'delete'   => true,
                        'rowCount' => 6,
                        'columns'  => [
                            ['caption' => 'Pos.',              'name' => 'Position',   'width' => '50px',  'add' => 0,       'edit' => ['type' => 'NumberSpinner', 'minimum' => 0, 'maximum' => 999]],
                            ['caption' => 'Bereich',           'name' => 'Bereich',    'width' => '110px', 'add' => '',      'edit' => ['type' => 'Select', 'options' => $bereichOptionen]],
                            ['caption' => 'Name',              'name' => 'Name',       'width' => '110px', 'add' => 'Zone',  'edit' => ['type' => 'ValidationTextBox']],
                            ['caption' => 'Navigation',        'name' => 'LinkID',     'width' => '120px', 'add' => 0,       'edit' => ['type' => 'SelectObject']],
                            ['caption' => 'Aktiv (Bool)',      'name' => 'AktivID',    'width' => '110px', 'add' => 0,       'edit' => ['type' => 'SelectVariable']],
                            ['caption' => 'Nächster Start',       'name' => 'NextStartID','width' => '120px', 'add' => 0,       'edit' => ['type' => 'SelectVariable']],
                            ['caption' => 'Restlaufzeit (Sek)',   'name' => 'LaufzeitID', 'width' => '130px', 'add' => 0,       'edit' => ['type' => 'SelectVariable']],
                            ['caption' => 'Bodenfeuchte (%)',     'name' => 'BodenID',    'width' => '120px', 'add' => 0,       'edit' => ['type' => 'SelectVariable']],
                            ['caption' => 'Heutiger Bedarf',      'name' => 'BedarfID',   'width' => '130px', 'add' => 0,       'edit' => ['type' => 'SelectVariable']],
                            ['caption' => 'Heutige Restlaufzeit', 'name' => 'TagesRestID','width' => '140px', 'add' => 0,       'edit' => ['type' => 'SelectVariable']],
                        ],
                    ]],
                ],
                [
                    'type'    => 'ExpansionPanel',
                    'caption' => 'Lüftungsanlage',
                    'items'   => [[
                        'type'     => 'List',
                        'name'     => 'Lueftungsanlagen',
                        'caption'  => 'Lüftungsanlagen',
                        'add'      => true,
                        'delete'   => true,
                        'rowCount' => 6,
                        'columns'  => [
                            ['caption' => 'Pos.',             'name' => 'Position',     'width' => '50px',  'add' => 0,            'edit' => ['type' => 'NumberSpinner', 'minimum' => 0, 'maximum' => 999]],
                            ['caption' => 'Bereich',          'name' => 'Bereich',      'width' => '110px', 'add' => '',           'edit' => ['type' => 'Select', 'options' => $bereichOptionen]],
                            ['caption' => 'Name',             'name' => 'Name',         'width' => '110px', 'add' => 'Lüftung',    'edit' => ['type' => 'ValidationTextBox']],
                            ['caption' => 'Navigation',       'name' => 'LinkID',       'width' => '120px', 'add' => 0,            'edit' => ['type' => 'SelectObject']],
                            ['caption' => 'Lüfterstufe',      'name' => 'LuefterID',    'width' => '120px', 'add' => 0,            'edit' => ['type' => 'SelectVariable']],
                            ['caption' => 'Betriebsmodus',    'name' => 'LueftModusID', 'width' => '120px', 'add' => 0,            'edit' => ['type' => 'SelectVariable']],
                            ['caption' => 'Frischluft (°C)',  'name' => 'FrischluftID', 'width' => '120px', 'add' => 0,            'edit' => ['type' => 'SelectVariable']],
                            ['caption' => 'Zuluft (°C)',      'name' => 'ZuluftID',     'width' => '120px', 'add' => 0,            'edit' => ['type' => 'SelectVariable']],
                            ['caption' => 'Betriebsart',      'name' => 'BetriebsartID','width' => '120px', 'add' => 0,            'edit' => ['type' => 'SelectVariable']],
                        ],
                    ]],
                ],
                [
                    'type'    => 'ExpansionPanel',
                    'caption' => 'Warmwasser-Wärmepumpe',
                    'items'   => [[
                        'type'     => 'List',
                        'name'     => 'Waermepumpen',
                        'caption'  => 'Wärmepumpen',
                        'add'      => true,
                        'delete'   => true,
                        'rowCount' => 6,
                        'columns'  => [
                            ['caption' => 'Pos.',              'name' => 'Position',    'width' => '50px',  'add' => 0,              'edit' => ['type' => 'NumberSpinner', 'minimum' => 0, 'maximum' => 999]],
                            ['caption' => 'Bereich',           'name' => 'Bereich',     'width' => '110px', 'add' => '',             'edit' => ['type' => 'Select', 'options' => $bereichOptionen]],
                            ['caption' => 'Name',              'name' => 'Name',        'width' => '110px', 'add' => 'Wärmepumpe',   'edit' => ['type' => 'ValidationTextBox']],
                            ['caption' => 'Navigation',        'name' => 'LinkID',      'width' => '120px', 'add' => 0,              'edit' => ['type' => 'SelectObject']],
                            ['caption' => 'Temp. Mitte (°C)',  'name' => 'TempMitteID', 'width' => '130px', 'add' => 0,              'edit' => ['type' => 'SelectVariable']],
                            ['caption' => 'Temp. Oben (°C)',   'name' => 'TempObenID',  'width' => '130px', 'add' => 0,              'edit' => ['type' => 'SelectVariable']],
                            ['caption' => 'Zustand Kompressor','name' => 'KompressorID','width' => '140px', 'add' => 0,              'edit' => ['type' => 'SelectVariable']],
                            ['caption' => 'Zustand Heizstab',  'name' => 'HeizstabID',  'width' => '130px', 'add' => 0,              'edit' => ['type' => 'SelectVariable']],
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
        $fahrzeuge        = json_decode($this->ReadPropertyString('Fahrzeuge'),        true) ?? [];
        $energieKacheln   = json_decode($this->ReadPropertyString('EnergieKacheln'),   true) ?? [];
        $klimaGeraete     = json_decode($this->ReadPropertyString('KlimaGeraete'),     true) ?? [];
        $bewaesserung     = json_decode($this->ReadPropertyString('Bewaesserung'),     true) ?? [];
        $lueftungsanlagen = json_decode($this->ReadPropertyString('Lueftungsanlagen'), true) ?? [];
        $waermepumpen     = json_decode($this->ReadPropertyString('Waermepumpen'),     true) ?? [];

        foreach ($raeume          as &$r) { $r['__typ'] = 'raum'; }
        foreach ($fahrzeuge       as &$f) { $f['__typ'] = 'auto'; }
        foreach ($energieKacheln  as &$e) { $e['__typ'] = 'energie'; }
        foreach ($klimaGeraete    as &$k) { $k['__typ'] = 'klima'; }
        foreach ($bewaesserung    as &$b) { $b['__typ'] = 'wasser'; }
        foreach ($lueftungsanlagen as &$l) { $l['__typ'] = 'lueftung'; }
        foreach ($waermepumpen    as &$w) { $w['__typ'] = 'waermepumpe'; }
        unset($r, $f, $e, $k, $b, $l, $w);

        $alleItems = array_merge($raeume, $fahrzeuge, $energieKacheln, $klimaGeraete, $bewaesserung, $lueftungsanlagen, $waermepumpen);

        if (empty($bereiche) && empty($alleItems)) {
            return '<p class="empty">Keine Kacheln konfiguriert.</p>';
        }

        $this->SortByPosition($bereiche);
        $this->SortByPosition($alleItems);

        // Nur Räume für den globalen Status und HasBereichAlarm
        $nurRaeume = array_filter($alleItems, fn($x) => ($x['__typ'] ?? '') === 'raum');

        // Items nach Bereich gruppieren
        $itemGruppen    = [];
        $itemReihenfolge = [];
        foreach ($alleItems as $item) {
            $bName = trim($item['Bereich'] ?? '');
            if (!isset($itemGruppen[$bName])) {
                $itemReihenfolge[] = $bName;
                $itemGruppen[$bName] = [];
            }
            $itemGruppen[$bName][] = $item;
        }

        // Bereiche-Index
        $bereichIndex = [];
        foreach ($bereiche as $b) {
            $bereichIndex[trim($b['Name'] ?? '')] = $b;
        }

        // Ausgabe-Reihenfolge: konfigurierte Bereiche, dann unkonfigurierte
        $ausgabeReihenfolge = [];
        foreach ($bereiche as $b) {
            $ausgabeReihenfolge[] = trim($b['Name'] ?? '');
        }
        foreach ($itemReihenfolge as $name) {
            if (!in_array($name, $ausgabeReihenfolge, true)) {
                $ausgabeReihenfolge[] = $name;
            }
        }

        $html  = $this->BuildOutdoorBar();
        $html .= $this->BuildGlobalStatus(array_values($nurRaeume));

        foreach ($ausgabeReihenfolge as $bereichName) {
            $gruppeItems = $itemGruppen[$bereichName] ?? [];
            $bereichDef  = $bereichIndex[$bereichName] ?? null;

            if (empty($gruppeItems) && $bereichDef === null) {
                continue;
            }

            $raeumeFuerHeader = array_values(array_filter($gruppeItems, fn($x) => ($x['__typ'] ?? '') === 'raum'));

            $html .= "<div class='grp'>";
            $html .= $this->BuildBereichHeader($bereichName, $bereichDef, $raeumeFuerHeader);

            if (!empty($gruppeItems)) {
                $html .= "<div class='grid'>";
                foreach ($gruppeItems as $item) {
                    $html .= $this->BuildRoomCard($item);
                }
                $html .= "</div>";
            }

            $html .= "</div>";
        }

        return $html ?: '<p class="empty">Keine Kacheln konfiguriert.</p>';
    }

    private function BuildOutdoorBar(): string
    {
        $tempID        = (int)$this->ReadPropertyInteger('AussenTempID');
        $tempMinID     = (int)$this->ReadPropertyInteger('AussenTempMinID');
        $tempMaxID     = (int)$this->ReadPropertyInteger('AussenTempMaxID');
        $humID         = (int)$this->ReadPropertyInteger('AussenHumID');
        $windRichtID   = (int)$this->ReadPropertyInteger('WindRichtungID');
        $windBoenID    = (int)$this->ReadPropertyInteger('WindBoenID');
        $regenRateID   = (int)$this->ReadPropertyInteger('RegenRateID');
        $regen24ID     = (int)$this->ReadPropertyInteger('RegenMenge24ID');
        $warnID        = (int)$this->ReadPropertyInteger('WetterwarnungID');
        $linkID        = (int)$this->ReadPropertyInteger('OutdoorLinkID');

        if ($tempID === 0 || !IPS_VariableExists($tempID)) {
            return '';
        }

        $temp    = round((float)GetValue($tempID), 1);
        $tempStr = str_replace('.', ',', (string)$temp) . '°';
        $hum     = null;

        if ($humID > 0 && IPS_VariableExists($humID)) {
            $hum = (int)round((float)GetValue($humID));
        }

        if ($temp <= 0)      { $tempCls = 'out-cold'; $icon = '❄️';  $barTheme = 'out-theme-freeze'; }
        elseif ($temp <= 5)  { $tempCls = 'out-cold'; $icon = '🌨️'; $barTheme = 'out-theme-cold'; }
        elseif ($temp <= 10) { $tempCls = 'out-cool'; $icon = '🌥️'; $barTheme = 'out-theme-cool'; }
        elseif ($temp <= 15) { $tempCls = 'out-cool'; $icon = '⛅';  $barTheme = 'out-theme-cool'; }
        elseif ($temp <= 22) { $tempCls = '';          $icon = '🌤️'; $barTheme = 'out-theme-mild'; }
        elseif ($temp <= 28) { $tempCls = 'out-warm'; $icon = '☀️';  $barTheme = 'out-theme-warm'; }
        else                 { $tempCls = 'out-hot';  $icon = '🌡️'; $barTheme = 'out-theme-hot'; }

        $trendIcon = $this->GetTempTrend($tempID);
        $comfort   = $this->OutdoorComfortLabel($temp, $hum);

        $taupunktID = (int)$this->ReadPropertyInteger('TaupunktID');
        $dewPoint   = '';
        if ($taupunktID > 0 && IPS_VariableExists($taupunktID)) {
            $dp       = round((float)GetValue($taupunktID), 1);
            $dewPoint = 'Taupunkt ' . str_replace('.', ',', (string)$dp) . '°';
        } elseif ($hum !== null && $temp > 10) {
            $dp       = round($temp - ((100 - $hum) / 5.0), 1);
            $dewPoint = 'Taupunkt ' . str_replace('.', ',', (string)$dp) . '°';
        }

        $minStr = '';
        $maxStr = '';
        if ($tempMinID > 0 && IPS_VariableExists($tempMinID)) {
            $minStr = str_replace('.', ',', (string)round((float)GetValue($tempMinID), 1)) . '°';
        }
        if ($tempMaxID > 0 && IPS_VariableExists($tempMaxID)) {
            $maxStr = str_replace('.', ',', (string)round((float)GetValue($tempMaxID), 1)) . '°';
        }

        // Wetterwarnung
        $warnBadge = '';
        if ($warnID > 0 && IPS_VariableExists($warnID)) {
            $warnLevel = (int)GetValue($warnID);
            $warnText  = htmlspecialchars(GetValueFormatted($warnID));
            $warnCls   = match(true) {
                $warnLevel === 0                       => 'out-warn-0',
                $warnLevel === 1                       => 'out-warn-1',
                $warnLevel === 2                       => 'out-warn-2',
                $warnLevel === 3                       => 'out-warn-3',
                $warnLevel >= 4 && $warnLevel < 10     => 'out-warn-4',
                $warnLevel === 10                      => 'out-warn-10',
                $warnLevel >= 11                       => 'out-warn-11',
                default                                => 'out-warn-0',
            };
            $warnIcon  = $warnLevel === 0 ? 'fa-shield-halved' : 'fa-triangle-exclamation';
            $warnBadge = "<span class='out-warn {$warnCls}'><i class='fa-solid {$warnIcon}'></i> {$warnText}</span>";
        }

        // UV-Index
        $uvBadge = '';
        $uvID    = (int)$this->ReadPropertyInteger('UVID');
        if ($uvID > 0 && IPS_VariableExists($uvID)) {
            $uvVal   = (int)GetValue($uvID);
            $uvCls   = match(true) {
                $uvVal <= 2  => 'uv-low',
                $uvVal <= 5  => 'uv-mid',
                $uvVal <= 7  => 'uv-high',
                $uvVal <= 10 => 'uv-veryhigh',
                default      => 'uv-extreme',
            };
            $uvBadge = "<span class='out-uv {$uvCls}'>UV {$uvVal}</span>";
        }

        // Warn- + UV-Segment zusammenführen
        $warnHtml = '';
        if ($warnBadge !== '' || $uvBadge !== '') {
            $segCls   = ($warnBadge !== '' && $uvBadge !== '') ? ' out-warn-seg' : '';
            $warnHtml = "<div class='out-seg{$segCls}'>{$warnBadge}{$uvBadge}</div>";
        }

        // Klick / Navigation
        $clickAttr = $linkID > 0 ? " onclick='openObject({$linkID})'" : '';
        $clickCls  = $linkID > 0 ? ' clickable' : '';

        $html  = "<div class='out-bar {$barTheme}{$clickCls}'{$clickAttr}>";
        $html .= "<span class='out-icon'>{$icon}</span>";
        $html .= "<div class='out-main'>";
        $html .= "<span class='out-label'>Außen</span>";
        $html .= "<span class='out-temp {$tempCls}'>{$tempStr}{$trendIcon}</span>";
        $html .= "</div>";
        $html .= "<div class='out-seg'><span class='out-comfort'>{$comfort}</span></div>";

        if ($minStr !== '' || $maxStr !== '') {
            $range  = $minStr !== '' ? "<span class='out-lo'>↓{$minStr}</span>" : '';
            $range .= $maxStr !== '' ? "<span class='out-hi'>↑{$maxStr}</span>" : '';
            $html  .= "<div class='out-seg out-range'>{$range}</div>";
        }
        if ($hum !== null) {
            $html .= "<div class='out-seg'><span class='out-hum'>💧 {$hum}%</span></div>";
        }
        if ($dewPoint !== '') {
            $html .= "<div class='out-seg'><span class='out-dew'>{$dewPoint}</span></div>";
        }
        $html .= $warnHtml;

        // Zweite Zeile: Wind & Regen – gleiche Segment-Optik wie Zeile 1
        $row2 = '';
        if ($windRichtID > 0 && IPS_VariableExists($windRichtID)) {
            $row2 .= "<div class='out-seg'><i class='fa-solid fa-compass' style='margin-right:3px'></i>" . htmlspecialchars(GetValueFormatted($windRichtID)) . "</div>";
        }
        if ($windBoenID > 0 && IPS_VariableExists($windBoenID)) {
            $row2 .= "<div class='out-seg'><i class='fa-solid fa-wind' style='margin-right:3px'></i>" . htmlspecialchars(GetValueFormatted($windBoenID)) . "</div>";
        }
        if ($regenRateID > 0 && IPS_VariableExists($regenRateID)) {
            $row2 .= "<div class='out-seg'><i class='fa-solid fa-cloud-rain' style='margin-right:3px'></i>" . htmlspecialchars(GetValueFormatted($regenRateID)) . "</div>";
        }
        if ($regen24ID > 0 && IPS_VariableExists($regen24ID)) {
            $row2 .= "<div class='out-seg'><i class='fa-solid fa-cloud-showers-heavy' style='margin-right:3px'></i>24h: " . htmlspecialchars(GetValueFormatted($regen24ID)) . "</div>";
        }
        if ($row2 !== '') {
            $html .= "<div class='out-row2'>{$row2}</div>";
        }

        $html .= "</div>";
        return $html;
    }

    private function OutdoorComfortLabel(float $temp, ?int $hum): string
    {
        $isHumid = $hum !== null && $hum > 65;
        $isDry   = $hum !== null && $hum < 35;

        if ($temp > 30) { return $isHumid ? '🥵 Drückend'  : '🔆 Sehr heiß'; }
        if ($temp > 25) { return $isHumid ? '😓 Schwül'    : '😎 Heiß'; }
        if ($temp > 20) { return $isDry   ? '😐 Trocken'   : '😊 Warm'; }
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
            $lichtID = (int)($def['LichtID'] ?? 0);
            if ($lichtID > 0 && IPS_VariableExists($lichtID)) {
                $val     = GetValue($lichtID);
                $varInfo = @IPS_GetVariable($lichtID);
                $varType = $varInfo['VariableType'] ?? 0;
                if ($varType === 0) {
                    $on   = (bool)$val;
                    $cls  = $on ? " class='al-r'" : '';
                    $text = $on ? 'an' : 'aus';
                } else {
                    $on   = $val > 0;
                    $cls  = $on ? " class='al-r'" : '';
                    $text = $on ? "{$val} an" : 'aus';
                }
                $icoL   = $on ? 'ico-on' : 'ico-muted';
                $stats .= "<span class='grp-stat'><i class='fa-solid fa-lightbulb {$icoL}'></i><span{$cls}>{$text}</span></span>";
            }

            $fensterID = (int)($def['FensterID'] ?? 0);
            if ($fensterID > 0 && IPS_VariableExists($fensterID)) {
                $val     = GetValue($fensterID);
                $varInfo = @IPS_GetVariable($fensterID);
                $varType = $varInfo['VariableType'] ?? 0;
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

            $rolladenID = (int)($def['RolladenID'] ?? 0);
            if ($rolladenID > 0 && IPS_VariableExists($rolladenID)) {
                $val     = GetValue($rolladenID);
                $varInfo = @IPS_GetVariable($rolladenID);
                $varType = $varInfo['VariableType'] ?? 0;
                if ($varType === 0) {
                    $cls  = $val ? " class='al-r'" : '';
                    $text = $val ? 'offen' : 'zu';
                } else {
                    $formatted = htmlspecialchars(GetValueFormatted($rolladenID));
                    $cls  = $val > 0 ? " class='al-r'" : '';
                    $text = $formatted;
                }
                $stats .= "<span class='grp-stat'><i class='fa-solid fa-bars'></i><span{$cls}>{$text}</span></span>";
            }
        }

        if ($stats === '' && !empty($raeume) && !$this->HasBereichAlarm($raeume)) {
            $stats = "<span class='grp-ok'><i class='fa-solid fa-check'></i> alles ok</span>";
        }

        $linkID      = (int)(($def ?? [])['LinkID'] ?? 0);
        $clickCls    = $linkID > 0 ? ' clickable' : '';
        $clickAttr   = $linkID > 0 ? " onclick='openObject({$linkID})'" : '';
        $displayName = $name !== '' ? htmlspecialchars($name) : 'Ohne Bereich';

        return "<div class='grp-hdr{$clickCls}'{$clickAttr}>"
            . "<span class='grp-name'>{$displayName}</span>"
            . ($stats !== '' ? "<span class='grp-chips'>{$stats}</span>" : '')
            . "</div>";
    }

    // Dispatch-Funktion – leitet nach Typ weiter
    private function BuildRoomCard(array $item): string
    {
        return match($item['__typ'] ?? 'raum') {
            'auto'        => $this->BuildCard_Auto($item),
            'energie'     => $this->BuildCard_Energie($item),
            'klima'       => $this->BuildCard_Klima($item),
            'wasser'      => $this->BuildCard_Wasser($item),
            'lueftung'    => $this->BuildCard_Lueftung($item),
            'waermepumpe' => $this->BuildCard_Waermepumpe($item),
            default       => $this->BuildCard_Raum($item),
        };
    }

    // ── Raum-Kachel ────────────────────────────────────────────────────────────────────

    private function BuildCard_Raum(array $raum): string
    {
        $name = htmlspecialchars($raum['Name'] ?? 'Unbenannt');

        // Temperatur + Trend
        $tempStr   = '';
        $tempCls   = '';
        $trendIcon = '';
        $tempID    = (int)($raum['TempID'] ?? 0);
        if ($tempID > 0 && IPS_VariableExists($tempID)) {
            $val       = round((float)GetValue($tempID), 1);
            $tempCls   = ($val < 18 || $val > 25) ? ' al-r' : '';
            $tempStr   = str_replace('.', ',', (string)$val) . '°';
            $trendIcon = $this->GetTempTrend($tempID);
        }

        // Licht
        $lichtID   = (int)($raum['LichtID'] ?? 0);
        $isLichtAn = false;
        $lichtHTML = '';
        if ($lichtID > 0 && IPS_VariableExists($lichtID)) {
            $on = (bool)GetValue($lichtID);
            if ((bool)($raum['LichtInvert'] ?? false)) $on = !$on;
            $isLichtAn = $on;
            $cls       = $on ? " class='al-r'" : '';
            $text      = $on ? 'an' : 'aus';
            $icoLicht  = $on ? 'ico-on' : 'ico-muted';
            $lichtHTML = "<span class='p-ico'><i class='fa-solid fa-lightbulb {$icoLicht}'></i></span><span{$cls}>{$text}</span>";
        }

        // Fenster
        $fensterID    = (int)($raum['FensterID'] ?? 0);
        $isFensterAuf = false;
        $fensterHTML  = '';
        if ($fensterID > 0 && IPS_VariableExists($fensterID)) {
            $open = (bool)GetValue($fensterID);
            if ((bool)($raum['FensterInvert'] ?? false)) $open = !$open;
            $isFensterAuf = $open;
            $cls          = $open ? " class='al-r'" : '';
            $text         = $open ? 'offen' : 'zu';
            $fenIco       = $open ? 'fa-door-open' : 'fa-door-closed';
            $icoFen       = $open ? 'ico-alert' : 'ico-muted';
            $fensterHTML  = "<span class='p-ico'><i class='fa-solid {$fenIco} {$icoFen}'></i></span><span{$cls}>{$text}</span>";
        }

        // Luftfeuchtigkeit
        $humID   = (int)($raum['HumID'] ?? 0);
        $humHTML = '';
        if ($humID > 0 && IPS_VariableExists($humID)) {
            $val     = (int)round((float)GetValue($humID));
            $alarm   = ($val < 30 || $val > 60);
            $cls     = $alarm ? " class='al-r'" : '';
            $icoHum  = $alarm ? 'ico-alert' : 'ico-muted';
            $humHTML = "<span class='p-ico'><i class='fa-solid fa-droplet {$icoHum}'></i></span><span{$cls}>{$val}%</span>";
        }

        // CO₂
        $co2ID   = (int)($raum['CO2ID'] ?? 0);
        $co2HTML = '';
        if ($co2ID > 0 && IPS_VariableExists($co2ID)) {
            $val = (int)GetValue($co2ID);
            if ($val > 1400)      { $valCls = " class='al-r'"; $dotCls = 'dot-r'; $icoCO2 = 'ico-alert'; }
            elseif ($val >= 1000) { $valCls = " class='al-y'"; $dotCls = 'dot-y'; $icoCO2 = 'ico-warn'; }
            else                  { $valCls = '';               $dotCls = 'dot-g'; $icoCO2 = 'ico-muted'; }
            $co2HTML = "<span class='p-ico'><i class='fa-solid fa-wind {$icoCO2}'></i></span><span{$valCls}>{$val}</span><span class='co2dot {$dotCls}'></span>";
        }

        // Slot-Pool
        $slotPool = [
            'licht'   => $lichtHTML,
            'fenster' => $fensterHTML,
            'hum'     => $humHTML,
            'co2'     => $co2HTML,
            'temp'    => $tempStr !== '' ? "<span class='p-ico'><i class='fa-solid fa-temperature-half ico-muted'></i></span><span class='{$tempCls}'>{$tempStr}{$trendIcon}</span>" : '',
            'geraet1' => $this->RenderGeraet(1, $raum),
            'geraet2' => $this->RenderGeraet(2, $raum),
            'geraet3' => $this->RenderGeraet(3, $raum),
            'geraet4' => $this->RenderGeraet(4, $raum),
        ];

        // Slots lesen (Defaults: licht, fenster, hum, co2)
        $slots = [
            $raum['Slot1'] ?? 'licht',
            $raum['Slot2'] ?? 'fenster',
            $raum['Slot3'] ?? 'hum',
            $raum['Slot4'] ?? 'co2',
        ];

        $row1 = "<div class='p-row'>"
            . "<span class='p-cell'>" . ($slotPool[$slots[0]] ?? '') . "</span>"
            . "<span class='p-cell'>" . ($slotPool[$slots[1]] ?? '') . "</span>"
            . "</div>";
        $row2 = "<div class='p-row'>"
            . "<span class='p-cell'>" . ($slotPool[$slots[2]] ?? '') . "</span>"
            . "<span class='p-cell'>" . ($slotPool[$slots[3]] ?? '') . "</span>"
            . "</div>";

        if ($isFensterAuf)  { $stateClass = ' s-alert'; }
        elseif ($isLichtAn) { $stateClass = ' s-warn'; }
        else                { $stateClass = ''; }

        $head = "<div class='c-head'><span class='c-name'>{$name}</span>"
            . ($tempStr !== '' ? "<span class='c-temp{$tempCls}'>{$tempStr}{$trendIcon}</span>" : '')
            . "</div>";

        $linkID   = (int)($raum['LinkID'] ?? 0);
        $cardAttr = $linkID > 0
            ? "class='card{$stateClass} clickable' onclick='openObject({$linkID})'"
            : "class='card{$stateClass}'";

        return "<div {$cardAttr}>{$head}{$row1}{$row2}</div>";
    }

    private function GetTempTrend(int $varID): string
    {
        static $archiveID = null;
        if ($archiveID === null) {
            $ids       = IPS_GetInstanceListByModuleID('{43192F0B-135B-4CE7-A0A7-1475603F3060}');
            $archiveID = !empty($ids) ? (int)$ids[0] : 0;
        }
        if ($archiveID === 0) {
            return '';
        }
        if (!AC_GetLoggingStatus($archiveID, $varID)) {
            return '';
        }

        $now    = time();
        $values = AC_GetLoggedValues($archiveID, $varID, $now - 2 * 3600, $now, 0);
        $thresh = 0.8;

        // Sensor loggt nur bei Änderung → 2-Std.-Fenster zu leer → 6-Std.-Fenster probieren
        if (!is_array($values) || count($values) < 2) {
            $values = AC_GetLoggedValues($archiveID, $varID, $now - 6 * 3600, $now, 0);
            $thresh = 2.0;
        }

        // Immer noch < 2 Werte → Temperatur ist konstant stabil
        if (!is_array($values) || count($values) < 2) {
            return " <i class='fa-solid fa-arrow-right trend-st'></i>";
        }

        // AC_GetLoggedValues liefert newest-first: Index 0 = neuster, letzter = ältester
        $newest = (float)$values[0]['Value'];
        $oldest = (float)$values[count($values) - 1]['Value'];
        $delta  = $newest - $oldest;

        if ($delta >= $thresh) {
            return " <i class='fa-solid fa-arrow-trend-up trend-up'></i>";
        }
        if ($delta <= -$thresh) {
            return " <i class='fa-solid fa-arrow-trend-down trend-dn'></i>";
        }
        return " <i class='fa-solid fa-arrow-right trend-st'></i>";
    }

    private function RenderGeraet(int $nr, array $raum): string
    {
        $id    = (int)($raum["Geraet{$nr}ID"] ?? 0);
        $label = htmlspecialchars($raum["Geraet{$nr}Name"] ?? "Gerät {$nr}");
        if ($id === 0 || !IPS_VariableExists($id)) {
            return '';
        }
        $on  = (bool)GetValue($id);
        $cls = $on ? " class='al-r'" : '';
        $ico = $on ? 'ico-on' : 'ico-muted';
        return "<span class='p-ico'><i class='fa-solid fa-plug {$ico}'></i></span><span{$cls}>{$label}</span>";
    }

    // ── E-Auto-Kachel ─────────────────────────────────────────────────────────────────────

    private function BuildCard_Auto(array $item): string
    {
        $name = htmlspecialchars($item['Name'] ?? '');

        // SoC
        $socID  = (int)($item['SoCID'] ?? 0);
        $soc    = null;
        $socStr = '';
        $socCls = '';
        if ($socID > 0 && IPS_VariableExists($socID)) {
            $soc    = (int)GetValue($socID);
            $socCls = $soc < 20 ? ' al-r' : ($soc < 40 ? ' al-y' : '');
            $socStr = "{$soc}%";
        }

        // Reichweite
        $rangeID  = (int)($item['RangeID'] ?? 0);
        $rangeStr = ($rangeID > 0 && IPS_VariableExists($rangeID))
            ? (int)GetValue($rangeID) . ' km' : '';

        // Lädt?
        $chargingID = (int)($item['ChargingID'] ?? 0);
        $isCharging = $chargingID > 0 && IPS_VariableExists($chargingID)
            && (bool)GetValue($chargingID);

        // Restladezeit (Wert in Sekunden)
        $chargeMin   = '';
        $chargeMinID = (int)($item['ChargeMinID'] ?? 0);
        if ($isCharging && $chargeMinID > 0 && IPS_VariableExists($chargeMinID)) {
            $sec       = (int)GetValue($chargeMinID);
            $min       = (int)round($sec / 60);
            $chargeMin = ($min >= 60 ? (int)floor($min / 60) . 'h ' : '') . ($min % 60) . 'min';
        }

        // Ladeleistung kW
        $chargePowerStr  = '';
        $chargePowerID   = (int)($item['ChargePowerID'] ?? 0);
        if ($isCharging && $chargePowerID > 0 && IPS_VariableExists($chargePowerID)) {
            $watts          = (float)GetValue($chargePowerID);
            $kw             = round($watts / 1000, 1);
            $chargePowerStr = str_replace('.', ',', (string)$kw) . ' kW';
        }

        // Status
        $statusStr = '';
        $statusID  = (int)($item['StatusID'] ?? 0);
        if ($statusID > 0 && IPS_VariableExists($statusID)) {
            $statusStr = htmlspecialchars(GetValueFormatted($statusID));
        }

        if ($isCharging)         { $stateClass = ' s-charging'; }
        elseif (!empty($socCls)) { $stateClass = ' s-warn'; }
        else                     { $stateClass = ''; }

        $html  = "<div class='card{$stateClass}'>";
        $html .= "<div class='c-head'>";
        $html .= "<span class='c-name'>{$name}</span>";
        if ($socStr) {
            $html .= "<span class='c-temp{$socCls}'>🔋 {$socStr}</span>";
        }
        $html .= "</div>";

        if ($soc !== null) {
            $fillCls = $soc < 20 ? 'soc-crit' : ($soc < 40 ? 'soc-low' : 'soc-ok');
            $html   .= "<div class='soc-track'><div class='soc-fill {$fillCls}' style='width:{$soc}%'></div></div>";
        }

        if ($statusStr) {
            $html .= "<div class='p-row'><span class='p-cell'><span class='p-ico'><i class='fa-solid fa-car ico-muted'></i></span><span>{$statusStr}</span></span></div>";
        }
        if ($isCharging) {
            $parts     = array_filter([$chargePowerStr, $chargeMin]);
            $chargeTxt = $parts ? implode(' · ', $parts) : 'Lädt…';
            $html .= "<div class='p-row'><span class='p-cell'><span class='p-ico'><i class='fa-solid fa-bolt ico-charging'></i></span><span>{$chargeTxt}</span></span></div>";
        }
        if ($rangeStr) {
            $html .= "<div class='p-row'><span class='p-cell'><span class='p-ico'><i class='fa-solid fa-road ico-muted'></i></span><span>{$rangeStr}</span></span></div>";
        }

        $linkID = (int)($item['LinkID'] ?? 0);
        if ($linkID > 0) {
            $html = "<div class='card{$stateClass} clickable' onclick='openObject({$linkID})'>"
                . substr($html, strlen("<div class='card{$stateClass}'>"));
        }

        $html .= "</div>";
        return $html;
    }

    // ── Energie/Solar-Kachel ──────────────────────────────────────────────────────

    private function BuildCard_Energie(array $item): string
    {
        $name = htmlspecialchars($item['Name'] ?? '');

        $solarID     = (int)($item['SolarID']     ?? 0);
        $verbrauchID = (int)($item['VerbrauchID'] ?? 0);
        $netzID      = (int)($item['NetzID']      ?? 0);
        $batterieID  = (int)($item['BatterieID']  ?? 0);

        $solarW  = ($solarID > 0     && IPS_VariableExists($solarID))     ? (int)GetValue($solarID)     : null;
        $verbW   = ($verbrauchID > 0 && IPS_VariableExists($verbrauchID)) ? (int)GetValue($verbrauchID) : null;
        $netzW   = ($netzID > 0      && IPS_VariableExists($netzID))      ? (int)GetValue($netzID)      : null;
        $batPct  = ($batterieID > 0  && IPS_VariableExists($batterieID))  ? (int)GetValue($batterieID)  : null;

        $html  = "<div class='card'>";
        $html .= "<div class='c-head'><span class='c-name'>{$name}</span>";
        if ($solarW !== null) {
            $html .= "<span class='c-temp'><i class='fa-solid fa-sun ico-solar'></i> {$solarW} W</span>";
        }
        $html .= "</div>";

        if ($verbW !== null) {
            $html .= "<div class='p-row'><span class='p-cell'><span class='p-ico'><i class='fa-solid fa-house ico-muted'></i></span><span>{$verbW} W</span></span></div>";
        }
        if ($netzW !== null) {
            if ($netzW >= 0) {
                $netzCls = 'ico-grid-out'; // Bezug = rot
                $netzTxt = "+{$netzW} W";
            } else {
                $netzCls = 'ico-grid-in';  // Einspeisung = grün
                $netzTxt = "{$netzW} W";
            }
            $html .= "<div class='p-row'><span class='p-cell'><span class='p-ico'><i class='fa-solid fa-plug-circle-bolt {$netzCls}'></i></span><span>{$netzTxt}</span></span></div>";
        }
        if ($batPct !== null) {
            $fillCls = $batPct < 20 ? 'soc-crit' : ($batPct < 40 ? 'soc-low' : 'soc-ok');
            $html   .= "<div class='p-row'><span class='p-cell'><span class='p-ico'><i class='fa-solid fa-battery-half ico-muted'></i></span><span>{$batPct}%</span></span></div>";
            $html   .= "<div class='soc-track'><div class='soc-fill {$fillCls}' style='width:{$batPct}%'></div></div>";
        }

        $linkID = (int)($item['LinkID'] ?? 0);
        if ($linkID > 0) {
            $html = str_replace("<div class='card'>", "<div class='card clickable' onclick='openObject({$linkID})'>", $html);
        }

        $html .= "</div>";
        return $html;
    }

    // ── Klima/Thermostat-Kachel ─────────────────────────────────────────────────────

    private function BuildCard_Klima(array $item): string
    {
        $name = htmlspecialchars($item['Name'] ?? '');

        $tempID     = (int)($item['TempID']     ?? 0);
        $sollTempID = (int)($item['SollTempID'] ?? 0);
        $modusID    = (int)($item['ModusID']    ?? 0);
        $statusID   = (int)($item['StatusID']   ?? 0);
        $ventilID   = (int)($item['VentilID']   ?? 0);

        $istTemp  = ($tempID > 0     && IPS_VariableExists($tempID))     ? round((float)GetValue($tempID), 1)     : null;
        $sollTemp = ($sollTempID > 0 && IPS_VariableExists($sollTempID)) ? round((float)GetValue($sollTempID), 1) : null;
        $modus    = ($modusID > 0    && IPS_VariableExists($modusID))    ? htmlspecialchars(GetValueFormatted($modusID)) : null;

        $statusRaw     = ($statusID > 0 && IPS_VariableExists($statusID)) ? (string)GetValue($statusID) : null;
        $statusDisplay = ($statusID > 0 && IPS_VariableExists($statusID)) ? htmlspecialchars(GetValueFormatted($statusID)) : null;

        $lueftermodus = ($ventilID > 0 && IPS_VariableExists($ventilID))
            ? htmlspecialchars(GetValueFormatted($ventilID)) : null;

        $trendIcon = ($tempID > 0 && IPS_VariableExists($tempID)) ? $this->GetTempTrend($tempID) : '';
        $istStr  = $istTemp  !== null ? str_replace('.', ',', (string)$istTemp)  . '°' : '';
        $sollStr = $sollTemp !== null ? str_replace('.', ',', (string)$sollTemp) . '°' : '';

        // Border color driven by Status raw value (what the unit actually does)
        $stateClass = '';
        if ($statusRaw !== null) {
            $stateClass = match($statusRaw) {
                'heating'  => ' s-alert',
                'cooling'  => ' s-charging',
                'drying'   => ' s-dehumid',
                default    => '',  // off, idle, fan, automatic → kein Rand
            };
        }

        $html  = "<div class='card{$stateClass}'>";
        $html .= "<div class='c-head'><span class='c-name'>{$name}</span>";
        if ($istStr) {
            $html .= "<span class='c-temp'><i class='fa-solid fa-temperature-half ico-muted'></i> {$istStr}{$trendIcon}</span>";
        }
        $html .= "</div>";

        if ($sollStr) {
            $html .= "<div class='p-row'><span class='p-cell'><span class='p-ico'><i class='fa-solid fa-sliders ico-muted'></i></span><span>Soll: {$sollStr}</span></span></div>";
        }
        if ($statusDisplay !== null && $statusDisplay !== '') {
            $html .= "<div class='p-row'><span class='p-cell'><span class='p-ico'><i class='fa-solid fa-circle-half-stroke ico-muted'></i></span><span>{$statusDisplay}</span></span></div>";
        } elseif ($modus) {
            $html .= "<div class='p-row'><span class='p-cell'><span class='p-ico'><i class='fa-solid fa-circle-half-stroke ico-muted'></i></span><span>{$modus}</span></span></div>";
        }
        if ($lueftermodus !== null && $lueftermodus !== '') {
            $html .= "<div class='p-row'><span class='p-cell'><span class='p-ico'><i class='fa-solid fa-fan ico-muted'></i></span><span>{$lueftermodus}</span></span></div>";
        }

        $linkID = (int)($item['LinkID'] ?? 0);
        if ($linkID > 0) {
            $html = str_replace("<div class='card{$stateClass}'>", "<div class='card{$stateClass} clickable' onclick='openObject({$linkID})'>", $html);
        }

        $html .= "</div>";
        return $html;
    }

    // ── Bewässerungs-Kachel ─────────────────────────────────────────────────────────

    private function BuildCard_Wasser(array $item): string
    {
        $name = htmlspecialchars($item['Name'] ?? '');

        $aktivID     = (int)($item['AktivID']     ?? 0);
        $nextStartID = (int)($item['NextStartID'] ?? 0);
        $laufzeitID  = (int)($item['LaufzeitID']  ?? 0);
        $bodenID     = (int)($item['BodenID']     ?? 0);
        $bedarfID    = (int)($item['BedarfID']    ?? 0);
        $tagesRestID = (int)($item['TagesRestID'] ?? 0);

        $isAktiv   = ($aktivID > 0     && IPS_VariableExists($aktivID))     && (bool)GetValue($aktivID);
        $nextStr   = ($nextStartID > 0 && IPS_VariableExists($nextStartID)) ? htmlspecialchars(GetValueFormatted($nextStartID)) : null;
        $laufzeit  = ($laufzeitID > 0  && IPS_VariableExists($laufzeitID))  ? (int)GetValue($laufzeitID)  : null;
        $boden     = ($bodenID > 0     && IPS_VariableExists($bodenID))     ? (int)GetValue($bodenID)     : null;
        if ($bedarfID > 0 && IPS_VariableExists($bedarfID)) {
            $bedarfSec = (int)GetValue($bedarfID);
            $bedarfMin = (int)round($bedarfSec / 60);
            $bedarfStr = ($bedarfMin >= 60 ? (int)floor($bedarfMin / 60) . 'h ' : '') . ($bedarfMin % 60) . 'min';
        } else {
            $bedarfStr = null;
        }
        $tagesRest = ($tagesRestID > 0 && IPS_VariableExists($tagesRestID)) ? (int)GetValue($tagesRestID) : null;

        $stateClass = $isAktiv ? ' s-active' : '';

        $html  = "<div class='card{$stateClass}'>";
        $html .= "<div class='c-head'><span class='c-name'>{$name}</span>";
        if ($isAktiv) {
            $html .= "<span class='c-temp al-g'><i class='fa-solid fa-droplet'></i> aktiv</span>";
        }
        $html .= "</div>";

        if ($isAktiv && $laufzeit !== null && $laufzeit > 0) {
            $restMin = (int)round($laufzeit / 60);
            $restStr = ($restMin >= 60 ? (int)floor($restMin / 60) . 'h ' : '') . ($restMin % 60) . 'min';
            $html   .= "<div class='p-row'><span class='p-cell'><span class='p-ico'><i class='fa-solid fa-clock ico-active'></i></span><span>noch {$restStr}</span></span></div>";
        }
        if ($tagesRest !== null) {
            $tagesMin = (int)round($tagesRest / 60);
            $tagesStr = ($tagesMin >= 60 ? (int)floor($tagesMin / 60) . 'h ' : '') . ($tagesMin % 60) . 'min';
            $html    .= "<div class='p-row'><span class='p-cell'><span class='p-ico'><i class='fa-solid fa-hourglass-half ico-muted'></i></span><span>Heute noch: {$tagesStr}</span></span></div>";
        }
        if ($bedarfStr !== null) {
            $html .= "<div class='p-row'><span class='p-cell'><span class='p-ico'><i class='fa-solid fa-chart-simple ico-muted'></i></span><span>Bedarf: {$bedarfStr}</span></span></div>";
        }
        if ($nextStr) {
            $html .= "<div class='p-row'><span class='p-cell'><span class='p-ico'><i class='fa-solid fa-calendar ico-muted'></i></span><span>{$nextStr}</span></span></div>";
        }
        if ($boden !== null) {
            $bodenCls = $boden < 30 ? 'ico-warn' : 'ico-muted';
            $html    .= "<div class='p-row'><span class='p-cell'><span class='p-ico'><i class='fa-solid fa-seedling {$bodenCls}'></i></span><span>Boden: {$boden}%</span></span></div>";
        }

        $linkID = (int)($item['LinkID'] ?? 0);
        if ($linkID > 0) {
            $html = str_replace("<div class='card{$stateClass}'>", "<div class='card{$stateClass} clickable' onclick='openObject({$linkID})'>", $html);
        }

        $html .= "</div>";
        return $html;
    }

    // ── Lüftungsanlage-Kachel ─────────────────────────────────────────────────

    private function BuildCard_Lueftung(array $item): string
    {
        $name = htmlspecialchars($item['Name'] ?? '');

        $luefterID     = (int)($item['LuefterID']     ?? 0);
        $lueftModusID  = (int)($item['LueftModusID']  ?? 0);
        $frischluftID  = (int)($item['FrischluftID']  ?? 0);
        $zuluftID      = (int)($item['ZuluftID']      ?? 0);
        $betriebsartID = (int)($item['BetriebsartID'] ?? 0);

        $luefter     = ($luefterID > 0     && IPS_VariableExists($luefterID))     ? (int)GetValue($luefterID)                                   : null;
        $modus       = ($lueftModusID > 0  && IPS_VariableExists($lueftModusID))  ? htmlspecialchars(GetValueFormatted($lueftModusID))           : null;
        $frischluft  = ($frischluftID > 0  && IPS_VariableExists($frischluftID))  ? round((float)GetValue($frischluftID), 1)                     : null;
        $zuluft      = ($zuluftID > 0      && IPS_VariableExists($zuluftID))      ? round((float)GetValue($zuluftID), 1)                         : null;
        $betriebsart = ($betriebsartID > 0 && IPS_VariableExists($betriebsartID)) ? htmlspecialchars(GetValueFormatted($betriebsartID))          : null;

        $isActive   = $luefter !== null && $luefter > 0;
        $stateClass = $isActive ? ' s-active' : '';
        $fanCls     = $isActive ? 'ico-active' : 'ico-muted';

        $html  = "<div class='card{$stateClass}'>";
        $html .= "<div class='c-head'><span class='c-name'>{$name}</span>";
        if ($luefter !== null) {
            $html .= "<span class='c-temp'><i class='fa-solid fa-fan {$fanCls}'></i> Stufe {$luefter}</span>";
        }
        $html .= "</div>";

        if ($modus) {
            $html .= "<div class='p-row'><span class='p-cell'><span class='p-ico'><i class='fa-solid fa-circle-half-stroke ico-muted'></i></span><span>{$modus}</span></span></div>";
        }
        if ($betriebsart) {
            $html .= "<div class='p-row'><span class='p-cell'><span class='p-ico'><i class='fa-solid fa-sliders ico-muted'></i></span><span>{$betriebsart}</span></span></div>";
        }
        if ($frischluft !== null || $zuluft !== null) {
            $frStr = $frischluft !== null ? str_replace('.', ',', (string)$frischluft) . '°' : '–';
            $zuStr = $zuluft     !== null ? str_replace('.', ',', (string)$zuluft)     . '°' : '–';
            $html .= "<div class='p-row'>"
                . "<span class='p-cell'><span class='p-ico'><i class='fa-solid fa-arrow-right-to-bracket ico-muted'></i></span><span>{$frStr}</span></span>"
                . "<span class='p-cell'><span class='p-ico'><i class='fa-solid fa-arrow-right-from-bracket ico-muted'></i></span><span>{$zuStr}</span></span>"
                . "</div>";
        }

        $linkID = (int)($item['LinkID'] ?? 0);
        if ($linkID > 0) {
            $html = str_replace("<div class='card{$stateClass}'>", "<div class='card{$stateClass} clickable' onclick='openObject({$linkID})'>", $html);
        }

        $html .= "</div>";
        return $html;
    }

    // ── Warmwasser-Wärmepumpe-Kachel ──────────────────────────────────────────

    private function BuildCard_Waermepumpe(array $item): string
    {
        $name = htmlspecialchars($item['Name'] ?? '');

        $tempMitteID  = (int)($item['TempMitteID']  ?? 0);
        $tempObenID   = (int)($item['TempObenID']   ?? 0);
        $kompressorID = (int)($item['KompressorID'] ?? 0);
        $heizstabID   = (int)($item['HeizstabID']   ?? 0);

        $tempMitte  = ($tempMitteID > 0  && IPS_VariableExists($tempMitteID))  ? round((float)GetValue($tempMitteID), 1)                    : null;
        $tempOben   = ($tempObenID > 0   && IPS_VariableExists($tempObenID))   ? round((float)GetValue($tempObenID), 1)                     : null;
        $kompStr    = ($kompressorID > 0 && IPS_VariableExists($kompressorID)) ? htmlspecialchars(GetValueFormatted($kompressorID))          : null;
        $heizStr    = ($heizstabID > 0   && IPS_VariableExists($heizstabID))   ? htmlspecialchars(GetValueFormatted($heizstabID))            : null;
        $isKompAn   = ($kompressorID > 0 && IPS_VariableExists($kompressorID)) && (bool)GetValue($kompressorID);
        $isHzAn     = ($heizstabID > 0   && IPS_VariableExists($heizstabID))   && (bool)GetValue($heizstabID);

        if ($isKompAn)   { $stateClass = ' s-charging'; }  // blau = Kompressor aktiv
        elseif ($isHzAn) { $stateClass = ' s-warn'; }      // orange = Heizstab aktiv
        else             { $stateClass = ''; }

        $tempObenStr  = $tempOben  !== null ? str_replace('.', ',', (string)$tempOben)  . '°' : '';
        $tempMitteStr = $tempMitte !== null ? str_replace('.', ',', (string)$tempMitte) . '°' : '';

        $html  = "<div class='card{$stateClass}'>";
        $html .= "<div class='c-head'><span class='c-name'>{$name}</span>";
        if ($tempObenStr) {
            $html .= "<span class='c-temp'><i class='fa-solid fa-temperature-high ico-muted'></i> {$tempObenStr}</span>";
        }
        $html .= "</div>";

        if ($tempMitteStr) {
            $html .= "<div class='p-row'><span class='p-cell'><span class='p-ico'><i class='fa-solid fa-temperature-half ico-muted'></i></span><span>Mitte: {$tempMitteStr}</span></span></div>";
        }
        if ($kompStr !== null) {
            $kCls = $isKompAn ? 'ico-charging' : 'ico-muted';
            $kTxt = $isKompAn ? " class='ico-charging'" : '';
            $html .= "<div class='p-row'><span class='p-cell'><span class='p-ico'><i class='fa-solid fa-gear {$kCls}'></i></span><span{$kTxt}>{$kompStr}</span></span></div>";
        }
        if ($heizStr !== null) {
            $hCls = $isHzAn ? 'ico-warn' : 'ico-muted';
            $hTxt = $isHzAn ? " class='al-y'" : '';
            $html .= "<div class='p-row'><span class='p-cell'><span class='p-ico'><i class='fa-solid fa-bolt {$hCls}'></i></span><span{$hTxt}>{$heizStr}</span></span></div>";
        }

        $linkID = (int)($item['LinkID'] ?? 0);
        if ($linkID > 0) {
            $html = str_replace("<div class='card{$stateClass}'>", "<div class='card{$stateClass} clickable' onclick='openObject({$linkID})'>", $html);
        }

        $html .= "</div>";
        return $html;
    }
}
