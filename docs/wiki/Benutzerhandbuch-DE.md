<!-- markdownlint-disable MD013 -->

# Benutzerhandbuch (Deutsch)

[Wiki-Startseite](Home) · [English](User-Guide-EN)

## 1. Zweck

Mit **Ticket Email Client** versenden berechtigte Anwender E-Mails direkt aus einem GLPI-Ticket. Die E-Mail wird über die zentrale SMTP-Konfiguration von GLPI versendet. Ein erfolgreicher Versand wird zusätzlich im Ticketverlauf und im Versandprotokoll dokumentiert.

Das Plugin ist kein eigenständiger E-Mail-Client. Entwürfe, automatischer Neuversand und eingehende E-Mail-Verarbeitung gehören nicht zum Funktionsumfang.

## 2. Voraussetzungen

- Sie sind in GLPI angemeldet.
- Sie dürfen das betreffende Ticket lesen.
- Zum Verfassen und Versenden benötigen Sie die Berechtigung, das Ticket zu aktualisieren bzw. Folgeaktivitäten hinzuzufügen.
- Die Plugin-Funktion muss für Ihre Entität und Ihr Profil freigegeben sein.

Fehlt **E-Mail antworten**, wenden Sie sich an Ihre GLPI-Administration.

## 3. E-Mail aus einem Ticket erstellen

1. Öffnen Sie das gewünschte Ticket.
2. Wählen Sie im Ticketverlauf **E-Mail antworten** bzw. **Reply**.
3. Das Formular öffnet sich direkt im Ticket.

> **Screenshot-Platzhalter:** Ticketansicht mit der Aktion „E-Mail antworten“ / “Email reply”.

Je nach Konfiguration kann das Formular beim Öffnen eines Tickets bereits ausgeklappt sein. Die native GLPI-Antwortfunktion kann zusätzlich sichtbar bleiben.

## 4. Empfänger

Das Formular bietet **An**, **CC** und **BCC**.

### Automatische Vorbelegung

- Anforderer → **An**
- Beobachter → **CC**
- Zugewiesene Bearbeiter → nicht automatisch hinzugefügt

Fehlende E-Mail-Adressen werden ausgelassen. Prüfen Sie die Vorbelegung vor jedem Versand.

### Empfänger hinzufügen

- Suchen Sie interne GLPI-Benutzer über die Autovervollständigung.
- Tragen Sie externe E-Mail-Adressen direkt ein.
- Trennen Sie mehrere Adressen mit Komma, Semikolon oder Eingabetaste.
- Entfernen Sie Empfänger über das Entfernen-Symbol; **Leeren** entfernt alle Einträge eines Feldes.

Mindestens eine gültige Adresse in **An**, **CC** oder **BCC** ist erforderlich. Ein Versand nur an BCC ist möglich. Ungültige Einträge werden nicht stillschweigend übersprungen, sondern als Fehler angezeigt.

> **Screenshot-Platzhalter:** Empfängerfelder mit internen und externen Empfängern.

### Wichtig: Sichtbarkeit von BCC

BCC-Adressen sind in der versendeten E-Mail nicht als sichtbare An-/CC-Kopfzeile enthalten. Die vollständige BCC-Liste ist jedoch für **alle Benutzer mit Leserecht auf das Ticket** im Ticketverlauf und Versandprotokoll sichtbar. Verwenden Sie BCC daher nicht, um Adressen vor anderen Ticketlesern zu verbergen.

## 5. Betreff und Nachricht

- **Betreff** ist erforderlich.
- **Nachricht** darf nicht leer sein.
- Der Editor unterstützt formatierte HTML-Inhalte.
- Abhängig von der GLPI-Konfiguration können Vorlagen und Quellen angeboten werden.
- Eine konfigurierte Signatur bzw. ein Betreffpräfix kann automatisch eingefügt werden.

> **Screenshot-Platzhalter:** Formular mit Betreff und Rich-Text-Editor.

## 6. Anhänge und eingebettete Bilder

### Neue Dateien anhängen

- Wählen Sie **Dateien auswählen** oder ziehen Sie Dateien in den Anhangsbereich.
- Mehrere Dateien sind möglich.
- GLPI-Uploadgrenzen gelten weiterhin.
- Entfernen Sie versehentlich hinzugefügte Dateien vor dem Versand.

### Bilder in die Nachricht einbetten

Ziehen Sie ein Bild in den Nachrichteneditor oder fügen Sie es aus der Zwischenablage ein. Nur Bilddateien können inline eingebettet werden.

### Öffentliche Ticket-Anhänge übernehmen

Unter **Öffentlichen Ticketverlauf anhängen** können angebotene öffentliche Ticket-Anhänge einzeln ausgewählt werden. Über das Öffnen-Symbol lässt sich ein angebotener Anhang vorab in einem neuen Tab prüfen.

Private Folgeaktivitäten und deren Dokumente werden nicht angeboten oder versendet.

> **Screenshot-Platzhalter:** Anhangsbereich, Inline-Bild und Auswahl öffentlicher Ticket-Anhänge.

## 7. Öffentlichen Ticketverlauf anhängen

Aktivieren Sie **Öffentlichen Ticketverlauf anhängen**, um den öffentlichen Ticketverlauf an den Nachrichtentext anzufügen. Die Option ist standardmäßig nicht aktiviert.

Die Auswahl einzelner öffentlicher Ticket-Anhänge ist davon unabhängig: Verlaufstext und Dateien können getrennt gewählt werden.

## 8. Ticketstatus nach Versand

Neben **Senden** können – abhängig von der bereitgestellten Oberfläche – Optionen zum Setzen des Ticketstatus verfügbar sein:

- auf **Wartend** setzen;
- auf **Gelöst** setzen.

Prüfen Sie diese Optionen vor dem Versand. Voreinstellungen können durch die Administration festgelegt sein.

## 9. Warnung bei einem eingehenden Postfach

Stimmt ein Empfänger exakt mit dem E-Mail-Login eines aktiven GLPI-Mail-Collectors überein, erscheint eine Warnung. Dadurch soll ein möglicher E-Mail-Kreislauf vermieden werden.

1. Prüfen Sie die markierten Empfänger.
2. Entfernen oder korrigieren Sie die Adresse, falls sie unbeabsichtigt gewählt wurde.
3. Soll trotzdem versendet werden, aktivieren Sie **Ich verstehe und möchte trotzdem senden**.
4. Senden Sie erneut.

Der Abgleich ist nur eine Best-Effort-Prüfung. Aliase, Weiterleitungen und Logins ohne E-Mail-Format werden nicht erkannt.

> **Screenshot-Platzhalter:** Postfachwarnung mit Bestätigungsfeld.

## 10. Senden und Ergebnis

1. Prüfen Sie Empfänger, BCC-Sichtbarkeit, Betreff, Nachricht, Anhänge und Statusoptionen.
2. Wählen Sie **Senden**.
3. Klicken Sie nicht mehrfach. Das Plugin führt pro akzeptiertem Versandvorgang genau einen SMTP-Versuch aus und startet bei Fehlern keinen automatischen Neuversand.

### Mögliche Ergebnisse

- **Gesendet:** SMTP-Versand erfolgreich; Eintrag im Ticketverlauf wurde erstellt.
- **Fehlgeschlagen:** SMTP-Versand fehlgeschlagen; es gibt keinen erfolgreichen Versandseintrag im Ticketverlauf.
- **Unvollständiger Versand (Timeline fehlgeschlagen):** Die E-Mail wurde versendet, aber der Eintrag im Ticketverlauf konnte nicht erstellt werden. **Nicht einfach erneut senden**, da sonst eine doppelte E-Mail entstehen kann. Informieren Sie die Administration und nennen Sie Ticket sowie Zeitpunkt.
- **Ausstehend:** Der Versandvorgang ist intern noch nicht abschließend dokumentiert. Bleibt dieser Zustand bestehen, informieren Sie die Administration.

## 11. Versand im Ticket prüfen

Nach vollständigem Erfolg erscheint eine normale Folgeaktivität im Ticketverlauf. Sie enthält Absender, Versandzeit, An/CC/BCC, Betreff, Nachricht und sichere Links zu Anhängen.

Alle Benutzer mit Leserecht auf das Ticket können diese Angaben einschließlich der vollständigen BCC-Liste sehen und die Anhänge öffnen.

> **Screenshot-Platzhalter:** Erfolgreicher E-Mail-Eintrag im Ticketverlauf.

## 12. Versandprotokoll öffnen

Im Ticket-Tab **Gesendete E-Mails** finden Sie die zugehörigen Protokolleinträge. Öffnen Sie einen Eintrag für:

- Empfänger einschließlich BCC;
- Nachricht und Klartextalternative;
- Anhänge und eingebettete Bilder;
- Versandstatus;
- Fehlerdetails;
- dokumentierte Bestätigung einer Postfachwarnung.

Der Zugriff setzt Leserecht auf das Ticket voraus.

> **Screenshot-Platzhalter:** Tab „Gesendete E-Mails“ und Detailansicht.

## 13. Fehlerbehebung

| Problem | Vorgehen |
| --- | --- |
| **E-Mail antworten** fehlt | Ticketrechte prüfen lassen; Plugin-Freigabe für Entität/Profil durch Administration prüfen lassen. |
| Ungültige Adresse | Angezeigten Eintrag vollständig korrigieren oder entfernen. |
| Kein Empfänger | Mindestens eine gültige Adresse in An, CC oder BCC eintragen. |
| Upload fehlgeschlagen / Datei zu groß | Dateityp und GLPI-Uploadgrenze beachten; kleinere Datei versuchen; sonst Administration kontaktieren. |
| Postfachwarnung | Empfänger prüfen; nur bewusst mit Bestätigungsfeld fortfahren. |
| Versand fehlgeschlagen | Fehlerdetail im Versandprotokoll öffnen; Administration kontaktieren. Kein automatischer Neuversand. |
| Unvollständiger Versand | Nicht erneut senden; Administration informieren, da SMTP bereits erfolgreich war. |
| Anhang lässt sich nicht öffnen | Ticket-Leserecht und Anmeldung prüfen; bei fortbestehendem Fehler Administration kontaktieren. |

## 14. Datenschutz-Kurzcheck

Vor **Senden**:

- Sind alle Empfänger korrekt?
- Dürfen alle Ticketleser die BCC-Adressen sehen?
- Enthalten Nachricht, öffentlicher Verlauf und Anhänge nur freigegebene Informationen?
- Sind die gewählten Ticketstatus-Optionen korrekt?

## 15. Geplante Erweiterung

Ein separater [Administratorleitfaden](Administrator-Guide-Planning) ist geplant. Er wird Installation, Berechtigungen, Entitäts-/Profilregeln, SMTP-Voraussetzungen, Signaturen, Betreff, Statusoptionen, Timeline-Darstellung, Datenschutz und Fehlerdiagnose behandeln.
