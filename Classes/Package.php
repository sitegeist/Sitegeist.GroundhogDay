<?php

declare(strict_types=1);

namespace Sitegeist\GroundhogDay;

use Neos\ContentRepository\Domain\Model\Node;
use Neos\Flow\Core\Bootstrap;
use Neos\Flow\Package as BasePackage;
use Sitegeist\GroundhogDay\Infrastructure\EventRelay;

/**
 * The Sitegeist.GroundhogDay package
 */
class Package extends BasePackage
{
    public function boot(Bootstrap $bootstrap): void
    {
        $dispatcher = $bootstrap->getSignalSlotDispatcher();

        $dispatcher->connect(
            Node::class,
            'nodePropertyChanged',
            EventRelay::class,
            'registerPropertyChange'
        );
    }
}
