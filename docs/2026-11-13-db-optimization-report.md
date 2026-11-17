# Database optimization report (2026-11-13)

History: the module's database usage was audited on 2026-11-08 (`db-optimizations.md` in the CUZY modules workspace), the changes were implemented, reviewed and merged into `main` on 2026-11-13, then reviewed again before release.

## Findings

| ID | Finding | Status |
|---|---|---|
| KCK1 | Login and back-channel logout looked up `user_auth` by unindexed columns | Done (module column); the `(source, source_id)` index of every login is in core: humhub/humhub#8750 |
| KCK2 | The groups full sync hydrated full ActiveRecord graphs and did O(n²) array scans | Done |

## Changes

- `GroupsFullSync`: the Keycloak users are read as `user_id, source_id` pairs (no model hydration), with a reverse map for O(1) `isKeycloakUser()` / `getKeycloakUserId()`; group members are read as ids.

## Migration

`m261108_100000_add_keycloak_sid_index` — adds `user_auth (keycloak_sid)` (a column of this module on a core table). `uninstall.php` drops it. No data change. Reversible.

## Tests

`KeycloakSidIndexTest`, `GroupsFullSyncMapsTest` (maps built from enabled Keycloak users only; group members keep only Keycloak users). Suite: 3 tests pass.

## Pre-release review

- Map semantics checked against the former `indexBy('user_id')` + `ArrayHelper::map()`: one Keycloak id per user (the latest auth row), same lookups.
- No issue found.
