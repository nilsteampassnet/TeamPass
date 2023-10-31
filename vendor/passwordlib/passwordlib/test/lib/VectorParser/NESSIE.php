<?php

namespace PasswordLibTest\lib\VectorParser;

class NESSIE {
    
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
            if (isset($line[0]) && $line[0] != ' ' && $line[0] != "\t") {
                if ($bufferValid) {
                    $this->processBuffer($buffer);
                }
                $buffer = array();
                $bufferValid = substr($line, 0, 3) == 'Set';
            }
            $buffer[] = $line;
        }
    }
    
    protected function processBuffer(array $lines) {
        $set = array_shift($lines);
        $key = '';
        $buffer = '';
        $record = array();
        foreach ($lines as $row) {
            $row = trim($row);
            if (strpos($row, '=') !== false) {
                if ($key) {
                    $record[$key] = $buffer;
                }
                list($key, $buffer) = explode('=', $row, 2);
            } else {
                $buffer .= $row;
            }
        }
        $record[$key] = $buffer;
        $this->vectors[] = $record;
    }
}