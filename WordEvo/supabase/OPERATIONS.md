# WordEvo low-I/O update (2026-09-25)

Deployment order:
1. Apply `migrations/20260925_reduce_push_io.sql` in the AWorded project.
2. Deploy `functions/send-push/index.ts` as `send-push`.
3. Update the WordPress theme (including `data-access.js` and its template include).

The migration preserves the existing cron HTTP command, including its credentials,
inside `cron.job`. It adds a due-time guard using Asia/Tbilisi. Cron still runs every
minute; the Edge Function runs only for minutes with enabled reminders. Reminder
times, weekdays and device delivery remain unchanged.

`wordevo_pick_push_card` is SECURITY INVOKER, executable only by service_role and
its owner. It filters by schedule owner, dictionary, progress and tags, chooses a
single ID, then returns only the selected word. Tag filters with no matches no
longer fall through to unrelated words. There is no API 1,000-row truncation in
this selection. Selection is still a scan of eligible IDs, appropriate for the
requested 1,000–2,000 words; it is not claimed to be constant time at any scale.

Client reads are paginated and tag relations are restricted to the current
dictionary. Same-turn statistics/progress writes are coalesced and serialized;
there is no timed background write polling. Failures are shown to the user without
unbounded retries. This does not add guaranteed offline synchronization. Avoid
closing the app before pending saves finish. Existing multi-device absolute
statistics semantics are unchanged.

Keep `cleanup-cron-history` enabled (`17 * * * *`, up to 5,000 completed runs older
than seven days per run) while it catches up. Once the backlog is gone, daily
cleanup is sufficient. DELETE does not imply immediate physical file shrinkage.
Do not run VACUUM FULL just to shrink the displayed history size.

Verification: `node --test --test-isolation=none WordEvo/tests/*.test.cjs`.
The 10 tests cover 2,001 words / 5,500 relations, lower server caps, failed pages,
write ordering, coalescing, unchanged progress, and push error handling.

Rollback: restore prior theme/function source from Git and run
`rollback-push-guard.sql` to unwrap the original cron HTTP command. No credentials
need to be copied. The unused service-only RPC can remain installed.

The September outage's original system-level cause remains unproven. These changes
reduce avoidable load; they cannot guarantee availability of a hosted free service.
