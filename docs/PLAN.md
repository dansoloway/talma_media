# Drive Media Organizer — Build Plan

Nov 2, 2026 · @Daniel

## Locked defaults (bootstrap)

- UI: Livewire + Tailwind (not Inertia/Vue)
- Tags: English + Hebrew labels in taxonomy from day one
- Access: all authenticated `@talmaisrael.com` staff see all media
- Drive write-back: off in v1 (Viewer service account only)
- Review queue: any logged-in staff can confirm/reject suggestions

## Overview

The app watches one Google Drive folder (and its subfolders), keeps an index of every photo and video in it, auto-tags new photos with AI, and gives TALMA staff a searchable gallery. Originals stay in Drive; the database stores a pointer (Drive file ID + link), a small thumbnail, and the tags.

Decisions so far:

- **Users:** TALMA staff, signing in with their @talmaisrael.com Google account.
- **Media:** index photos and videos from day one; AI-tag photos only in v1, videos in a later phase.
- **People tags:** roles and groups only (students, teachers, adults, group size). No face recognition or naming individuals.
- **Tags:** AI suggests, people can confirm, reject or add. Manual tags always win over AI.
- **Taxonomy is data, not code:** new tag categories can be added later without changing the database structure.

## Recommended stack

Use Laravel, hosted on Laravel Cloud. You know the framework, so you can read and fix what Cursor writes. Cloud also provides every piece this app needs: Postgres with pgvector, file storage, managed queues and a scheduler.

| Piece | Choice | Why |
| --- | --- | --- |
| Framework | Laravel (latest), PHP 8 | Your strongest stack; Cursor handles it well |
| UI | Livewire starter kit + Tailwind (Inertia + Vue if you prefer a JS front end) | Stay in PHP and Blade; filters and infinite scroll are simple Livewire components |
| Hosting | Laravel Cloud, EU region nearest Israel | Deploys on push to GitHub; restarts queue workers on every deploy |
| Database | Laravel Serverless Postgres (runs on Neon) + pgvector | Real SQL for tag filters; pgvector supported for natural-language search later; can scale to zero when idle |
| Login | Socialite, Google provider, talmaisrael.com only | Check the email domain on the server; Google's `hd` hint alone does not enforce it |
| Thumbnails | Laravel Cloud bucket (S3-compatible), private | The app's disk is wiped on every deploy, so files must live in a bucket; use the `Storage` facade |
| Drive access | `google/apiclient` with a service account | Runs unattended; the folder is shared with it as Viewer |
| AI tagging | Claude API (vision) via Prism, or Laravel's HTTP client | Returns tags in your taxonomy as JSON |
| Background jobs | Laravel Cloud managed queue + Scheduler toggle | Workers scale with the backlog, which suits the one-time backfill |
| Image resizing | Intervention Image | Makes the 400 px thumbnails |

If the app runs more than one replica, schedule the Drive sync with `onOneServer()` so it runs once per cycle. Sources: [Laravel Cloud deploy guide](https://laravel.com/cloud/docs/deploy-guides/laravel), [Laravel Serverless Postgres](https://laravel.com/cloud/docs/resources/databases/postgres).

## Architecture

&#91;embedded content: system architecture · 7 parts\]

Originals never leave Drive. The sync job finds new or changed files, the worker tags them, and the app reads everything from one Postgres database, with an "Open in Drive" link on every photo.

## Data model

Tags live in their own tables, so adding a new kind of tag later (program, season, photo quality) is a new row, not a code change.

| Table | Holds | Key columns |
| --- | --- | --- |
| `media` | One row per Drive file | `drive_file_id` (unique), `name`, `mime_type`, `folder_id`, `web_view_link`, `thumb_path`, `md5`, `width`, `height`, `taken_at`, `taken_at_source`, `lat`, `lng`, `status`, `caption`, `ai_raw` (JSON), `prompt_version`, `embedding` |
| `folders` | Drive folder tree | `drive_folder_id`, `name`, `path`, `parent_id`, hints parsed from the name (`year`, `program`, `place`) |
| `tag_categories` | Kinds of tag | `key` (location, people, activity, setting, program, event), `label_en`, `label_he`, `multi`, `ai_enabled` |
| `tags` | Allowed values | `category_id`, `value`, `label_en`, `label_he`, `synonyms` |
| `media_tags` | Tag on a photo | `media_id`, `tag_id`, `source` (ai, manual, metadata, folder), `confidence`, `status` (suggested, confirmed, rejected), `by_user`, `created_at` |
| `places` | Known TALMA sites | `name`, `lat`, `lng`, `radius_m`, so GPS points become "Tel Aviv office" instead of raw coordinates |
| `tag_runs` | Log of each AI call (the queue itself is Laravel's) | `media_id`, `model`, `prompt_version`, `status`, `error`, `input_tokens`, `output_tokens` |
| `sync_state` | Drive watcher bookmark | `page_token`, `last_run_at` |

Rules worth writing into the code from day one:

- A re-run of AI tagging never touches tags with `source = manual` or `status = rejected`.
- `md5` flags duplicates (WhatsApp exports often repeat the same photo).
- Files removed from Drive are marked `trashed` and their thumbnail deleted, not left behind.

## AI tagging design

Each photo gets tags from three sources, cheapest first: Drive metadata, the folder it sits in, then the AI model. The AI is told what the first two already found, so it fills gaps instead of guessing.

**Date, in order of trust**

1. Camera date from Drive's `imageMediaMetadata.time`
2. Date in the filename (WhatsApp files look like `IMG-20230514-WA0001`)
3. Year in the folder name ("2026 Summer SF")
4. Drive upload date, marked low confidence

**Location, in order of trust**

1. GPS from photo metadata, matched to the `places` table, else reverse-geocoded to a city
2. Place in the folder name ("NYC", "SF")
3. Manual tag. The AI only tags the type of setting (classroom, auditorium, outdoors), not the city.

WhatsApp exports strip camera date and GPS, so folder names will carry a lot of weight. Clear folder naming going forward helps more than any model.

**What the AI returns** (one JSON object per photo, values limited to the taxonomy)

- `caption`: one or two sentences describing the photo
- `people`: students, teachers, adults, children, group size (1, 2–5, 6–20, 20+)
- `setting`: indoor or outdoor, plus place type
- `activity`: lesson, ceremony, workshop, trip, performance, group photo, meeting
- `visible_text`: words on banners or signs, often the event name
- `quality`: sharp or blurry, usable for marketing yes or no
- `confidence` per tag, and `new_tag_suggestions` for values outside the list, sent to a review queue

**How it runs**

- Send Drive's JPEG thumbnail at about 1,600 px instead of the original. It handles HEIC files and keeps cost down.
- Save the raw AI answer and a `prompt_version`, so you can re-tag later when you add categories.
- Tags under a confidence threshold (start at 0.7) are stored as suggestions, not shown as fact.
- Telling students from teachers by appearance is unreliable; expect staff to correct this tag most.

**Cost:** with a small, fast vision model, expect a few dollars per thousand photos. Confirm current pricing and tag a 200-photo test set before the full backfill. Use the Batch API for the backfill, which is cheaper and fine for work that can wait hours.

## Front end features

**v1**

- Google sign-in, talmaisrael.com accounts only
- Gallery grid with infinite scroll, newest first
- Filter sidebar: date range, location, folder or program, people, activity, setting, media type, tag status (AI-suggested vs confirmed)
- Keyword search across captions, filenames and tags
- Photo detail panel: large preview, all tags with source and confidence, "Open in Drive" and "Download original" buttons
- Tag editing: confirm, reject or add a tag in one click; changes record who made them
- Multi-select bulk tagging (select 40 photos from one event, tag them all "Graduation 2026")
- Review queue: low-confidence tags and AI-suggested new tag values waiting for a human
- Admin page: manage tag categories and values (English + Hebrew labels), known places, sync status and job errors

**Later**

- Natural-language search ("teachers outdoors at a summer program") using embeddings of the captions
- Video tagging from keyframes, then audio transcripts
- Saved searches and shareable collections, e.g. a set of photos for a funder report
- Write a short summary back into each file's Drive description, so Drive's own search finds them too
- Hebrew interface

## Build phases with Cursor

Build against a test folder of about 50 mixed photos first, and point at the real archive only in phase 5.

**Setting up Cursor**

- Export this doc as Markdown and save it in the repo as `docs/PLAN.md`.
- Add a project rule in `.cursor/rules/` stating: Laravel conventions, PHP 8 strict types, Livewire components for UI, Eloquent models and migrations for every DB change, queued jobs for anything slow, Pest tests, secrets only in `.env`.
- Use Agent mode, one chat per phase. Start each chat with "Read docs/PLAN.md, then…". Commit to Git after every working step.

**Phases**

1. **Setup (you, not Cursor)**
   - Google Cloud project: enable the Drive API, create a service account, share the test folder with its email as Viewer
   - Google OAuth client for staff login through Socialite
   - `laravel new` with the Livewire starter kit, pushed to GitHub
   - Laravel Cloud: create the app from the repo, attach Serverless Postgres, a private bucket and a managed queue, turn on the Scheduler
   - Add the service account key (base64) and the Claude API key as Cloud environment variables
2. **Inventory, no AI**
   - Prompt: "Create migrations and Eloquent models for the data model in PLAN.md. Then write an Artisan command `media:sync-full` that walks the Drive folder recursively, upserts every file into `media` and `folders`, dispatches a job that saves a 400 px thumbnail to the bucket, and fills date and GPS from Drive metadata."
   - Done when: every test photo is in the table with a thumbnail.
3. **AI tagging**
   - Prompt: "Build a queued `TagMedia` job: send the Drive thumbnail plus folder and date context to the Claude API, validate the JSON against the taxonomy, write `media_tags` with source ai and confidence, and log the call in `tag_runs`. Never overwrite manual tags. Add rate limiting and retries with backoff."
   - Done when: you read 50 photos' tags and agree with most of them. Tune the prompt here.
4. **Front end**
   - Prompt: "Build the gallery, filter sidebar, detail panel and tag editing from PLAN.md v1 as Livewire components, behind Socialite Google login limited to talmaisrael.com."
   - Done when: two colleagues can log in, find photos and fix tags.
5. **Watching for changes**
   - Prompt: "Add an Artisan command `media:sync-changes` that calls Drive `changes.list` from the saved page token, handles added, renamed, moved and trashed files, and dispatches `TagMedia` for new photos. Schedule it every 10 minutes with `withoutOverlapping()` and `onOneServer()`."
   - Done when: a photo dropped into the folder is tagged and searchable within 15 minutes.
6. **Backfill the archive**
   - Run `media:sync-full` on the real folder, check the file count, then tag in Laravel job batches with a daily cost cap.
7. **Later features** from the front end list, one per chat.

Polling every 10 minutes with the scheduler is simpler and more reliable than Drive push notifications, which expire and need a public webhook. Switch only if near-instant tagging matters.

## Privacy and security

Many photos show students, so treat thumbnails and tags as sensitive data from the start.

- **No identification of individuals.** The prompt tells the model to describe roles and groups only, never names or identity.
- **Thumbnails stay private.** Private bucket; images served through short-lived temporary URLs or a controller that checks login. No public links.
- **Login on every route.** All routes behind `auth` middleware; policies limit the admin pages (taxonomy, places, sync status) to admins.
- **Least privilege in Drive.** The service account is Viewer on one folder. Upgrade to Editor only if you turn on writing descriptions back to Drive.
- **AI provider terms.** Confirm TALMA's AI-use policy allows sending student photos to an AI API, and use an account where inputs are not used for training.
- **Secrets** live in Laravel Cloud environment variables, never in the repo. The service account key never reaches the browser.
- **Deletion follows Drive.** When a photo is deleted in Drive, its thumbnail and tags are removed too.

## Open questions for Daniel

- [ ] Is the folder to watch the existing photo archive (with its program/event subfolders), or a new one?
- [ ] Is it in a Shared Drive or someone's My Drive? A Shared Drive is easier for a service account.
- [ ] Roughly how many files are there now, and how many arrive per month?
- [ ] Should every staff member see every photo, or should some folders be limited to certain people?
- [ ] Tag values in English, Hebrew, or both?
- [ ] Starting tag list: which programs, events and TALMA sites should be in the taxonomy on day one?
- [ ] Who reviews AI suggestions: you, a comms person, or anyone who uses the app?
- [ ] Do you want tags written back into Drive file descriptions?
