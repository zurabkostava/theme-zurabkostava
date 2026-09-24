-- Restore the exact original HTTP cron command, without printing its credentials.
DO $rollback$
DECLARE
    target_id bigint;
    guarded text;
    original_command text;
    quoted_command text;
BEGIN
    SELECT jobid, command INTO STRICT target_id, guarded
    FROM cron.job WHERE jobname = 'send-push-notifications';
    IF position('wordevo_due_guard_v1' IN guarded) > 0 THEN
        quoted_command := substring(guarded FROM 'THEN EXECUTE (.*); END IF; END');
        IF quoted_command IS NULL THEN RAISE EXCEPTION 'Unknown guard format'; END IF;
        EXECUTE 'SELECT ' || quoted_command INTO original_command;
        PERFORM cron.alter_job(target_id, command := original_command);
    END IF;
END
$rollback$;
-- Redeploy the previous send-push source if reverting its RPC integration.
-- The service-only RPC may remain installed; old code does not call it.
