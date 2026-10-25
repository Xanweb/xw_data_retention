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

Das Package enthält englische Ausgangstexte und deutsche Übersetzungen. Eine Deinstallation entfernt nur die Package-Komponenten, nicht gespeicherte Formularantworten oder Core-Objekte.

## Support

Herausgeber: Xanweb GmbH. Support erfolgt über [GitHub-Issues](https://github.com/Xanweb/xw_data_retention/issues). Dieses kostenlose Addon hat keine garantierte Reaktionszeit. Bitte bei einer Fehlermeldung die ConcreteCMS-Version, Package-Version, relevante Aufgabenausgabe und eingestellte Fristen nennen. Keine personenbezogenen Formulardaten mitsenden.
