<?php

namespace App\Manager;

use App\Entity\Campaign;
use App\Entity\Choice;
use App\Entity\Communication;
use App\Entity\Communication as CommunicationEntity;
use App\Entity\Message;
use App\Form\Model\Communication as CommunicationModel;
use App\Issue\IssueLogger;
use App\Repository\CommunicationRepository;

class CommunicationManager
{
    /**
     * @var CampaignManager
     */
    private $campaignManager;

    /**
     * @var MessageManager
     */
    private $messageManager;

    /**
     * @var CommunicationRepository
     */
    private $communicationRepository;

    /**
     * @param MessageManager $messageManager
     * @param CommunicationRepository $communicationRepository
     */
    public function __construct(MessageManager $messageManager,
                                CommunicationRepository $communicationRepository)
    {
        $this->messageManager = $messageManager;
        $this->communicationRepository = $communicationRepository;
    }

    /**
     * @param int $communicationId
     *
     * @return CommunicationEntity|null
     */
    public function find(int $communicationId) : ?CommunicationEntity
    {
        return $this->communicationRepository->find($communicationId);
    }

    /**
     * @param CampaignManager $campaignManager
     */
    public function setCampaignManager(CampaignManager $campaignManager)
    {
        $this->campaignManager = $campaignManager;
    }

    /**
     * @param Campaign $campaign
     * @param CommunicationModel $communicationModel
     *
     * @throws \Exception
     *
     * @return CommunicationEntity
     */
    public function launchNewCommunication(Campaign $campaign, CommunicationModel $communicationModel) : CommunicationEntity
    {
        $communicationEntity = $this->createCommunication($communicationModel);

        $campaign->addCommunication($communicationEntity);

        $this->campaignManager->save($campaign);

        $this->processor->process($communicationEntity);

        $this->communicationRepository->save($communicationEntity);

        return $communicationEntity;
    }

    /**
     * @param CommunicationModel $communicationModel
     *
     * @return CommunicationEntity
     *
     * @throws \Exception
     */
    public function createCommunication(CommunicationModel $communicationModel) : CommunicationEntity
    {
        $communicationEntity = new CommunicationEntity();
        $communicationEntity
            ->setType($communicationModel->type)
            ->setBody($communicationModel->message)
            ->setGeoLocation($communicationModel->geoLocation)
            ->setCreatedAt(new \DateTime())
            ->setMultipleAnswer($communicationModel->multipleAnswer)
            ->setSubject($communicationModel->subject)
            ->setPrefix($communicationModel->prefix);

        foreach ($communicationModel->volunteers as $volunteer) {
            $message = new Message();

            $message->setWebCode(
                $this->messageManager->generateWebCode()
            );

            $communicationEntity->addMessage($message->setVolunteer($volunteer));
        }

        // The first choice key is always "1"
        $choiceKey = 1;
        foreach (array_unique($communicationModel->answers) as $choiceValue) {
            $choice = new Choice();
            $choice
                ->setCode(sprintf('%s%d', $communicationModel->prefix, $choiceKey))
                ->setLabel($choiceValue);

            $communicationEntity->addChoice($choice);
            $choiceKey++;
        }

        return $communicationEntity;
    }

    /**
     * @return array
     */
    public function getTakenPrefixes() : array
    {
        return $this->communicationRepository->getTakenPrefixes();
    }

    /**
     * @param \App\Manager\Communication $communication
     * @param string                     $newName
     */
    public function changeName(Communication $communication, string $newName)
    {
        $this->communicationRepository->changeName($communication, $newName);
    }

    private function dispatch(CommunicationEntity $communication)
    {
        try {
            $this->processor->process($communication);
        } catch (\Throwable $e) {
            $this->eventLogger->fileIssueFromException('Failed to dispatch communication', $e, IssueLogger::SEVERITY_CRITICAL, [
                'communication_id'    => $communication->getId(),
                'communication_type'  => $communication->getType(),
                'targeted_volunteers' => $communication->getMessages()->count(),
            ]);
        }

   }
}