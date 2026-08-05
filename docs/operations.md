# Operations

## Runtime

- Command: `php bin/generate-transformed-rss.php`
- Exit code `0` on success
- Non-zero exit code on fetch, parse, validation, or publish failure

## Required Environment

- `RSS_SOURCE_URL`
- `RSS_OUTPUT_PATH`
- `RSS_PUBLIC_FEED_URL`
- `RSS_CHANNEL_TITLE_OVERRIDE`

Example placeholder public URL:

```text
https://example.com/feeds/feed.xml
```

## Cron

Use timezone-aware cron entries so the job runs at `07:37` and `13:11 Europe/Rome`:

```cron
CRON_TZ=Europe/Rome
37 7 * * * php /path/to/bin/generate-transformed-rss.php >> /var/log/rss-transform.log 2>&1
11 13 * * * php /path/to/bin/generate-transformed-rss.php >> /var/log/rss-transform.log 2>&1
```

## GitHub Actions + GitHub Pages

This repository also includes `.github/workflows/publish-rss-to-pages.yml` for a fully hosted GitHub setup.

What it does:

- runs on manual dispatch
- runs on pushes to `main` or `master`
- runs twice daily at `07:37` and `13:11 Europe/Rome`
- generates the RSS file into `build/pages/feeds/feed.xml`
- deploys that directory to GitHub Pages
- keeps its own schedule alive (see below)

## Scheduled Workflow Keepalive

GitHub disables `schedule` triggers in public repositories after 60 days without
repository activity. Scheduled runs do not count as activity, so a cron-only
repository silently switches itself off. This happened on 2026-07-31: the
workflow was disabled with state `disabled_inactivity`, the feed froze at the
last successful run, and GitHub Pages kept serving that stale file with
`HTTP 200` and no error signal.

The `Keep scheduled workflow alive` step prevents a repeat. On scheduled runs it
checks the age of the newest commit on the default branch and, once that exceeds
`KEEPALIVE_MAX_COMMIT_AGE_DAYS` (14), writes a UTC timestamp to
`.github/keepalive` and pushes it. That push counts as repository activity and
resets GitHub's 60-day clock, leaving roughly a 4x safety margin.

Notes:

- The `build` job needs `permissions: contents: write` for that push.
- Commits pushed with the default `GITHUB_TOKEN` do not trigger new workflow
  runs, so the keepalive cannot loop back into this workflow. The commit message
  also carries `[skip ci]`.
- To verify the push path without waiting 14 days, dispatch the workflow
  manually with `force_keepalive` set to `true`.

If the workflow is ever disabled again, re-enable it under `Actions` -> the
workflow -> `Enable workflow`, or with `gh workflow enable`, then dispatch one
run to refresh the published feed. Watch for GitHub's
"will be disabled soon" email; treat it as a prompt to act, not a countdown.

Default GitHub Pages URL behavior:

- if the repository name is not `<owner>.github.io`, the feed URL becomes `https://<owner>.github.io/<repo>/feeds/feed.xml`
- if the repository name is `<owner>.github.io`, the feed URL becomes `https://<owner>.github.io/feeds/feed.xml`

Optional repository variables:

- `RSS_SOURCE_URL`
- `RSS_PUBLIC_FEED_URL`
- `RSS_CHANNEL_TITLE_OVERRIDE`
- `RSS_FETCH_TIMEOUT_SECONDS`
- `RSS_SKIP_ITEMS_WITH_EMPTY_TITLE`
- `RSS_REQUEST_HEADERS_JSON`
- `LOG_LEVEL`

Setup steps in GitHub:

1. Push the project to GitHub.
2. Open `Settings` -> `Pages`.
3. Set `Build and deployment` -> `Source` to `GitHub Actions`.
4. If needed, add repository variables under `Settings` -> `Secrets and variables` -> `Actions`.
5. Run the `Publish RSS Feed to GitHub Pages` workflow manually once.
6. Use the deployed `.../feeds/feed.xml` URL in Walls.io.

## Publishing Behavior

- The job writes a temporary file in the target directory.
- The temporary file is validated before publish.
- The temporary file replaces the live file with `rename()`, so the published path is never half-written.
- If the run fails, the previous published file remains in place.

## Walls.io

Configure the generated HTTPS URL as an RSS source in Walls.io. The feed overrides the channel title to `Gasconnect RSS`, replaces each item `title` with the item `description`, keeps `description`, `link`, `pubDate`, and optional metadata, removes item `author`, and rewrites each published `dc:creator` to the original item `title`.
