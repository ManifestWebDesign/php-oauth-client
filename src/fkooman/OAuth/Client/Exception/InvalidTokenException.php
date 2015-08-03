<?php

/**
 *  This program is free software: you can redistribute it and/or modify
 *  it under the terms of the GNU Lesser General Public License as published by
 *  the Free Software Foundation, either version 3 of the License, or
 *  (at your option) any later version.
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU Lesser General Public License for more details.
 *
 *  You should have received a copy of the GNU Lesser General Public License
 *  along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */
namespace fkooman\OAuth\Client\Exception;

class InvalidTokenException extends \Exception {

	/**
	 * @var	string
	 */
	protected $clientConfigId;

	/**
	 *
	 * @param	string		$message				optional.
	 * @param	string		$code					optional.
	 * @param	string		$previous				optional.
	 * @param	string		$client_config_id		optional.
	 * @return	\fkooman\OAuth\Client\Exception\InvalidTokenException
	 */
	function __construct($message, $code, $previous, $client_config_id = null) {
		parent::__construct($message, $code, $previous);
		$this->clientConfigId = $client_config_id;
	}

	/**
	 * @return	string
	 */
	function getClientConfigId() {
		return $this->clientConfigId;
	}

	/**
	 * @param	string		$client_config_id
	 * @return	\fkooman\OAuth\Client\Exception\InvalidTokenException
	 */
	function setClientConfigId($client_config_id) {
		$this->clientConfigId = $client_config_id;
		return $this;
	}
}