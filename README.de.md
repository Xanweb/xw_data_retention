# Xanweb Datenaufbewahrung

Dieses eigenständige ConcreteCMS-Package legt getrennte Aufbewahrungsfristen für gespeicherte Formularantworten und optional für Datenbankprotokolle fest. Es ändert weder Seiteninhalte noch Core-Dateien. Kompatibel mit Concrete CMS ab 9.3.0; geprüft mit 9.3.0 und 9.5.7.

Dieses kostenlose Addon steht unter der MIT-Lizenz; siehe `LICENSE.txt`.

## Installation

1. ZIP so entpacken, dass `xw_data_retention/controller.php` im Verzeichnis `packages/` der Website liegt.
2. Unter **Verwaltung → Concrete erweitern → Installieren** das Package **Xanweb Datenaufbewahrung** installieren.
3. **Verwaltung → System und Einstellungen → Automatisierung → Datenaufbewahrung** öffnen.

Die Installation löscht keine Einträge.

## Fristen einstellen

Alle Datenarten stehen zunächst auf **Nie automatisch löschen**. Wählbar sind 1, 2, 3, 6, 12, 24 oder 36 Monate, jeweils getrennt für:

- gespeicherte Antworten aus Express-Formularblöcken;
- gespeicherte Antworten aus klassischen Formularblöcken;
- optional Einträge in der ConcreteCMS-Datenbanktabelle `Logs`.

Eine Frist über null muss beim Speichern ausdrücklich bestätigt werden. Die Einstellungsseite zeigt die Zahl der derzeit abgelaufenen Einträge. Formularantworten werden innerhalb eines Aufgabendurchlaufs in 200er-Blöcken entfernt, bis keine abgelaufenen Einträge mehr vorhanden sind oder das Zeitlimit erreicht ist (die Hälfte der eingestellten PHP-Ausführungszeit, ohne Limit höchstens fünf Minuten). Die vom laufenden ConcreteCMS-Task verwalteten Objekte bleiben dabei eingebunden. Datenbankprotokolle werden in SQL-Blöcken von 5.000 Einträgen gelöscht, bis keine abgelaufenen Einträge mehr vorhanden sind. Eine Löschung lässt sich nur aus einer vorherigen Sicherung wiederherstellen.

Sobald mindestens eine Frist aktiv ist, richtet das Package eine tägliche native ConcreteCMS-Aufgabe für 03:10 Uhr ein. Der ConcreteCMS-Scheduler und sein serverseitiger Auslöser müssen zusätzlich aktiv sein. Ohne sie reicht das Speichern der Frist nicht aus. Die Aufgabe kann auch manuell unter **Aufgaben** gestartet werden. Stehen wieder alle Fristen auf **Nie**, wird die Planung entfernt.

## Geltungsbereich

Express-Einträge werden nur erfasst, wenn ihre Entität von einem noch vorhandenen Express-Formularblock verwendet wird. Andere Express-Objekte bleiben unberührt. **Hochgeladene Dateien in der Dateiverwaltung werden nicht gelöscht**, weil sie an anderer Stelle verwendet werden können. Zugestellte Benachrichtigungs-E-Mails im Postfach, Webserver-Logs, externe Statistiken und Backups sind nicht erfasst. Im CMS-Datenbankprotokoll gespeicherte Kopien versandter E-Mails werden dagegen mitgelöscht, wenn dafür eine Frist aktiviert ist. Für die übrigen Speicherorte sind eigene Löschregeln nötig. Datenschutzhinweise müssen zu den tatsächlich aktivierten Fristen passen.

Das Package enthält englische Ausgangstexte. Übersetzungen kommen über translate.concretecms.org oder Verwaltung → System und Einstellungen → Mehrsprachigkeit → Sprachen aktualisieren. Eine Deinstallation entfernt nur die Package-Komponenten, nicht gespeicherte Formularantworten oder Core-Objekte.

## Support

Herausgeber: Xanweb GmbH. Support erfolgt über [GitHub-Issues](https://github.com/Xanweb/xw_data_retention/issues). Dieses kostenlose Addon hat keine garantierte Reaktionszeit. Bitte bei einer Fehlermeldung die ConcreteCMS-Version, Package-Version, relevante Aufgabenausgabe und eingestellte Fristen nennen. Keine personenbezogenen Formulardaten mitsenden.

## Seitenversionen (Unreleased)

Der eigene Abschnitt **Page versions** steht standardmäßig auf **Never delete automatically**. Zum Aktivieren mindestens **10 bis höchstens 1000 Versionen je Seite** festlegen und optional eine Altersgrenze von 1, 2, 3, 6, 12, 24 oder 36 Monaten wählen. **No age limit** bedeutet: nur Anzahl- und Schutzregeln anwenden. Beim Speichern muss die automatische Löschung ausdrücklich bestätigt werden. Die Formular-/Log-Fristen und deren Aufgabe um 03:10 bleiben unabhängig davon.

Gelöscht wird nur außerhalb der neuesten N Versionen und, falls ausgewählt, zusätzlich nur bei einem Erstellungsdatum vor der Altersgrenze. Die jüngste und sämtliche freigegebenen Versionen bleiben immer erhalten; dadurch können mehr als N Versionen übrig bleiben. Ein künftiger Veröffentlichungsbeginn schützt auch nicht freigegebene Versionen. Bei einem offenen Workflow wird vorsichtshalber die gesamte Seite übersprungen: Der Core speichert die genaue angefragte Version im serialisierten Workflow-Request. Diese Regel schützt offene Freigaben ohne SQL-Auswertung serialisierter Inhalte.

Stacks und Global Areas bleiben ausgenommen, solange **Also clean up stacks and global areas** nicht aktiviert wird. Danach gelten für jeden Stack dieselben Regeln. Papierkorb, Composer-Entwürfe, Dashboard-/Systemseiten, Aliase und Seitenvorlagen bleiben ausgeschlossen. Jede Sprachseite wird einzeln bewertet. Die SQL-Vorschau zählt Versionen und betroffene Seiten anhand der gespeicherten Einstellungen, ohne Versionsobjekte zu laden. Änderungen bis zum Lauf können die tatsächliche Anzahl verringern.

Die separate native Aufgabe **Delete old page versions** (`xw_delete_old_page_versions`) läuft täglich um **03:40**, wenn der Concrete-Scheduler eingerichtet ist, und lässt sich unter **Tasks** manuell starten. Ausschalten entfernt nur ihren eigenen Zeitplan. Pro betroffener Seite wird ein Batch-Befehl ausgeführt; Versions-IDs werden in Blöcken von 100 gelesen. Die Core-API `Version::delete()` berücksichtigt gemeinsam genutzte Blöcke. Fehler einzelner Versionen landen im Protokollkanal `xw_data_retention`; weitere Versionen werden weiterbearbeitet. Die native Aufgabenausgabe enthält Seitenzähler und die Gesamtsumme je Lauf. Für ein dauerhaftes Dashboard-Protokoll das Core-Prozesslogging aktivieren. Eine eigene Package-Tabelle speichert die Summen; abgeschlossene Zähler älter als 30 Tage werden entfernt. Eine Deinstallation entfernt diese Tabelle, beide Aufgaben, deren Zeitpläne und die Package-Config, ohne eine Bereinigung auszuführen.

**Den Zeitplan des Core-Tasks Remove Old Page Versions deaktivieren, wenn diese Erweiterung verwendet wird.** Der Core-Task hält unabhängig von den Einstellungen nur die neuesten zehn plus seine geschützten Versionen und kennt weder diese Alters-/Anzahlregeln noch den Schutz offener Workflows. Bei geplantem Core-Task erscheint eine Warnung im Dashboard. Beide Aufgaben gleichzeitig können Historie entfernen, die das Addon behalten würde.

Gelöschte Versionen lassen sich nur aus einem Backup wiederherstellen. Vor der Aktivierung Datenbank und Website-Dateien sichern. Veröffentlichte aktuelle Seiteninhalte bleiben erhalten.

Der Feature-Branch basiert auf `main` und ändert die Package-Version absichtlich nicht. Auf einer vorhandenen isolierten Testinstallation mit derselben Version die Installations-/Upgrade-Änderungen über `php concrete/bin/concrete c5:package:update --force xw_data_retention` anwenden. Versionierung, Merge und Tag entscheidet der Herausgeber später. Die deutsche Übersetzungsquelle liegt außerhalb des Archivs in `translations/de_DE.po`; neue Texte müssen zunächst auf translate.concretecms.org veröffentlicht werden.
