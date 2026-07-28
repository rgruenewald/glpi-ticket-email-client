<!-- markdownlint-disable MD013 -->

# Benutzerhandbuch (Deutsch)

[Wiki-Startseite](Home) · [English](User-Guide-EN)

## 1. E-Mail aus einem Ticket senden

1. Öffnen Sie das Ticket.
2. Wählen Sie **E-Mail antworten**.
3. Das E-Mail-Formular öffnet sich im Ticket.

![Geöffnetes E-Mail-Formular im Ticket](images/email-compose-form.png)

Fehlt **E-Mail antworten**? Wenden Sie sich an Ihren GLPI-Administrator.

## 2. Empfänger wählen

Das Formular bietet **An**, **CC** und **BCC**.

Diese Empfänger sind oft schon eingetragen:

- Anforderer → **An**
- Beobachter → **CC**

Sie können weitere Empfänger hinzufügen:

- Suchen Sie GLPI-Benutzer über die Autovervollständigung.
- Tragen Sie andere E-Mail-Adressen direkt ein.
- Trennen Sie mehrere Adressen mit Komma, Semikolon oder Eingabetaste.

Mindestens eine gültige Adresse ist nötig. Ein Versand nur an BCC ist möglich. Ungültige Adressen werden angezeigt.

### BCC ist im Ticket sichtbar

Empfänger der E-Mail sehen die BCC-Adressen nicht.

Alle Ticketleser sehen sie im Versandprotokoll. Nach erfolgreichem Versand stehen sie auch im Ticketverlauf.

Nutzen Sie BCC nicht, um Adressen vor anderen Ticketlesern zu verbergen.

## 3. Betreff und Nachricht schreiben

- **Betreff** und **Nachricht** sind Pflichtfelder.
- Sie können den Text formatieren.
- Eine Vorlage kann Betreff und Signatur einfügen.
- Sie können Betreff, Nachricht und Signatur ändern.
- Empfänger aus der Vorlage werden nicht übernommen.
- Sie sehen nur Daten, für die Sie eine Berechtigung haben.

## 4. Dateien und Bilder hinzufügen

### Neue Dateien

Wählen Sie **Dateien auswählen**. Sie können Dateien auch in den Anhangsbereich ziehen.

Prüfen Sie die Dateien vor dem Senden. Es gelten die Upload-Grenzen von GLPI.

### Bilder in der Nachricht

Ziehen Sie ein Bild in den Editor. Sie können es auch aus der Zwischenablage einfügen.

### Dateien aus dem Ticket

Sie können öffentliche Ticket-Anhänge auswählen. Private Vermerke und deren Dateien werden nicht angeboten.

## 5. Öffentlichen Ticketverlauf anhängen

Aktivieren Sie **Öffentlichen Ticketverlauf anhängen**, wenn der Empfänger den öffentlichen Verlauf erhalten soll.

Die Option ist standardmäßig ausgeschaltet. Private Vermerke werden nicht versendet.

## 6. Ticketstatus wählen

Neben **Senden** können diese Optionen erscheinen:

- Ticket auf **Wartend** setzen
- Ticket auf **Gelöst** setzen

Prüfen Sie die Auswahl vor dem Senden.

## 7. Warnung vor einem E-Mail-Kreislauf

GLPI warnt, wenn ein Empfänger zu einem aktiven Mail-Collector passt.

1. Prüfen Sie die Adresse.
2. Entfernen oder korrigieren Sie die Adresse bei Bedarf.
3. Bestätigen Sie den Versand nur, wenn die Adresse richtig ist.
4. Senden Sie erneut.

Die Prüfung erkennt nicht alle Aliase und Weiterleitungen.

## 8. E-Mail senden

1. Prüfen Sie Empfänger, Betreff und Nachricht.
2. Prüfen Sie Dateien und Statusoptionen.
3. Wählen Sie **Senden** einmal.

Das Plugin startet keinen automatischen Neuversand.

## 9. Versand prüfen

Nach einem vollständigen Versand erscheint die E-Mail im Ticketverlauf.

![Erfolgreiche E-Mail-Einträge im Ticketverlauf](images/ticket-email-timeline.png)

Der Eintrag zeigt Nachricht, Empfänger und Anhänge. Alle Benutzer mit Leserecht auf das Ticket können diese Daten sehen.

Zusätzliche Details stehen im Tab **Gesendete E-Mails**.

![Liste gesendeter E-Mails](images/sent-email-log.png)

![Detailansicht einer gesendeten E-Mail](images/sent-email-detail.png)

## 10. Probleme lösen

| Problem | Lösung |
| --- | --- |
| **E-Mail antworten** fehlt | Wenden Sie sich an Ihre GLPI-Administration. |
| Adresse ist ungültig | Korrigieren oder entfernen Sie die Adresse. |
| Kein Empfänger | Tragen Sie mindestens eine Adresse ein. |
| Datei ist zu groß | Wählen Sie eine kleinere Datei. |
| Postfachwarnung erscheint | Prüfen Sie die Adresse. Bestätigen Sie nur bewusst. |
| Versand ist fehlgeschlagen | Öffnen Sie das Versandprotokoll. Wenden Sie sich an die Administration. |
| E-Mail fehlt im Ticketverlauf | Senden Sie nicht erneut. Die E-Mail könnte bereits zugestellt sein. Wenden Sie sich an die Administration. |
| Anhang lässt sich nicht öffnen | Prüfen Sie Anmeldung und Ticket-Leserecht. |
