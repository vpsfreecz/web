const { test, expect } = require('@playwright/test');
const {
  makeRegistrationData,
  openRegistrationForm,
  addHiddenInput,
  fillRegistrationForm,
  submitRegistration,
  submitValidationForm,
} = require('../lib/registration');

async function expectFieldError(page, field, text) {
  await expect(page.locator(`input[name="${field}"], select[name="${field}"]`)).toHaveClass(
    /error/,
  );
  await expect(page.locator('body')).toContainText(text);
}

test.describe.configure({ mode: 'serial' });

test('Czech company form shows required-field validation messages', async ({ page }) => {
  await openRegistrationForm(page, 'cs', 'pravnicka', 'dynamic');
  await submitValidationForm(page);

  await expectFieldError(
    page,
    'login',
    'Přihlašovací jméno: může obsahovat pouze znaky',
  );
  await expectFieldError(page, 'name', 'Celé jméno: musí mít minimálně 5 znaků');
  await expectFieldError(page, 'email', 'E-mail: není platná e-mailová adresa');
  await expectFieldError(page, 'address', 'chybí číslo popisné');
  await expectFieldError(page, 'zip', 'PSČ: musí být vyplněno');
  await expectFieldError(
    page,
    'distribution',
    'Vyber si distribuci: vyber některou z nabízených voleb',
  );
  await expectFieldError(
    page,
    'location',
    'Preferovaná lokace pro VPS: vyber některou z nabízených voleb',
  );
  await expectFieldError(page, 'org_name', 'Název organizace: musí mít minimálně 3 znaky');
  await expectFieldError(page, 'ic', 'IČ: musí mít minimálně 6 znaků');
});

test('English person form shows required-field validation messages', async ({ page }) => {
  await openRegistrationForm(page, 'en', 'fyzicka', 'dynamic');
  await submitValidationForm(page);

  await expectFieldError(page, 'login', 'Login name: can contain only characters');
  await expectFieldError(page, 'name', 'Name: must contain at least 5 characters');
  await expectFieldError(page, 'email', 'E-mail: it is not valid mail address');
  await expectFieldError(page, 'zip', 'ZIP/Postal code: must be filled');
  await expectFieldError(page, 'distribution', 'Choose your distro: choose one option');
  await expectFieldError(page, 'currency', 'Currency for member fees: choose one option');
});

test('Czech person form rejects representative invalid values', async ({ page }) => {
  const data = makeRegistrationData('cs', 'fyzicka');

  await openRegistrationForm(page, 'cs', 'fyzicka', 'static');
  await fillRegistrationForm(page, data);
  await page.locator('input[name="login"]').fill('bad login');
  await page.locator('input[name="name"]').fill('J1');
  await page.locator('input[name="email"]').fill('not-mail');
  await page.locator('input[name="birth"]').fill('2025');
  await page.locator('input[name="address"]').fill('Bez cisla');
  await page.locator('input[name="zip"]').fill('abc');
  await page.locator('select[name="country"]').selectOption('Česko');
  await submitValidationForm(page);

  await expectFieldError(page, 'login', 'Přihlašovací jméno: může obsahovat pouze znaky');
  await expectFieldError(page, 'name', 'Celé jméno: musí mít minimálně 5 znaků');
  await expect(page.locator('body')).toContainText('nemůže obsahovat číslo');
  await expect(page.locator('body')).toContainText('uveď prosím celé jméno a příjmení');
  await expectFieldError(page, 'email', 'E-mail: není platná e-mailová adresa');
  await expectFieldError(page, 'birth', 'Rok narození: nepřijatelný rok narození');
  await expectFieldError(page, 'address', 'Ulice, č.p.: chybí číslo popisné');
  await expectFieldError(page, 'zip', 'PSČ: může obsahovat pouze číslice 0-9');
  await expect(page.locator('body')).toContainText('musí mít přesně 5 číslic');
});

test('Czech person form requires selected country', async ({ page }) => {
  const data = makeRegistrationData('cs', 'fyzicka');

  await openRegistrationForm(page, 'cs', 'fyzicka', 'static');
  await fillRegistrationForm(page, data);
  await page.locator('select[name="country"]').evaluate((select) => {
    const option = document.createElement('option');
    option.value = '';
    option.selected = true;
    select.prepend(option);
  });
  await submitValidationForm(page);

  await expectFieldError(page, 'country', 'Stát: musí mít minimálně 2 znaky');
});

test('English company form rejects representative invalid organization values', async ({
  page,
}) => {
  const data = makeRegistrationData('en', 'pravnicka');

  await openRegistrationForm(page, 'en', 'pravnicka', 'static');
  await fillRegistrationForm(page, data);
  await page.locator('input[name="org_name"]').fill('A');
  await page.locator('input[name="ic"]').fill('12ab56');
  await submitValidationForm(page);

  await expectFieldError(page, 'org_name', 'Company name: must contain at least 3 characters');
  await expectFieldError(page, 'ic', 'ID: can contain only numbers 0-9');
});

test('mocked validation accepts Czech non-ASCII values', async ({ page }) => {
  const data = makeRegistrationData('cs', 'fyzicka');
  data.name = 'Žaneta Přílišná';
  data.city = 'České Budějovice';
  data.country = 'Česko';

  await openRegistrationForm(page, 'cs', 'fyzicka', 'dynamic');
  await fillRegistrationForm(page, data);
  await addHiddenInput(page, '_mock', '1');
  await submitRegistration(page, 'cs');
});

test('mocked validation accepts English postal codes without house number', async ({
  page,
}) => {
  const data = makeRegistrationData('en', 'fyzicka');
  data.address = 'Long Street';
  data.zip = 'SW1A 1AA';

  await openRegistrationForm(page, 'en', 'fyzicka', 'dynamic');
  await fillRegistrationForm(page, data);
  await addHiddenInput(page, '_mock', '1');
  await submitRegistration(page, 'en');
});

test('antispam rejects missing form token', async ({ page }) => {
  const data = makeRegistrationData('cs', 'fyzicka');

  await openRegistrationForm(page, 'cs', 'fyzicka', 'dynamic');
  await fillRegistrationForm(page, data);
  await addHiddenInput(page, '_mock', '1');
  await page.locator('input[name="form_token"]').evaluate((el) => el.remove());
  await submitValidationForm(page);

  await expect(page.locator('body')).toContainText(
    'Přihláška: platnost formuláře nelze ověřit',
  );
});

test('antispam rejects forged form token', async ({ page }) => {
  const data = makeRegistrationData('en', 'fyzicka');

  await openRegistrationForm(page, 'en', 'fyzicka', 'dynamic');
  await fillRegistrationForm(page, data);
  await addHiddenInput(page, '_mock', '1');
  await page.locator('input[name="form_token"]').evaluate((el) => {
    el.value = 'bad-token';
  });
  await submitValidationForm(page);

  await expect(page.locator('body')).toContainText(
    'Registration: the form validity cannot be verified',
  );
});

test('antispam rejects filled honeypot field', async ({ page }) => {
  const data = makeRegistrationData('cs', 'fyzicka');

  await openRegistrationForm(page, 'cs', 'fyzicka', 'dynamic');
  await fillRegistrationForm(page, data);
  await addHiddenInput(page, '_mock', '1');
  await page.locator('input[name="website"]').evaluate((el) => {
    el.value = 'https://spam.example/';
  });
  await submitValidationForm(page);

  await expect(page.locator('body')).toContainText('Přihlášku se nepodařilo uložit');
});

test('antispam rejects immediate real submit', async ({ page }) => {
  const data = makeRegistrationData('en', 'fyzicka');

  await openRegistrationForm(page, 'en', 'fyzicka', 'dynamic');
  await fillRegistrationForm(page, data);
  await Promise.all([
    page.waitForNavigation({ waitUntil: 'domcontentloaded' }),
    page.locator('#send').click(),
  ]);

  await expect(page.locator('body')).toContainText(
    'Registration: the form was submitted too quickly',
  );
});

test('country-aware postal validation accepts and rejects representative values', async ({
  page,
}) => {
  const accepted = makeRegistrationData('en', 'fyzicka');
  accepted.address = '221B Baker Street';
  accepted.city = 'London';
  accepted.zip = 'NW1 6XE';
  accepted.country = 'United Kingdom';
  accepted.region = 'England';

  await openRegistrationForm(page, 'en', 'fyzicka', 'dynamic');
  await fillRegistrationForm(page, accepted);
  await page.locator('input[name="email"]').fill('not-mail');
  await submitValidationForm(page);

  await expectFieldError(page, 'email', 'E-mail: it is not valid mail address');
  await expect(page.locator('input[name="zip"]')).not.toHaveClass(/error/);

  const rejected = makeRegistrationData('en', 'fyzicka');
  rejected.address = 'Plot 2 Kampala Road';
  rejected.city = 'Kampala';
  rejected.zip = '256';
  rejected.country = 'Uganda';

  await openRegistrationForm(page, 'en', 'fyzicka', 'dynamic');
  await fillRegistrationForm(page, rejected);
  await addHiddenInput(page, '_mock', '1');
  await submitValidationForm(page);

  await expectFieldError(page, 'zip', 'ZIP/Postal code: is not a valid postal code format');
});
