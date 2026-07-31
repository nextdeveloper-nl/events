-- PostgreSQL

CREATE TABLE event_available (
    id           bigint NOT NULL DEFAULT nextval('event_available_id_seq'::regclass),
    uuid         uuid DEFAULT gen_random_uuid(),
    event        text NOT NULL,
    description  text,
    created_at   timestamp with time zone NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at   timestamp with time zone NOT NULL DEFAULT CURRENT_TIMESTAMP,
    deleted_at   timestamp with time zone,
    CONSTRAINT event_available_pkey PRIMARY KEY (id)
);
