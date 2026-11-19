<?php
/**
 * @package      Webcode_MultisiteFpc
 *
 * @author       Webcode (contact@webcode.bg)
 * @copyright    Copyright © Webcode Ltd. (https://webcode.bg/)
 * @license      https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */

declare(strict_types=1);

namespace Webcode\MultisiteFpc\Test\Unit\Plugin\Magento\Framework\App\Http;

use Magento\Framework\App\Http\Context as HttpContext;
use Magento\Framework\Exception\LocalizedException;
use Magento\Store\Api\Data\GroupInterface;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Api\Data\WebsiteInterface;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Webcode\MultisiteFpc\Plugin\Magento\Framework\App\Http\Context as ContextPlugin;

class ContextTest extends TestCase
{
    private const GLOBAL_STORE_ID = 1;
    private const GLOBAL_STORE_CODE = 'bg';
    private const GLOBAL_CURRENCY = 'BGN';
    private const WEBSITE_STORE_ID = 21;
    private const WEBSITE_STORE_CODE = 'bgapteka';
    private const WEBSITE_CURRENCY = 'EUR';

    /**
     * @var StoreManagerInterface|MockObject
     */
    private StoreManagerInterface $storeManager;

    /**
     * @var HttpContext|MockObject
     */
    private HttpContext $httpContext;

    /**
     * @var ContextPlugin
     */
    private ContextPlugin $plugin;

    protected function setUp(): void
    {
        $this->storeManager = $this->createMock(StoreManagerInterface::class);
        $this->httpContext = $this->createMock(HttpContext::class);
        $this->plugin = new ContextPlugin($this->storeManager);
    }

    public function testUnrelatedContextKeyPassesThroughUntouched(): void
    {
        $this->storeManager->expects($this->never())->method('getDefaultStoreView');
        $this->storeManager->expects($this->never())->method('getWebsite');

        $this->assertSame(
            ['customer_group', 2, 0],
            $this->plugin->beforeSetValue($this->httpContext, 'customer_group', 2, 0)
        );
    }

    public function testStoreDefaultIsScopedToCurrentWebsite(): void
    {
        $this->wireStores(
            $this->createStore(self::GLOBAL_STORE_ID, self::GLOBAL_STORE_CODE, self::GLOBAL_CURRENCY),
            $this->createStore(self::WEBSITE_STORE_ID, self::WEBSITE_STORE_CODE, self::WEBSITE_CURRENCY)
        );

        $this->assertSame(
            [StoreManagerInterface::CONTEXT_STORE, self::WEBSITE_STORE_CODE, self::WEBSITE_STORE_CODE],
            $this->plugin->beforeSetValue(
                $this->httpContext,
                StoreManagerInterface::CONTEXT_STORE,
                self::WEBSITE_STORE_CODE,
                self::GLOBAL_STORE_CODE
            )
        );
    }

    public function testStoreDefaultUntouchedOnTheDefaultWebsite(): void
    {
        $globalDefault = $this->createStore(self::GLOBAL_STORE_ID, self::GLOBAL_STORE_CODE, self::GLOBAL_CURRENCY);
        $this->wireStores($globalDefault, $globalDefault);

        $this->assertSame(
            [StoreManagerInterface::CONTEXT_STORE, self::GLOBAL_STORE_CODE, self::GLOBAL_STORE_CODE],
            $this->plugin->beforeSetValue(
                $this->httpContext,
                StoreManagerInterface::CONTEXT_STORE,
                self::GLOBAL_STORE_CODE,
                self::GLOBAL_STORE_CODE
            )
        );
    }

    public function testStoreDefaultUntouchedWhenCoreDidNotUseTheGlobalFallback(): void
    {
        $this->wireStores(
            $this->createStore(self::GLOBAL_STORE_ID, self::GLOBAL_STORE_CODE, self::GLOBAL_CURRENCY),
            $this->createStore(self::WEBSITE_STORE_ID, self::WEBSITE_STORE_CODE, self::WEBSITE_CURRENCY)
        );

        // MAGE_RUN_TYPE=store / store-code-in-URL branches pass a non-global default.
        $this->assertSame(
            [StoreManagerInterface::CONTEXT_STORE, 'herbde', 'herben'],
            $this->plugin->beforeSetValue(
                $this->httpContext,
                StoreManagerInterface::CONTEXT_STORE,
                'herbde',
                'herben'
            )
        );
    }

    public function testCurrencyDefaultIsScopedToCurrentWebsite(): void
    {
        $this->wireStores(
            $this->createStore(self::GLOBAL_STORE_ID, self::GLOBAL_STORE_CODE, self::GLOBAL_CURRENCY),
            $this->createStore(self::WEBSITE_STORE_ID, self::WEBSITE_STORE_CODE, self::WEBSITE_CURRENCY)
        );

        $this->assertSame(
            [HttpContext::CONTEXT_CURRENCY, self::WEBSITE_CURRENCY, self::WEBSITE_CURRENCY],
            $this->plugin->beforeSetValue(
                $this->httpContext,
                HttpContext::CONTEXT_CURRENCY,
                self::WEBSITE_CURRENCY,
                self::GLOBAL_CURRENCY
            )
        );
    }

    public function testCurrencyDefaultUntouchedWhenWebsiteCurrencyIsUnresolvable(): void
    {
        // A bare StoreInterface double (not the Store model) cannot expose a default currency.
        $bareStore = $this->createMock(StoreInterface::class);
        $bareStore->method('getId')->willReturn(self::WEBSITE_STORE_ID);

        $this->wireStores(
            $this->createStore(self::GLOBAL_STORE_ID, self::GLOBAL_STORE_CODE, self::GLOBAL_CURRENCY),
            $bareStore
        );

        $this->assertSame(
            [HttpContext::CONTEXT_CURRENCY, self::WEBSITE_CURRENCY, self::GLOBAL_CURRENCY],
            $this->plugin->beforeSetValue(
                $this->httpContext,
                HttpContext::CONTEXT_CURRENCY,
                self::WEBSITE_CURRENCY,
                self::GLOBAL_CURRENCY
            )
        );
    }

    public function testStoreResolutionFailureFallsBackToCoreBehaviour(): void
    {
        $this->storeManager->method('getDefaultStoreView')
            ->willReturn($this->createStore(self::GLOBAL_STORE_ID, self::GLOBAL_STORE_CODE, self::GLOBAL_CURRENCY));
        $this->storeManager->method('getWebsite')
            ->willThrowException(new LocalizedException(__('No website in scope')));

        $this->assertSame(
            [StoreManagerInterface::CONTEXT_STORE, self::WEBSITE_STORE_CODE, self::GLOBAL_STORE_CODE],
            $this->plugin->beforeSetValue(
                $this->httpContext,
                StoreManagerInterface::CONTEXT_STORE,
                self::WEBSITE_STORE_CODE,
                self::GLOBAL_STORE_CODE
            )
        );
    }

    /**
     * Wire the store manager to resolve the given global default view and current website default store.
     *
     * @param StoreInterface $globalDefault
     * @param StoreInterface $websiteDefault
     * @return void
     */
    private function wireStores(StoreInterface $globalDefault, StoreInterface $websiteDefault): void
    {
        $website = $this->createMock(WebsiteInterface::class);
        $website->method('getDefaultGroupId')->willReturn(14);

        $group = $this->createMock(GroupInterface::class);
        $group->method('getDefaultStoreId')->willReturn((int)$websiteDefault->getId());

        $this->storeManager->method('getDefaultStoreView')->willReturn($globalDefault);
        $this->storeManager->method('getWebsite')->willReturn($website);
        $this->storeManager->method('getGroup')->with(14)->willReturn($group);
        $this->storeManager->method('getStore')->with((int)$websiteDefault->getId())->willReturn($websiteDefault);
    }

    /**
     * Store model double exposing id, code and default display currency.
     *
     * @param int $id
     * @param string $code
     * @param string $currency
     * @return Store|MockObject
     */
    private function createStore(int $id, string $code, string $currency): Store
    {
        $store = $this->createMock(Store::class);
        $store->method('getId')->willReturn($id);
        $store->method('getCode')->willReturn($code);
        $store->method('getDefaultCurrencyCode')->willReturn($currency);

        return $store;
    }
}
