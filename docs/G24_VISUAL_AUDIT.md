# G24 visual audit — 2026-11-24

Evidence reviewed before changes: real GitHub Actions run 45495473614, artifact 14968521863 (three local game packs, domain desktop/mobile and productive domain). Run 45495474382, artifact 14970446380 (medieval/industrial browser evidence), and error artifact 14970251568 for G23. No generated mockups were used as evidence.

Findings: the desktop domains show distinct theme palettes and authored holdings, but angular background ridges and sparse ground details make scenery look schematic. Mobile captures expose a large vertical gap and navigation overlays from sticky positioning at arbitrary screenshot scroll offsets; introductory controls occupy too much space. The three skins need clearer contrast, stable selection and keyboard focus. The legacy classic evidence is intentionally more technical and must not be mistaken for the modern experience.

Implemented: bounded original terrain compound paths (eight per scene), depth-based grass/ballast/sand patterns and smoothed horizon; compact mobile mission and navigation, removal of the sticky capture overlap, contrast and focus rules, preserving nav DOM on unchanged profile and locale. Capture procedure resets scroll and adds map-only evidence at both viewport sizes. No gameplay rules or authoritative holdings changed.

Acceptance: deterministic art contract for 18 combinations, mobile navigation geometry, overflow, actual stage selection and map-only screenshots in three real worlds. Final visual inspection of the new captures and real-device FPS/memory remain outstanding until CI produces the evidence. These are visual refinements, not a claim of final AAA-quality artwork.
