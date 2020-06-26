<?php
declare(strict_types=1);

namespace App\Controller;


use League\Csv\Reader;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class BrewfileController extends AbstractController
{
    /**
     * @param string $name
     * @Route("/{name}")
     */
    public function showAction(string $name): Response
    {
        $metaFile = file_get_contents("/Users/magnus/brews/brew_".$name.".meta_v1.json");
        $meta = json_decode($metaFile, true);

        $data = Reader::createFromPath("/Users/magnus/brews/brew_".$name.".brewfile_v1.csv");
        $data->setHeaderOffset(0);
        $recs = iterator_to_array($data->getRecords());

        return $this->render("show.html.twig", ['data' => $recs, 'meta' => $meta]);
    }
}
