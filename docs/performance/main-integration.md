# Integration von main in PR #32

Integrierter main-Stand: 9df8f93 (PR #31). Die historische Messreihe bleibt als Vergleich gegen dd61009 erhalten; sie ist keine neue Messung gegen main.

Konflikte aufgelöst:

- Optimierten Timeline-Observer und Regressionstests des Branches beibehalten.
- Aktuelle Docker-/Browser-Prüfanleitung von main mit dem neuen Performance-Gate kombiniert.
- test:e2e und @playwright/test aus main erhalten; direkte Playwright-Abhängigkeit auf dieselbe Version 1.64.1 angeglichen und Lockfile regeneriert.
- Composer-Idle-Preloading, neue Aktionen, übrige Produktfunktionen und CSS-Erweiterungen aus main übernommen.

Prüfungen nach Integration:

- npm run test:ui: bestanden (bestehende JS-Prüfungen und 13 Budgettests).
- npm run test:performance: bestanden, sowohl mit explizitem lokalem Chromium als auch mit dem von Playwright 1.64.1 ausgewählten Standard-Chromium.
- Standard-Chromium: aktive Scriptzeit Referenz 6,600 ms, Kandidat 6,934 ms; unbeteiligte Änderungen Referenz 0,636 ms, Kandidat 0,643 ms. Beide innerhalb der festgelegten Budgets; keine DOM-Abfragen/Toggles bei unbeteiligten Änderungen.
- configurable-actions.test.js und proactive-images.test.js: bestanden.
- npx playwright test --list: 11 E2E-Tests erfolgreich erkannt; nicht ausgeführt.
- v2-Strukturgate: 180 PASS, 0 FAIL.
- Diff gegen origin/main ohne Whitespacefehler. Beim Vergleich des Merge-Index gegen den alten Branch zeigt Git ausschließlich bereits aus main übernommene Whitespacefehler in inc/sendservice.class.php; diese wurden nicht sachfremd verändert.
- Unabhängiges frisches natives Pi-Review: keine konkreten Fehler der Konfliktauflösung; UI-/Performance-/Aktionsprüfungen dort ebenfalls bestanden. Modellfamilienunabhängigkeit des Combo-Alias nicht belegt.

Grenzen: Kein produktiver Versand, keine Deploymentänderung, keine echte GLPI-/Docker-E2E-Abnahme und kein PHPUnit-Lauf für diese Integration.
