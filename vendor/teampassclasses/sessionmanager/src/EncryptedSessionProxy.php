<?php
namespace TeampassClasses\SessionManager;

/**
 * Teampass - a collaborative passwords manager.
 * ---
 * This file is part of the TeamPass project.
 * 
 * TeamPass is free software: you can redistribute it and/or modify it
 * under the terms of the GNU General Public License as published by
 * the Free Software Foundation, version 3 of the License.
 * 
 * TeamPass is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 * 
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 * 
 * Certain components of this file may be under different licenses. For
 * details, see the `licenses` directory or individual file headers.
 * ---
 * @file      EncryptedSessionProxy.php
 * @author    Nils Laumaillé (nils@teampass.net)
 * @copyright 2009-2024 Teampass.net
 * @license   GPL-3.0
 * @see       https://www.teampass.net
 */

use Defuse\Crypto\Crypto;
use Defuse\Crypto\Key;
use Symfony\Component\HttpFoundation\Session\Storage\Proxy\SessionHandlerProxy;

class EncryptedSessionProxy extends SessionHandlerProxy
{
    protected $handler;
    private $key;

    /**
     * Constructor.
     *
     * @param \SessionHandlerInterface $handler
     * @param Key $key
     */
    public function __construct(
        \SessionHandlerInterface $handler,
        Key $key
    ) {
        parent::__construct($handler);
        $this->key = $key;
    }

    /**
     * Decrypt the session data after reading it from the session handler.
     *
     * @param string $id
     *
     * @return string
     */
    public function read($id): string
    {
        $data = parent::read($id);

        if ($data !== '') {
            return Crypto::decrypt($data, $this->key);
        }

        return '';
    }

    /**
     * Encrypt the session data before writing it to the session handler.
     *
     * @param string $id
     * @param string $data
     *
     * @return bool
     */
    public function write($id, $data): bool
    {
        $data = Crypto::encrypt($data, $this->key);

        return parent::write($id, $data);
    }
}