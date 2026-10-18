# Automatische UI-Performance-Regressionsprüfung

## Ausführen

~~~sh
npm ci
npx playwright install chromium
npm run test:ui
npm run test:performance
~~~

Auf frischen Linux-Rechnern gegebenenfalls npx playwright install --with-deps chromium verwenden. Ein vorhandenes Chromium kann über CHROMIUM (absoluter Pfad) ausgewählt werden. Abhängigkeiten sind versionsgebunden; package-lock.json wird eingecheckt.

Der Workflow .github/workflows/ui-performance.yml läuft bei jedem Pull Request und bei Pushes nach main. Rohmessungen und Screenshots werden auch bei Fehlern für 14 Tage als Artefakte gespeichert. Damit ein Fehler das Zusammenführen zwingend verhindert, muss der Repository-Administrator ui-performance als erforderlichen Statuscheck in den Branchregeln setzen. Der Workflow ändert diese externe Einstellung nicht selbst.

## Testfälle und Grenzen

- 30 unbeteiligte Klassenänderungen über 30 Frames mit 1500 Timeline-Knoten: keine document.querySelectorAll-Aufrufe und keine jQuery-Toggles.
- 30 aktive Formularwechsel mit nativen/jQuery-Collapse-Ereignissen und Ajax: höchstens 150 document.querySelectorAll-Aufrufe und 45 jQuery-Toggles. Verlorene Bündelung und doppelte Abgleiche werden erkannt.
- Funktionale Browserprüfungen ohne künstliche Ajax-Hilfe für Observer-Lifecycles: Einfügen/Entfernen von Formularen, Klassenentfernung, verborgene Vorfahren und native Collapse-Zustände.
- JavaScript-Fehler im Browser lassen den Test fehlschlagen, statt scheinbar schnelle Ergebnisse zu erzeugen.
- Zeitbudgets: Median aus neun Messungen, zusätzlich ein verworfener Aufwärmlauf je Szenario. Feste optimierte Referenz und aktueller Code werden im selben Browser auf demselben Rechner gemessen.

Zeitgrenze für unbeteiligte Mutationen: größerer Wert aus Referenz × 1,6 und Referenz + 1 ms. Für aktive Wechsel: größerer Wert aus Referenz × 1,4 und Referenz + 3 ms. Überschreitungen führen zu einem Fehlercode. Die Toleranz reduziert CI-Fehlalarme; sie garantiert nicht, jede minimale Verlangsamung zu erkennen. Deterministische Arbeitsbudgets sind strenger als Zeitmessungen.

Für aktive Layout- und Stylezeiten gilt jeweils die größere Grenze aus Referenz × 1,6 und Referenz + 2 ms. Taskzeiten bleiben reine Diagnosewerte. Das Gate schützt den hier optimierten Synchronisationspfad, nicht gesamte GLPI-Ladezeit, TinyMCE, Netzwerk, Paint oder jede UI-Komponente.

## Referenz pflegen

Die Referenzen tests/performance/reference-timeline.js und reference.css sind feste Kopien des optimierten Stands. Sie wird nicht zur Laufzeit aus Produktcode generiert und nicht bei Fehlern automatisch aktualisiert. Änderungen daran benötigen eine bewusste Prüfung von Messwerten und Budgets. Grenzen nicht lediglich erhöhen, damit ein roter Test grün wird.

Die Referenz verwendet festgeschriebenes CSS, der Kandidat das aktuelle CSS. Damit werden auch in der Fixture wirksame CSS-Regressionen unabhängig verglichen. Eine gemeinsame Verlangsamung des Messgerüsts wird nicht erkannt. Bei neuen Szenarien die Referenz und Messmethode unabhängig überprüfen.

## Wirksamkeitsnachweis

- Dreizehn Budgettests prüfen erlaubtes Rauschen, wiederholte DOM-Arbeit, zusätzliche Toggles, langsame Abläufe und ungültige Messdaten.
- Eine reale Negativprobe außerhalb des Repository fügte einer privaten Referenzkopie 2 ms CPU-Arbeit pro Abgleich hinzu. Der Browserlauf scheiterte erwartungsgemäß: 65,410 ms > 9,488 ms im aktiven Szenario. Das Produkt blieb unverändert.
- Der unveränderte optimierte Produktcode besteht den vollständigen Vergleich.

Für isolierte Negativproben kann PERFORMANCE_CANDIDATE_SCRIPT auf eine private JavaScript-Datei zeigen. Im normalen CI-Lauf ist diese Variable nicht gesetzt. Ergebnisse liegen unter test-results/performance/ und sind von Git ausgeschlossen.

Die Workflowdatei ist lokal geprüft, aber noch nicht auf GitHub ausgeführt. Branchschutz wurde nicht geändert.

Ein unabhängiges natives Pi-Review fand die zunächst fehlende CSS-Isolation und Browserbereinigung auf Fehlerpfaden. Beide wurden behoben: feste CSS-Referenz plus Layout-/Stylebudgets sowie browser.close() in finally. Die tatsächliche Modellfamilie des Combo-Reviewers bleibt nicht verifizierbar.
