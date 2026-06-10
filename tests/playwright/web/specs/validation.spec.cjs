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
  await expectFieldError(page, 'name', 'Celé jméno: musí mít minimálně 2 znaky');
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
  await expectFieldError(page, 'org_name', 'Název organizace: musí mít minimálně 2 znaky');
  await expectFieldError(page, 'ic', 'IČ: musí mít minimálně 6 znaků');
});

test('English person form shows required-field validation messages', async ({ page }) => {
  await openRegistrationForm(page, 'en', 'fyzicka', 'dynamic');
  await submitValidationForm(page);

  await expectFieldError(page, 'login', 'Login name: can contain only characters');
  await expectFieldError(page, 'name', 'Name: must contain at least 2 characters');
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
  await page.locator('input[name="country"]').fill('CZ1');
  await submitValidationForm(page);

  await expectFieldError(page, 'login', 'Přihlašovací jméno: může obsahovat pouze znaky');
  await expectFieldError(page, 'name', 'Celé jméno: nemůže obsahovat číslo');
  await expectFieldError(page, 'email', 'E-mail: není platná e-mailová adresa');
  await expectFieldError(page, 'birth', 'Rok narození: nepřijatelný rok narození');
  await expectFieldError(page, 'address', 'Ulice, č.p.: chybí číslo popisné');
  await expectFieldError(page, 'zip', 'PSČ: může obsahovat pouze');
  await expectFieldError(page, 'country', 'Stát: nemůže obsahovat číslo');
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

  await expectFieldError(page, 'org_name', 'Company name: must contain at least 2 characters');
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
