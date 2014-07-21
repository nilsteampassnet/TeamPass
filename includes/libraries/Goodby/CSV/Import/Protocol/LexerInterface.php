<?php

namespace Goodby\CSV\Import\Protocol;

use Goodby\CSV\Import\Protocol\Exception\CsvFileNotFoundException;
require $_SESSION['settings']['cpassman_dir']."/includes/libraries/Goodby/CSV/Import/Protocol/Exception/CsvFileNotFoundException.php";

/**
 * Interface of Lexer
 */
interface LexerInterface
{
    /**
     * Parse csv file
     * @param string $filename
     * @param InterpreterInterface $interpreter
     * @return boolean
     * @throws CsvFileNotFoundException
     */
    public function parse($filename, InterpreterInterface $interpreter);
}
