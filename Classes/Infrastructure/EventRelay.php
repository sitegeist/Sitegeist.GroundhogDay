<?php

declare(strict_types=1);

namespace Sitegeist\GroundhogDay\Infrastructure;

use Neos\ContentRepository\Domain\Model\NodeInterface;
use Neos\ContentRepository\Domain\Model\Workspace;
use Neos\ContentRepository\Domain\NodeAggregate\NodeAggregateIdentifier;
use Neos\Flow\Annotations as Flow;
use Neos\Neos\Domain\Service\ContentContextFactory;
use Recurr\Rule;
use Sitegeist\GroundhogDay\Domain\EventDateZookeeper;
use Sitegeist\GroundhogDay\Domain\RecurrenceRuleWasChanged;

/**
 * The event relay infrastructure service
 *
 * Translates CR / Neos events into GroundhogDay domain events
 */
#[Flow\Scope('singleton')]
class EventRelay
{
    /**
     * @var array<string,Rule>
     */
    private array $publishingRegistry;

    public function __construct(
        private readonly ContentContextFactory $contentContextFactory,
        private readonly EventDateZookeeper $eventDateZookeeper,
    ) {
    }

    public function preRegisterNodePublishing(NodeInterface $node, Workspace $targetWorkspace): void
    {
        if ($targetWorkspace->getName() === 'live' && $node->getNodeType()->isOfType('Sitegeist.GroundhogDay:Mixin.Event')) {
            $contextProperties = $node->getContext()->getProperties();
            $contextProperties['workspaceName'] = 'live';
            $liveContext = $this->contentContextFactory->create($contextProperties);

            $liveEvent = $liveContext->getNodeByIdentifier($node);
            $recurrenceRule = $node->getProperty('recurrenceRule');
            if (!$recurrenceRule instanceof Rule) {
                $recurrenceRule = null;
            }
            if ($liveEvent instanceof NodeInterface) {
                $liveRecurrenceRule = $liveEvent->getProperty('recurrenceRule');
                if (!$liveRecurrenceRule instanceof Rule) {
                    $liveRecurrenceRule = null;
                }

                if ($recurrenceRule?->getString() !== $liveRecurrenceRule?->getString()) {
                    $this->publishingRegistry[$node->getIdentifier()] = $recurrenceRule;
                }
            } else {
                if ($recurrenceRule) {
                    $this->publishingRegistry[$node->getIdentifier()] = $recurrenceRule;
                }
            }
        }
    }
    public function handleNodePublishing(NodeInterface $node, Workspace $targetWorkspace): void
    {
        if (
            $targetWorkspace->getName() === 'live'
            && $node->getNodeType()->isOfType('Sitegeist.GroundhogDay:Mixin.Event')
            && array_key_exists($node->getIdentifier(), $this->publishingRegistry)
        ) {
            $this->eventDateZookeeper->whenRecurrenceRuleWasChanged(new RecurrenceRuleWasChanged(
                NodeAggregateIdentifier::fromString($node->getIdentifier()),
                $this->publishingRegistry[$node->getIdentifier()],
            ));
        }
    }
}
