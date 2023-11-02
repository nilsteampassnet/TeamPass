<?php

namespace PasswordLibTest\lib\VectorParser;

class CAVS {

    protected $file = '';

    protected $vectors = array();

    public function __construct($file) {
        $this->file = $file;
        $this->parse();
    }

    public function getVectors() {
        return $this->vectors;
    }

    protected function parse() {
        $data = file($this->file);
        $bufferValid = false;
        $buffer = array();
        foreach ($data as $line) {
            $line = trim($line);
            if (empty($line)) {
                if ($bufferValid) {
                    $this->processBuffer($buffer);
                    $buffer = array();
                    $bufferValid = false;
                }
            } elseif ($line[0] == '#') {
                continue;
            } elseif ($line[0] == '[') {
                list ($key, $value) = explode('=', substr($line, 1, -1), 2);
                $buffer[trim($key)] = trim($value);
            } elseif (substr($line, 0, 5) == 'Count') {
                $bufferValid = true;
            } else {
                list ($key, $value) = explode('=', $line, 2);
                $buffer[trim($key)] = trim($value);
            }
        }
    }

    protected function processBuffer(array $buffer) {
        $this->vectors[] = $buffer;
    }
}