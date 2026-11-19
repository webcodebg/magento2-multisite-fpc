<?php
/**
 * @package      Webcode_MultisiteFpc
 *
 * @author       Webcode (contact@webcode.bg)
 * @copyright    Copyright © Webcode Ltd. (https://webcode.bg/)
 * @license      https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */

declare(strict_types=1);

namespace Webcode\MultisiteFpc\Plugin\Magento\Framework\App\Http;

use Magento\Framework\App\Http\Context as HttpContext;
use Magento\Framework\Exception\LocalizedException;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Scope the HTTP context defaults for store and currency to the CURRENT website.
 *
 * Core Magento\Store\App\Action\Plugin\Context::updateContext() falls back to the
 * GLOBAL default store view when the site runs with MAGE_RUN_TYPE=website (the most
 * common multi-website setup), so on every non-default website the context value
 * always differs from its default. Context::getVaryString() is then non-empty for
 * every guest, Magento sends Set-Cookie: X-Magento-Vary on every response, and the
 * official 2.4.6+ Varnish VCL refuses to cache a response that sets the vary cookie
 * on a request that carried none (cache-poisoning guard). Net effect: first-time
 * visitors, bots and crawlers can never populate the FPC on those websites.
 *
 * The fix narrowly targets that broken branch: only when the default passed for the
 * store/currency context is the global default store view's (and the current website
 * is a different one) it is substituted with the current website's default store view
 * (resp. that view's default currency). Single-store websites then produce
 * value == default, no vary cookie is sent and pages become cacheable; multi-store
 * websites still correctly vary on their non-default views. The other two core
 * branches (store code in URL, MAGE_RUN_TYPE=store) never match the condition and
 * stay untouched.
 */
class Context
{
    /**
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(
        private readonly StoreManagerInterface $storeManager
    ) {
    }

    /**
     * Substitute the global default with the current website's default for store/currency context.
     *
     * @param HttpContext $subject
     * @param mixed $name
     * @param mixed $value
     * @param mixed $default
     * @return array
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function beforeSetValue(HttpContext $subject, $name, $value, $default): array
    {
        if ($name !== StoreManagerInterface::CONTEXT_STORE && $name !== HttpContext::CONTEXT_CURRENCY) {
            return [$name, $value, $default];
        }

        try {
            $globalDefault = $this->storeManager->getDefaultStoreView();
            $websiteDefault = $this->getCurrentWebsiteDefaultStore();
        } catch (LocalizedException $exception) {
            // Cache correctness must never break rendering - fall back to core behaviour.
            return [$name, $value, $default];
        }

        if ($globalDefault === null
            || $websiteDefault === null
            || (int)$websiteDefault->getId() === (int)$globalDefault->getId()
        ) {
            return [$name, $value, $default];
        }

        if ($name === StoreManagerInterface::CONTEXT_STORE && $default === $globalDefault->getCode()) {
            return [$name, $value, $websiteDefault->getCode()];
        }

        if ($name === HttpContext::CONTEXT_CURRENCY) {
            $websiteCurrency = $this->getDefaultCurrencyCode($websiteDefault);
            if ($websiteCurrency !== null && $default === $this->getDefaultCurrencyCode($globalDefault)) {
                return [$name, $value, $websiteCurrency];
            }
        }

        return [$name, $value, $default];
    }

    /**
     * Resolve the default store view of the current website through scope interfaces only.
     *
     * @return StoreInterface|null
     * @throws LocalizedException
     */
    private function getCurrentWebsiteDefaultStore(): ?StoreInterface
    {
        $groupId = $this->storeManager->getWebsite()->getDefaultGroupId();
        if (!$groupId) {
            return null;
        }

        $storeId = (int)$this->storeManager->getGroup($groupId)->getDefaultStoreId();
        if (!$storeId) {
            return null;
        }

        return $this->storeManager->getStore($storeId);
    }

    /**
     * Default display currency of a store view; null when unresolvable.
     *
     * @param StoreInterface $store
     * @return string|null
     */
    private function getDefaultCurrencyCode(StoreInterface $store): ?string
    {
        if (!$store instanceof Store) {
            return null;
        }

        $code = $store->getDefaultCurrencyCode();

        return is_string($code) && $code !== '' ? $code : null;
    }
}
