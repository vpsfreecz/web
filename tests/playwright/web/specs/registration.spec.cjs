const fs = require('fs');
const path = require('path');
const { test, expect } = require('@playwright/test');
const { preparePage, expectHealthyPage } = require('../lib/site');
const {
  registration,
  registrationUrl,
  makeRegistrationData,
  openRegistrationForm,
  expectRegistrationFormShape,
  fillRegistrationForm,
  submitRegistration,
} = require('../lib/registration');

const submitted = [];

test.describe.configure({ mode: 'serial' });

for (const locale of Object.keys(registration)) {
  for (const entity of ['fyzicka', 'pravnicka']) {
    test(`${locale} dynamic ${entity} form loads`, async ({ page }) => {
      await openRegistrationForm(page, locale, entity, 'dynamic');
      await expectRegistrationFormShape(page, locale, entity, 'dynamic');

      const timeZone = page.locator('select[name="time_zone"]');
      await expect(timeZone).not.toHaveValue('');
    });

    test(`${locale} static ${entity} form loads`, async ({ page }) => {
      const state = await preparePage(page);
      await expectHealthyPage(page, state, registrationUrl(locale, 'static', entity));
      await expectRegistrationFormShape(page, locale, entity, 'static');
    });
  }
}

const submissionCases = [
  { locale: 'cs', entity: 'fyzicka', mode: 'dynamic' },
  { locale: 'cs', entity: 'pravnicka', mode: 'static' },
  { locale: 'en', entity: 'fyzicka', mode: 'dynamic' },
  { locale: 'en', entity: 'pravnicka', mode: 'static' },
];

for (const item of submissionCases) {
  test(`${item.locale} ${item.entity} ${item.mode} form submits to vpsAdmin`, async ({
    page,
  }) => {
    const data = makeRegistrationData(item.locale, item.entity);

    await openRegistrationForm(page, item.locale, item.entity, item.mode);
    await fillRegistrationForm(page, data);

    const id = await submitRegistration(page, item.locale);
    submitted.push({
      id,
      mode: item.mode,
      locale: data.locale,
      entity: data.entity,
      login: data.login,
      name: data.name,
      email: data.email,
      address: data.address,
      city: data.city,
      country: data.country,
      orgName: data.orgName,
      orgId: data.orgId,
      timeZone: data.timeZone,
    });
  });
}

test.afterAll(async () => {
  const target = process.env.WEB_REGISTRATION_RESULTS;

  if (!target) {
    return;
  }

  fs.mkdirSync(path.dirname(target), { recursive: true });
  fs.writeFileSync(target, `${JSON.stringify(submitted, null, 2)}\n`);
});
