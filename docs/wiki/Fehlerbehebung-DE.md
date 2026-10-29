# Fehlerbehebung

[Start](https://github.com/rgruenewald/glpi-ticket-email-client/wiki/Home) · [English](https://github.com/rgruenewald/glpi-ticket-email-client/wiki/Troubleshooting-EN) · [Administratorhandbuch](https://github.com/rgruenewald/glpi-ticket-email-client/wiki/Administratorhandbuch-DE)

## Zuerst den Versandstatus klären

Vor einer Wiederholung **Versandprotokoll**, Auditdetail und Timeline des Tickets prüfen. Ein Browserfehler oder fehlender Folgeeintrag beweist keinen SMTP-Fehler. Interne Notizen haben keinen E-Mail-Auditdatensatz.

| Auditstatus | Bedeutung | Nächster Schritt |
| --- | --- | --- |
| `pending` | Die Sendeabsicht ist dauerhaft gespeichert, ein Endergebnis fehlt noch. | Bleibt der Status bestehen, vor erneutem Versand mit einem Administrator untersuchen. |
| `sent` | SMTP und nachgelagerte Speicherung waren erfolgreich. | Zielpostfach prüfen. Ein veraltet wirkender Dialog ist kein Grund für erneuten Versand. |
| `failed` | Der SMTP-Versuch ist fehlgeschlagen. | Ursache beheben und Auditfehler vor einer bewussten Wiederholung prüfen. |
| `sent_with_error` | SMTP war erfolgreich, ein späterer Schritt schlug fehl. | Vorhandenen Folgeeintrag, Dokumente und Ticketstatus prüfen. Nicht erneut senden, nur um GLPI-Daten zu reparieren. |

SMTP-Erfolg bedeutet Annahme durch den Transport, keine garantierte Posteingangszustellung oder Lesebestätigung. Nach Absturz, unterbrochener Anfrage oder unklarem Transportergebnis vor einer manuellen Wiederholung mit dem Mailsystem abgleichen. Ticketmailer wiederholt Teilfehler nicht automatisch.

## Antwortaktion fehlt oder Zugriff verweigert

1. Plugin-Aktivierung und Kompatibilität mit GLPI 11.0.x / PHP ab 8.2 prüfen.
2. Aktives Profil, Entität, Ticketleserecht und natives GLPI-Recht zum Hinzufügen eines Folgeeintrags prüfen.
3. Antwortrichtlinie und globale Schaltflächensichtbarkeit prüfen. Eine hervorgehobene oder umgeleitete native Antwort kann den separaten Einstieg ersetzen.
4. Nach Rechte- oder Konfigurationsänderungen neu laden. Sichtbarkeit umgeht keine Berechtigungen.

Ist **Gelöst** deaktiviert, Statuswechselrechte, Pflichtfelder und einen bereits gelösten oder geschlossenen Ticketstatus prüfen. Profilrechte nicht nur zum Umgehen einer korrekten Lebenszyklusbeschränkung ändern.

## Formular wird vor dem Versand abgewiesen

- **Ungültiger Empfänger:** gültige Adressen und die dokumentierten Trennzeichen verwenden. Ein ungültiger Eintrag weist die gesamte Anfrage ab und wird nicht still entfernt.
- **Kein Empfänger:** bei Ticketantworten genügt To, CC oder BCC. Neue Konversationen benötigen einen To-Empfänger.
- **Betreff/Nachricht erforderlich:** lesbaren Betreff und nicht leeren Nachrichtentext angeben. Ausgeblendete Felder brauchen weiterhin gültige Werte.
- **Quelle des Folgeeintrags nicht verfügbar:** eine aktive, für Folgeeinträge zugelassene Quelle wählen oder die optionale Auswahl leeren.
- **Ticketstatus geändert:** Dialog neu laden, aktuellen Zustand prüfen und gewünschten Wechsel erneut wählen. Ein veraltetes Formular wird nicht gesendet.
- **Postfachwarnung:** passenden aktiven Kollektor-Login prüfen. Nur bestätigen, wenn der Versand dorthin beabsichtigt ist.
- **Anhang zu groß oder nicht zugänglich:** Gesamtgröße reduzieren, GLPI-/PHP-Uploadlimits und Dokumentzugriff prüfen. Entfernte oder unlesbare Dokumente sind nicht wiederverwendbar.

## SMTP schlägt fehl oder die Nachricht kommt nicht an

SMTP-Host, Port, TLS/Authentifizierung und Entitätsabsender in GLPI prüfen. Ticketmailer hat keine eigenen Zugangsdaten. Mit Auditfehler und GLPI-/Mailserver-Logs zwischen Verbindungs-, Anmelde-, Absenderregel- und nachgelagerten Filterproblemen unterscheiden.

Keine Zugangsdaten, Tokens, vollständigen Kundennachrichten oder unbereinigten Empfängerlisten in Issues veröffentlichen. SMTP-Erfolg mit späterem Spamfilter oder Rückläufer ist nicht dasselbe wie ein SMTP-Versandfehler.

## Nachricht zugestellt, Ticket aber unvollständig

Bei `sent_with_error` den fehlgeschlagenen Schritt bestimmen:

- **Folgeeintrag:** Die Nachricht wurde auch dann versendet, wenn kein Timeline-Eintrag vorhanden ist.
- **Dokumentverknüpfung:** Ein Folgeeintrag kann bereits existieren. Im aktuellen Code nach 2.6.1 bleibt dessen Auditverknüpfung trotz Fehler bei der Dokumentverknüpfung erhalten.
- **Statuswechsel:** Versand oder Notizerstellung können erfolgreich gewesen sein, der gewünschte Statuswechsel jedoch nicht. Aktuellen Status und Lebenszyklusrechte prüfen.

Fehlende GLPI-Daten mit den regulären berechtigten Verfahren korrigieren oder dokumentieren. **Nicht erneut auf Senden klicken, nur um Folgeeintrag, Dokumentverknüpfung oder Statuswechsel herzustellen.** Bei neuen Konversationen außerdem prüfen, ob das neue Ticket bereits existiert.

## Gesendeter Anhang fehlt bei einer späteren Antwort

Zuerst die installierte Version prüfen. In Release 2.6.1 fehlt die Änderung, die neu gesendete Uploads als auswählbare Ticketdokumente speichert. Ein Update ergänzt ältere Uploads nicht rückwirkend. Bei Code mit dieser Korrektur Dokumentrechte, Lesbarkeit der Datei und einen möglichen Auditstatus `sent_with_error` prüfen.

## Unerwarteter Inhalt, Status oder Benachrichtigungen

- Entitätsvorgaben und persönliche Einstellungen für Vorlage, Signatur und Wartend prüfen.
- Wartend kann vorausgewählt sein. Ausschalten, wenn der Status unverändert bleiben soll.
- Ohne gewählten Wechsel erhalten Version 2.6.1 und neuer den Status bei Antworten und Notizen. Älterer Code kann anders reagieren.
- Eine Lösungsvorlage fügt Text ein. Der Schalter Gelöst fordert den Statuswechsel an.
- E-Mail-Folgeeinträge unterdrücken native Benachrichtigungen. Interne Notizen können weiterhin native Benachrichtigungen für private Folgeeinträge auslösen.
- Vollständige BCC-Sichtbarkeit in Timeline und Audit ist beabsichtigt. Siehe [Datenschutz](https://github.com/rgruenewald/glpi-ticket-email-client/wiki/Administratorhandbuch-DE#datenschutz-aufbewahrung-und-deinstallation).

## Fehler melden

Plugin-/GLPI-/PHP-Version, aktives Profil und Entitätskontext, Schritte mit synthetischen Daten, erwartetes und tatsächliches Ergebnis, Audit-ID/-Status und tatsächlichen SMTP-Versand angeben. Dafür [GitHub Issues](https://github.com/rgruenewald/glpi-ticket-email-client/issues) verwenden. Bei Sicherheitsproblemen stattdessen der [Sicherheitsrichtlinie](https://github.com/rgruenewald/glpi-ticket-email-client/blob/main/SECURITY.md) folgen und keine vertraulichen Details veröffentlichen.
