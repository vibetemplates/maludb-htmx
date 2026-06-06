-- Client-owned LLM connection + prompt tables (public schema — user application tables).
--
-- Replaces the API server's local MySQL store read by v1/memory_ingest.php via
-- LocalDatabase::modelPrompt($model). The system-owned maludb_* views/tables never hold
-- client tokens; these live with the rest of the user application tables in public.
--
-- A join of the two tables reproduces the $cfg array memory_ingest.php builds for
-- llm_complete(): api_format, base_url, model_identifier, token (api_key), max_tokens,
-- generation_params, plus the system_prompt.
--
-- Future (planned): client_api_token — sha256-hashed, shown-once HTTP auth tokens for the
-- upcoming MCP server interfacing with Retell AI. Managed from the same Token Setup page.

-- "Token Setup" page manages this: provider connections + API keys.
CREATE TABLE IF NOT EXISTS public.client_token (
    token_id    SERIAL PRIMARY KEY,
    token_name  TEXT NOT NULL UNIQUE,         -- e.g. 'openai-prod'
    api_format  TEXT NOT NULL CHECK (api_format IN ('openai','anthropic')),
    base_url    TEXT NOT NULL,                -- e.g. https://api.openai.com/v1
    api_key     TEXT NOT NULL,                -- entered once; never echoed back to the UI
    created_at  TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at  TIMESTAMPTZ NOT NULL DEFAULT now()
);

-- "Model Prompts" page manages this: per-model extraction prompt + which token it uses.
CREATE TABLE IF NOT EXISTS public.client_model_prompt (
    model             TEXT PRIMARY KEY,        -- e.g. 'chatgpt-4o'
    token_id          INT NOT NULL REFERENCES public.client_token(token_id),
    model_identifier  TEXT,                    -- provider's model id; falls back to model
    system_prompt     TEXT NOT NULL,
    max_tokens        INT NOT NULL DEFAULT 4096,
    generation_params JSONB NOT NULL DEFAULT '{}'::jsonb,
    created_at        TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at        TIMESTAMPTZ NOT NULL DEFAULT now()
);
