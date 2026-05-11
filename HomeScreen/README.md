# HomeScreen

Visualisierungs-Kachel für IP-Symcon ≥ 8.1 — zeigt Räume, E-Autos, Energie, Klima und Bewässerung als kompakte Kachel-Übersicht, gruppiert nach Stockwerken/Bereichen.

## Modul-Informationen

| Eigenschaft | Wert |
|---|---|
| Modul-GUID | `{D2E7F94A-3B16-4C8E-A591-7F0D2B3E5A8C}` |
| Präfix | `HomeScreen` |
| Typ | Visualisierung (Typ 3) |
| Version | 1.0.0 |

## Konfiguration

Die Einstellungen erfolgen über die Instanz-Konfiguration mit getrennten Panels pro Kacheltyp:

- **Außen / Wetter** – Außentemperatur, Luftfeuchte, Min/Max
- **Bereiche / Stockwerke** – Gruppierung mit Aggregat-Variablen
- **Räume** – Sensoren, 4 Anzeige-Slots, bis zu 4 schaltbare Geräte
- **Fahrzeuge** – E-Auto-Daten (SoC, Reichweite, Ladestatus)
- **Energie / Solar** – Produktion, Verbrauch, Netz, Batterie
- **Klima / Thermostat** – Ist-/Solltemperatur, Modus, Ventil
- **Bewässerung** – Aktivstatus, Laufzeit, Bodenfeuchte

Ausführliche Dokumentation: [README im Repository-Root](../README.md)

## Öffentliche Funktionen

| Funktion | Beschreibung |
|---|---|
| `HomeScreen_Update($id)` | Sofortige Aktualisierung der Kachel-Ansicht |
