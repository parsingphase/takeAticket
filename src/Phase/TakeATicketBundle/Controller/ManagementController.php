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
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
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

        return $this->render(
            'default/manage.html.twig',
            [
                'tickets' => $tickets,
                'performers' => $performers,
                'displayOptions' => $this->getDisplayOptions(),
            ]
        );
    }


    public function helpAction($section = 'readme')
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

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
            'remotesUrl' => '/'
        ];

        $formDefaults = $settingKeys;

        foreach ($settingKeys as $k => $v) {
            $value = $this->getDataStore()->getSetting($k);
            if (!is_null($value)) {
                $formDefaults[$k] = is_bool($v) ? (bool)$value : $value; // fixme handle type better
            }
        }

        $formFactory = Forms::createFormFactoryBuilder()
            ->addExtension(new HttpFoundationExtension())
            ->getFormFactory();

        $settingsSubmit = 'Save Settings';
        $settingsForm = $formFactory->createNamedBuilder('settingsForm', FormType::class, $formDefaults)
            ->add('freeze', CheckboxType::class, ['label' => 'Display "Queue Frozen" message'])
            ->add('freezeMessage', TextType::class, ['label' => 'Customise "Queue Frozen" message'])
            ->add('remotesUrl', TextType::class, ['label' => 'URL to display on remote screens'])
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
                foreach ($data as $k => $v) {
                    $this->getDataStore()->updateSetting($k, $v);
                }
                $settingsFormSaved = true;
            }
        }

        // ----------------

        $resetSubmit = 'Reset all';
        $resetForm = $formFactory->createNamedBuilder('resetForm', FormType::class, $formDefaults)
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
                //                var_dump($data);
                //                die();
                if (trim($data['resetMessage']) === $requiredResetText) {
                    $this->getDataStore()->resetAllSessionData();
                    $resetFormSaved = true;
                }
            }
        }

        // -------------------

        $songListSubmit = 'Upload song list';
        $songListForm = $formFactory->createNamedBuilder('songListForm', FormType::class, $formDefaults)
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

                $songsLoaded = $loader->run($file->getPathname(), $this->get('database_connection'));

                $songFormSaved = true;
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
            ]
        );
    }
}
