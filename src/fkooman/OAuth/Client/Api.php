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

namespace fkooman\OAuth\Client;

use fkooman\OAuth\Client\Exception\ApiException;
use fkooman\OAuth\Client\Exception\InvalidTokenException;

/**
 * API for talking to OAuth 2.0 protected resources.
 *
 * @author François Kooman <fkooman@tuxed.net>
 */
class Api {

	const RANDOM_LENGTH = 8;

	private $clientConfigId;
	private $clientConfig;
	private $tokenStorage;

	/** @var fkooman/OAuth/Client/HttpClientInterface */
	private $httpClient;

	public function __construct($clientConfigId, ClientConfigInterface $clientConfig, StorageInterface $tokenStorage, HttpClientInterface $httpClient) {
		$this->setClientConfigId($clientConfigId);
		$this->setClientConfig($clientConfig);
		$this->setTokenStorage($tokenStorage);
		$this->setHttpClient($httpClient);
	}

	public function setClientConfigId($clientConfigId) {
		if (!is_string($clientConfigId) || 0 >= strlen($clientConfigId)) {
			throw new ApiException('clientConfigId must be a non-empty string');
		}
		$this->clientConfigId = $clientConfigId;
	}

	public function setClientConfig(ClientConfigInterface $clientConfig) {
		$this->clientConfig = $clientConfig;
	}

	public function setTokenStorage(StorageInterface $tokenStorage) {
		$this->tokenStorage = $tokenStorage;
	}

	public function setHttpClient(HttpClientInterface $httpClient) {
		$this->httpClient = $httpClient;
	}

	public function getRefreshToken(Context $context) {
		return $this->tokenStorage->getRefreshToken($this->clientConfigId, $context);
	}

	public function getAccessToken(Context $context) {
		// do we have a valid access token?
		$accessToken = $this->tokenStorage->getAccessToken($this->clientConfigId, $context);
		$invalid_token = false;
		$scope = null;

		if (false !== $accessToken) {
			if (null === $accessToken->getExpiresIn()) {
				// no expiry set, assume always valid
				return $accessToken;
			}
			// check if expired
			if (time() < $accessToken->getIssueTime() + $accessToken->getExpiresIn()) {
				// not expired
				return $accessToken;
			}
			// expired, delete it and continue
			//$this->tokenStorage->deleteAccessToken($accessToken);
			$invalid_token = true;
		}

		// no valid access token, is there a refresh_token?
		$refreshToken = $this->getRefreshToken($context);
		// no access token, and refresh token didn't work either or was not there, probably the tokens were revoked
		//return false;

		if ($refreshToken !== false) {
			// obtain a new access token with refresh token
			$tokenRequest = new TokenRequest($this->httpClient, $this->clientConfig);
			$tokenResponse = $tokenRequest->withRefreshToken($refreshToken->getRefreshToken());

			if ($tokenResponse && method_exists($tokenResponse, 'getScope')) {
				$invalid_token = false;
			} else {
				//$this->tokenStorage->deleteRefreshToken($refreshToken);
			}
		}

		if ($invalid_token === false && !empty($tokenResponse)) {

			try {

				if (null === $tokenResponse->getScope()) {
					// no scope in response, we assume we got the requested scope
					$scope = $context->getScope();
				} else {
					// the scope we got should be a superset of what we requested
					$scope = $tokenResponse->getScope();
					if (!$scope->hasScope($context->getScope())) {
						// we didn't get the scope we requested, stop for now
						// FIXME: we need to implement a way to request certain
						// scope as being optional, while others need to be
						// required
						throw new ApiException('requested scope not obtained');
					}
				}
			} catch (Exception $e) {
				error_log('OAUTH ERROR: ' . $e->getMessage());
				$invalid_token = true;
			}
		}

		if ($invalid_token === true || $scope === null) {
			$e = new InvalidTokenException(
				'Token for user # "' . $context->getUserId() . '" is invalid or expired.', null, null, $context->getUserId()
			);

			throw $e;
		}


		$accessToken = new AccessToken(
			array(
			'client_config_id' => $this->clientConfigId,
			'user_id' => $context->getUserId(),
			'scope' => $scope,
			'access_token' => $tokenResponse->getAccessToken(),
			'token_type' => $tokenResponse->getTokenType(),
			'issue_time' => time(),
			'expires_in' => $tokenResponse->getExpiresIn(),
			)
		);

		$this->tokenStorage->storeAccessToken($accessToken);

		if (null !== $tokenResponse->getRefreshToken()) {
			// delete the existing refresh token as we'll store a new one
			$this->tokenStorage->deleteRefreshToken($refreshToken);
			$refreshToken = new RefreshToken(
				array(
				'client_config_id' => $this->clientConfigId,
				'user_id' => $context->getUserId(),
				'scope' => $scope,
				'refresh_token' => $tokenResponse->getRefreshToken(),
				'issue_time' => time(),
				)
			);
			$this->tokenStorage->storeRefreshToken($refreshToken);
		}

		return $accessToken;
	}

	public function deleteAccessToken(Context $context) {
		$accessToken = $this->getAccessToken($context);
		if (false !== $accessToken) {
			$this->tokenStorage->deleteAccessToken($accessToken);
		}
	}

	public function deleteRefreshToken(Context $context) {
		$refreshToken = $this->getRefreshToken($context);
		if (false !== $refreshToken) {
			$this->tokenStorage->deleteRefreshToken($refreshToken);
		}
	}

	public function getAuthorizeUri(Context $context, $stateValue = null) {
		// allow caller to override a random generated state
		// FIXME: is this actually used anywhere?
		if (null === $stateValue) {
			$stateValue = bin2hex(openssl_random_pseudo_bytes(self::RANDOM_LENGTH));
		} else {
			if (!is_string($stateValue) || 0 >= strlen($stateValue)) {
				throw new ApiException('state must be a non-empty string');
			}
		}

		// try to get a new access token
		$this->tokenStorage->deleteStateForContext($this->clientConfigId, $context);
		$state = new State(
			array(
			'client_config_id' => $this->clientConfigId,
			'user_id' => $context->getUserId(),
			'scope' => $context->getScope(),
			'issue_time' => time(),
			'state' => $stateValue,
			)
		);
		if (false === $this->tokenStorage->storeState($state)) {
			throw new ApiException('unable to store state');
		}

		$q = array(
			'client_id' => $this->clientConfig->getClientId(),
			'response_type' => 'code',
			'state' => $state->getState(),
		);

		// scope
		$contextScope = $context->getScope();
		if (!$contextScope->isEmpty()) {
			if ($this->clientConfig->getUseCommaSeparatedScope()) {
				$q['scope'] = $contextScope->toString(',');
			} else {
				$q['scope'] = $contextScope->toString();
			}
		}

		// redirect_uri
		if ($this->clientConfig->getRedirectUri()) {
			$q['redirect_uri'] = $this->clientConfig->getRedirectUri();
		}

		$separator = (false === strpos($this->clientConfig->getAuthorizeEndpoint(), '?')) ? '?' : '&';
		$authorizeUri = $this->clientConfig->getAuthorizeEndpoint() . $separator . http_build_query($q, null, '&');

		return $authorizeUri;
	}

}