<?php

namespace AppBundle\Form;

use AppBundle\Entity\ApiApp;
use AppBundle\Entity\Store;
use Doctrine\ORM\EntityRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormError;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Constraints;
use Trikoder\Bundle\OAuth2Bundle\Model\Client;
use Trikoder\Bundle\OAuth2Bundle\Model\Grant;
use Trikoder\Bundle\OAuth2Bundle\Model\Scope;
use Trikoder\Bundle\OAuth2Bundle\OAuth2Grants;

class ApiAppType extends AbstractType
{
    public function __construct()
    {
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'form.api_app.name.label',
            ]);
        $builder
            ->add('type', ChoiceType::class, [
                'label' => 'form.api_app.type.label',
                'choices' => [
                    'form.api_app.type.oauth.label' => 'oauth',
                    'form.api_app.type.api_key.label' => 'api_key',
                ],
            ]);

        $builder->addEventListener(FormEvents::POST_SET_DATA, function (FormEvent $event) {

            $form = $event->getForm();
            $apiApp = $event->getData();

            $storeOptions = [
                'class' => Store::class,
                'query_builder' => function (EntityRepository $repository) {
                    return $repository->createQueryBuilder('s')
                        ->orderBy('s.name', 'ASC');
                },
                'label' => 'form.api_app.store.label',
                'choice_label' => 'name',
            ];

            if (null !== $apiApp->getId()) {

                $type = $apiApp->getType();

                switch ($type) {
                    case 'api_key':
                        $form->add('apiKey', TextType::class, [
                            'label' => 'form.api_app.client_id.label',
                            'data' => 'ak_' . $apiApp->getApiKey(),
                            'disabled' => true,
                            'mapped' => false,
                        ]);
                        break;
                    case 'oauth':
                    default:
                        $form->add('client_id', TextType::class, [
                            'label' => 'form.api_app.client_id.label',
                            'data' => $apiApp->getOauth2Client()->getIdentifier(),
                            'disabled' => true,
                            'mapped' => false,
                        ]);
                        $form->add('client_secret', TextType::class, [
                            'label' => 'form.api_app.client_secret.label',
                            'data' => $apiApp->getOauth2Client()->getSecret(),
                            'disabled' => true,
                            'mapped' => false,
                        ]);
                        break;
                }

                $storeOptions['disabled'] = true;
            }

            $form->add('store', EntityType::class, $storeOptions);
        });

        $builder->addEventListener(FormEvents::SUBMIT, function (FormEvent $event) {

            $form = $event->getForm();
            $apiApp = $event->getData();

            $identifier = hash('md5', random_bytes(16));
            $secret = hash('sha512', random_bytes(32));

            $client = new Client($identifier, $secret);
            $client->setActive(true);

            $tasksScope = new Scope('tasks');
            $deliveriesScope = new Scope('deliveries');
            $client->setScopes($tasksScope, $deliveriesScope);

            switch ($apiApp->getType()) {
                case 'api_key':
                    $key = hash('sha1', random_bytes(32));
                    $apiApp->setApiKey($key);
                    break;
                case 'oauth':
                default:
                    $clientCredentials = new Grant(OAuth2Grants::CLIENT_CREDENTIALS);
                    $client->setGrants($clientCredentials);
                    break;
            }

            $apiApp->setOauth2Client($client);
        });
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(array(
            'data_class' => ApiApp::class,
        ));
    }
}
