# Source Trail challenge

- Title: Source Trail | Category: Web Exploitation (`web-exploitation`) | Difficulty: Easy (stored as the existing `beginner` level, labelled "Beginner") | Points: 30
- Flag: `p4dCTF{every_file_tells_a_story}` (fragments: `p4dCTF{every` in HTML comment, `_file_tells` in CSS comment, `_a_story}` in JS comment)
- Target URL: internal route `GET /challenges/source-trail/target` (e.g. `http://localhost:8080/challenges/source-trail/target`), assets in `public/challenges/source-trail/`.

## Root cause / why this was needed
The base platform had no challenge content, flag submission or challenge admin. The `challenges` table lacked description, target, active flag, canonical flag and hints columns.

## Changed / added files
- Model: `app/Models/Challenge.php` (fillable, casts incl. `encrypted` flag, `$hidden = ['flag']`, `isLive()`, `resolveTargetUrl()`, `hintList()`)
- Service: `app/Services/FlagSubmissionService.php` (reuses `challenge_submissions` + `verified_solves`; constant-time compare; one solve per user and one team award per challenge via existing unique indexes; `team_id_at_solve` keeps existing team scoring)
- Controllers: `ChallengeController`, `ChallengeTargetController`, `Admin/AdminChallengeController` (admin-only via `role:admin`; delete refused when solves exist), `DashboardController`
- Views: `challenges/index|show`, `challenges/targets/source-trail`, `admin/challenges/*`, `layouts/app` (nav)
- Target assets: `public/challenges/source-trail/style.css`, `script.js`
- `routes/web.php`, `public/css/app.css`, `database/seeders/SourceTrailChallengeSeeder.php` (firstOrCreate by slug; never touches users) and `DatabaseSeeder`
- Migrations (additive, non-destructive): `2026_10_13_000000_add_challenge_configuration.php`, `2026_10_14_000000_widen_challenge_flag_column.php` (encrypted payload needs `text`)
- Test: `scripts/source-trail-check.php`

## Hosting
```powershell
docker compose up --build -d
docker compose exec app php artisan migrate --force
docker compose exec app php artisan db:seed --class=SourceTrailChallengeSeeder --force
```
Set `SOURCE_TRAIL_FLAG` in `.env` to rotate the seeded flag before first seeding. Requires a valid `APP_KEY` (flag is encrypted at rest). Use `https` `APP_URL` in production.
