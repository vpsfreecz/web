const { test } = require('@playwright/test');
const { locales, preparePage, expectHealthyPage, urlFor } = require('../lib/site');

for (const [locale, config] of Object.entries(locales)) {
  for (const route of config.routes) {
    test(`${locale} public page ${route} opens`, async ({ page }) => {
      const state = await preparePage(page);
      await expectHealthyPage(page, state, urlFor(locale, route));
    });
  }
}
