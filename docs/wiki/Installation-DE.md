# Installation und Updates

[Start](https://github.com/rgruenewald/glpi-ticket-email-client/wiki/Home) · [English](https://github.com/rgruenewald/glpi-ticket-email-client/wiki/Installation-EN) · [Administratorhandbuch](https://github.com/rgruenewald/glpi-ticket-email-client/wiki/Administratorhandbuch-DE)

## Voraussetzungen

- GLPI **11.0.x**, PHP **ab 8.2**. Referenzumgebung ist GLPI 11.0.8; GLPI 11.1 wird nicht unterstützt.
- Eine funktionsfähige GLPI-Installation mit administrativ konfiguriertem SMTP-Versand.
- Ein Backup der GLPI-Datenbank und Plugin-Dateien vor Installation oder Update.

Für den Produktivbetrieb ein versioniertes [Release-Paket](https://github.com/rgruenewald/glpi-ticket-email-client/releases) verwenden, keinen Branch-Snapshot. Diese Seiten beschreiben auch unveröffentlichte Änderungen, die in den [Versionshinweisen](https://github.com/rgruenewald/glpi-ticket-email-client/wiki/Versionshinweise-DE) ausdrücklich gekennzeichnet sind.

## 1. Paket installieren

1. `ticketmailer-<version>.tar.gz` aus dem gewünschten GitHub-Release herunterladen und die veröffentlichte SHA-256-Prüfsumme aus `SHA256SUMS` prüfen.
2. Das Paket in das Verzeichnis `plugins/` von GLPI entpacken, alternativ in das von der Installation verwendete Plugin-Verzeichnis `marketplace/`.
3. Der Ordner muss genau `ticketmailer` heißen. Kein zusätzliches Unterverzeichnis `ticketmailer/` und keinen Versionssuffix verwenden.
4. Der Webserver muss die Plugin-Dateien lesen und in das reguläre GLPI-Dateiverzeichnis schreiben können. Bestehende Eigentümer- und Rechtevorgaben der Installation beibehalten; das Plugin nicht für alle beschreibbar machen.

Erwartete Struktur am Beispiel `plugins/`:

```text
<glpi>/plugins/ticketmailer/
├── setup.php
├── hook.php
├── inc/
├── front/
├── ajax/
├── templates/
├── assets/
├── locales/
└── sql/
```

Das Paket enthält die Laufzeitdateien und kompilierten Übersetzungen. Node.js, npm, Composer und Werkzeuge zum Bauen aus dem Quellcode sind für die Paketinstallation nicht erforderlich.

## 2. In GLPI installieren und aktivieren

1. Als Administrator **Konfiguration → Plugins** öffnen.
2. **GLPI Ticket Email Client** auswählen, **Installieren** und danach **Aktivieren** ausführen.
3. Den E-Mail-Versand in den GLPI-Einstellungen konfigurieren. Ticketmailer hat keine eigene SMTP-Konfiguration.
4. **Konfiguration → Allgemein → Ticket-E-Mail**, Entitätseinstellungen und Profilrechte anhand des [Administratorhandbuchs](https://github.com/rgruenewald/glpi-ticket-email-client/wiki/Administratorhandbuch-DE) prüfen.

Die Menübezeichnungen hängen von der GLPI-Sprache ab. Fehlen nach einem Update übersetzte Texte, kann die übliche GLPI-Cache-Aktualisierung nötig sein.

## 3. Vor dem Produktivbetrieb prüfen

Ein synthetisches Ticket und ein kontrolliertes Testpostfach verwenden, keine Kundendaten.

- Ein berechtigter Benutzer sieht die konfigurierte Antwortaktion. Ein Ticketleser ohne Folgeeintragsrecht darf nicht senden.
- To enthält anfangs die Anforderer, CC die Beobachter, BCC ist leer.
- Genau eine Testnachricht senden und im Zielpostfach prüfen.
- Den zugehörigen E-Mail-Folgeeintrag und das Versandprotokoll prüfen. Der E-Mail-Folgeeintrag darf keine zusätzliche native Benachrichtigung auslösen.
- Bei verwendeten Statusschaltern die erlaubten Wechsel prüfen. Ohne gewählten Wechsel muss der Status erhalten bleiben.
- Bei Versionen mit der Anhangskorrektur nach 2.6.1 prüfen, ob ein gesendeter Upload als Ticketdokument erscheint und in einer weiteren Antwort auswählbar ist.

Interne Notizen verhalten sich anders: Native Benachrichtigungen für private Folgeeinträge können gelten. Ein Notiztest belegt daher nicht die Unterdrückung nativer Benachrichtigungen bei E-Mail-Antworten.

## Update

1. [Versionshinweise](https://github.com/rgruenewald/glpi-ticket-email-client/wiki/Versionshinweise-DE) und [vollständiges Changelog](https://github.com/rgruenewald/glpi-ticket-email-client/blob/main/CHANGELOG.md) lesen.
2. Datenbank und Plugin-Dateien sichern. Das Update so planen, dass niemand aus einem alten geöffneten Dialog sendet.
3. Plugin deaktivieren, Dateien durch das neue Paket ersetzen und die Aktion **Aktualisieren** ausführen, falls GLPI sie anbietet.
4. Plugin aktivieren, bei Bedarf den GLPI-Cache aktualisieren und die obigen Prüfungen wiederholen.

**Deinstallieren** ist kein Update-Schritt. Ein Dateiaustausch wandelt alte Anhangsdatensätze nicht nachträglich in Ticketdokumente um. Die Hinweise der installierten Version beachten.

## Deaktivieren oder entfernen

Deaktivieren beendet die UI-Integration, die Tabellen bleiben erhalten. Eine Deinstallation **löscht die Plugin-Tabellen einschließlich Konfiguration, Benutzereinstellungen und Auditprotokoll**. Benötigte Auditdaten und Backups vorher sichern. Ticket-Folgeeinträge und GLPI-Dokumente sind eigenständige Core-Datensätze.

## Entwicklungsumgebung

Für die Mitarbeit gibt es eine Docker-Umgebung mit SMTP-Sink. Siehe die gerenderte [Installationsanleitung im Repository](https://github.com/rgruenewald/glpi-ticket-email-client/blob/main/docs/INSTALLATION-DE.md) und [CONTRIBUTING](https://github.com/rgruenewald/glpi-ticket-email-client/blob/main/CONTRIBUTING.md). Diese Werkzeuge sind kein Produktiv-Deployment.
