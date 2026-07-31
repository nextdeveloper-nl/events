-- PostgreSQL

CREATE TABLE event_listeners (
    id              bigint NOT NULL DEFAULT nextval('event_listeners_id_seq'::regclass),
    uuid            uuid DEFAULT gen_random_uuid(),
    event           text NOT NULL,
    callback        text NOT NULL,
    iam_account_id  bigint,
    created_at      timestamp with time zone NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      timestamp with time zone NOT NULL DEFAULT CURRENT_TIMESTAMP,
    deleted_at      timestamp with time zone,
    CONSTRAINT event_listeners_pkey PRIMARY KEY (id)
);
