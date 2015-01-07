<?php
/**
 * An abstract mixer to implement a common mixing strategy
 *
 * PHP version 5.3
 *
 * @category  PHPPasswordLib
 * @package   Random
 * @author    Anthony Ferrara <ircmaxell@ircmaxell.com>
 * @copyright 2011 The Authors
 * @license    http://www.opensource.org/licenses/mit-license.html  MIT License
 * @version   Build @@version@@
 */

namespace PasswordLib\Random;

/**
 * An abstract mixer to implement a common mixing strategy
 *
 * @see      http://tools.ietf.org/html/rfc4086#section-5.2
 * @category PHPPasswordLib
 * @package  Random
 * @author   Anthony Ferrara <ircmaxell@ircmaxell.com>
 */
abstract class AbstractMixer implements \PasswordLib\Random\Mixer {

    /**
     * Get the block size (the size of the individual blocks used for the mixing)
     * 
     * @return int The block size
     */
    abstract protected function getPartSize();

    /**
     * Mix 2 parts together using one method
     *
     * @param string $part1 The first part to mix
     * @param string $part2 The second part to mix
     * 
     * @return string The mixed data
     */
    abstract protected function mixParts1($part1, $part2);

    /**
     * Mix 2 parts together using another different method
     *
     * @param string $part1 The first part to mix
     * @param string $part2 The second part to mix
     * 
     * @return string The mixed data
     */
    abstract protected function mixParts2($part1, $part2);

    /**
     * Mix the provided array of strings into a single output of the same size
     *
     * All elements of the array should be the same size.
     *
     * @param array $parts The parts to be mixed
     *
     * @return string The mixed result
     */
    public function mix(array $parts) {
        if (empty($parts)) {
            return '';
        }
        $len        = strlen($parts[0]);
        $parts      = $this->normalizeParts($parts);
        $stringSize = count($parts[0]);
        $partsSize  = count($parts);
        $result     = '';
        $offset     = 0;
        for ($i = 0; $i < $stringSize; $i++) {
            $stub = $parts[$offset][$i];
            for ($j = 1; $j < $partsSize; $j++) {
                $newKey = $parts[($j + $offset) % $partsSize][$i];
                //Alternately mix the output for each source
                if ($j % 2 == 1) {
                    $stub ^= $this->mixParts1($stub, $newKey);
                } else {
                    $stub ^= $this->mixParts2($stub, $newKey);
                }
            }
            $result .= $stub;
            $offset  = ($offset + 1) % $partsSize;
        }
        return substr($result, 0, $len);
    }

    /**
     * Normalize the part array and split it block part size.  
     * 
     * This will make all parts the same length and a multiple
     * of the part size
     *
     * @param array $parts The parts to normalize
     * 
     * @return array The normalized and split parts
     */
    protected function normalizeParts(array $parts) {
        $blockSize = $this->getPartSize();
        $callback  = function($value) {
            return strlen($value);
        };
        $maxSize = max(array_map($callback, $parts));
        if ($maxSize % $blockSize != 0) {
            $maxSize += $blockSize - ($maxSize % $blockSize);
        }
        foreach ($parts as &$part) {
            $part = str_pad($part, $maxSize, chr(0));
            $part = str_split($part, $blockSize);
        }
        return $parts;
    }
}
