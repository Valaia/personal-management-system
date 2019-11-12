<?php

namespace App\Controller\Modules\Contacts2;

use App\Controller\Utils\Application;
use App\Controller\Utils\Repositories;
use App\Controller\Utils\Utils;
use App\DTO\Modules\Contacts\ContactsTypesDTO;
use App\DTO\Modules\Contacts\ContactTypeDTO;
use App\Entity\Modules\Contacts2\MyContact;
use App\Form\Modules\Contacts2\MyContactType;
use App\Form\Modules\Contacts2\MyContactTypeDtoType;
use App\Form\Modules\Contacts2\MyContactTypeType;
use App\Services\Exceptions\ExceptionDuplicatedTranslationKey;
use Doctrine\DBAL\DBALException;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\ORMException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class MyContactsController extends AbstractController {

    const TWIG_TEMPLATE = 'modules/my-contacts-2/my-contacts.html.twig';

    const KEY_CONTACTS    = 'contacts';
    const KEY_AJAX_RENDER = 'ajax_render';
    const KEY_TYPE        = 'type';

    /**
     * @var Application $app
     */
    private $app;

    public function __construct(Application $app) {
        $this->app = $app;
    }

    /**
     * @Route("/my-contacts-2", name="my-contacts-2")
     * @param Request $request
     * @return Response
     * @throws \Exception
     */
    public function display(Request $request) {

        $this->handleForms($request);

        if (!$request->isXmlHttpRequest()) {
            return $this->renderTemplate( false);
        }
        return $this->renderTemplate( true);
    }

    protected function renderTemplate($ajax_render = false) {

        $contacts = $this->app->repositories->myContactRepository->findAllNotDeleted();

        $data = [
            self::KEY_AJAX_RENDER   => $ajax_render,
            self::KEY_CONTACTS      => $contacts
        ];

        return $this->render(self::TWIG_TEMPLATE, $data);
    }

    /**
     * @Route("/my-contacts-2/remove", name="my-contacts-2-remove")
     * @param Request $request
     * @return Response
     * @throws \Exception
     */
    public function remove(Request $request) {

        $response = $this->app->repositories->deleteById(
            Repositories::MY_CONTACT_REPOSITORY,
            $request->request->get('id')
        );

        if ($response->getStatusCode() == 200) {
            return $this->renderTemplate(true);
        }
        return $response;
    }

    /**
     * @Route("my-contacts-2/update" ,name="my-contacts-2-update")
     * @param Request $request
     * @return JsonResponse
     * @throws ExceptionDuplicatedTranslationKey
     */
    public function update(Request $request) {
        $parameters = $request->request->all();
        $entity     = $this->app->repositories->myContactRepository->find($parameters['id']);
        $response   = $this->app->repositories->update($parameters, $entity);

        return $response;
    }

    /**
     * @param Request $request
     * @throws DBALException
     * @throws ORMException
     * @throws OptimisticLockException
     */
    public function handleForms(Request $request){

        $contact_type_form_prefix   = Utils::formClassToFormPrefix(MyContactType::class);
        $my_contact_type_form       = $request->request->get($contact_type_form_prefix);
        $forms                      = $request->request->all();
        $filtered_types_forms       = Utils::filterRequestForms([$contact_type_form_prefix], $forms);

        // build request for processing the main form
        $request = new Request();
        $request->request->set($contact_type_form_prefix, $my_contact_type_form);
        $contact_form = $this->app->forms->contact()->handleRequest($request);
        $contact_form->submit($my_contact_type_form);

        if( $contact_form->isSubmitted() && $contact_form->isValid() ){

            $array_of_contacts_types_dtos = [];

            // Build contacts from all passed in forms

            foreach( $filtered_types_forms as $uuid => $form ){

                if( !array_key_exists(MyContactTypeDtoType::KEY_NAME, $form) ){
                    $message = '';
                    throw new \Exception($message);
                }elseif( !array_key_exists(MyContactTypeDtoType::KEY_TYPE, $form) ){
                    $message = '';
                    throw new \Exception($message);
                }

                $type_details   = $form[MyContactTypeDtoType::KEY_NAME];
                $type_id        = $form[MyContactTypeDtoType::KEY_TYPE];

                $icon_path   = $this->app->repositories->myContactTypeRepository->getImagePathForTypeById($type_id);
                $type_name   = $this->app->repositories->myContactTypeRepository->getTypeNameTypeById($type_id);

                if( empty($icon_path) ){
                    $message = '';
                    throw new \Exception($message);
                }

                $contact_type_dto = new ContactTypeDTO();
                $contact_type_dto->setDetails($type_details);
                $contact_type_dto->setName($type_name);
                $contact_type_dto->setIconPath($icon_path);
                $contact_type_dto->setUuid($uuid);

                $array_of_contacts_types_dtos[] = $contact_type_dto;

            }

            $contacts_types_dto = new ContactsTypesDTO();
            $contacts_types_dto->setContactTypeDtos($array_of_contacts_types_dtos);
            $contacts_json = $contacts_types_dto->toJson();

            /**
             * @var MyContact $my_contact
             */
            $my_contact = $contact_form->getData();

            if( !$my_contact instanceof MyContact ){
                $message = '';
                throw new \Exception($message);
            }

            $my_contact->setContacts($contacts_json);

            $this->app->repositories->myContactRepository->saveEntity($my_contact );
        }


    }

}