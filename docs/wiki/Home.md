# GLPI Ticket Email Client

Ticketmailer adds email replies, new outgoing conversations, and internal notes to GLPI tickets. / Ticketmailer ergänzt GLPI-Tickets um E-Mail-Antworten, neue ausgehende Konversationen und interne Notizen.

**Compatibility / Kompatibilität:** GLPI 11.0.x, PHP ≥ 8.2. Reference environment / Referenzumgebung: GLPI 11.0.8.

**Documentation scope / Dokumentationsstand:** current `main` code, including changes after released version **2.6.1**. / Aktueller Code auf `main`, einschließlich Änderungen nach der veröffentlichten Version **2.6.1**. Unreleased changes are labelled in the version notes. / Noch unveröffentlichte Änderungen sind in den Versionshinweisen gekennzeichnet.

## English

1. [User guide](https://github.com/rgruenewald/glpi-ticket-email-client/wiki/User-Guide-EN): reply, forward, start a conversation, add internal notes.
2. [Installation and upgrades](https://github.com/rgruenewald/glpi-ticket-email-client/wiki/Installation-EN): requirements, package layout, activation, upgrade and removal.
3. [Administrator guide](https://github.com/rgruenewald/glpi-ticket-email-client/wiki/Administrator-Guide-EN): SMTP, entities, reply policy, permissions, templates and privacy.
4. [Troubleshooting](https://github.com/rgruenewald/glpi-ticket-email-client/wiki/Troubleshooting-EN): missing actions, recipient validation, delivery and partial failures.
5. [Version notes](https://github.com/rgruenewald/glpi-ticket-email-client/wiki/Version-Notes-EN): what changed since the old 2.4.0 guide.

## Deutsch

1. [Benutzerhandbuch](https://github.com/rgruenewald/glpi-ticket-email-client/wiki/Benutzerhandbuch-DE): antworten, weiterleiten, neue Konversationen und interne Notizen.
2. [Installation und Updates](https://github.com/rgruenewald/glpi-ticket-email-client/wiki/Installation-DE): Voraussetzungen, Paketstruktur, Aktivierung, Update und Entfernung.
3. [Administratorhandbuch](https://github.com/rgruenewald/glpi-ticket-email-client/wiki/Administratorhandbuch-DE): SMTP, Entitäten, Antwortrichtlinie, Rechte, Vorlagen und Datenschutz.
4. [Fehlerbehebung](https://github.com/rgruenewald/glpi-ticket-email-client/wiki/Fehlerbehebung-DE): fehlende Aktionen, Empfängerprüfung, Versand und Teilfehler.
5. [Versionshinweise](https://github.com/rgruenewald/glpi-ticket-email-client/wiki/Versionshinweise-DE): Änderungen seit dem bisherigen Handbuch zu 2.4.0.

## Important / Wichtig

- **BCC is visible to every ticket reader** in the followup and audit detail. It is hidden only from the email’s To/CC headers. / **BCC ist für jeden Ticketleser sichtbar**, im Folgeeintrag und Auditdetail. Nur im To-/CC-Header der E-Mail bleibt BCC verborgen.
- Ticketmailer sends through GLPI’s configured SMTP transport, not the notification queue. / Ticketmailer versendet über den konfigurierten SMTP-Transport von GLPI, nicht über dessen Benachrichtigungswarteschlange.
- A `sent_with_error` result means SMTP succeeded. Check the ticket and audit before sending again. / Bei `sent_with_error` war SMTP erfolgreich. Vor einem erneuten Versand Ticket und Audit prüfen.

## Project / Projekt

[Repository](https://github.com/rgruenewald/glpi-ticket-email-client) · [Releases](https://github.com/rgruenewald/glpi-ticket-email-client/releases) · [Changelog](https://github.com/rgruenewald/glpi-ticket-email-client/blob/main/CHANGELOG.md) · [Report a bug / Fehler melden](https://github.com/rgruenewald/glpi-ticket-email-client/issues)

The wiki is maintained in [docs/wiki](https://github.com/rgruenewald/glpi-ticket-email-client/tree/main/docs/wiki) and published by GitHub Actions. Change the repository sources rather than editing only the published wiki. / Das Wiki wird unter [docs/wiki](https://github.com/rgruenewald/glpi-ticket-email-client/tree/main/docs/wiki) gepflegt und über GitHub Actions veröffentlicht. Änderungen gehören in diese Quellen, nicht nur ins veröffentlichte Wiki.
