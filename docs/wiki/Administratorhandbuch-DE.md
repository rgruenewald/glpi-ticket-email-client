# Administratorhandbuch

[Start](https://github.com/rgruenewald/glpi-ticket-email-client/wiki/Home) · [English](https://github.com/rgruenewald/glpi-ticket-email-client/wiki/Administrator-Guide-EN) · [Installation](https://github.com/rgruenewald/glpi-ticket-email-client/wiki/Installation-DE) · [Benutzerhandbuch](https://github.com/rgruenewald/glpi-ticket-email-client/wiki/Benutzerhandbuch-DE)

## Umfang und Berechtigungen

Ticketmailer ist ein E-Mail-Client im Ticketkontext, kein Postfach und keine Benachrichtigungs-Engine. Es gibt keinen IMAP-Leser, keine Entwurfsspeicherung und keine eigene SMTP-Konfiguration. Empfängervorschläge für sichtbare GLPI-Benutzer stehen ab zwei eingegebenen Zeichen bereit. Eingehende Antworten hängen weiterhin von GLPI-Mailkollektoren und der Routing-Konfiguration ab.

Benutzer benötigen Ticketzugriff und das native GLPI-Recht zum Hinzufügen eines Folgeeintrags. Der Server prüft Entitätszugriff und Folgeeintragsrechte beim Absenden, auch bei gelösten und geschlossenen Tickets. Die Antwortrichtlinie steuert die Darstellung, nicht die Berechtigung. Administrative Einstellungen erfordern GLPI-Konfigurationsrechte.

## Versandtransport und Absender

SMTP in den zentralen E-Mail-Einstellungen von GLPI konfigurieren und testen. Ticketmailer verwendet `GLPIMailer` direkt und stellt die verfasste Nachricht nicht in die Benachrichtigungswarteschlange. Pro Sendeanfrage erfolgt ein SMTP-Versuch. Das garantiert keine einmalige Zustellung über manuelle Wiederholungen oder Netzwerkfehler hinweg.

Bei Antworten ermittelt GLPI Absenderadresse und Anzeigenamen aus der Ticketentität. Bei einer neuen ausgehenden Konversation bestimmt die gewählte Absendereinheit Absender, Vorlage, Signatur, Einstellungen und die spätere Ticketentität. Nur zugängliche Entitäten sind wählbar. Ein ungültiger Absender oder eine nicht verfügbare Entität verhindert den Versand.

Ein Wechsel der Absendereinheit lädt entitätsspezifische Vorgaben neu. Eingegebener Betreff, Empfänger und Nachricht bleiben erhalten. Diese Felder danach erneut prüfen lassen.

## Globale und entitätsspezifische Einstellungen

Unter **Konfiguration → Allgemein → Ticket-E-Mail** liegen globale Vorgaben und Oberflächeneinstellungen. Der Reiter **Ticket-E-Mail** einer Entität enthält deren Einstellungen. Menübezeichnungen richten sich nach der GLPI-Sprache.

| Einstellung | Zweck |
| --- | --- |
| Standard-Benachrichtigungsvorlage | Liefert anfänglichen Betreff und Nachricht aus einer GLPI-Benachrichtigungsvorlage. Dadurch wird kein Versand über die Benachrichtigungs-Engine aktiviert. |
| Signatur | Rich-Text-Signatur mit unterstützten Variablen. Eine nicht leere Signatur wird entlang der Entitätshierarchie gesucht. |
| Standardmäßig Wartend | Anfangswert des Schalters Wartend, sofern das Ticket diesen Wechsel erlaubt. |
| Neueste Timeline-Einträge zuerst | Steuert die Anzeigereihenfolge der Timeline. |
| Antwortrichtlinie je Profil | Bietet die E-Mail-Aktion an, hebt sie hervor oder fordert das Ersetzen der nativen Antwort an. |

Benutzer können, soweit angeboten, eigene Vorgaben für Vorlage, Signatur und Wartend speichern. Diese Vorgaben sind Einstellungen, **keine Entwürfe**. Entitätskonfiguration und Benutzervorgaben können erklären, warum zwei Benutzer unterschiedliche Anfangsinhalte sehen.

Die globale Konfiguration steuert außerdem Texte, Symbole, Tooltips und Sichtbarkeit der Ticket-E-Mail-Einstiege und Dialogfelder. Ein ausgeblendetes Feld gewährt keine Rechte und hebt keine Servervalidierung auf. Nach Änderungen einen echten Dialog prüfen. Pflichtangaben nicht ohne gültige Vorgaben ausblenden.

## Antwortrichtlinie und native Antwort

Die Auswertung verwendet Ticketentität und aktives Profil. Vorrang hat die genaue Entitäts-/Profilrichtlinie, danach die entitätsweite Richtlinie und zuletzt die globale Vorgabe Verfügbar.

| Modus | Wirkung |
| --- | --- |
| Verfügbar | Bietet Ticketmailer neben der nativen GLPI-Antwort an. |
| Hervorgehoben | Stellt Ticketmailer als bevorzugte Antwortaktion dar. |
| Nativ ausblenden | Fordert das Ersetzen über einen unterstützten GLPI-Erweiterungspunkt an. Ohne verifizierten Erweiterungspunkt gilt als Rückfall Hervorgehoben. |

Es gibt weder Core-Patch noch DOM-/CSS-Verstecken als Ersatz. Die Oberflächenoption **Native „Antworten“ öffnet Ticket-E-Mail** leitet die native Aktion zu Ticketmailer um. Eine technisch nicht unterstützte Unterdrückung darf nicht als erzwungene Richtlinie verstanden werden.

## Vorlagen, Signaturen und Quelle des Folgeeintrags

GLPI-Benachrichtigungsvorlagen liefern den anfänglichen Betreff und Nachrichtentext. Folgeeintrags-, Aufgaben- und Lösungsvorlagen stehen in den jeweiligen Bereichen des Dialogs bereit und lassen sich einfügen oder kopieren. Eine Aufgaben- oder Lösungsvorlage als Text legt nicht selbst eine GLPI-Aufgabe oder Lösung an.

Die Einstellungen zeigen unterstützte Ticket-, Bearbeiter- und Entitätsvariablen unter **Verfügbare Variablen**. Nicht verfügbare Werte werden leer dargestellt. HTML aus Vorlagen und Signaturen wird bereinigt. Vorlagen mit synthetischen Tickets in jeder relevanten Sprache und Entität prüfen.

Eine optionale Quelle des Folgeeintrags muss aktiv und für Folgeeinträge zugelassen sein. Erst **Ticketstatus auf Gelöst setzen**, nicht die Auswahl eines Lösungstextes, führt die ausdrückliche Lösungsaktion aus, sofern GLPI sie erlaubt. Pflichtfelder und Lebenszyklusrechte werden vor dem Versand geprüft.

## Empfängervorgaben und Postfachwarnung

- To: Ticket-Anforderer. CC: Ticket-Beobachter. BCC: leer. Zugewiesene Bearbeiter werden nicht automatisch in CC aufgenommen.
- Der Server validiert und dedupliziert ohne Beachtung der Groß- und Kleinschreibung. Vorrang: To → CC → BCC.
- Eine Ticketantwort benötigt mindestens einen Empfänger in To, CC oder BCC. Eine neue ausgehende Konversation benötigt mindestens einen To-Empfänger.
- Entspricht ein Empfänger dem gültigen E-Mail-Login eines aktiven GLPI-Kollektors, ist vor dem Versand eine ausdrückliche Bestätigung über eine CSRF-geschützte Anfrage nötig.

Die Warnung arbeitet nach **Best Effort**. Aliasse, Weiterleitungen, nicht als E-Mail-Adresse geführte Logins, inaktive Kollektoren und bestimmte Postfachkonfigurationen werden nicht erkannt. Sie ist kein Schutz vor allen Mail-Schleifen. Eingehende Post separat konfigurieren und überwachen.

## Timeline, Status und Benachrichtigungen

Eine erfolgreiche E-Mail wird als regulärer GLPI-Folgeeintrag mit Empfängern, vollständigem Betreff, Nachricht und sicheren Anhangslinks dargestellt. Dieser entsteht mit `_disablenotif=1`: Die SMTP-E-Mail ist die Zustellung an die Empfänger; der Folgeeintrag darf keine zusätzliche native E-Mail auslösen.

Ohne gewählten Statuswechsel bleibt der aktuelle Ticketstatus bei Antworten und Notizen erhalten. Wartend und Gelöst erfordern die nativen Lebenszyklusrechte, Gelöst zusätzlich die einschlägigen Pflichtfelder. Gelöst bleibt sichtbar, ist bei fehlenden Voraussetzungen aber deaktiviert. Hat sich der Ticketstatus seit dem Öffnen geändert, wird das veraltete Formular vor dem Versand abgewiesen. Fehler nach der Zustellung lösen keine SMTP-Wiederholung aus.

Interne Notizen sind native private Folgeeinträge ohne E-Mail-Empfängerfelder und Versand-Audit. **Native Benachrichtigungen für private Folgeeinträge können weiterhin gelten**, beschränkt auf berechtigte interne Benutzer. Keine vollständige Benachrichtigungsstille zusichern.

## Anhänge und Speicherung

Uploads und ausgewählte Ticketdokumente unterliegen Ticket-/Dokumentrechten und dem GLPI-Uploadlimit. Downloadlinks im Folgeeintrag prüfen das Ticketleserecht und geben keine rohen Speicherpfade preis.

Der aktuelle Code nach 2.6.1 speichert erfolgreich gesendete Uploads zusätzlich als GLPI-Ticketdokumente und verknüpft sie mit dem Folgeeintrag. Bereits vorhandene Dokumente werden nicht dupliziert. Automatische native Benachrichtigungen für diese Dokumentverknüpfungen werden unterdrückt. Ältere gesendete Uploads werden nicht durch eine Migration nachträglich zu Ticketdokumenten.

Scheitert die Dokumentverknüpfung nach dem Versand, wird ein Teilfehler gemeldet. Die vorhandene Verknüpfung zum Folgeeintrag bleibt bestehen, SMTP wird nicht wiederholt. Vor einer Korrektur Timeline und Ticketdokumente prüfen.

## Datenschutz, Aufbewahrung und Deinstallation

**Vollständige BCC-Adressen sind für jeden Ticketleser im Folgeeintrag und Auditdetail sichtbar.** Nur in den sichtbaren To-/CC-Headern der E-Mail wird BCC nicht aufgeführt. Dies vor dem Einsatz mit den Verantwortlichen für die Daten klären.

Das Plugin speichert Empfängerlisten, Betreff, bereinigtes HTML/Klartext, Anhangsmetadaten, Auditstatus, Konfiguration und Benutzervorgaben in der GLPI-Datenbank. Dateien liegen unter `GLPI_PLUGIN_DOC_DIR/ticketmailer/`. Der aktuelle Code kann zusätzlich GLPI-Dokumentdatensätze für gesendete Dateien anlegen. Datenbank und relevante GLPI-Dateien in Backups aufnehmen. Es gibt keine automatische Ablauf- oder Aufbewahrungsbereinigung.

Eine Deinstallation löscht die Plugin-Tabellen einschließlich Auditprotokoll. Deaktivieren erhält sie. Aufbewahrung und Export vor einer Deinstallation klären. Siehe [Installation und Entfernung](https://github.com/rgruenewald/glpi-ticket-email-client/wiki/Installation-DE).

## Prüfung im Betrieb

Für Fehlerfälle die [Fehlerbehebung](https://github.com/rgruenewald/glpi-ticket-email-client/wiki/Fehlerbehebung-DE), für versionsabhängiges Verhalten die [Versionshinweise](https://github.com/rgruenewald/glpi-ticket-email-client/wiki/Versionshinweise-DE) verwenden. Fehlermeldungen mit Versionen, Audit-ID/-Status und bereinigten Reproduktionsschritten melden, niemals mit SMTP-Zugangsdaten oder echten Kundennachrichten.
