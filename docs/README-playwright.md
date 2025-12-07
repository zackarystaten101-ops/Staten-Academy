Run Playwright tests locally

1. Install node (if not installed): https://nodejs.org/
2. From project root run:

   npm install

3. Install browsers (Playwright):

   npx playwright install

4. Run tests:

   npm run test:playwright

Notes:
- The test expects the demo page to be available at: `http://localhost/Staten Accademy Webpage/demo-menu.html`.
- If your local server uses a different path or port, update `tests/menu.spec.ts` accordingly.
