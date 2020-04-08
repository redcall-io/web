<?php

namespace Bundles\TwilioBundle\Controller;

use Bundles\TwilioBundle\Entity\TwilioStatus;
use Bundles\TwilioBundle\Event\TwilioMessageEvent;
use Bundles\TwilioBundle\Manager\TwilioMessageManager;
use Bundles\TwilioBundle\Manager\TwilioStatusManager;
use Bundles\TwilioBundle\TwilioEvents;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @Route(name="twilio_", path="/twilio/")
 */
class StatusController extends BaseController
{
    /**
     * @var TwilioMessageManager
     */
    private $messageManager;

    /**
     * @var TwilioStatusManager
     */
    private $statusManager;

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @param TwilioMessageManager     $messageManager
     * @param TwilioStatusManager      $statusManager
     * @param EventDispatcherInterface $eventDispatcher
     * @param LoggerInterface|null     $logger
     */
    public function __construct(TwilioMessageManager $messageManager, TwilioStatusManager $statusManager, EventDispatcherInterface $eventDispatcher, LoggerInterface $logger = null)
    {
        $this->messageManager = $messageManager;
        $this->statusManager = $statusManager;
        $this->eventDispatcher = $eventDispatcher;
        $this->logger = $logger ?? new NullLogger();
    }

    /**
     * @Route(name="status", path="message-status/{uuid}")
     * @Template()
     */
    public function messageStatus(Request $request, string $uuid)
    {
        $this->validateRequestSignature($request);

        $this->logger->info('Twilio webhooks - message delivery status', [
            'headers' => $request->headers->all(),
            'query' => $request->query->all(),
            'request' => $request->request->all(),
        ]);

        $outbound = $this->messageManager->get($uuid);

        if ($outbound) {
            $outbound->setStatus($request->get('MessageStatus'));
            $this->eventDispatcher->dispatch(new TwilioMessageEvent($outbound), TwilioEvents::STATUS_UPDATED);
            $this->messageManager->save($outbound);
        }

        $status = new TwilioStatus();
        $status->setType(TwilioStatus::TYPE_MESSAGE);
        $status->setSid($request->get('MessageSid'));
        $status->setStatus($request->get('MessageStatus'));
        $this->statusManager->save($status);

        return new Response();
    }
}
