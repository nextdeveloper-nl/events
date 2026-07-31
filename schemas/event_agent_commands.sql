-- PostgreSQL

CREATE TABLE event_agent_commands (
    id              bigint NOT NULL DEFAULT nextval('event_agent_commands_id_seq'::regclass),
    uuid            uuid NOT NULL DEFAULT gen_random_uuid(),
    agent_type      text NOT NULL,
    agent_uuid      uuid NOT NULL,
    operation       text NOT NULL,
    params          json,
    status          text NOT NULL DEFAULT 'pending'::text,
    result          json,
    error           text,
    iam_account_id  bigint,
    iam_user_id     bigint,
    sent_at         timestamp with time zone,
    completed_at    timestamp with time zone,
    timeout_at      timestamp with time zone,
    created_at      timestamp with time zone NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      timestamp with time zone NOT NULL DEFAULT CURRENT_TIMESTAMP,
    deleted_at      timestamp with time zone,
    CONSTRAINT event_agent_commands_iam_account_id_fkey FOREIGN KEY (iam_account_id) REFERENCES iam_accounts(id),
    CONSTRAINT event_agent_commands_iam_user_id_fkey FOREIGN KEY (iam_user_id) REFERENCES iam_users(id),
    CONSTRAINT event_agent_commands_pkey PRIMARY KEY (id)
);

CREATE INDEX event_agent_commands_agent_type_agent_uuid_idx ON public.event_agent_commands USING btree (agent_type, agent_uuid);
CREATE INDEX event_agent_commands_agent_uuid_idx ON public.event_agent_commands USING btree (agent_uuid);
CREATE INDEX event_agent_commands_iam_account_id_idx ON public.event_agent_commands USING btree (iam_account_id);
CREATE INDEX event_agent_commands_status_idx ON public.event_agent_commands USING btree (status) WHERE (status = ANY (ARRAY['pending'::text, 'sent'::text]));
CREATE INDEX event_agent_commands_timeout_at_idx ON public.event_agent_commands USING btree (timeout_at) WHERE (status = 'sent'::text);
CREATE UNIQUE INDEX event_agent_commands_uuid_idx ON public.event_agent_commands USING btree (uuid);
