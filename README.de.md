# Xanweb Datenaufbewahrung

Dieses eigenständige ConcreteCMS-Package legt getrennte Aufbewahrungsfristen für gespeicherte Formularantworten und optional für Datenbankprotokolle fest. Es ändert weder Seiteninhalte noch Core-Dateien. Kompatibel mit Concrete CMS ab 9.3.0; Die Upload-Erweiterung wird mit 9.3.9 und 9.5.7 geprüft; die bisherigen Aufbewahrungsfunktionen wurden außerdem mit 9.3.0 geprüft.

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

## Optionale Löschung von Express-Uploads (0.9.3)

Hochgeladene Dateien bleiben **standardmäßig erhalten**, auch nach einem Upgrade von 1.0.x. Zum Aktivieren eine Express-Frist größer null setzen, einen eigenen Ordner außerhalb des Roots der Dateiverwaltung auswählen, **Also delete uploaded files** einschalten und die zusätzliche Bestätigung zur endgültigen Dateilöschung akzeptieren.

Eine Datei kommt nur infrage, wenn **beide** Bedingungen erfüllt sind:

1. Sie ist über ein Datei-Attribut (`image_file`) mit einem Express-Eintrag verknüpft, der gerade wegen Ablaufs gelöscht wird.
2. Sie liegt direkt im ausdrücklich freigegebenen Ordner. Unterordner zählen nicht.

Dateien ohne Express-Eintrag werden niemals eingesammelt. Nach der Löschung des Eintrags prüft die Aufgabe sämtliche verbleibenden Express-Einträge, auch anderer Express-Objekte. Besteht noch eine Verknüpfung, bleibt die Datei erhalten. Auch vom Core-Usage-Tracker erfasste Seiten- und Blockverwendungen verhindern die Löschung. Die Core-API entfernt eine freigegebene Datei einschließlich aller Versionen und Vorschaubilder. Die Aufgabenausgabe nennt die gelöschten Dateien. Ein Eintrag und seine Dateien werden zusammen abgeschlossen, bevor das Zeitlimit erneut geprüft wird.

Im Express-Formularblock **Options → Files → Add uploaded files to folder** öffnen und denselben Ordner auswählen. Das betrifft neue Uploads und verschiebt keine älteren Dateien. Die Vorschau verwendet die gespeicherten Einstellungen und zählt unterschiedliche freigegebene Dateien. Sie warnt vor Dateien ohne Express-Verknüpfung und vor einem inzwischen entfernten Ordner. Zeitlimit, Löschereignisse, Fehler und gleichzeitige Änderungen können die tatsächliche Zahl im nächsten Lauf verringern. Bei entferntem Ordner werden die Einträge weiter gelöscht, die Dateilöschung wird übersprungen. Fehler oder durch Core-Ereignisse verhinderte Dateilöschungen werden im Concrete-Protokollkanal `xw_data_retention` vermerkt; der Lauf geht weiter. Solche Dateien werden danach nicht automatisch erneut versucht oder als verwaiste Dateien eingesammelt.

Für personenbezogene oder vertrauliche Uploads unter **Dashboard → System & Settings → Files → File Storage Locations** einen nicht öffentlichen Speicherort einrichten. Bei lokalem Speicher einen Ordner außerhalb des Webroots ohne öffentliche relative URL verwenden. In der Dateiverwaltung über **Edit Folder → Storage Location** dem Upload-Ordner diesen Speicherort zuweisen. Neue Formular-Uploads übernehmen ihn beim Verschieben in diesen Ordner. Eine Änderung am Ordner verschiebt vorhandene Dateien nicht; deren Speicherort separat ändern. Zusätzlich die Download-Berechtigungen einschränken: Ohne öffentliche URL kann Concrete weiterhin Downloads entsprechend seinen Berechtigungen ausliefern.

Einen eigenen Upload-Ordner verwenden und seine Dateien nicht anderweitig wiederverwenden. Der Core-Usage-Tracker erfasst teilnehmende Blöcke und Attribute, aber keine beliebigen fest eingetragenen URLs oder eigenen Integrationen. Upgrade-Verhalten: siehe [CHANGELOG.md](CHANGELOG.md). Die deutsche Übersetzungsquelle liegt außerhalb des Pakets in `translations/de_DE.po`; Downloads über die Sprachverwaltung setzen eine Veröffentlichung auf translate.concretecms.org voraus.

## Geltungsbereich

Express-Einträge werden nur erfasst, wenn ihre Entität von einem noch vorhandenen Express-Formularblock verwendet wird. Andere Express-Objekte bleiben unberührt. **Hochgeladene Dateien bleiben standardmäßig erhalten.** Die optionale Löschung gilt nur für Dateien abgelaufener Express-Einträge direkt im freigegebenen Ordner und unter den oben beschriebenen Prüfungen. Uploads klassischer Formularblöcke und andere Dateien bleiben ausgenommen. Zugestellte Benachrichtigungs-E-Mails im Postfach, Webserver-Logs, externe Statistiken und Backups sind nicht erfasst. Im CMS-Datenbankprotokoll gespeicherte Kopien versandter E-Mails werden dagegen mitgelöscht, wenn dafür eine Frist aktiviert ist. Für die übrigen Speicherorte sind eigene Löschregeln nötig. Datenschutzhinweise müssen zu den tatsächlich aktivierten Fristen passen.

Das Package enthält englische Ausgangstexte. Übersetzungen kommen über translate.concretecms.org oder Verwaltung → System und Einstellungen → Mehrsprachigkeit → Sprachen aktualisieren. Eine Deinstallation entfernt nur die Package-Komponenten, nicht gespeicherte Formularantworten oder Core-Objekte.

## Support

Herausgeber: Xanweb GmbH. Support erfolgt über [GitHub-Issues](https://github.com/Xanweb/xw_data_retention/issues). Dieses kostenlose Addon hat keine garantierte Reaktionszeit. Bitte bei einer Fehlermeldung die ConcreteCMS-Version, Package-Version, relevante Aufgabenausgabe und eingestellte Fristen nennen. Keine personenbezogenen Formulardaten mitsenden.

## Seitenversionen (0.9.3)

Der eigene Abschnitt **Page versions** steht standardmäßig auf **Never delete automatically**. Zum Aktivieren mindestens **10 bis höchstens 1000 Versionen je Seite** festlegen und optional eine Altersgrenze von 1, 2, 3, 6, 12, 24 oder 36 Monaten wählen. **No age limit** bedeutet: nur Anzahl- und Schutzregeln anwenden. Beim Speichern muss die automatische Löschung ausdrücklich bestätigt werden. Die Formular-/Log-Fristen und deren Aufgabe um 03:10 bleiben unabhängig davon.

Gelöscht wird nur außerhalb der neuesten N Versionen und, falls ausgewählt, zusätzlich nur bei einem Erstellungsdatum vor der Altersgrenze. Die jüngste und sämtliche freigegebenen Versionen bleiben immer erhalten; dadurch können mehr als N Versionen übrig bleiben. Ein künftiger Veröffentlichungsbeginn schützt auch nicht freigegebene Versionen. Bei einem offenen Workflow wird vorsichtshalber die gesamte Seite übersprungen: Der Core speichert die genaue angefragte Version im serialisierten Workflow-Request. Diese Regel schützt offene Freigaben ohne SQL-Auswertung serialisierter Inhalte.

Stacks und Global Areas bleiben ausgenommen, solange **Also clean up stacks and global areas** nicht aktiviert wird. Danach gelten für jeden Stack dieselben Regeln. Papierkorb, Composer-Entwürfe, Dashboard-/Systemseiten, Aliase und Seitenvorlagen bleiben ausgeschlossen. Jede Sprachseite wird einzeln bewertet. Die SQL-Vorschau zählt Versionen und betroffene Seiten anhand der gespeicherten Einstellungen, ohne Versionsobjekte zu laden. Änderungen bis zum Lauf können die tatsächliche Anzahl verringern.

Die separate native Aufgabe **Delete old page versions** (`xw_delete_old_page_versions`) läuft täglich um **03:40**, wenn der Concrete-Scheduler eingerichtet ist, und lässt sich unter **Tasks** manuell starten. Ausschalten entfernt nur ihren eigenen Zeitplan. Pro betroffener Seite wird ein Batch-Befehl ausgeführt; Versions-IDs werden in Blöcken von 100 gelesen. Die Core-API `Version::delete()` berücksichtigt gemeinsam genutzte Blöcke. Fehler einzelner Versionen landen im Protokollkanal `xw_data_retention`; weitere Versionen werden weiterbearbeitet. Die native Aufgabenausgabe enthält Seitenzähler und die Gesamtsumme je Lauf. Für ein dauerhaftes Dashboard-Protokoll das Core-Prozesslogging aktivieren. Eine eigene Package-Tabelle speichert die Summen; abgeschlossene Zähler älter als 30 Tage werden entfernt. Eine Deinstallation entfernt diese Tabelle, beide Aufgaben, deren Zeitpläne und die Package-Config, ohne eine Bereinigung auszuführen.

**Den Zeitplan des Core-Tasks Remove Old Page Versions deaktivieren, wenn diese Erweiterung verwendet wird.** Der Core-Task hält unabhängig von den Einstellungen nur die neuesten zehn plus seine geschützten Versionen und kennt weder diese Alters-/Anzahlregeln noch den Schutz offener Workflows. Bei geplantem Core-Task erscheint eine Warnung im Dashboard. Beide Aufgaben gleichzeitig können Historie entfernen, die das Addon behalten würde.

Gelöschte Versionen lassen sich nur aus einem Backup wiederherstellen. Vor der Aktivierung Datenbank und Website-Dateien sichern. Veröffentlichte aktuelle Seiteninhalte bleiben erhalten.


Für das Upgrade von 0.9.0 die Paketdateien ersetzen und im Dashboard aktualisieren oder `php concrete/bin/concrete c5:package:update xw_data_retention` ausführen. Vorhandene Fristen bleiben erhalten. Beide neuen Löschfunktionen bleiben bis zur ausdrücklichen Aktivierung ausgeschaltet.
