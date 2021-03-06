<?php

namespace App\Facade\Resource;

use Bundles\ApiBundle\Annotation as Api;
use Bundles\ApiBundle\Contracts\FacadeInterface;

class StructureResourceFacade extends ResourceFacade
{
    public function __construct()
    {
        $this->type = self::TYPE_STRUCTURE;
    }

    public static function getExample(Api\Facade $decorates = null) : FacadeInterface
    {
        $facade = new self;

        $facade->externalId = 'demo-structure';
        $facade->label      = 'Paris';

        return $facade;
    }
}