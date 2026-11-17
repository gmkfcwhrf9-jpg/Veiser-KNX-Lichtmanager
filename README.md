# Veiser KNX Lichtmanager v1.0 (Testversion)

IP-Symcon Modul fuer KNX Lichtgruppen. Pro Lichtgruppe eine Instanz.

## Funktionen
- Schalten (DPT 1.001)
- Optional absolut dimmen 0-100 % (DPT 5.001)
- Optional Farbtemperatur in Kelvin (z.B. DPT 7.600, Controller pruefen)
- Optional RGB 24-bit Integer (DPT 232.600; Format des KNX-Objekts pruefen)
- RGB und Farbtemperatur sind gleichzeitig aktivierbar
- Separate Rueckmeldungsvariablen und Testmodus

## Installation
Repository in der IP-Symcon Modulverwaltung hinzufuegen und Modul aktualisieren. Instanz "Veiser KNX Lichtmanager" anlegen. Testmodus zunaechst aktiviert lassen.

## Visualisierung
Das Modul erzeugt Standardvariablen mit Aktionen. Die native Licht-Kachel in IP-Symcon muss in der Visualisierung eingerichtet und mit den passenden Variablen verknuepft werden. Das Modul erzeugt keine eigene HTML-Kachel und garantiert nicht automatisch die Darstellung des gezeigten kombinierten Licht-Rads.

## Wichtige Hinweise
- KNX-Variablen muessen passende Datentypen und Aktionen haben. IPS_RequestAction muss fuer den jeweiligen Datenpunkt funktionieren.
- Die RGB-Unterstuetzung gilt fuer einen 24-Bit RGB-Integer (0xRRGGBB). Separate R/G/B/W-Kanaele oder abweichende Formate sind noch nicht unterstuetzt.
- Die Modul-Variablen spiegeln bei Bedienung den Sollwert sofort wider; KNX-Rueckmeldungen aktualisieren den Wert spaeter.
- Beim Wechsel der KNX-Quellvariablen koennen alte Nachrichtenregistrierungen bestehen bleiben; diese werden bei Bedarf in einer Folgeversion bereinigt.
- Nicht ohne Test an produktiven KNX-Aktoren verwenden.

## Raum und Instanzname
Im Konfigurationsformular stehen Raum und Lichtgruppenname ganz oben. Der Lichtgruppenname wird beim Uebernehmen als Instanzname gesetzt. Der Raum wird als Eigenschaft gespeichert, eine automatische Kategoriezuordnung erfolgt noch nicht.
