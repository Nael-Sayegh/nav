-- À exécuter manuellement dans la base de données (ce projet n'a pas de système
-- de migration automatisé, cf. admin/adminer pour l'exécuter).
--
-- Tables support de la file d'attente d'envoi de newsletter en plusieurs
-- passes (cf. plan_fiabilisation_newsletter.txt, section 3.1).

CREATE SEQUENCE newsletter_campaigns_id_seq INCREMENT 1 MINVALUE 1 MAXVALUE 9223372036854775807 CACHE 1;

CREATE TABLE "newsletter_campaigns" (
    "id" bigint DEFAULT nextval('newsletter_campaigns_id_seq') NOT NULL,
    "subject" character varying(255) NOT NULL,
    "body_html" text NOT NULL,
    "body_text" text NOT NULL,
    "site" character varying(10) NOT NULL,
    "status" character varying(20) DEFAULT 'pending' NOT NULL,
    "created_by" bigint,
    "created_at" bigint NOT NULL,
    "last_progress_at" bigint DEFAULT '0' NOT NULL,
    CONSTRAINT "newsletter_campaigns_pkey" PRIMARY KEY ("id")
)
WITH (oids = false);

CREATE SEQUENCE newsletter_campaign_recipients_id_seq INCREMENT 1 MINVALUE 1 MAXVALUE 9223372036854775807 CACHE 1;

CREATE TABLE "newsletter_campaign_recipients" (
    "id" bigint DEFAULT nextval('newsletter_campaign_recipients_id_seq') NOT NULL,
    "campaign_id" bigint NOT NULL,
    "mail" character varying(255) NOT NULL,
    "hash" character varying(80) NOT NULL,
    "status" character varying(20) DEFAULT 'pending' NOT NULL,
    "attempts" smallint DEFAULT 0 NOT NULL,
    "last_error" text,
    "sent_at" bigint,
    CONSTRAINT "newsletter_campaign_recipients_pkey" PRIMARY KEY ("id"),
    CONSTRAINT "newsletter_campaign_recipients_campaign_fk" FOREIGN KEY ("campaign_id") REFERENCES "newsletter_campaigns" ("id") ON DELETE CASCADE
)
WITH (oids = false);

CREATE INDEX "newsletter_campaign_recipients_campaign_status_idx" ON "newsletter_campaign_recipients" ("campaign_id", "status");
