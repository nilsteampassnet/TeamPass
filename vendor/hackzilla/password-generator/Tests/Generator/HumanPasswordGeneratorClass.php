<?php

namespace Hackzilla\PasswordGenerator\Tests\Generator;

use Hackzilla\PasswordGenerator\Generator\HumanPasswordGenerator;

class HumanPasswordGeneratorClass extends HumanPasswordGenerator
{
    public function generateWordList()
    {
        return array();
    }
}
