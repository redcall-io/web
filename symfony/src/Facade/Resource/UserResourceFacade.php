<?php

namespace App\Facade\Resource;

use Bundles\ApiBundle\Annotation as Api;
use Bundles\ApiBundle\Contracts\FacadeInterface;

class UserResourceFacade extends ResourceFacade
{
    public function __construct()
    {
        $this->type = self::TYPE_USER;
    }

    public static function getExample(Api\Facade $decorates = null) : FacadeInterface
    {
        $facade = new self;

        $facade->externalId = 'demo@example.com';
        $facade->label      = 'John Doe';

        return $facade;
    }
}