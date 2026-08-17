# Lizenz (MIT) hinzufügen, CONTRIBUTING.md, CODE_OF_CONDUCT.md und Lizenz-Header einfügen

Dieses Issue fasst die notwendigen Repository‑Änderungen zusammen, um die Lizenz klar sichtbar zu machen, Mitwirkende anzuleiten und einen Verhaltenskodex bereitzustellen. Ziel ist, die Zusammenarbeit zu fördern und Lizenzinformationen konsistent im Repo zu verankern.

**Aktueller Stand**

- LICENSE (MIT) wurde bereits zum Repository hinzugefügt.
- Branch: `add-mit-and-contributing` ist vorhanden und kann für die Änderungen verwendet werden.

**Aufgaben**

- [ ] `README.md`: License‑Badge hinzufügen und einen Lizenz‑Abschnitt ergänzen (Link auf `LICENSE`).
  - Vorschlag:
    ```
    ![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)

    ## 📝 Lizenz

    Dieses Projekt steht unter der MIT License — siehe [LICENSE](./LICENSE) für Details.
    SPDX-Identifier: MIT
    ```

- [ ] `pdfimport.php`: Lizenz‑Header / Kurzinfo + SPDX‑Identifier ganz oben einfügen.
  - Vorschlag:
    ```php
    <?php
    /*
     * pdfimport — MIT License
     * Copyright (c) 2026 Thomas Kujawa
     * SPDX-License-Identifier: MIT
     *
     * See LICENSE file for details.
     */
    ```

- [ ] `CONTRIBUTING.md`: Kurzanleitung erstellen (Issues, PRs, Branch‑/Commit‑Konventionen, Tests).

- [ ] `CODE_OF_CONDUCT.md`: Kurzfassung des Contributor Covenant und Meldeweg (`webmaster@familienfreund.de`).

- [ ] Sicherstellen, dass `config/config.php` weiterhin in `.gitignore` bleibt (keine Änderung erforderlich).

- [ ] (Optional) Falls vorhanden: package/metadaten (z. B. `composer.json`) mit `"license": "MIT"` ergänzen.

- [ ] PR erstellen, die alle obigen Änderungen auf Branch `add-mit-and-contributing` zusammenfasst und dieses Issue referenziert.

**Akzeptanzkriterien**

- README zeigt License‑Badge und Lizenzabschnitt mit Link zu `LICENSE`.
- `pdfimport.php` enthält einen klaren Lizenzheader mit SPDX‑Identifier.
- `CONTRIBUTING.md` und `CODE_OF_CONDUCT.md` sind im Repo vorhanden.
- `config/config.php` bleibt ignoriert (sensible Daten nicht committed).
- Ein PR existiert, referenziert dieses Issue und fasst alle Änderungen zusammen.

**Labels (Vorschlag)**: `documentation`, `enhancement`

**Branch**: `add-mit-and-contributing`

---

Wenn du möchtest, kann ich nun die noch ausstehenden Änderungen (README, pdfimport.php, CONTRIBUTING.md, CODE_OF_CONDUCT.md) in dieser Branch committen und anschließend einen Pull Request erstellen, der dieses Issue referenziert. Soll ich das jetzt ausführen?