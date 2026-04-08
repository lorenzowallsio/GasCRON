# RSS Cron Feed Publisher

This project fetches an upstream RSS feed once per day, keeps the latest 5 items by `pubDate`, replaces each selected item's `title` with its `description`, rewrites both `author` and `dc:creator` to the original `title`, overrides the channel title to `Gasconnect RSS`, and publishes the transformed RSS output for Walls.io.

## Requirements

- PHP 8.2 or newer
- PHP extensions: `curl`, `dom`, `libxml`
- A writable output path that is served over HTTPS by your backend

## Configuration

Copy `.env.example` to `.env` and update the values:

- `RSS_SOURCE_URL`: upstream Pressrelations feed URL
- `RSS_OUTPUT_PATH`: absolute filesystem path to the published XML file
- `RSS_PUBLIC_FEED_URL`: public HTTPS URL for the published feed
- `RSS_CHANNEL_TITLE_OVERRIDE`: feed-level channel title override, defaults to `Gasconnect RSS`
- `RSS_TIMEZONE`: execution timezone, defaults to `Europe/Rome`
- `RSS_CRON_SCHEDULE`: documentation-only cron expression, defaults to `0 8,13 * * *`
- `RSS_FETCH_TIMEOUT_SECONDS`: HTTP timeout, defaults to `15`
- `RSS_SKIP_ITEMS_WITH_EMPTY_TITLE`: `true` to skip items whose transformed title would be blank because `description` is empty or missing
- `RSS_REQUEST_HEADERS_JSON`: optional JSON object of request headers
- `LOG_LEVEL`: `debug`, `info`, `warning`, or `error`

## Usage

Manual run:

```bash
php bin/generate-transformed-rss.php
```

Cron example:

```cron
CRON_TZ=Europe/Rome
0 8,13 * * * php /path/to/bin/generate-transformed-rss.php >> /var/log/rss-transform.log 2>&1
```

## Testing

Install dependencies and run PHPUnit:

```bash
composer install
composer test
```

## GitHub Pages Deployment

This project includes a GitHub Actions workflow at `.github/workflows/publish-rss-to-pages.yml` that can:

- run twice per day at `08:00` and `13:00 Europe/Rome`
- run manually with `workflow_dispatch`
- publish the generated RSS feed to GitHub Pages

Default GitHub Pages feed URL patterns:

- project repository: `https://<owner>.github.io/<repo>/feeds/feed.xml`
- user or organization site repository named `<owner>.github.io`: `https://<owner>.github.io/feeds/feed.xml`

Optional GitHub repository variables:

- `RSS_SOURCE_URL`
- `RSS_PUBLIC_FEED_URL`
- `RSS_CHANNEL_TITLE_OVERRIDE`
- `RSS_FETCH_TIMEOUT_SECONDS`
- `RSS_SKIP_ITEMS_WITH_EMPTY_TITLE`
- `RSS_REQUEST_HEADERS_JSON`
- `LOG_LEVEL`

To use the workflow:

1. Push this project to a GitHub repository.
2. If you are using GitHub Free, make the repository public.
3. In GitHub, enable Pages and set the source to `GitHub Actions`.
4. Run the workflow once manually to publish the first feed.
5. Add the resulting `.../feeds/feed.xml` URL to Walls.io.

## Notes

- The placeholder public feed URL is `https://example.com/feeds/feed.xml`.
- The job preserves the previous published file if fetching, parsing, validation, or publishing fails.
- Walls.io usually shows RSS items newer than 24 hours unless moderation is used.
