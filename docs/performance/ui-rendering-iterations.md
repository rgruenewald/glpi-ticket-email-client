# UI-Rendering: Messreihe und Ergebnis

## Umfang

Ausgangspunkt: main, Commit dd61009. Änderungen ausschließlich in Branch/Worktree perf/ui-rendering-iterations. Keine Änderung an Versand, Empfängern, Berechtigungen oder laufenden Docker-Mounts. Bereits vorhandene andere Performance-Branches wurden weder überschrieben noch übernommen.

## Methode

Chromium 156.0.8078.4, Headless, 1280 × 900, keine CPU-Drosselung, jQuery 3.7.1. Pro Variante neun Wiederholungen mit frischem Browserdokument; Median. Fixture mit 1500 Timeline-Einträgen und Compose-/Notizflächen. Zwei Szenarien:

1. **Unbeteiligte UI-Änderungen:** 30 Klassenänderungen in 30 Frames außerhalb der Formulare; danach 400 ms Ruhezeit.
2. **Aktive UI:** 30 Compose-Zustandswechsel mit gleichzeitigem nativen Collapse-, jQuery-Collapse- und Ajax-Ereignis; danach 400 ms Ruhezeit.

CDP Performance-Metriken messen ScriptDuration, TaskDuration, LayoutDuration und RecalcStyleDuration. Queries zählen document.querySelectorAll einschließlich jQuery; Toggles zählen jQuery-Aufrufe. Das sind keine allgemeinen DOM- oder Paint-Zähler. Die Scriptzeit umfasst auch den Stimulus und Observer, nicht nur die Plugin-Funktion. Die 400 ms Wartezeit ist nicht die ausgewiesene Scriptzeit.

Dies ist ein isolierter Browserbenchmark, **keine Messung der Ladezeit einer echten GLPI-Seite**. Bootstrap und TinyMCE sind nicht enthalten. Die relativen Gewinne gelten für diese Lastfälle, nicht pauschal für das gesamte Produkt.

## Vorher/Nachher und Optimierungsschritte

| Stand | Änderung | Unbeteiligt: Script ms | Queries / jQuery-Toggles | Aktiv: Script ms | Aktiv: Task ms |
|---|---|---:|---:|---:|---:|
| 0 | unverändertes main | 18,671 | 845 / 169 | 21,425 | 82,257 |
| 1 | ein Abgleich pro Frame statt Timer-Bursts | 2,243 | 75 / 30 | 6,404 | 63,246 |
| 2 | nur relevante Mutationen beobachten | 1,574 | 0 / 0 | 6,804 | 64,843 |
| 3 | Klassen-/Nachfahrentest statt allgemeiner CSS-Suche | 0,677 | 0 / 0 | 6,797 | 62,207 |
| 4 | breite Nachfahrensuche nur bei Struktureingriffen; CSS vereinfachen | 0,635 | 0 / 0 | 6,544 | 62,649 |
| 5 | vorigen Compose-Klassenzustand berücksichtigen | 0,633 | 0 / 0 | 6,860 | 64,066 |
| 6 | vorigen nativen Collapse-Zustand berücksichtigen; Observer-Tests verschärfen | 0,704 | 0 / 0 | 6,575 | 61,946 |

Endstand gegenüber Ausgangspunkt:

- **96,2 % weniger Scriptzeit** bei unbeteiligten Änderungen. Keine vollständigen UI-Abgleiche mehr für diese Änderungen.
- **69,3 % weniger Scriptzeit** bei aktiven Zustandswechseln, Taskzeit **24,7 % niedriger**.
- Aktive Queries: 1295 → 150; jQuery-Toggles: 259 → 45.
- Aktive Layout-/Stylezeit nicht verbessert: Layout 1,788 → 1,902 ms, Style 2,880 → 2,900 ms. Kein belegter Layout- oder Paint-Gewinn.

Die großen Gewinne stammen aus Schritt 1 bis 3. Schritte 4 bis 6 bringen keinen weiteren belastbaren Geschwindigkeitsgewinn; 5 und 6 sichern Lifecycle-Korrektheit. Abbruch nach sechs Runden, unter dem Limit von zehn. Für diese Reihe gelten mindestens 10 % **und** 0,5 ms Scriptzeit als praktisch relevante Verbesserung. Keine statistische Signifikanzbehauptung; sequenzielle Messungen, keine kontrollierte Laborstudie.

## CSS

- Zwei identische Padding-Deklarationen für Editor-/Anhangzeilen zusammengeführt.
- Den getrennten zweiten recipient-control-Block in den ersten integriert.
- 15976 → 15944 Bytes, also 32 Bytes weniger. Keine Behauptung eines messbaren CSS-Geschwindigkeitsgewinns.
- GLPI-Overrides, !important und Regeln für seltene Zustände bleiben erhalten. Eine Klasse ohne Treffer in der isolierten Fixture ist kein Beleg für ungenutztes CSS.

## Funktions- und Darstellungsprüfung

- Node-Timeline-Test: bestanden, einschließlich verzögertem Abgleich, Burst-Bündelung, unbeteiligten Mutationen und Klassenentfernung.
- Node-Compose-Send-Lock-Test: bestanden.
- v2 score.sh: **111 PASS, 0 FAIL**.
- JS-Syntax und git diff --check: bestanden.
- Browser: native/Plugin-Formulare, mehrere Ereigniswege, dynamisches Einfügen/Entfernen, sichtbare Formulare außerhalb Collapse, versteckter Vorfahr, Entfernen der Compose-/Collapse-Klasse bestanden. Mit OBSERVER_CHECKS=1 kein künstlicher Ajax-Aufruf im Zustandshelfer.
- Gespeicherte Vorher-/Nachher-Screenshots der Fixture bei 1280 und 390 px: jeweils **0 unterschiedliche Pixel**, mit dem Vergleichsskript erneut bestätigt.
- PHPUnit nicht ausgeführt: kein lokales PHP/Composer/PHPUnit und keine vorhandene PHPUnit-Installation im laufenden Container. Kein Container oder Dienst für diesen Auftrag verändert.
- Kein echter GLPI-/Bootstrap-/TinyMCE-E2E-Lauf, keine Sendetests und keine Produktionsmessung.

## Unabhängige Prüfung

Frische native Pi-Sitzungen mit omni/combo/review, Denkstufe max, ausschließlich lesend. Frühe Befunde zu fehlender Observer-Abdeckung wurden mit Regressionstests behoben. Abschlussprüfung meldete keine verbleibenden funktionalen Observer-Befunde.

Prüfgrenzen: Die tatsächliche Modellfamilie des Combo-Routings konnte der Reviewer nicht bestätigen; Familienunabhängigkeit ist daher **nicht belegt**. Sein Screenshot-Lauf meldete 86 abweichende Pixel mobil. Der erneute lokale Vergleich der gespeicherten vollständigen Messreihe ergab dagegen 0 Pixel bei beiden Breiten. Ursache des abweichenden Review-Laufs ungeklärt. Der Reviewer nannte außerdem 180 strukturelle PASS; im hier ausgeführten Gate sind es reproduzierbar 111. Maßgeblich sind die eigenen ausgeführten Prüfungen, nicht diese unbestätigten Review-Angaben.

## Reproduktion

Abhängigkeiten außerhalb des Repositories installieren:

~~~sh
npm install --prefix /tmp/ticketmailer-ui-bench playwright pngjs jquery@3.7.1
export NODE_PATH=/tmp/ticketmailer-ui-bench/node_modules
export CHROMIUM=/path/to/chrome
node scripts/benchmark-ui.cjs /path/to/baseline /tmp/ui-before.json
OBSERVER_CHECKS=1 node scripts/benchmark-ui.cjs . /tmp/ui-after.json
node scripts/compare-ui-screenshots.cjs /tmp/ui-before.json /tmp/ui-after.json
node tests/ticket-timeline.test.js
node tests/composer-send-lock.test.js
ROOT=. bash .agent/contracts/glpi-ticket-email-client-v2/score.sh
~~~

CHROMIUM ist optional, wenn der im Skript hinterlegte lokale Cachepfad existiert. REPETITIONS steuert die Anzahl, Standard 9. Rohdaten und Screenshots liegen neben diesem Bericht. Zwischenstände der Timeline-Datei liegen in variants/iteration-N.js; für Nachmessungen diese Datei in eine private Fixture-Wurzel unter public/js/ticket-timeline.js legen und das unveränderte Baseline-CSS unter public/css/ticketmailer.css bereitstellen; Runde 4 verwendet das vereinfachte CSS. Varianten sind Messartefakte, keine Laufzeitdateien. Runden 5 und 6 sind Korrektheitskorrekturen auf Basis des finalen Codes.

## Weitere Verbesserungsmöglichkeiten

1. **Echte GLPI-Profile:** Erstöffnung des Dialogs, TinyMCE-/Select2-Initialisierung und Notiz-/Forward-Wechsel unter CPU-Drosselung messen. Dafür wurde hier weder das laufende Testdeployment umgemountet noch ein anderer Branch verändert.
2. **Composer-DOM-Aufbau:** Die Optionen/Layouts werden beim Initialisieren mehrfach abgefragt und umgehängt. Eine gebündelte Umstrukturierung ist denkbar, braucht aber repräsentative native GLPI-Fragmente und Integrationsmessungen. Ohne diese Evidenz nicht spekulativ geändert.
3. **Sichtbarkeits-Schreibzugriffe:** jQuery-Toggles könnten bei gleichbleibendem Zustand entfallen. GLPI kann die Gruppen zwischenzeitlich selbst verstecken; ein reiner Zustands-Cache wäre falsch. Ohne echten GLPI-Lifecycle-Nachweis nicht eingebaut.
4. **CSS-Nutzungsanalyse:** Coverage über alle Dialogzustände, Sprache, Mobil/Desktop und GLPI-Themes sammeln. Danach weitere Regeln entfernen; nicht allein nach dieser Fixture.

Diese Punkte sind mögliche nächste Untersuchungen, keine belegten verbleibenden Performancefehler. Weitere Mikrooptimierungen am untersuchten Observer liegen unter der praktischen Relevanzgrenze.
