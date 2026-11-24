# WORLD POLITICS — BRANCH ONLY / NO MERGE INTO AETERNUM MAIN

**Binding project separation decision — 24 November 2026.**

Aeternum Engine is a **generic reusable game engine**, NOT the World Politics game. 
The `feat/world-politics-wp01-identity-government` branch exists **only as a temporary workspace**
because the game has not yet been moved to its own repository.

**NEVER merge this branch, its descendants, or World Politics-specific files into the
`main` branch of `Gladius33/Aeternum-Engine`.** This remains true if all CI checks are green.
Do not create a pull request targeting engine `main` for the game. Avoid auto-merge.
Keep the original engine and its other games independent.

Continue World Politics-specific development on this isolated branch, or on
dedicated game branches deriving from it and not merged into engine `main`.
When the user establishes a dedicated World Politics repository, migrate the
game package, source history, tests and documentation there; make Aeternum
an explicitly versioned generic dependency or engine integration, instead of
mixing application code into the engine mainline.

Generic improvements to Aeternum should be designed independently and should
only enter engine `main` with the user's separate explicit approval. This
document is a project instruction, not a substitute for GitHub branch protections.
