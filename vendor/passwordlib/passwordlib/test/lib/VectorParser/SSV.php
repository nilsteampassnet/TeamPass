<?php

namespace PasswordLibTest\lib\VectorParser;

class SSV {
    
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
        foreach ($data as $line) {
            $line = trim($line);
            if (empty($line) || $line[0] == '#') {
                continue;
            }
            $this->vectors[] = explode(' ', $line);
        }
    }
    
}