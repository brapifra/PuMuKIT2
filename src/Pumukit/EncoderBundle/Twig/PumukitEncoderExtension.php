<?php

namespace Pumukit\EncoderBundle\Twig;

use Doctrine\ODM\MongoDB\DocumentManager;
use Symfony\Component\Translation\TranslatorInterface;

class PumukitEncoderExtension extends \Twig_Extension
{
    private $dm;
    private $translator;

    /**
     * Constructor
     */
    public function __construct(DocumentManager $documentManager, TranslatorInterface $translator)
    {
        $this->dm = $documentManager;
        $this->translator = $translator;
    }

    /**
     * Get name.
     */
    public function getName()
    {
        return 'pumukitencoder_extension';
    }

    /**
     * Get filters.
     */
    public function getFilters()
    {
        return array(
                     new \Twig_SimpleFilter('multimediaobject_title', array($this, 'getMultimediaObjectTitle')),
                     );
    }

    public function getMultimediaObjectTitle($id='')
    {
        if ($id) {
            $multimediaObject = $this->dm->getRepository('PumukitSchemaBundle:MultimediaObject')->find($id);
            return $multimediaObject->getTitle();
        }

        return $this->translator->trans('No title');
    }
}