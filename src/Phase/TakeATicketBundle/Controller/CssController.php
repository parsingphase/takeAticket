<?php
/**
 * Created by PhpStorm.
 * User: wechsler
 * Date: 24/02/2017
 * Time: 20:39
 */

namespace Phase\TakeATicketBundle\Controller;

use Symfony\Component\HttpFoundation\Response;

class CssController extends BaseController
{
    public function customCssAction()
    {
        $backgroundFilename = $this->getDataStore()->getSetting('backgroundFilename');
        $customCss = $this->getDataStore()->getSetting('customCss');
        $backgroundFullPath = dirname($this->get('kernel')->getRootDir()) . '/web/uploads/' . $backgroundFilename;
        $backgroundUrl = ($backgroundFilename && file_exists($backgroundFullPath)) ? '/uploads/' . $backgroundFilename : null;

        $headers = ['Content-type' => 'text/css'];

        $data = '';

        if ($backgroundUrl) {
            $data .= "body {\n\tbackground-image: url('$backgroundUrl');\n\tbackground-size: cover;\n}\n";
        }

        $data .= $customCss;

        return new Response($data, 200, $headers);

    }
}