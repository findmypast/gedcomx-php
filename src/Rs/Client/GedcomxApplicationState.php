<?php

namespace Gedcomx\Rs\Client;

use Gedcomx\Links\Link;
use Gedcomx\Rs\Client\Exception\GedcomxApplicationException;
use Gedcomx\Rs\Client\Options\HeaderParameter;
use Guzzle\Http\Client;
use Guzzle\Http\Message\Request;
use Guzzle\Http\Message\EntityEnclosingRequest;
use Guzzle\Http\Message\Response;
use RuntimeException;


abstract class GedcomxApplicationState
{

    const GEDCOMX_MEDIA_TYPE = 'application/x-gedcomx-v1+json';
    const ATOM_MEDIA_TYPE = 'application/x-gedcomx-atom+json';

    /**
     * @var Client
     */
    protected $client;
    /**
     * @var Request
     */
    protected $request;
    /**
     * @var Response
     */
    protected $response;
    /**
     * @var string
     */
    protected $accessToken;
    /**
     * @var StateFactory
     */
    protected $stateFactory;
    /**
     * @var array
     */
    protected $links;
    /**
     * @var object
     */
    protected $entity;

    function __construct($client, $request, $response, $accessToken, $stateFactory)
    {
        $this->client = $client;
        $this->request = $request;
        $this->response = $response;
        $this->accessToken = $accessToken;
        $this->stateFactory = $stateFactory;
        $this->entity = $this->loadEntityConditionally($response);
        $this->links = $this->loadLinks($response, $this->entity);
    }

    protected function loadEntityConditionally()
    {
        if ('HEAD' != $this->request->getMethod() && $this->response->getStatusCode() == 200) {
            return $this->loadEntity();
        }
        else {
            return null;
        }
    }

    protected abstract function reconstruct($request, $response);

    protected abstract function loadEntity();

    protected abstract function getScope();

    /**
     * @return array
     */
    protected function loadLinks()
    {
        $links = array();

        //if there's a location, we'll consider it a "self" link.
        $myLocation = $this->response->getHeader('Location');
        if (isset($myLocation)) {
            $links['self'] = new Link();
            $links['self']->setRel('self');
            $links['self']->setHref($myLocation);
        }

        //load link headers
        $linkHeaders = $this->getLinkHeaders();
        foreach ($linkHeaders as $linkHeader) {
            if (isset($linkHeader['rel'])) {
                $link = new Link($linkHeader);
                $links[$linkHeader['rel']] = $link;
            }
        }

        //load links from the entity.
        if (isset($this->entity)) {
            $links = array_merge($links, $this->entity->getLinks());
        }

        $scope = $this->getScope();
        if (isset($scope)) {
            $links = array_merge($links, $scope->getLinks());
        }

        return $links;
    }

    /**
     * @return \Guzzle\Http\Client
     */
    public function getClient()
    {
        return $this->client;
    }

    /**
     * @return string
     */
    public function getAccessToken()
    {
        return $this->accessToken;
    }

    /**
     * @return \Guzzle\Http\Message\Request
     */
    public function getRequest()
    {
        return $this->request;
    }

    /**
     * @return \Guzzle\Http\Message\Response
     */
    public function getResponse()
    {
        return $this->response;
    }

    /**
     * @return object
     */
    public function getEntity()
    {
        return $this->entity;
    }

    /**
     * @return boolean whether this state is authenticated.
     */
    public function isAuthenticated()
    {
        return isset($this->accessToken);
    }

    /**
     * @return string The URI for this application state.
     */
    public function getUri()
    {
        return $this->request->getUrl();
    }

    /**
     * @return bool Whether this state is a client-side error.
     */
    public function hasClientError()
    {
        $statusCode = intval($this->response->getStatusCode());
        return $statusCode >= 400 && $statusCode < 500;
    }

    /**
     * @return bool Whether this state is a server-side error.
     */
    public function hasServerError()
    {
        $statusCode = intval($this->response->getStatusCode());
        return $statusCode >= 500 && $statusCode < 600;
    }

    /**
     * @return bool Whether this state has an error.
     */
    public function hasError()
    {
        return $this->hasClientError() || $this->hasServerError();
    }

    /**
     * @return array The headers for this state.
     */
    public function getHeaders()
    {
        return $this->response->getHeaders();
    }

    /**
     * @return string The self-URI for this state.
     */
    public function getSelfUri()
    {
        $selfLink = $this->getLink(Rel::SELF);
        if (isset($selfLink)) {
            return $selfLink->getHref();
        }
        else {
            return $this->getUri();
        }
    }

    public function head()
    {
        $request = $this->createAuthenticatedRequest("HEAD");
        $accept = $this->request->getHeader("Accept");
        if (isset($accept)) {
            $request->setHeader("Accept", $accept);
        }
        return $this->reconstruct($request, $this->invoke($request));
    }

    public function get()
    {
        $request = $this->createAuthenticatedRequest("GET");
        $accept = $this->request->getHeader("Accept");
        if (isset($accept)) {
            $request->setHeader("Accept", $accept);
        }
        return $this->reconstruct($request, $this->invoke($request));
    }

    public function delete()
    {
        $request = $this->createAuthenticatedRequest("DELETE");
        $accept = $this->request->getHeader("Accept");
        if (isset($accept)) {
            $request->setHeader("Accept", $accept);
        }
        return $this->reconstruct($request, $this->invoke($request));
    }

    public function options()
    {
        $request = $this->createAuthenticatedRequest("OPTIONS");
        $accept = $this->request->getHeader("Accept");
        if (isset($accept)) {
            $request->setHeader("Accept", $accept);
        }
        return $this->reconstruct($request, $this->invoke($request));
    }

    public function put($entity)
    {
        $request = $this->createAuthenticatedRequest("PUT");
        $accept = $this->request->getHeader("Accept");
        if (isset($accept)) {
            $request->setHeader("Accept", $accept);
        }
        $contentType = $this->request->getHeader("Content-Type");
        if (isset($contentType)) {
            $request->setHeader("Content-Type", $contentType);
        }
        $request->setBody(json_encode($entity->toArray()));
        return $this->reconstruct($request, $this->invoke($request));
    }

    /**
     * @param string $rel The link rel.
     * @return \Gedcomx\Links\Link
     */
    public function getLink($rel)
    {
		if( isset($this->links[$rel]) ){
			return $this->links[$rel];
		}
        return null;
    }

    /**
     * @throws GedcomxApplicationException If this state captures an error.
     * @return GedcomxApplicationState $this
     */
    public function ifSuccessful()
    {
        if ($this->hasError()) {
            throw new GedcomxApplicationException($this->buildFailureMessage(), $this->response);
        }

        return $this;
    }

    /**
     * @param string $username The username.
     * @param string $password The password.
     * @param string $clientId The client id.
     * @param string $clientSecret  The client secret.
     * @return GedcomxApplicationState $this
     */
    public function authenticateViaOAuth2Password($username, $password, $clientId, $clientSecret = NULL)
    {
        $formData = array(
            'grant_type' => 'password',
            'username' => $username,
            'password' => $password,
            'client_id' => $clientId
        );

        if (isset($clientSecret)) {
            $formData['client_secret'] = $clientSecret;
        }

        return $this->authenticateViaOAuth2($formData);
    }

    /**
     * @param string $authCode The auth code.
     * @param string $redirect The redirect URI.
     * @param string $clientId The client id.
     * @param string $clientSecret The client secret.
     * @return GedcomxApplicationState $this
     */
    public function authenticateViaOAuth2AuthCode($authCode, $redirect, $clientId, $clientSecret = NULL)
    {
        $formData = array(
            'grant_type' => 'authorization_code',
            'code' => $authCode,
            'redirect_uri' => $redirect,
            'client_id' => $clientId
        );

        if (isset($clientSecret)) {
            $formData['client_secret'] = $clientSecret;
        }

        return $this->authenticateViaOAuth2($formData);
    }

    /**
     * @param string $clientId The client id.
     * @param string $clientSecret The client secret.
     * @return GedcomxApplicationState $this
     */
    public function authenticateViaOAuth2ClientCredentials($clientId, $clientSecret)
    {
        $formData = array(
            'grant_type' => 'client_credentials',
            'client_id' => $clientId,
            'client_secret' => $clientSecret
        );

        return $this->authenticateViaOAuth2($formData);
    }

	public function getETag() {
		return $this->response->getHeader(HeaderParameter::ETag);
	}

	public function getLastModified() {
		return $this->response->getHeader(HeaderParameter::LAST_MODIFIED);
	}

    /**
     * @return Link[] links if Link headers found
     */
    private function getLinkHeaders()
    {
        $headers = $this->response->getHeaders();
        foreach( $headers as $h ){
            if( $h->getName() == "Link" ){
                return $h->getLinks();
            }
        }
        return array();
    }


    /**
     * @return string[] warning messages if Warning Headers are found
     */
    public function getWarnings()
    {
        $headers = $this->response->getHeaders();

        $warnings = array();
        foreach( $headers as $h ){
            if( $h->getName() == "Warning" ){
                $warnings[] = $h->getValue();
            }
        }

        return $warnings;
    }

    protected function buildFailureMessage() {
        $message = "Unsuccessful " . $this->request.getMethod() . " to " . $this->getUri() . " (" . $this->response->getStatus() . ")";
        $warnings = $this->getWarnings();
        foreach( $warnings as $w ) {
            $message .= "\nWarning: " . $w;
        }

        return $message;
    }


    /**
     * @param string $rel The rel
     * @return GedcomxApplicationState The requested page.
     */
    protected function readPage($rel)
    {
        $link = $this->getLink($rel);
        if ($link === null || $link->getHref() === null) {
            return null;
        }

        $request = $this->createAuthenticatedRequest($this->request->getMethod(), $link->getHref());
        $request->setHeader("Accept", $this->request->getHeader("Accept"));
        $request->setHeader("Content-Type", $this->request->getHeader("Content-Type"));
        $class = get_class($this);
        return new $class( $this->client, $request, $this->client->send($request), $this->accessToken, $this->stateFactory );
    }

    /**
     * @param StateTransitionOption $options,... zero or more StateTransitionOption objects
     *
     * @return GedcomxApplicationState The next page.
     */
    protected function readNextPage( $options = null )
    {
        $options = $this->getTransitionOptions( func_get_args() );
        return $this->readPage(Rel::NEXT, $options );
    }

    /**
     * @param StateTransitionOption $options,... zero or more StateTransitionOption objects
     *
     * @return GedcomxApplicationState The previous page.
     */
    protected function readPreviousPage( $options = null )
    {
        $options = $this->getTransitionOptions( func_get_args() );
        return $this->readPage(Rel::PREVIOUS, $options);
    }

    /**
     * @param StateTransitionOption $options,... zero or more StateTransitionOption objects
     *
     * @return GedcomxApplicationState The first page.
     */
    protected function readFirstPage( $options = null )
    {
        $options = $this->getTransitionOptions( func_get_args() );
        return $this->readPage(Rel::FIRST, $options);
    }

    /**
     * @param StateTransitionOption $options,... zero or more StateTransitionOption objects
     *
     * @return GedcomxApplicationState the last page.
     */
    protected function readLastPage( $options = null )
    {
        $options = $this->getTransitionOptions( func_get_args() );
        return $this->readPage(Rel::LAST, $options);
    }

    /**
     * @param array $formData The form parameters.
     * @return GedcomxApplicationState $this
     * @throws GedcomxApplicationException If there are problems.
     */
    protected function authenticateViaOAuth2($formData)
    {
        $tokenLink = $this->getLink(Rel::OAUTH2_TOKEN);
        if (!isset($tokenLink)) {
            $here = $this->getUri();
            throw new GedcomxApplicationException("No OAuth2 token URI supplied for resource at {$here}");
        }

        $href = $tokenLink->getHref();
        if (!isset($href)) {
            $here = $this->getUri();
            throw new GedcomxApplicationException("No OAuth2 token URI supplied for resource at {$here}");
        }

        $request = $this->createRequest('POST', $href);
        /**
         * @var $request EntityEnclosingRequest
         */
        $request->setHeader('Accept', 'application/json');
        $request->setHeader('Content-Type', 'application/x-www-form-urlencoded');
        $request->addPostFields($formData);
        $response = $this->invoke($request);

        $statusCode = intval($response->getStatusCode());
        if ($statusCode >= 200 && $statusCode < 300) {
            $tokens = $response->json();
            $accessToken = $tokens['access_token'];

            if (!isset($accessToken)) {
                //workaround to accommodate providers that were built on an older version of the oauth2 specification.
                $accessToken = $tokens['token'];
            }

            if (!isset($accessToken)) {
                throw new GedcomxApplicationException('Illegal access token response: no access_token provided.', $response);
            }

            $this->accessToken = $accessToken;
            return $this;
        }
        else {
            throw new GedcomxApplicationException('Unable to obtain an access token.', $response);
        }

    }

    /**
     * @param string       $method  The http method.
     * @param string|array $uri     optional: string with an href, or an array with template info
     *
     * @return Request The request.
     */
    protected function createRequest($method, $uri = null)
    {
        return $this->client->createRequest($method, $uri );
    }

    /**
     * @param string       $method  The http method.
     * @param string|array $uri     optional: string with an href, or an array with template info
     *
     * @return Request The request.
     */
    protected function createAuthenticatedRequest($method, $uri = null)
    {
        $request = $this->createRequest($method, $uri);
        if (isset($this->accessToken)) {
            $request->addHeader('Authorization', "Bearer {$this->accessToken}");
        }
        return $request;
    }

    /**
     * @param string       $method  The http method.
     * @param string|array $uri     optional: string with an href, or an array with template info
     *
     * @return Request The request.
     */
    protected function createAuthenticatedFeedRequest($method, $uri = null)
    {
        $request = $this->createAuthenticatedRequest($method, $uri);
        $request->setHeader('Accept', GedcomxApplicationState::ATOM_MEDIA_TYPE);
        return $request;
    }

    /**
     * @param string       $method  The http method.
     * @param string|array $uri    optional: string with an href, or an array with template info
     *
     * @return Request The request.
     */
    protected function createAuthenticatedGedcomxRequest($method, $uri)
    {
        $request = $this->createAuthenticatedRequest($method, $uri);
        $request->setHeader('Accept', GedcomxApplicationState::GEDCOMX_MEDIA_TYPE);
        $request->setHeader('Content-Type', GedcomxApplicationState::GEDCOMX_MEDIA_TYPE);
        return $request;
    }

    /**
     * @param Request                 $request the request to send.
     * @param StateTransitionOption[] $options to be applied before sending
     *
     * @return Response The response.
     */
    protected function invoke($request, $options = null)
    {
        if( $options !== null ){
            foreach( $options as $opt ){
                $opt->apply($request);
            }
        }
        return $this->client->send($request);
    }

    protected function getTransitionOptions( $args )
    {
        while (!empty($args) && !$args[0] instanceof StateTransitionOption){
            array_shift($args);
        }

        return $args;
    }

}