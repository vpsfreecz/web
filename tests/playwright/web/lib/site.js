const { expect } = require('@playwright/test');

const locales = {
  cs: {
    host: 'cs.vpsfree.test',
    routes: [
      '/',
      '/404.html',
      '/o-vpsfree/',
      '/tym/',
      '/komunita/',
      '/parametry/',
      '/prihlaska/',
      '/prihlaska/fyzicka-osoba/',
      '/prihlaska/pravnicka-osoba/',
      '/prihlaska/prijata/?0',
      '/faq/',
      '/hw/',
      '/hw/ajeto.html',
      '/kontakt/',
    ],
  },
  en: {
    host: 'en.vpsfree.test',
    routes: [
      '/',
      '/404.html',
      '/about/',
      '/team/',
      '/community/',
      '/parameters/',
      '/registration/',
      '/registration/fyzicka-osoba/',
      '/registration/pravnicka-osoba/',
      '/registration/accepted/?0',
      '/faq/',
      '/contact/',
    ],
  },
};

const allowedHosts = new Set(Object.values(locales).map((locale) => locale.host));

function urlFor(locale, path) {
  return `http://${locales[locale].host}${path}`;
}

async function preparePage(page) {
  const state = {
    failedResponses: [],
    pageErrors: [],
  };

  page.on('pageerror', (error) => {
    state.pageErrors.push(error.message);
  });

  page.on('response', (response) => {
    let url;

    try {
      url = new URL(response.url());
    } catch (_error) {
      return;
    }

    if (allowedHosts.has(url.hostname) && response.status() >= 400) {
      state.failedResponses.push(`${response.status()} ${response.url()}`);
    }
  });

  await page.route('**/*', async (route) => {
    const requestUrl = route.request().url();

    if (requestUrl.startsWith('data:') || requestUrl.startsWith('about:')) {
      await route.continue();
      return;
    }

    let url;

    try {
      url = new URL(requestUrl);
    } catch (_error) {
      await route.continue();
      return;
    }

    if (!allowedHosts.has(url.hostname)) {
      await route.fulfill({ status: 204, body: '' });
      return;
    }

    await route.continue();
  });

  return state;
}

async function expectHealthyPage(page, state, targetUrl) {
  const response = await page.goto(targetUrl, { waitUntil: 'domcontentloaded' });

  expect(response, `missing response for ${targetUrl}`).not.toBeNull();
  expect(response.status(), `${targetUrl} status`).toBeGreaterThanOrEqual(200);
  expect(response.status(), `${targetUrl} status`).toBeLessThan(400);

  await page.waitForLoadState('networkidle').catch(() => {});

  const bodyText = (await page.locator('body').innerText()).trim();
  const html = await page.content();

  expect(bodyText.length, `${targetUrl} body length`).toBeGreaterThan(20);
  expect(html, `${targetUrl} raw SSI`).not.toContain('<!--#');
  expect(html, `${targetUrl} PHP diagnostics`).not.toMatch(
    /(?:Fatal error|Parse error|Warning|Notice):/,
  );
  expect(state.pageErrors, `${targetUrl} page errors`).toEqual([]);
  expect(state.failedResponses, `${targetUrl} same-origin failures`).toEqual([]);
}

module.exports = {
  locales,
  preparePage,
  expectHealthyPage,
  urlFor,
};
