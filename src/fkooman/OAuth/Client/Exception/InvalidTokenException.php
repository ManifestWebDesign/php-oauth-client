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
	protected $userId;

	/**
	 *
	 * @param	string		$message				optional.
	 * @param	string		$code					optional.
	 * @param	string		$previous				optional.
	 * @param	string		$user_id		optional.
	 * @return	\fkooman\OAuth\Client\Exception\InvalidTokenException
	 */
	function __construct($message, $code, $previous, $user_id = null) {
		parent::__construct($message, $code, $previous);
		$this->userId = $user_id;
	}

	/**
	 * @return	string
	 */
	function getUserId() {
		return $this->userId;
	}

	/**
	 * @param	string		$user_id
	 * @return	\fkooman\OAuth\Client\Exception\InvalidTokenException
	 */
	function setUserId($user_id) {
		$this->userId = $user_id;
		return $this;
	}
}