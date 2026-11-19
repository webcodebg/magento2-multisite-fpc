# Webcode_MultisiteFpc

Makes multi-website Magento installations cacheable again for first-time
visitors, bots and crawlers.

## The problem

With `MAGE_RUN_TYPE=website` (the most common multi-domain setup), core
`Magento\Store\App\Action\Plugin\Context::updateContext()` compares the current
store against the **global** default store view instead of the current
website's own default. On every non-default website the HTTP context therefore
always deviates from its default, `Context::getVaryString()` is non-empty for
every guest, and Magento emits `Set-Cookie: X-Magento-Vary` on **every**
response.

The official Magento Varnish VCL (2.4.6+) contains a cache-poisoning guard:
a response that sets `X-Magento-Vary` on a request that carried no such cookie
is marked uncacheable (hit-for-pass). Combined with the core defect this means
no cookieless request — first visit, bot, crawler, `curl` — can ever populate
the full page cache on a non-default website (`x-magento-cache-debug:
UNCACHEABLE` on every page).

## The fix

A `before` plugin on `Magento\Framework\App\Http\Context::setValue` (frontend
area) narrowly targets the broken core branch: when the default passed for the
`store` / `current_currency` context keys is the global default store view's
(and the current website is a different one), it is substituted with the
current website's default store view, resp. that view's default currency.

- Single-store websites: value == default → no vary cookie → pages cache.
- Multi-store websites: non-default store views still correctly vary.
- The other core branches (store code in URL, `MAGE_RUN_TYPE=store`) never
  match the substitution condition and stay untouched.

No configuration — there is exactly one correct behaviour.

## Verification on a live site

Before: `curl -sD- https://<non-default-domain>/ | grep -iE 'vary|cache'`
shows `Set-Cookie: X-Magento-Vary=…` from the backend and
`x-magento-cache-debug: UNCACHEABLE` through Varnish.
After: no vary cookie for a plain guest request; through Varnish the second
request is a `HIT`.

## Installation

Webcode's free modules are installed from our own Composer repository — they are not published on Packagist.

```bash
composer config repositories.webcode composer https://repo.webcode.bg/public
composer require webcode/module-multisite-fpc
bin/magento module:enable Webcode_MultisiteFpc
bin/magento setup:upgrade
```

## Compatibility

Magento Open Source and Mage-OS 2.4.7–2.4.9, PHP 8.3–8.5.

## License

[OSL-3.0](LICENSE.txt) and [AFL-3.0](LICENSE_AFL.txt), like Magento itself.

## About

Made by [Webcode](https://webcode.bg/), a Magento development and support studio from Bulgaria. Module page: <https://webcode.bg/en/multisite-fpc>.
