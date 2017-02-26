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
        $backgroundFilename = $this->getDataStore()->fetchSetting('backgroundFilename');
        $customCss = $this->getDataStore()->fetchSetting('customCss');
        $backgroundFullPath = dirname($this->get('kernel')->getRootDir()) . '/web/uploads/' . $backgroundFilename;

        $backgroundUrl = null;
        if ($backgroundFilename && file_exists($backgroundFullPath)) {
            $backgroundUrl = '/uploads/' . $backgroundFilename;
        }

        $headers = ['Content-type' => 'text/css'];

        $data = '';

        if ($backgroundUrl) {
            $data .= "body {\n\tbackground-image: url('$backgroundUrl');\n\tbackground-size: cover;\n}\n";
        }

        $data .= $customCss;

        return new Response($data, 200, $headers);
    }
}
