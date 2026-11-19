<?php
/**
 * @package      Webcode_MultisiteFpc
 *
 * @author       Webcode (contact@webcode.bg)
 * @copyright    Copyright © Webcode Ltd. (https://webcode.bg/)
 * @license      https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */

declare(strict_types=1);

use Magento\Framework\Component\ComponentRegistrar;

ComponentRegistrar::register(
    ComponentRegistrar::MODULE,
    'Webcode_MultisiteFpc',
    __DIR__
);
