<!-- markdownlint-disable MD013 -->

# Benutzerhandbuch: GLPI Ticket Email Client

[Dokumentationsstart](https://github.com/rgruenewald/glpi-ticket-email-client/wiki/Home) · [English](https://github.com/rgruenewald/glpi-ticket-email-client/wiki/User-Guide-EN) · [Installation und Administration](https://github.com/rgruenewald/glpi-ticket-email-client/wiki/Installation-DE)

Mit Ticketmailer schreiben Sie E-Mails aus einem Ticket, beginnen eine neue Unterhaltung oder erfassen eine interne Notiz direkt in GLPI. Dieses Handbuch beschreibt den aktuellen Code auf `main`, einschließlich Änderungen nach Release **2.6.1**. Unterstützte Laufzeit: GLPI 11.0.x und PHP ab 8.2; die dokumentierte Referenzumgebung ist GLPI 11.0.8. Die [Versionshinweise](https://github.com/rgruenewald/glpi-ticket-email-client/wiki/Versionshinweise-DE) trennen veröffentlichte und noch unveröffentlichte Änderungen.

Die Bilder stammen als Beispiele aus der Testinstanz zu Version 2.4.0 mit synthetischen Tickets und Empfängern unter example.test. Sichtbare Aktionen und Felder hängen von Ihren Rechten und der Konfiguration ab. Je nach GLPI-Sprache und Konfiguration heißt die E-Mail-Aktion auch **Antworten**.

## Schnellstart

1. Öffnen Sie ein Ticket und wählen Sie **E-Mail antworten** oder die konfigurierte Aktion **Antworten**.
2. Lassen Sie **E-Mail** ausgewählt. Prüfen Sie An, CC und BCC.
3. Schreiben Sie Betreff und Nachricht. Fügen Sie die benötigten Dateien hinzu.
4. Prüfen Sie die Statusschalter und mögliche Empfängerwarnungen.
5. Wählen Sie einmal **Senden**. Kontrollieren Sie das Ergebnis im Ticketverlauf und im Versandprotokoll.

> **BCC ist innerhalb von GLPI nicht vertraulich.** Jeder mit Leserecht auf das Ticket sieht die vollständige BCC-Liste im Ticketbeitrag und im Versanddetail. In den sichtbaren An-/CC-Kopfzeilen der E-Mail erscheinen BCC-Empfänger nicht.

## 1. Aus einem Ticket antworten

Die Antwortaktion öffnet einen Dialog über dem Ticket. **E-Mail** ist vorausgewählt. Der Dialog enthält Empfängerfelder, Betreff, den GLPI-Texteditor und Anhänge.

![Aktueller E-Mail-Dialog mit Empfängern, Ticketkennung im Betreff, Editor, Anhängen und Statusschaltern](https://raw.githubusercontent.com/wiki/rgruenewald/glpi-ticket-email-client/images/current/compose-de.png)

Fehlt die Aktion? Lassen Sie die Ticketrechte und die Antwortrichtlinie prüfen. Für den Versand sind Ticketzugriff und die native GLPI-Berechtigung zum Hinzufügen eines Folgeeintrags erforderlich. Das Recht zum Aktualisieren des Tickets genügt allein nicht. Ein reines Leserecht erlaubt keinen Versand.

### Empfänger auswählen

- **An:** Antragsteller des Tickets werden vorbelegt.
- **CC:** Beobachter des Tickets werden vorbelegt.
- Zugewiesene Bearbeiter werden nicht automatisch hinzugefügt.
- **BCC:** Empfänger hinzufügen, die nicht in den sichtbaren Kopfzeilen der E-Mail stehen sollen. Ticketleser können sie trotzdem sehen.

Trennen Sie mehrere Adressen durch Komma, Semikolon oder Zeilenumbruch. Für GLPI-Benutzer geben Sie mindestens zwei Zeichen eines Anmeldenamens, Namens oder einer Standard-E-Mail-Adresse ein und wählen einen Vorschlag. Die Vorschläge berücksichtigen die Sichtbarkeit der Einheiten. Externe E-Mail-Adressen sind ebenfalls möglich.

Prüfen Sie die Empfängermarken vor dem Versand. Über **×** entfernen Sie eine Adresse; die Löschschaltfläche des Feldes leert das Feld. Mindestens ein gültiger Empfänger in An, CC oder BCC ist erforderlich. Versand ausschließlich an BCC ist erlaubt. Ungültige, nicht leere Adresseingaben führen zu einem Fehler und werden nicht stillschweigend entfernt.

### Betreff und Zuordnung von Antworten

Schreiben Sie den Betreff neben der festen Kennung **[GLPI #Ticketnummer]**. Diese Kennung bleibt normalerweise im versendeten Betreff, damit GLPI eingehende Antworten dem Ticket zuordnen kann.

Aktivieren Sie **Neue Konversation beginnen** nur, wenn Sie die Kennung bewusst weglassen möchten. **Antworten können dadurch ein neues Ticket erzeugen.** Die Option selbst erstellt beim Antworten aus einem bestehenden Ticket kein weiteres Ticket.

Betreff und Nachricht dürfen nicht leer sein. Je nach Einheitskonfiguration kann eine Ticketvorlage Betrefftext und eine bearbeitbare Signatur liefern. Prüfen Sie beides vor dem Versand.

### Vorlagen und Wissensdatenbank

Nutzen Sie die verfügbaren **Antwortvorlagen**, **Lösungsvorlagen**, die Quellenauswahl und die **Wissensdatenbank**, um die Nachricht vorzubereiten. Einzelne Bedienelemente können administrativ ausgeblendet werden.

Prüfen Sie eingefügten Inhalt. **Das Übernehmen eines Wissensartikels kann den Editorinhalt ersetzen.** Kopieren Sie Text, den Sie behalten möchten, vor dem Einfügen. Die Auswahl einer Vorlage ändert nicht automatisch den Ticketstatus. Prüfen Sie dafür die Statusschalter gesondert.

## 2. Dateien, eingebettete Bilder und öffentlicher Verlauf

### Neue Datei hochladen

Wählen Sie **Dateien auswählen** oder ziehen Sie Dateien in den Anhangsbereich. Prüfen Sie die angezeigte Auswahl. Es gelten die Uploadgrenzen von GLPI. Zu große oder abgewiesene Dateien müssen vor dem Versand korrigiert werden.

Auf dem aktuellen `main` werden Uploads erfolgreich gesendeter E-Mails zusätzlich als Ticketdokumente gespeichert und mit dem Folgeeintrag verknüpft. Sie sind in späteren Antworten erneut auswählbar. Vorhandene Dokumente werden nicht dupliziert. Diese Änderung ist noch nicht in Release 2.6.1 enthalten.

Für eingebettete Bilder nutzen Sie die Bildfunktionen des Editors, fügen ein kopiertes Bild ein oder ziehen ein Bild in den Nachrichtentext. Kontrollieren Sie die Darstellung vor dem Versand.

### Vorhandene Ticketanhänge verwenden

Wenn am Ticket oder an öffentlichen Ticketbeiträgen Dateien hängen, können Sie die gewünschten Dateien einzeln auswählen. Öffnen Sie einen Dateilink zum Prüfen in einem eigenen Tab. Anhänge privater Ticketbeiträge werden über diese Auswahl weder angeboten noch versendet.

### Öffentlichen Ticketverlauf hinzufügen

**Öffentlichen Ticketverlauf anhängen** ist zunächst ausgeschaltet. Die Option ergänzt den öffentlichen Ticketverlauf im Nachrichtentext. Sie wählt trotz ihrer Bezeichnung nicht automatisch vorhandene Dateien aus. Verlauf und Dateiauswahl sind voneinander unabhängig.

Private Ticketbeiträge und deren Dokumente bleiben ausgeschlossen. Prüfen Sie auch den öffentlichen Verlauf: Er kann Informationen enthalten, die Sie diesen Empfängern nicht weitergeben möchten.

## 3. Interne Notizen und Moduswechsel

Wählen Sie **Interne Notiz**, um das native GLPI-Formular für private Ticketbeiträge zu verwenden. Es enthält keine Ticketmailer-Empfängerfelder und keine automatisch erzeugte E-Mail-Signatur. Schreiben Sie die Notiz, fügen Sie bei Bedarf Dateien hinzu, prüfen Sie die Statusoptionen und wählen Sie **Hinzufügen**.

![Aktueller Dialog für eine interne Notiz mit nativem GLPI-Editor und der Aktion Hinzufügen](https://raw.githubusercontent.com/wiki/rgruenewald/glpi-ticket-email-client/images/current/internal-note-de.png)

Die Notiz ist nach den nativen GLPI-Rechten und Sichtbarkeitsregeln privat. **Sie durchläuft weder den SMTP-Versand noch das Versandprotokoll von Ticketmailer. Die in GLPI konfigurierten Benachrichtigungen für Ticketbeiträge gelten jedoch weiterhin.** Eine private Notiz bedeutet deshalb nicht grundsätzlich, dass keine Benachrichtigungs-E-Mail entstehen kann.

Beim Wechsel zwischen E-Mail und interner Notiz bleiben die Eingaben und Anhänge der beiden Formulare erhalten, solange der aktuelle Dialog eingebunden bleibt. Das sind keine gespeicherten Entwürfe. Verlassen Sie sich nach Neuladen, Verlassen des Tickets oder Sitzungsende nicht auf eine Wiederherstellung.

[Video: E-Mail → Interne Notiz → E-Mail](https://raw.githubusercontent.com/wiki/rgruenewald/glpi-ticket-email-client/videos/switch-modes.mp4) (etwa 7 Sekunden, englische Oberfläche, ohne Ton).

*Die echte Bildschirmaufnahme zeigt, wie die Empfängerfelder im Notizmodus verschwinden und der E-Mail-Text beim Zurückwechseln wieder erscheint. Anfang und Ende sind gekürzt. Die Aufnahme läuft in Originalgeschwindigkeit und zeigt keinen Versand.*

## 4. Ticketstatus wählen

Ohne ausdrücklich gewählten Statuswechsel behalten Antworten und Notizen den bisherigen Status bei, auch bei gelösten oder geschlossenen Tickets, sofern GLPI Folgeeinträge erlaubt.

| Schalter | Wirkung |
| --- | --- |
| **Wartend** | Setzt das Ticket nach der Aktion auf Wartend, sofern der Wechsel erlaubt ist. |
| **Gelöst** | Fordert nach der Aktion die Lösung des Tickets an. Bleibt sichtbar, ist aber bei fehlenden Rechten, Pflichtfeldern oder unzulässigem Lebenszyklus deaktiviert. |

Bei E-Mails ist Wartend standardmäßig aktiviert, sofern die Entitäts- oder Benutzervorgabe es so festlegt und der Wechsel zulässig ist. Schalten Sie es aus, wenn der Status unverändert bleiben soll. Eine Lösungsvorlage allein löst das Ticket nicht. Sollten beide Werte übermittelt werden, hat Gelöst Vorrang.

Hat sich der Status seit dem Öffnen geändert, laden Sie das Formular vor dem Senden neu. Statusänderungen erfolgen nach erfolgreichem Versand oder Anlegen der Notiz. Ein SMTP-Fehler ändert den Status nicht. Scheitert der Statuswechsel erst nach Zustellung, wird die E-Mail nicht erneut gesendet.

## 5. Warnung vor einem GLPI-Postfach

Stimmt ein Empfänger mit der E-Mail-Anmeldung eines aktiven GLPI-Mail-Kollektors überein, erscheint eine Warnung. Der Versand an einen Kollektor kann E-Mails wieder in GLPI einspeisen.

1. Prüfen Sie die Adresse. Entfernen oder korrigieren Sie sie bei Bedarf.
2. Ist sie beabsichtigt, bestätigen Sie ausdrücklich den Versand trotz Postfachtreffer.
3. Senden Sie erst nach Prüfung der Warnung erneut ab.

Der Server prüft Adresse und Bestätigung vor dem Versand nochmals. **Die Erkennung ist nicht vollständig:** Sie vergleicht exakte, normalisierte E-Mail-Anmeldungen, aber keine Aliasse, Weiterleitungen oder Kollektor-Anmeldungen ohne E-Mail-Format. Keine Warnung ist kein Nachweis für eine sichere Adresse.

## 6. Senden und Ergebnis prüfen

Prüfen Sie Empfänger, Betreff, Text, Dateien, Verlauf und Statusoptionen. Wählen Sie anschließend einmal **Senden**. Ticketmailer unternimmt einen SMTP-Versuch über die vorhandenen GLPI-Mail-Einstellungen. Es gibt weder automatische Wiederholung noch Versandwarteschlange.

| Ergebnis | Bedeutung | Nächster Schritt |
| --- | --- | --- |
| Gesendet und protokolliert | SMTP war erfolgreich und die E-Mail wurde als Ticketbeitrag gespeichert. | Verlauf oder Versanddetail prüfen. SMTP-Erfolg beweist weder den Eingang im Postfach noch das Lesen durch den Empfänger. |
| Fehlgeschlagen | Der SMTP-Versuch ist fehlgeschlagen. | Geschütztes Versanddetail öffnen und die Ursache administrativ prüfen lassen. Es gibt keinen automatischen Neuversand. |
| Unvollständig | SMTP war erfolgreich, aber ein späterer GLPI-Schritt schlug fehl: Folgeeintrag, Dokumentverknüpfung oder gewünschter Statuswechsel. Ein Folgeeintrag kann bereits existieren. | **Nicht erneut senden.** Die E-Mail wurde bereits versendet. Lassen Sie den fehlgeschlagenen GLPI-Schritt prüfen. |

Bei Zeitüberschreitung oder unklarem Ergebnis prüfen Sie zuerst **Gesendete E-Mails**. Ein fehlender Verlaufseintrag bedeutet nicht automatisch, dass keine E-Mail versendet wurde.

### Ticketverlauf und Versandprotokoll

Ein vollständiger E-Mail-Versand erzeugt einen normalen öffentlichen Ticketbeitrag mit Absender, Zeit, An/CC/BCC, Betreff, Nachricht und geschützten Dateilinks. Dieser E-Mail-Beitrag löst keine zweite GLPI-Benachrichtigungs-E-Mail aus.

Öffnen Sie im Ticket **Gesendete E-Mails** und wählen Sie einen Betreff. Die Detailansicht zeigt Protokoll, Versandinformationen und Dateien. Nur Ticketleser können Nachrichtendetails und gespeicherte ausgehende Anhänge öffnen. Dazu gehört auch die vollständige BCC-Liste.

![Aktuelles Versanddetail mit vollständiger BCC-Liste, Nachricht und gespeichertem Anhang](https://raw.githubusercontent.com/wiki/rgruenewald/glpi-ticket-email-client/images/current/sent-detail-de.png)

Private Notizen bleiben native GLPI-Ticketbeiträge und erscheinen nicht als E-Mail-Einträge im Ticketmailer-Versandprotokoll.

## 7. Neue E-Mail ohne vorhandenes Ticket

Wählen Sie **Helpdesk → Neue E-Mail**. In der englischen GLPI-Oberfläche heißt das Menü **Assistance → New email**. Diese Aktion ist etwas anderes als **Neue Konversation beginnen** in einer Ticketantwort.

![Aktuelle Seite Neue E-Mail mit Absendereinheit, Empfängerfeldern, Editor und Senden](https://raw.githubusercontent.com/wiki/rgruenewald/glpi-ticket-email-client/images/current/new-email-de.png)

1. Wählen Sie **Absender**. Die Liste enthält berechtigte Einheiten mit einer konfigurierten Absenderadresse.
2. Ergänzen Sie Empfänger, Betreff, Nachricht und bei Bedarf Dateien.
3. Prüfen Sie die ausgewählte Einheit und Signatur.
4. Wählen Sie einmal **Senden**.

Beim Senden wird das Ticket in der gewählten Absendereinheit erstellt. Danach wird die E-Mail versendet und protokolliert. An-Empfänger werden Antragsteller, CC-Empfänger Beobachter. BCC-Empfänger werden keine Beobachter, bleiben aber für Ticketleser im Versanddatensatz sichtbar.

Die Absenderauswahl bestimmt Ticketeinheit und Signatur, ohne Ihre aktive GLPI-Einheit umzuschalten. Die Aktion benötigt Rechte zum Erstellen, Aktualisieren und Lesen von Tickets in einer berechtigten Einheit. Fehlt ein gültiger Absender, lassen Sie Einheitsadresse und Rechte prüfen.

Tritt nach der Ticketerstellung ein Fehler auf, prüfen Sie das erstellte Ticket und das Versandprotokoll, bevor Sie etwas wiederholen. Erzeugen Sie keine doppelten Tickets und versenden Sie keine E-Mail erneut, deren SMTP-Versuch bereits erfolgreich war.

## 8. Grenzen und Fehlersuche

- Ticketmailer ist ein ausgehender E-Mail-Editor, kein Posteingang. Es ruft keine E-Mails ab und synchronisiert kein IMAP.
- Es speichert keine dauerhaften Entwürfe, stellt keine Versandaufträge in eine Warteschlange und wiederholt den Versand nicht automatisch.
- Der Mailtransport wird in GLPI eingerichtet, nicht in Ticketmailer.
- Schaltflächen, Vorlagen, Quellenauswahl, Statusvorgaben und Reihenfolge des Verlaufs sind administrativ konfigurierbar.
- Auch mit bekannter Downloadadresse benötigen gespeicherte Anhänge ein Ticketleserecht.

Nennen Sie bei einer Fehlermeldung Ticketnummer, Zeitpunkt, angezeigtes Ergebnis und bereinigten Fehlertext. Veröffentlichen Sie keine SMTP-Zugangsdaten, echten Empfängerlisten oder privaten Ticketinhalte in öffentlichen Issues.

[Installation und Administration](https://github.com/rgruenewald/glpi-ticket-email-client/wiki/Installation-DE) · [Sicherheitsrichtlinie](https://github.com/rgruenewald/glpi-ticket-email-client/blob/main/SECURITY.md) · [Zur Dokumentationsstartseite](https://github.com/rgruenewald/glpi-ticket-email-client/wiki/Home)

## Weitere Hilfe

- [Installation](https://github.com/rgruenewald/glpi-ticket-email-client/wiki/Installation-DE)
- [Administratorhandbuch](https://github.com/rgruenewald/glpi-ticket-email-client/wiki/Administratorhandbuch-DE)
- [Fehlerbehebung](https://github.com/rgruenewald/glpi-ticket-email-client/wiki/Fehlerbehebung-DE)
- [Versionshinweise](https://github.com/rgruenewald/glpi-ticket-email-client/wiki/Versionshinweise-DE)
