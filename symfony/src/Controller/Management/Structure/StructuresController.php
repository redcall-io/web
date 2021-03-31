<?php

namespace App\Controller\Management\Structure;

use App\Base\BaseController;
use App\Component\HttpFoundation\ArrayToCsvResponse;
use App\Entity\Structure;
use App\Entity\Volunteer;
use App\Enum\Platform;
use App\Form\Type\StructureType;
use App\Manager\StructureManager;
use App\Manager\UserManager;
use App\Model\Csrf;
use Bundles\PaginationBundle\Manager\PaginationManager;
use Bundles\PegassCrawlerBundle\Entity\Pegass;
use Bundles\PegassCrawlerBundle\Manager\PegassManager;
use DateTime;
use DateTimeZone;
use Ramsey\Uuid\Uuid;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @Route(path="management/structures", name="management_structures_")
 */
class StructuresController extends BaseController
{
    /**
     * @var StructureManager
     */
    private $structureManager;

    /**
     * @var PaginationManager
     */
    private $paginationManager;

    /**
     * @var PegassManager
     */
    private $pegassManager;

    /**
     * @var UserManager
     */
    private $userManager;

    /**
     * @var KernelInterface
     */
    private $kernel;

    /**
     * @var TranslatorInterface
     */
    private $translator;

    public function __construct(StructureManager $structureManager,
        PaginationManager $paginationManager,
        PegassManager $pegassManager,
        UserManager $userManager,
        KernelInterface $kernel,
        TranslatorInterface $translator)
    {
        $this->structureManager  = $structureManager;
        $this->paginationManager = $paginationManager;
        $this->pegassManager     = $pegassManager;
        $this->userManager       = $userManager;
        $this->kernel            = $kernel;
        $this->translator        = $translator;
    }

    /**
     * @Route("/{enabled}", name="list", defaults={"enabled" = true}, requirements={"enabled" = "^\d?$"})
     */
    public function listAction(Request $request, bool $enabled)
    {
        $search = $this->createSearchForm($request);

        $criteria = null;
        if ($search->isSubmitted() && $search->isValid()) {
            $criteria = $search->get('criteria')->getData();
            $enabled  = $search->get('only_enabled')->getData();
        }

        if ($this->isGranted('ROLE_ADMIN')) {
            $queryBuilder = $this->structureManager->searchAllQueryBuilder($criteria, $enabled);
        } else {
            $queryBuilder = $this->structureManager->searchForCurrentUserQueryBuilder($criteria, $enabled);
        }

        $redcallUsers = $this->structureManager->countRedCallUsersInPager(
            $this->paginationManager->getPager(
                $this->structureManager->countRedCallUsersQueryBuilder($queryBuilder)
            )
        );

        return $this->render('management/structures/list.html.twig', [
            'search'       => $search->createView(),
            'structures'   => $this->paginationManager->getPager($queryBuilder),
            'redcallUsers' => $redcallUsers,
            'enabled'      => $enabled,
        ]);
    }

    /**
     * @Route("/create/{id}", name="create", defaults={"id" = null})
     * @Security("is_granted('ROLE_ADMIN')")
     * @Template("management/structures/form.html.twig")
     */
    public function createStructure(Request $request, ?Structure $structure = null)
    {
        if (null === $structure) {
            $structure = new Structure();
            $structure->setExternalId(Uuid::uuid4());
            $structure->setPlatform($this->getPlatform());
        }

        $form = $this->createForm(StructureType::class, $structure);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->structureManager->save($structure);

            return $this->redirectToRoute('management_structures_list');
        }

        return ['form' => $form->createView()];
    }

    /**
     * @Route(name="force_update", path="/force-update/{csrf}/{id}")
     * @IsGranted("STRUCTURE", subject="structure")
     */
    public function forceUpdate(Request $request, Structure $structure, string $csrf)
    {
        $this->validateCsrfOrThrowNotFoundException('structures', $csrf);

        if ($structure->isLocked()) {
            throw $this->createNotFoundException();
        }

        if (Platform::FR !== $structure->getPlatform()) {
            throw $this->createNotFoundException();
        }

        if (!$structure->canForcePegassUpdate()) {
            return $this->redirectToRoute('management_structures_list', $request->query->all());
        }

        // Just in case Pegass database would contain some RCE?
        if (!preg_match('/^[a-zA-Z0-9]+$/', $structure->getExternalId())) {
            return $this->redirectToRoute('management_structures_list', $request->query->all());
        }

        // Prevents multiple clicks
        $structure->setLastPegassUpdate(new DateTime('now', new DateTimeZone('UTC')));
        $this->structureManager->save($structure);

        // Executing asynchronous task to prevent against interruptions
        $console = sprintf('%s/bin/console', $this->kernel->getProjectDir());
        $command = sprintf('%s pegass --structure %s', escapeshellarg($console), $structure->getIdentifier());
        exec(sprintf('%s > /dev/null 2>&1 & echo -n \$!', $command));

        return $this->redirectToRoute('management_structures_list', $request->query->all());
    }

    /**
     * @Route(name="pegass", path="/pegass/{id}")
     * @IsGranted("ROLE_ADMIN")
     */
    public function pegass(Structure $structure, Request $request)
    {
        if (Platform::FR !== $structure->getPlatform()) {
            throw $this->createNotFoundException();
        }

        $entity = $this->pegassManager->getEntity(Pegass::TYPE_STRUCTURE, $structure->getExternalId(), false);
        if (!$entity) {
            throw $this->createNotFoundException();
        }

        return $this->render('management/structures/pegass.html.twig', [
            'structure' => $structure,
            'pegass'    => json_encode($entity->getContent(), JSON_PRETTY_PRINT),
        ]);
    }

    /**
     * @Route(name="export", path="/export/{id}")
     * @IsGranted("STRUCTURE", subject="structure")
     */
    public function export(Structure $structure)
    {
        $rows = [];
        foreach ($structure->getVolunteers() as $volunteer) {
            /** @var Volunteer $volunteer */
            $rows[] = [
                'nivol'     => $volunteer->getNivol(),
                'firstname' => $volunteer->getFirstName(),
                'lastname'  => $volunteer->getLastName(),
                'phone'     => $volunteer->getPhoneNumber() ?: null,
                'email'     => $volunteer->getEmail(),
            ];
        }

        return new ArrayToCsvResponse($rows, sprintf('%s.%s.csv', date('Y-m-d'), $structure->getName()));
    }

    /**
     * @Route(name="list_users", path="/list-users")
     */
    public function listUsers(Request $request)
    {
        $structure = $this->getStructureById($request->get('id'));
        $users     = $this->userManager->getRedCallUsersInStructure($structure);

        return $this->json([
            'title' => $this->translator->trans('manage_structures.redcall_users', [
                '%name%' => $structure->getName(),
            ]),
            'body'  => $this->renderView('management/structures/users.html.twig', [
                'users' => $users,
            ]),
        ]);
    }

    /**
     * @Route(path="/toggle-lock-{id}/{token}", name="toggle_lock")
     * @IsGranted("STRUCTURE", subject="structure")
     * @IsGranted("ROLE_ADMIN")
     * @Template("management/structures/structure.html.twig")
     */
    public function toggleLock(Structure $structure, Csrf $token)
    {

        $structure->setLocked(1 - $structure->isLocked());

        $this->structureManager->save($structure);

        return [
            'structure'    => $structure,
            'redcallUsers' => [
                $structure->getId() => count($structure->getUsers()),
            ],
        ];
    }

    /**
     * @Route(path="/toggle-enable-{id}/{token}", name="toggle_enable")
     * @IsGranted("STRUCTURE", subject="structure")
     * @IsGranted("ROLE_ADMIN")
     * @Template("management/structures/structure.html.twig")
     */
    public function toggleEnable(Structure $structure, Csrf $token)
    {
        if ($structure->isLocked()) {
            throw $this->createNotFoundException();
        }

        $structure->setEnabled(1 - $structure->isEnabled());

        $this->structureManager->save($structure);

        return [
            'structure'    => $structure,
            'redcallUsers' => [
                $structure->getId() => count($structure->getUsers()),
            ],
        ];
    }

    private function createSearchForm(Request $request) : FormInterface
    {
        return $this->createFormBuilder(['only_enabled' => true], ['csrf_protection' => false])
                    ->setMethod('GET')
                    ->add('criteria', TextType::class, [
                        'label'    => 'manage_structures.search.label',
                        'required' => false,
                    ])
                    ->add('only_enabled', CheckboxType::class, [
                        'label'    => 'manage_structures.search.only_enabled',
                        'required' => false,
                    ])
                    ->add('submit', SubmitType::class, [
                        'label' => 'manage_structures.search.button',
                    ])
                    ->getForm()
                    ->handleRequest($request);
    }

    private function getStructureById(?int $id) : Structure
    {
        $structure = $this->structureManager->find($id);

        if (null === $id) {
            throw $this->createNotFoundException();
        }

        if (!$this->isGranted('STRUCTURE', $structure)) {
            throw $this->createAccessDeniedException();
        }

        return $structure;
    }
}
