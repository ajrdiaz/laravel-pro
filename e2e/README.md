# Browser tests

End-to-end tests with [Playwright](https://playwright.dev). They drive the real app in a
real browser: they type in the notes, answer the stack wizard, tick project milestones,
download the Anki file. The Pest tests cover the PHP side; these cover what only a
browser can show (autosave, scrolling, `v-model`, downloads, phone-sized screens).

## Running them

```bash
npm run e2e          # builds the frontend, then runs everything
npm run e2e:run      # runs without building (use it when the build is fresh)
npm run e2e:ui       # Playwright's interactive runner
npm run e2e:report   # opens the HTML report of the last run
npm run e2e:types    # type-checks these files

npx playwright test e2e/notes.spec.ts            # one file
npx playwright test -g "wrong password"          # tests whose title matches
```

The tests run against the **built** frontend (`public/build`), so rebuild after changing
a Vue file: `npm run e2e` does it for you.

## Browser

If Playwright's own Chromium is installed (`npx playwright install chromium`) it is used.
Otherwise the Chrome installed on the machine is used, with no download. Force one with
`PW_CHANNEL=chrome` or `PW_CHANNEL=msedge`.

## How it stays out of your way

- **Own database.** The app runs against `database/e2e.sqlite`, rebuilt from scratch
  (`migrate:fresh --seed`) before every run. Your development database is never touched.
- **Own server.** PHP's built-in server on port `8010` (`E2E_PORT` changes it), started and
  stopped by Playwright. A server that is already listening there is not reused.
- **One user per test.** `e2e/support/create-user.php` creates a verified user and the
  `test` fixture signs it in, so tests never see each other's notes or progress. Use
  `guest` from `e2e/fixtures.ts` for what a visitor sees.
- **No service worker, except in one file.** The app's service worker keeps lessons for
  offline reading, which would only get in the way of other tests, so Playwright blocks it
  (`serviceWorkers: 'block'`). `e2e/pwa.spec.ts` allows it and tests it, going offline with
  `context.setOffline(true)`.
- **No Vite dev server.** If `public/hot` exists (`npm run dev` is running) the run stops
  with a message, because the app would load its assets from that server.
- **Quiet.** Mail goes to the log, the queue is synchronous, the cache is in memory and
  the application log is discarded. `E2E_SERVER_LOG=1` sends the server's output to the
  terminal, which helps when a test fails with a 500.

## Writing a test

```ts
import { expect, test } from './fixtures';

test('a signed-in user can do the thing', async ({ page }) => {
    await page.goto('/somewhere');
    await page.getByRole('button', { name: 'Do it' }).click();
    await expect(page.getByText('Done')).toBeVisible();
});
```

- Find things the way a person would: `getByRole`, `getByLabel`, `getByText`. The app marks
  a few elements with `data-test`, which `getByTestId` reads.
- Wait with `expect(...)` (it retries), never with fixed sleeps.
- A checkbox that changes after the server answers needs `click()` and then
  `expect(...).toBeChecked()`: `check()` expects the change to be immediate.
- `getByRole(..., { name: 'Lo sabía' })` also matches "No lo sabía": add `exact: true`.
