<?php

namespace PasswordLibTest\lib\VectorParser;

class RFC3610 {
    
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
                $this->processBuffer($buffer);
                $buffer = array();
            } elseif ($line[0] == '=' || strpos($line, ':') !== false) {
                continue;
            } else {
                list ($key, $value) = explode('=', $line, 2);
                $buffer[trim($key)] = trim($value);
            }
        }
    }
    
    protected function processBuffer(array $buffer) {
        $vector = $buffer;
        $adl = strlen($vector['Adata']);
        $vector['Cipher'] = substr($vector['Cipher'], $adl);
        $vector['Data'] = substr($vector['Data'], $adl);
        $this->vectors[] = $vector;
    }
}