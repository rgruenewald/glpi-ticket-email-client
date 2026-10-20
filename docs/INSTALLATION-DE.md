# Installation und Administration

[English](INSTALLATION.md) · [Produktübersicht](../README.md) · [Benutzerhandbuch](wiki/Benutzerhandbuch-DE.md)

## Kompatibilität und Voraussetzungen

- Ticketmailer 2.4.0; GLPI 11.0.x, geprüft mit GLPI 11.0.8.
- PHP ab 8.2 und eine funktionierende SMTP-Konfiguration in GLPI.
- Zum Antworten: Recht zur Ticketaktualisierung oder zum Hinzufügen eines Ticketbeitrags.
- Zum Lesen von Versandprotokoll und Anhängen: Leserecht auf das zugehörige Ticket.
- Für Neue E-Mail: Rechte zum Erstellen, Aktualisieren und Lesen von Tickets in einer berechtigten Einheit mit gültiger Absenderadresse.
- Datenbankrechte für Installation und Aktualisierung von GLPI-Plugins.

Eine Veröffentlichung oder Annahme im GLPI Marketplace ist nicht nachgewiesen. Nutzen Sie ein GitHub-Release oder den Quellcode. Vorhandene Katalogmetadaten belegen keine Aufnahme in den Marketplace.

## Vor der Installation: Sicherheit und Datenschutz

**Jeder Ticketleser sieht die vollständige BCC-Liste** im Ticketbeitrag und im Versanddetail. BCC bleibt aus den sichtbaren An-/CC-Kopfzeilen der versendeten E-Mail ausgeschlossen, ist innerhalb des Tickets aber nicht vertraulich.

Gespeicherte ausgehende Nachrichten und Dateien sind nur mit Ticketleserecht zugänglich. Private Ticketbeiträge und ihre Dokumente werden nicht als öffentlicher Verlauf oder auswählbare Ticketanhänge angeboten. Prüfen Sie dennoch öffentlichen Inhalt vor einer Weitergabe.

Der Postfachschutz vergleicht Empfänger mit den E-Mail-Anmeldungen aktiver GLPI-Mail-Kollektoren. Ein Treffer benötigt eine ausdrückliche, serverseitig geprüfte Freigabe. Aliasse, Weiterleitungen und Anmeldungen ohne E-Mail-Format werden nicht erkannt.

## Installation

1. Laden Sie ein Releasearchiv herunter oder klonen Sie das Repository.
2. Legen Sie den Pluginquellcode unter folgendem Namen ab:

   ```text
   <glpi-root>/plugins/ticketmailer
   ```

3. Der Webserverbenutzer benötigt Leserechte auf die Plugindateien und Schreibrechte auf das konfigurierte GLPI-Dokumentverzeichnis.
4. Installieren und aktivieren Sie das Plugin aus dem GLPI-Stammverzeichnis:

   ```bash
   php bin/console plugin:install ticketmailer
   php bin/console plugin:enable ticketmailer
   ```

5. Prüfen Sie die SMTP-Einstellungen in GLPI. Ticketmailer hat keine eigenen SMTP-Zugangsdaten oder Transporteinstellungen.
6. Öffnen Sie ein Ticket mit einem berechtigten Benutzer. Prüfen Sie die Aktion **E-Mail antworten** beziehungsweise die konfigurierte Aktion **Antworten**.

### Aktualisierung

1. Sichern Sie GLPI-Datenbank und Dokumentverzeichnis nach Ihrem regulären Wartungsverfahren.
2. Ersetzen Sie das Pluginverzeichnis durch das neue Release. Der Verzeichnisname bleibt `ticketmailer`.
3. Führen Sie die GLPI-Pluginaktualisierung über die Administration oder CLI aus.
4. Prüfen Sie die angezeigte Version und die unten genannten Funktionstests.

Die Migration ergänzt bestehende Versandprotokolle um Verlauf- und Postfachschutzinformationen. Installationsschema und Versionsmigrationen liegen unter `sql/`. Behandeln Sie einen fehlgeschlagenen Upgrade nicht als erfolgreich; prüfen Sie die GLPI-Protokolle und Ihr Wiederherstellungsverfahren.

## Konfiguration

### GLPI-Mailtransport

Richten Sie SMTP zentral in GLPI ein. Ticketmailer versendet die zusammengestellte E-Mail direkt über diesen Transport, nicht über die GLPI-Benachrichtigungszustellung.

### Einstellungen je Einheit

In der Plugin-Konfiguration wählen Sie eine Einheit. Zu den Einstellungen gehören:

- **Ticket-Benachrichtigungsvorlage:** liefert Betreff und bearbeitbare Signatur. Untereinheiten können die Einstellung erben. Die Empfänger stammen weiterhin aus dem Formular; Zugriffsrechte bleiben wirksam.
- **Ticket nach Versand auf wartend setzen:** standardmäßig aktiviert.
- **Neueste Ticketeinträge zuerst:** standardmäßig aktiviert.
- **Native GLPI-Antwortaktion ausblenden:** standardmäßig deaktiviert. Die Ersetzung verwendet GLPIs unterstützten Timeline-Hook, keine reine CSS-/DOM-Verdeckung. Das native Formular für interne Notizen bleibt erhalten.

### Schaltflächen und Dialogfelder

Die globale Konfiguration gruppiert Ticketaktionen, E-Mail-Dialog und Verhalten. Unter anderem sind E-Mail-/Antwort-, Lösungs-, Interne-Notiz-, Aufgaben- und Dokumentaktionen sowie Antwortvorlagen, Lösungsvorlagen, Quelle und Wissensdatenbank steuerbar. Aktivierte Ersetzungen entfernen doppelte native Menüeinträge über GLPI-Deskriptoren. Deaktivierte Ersetzungen lassen die nativen Einträge bestehen.

Auch das private Notizformular berücksichtigt die Schalter für Antwortvorlagen und Quelle. Die Schalter liegen im GLPI-Plugin-Konfigurationsbereich; dafür ist keine eigene Schemamigration nötig.

### Antwortrichtlinie

Die Modi `available`, `promoted` und `hide_native` steuern die Antwortaktion. Es gilt zuerst die genaue Kombination aus Einheit und Profil, dann die Standardregel der Einheit und schließlich global `available`. Daraus folgt keine zusätzliche Vererbung über die Einheitenhierarchie.

### Neue E-Mail und Absendereinheit

**Helpdesk → Neue E-Mail** erstellt beim Senden ein Ticket in der ausgewählten Absendereinheit. Diese Auswahl bestimmt auch die Signatur und ändert nicht dauerhaft die aktive GLPI-Einheit. Die Liste zeigt nur berechtigte Einheiten mit gültiger Absenderadresse.

An-Empfänger werden Antragsteller, CC-Empfänger Beobachter. BCC-Empfänger werden keine Beobachter, sind aber im Versanddatensatz für Ticketleser sichtbar. Bei Fehlern nach Ticketerstellung erst das Ticket und den Versandstatus prüfen, bevor ein neuer Versuch gestartet wird.

## Versand und Fehlergrenzen

Nach der Validierung legt Ticketmailer einen dauerhaften Versandversuch an und unternimmt genau einen SMTP-Versuch. Bei SMTP-Erfolg wird ein öffentlicher Standard-Ticketbeitrag mit vollständigem ausgehendem Inhalt und geschützten Dateilinks angelegt. Dieser E-Mail-Beitrag ist für zusätzliche GLPI-Benachrichtigungen deaktiviert, damit nicht noch eine zweite Benachrichtigungs-E-Mail entsteht.

| Zustand | Bedeutung und Vorgehen |
| --- | --- |
| SMTP erfolgreich, Verlauf gespeichert | Vollständiger Versand. SMTP-Erfolg ist kein Nachweis für den Eingang im Empfängerpostfach. |
| SMTP fehlgeschlagen | Fehler im geschützten Versanddetail prüfen und den GLPI-Mailtransport untersuchen. Kein automatischer Neuversand. |
| SMTP erfolgreich, Verlauf fehlgeschlagen | **Unvollständig. Nicht erneut senden:** Die E-Mail wurde bereits versendet. Fehlenden Ticketeintrag und Fehlerprotokoll administrativ klären. |
| Unklar oder ausstehend | Versandprotokoll und GLPI-Logs untersuchen. Nicht allein wegen eines fehlenden Verlaufseintrags erneut senden. |

Der Ticketstatus wird beim E-Mail-Versand erst nach SMTP-Erfolg und gespeichertem Verlauf geändert. Wartend und gelöst sind gegenseitig ausschließend.

**Interne Notiz** ist dagegen der native private GLPI-Ticketbeitrag. Er verwendet weder Ticketmailer-SMTP noch Ticketmailer-Versandprotokoll. Native Rechte, Uploads, Statusverarbeitung und konfigurierte GLPI-Benachrichtigungen bleiben maßgeblich. Eine private Notiz bedeutet nicht automatisch, dass keinerlei Benachrichtigungs-E-Mail ausgelöst wird.

## Gespeicherte Daten und Grenzen

Das Plugin speichert ausgehende Inhalte, Empfänger einschließlich BCC, Dateien, Versuch und Ergebnis des Versands sowie Verweise auf Ticketbeiträge. Dateizugriffe erfordern Ticketleserecht und erfolgen über kontrollierte Downloadendpunkte.

Ticketmailer speichert keine dauerhaften Entwürfe, besitzt keine Versandwarteschlange und wiederholt SMTP nicht automatisch. Es ist kein Posteingang, ruft keine Nachrichten ab und synchronisiert kein IMAP. Beim Moduswechsel erhaltene Formulareingaben sind keine gesicherten Entwürfe.

## Prüfung und Entwicklung

Für die lokale, ausdrücklich gewählte, verwerfbare Testumgebung mit synthetischen Daten folgen Sie der [Docker-/Mailpit-Prüfrezeptur](verification/docker-e2e.md). Sie beschreibt Voraussetzungen, persistente Volumes, Rootless-Rechte und Resetgrenzen. Kein Produktionsbestand und keine echten SMTP-Zugangsdaten dürfen in diese Testumgebung übernommen werden.

Vom Repository-Stamm aus:

```bash
scripts/dev up
scripts/dev test
scripts/dev e2e
scripts/dev status
scripts/dev stop
```

`test` führt PHPUnit, JavaScript-Prüfungen und das strukturelle v2-Gate aus. `e2e` prüft echte Chromium-Abläufe gegen die lokale GLPI-/Mailpit-Instanz. Der SMTP-Ausfalltest startet Mailpit neu und leert damit dessen In-Memory-Postfach. Vor dem Start immer die aktuelle Prüfrezeptur lesen.

### Manuelle Funktionsprüfung

1. E-Mail mit An/CC/BCC, Text, Datei und eingebettetem Bild an synthetische Empfänger senden.
2. Genau eine Nachricht in Mailpit und einen vollständigen Eintrag im Ticketverlauf prüfen.
3. Versanddetail und gespeicherte Dateien mit Ticketleserecht öffnen; Zugriff ohne Ticketleserecht muss verweigert werden.
4. Postfachtreffer ohne ausdrückliche Freigabe ablehnen lassen; anschließend einen kontrollierten Test mit Freigabe prüfen.
5. SMTP-Fehler und Verlauf-Fehler nach SMTP-Erfolg getrennt prüfen. Keine automatische Wiederholung und keine Erfolgsmeldung für den unvollständigen Zustand.
6. Zwischen E-Mail und interner Notiz wechseln. Rechte, Status und Benachrichtigungskonfiguration des nativen Formulars prüfen.
7. Neue E-Mail mit einer berechtigten Absendereinheit prüfen. Ticketeinheit und Antragsteller-/Beobachterzuordnung kontrollieren.

Die vollständigen automatisierten und manuellen Abnahmekriterien stehen im [v2-Vertrag](specifications/glpi-ticket-email-client-v2/spec.md). Diese Dokumentationsänderung ersetzt keine vollständige Releaseprüfung.

[Benutzerhandbuch](wiki/Benutzerhandbuch-DE.md) · [English administration guide](INSTALLATION.md)
