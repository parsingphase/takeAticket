<?php
/**
 * Created by PhpStorm.
 * User: wechsler
 * Date: 28/01/2017
 * Time: 19:12
 */

namespace Phase\TakeATicketBundle\Controller;

use Phase\TakeATicket\SongLoader;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\HttpFoundation\HttpFoundationExtension;
use Symfony\Component\Form\Forms;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ManagementController extends BaseController
{
    public function indexAction()
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $tickets = $this->getDataStore()->fetchUndeletedTickets();

        $performers = $this->getDataStore()->generatePerformerStats();

        $viewParams = $this->defaultViewParams();

        $viewParams += [
            'tickets' => $tickets,
            'performers' => $performers
        ];

        return $this->render(
            'default/manage.html.twig',
            $viewParams
        );
    }


    public function helpAction($section = 'readme')
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        /** @noinspection RealpathInSteamContextInspection */
        $rootDir = realpath(__DIR__ . '/../../../../');
        $map = [
            'readme' => $rootDir . '/README.md',
            'CONTRIBUTING' => $rootDir . '/docs/CONTRIBUTING.md',
            'TODO' => $rootDir . '/docs/TODO.md',
        ];

        if (!isset($map[$section])) {
            throw new NotFoundHttpException();
        }

        $markdown = file_get_contents($map[$section]);

        $markdown = preg_replace(
            '#\[docs/\w+.md\]\((./)?docs/(\w+).md\)#',
            '[docs/$2.md](/help/$2)',
            $markdown
        );

        return $this->render(
            ':default:help.html.twig',
            ['helpText' => $markdown]
        );
    }

    public function settingsAction(Request $request)
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $requiredResetText = 'THIS DELETES ALL TICKETS';

        $settingKeys = [
            'freeze' => false,
            'freezeMessage' => '',
            'remotesUrl' => '/',
            'upcomingCount' => 3,
            'songInPreview' => false,
            'selfSubmission' => false
        ];

        $formDefaults = $settingKeys;

        $dataStore = $this->getDataStore();

        foreach ($settingKeys as $key => $default) {
            $value = $dataStore->fetchSetting($key);
            if (is_null($value)) {
                $value = $default;
            } else {
                switch (gettype($default)) {
                    case 'integer':
                        $value = (int)$value;
                        break;
                    case 'boolean':
                        $value = (bool)$value;
                        break;
                }
            }
            $formDefaults[$key] = $value; // fixme handle type better
        }

        $formFactory = Forms::createFormFactoryBuilder()
            ->addExtension(new HttpFoundationExtension())
            ->getFormFactory();

        $settingsSubmit = 'Save Settings';
        $settingsForm = $formFactory->createNamedBuilder('settingsForm', FormType::class, $formDefaults)
            ->add(
                'freeze',
                CheckboxType::class,
                ['label' => 'Display "Queue Frozen" message', 'required' => false]
            )
            ->add(
                'freezeMessage',
                TextType::class,
                ['label' => 'Customise "Queue Frozen" message', 'required' => false]
            )
            ->add(
                'remotesUrl',
                TextType::class,
                ['label' => 'URL to display on remote screens', 'required' => false]
            )
            ->add(
                'upcomingCount',
                NumberType::class,
                ['label' => 'Upcoming songs to display', 'required' => false]
            )
            ->add(
                'songInPreview',
                CheckboxType::class,
                ['label' => 'Display song titles on public queue', 'required' => false]
            )
            ->add(
                'selfSubmission',
                CheckboxType::class,
                ['label' => 'Enable self-submission', 'required' => false]
            )
            ->add($settingsSubmit, SubmitType::class)
            ->getForm();

        $settingsFormSaved = false;

        if ($request->request->has('settingsForm')) {
            $settingsForm->handleRequest($request);

            /**
             * @noinspection PhpUndefinedMethodInspection
             */ // isClicked on Submit
            if ($settingsForm->isSubmitted()
                && $settingsForm->isValid()
                && $settingsForm->get($settingsSubmit)->isClicked()
            ) {
                $data = $settingsForm->getData();
                foreach ($data as $key => $default) {
                    $dataStore->updateSetting($key, $default);
                }
                $settingsFormSaved = true;
            }
        }

        // ----------------

        $resetSubmit = 'Reset all';
        $resetForm = $formFactory->createNamedBuilder('resetForm', FormType::class)
            ->add('resetMessage', TextType::class)
            ->add($resetSubmit, SubmitType::class)
            ->getForm();


        $resetFormSaved = false;

        if ($request->request->has('resetForm')) {
            $resetForm->handleRequest($request);

            /**
             * @noinspection PhpUndefinedMethodInspection
             */ // isClicked on Submit
            if ($resetForm->isSubmitted()
                && $resetForm->isValid()
                && $resetForm->get($resetSubmit)->isClicked()
            ) {
                $data = $resetForm->getData();
                if (trim($data['resetMessage']) === $requiredResetText) {
                    $dataStore->resetAllSessionData();
                    $resetFormSaved = true;
                }
            }
        }

        // -------------------

        $rowMapperManager = $this->container->get('songloader.rowmappermanager');
        /** @var  SongLoader\RowMapperManager $rowMapperManager */
        $mappers = $rowMapperManager->getRowMappers();
        $mapperInput = [];
        foreach ($mappers as $mapper) {
            $mapperInput[$mapper->getFormatterName()] = $mapper->getShortName();
        }

        $songListSubmit = 'Upload song list';
        $songListForm = $formFactory->createNamedBuilder('songListForm', FormType::class)
            ->add('rowMapper', ChoiceType::class, ['choices' => $mapperInput, 'label' => 'Input formatter'])
            ->add('songListFile', FileType::class)
            ->add($songListSubmit, SubmitType::class)
            ->getForm();

        $songFormSaved = false;
        $songsLoaded = false;

        if ($request->request->has('songListForm')) {
            $songListForm->handleRequest($request);

            /**
             * @noinspection PhpUndefinedMethodInspection
             */ // isClicked on Submit
            if ($songListForm->isSubmitted()
                && $songListForm->isValid()
                && $songListForm->get($songListSubmit)->isClicked()
            ) {
                $data = $songListForm->getData();

                $file = $data['songListFile'];
                /**
                 * @var UploadedFile $file
                 */

                $loader = new SongLoader();
                $loader->setRowMapperClass($rowMapperManager->getRowMapperClassByShortName($data['rowMapper']));
                $songsLoaded = $loader->run($file->getPathname(), $this->get('database_connection'));

                $songFormSaved = true;
            }
        }

        // -------------------

        $defaults = ['customCss' => $dataStore->fetchSetting('customCss')];

        $stylingSubmit = 'Update styles';
        $stylingForm = $formFactory->createNamedBuilder('stylingForm', FormType::class, $defaults)
            ->add('backgroundImageFile', FileType::class, ['label' => 'New background image'])
            ->add('customCss', TextareaType::class, ['attr' => ['rows' => 8, 'cols' => 60, 'label' => 'Custom CSS']])
            ->add($stylingSubmit, SubmitType::class)
            ->getForm();

        $styleFormSaved = false;
        $backgroundUpdated = false;

        if ($request->request->has('stylingForm')) {
            $stylingForm->handleRequest($request);

            /**
             * @noinspection PhpUndefinedMethodInspection
             */ // isClicked on Submit
            if ($stylingForm->isSubmitted()
                && $stylingForm->isValid()
                && $stylingForm->get($stylingSubmit)->isClicked()
            ) {
                $data = $stylingForm->getData();

                $file = $data['backgroundImageFile'];
                if ($file) {
                    $mimeType = $file->getMimeType();
                    $pathName = $file->getPathName();
                    /**
                     * @var UploadedFile $file
                     */
                    $suffixByMimeType = [
                        'image/jpeg' => 'jpg',
                        'image/gif' => 'gif',
                        'image/png' => 'png',
                    ];

                    if (array_key_exists($mimeType, $suffixByMimeType)) {
                        $suffix = $suffixByMimeType[$mimeType];
                        $targetFile = 'background.' . $suffix;
                        $destination = dirname($this->get('kernel')->getRootDir()) . '/web/uploads/' . $targetFile;
                        move_uploaded_file($pathName, $destination);
                        $dataStore->updateSetting('backgroundFilename', $targetFile);
                    } else {
                        throw new \UnexpectedValueException("Invalid mimetype '$mimeType'");
                    }
                    $backgroundUpdated = true;
                }

                $dataStore->updateSetting('customCss', $data['customCss']);

                $styleFormSaved = true;
            }
        }

        // -------------------

        return $this->render(
            ':admin:settings.html.twig',
            [
                'settingsFormSaved' => $settingsFormSaved,
                'settingsForm' => $settingsForm->createView(),
                'resetForm' => $resetForm->createView(),
                'resetFormSaved' => $resetFormSaved,
                'resetRequiredText' => $requiredResetText,
                'songListForm' => $songListForm->createView(),
                'songFormSaved' => $songFormSaved,
                'songsLoaded' => $songsLoaded,
                'stylingForm' => $stylingForm->createView(),
                'styleFormSaved' => $styleFormSaved,
                'backgroundUpdated' => $backgroundUpdated,
            ]
        );
    }
}
