<?php

namespace App\Service;

use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class StateChangeFormValidationService
{
    public function __construct(
        private readonly FormFactoryInterface $formFactory,
        private readonly RequestStack $requestStack,
        private readonly UrlGeneratorInterface $urlGenerator,
    )
    {
    }

    /**
     * Create a small form for the purpose of validating state change (e.g. delete, restore) forms.
     * @param {string} $routeName - name of the route to submit to (e.g. the action)
     * @param {array} $params - parameters to pass to the route
     * @param {string} $csrfTokenId - manually specify the CSRF token ID, otherwise use Symfony form defaults
     * @return {Form} form to attach to the template
     */
    public function createStateChangeForm(string $routeName, array $params, ?string $csrfTokenId = null): FormInterface
    {
        $formData = [
            'method' => 'POST',
            'action' => $this->urlGenerator->generate($routeName, $params),
        ];

        if ($csrfTokenId !== null) {
            $formData['csrf_token_id'] = $csrfTokenId;
        }

        $builder = $this->formFactory->createBuilder(FormType::class, null, $formData);
        return $builder->getForm();
    }

    /**
     * Rather than manually assigning and verifying CSRF tokens, create a small form and let
     * Symfony handle form validation.
     * @param {string} $csrfTokenId - manually specify the CSRF token ID, otherwise use Symfony form defaults
     * @return true if the form is valid, false otherwise
     */
    public function isStateChangeValid(Request $request, ?string $csrfTokenId = null): bool
    {
        $formData = [
            'method' => 'POST',
        ];

        if ($csrfTokenId !== null) {
            $formData['csrf_token_id'] = $csrfTokenId;
        }

        $builder = $this->formFactory->createBuilder(FormType::class, null, $formData);

        $form = $builder->getForm();

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            return true;
        }

        $this->requestStack->getSession()->getFlashBag()->add('danger', 'Invalid request.');
        return false;
    }
}
