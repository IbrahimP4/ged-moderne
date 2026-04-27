<?php

declare(strict_types=1);

namespace App\UI\Http\Controller\Security;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;

final class LoginController extends AbstractController
{
    public function __invoke(): Response
    {
        throw new \LogicException('This code should never be reached.');
    }
}
