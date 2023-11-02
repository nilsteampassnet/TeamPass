<?php

namespace GibberishAES;

class GibberishAESTest extends \PHPUnit\Framework\TestCase
{
    protected $examples = array(
        "Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam nonumy eirmod tempor invidunt ut labore et dolore magna aliquyam erat, sed diam voluptua. At vero eos et accusam et justo duo dolores et ea rebum. Stet clita kasd gubergren, no sea takimata sanctus est Lorem ipsum dolor sit amet. Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam nonumy eirmod tempor invidunt ut labore et dolore magna aliquyam erat, sed diam voluptua. At vero eos et accusam et justo duo dolores et ea rebum. Stet clita kasd gubergren, no sea takimata sanctus est Lorem ipsum dolor sit amet."
    );

    protected $passPhrase = "C@!e?M>/U}[]r-!qnuQ_.n?K%-At9^j^-9FkehyYB68#fb!";

    public function testEncryptionWithDefaultKeySize()
    {
        foreach ($this->examples as $example) {
            $encrypted = GibberishAES::enc($example, $this->passPhrase);

            $this->assertEquals($example, GibberishAES::dec($encrypted, $this->passPhrase));
        }
    }

    public function testEncryptionWith128BitKeySize()
    {
        GibberishAES::size(128);

        foreach ($this->examples as $example) {
            $encrypted = GibberishAES::enc($example, $this->passPhrase);

            $this->assertEquals($example, GibberishAES::dec($encrypted, $this->passPhrase));
        }
    }

    public function testEncryptionWith192BitKeySize()
    {
        GibberishAES::size(192);

        foreach ($this->examples as $example) {
            $encrypted = GibberishAES::enc($example, $this->passPhrase);

            $this->assertEquals($example, GibberishAES::dec($encrypted, $this->passPhrase));
        }
    }

    public function testEncryptionWith256BitKeySize()
    {
        GibberishAES::size(256);

        foreach ($this->examples as $example) {
            $encrypted = GibberishAES::enc($example, $this->passPhrase);

            $this->assertEquals($example, GibberishAES::dec($encrypted, $this->passPhrase));
        }
    }
}