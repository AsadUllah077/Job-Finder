# Approved job integrations

Documentation reviewed on 2026-10-19. Public API availability does not guarantee coverage in a particular country or freedom from future terms changes. Use each source's original link and name. The app does not crawl HTML search results, bypass access controls, or submit applications.

| Source | Setup | Implemented request | Minimum interval | Live verification in this workspace |
|---|---|---|---|---|
| Arbeitnow | Enabled by default, no key | `GET https://www.arbeitnow.com/api/job-board-api?page=N` | 1 hour | Initial restricted-network request failed; not verified |
| Remotive | Enabled by default, no key | `GET https://remotive.com/api/remote-jobs?category=software-dev` | 6 hours | Successful; three PHP/Laravel matches persisted to MariaDB |
| Remote OK | Enable in admin; no key | `GET https://remoteok.com/api` | 6 hours | Adapter tested with mocked payloads only |
| Greenhouse | Public board slug and optional company name | `GET https://boards-api.greenhouse.io/v1/boards/{board}/jobs?content=true` | 1 hour | Requires a selected board; mocked tests only |
| Lever | Public board slug, company name, global/EU region | `GET https://api.lever.co/v0/postings/{board}?mode=json&skip=N&limit=100` (or EU host) | 1 hour | Requires a selected board; mocked tests only |
| Adzuna | App ID and key in environment; country/currency in admin | `GET https://api.adzuna.com/v1/api/jobs/{country}/search/{page}` | 1 hour | Requires credentials; mocked tests only |

No API secrets are stored in `job_sources.configuration`. Source status says “Ready · not verified” until a request succeeds, “Setup required” when credentials/board settings are absent, and “Needs attention” after failures. Disabled sources are explicitly labeled. Successful requests record a completion time and counts. A success with zero keyword matches is still an actual request, not invented listings.

## Provider instructions and terms

- **Arbeitnow:** [official API announcement](https://www.arbeitnow.com/blog/job-board-api) links to its [Postman documentation](https://documenter.getpostman.com/view/18545278/UVJbJdKh). Feed coverage is primarily Europe. Pagination is constructed locally on the fixed host; returned `next` URLs are never fetched directly. Run `php artisan jobs:sync --source=arbeitnow` and process the queue. The default cap is three pages per run; admins can use 1–10 pages.
- **Remotive:** [official API documentation and terms](https://github.com/remotive-com/remote-jobs-api). Public listings remain accessible without signup. Links point back to Remotive with attribution. Source publication dates are delayed by 24 hours. The six-hour cooldown follows the recommendation of no more than four requests daily. Never redistribute these jobs to prohibited third-party aggregators or add signup walls to listing pages.
- **Remote OK:** the [feed's first object contains its current attribution terms](https://remoteok.com/api). The adapter skips that metadata object. The site links back with the source name, without `nofollow`, and does not use the source's logo. Salary fields in this feed use USD annually. Enable only after reviewing the current terms.
- **Greenhouse:** [official Job Board API documentation](https://docs.greenhouse.io/job-board.html). Supply the token from the public employer board URL (for example `job-boards.greenhouse.io/{token}`). Only letters, digits, underscores, and hyphens are accepted. No arbitrary URL is accepted. `updated_at` is deliberately not treated as a publication date. This version configures one board per integration.
- **Lever:** [official Postings API documentation](https://github.com/lever/postings-api). Supply the company slug in its public `jobs.lever.co/{slug}` URL. EU boards use the fixed `api.eu.lever.co` host. List pagination is capped at the configured maximum. Requirements lists, workplace type, country, and salary interval are normalized when supplied. This version configures one board per integration.
- **Adzuna:** register an application at the [developer portal](https://developer.adzuna.com/) and obtain your own ID/key. Set `ADZUNA_APP_ID` and `ADZUNA_APP_KEY`; rebuild config cache and restart workers. Review [search documentation](https://developer.adzuna.com/docs/search) and account-specific quota/terms. Select an actual supported endpoint country. **UAE and Pakistan are not available country endpoints in this adapter.** The initial search terms are Laravel and PHP; each term is paginated separately. Predicted salaries are omitted, and descriptions may be source-provided snippets. Only set the endpoint salary currency when confirmed from the provider's documentation.

## Adding a provider

Implement `App\Services\Sources\JobSourceInterface`, register the adapter in `SourceRegistry::ADAPTERS`, add reviewed source metadata in the seeder, and add HTTP-mocked tests. Construct requests against a fixed approved HTTPS host; reject redirects; never fetch administrator- or user-provided URLs. Add provider-specific timeout, quota, pagination, identity namespace, attribution, and permission rules. Normalize into the common fields, preserve the original listing URL, and distinguish publication from update/discovery dates.

The adapters stream pages to `SyncSource`. Job normalization skips rows missing IDs, titles, companies, or safe HTTP(S) links. Provider failures are recorded without response bodies, request URLs containing credentials, or raw exception details. A partially processed failure may have persisted valid rows; counts show that explicitly. Unseen jobs are retained because a limited page window does not establish removal.

Default collection keeps listings that mention **Laravel or PHP** in title/description. Administrators can broaden this keyword list. All locations are retained; users choose locations in search/preferences. Direct external search links are supplied for LinkedIn, Rozee, and Bayt because this app does not have approved ingestion integrations for them.
