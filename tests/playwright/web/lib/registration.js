const { expect } = require('@playwright/test');
const { urlFor } = require('./site');

const registration = {
  cs: {
    dynamicPath: '/prihlaska/',
    staticBase: '/prihlaska',
    acceptedPath: '/prihlaska/prijata/',
    actionPath: '/prihlaska/send.php',
    submitText: 'Odeslat',
  },
  en: {
    dynamicPath: '/registration/',
    staticBase: '/registration',
    acceptedPath: '/registration/accepted/',
    actionPath: '/registration/send.php',
    submitText: 'Send',
  },
};

const entityPaths = {
  fyzicka: 'fyzicka-osoba',
  pravnicka: 'pravnicka-osoba',
};

let uniqueCounter = 0;

function registrationUrl(locale, mode, entity = null) {
  const config = registration[locale];

  if (mode === 'dynamic') {
    return urlFor(locale, config.dynamicPath);
  }

  return urlFor(locale, `${config.staticBase}/${entityPaths[entity]}/`);
}

function acceptedUrlPattern(locale) {
  const config = registration[locale];
  const host = locale === 'cs' ? 'cs\\.vpsfree\\.test' : 'en\\.vpsfree\\.test';

  return new RegExp(
    `^https?://${host}${config.acceptedPath.replace(
      /\//g,
      '\\/',
    )}\\?\\d+$`,
  );
}

function uniqueSuffix() {
  uniqueCounter += 1;
  return `${Date.now().toString(36)}${uniqueCounter.toString(36)}`;
}

function makeRegistrationData(locale, entity) {
  const suffix = uniqueSuffix();
  const login = `w${locale}${entity === 'pravnicka' ? 'p' : 'f'}${suffix}`;
  const company = entity === 'pravnicka';

  return {
    locale,
    entity,
    login,
    name: company ? 'Test Contact' : 'Test User',
    email: `${login}@example.test`,
    birth: '1990',
    address: locale === 'cs' ? `Testovaci ${100 + uniqueCounter}` : 'Example Street',
    city: locale === 'cs' ? 'Praha' : 'London',
    zip: locale === 'cs' ? '120 00' : 'AB12 3CD',
    country: locale === 'cs' ? 'Česko' : 'United Kingdom',
    region: '',
    how: `web integration test ${suffix}`,
    note: `registration test ${suffix}`,
    orgName: company ? `Example Org ${suffix}` : null,
    orgId: company ? `${100000 + uniqueCounter}` : null,
    timeZone: '',
    currency: locale === 'cs' ? 'czk' : 'eur',
  };
}

async function openRegistrationForm(page, locale, entity, mode) {
  await page.goto(registrationUrl(locale, mode, entity), {
    waitUntil: 'domcontentloaded',
  });

  if (mode === 'dynamic') {
    await page.locator('select[name="entity_type"]').selectOption(entity);
  }

  await expect(page.locator('input[name="login"]')).toBeVisible();
  await expect(page.locator('#send')).toBeVisible();
}

async function expectRegistrationFormShape(page, locale, entity, mode) {
  const config = registration[locale];

  await expect(page.locator('form')).toHaveAttribute('action', config.actionPath);
  await expect(page.locator('input[name="login"]')).toBeVisible();
  await expect(page.locator('input[name="email"]')).toBeVisible();
  await expect(page.locator('select[name="country"]')).toBeVisible();
  await expectSelectedOptionEnabled(page.locator('select[name="location"]'));
  await expectSelectedOptionEnabled(page.locator('select[name="distribution"]'));
  await expectSelectedOptionEnabled(page.locator('select[name="currency"]'));
  await expect(page.locator('select[name="currency"]')).toHaveValue(
    locale === 'cs' ? 'czk' : 'eur',
  );

  if (locale === 'en') {
    await expect(page.locator('input[name="region"]')).toBeVisible();
  } else {
    await expect(page.locator('input[name="region"]')).toHaveCount(0);
  }

  await expect(page.locator('select[name="country"] option').nth(1)).toHaveText(
    locale === 'cs' ? 'Česko' : 'Czechia',
  );
  await expect(page.locator('select[name="country"] option').nth(2)).toHaveText(
    locale === 'cs' ? 'Slovensko' : 'Slovakia',
  );
  const honeypot = page.locator('input[name="website"]');
  await expect(honeypot).toHaveAttribute('tabindex', '-1');
  await expect(honeypot).toHaveAttribute('autocomplete', 'off');
  await expect(honeypot).toHaveValue('');
  await expect(page.locator('input[name="form_started_at"]')).toHaveValue(/\d+/);
  await expect(page.locator('input[name="form_token"]')).toHaveValue(/[a-f0-9]{64}/);
  await expect(page.locator('input[type="hidden"][name="time_zone"]')).toHaveCount(1);
  await expect(page.locator('#send')).toHaveValue(config.submitText);

  if (mode === 'static') {
    await expect(page).toHaveURL(registrationUrl(locale, 'static', entity));
    await expect(page.locator('input[name="entity_type"]')).toHaveValue(entity);
  } else {
    await expect(page.locator('select[name="entity_type"]')).toHaveValue(entity);
  }

  if (entity === 'pravnicka') {
    await expect(page.locator('input[name="org_name"]')).toBeVisible();
    await expect(page.locator('input[name="ic"]')).toBeVisible();
  } else {
    await expect(page.locator('input[name="org_name"]')).toHaveCount(0);
    await expect(page.locator('input[name="ic"]')).toHaveCount(0);
  }
}

async function addHiddenInput(page, name, value) {
  await page.locator('form').evaluate(
    (form, input) => {
      let el = form.querySelector(`input[name="${input.name}"]`);

      if (!el) {
        el = document.createElement('input');
        el.type = 'hidden';
        el.name = input.name;
        form.appendChild(el);
      }

      el.value = input.value;
    },
    { name, value },
  );
}

async function disableNativeValidation(page) {
  await page.locator('form').evaluate((form) => {
    form.noValidate = true;
  });
}

async function selectFirstRealOption(locator) {
  const option = locator.locator('option:not([disabled])').first();
  const value = await option.getAttribute('value');

  expect(value).toBeTruthy();
  await locator.selectOption(value);
  return value;
}

async function expectSelectedOptionEnabled(locator) {
  await expect(locator).toBeVisible();
  await expect(
    locator.evaluate((select) => {
      const selected = select.selectedOptions[0];

      return selected && selected.value && !selected.disabled;
    }),
  ).resolves.toBeTruthy();
}

async function formDataSnapshot(page) {
  return page.locator('form').evaluate((form) => {
    const values = {};

    for (const [key, value] of new FormData(form).entries()) {
      values[key] = value;
    }

    return values;
  });
}

async function fillRegistrationForm(page, data) {
  await page.locator('input[name="login"]').fill(data.login);
  await page.locator('input[name="name"]').fill(data.name);
  await page.locator('input[name="email"]').fill(data.email);
  await page.locator('input[name="birth"]').fill(data.birth);
  await page.locator('input[name="address"]').fill(data.address);
  await page.locator('input[name="city"]').fill(data.city);
  await page.locator('input[name="zip"]').fill(data.zip);
  await page.locator('select[name="country"]').selectOption(data.country);

  if (await page.locator('input[name="region"]').count()) {
    await page.locator('input[name="region"]').fill(data.region || '');
  }

  await page.locator('input[name="how"]').fill(data.how);
  await page.locator('input[name="note"]').fill(data.note);
  data.timeZone = await page.locator('input[name="time_zone"]').inputValue();
  await page.locator('select[name="currency"]').selectOption(data.currency);

  if (data.entity === 'pravnicka') {
    await page.locator('input[name="org_name"]').fill(data.orgName);
    await page.locator('input[name="ic"]').fill(data.orgId);
  }

  data.location = await selectFirstRealOption(page.locator('select[name="location"]'));
  data.distribution = await selectFirstRealOption(page.locator('select[name="distribution"]'));
}

async function submitRegistration(page, locale) {
  const beforeSubmit = await formDataSnapshot(page);
  await waitForAntispamDelay(page);
  const requestPromise = page.waitForRequest(
    (request) => request.method() === 'POST' && request.url().endsWith('/send.php'),
  );

  const [request] = await Promise.all([
    requestPromise,
    page.waitForNavigation({ waitUntil: 'domcontentloaded' }),
    page.locator('#send').click(),
  ]);

  const acceptedPattern = acceptedUrlPattern(locale);

  if (!acceptedPattern.test(page.url())) {
    const body = await page.locator('body').innerText().catch(() => '<body unavailable>');
    throw new Error(
      [
        `registration was not accepted; current URL ${page.url()}`,
        `form data before submit: ${JSON.stringify(beforeSubmit)}`,
        `posted data: ${request.postData() || '<empty>'}`,
        `body:\n${body.slice(0, 4000)}`,
      ].join('\n'),
    );
  }

  const id = Number(new URL(page.url()).search.slice(1));
  expect(Number.isInteger(id)).toBe(true);
  await expect(page.locator('body')).toContainText(`#${id}`);
  return id;
}

async function submitValidationForm(page) {
  await disableNativeValidation(page);
  await addHiddenInput(page, '_mock', '1');
  await Promise.all([
    page.waitForNavigation({ waitUntil: 'domcontentloaded' }),
    page.locator('#send').click(),
  ]);
}

async function waitForAntispamDelay(page) {
  const startedAt = Number(await page.locator('input[name="form_started_at"]').inputValue());
  const age = Math.floor(Date.now() / 1000) - startedAt;
  const targetAge = 7;

  if (Number.isFinite(age) && age < targetAge) {
    await page.waitForTimeout((targetAge - age) * 1000 + 250);
  }
}

module.exports = {
  registration,
  registrationUrl,
  makeRegistrationData,
  openRegistrationForm,
  expectRegistrationFormShape,
  addHiddenInput,
  disableNativeValidation,
  fillRegistrationForm,
  submitRegistration,
  submitValidationForm,
  waitForAntispamDelay,
};
