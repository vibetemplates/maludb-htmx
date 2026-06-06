# SalesCoach Pro — Build Prompts for Claude Code

## Overview

This directory contains a series of autonomous build prompts to convert the HTMX/PHP/Bootstrap template into the SalesCoach Pro AI sales coaching application. Each prompt is designed to be executed independently in Claude Code, in order.

## Execution Instructions

1. Open the project in Claude Code: `cd /var/www && claude`
2. Copy-paste each prompt file's content into Claude Code in sequence
3. Wait for each phase to complete and verify before moving to the next
4. Commit after each phase: `git add -A && git commit -m "Phase X: description"`

## Prompt Sequence

| # | File | Phase | Description |
|---|------|-------|-------------|
| 1 | `01-schema-migration.md` | Foundation | Database schema changes — add columns, remove unused tables, create migration SQL |
| 2 | `02-auth-and-roles.md` | Foundation | Adapt auth system for sales-manager/sales-rep roles, org isolation |
| 3 | `03-navigation-and-layout.md` | Foundation | Sidebar, navbar, dashboard shells, role-based menu |
| 4 | `04-prospect-management.md` | Content | Prospect CRUD with personality config and sub-agents |
| 5 | `05-product-management.md` | Content | Product CRUD with prompt/knowledgebase fields |
| 6 | `06-scoring-and-context.md` | Content | Scoring categories, rubrics, business context CRUD |
| 7 | `07-agent-configuration.md` | Agents | Retell AI agent management, model prompts, settings |
| 8 | `08-session-setup-and-call.md` | Sessions | Practice session wizard, Retell Web SDK integration |
| 9 | `09-session-scoring-review.md` | Sessions | AI scoring, scorecard display, session detail view |
| 10 | `10-session-history.md` | Sessions | Session history list with filtering, rep and manager views |
| 11 | `11-dashboards-and-analytics.md` | Analytics | Rep and manager dashboards with charts |
| 12 | `12-leaderboard-notifications.md` | Polish | Leaderboard, notification system, final polish |

## Key References

- `requirements.md` — Full application requirements
- `tech-stack.md` — Technology stack details
- `api_manager__1_.sql` — Current database schema
- The existing codebase in `/var/www/html/` (web root) and `/var/www/` (secure root)
