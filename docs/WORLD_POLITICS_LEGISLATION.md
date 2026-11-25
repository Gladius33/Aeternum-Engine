# WP40 — French ordinary legislation pilot

Opt-in fr-legislative-v1, with no new SQL migration. Constitutional basis:
https://qpc360.conseil-constitutionnel.fr/constitution-4-octobre-1958
(articles 10, 42, 44, 45, 61). Government ordinary bills only.
A reviewed one-clause catalog has two immutable text variants. The first
ordinary readings enforce simplified six/four-week delays. Accelerated
procedure requires certified absence of joint opposition. Alternate chamber
readings and certified amendments preserve text hashes; identical positive
votes adopt the text. CMP after two readings each, or one each when
accelerated; both chambers may approve the CMP compromise. If it fails,
new readings precede a government-requested final Assembly vote. Limited
final text choices, no full amendment tree or committee rules. This is a
conservative procedural subset, not a complete legislative rulebook.

Adoption, transmission, referral, review, promulgation and publication
are separate events. Review suspends the simplified fifteen-day promulgation
clock; an adverse decision rejects the entire single-clause bill. A separate
publication fixes a future effective day. Presidential requests for
reconsideration, partial censorship, finance/organic bills and 49(3)
integration remain open. Same-day events are ordered by journal; timing
and admissibility beyond implemented checks are certified by the local clerk.

The owner may draft via institutions/decisions (legislation_draft with
policy_id). GET /worlds/{world}/countries/FR/legislation is read-only.
No public vote or publication route. Trusted local operator:
WP_LEGISLATION_CLERK=YES php bin/legislation.php certify evidence.json
and ... apply world-id bill-id with dedicated DB environment.
The latter verifies replay, published text, matching effective day and
pending domestic proposal, publishes a registry proof then authorizes the
policy. It is recoverable after interruption, not a single cross-aggregate
transaction. The existing world fence and registry guard each write.
Older publication routes are rejected for WP40 worlds. Only the standard
education text matches existing fiscal coefficients; amended text cannot
silently enact the standard policy. No legal authority outside the game.
Tests include actual domestic budget effects via the coordinated day clock.
Autonomous votes, full navette, amendment-level effects, 49(3) and
automatic clerk evidence are still open.
