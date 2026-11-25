# WP37 — campaign survival and terminal outcomes

The objective in fr-survival-v1 is continued possession of the fictional
French head-of-state office. Creation pins a fictional player president.
Older worlds retain their original semantics. A distinct fr-revolution-case-v1
is an explicitly scripted four-day narrative case for testing and illustrating
the lifecycle; it is not the ordinary survival campaign.

A cabinet resignation, disputed constitutional order, attempted revolution
and failed revolution are nonterminal. Successful replacement of the
constitutional order by a certified fictional narrative event ends the
campaign. Presidential electoral defeat, mandate expiry, resignation,
removal and death also end it without automatically changing the regime.
Dissatisfaction, strikes, opinion indices and deadline breaches alone
never decide revolutionary success. No calibrated real-world prediction,
coercive decision mechanics, or numerical regime-survival optimization is
implemented.

The immutable fictional outcome queue is authored by a trusted scenario
operator or future simulation worker; no public POST route exists. The local
CLI requires WP_CAMPAIGN_AUTHOR=YES and the dedicated database environment.
Supply a JSON file with world_id, country FR, day, id, type and source
curated_fictional_outcome. The queue validates chronology, uniqueness,
state transitions, world scope and the ten-year horizon under the world
fence. A terminal outcome cannot have later queued events. In normal
survival mode no forced defeat date is seeded.

Migration 022 adds campaign_enabled, game_over and the event queue. The
opinion checkpoint consumes the exact day's event and archives its
provenance; final coherent clock publication and the terminal flag are
atomic. Mutation fence rejects subsequent world gameplay writes; clock
replay of the terminal receipt still works. Member read access and
account administration remain available. GET /worlds/{world}/campaign
exposes the archived outcome; ordinary roster and institutions remain
historical snapshots, with campaign status authoritative for player tenure.
The initial character is a placeholder; custom player creation, actual
presidential election mechanics, automatic verified outcome producers and
French constitutional succession integration are remaining work.
