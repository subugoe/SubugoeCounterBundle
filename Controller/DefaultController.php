<?php

namespace Subugoe\CounterBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;

class DefaultController extends Controller
{
    public function indexAction()
    {
        return $this->render('SubugoeCounterBundle:Default:index.html.twig');
    }
}
