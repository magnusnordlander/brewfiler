<?php
declare(strict_types=1);

namespace App\Controller;


use App\Entity\Brew;
use App\Form\BrewMetaType;
use App\Repository\BrewRepository;
use League\Csv\Reader;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Finder\Finder;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class BrewfileController extends AbstractController
{
    /**
     * @var BrewRepository
     */
    private $repository;

    public function __construct(BrewRepository $repository)
    {
        $this->repository = $repository;
    }

    /**
     * @param string $name
     * @Route("/", name="list_brews")
     */
    public function listAction(Request $request): Response
    {
        $page = $request->query->get('page', 1);

        $perPage = 10;
        $offset = ($page-1)*$perPage;

        $brews = $this->repository->findAll($offset, $perPage);

        return $this->render("list.html.twig", ['brews' => $brews, 'page' => $page]);
    }

    /**
     * @param string $name
     * @Route("/{id}", name="show_brew", methods={"GET", "POST"})
     */
    public function showAction(Request $request, string $id): Response
    {
        $brew = $this->repository->find($id);

        if (!$brew) {
            throw $this->createNotFoundException();
        }

        $form = $this->createForm(BrewMetaType::class, $brew);

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $brew->updateMeta();
        }

        return $this->render("show.html.twig", ['brew' => $brew, 'form' => $form->createView()]);
    }

    /**
     * @param string $name
     * @Route("/{id}", name="delete_brew", methods={"DELETE"})
     */
    public function deleteAction(string $id): Response
    {
        $this->repository->delete($id);

        return new RedirectResponse($this->generateUrl('list_brews'));
    }
}
