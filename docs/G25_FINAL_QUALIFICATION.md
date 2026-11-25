# G25 — Three-world candidate qualification / Recette des trois jeux

Base: G24 merged at 2f8a9845f9811caef654c9b70397ecc5550f6753. This is a private laboratory candidate, **not V1.0 release approval**.

## Acceptance matrix

| Area | Evidence on exact candidate SHA |
| --- | --- |
| Medieval | Real construction, population, lumber, campaign, market, diplomacy, reconnect |
| Industrial | Real construction, generation, campaign, market, diplomacy, reconnect |
| Guild Caravans | Real construction, offer, market, progression, diplomacy, reconnect |
| Sessions | Explicit logout revokes bearer (401); alternate Bob then Alice; no foreign owned building IDs; durable Alice building levels |
| Presentation | French/English, physical touch and keyboard map navigation, reduced motion, desktop and mobile captures |
| Rendering regression | 30 requestAnimationFrame intervals per viewport, p95 interval < 250 ms, SVG descendants < 10,000, no document overflow |
| Installation | G08 actual Docker Compose build, autonomous worker, three worlds, ten bots/world, private registration disabled |
| Distribution | M11 actual offline archive build and restore drills; M12 security and M13 release checks |
| Exact SHA | Ten required CI workflow conclusions, never inherited from earlier commits |

The rendering sample measures frame scheduling in the idle reduced-motion district after reconnect, not input latency, GPU throughput, peak FPS, memory or a capacity certification. Thresholds are deliberately broad regression guards for shared CI runners. Capture JSON only contains pack, dimensions, aggregate building count and numerical measurements; no token, password, account ID or building ID is serialized. Existing graphical captures are private laboratory fixtures.

## Run locally

From the candidate checkout, on an isolated Docker host, follow [G08 laboratory](G08-LOCAL-PLAYTEST.md). Then, with Node 22 and the supported Playwright version:

```sh
npm install --no-save --package-lock=false @playwright/test@1.56.1
npx playwright install chromium
npx playwright test tests/browser/local-lab.spec.cjs --workers=1
```

The browser scenario expects fresh disposable laboratory data. Never remove a volume containing real player data. After the CI completes, run `php tools/m13-actions-audit.php FULL_SHA` with the exact candidate commit. G08 retains PNG and G25 JSON in its screenshot artifact. The M11 packaging and database restore are separate CI evidence.

## Décision de qualification

Les tests automatiques ne remplacent pas la revue visuelle des captures réelles sur les trois jeux, ni les essais sur téléphone physique. Les objectifs de victoire intégralement atteints, les mesures de capacité en production, l'audit de sécurité exhaustif, la restauration externe et le staging autorisé restent à établir selon [M13](M13_RELEASE_ACCEPTANCE.md). Aucune approbation d'opérateur, publication, modification de licence, dépense ni mise en production implicite. Ne pas modifier le manifeste de release pour simuler un succès.

Statut initial : tests G25 soumis à CI, aucune conclusion anticipée.
