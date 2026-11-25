# F05 — Player experience, original artwork and accessibility

## Actual visual evidence reviewed

The exact F04 candidate `d6127c4e32c89aad071c67555293edaca292bc6b` passed
[G08 run 45614401520](https://github.com/Gladius33/Aeternum-Engine/actions/runs/45614401520).
Downloaded artifact 15022580696 (about 105 MB) contains G24 map-only,
G25 reconnected domain and F04 victory captures. Reviewed all three
themes and both desktop and mobile, not mockups. All three F04 JSON
reports record victory, reconnect, research 2, expeditions 2.

| Theme | G25 SVG descendants | Desktop frame p95 | Mobile frame p95 |
|---|---:|---:|---:|
| Medieval | 1709 | 16.7 ms | 16.7 ms |
| Industrial | 806 | 16.7 ms | 16.8 ms |
| Guild | 1124 | 16.7 ms | 16.8 ms |

No horizontal overflow was reported. These are Chromium frame intervals
at 1440x900 and 390x844 under reduced motion, not physical-device FPS.
The mobile map shows overlapping circular level and completion markers
(especially the power plant and market hall). Completed campaign captures
are excessively tall: desktop 3874–4150 px, mobile 4723–4932 px, with
domain art and redundant objectives above or beside the chronicle.
Theme silhouettes are recognizable, but repeated bare road edges and
small markers compete with building labels.

## Implemented changes

- District art: one stage marker embedded in each building caption,
  descriptive accessible name and hidden duplicate model badges only in
  the district. Detailed architecture keeps its full level badges.
  Theme-specific compound-path road verges add grass, ballast and sand
  detail with two decorative paths per row; no simulated state changes.
- Campaign and council workspaces: focus on the relevant panel, remove
  the redundant modern objective list, keep the classic client unchanged,
  compact completed chapter artwork, improve touch sizes, field contrast
  and wrapping, and mark server-confirmed victory. Research receipt means
  scheduled, not completed; display the server's scheduled UTC time.
- Keyboard navigation supports arrows, Home and End without removing Tab
  navigation. Focus is restored to matching stable controls after eligible
  district or campaign redraws. Reduced-motion media preference disables
  animation/transition and smooth-scroll effects.
- Shared UI helpers provide scoped focus and bilingual failure feedback.
  The Vue TypeScript shell validates presentation event shapes and
  context; malformed or previous-account receipts are discarded.
- Lost network/invalid response is not a confirmation. A visible retry
  affordance uses the original pending command ID without exposing it.
  No automatic repetition of social, authentication or recovery writes.
  Pending command memory is session-local; sign-out clears it, and after
  sign-in the player must reconcile from authoritative server state.
  Existing audio remains opt-in; no external assets or new dependencies.

## Qualification

G08 keeps the full F04 campaigns and adds a separate real browser
scenario for each theme with independent players: ordinary escrow trade,
verified server reply abandoned then same-ID retry, alliance invite and
accept, literal private message, block/unblock, report and actual
operator-only moderation on the disposable lab, logout and reconnect.
Existing PHP Core, security, capacity, legacy browser, third-pack and
bot suites remain unchanged and required.

Per-theme JSON evidence includes keyboard navigation, successful
independent actor actions, CSS contrast >= 4.5 for navigation,
minimum nav target height 44 CSS px, no horizontal overflow, bounded
SVG nodes (<10000), p95 of 30 animation-frame intervals <250 ms and
bounded workspace interaction time (<30 s, includes test synchronization
and any authorized reads). No tokens, account IDs, or message contents
are written into these JSON metrics. Viewport-specific campaign and
district screenshots accompany the metrics. The real lab is the existing
HTTPS loopback Docker stack; no production data or external deployment.

## Human/physical-device checklist before release

1. Desktop Chrome/Firefox/Safari where available: all three skins,
   keyboard-only navigation, visible focus, zoom, readable status and
   long localized labels at 200% text zoom.
2. Android/iOS phones and tablet: touch selection, pinch and drag,
   portrait/landscape, no accidental command, keyboard dismissal,
   visible focus/status and no horizontal overflow.
3. Slow mobile device: sustained map navigation and session switching,
   sample interaction latency, CPU/GPU, memory and battery. Record device,
   browser, scene size and lighting. Do not infer physical-device
   performance from virtual runner frame intervals.
4. English/French: registration, recovery, campaign, trade, diplomacy,
   error and lost-reply flows; verify that retries preserve request
   identity and that reconnect does not leak previous player data.
5. Audio: opt-in gesture, mute, volume, reduced motion, background
   suspend, no autoplay or repeated receipt sound on refresh.
6. Independent player walkthrough: all three campaigns, trade, alliance,
   message, block/report and operator review. Retain screenshots and
   record observed defects rather than silently declaring approval.

This is an engineering refinement of original procedural SVG artwork.
No new 3D renderer, external asset license, or claim of AAA visual quality.
Physical-device and independent human acceptance remain operator gates.

## Qualification follow-up

The first F05 candidate returned the new stable `network_unavailable` code
instead of the browser-specific `Failed to fetch` text. The existing
lost-reply assertion now checks that code while retaining all receipt
and retry checks. A recovery replay received 429 before authentication;
the test now waits for the real quota window and still requires 401.
The medieval reconnect scenario stopped before loading stock. Initial
read failures now expose a read-only world reload control, with the
reconnect test logging the exact stable error and respecting quota windows.
The specific cause of the first missing-stock observation was not captured
in the original test log; it is not asserted to have been a quota failure.

## Observed F05 qualification

Corrected head `5f943bad5ee4f6b611d1ca6b66dcd388b2de849c` passed all
ten exact-head workflows, including Browser E2E run 45625210081 and
[G08 run 45625209953](https://github.com/Gladius33/Aeternum-Engine/actions/runs/45625209953).
G08 artifact 15029740393 is retained by the workflow. Merge
`118345bb`. Independent human and physical-device acceptance remain
separate from automated browser qualification.
