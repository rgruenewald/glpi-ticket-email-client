# Versionshinweise

[Start](https://github.com/rgruenewald/glpi-ticket-email-client/wiki/Home) · [English](https://github.com/rgruenewald/glpi-ticket-email-client/wiki/Version-Notes-EN) · [Releases](https://github.com/rgruenewald/glpi-ticket-email-client/releases) · [Vollständiges Changelog](https://github.com/rgruenewald/glpi-ticket-email-client/blob/main/CHANGELOG.md)

Diese Hinweise erklären die für Benutzer und Administratoren relevanten Änderungen seit dem bisherigen Wiki zu 2.4.0. Sie ersetzen weder Release-Pakete noch das vollständige Changelog. Vor der Nutzung unveröffentlichter Funktionen die tatsächlich installierte Version prüfen.

## Aktueller main nach 2.6.1: gesendete Anhänge, noch nicht veröffentlicht

Der Code enthält eine Anhangskorrektur nach dem zuletzt veröffentlichten Release **2.6.1**:

- Neue Uploads erfolgreich gesendeter E-Mails werden GLPI-Ticketdokumente und mit dem zugehörigen Folgeeintrag verknüpft.
- Sie stehen in späteren Antworten erneut zur Auswahl. Vorhandene Dokumente werden beim Wiederverwenden nicht dupliziert.
- Native Benachrichtigungen für die automatischen Dokumentverknüpfungen werden unterdrückt.
- Scheitert die Dokumentverknüpfung nach SMTP, lautet das Ergebnis `sent_with_error`. Die vorhandene Verknüpfung zum Folgeeintrag bleibt erhalten; SMTP wird nicht wiederholt.
- Ältere gesendete Uploads werden nicht automatisch als Ticketdokumente nachgetragen.

Die nächste Versionsnummer verwaltet der Release-Prozess. Ein vorhandener Changelog-Abschnitt bedeutet nicht, dass ein Paket 2.6.2 bereits veröffentlicht ist.

## 2.6.1: Statuserhalt und Lebenszyklusprüfung

- Antworten und interne Notizen erhalten den Ticketstatus ohne ausdrücklich gewählten Wechsel. Das gilt auch für gelöste oder geschlossene Tickets, sofern GLPI Kommunikation erlaubt.
- Native Folgeeintragsrechte, Statuswechsel und Pflichtfelder für das Lösen werden vor SMTP oder Notizerstellung geprüft.
- Gelöst bleibt bei fehlenden Voraussetzungen sichtbar, aber deaktiviert. Ein Formular mit veraltetem Ticketstatus wird abgewiesen.
- Ein erst nach Zustellung fehlgeschlagener Statuswechsel wird gemeldet, ohne die E-Mail erneut zu senden.

## 2.6.0: erweiterter Notizdialog

Interne Notizen können Anhänge, Folgeeintragsquelle und ausdrückliche Statusaktionen verwenden, abhängig von den nativen Rechten. Der Dialog bietet Folgeeintrags-, Aufgaben- und Lösungsvorlagen. Vorlagentext einzufügen legt weder eine Aufgabe an noch löst es das Ticket. Native Benachrichtigungen für private Folgeeinträge bleiben vom Versand der verfassten E-Mail getrennt.

## 2.5.0: konfigurierbare Oberfläche

Administratoren können Texte, Symbole, Tooltips und Sichtbarkeit von Dialogschaltflächen und Feldern konfigurieren. Die native Antwort lässt sich zu Ticketmailer umleiten. Sichtbarkeitseinstellungen umgehen weder Serverberechtigungen noch Validierung.

## 2.4.0: bisheriger Dokumentationsstand

Die vorhandenen Screenshot-Beispiele stammen aus dieser Dialoggeneration. Aktuelle Berechtigungen, Statusprüfungen und Anhangsspeicherung können von den abgebildeten Schaltern abweichen. Bei Abweichungen ist der Text dieser Seiten maßgeblich.
