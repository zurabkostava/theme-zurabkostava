-- Apply before deploying send-push. This migration does not delete application data.
BEGIN;

-- Keep selection and the progress increment in one round trip. Only the selected
-- word leaves Postgres; all matching words, including those beyond row 1000, qualify.
CREATE OR REPLACE FUNCTION public.wordevo_pick_push_card(p_schedule_id uuid)
RETURNS jsonb
LANGUAGE plpgsql
SECURITY INVOKER
SET search_path = ''
AS $function$
DECLARE
    schedule public.notification_schedules%ROWTYPE;
    chosen public.cards%ROWTYPE;
    chosen_id uuid;
BEGIN
    SELECT * INTO schedule FROM public.notification_schedules
    WHERE id = p_schedule_id AND enabled;
    IF NOT FOUND THEN RETURN NULL; END IF;

    SELECT c.id INTO chosen_id
    FROM public.cards AS c
    WHERE c.user_id = schedule.user_id
      AND (schedule.dictionary_id IS NULL OR c.dictionary_id = schedule.dictionary_id)
      AND (coalesce(schedule.progress_range, '') = '' OR
           c.progress BETWEEN split_part(schedule.progress_range, '-', 1)::numeric
                          AND split_part(schedule.progress_range, '-', 2)::numeric)
      AND (coalesce(cardinality(schedule.tags), 0) = 0 OR EXISTS (
          SELECT 1 FROM public.card_tags AS ct
          JOIN public.tags AS t ON t.id = ct.tag_id
          WHERE ct.card_id = c.id AND t.user_id = schedule.user_id
            AND t.name = ANY(schedule.tags)
      ))
    ORDER BY random()
    LIMIT 1;
    IF NOT FOUND THEN RETURN NULL; END IF;
    SELECT * INTO chosen FROM public.cards WHERE id = chosen_id;

    UPDATE public.cards SET progress = least(100, coalesce(progress, 0) + 1)
    WHERE id = chosen.id AND progress IS DISTINCT FROM 100;
    RETURN jsonb_build_object('id', chosen.id, 'word', chosen.word,
                             'main_translations', chosen.main_translations);
END
$function$;
REVOKE ALL ON FUNCTION public.wordevo_pick_push_card(uuid) FROM PUBLIC, anon, authenticated;
GRANT EXECUTE ON FUNCTION public.wordevo_pick_push_card(uuid) TO service_role;

-- Preserve the current URL, credentials, HTTP parameters and minute schedule.
-- Invoke the original HTTP command only when a reminder is due in Tbilisi.
-- The original command stays quoted inside cron.job, never in this repository.
DO $migration$
DECLARE
    target_id bigint;
    original_command text;
BEGIN
    SELECT jobid, command INTO STRICT target_id, original_command
    FROM cron.job WHERE jobname = 'send-push-notifications';
    IF position('wordevo_due_guard_v1' IN original_command) = 0 THEN
        IF original_command NOT ILIKE '%net.http_post%' OR original_command NOT ILIKE '%/send-push%' THEN
            RAISE EXCEPTION 'Unexpected notification cron command; no changes applied';
        END IF;
        PERFORM cron.alter_job(target_id, command := format(
            'DO $wordevo_due_guard_v1$ BEGIN IF EXISTS (
                SELECT 1 FROM public.notification_schedules
                WHERE enabled AND time = to_char(now() AT TIME ZONE ''Asia/Tbilisi'', ''HH24:MI'')
                  AND days @> ARRAY[extract(dow FROM now() AT TIME ZONE ''Asia/Tbilisi'')::integer]
            ) THEN EXECUTE %L; END IF; END $wordevo_due_guard_v1$;', original_command));
    END IF;
END
$migration$;
COMMIT;
