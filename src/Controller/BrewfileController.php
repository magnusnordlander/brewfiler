<?php
declare(strict_types=1);

namespace App\Controller;


use App\Entity\Brew;
use App\Form\BrewMetaType;
use League\Csv\Reader;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Finder\Finder;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class BrewfileController extends AbstractController
{
    /**
     * @param string $name
     * @Route("/", name="list_brews")
     */
    public function listAction(): Response
    {
        $finder = new Finder();
        $finder->files()
            ->in('/Users/magnus/brews/')
            ->name("brew_*.meta_v1.json")
            ->sortByName()
        ;

        $brews = [];
        foreach($finder as $file) {
            if (preg_match("/brew_(\d+\.\d+)\.meta_v1\.json/", $file->getFilename(), $matches)) {
                $brews[] = new Brew($matches[1], $file->getRealPath(), null);
            }
        }

        return $this->render("list.html.twig", ['brews' => $brews]);
    }

    /**
     * @param string $name
     * @Route("/{id}", name="show_brew")
     */
    public function showAction(Request $request, string $id): Response
    {
        $brew = new Brew($id, "/Users/magnus/brews/brew_".$id.".meta_v1.json", "/Users/magnus/brews/brew_".$id.".brewfile_v1.csv");

        $form = $this->createForm(BrewMetaType::class, $brew);

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $brew->updateMeta();
        }

        return $this->render("show.html.twig", ['brew' => $brew, 'form' => $form->createView()]);
    }
}
