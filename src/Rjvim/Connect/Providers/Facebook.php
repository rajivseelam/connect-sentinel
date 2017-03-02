<?php namespace Rjvim\Connect\Providers;

use Config;
use Google_Client;
use Request;
use Redirect;
use Google_Service_Plus;
use Facebook\FacebookRequest;
use Rjvim\Connect\Models\OAuthAccount;
use Rjvim\Connect\Providers\LaravelFacebookRedirectLoginHelper;
use Rjvim\Connect\Providers\MyLaravelPersistentDataHandler;

class Facebook implements ProviderInterface{


	protected $client;
	protected $scopes;
	protected $sentinel;
	protected $redirectUrl;

	/**
	 * Constructor for Connect Library
	 */
	public function __construct($client, $scope, $state = 'default')
	{
		$this->scopes = $scope;
		$this->client = $this->prepareClient($client,$scope,$state);

		$this->sentinel = \App::make('sentinel');
	}

	/**
	 * Prepare a Google Client with client id and scope
	 *
	 * @return void
	 * @author 
	 **/
	public function prepareClient($client, $scope, $state = 'default')
	{

		$client = Config::get('rjvim.connect.facebook.clients.'.$client);

		if(is_array($scope))
		{
			$scopes = array();

			foreach($scope as $s)
			{
				$scopes = array_merge(Config::get('rjvim.connect.facebook.scopes.'.$s),$scopes);
			}
		}
		else
		{
			$scopes = Config::get('rjvim.connect.facebook.scopes.'.$scope);
		}

		$this->scopes = $scopes;
		$this->redirectUrl = $client['redirect_uri'];

		$fb = new \Facebook\Facebook([
		  'app_id' => $client['client_id'],
		  'app_secret' => $client['client_secret'],
		  'default_graph_version' => 'v2.8',
		  'persistent_data_handler' => new MyLaravelPersistentDataHandler(),
		]);

		return $fb;
	}

    /**
	 * Get User Data from Google
	 *
	 * @return void
	 * @author 
	 **/
	public function getGoogleUserData()
	{
		$result = array();

		$plus = new Google_Service_Plus($this->client);

		$person = $plus->people->get('me');

		if($person->getEmails()[0]->getType() == 'account')
		{
			$email = $person->getEmails()[0]->getValue();
		}
		else
		{
			$email = 'Not Found';
		}

		$result['uid'] = $person->id;

		if($this->sentinel->check())
		{
			$result['email'] = $this->sentinel->getUser()->email;
		}
		else
		{
			$result['email'] = $email;
		}
			
		$result['first_name'] = $person->getName()->getGivenName();
		$result['last_name'] = $person->getName()->getFamilyName();
		$result['username'] = $result['first_name'].' '.$result['last_name'];
		$result['name'] = $result['first_name'].' '.$result['last_name'];
		$result['url'] = $person->getUrl();
		$result['image'] = $person->getImage()->getUrl();

		$result['description'] = $person['aboutMe'];
		$result['gender'] = $person['gender'];
		$result['birthday'] = $person['birthday'];

		return $result;
	}

	/**
	 * undocumented function
	 *
	 * @return void
	 * @author 
	 **/
	public function authenticate()
	{
		$helper = $this->client->getRedirectLoginHelper();

		$loginUrl = $helper->getLoginUrl($this->redirectUrl, $this->scopes);

		// dd($loginUrl);

		return redirect()->to($loginUrl);
	}

	/**
	 * undocumented function
	 *
	 * @return void
	 * @author 
	 **/
	public function takeCare()
	{
		$req = Request::instance();

		// dd($req->get('code'));

		$helper = $this->client->getRedirectLoginHelper();

		$accessToken = $helper->getAccessToken();

		// dd($accessToken);

		$this->client->setDefaultAccessToken($accessToken);

		try {
		  $response = $this->client->get('/me?fields=id,name,email,birthday,location,gender,link');
		  $userNode = $response->getGraphUser();
		} catch(\Facebook\Exceptions\FacebookResponseException $e) {
		  // When Graph returns an error
		  echo 'Graph returned an error: ' . $e->getMessage();
		  exit;
		} catch(\Facebook\Exceptions\FacebookSDKException $e) {
		  // When validation fails or other local issues
		  echo 'Facebook SDK returned an error: ' . $e->getMessage();
		  exit;
		}

		$plainOldArray = $response->getDecodedBody();

		$result = array();

		$result['uid'] = $plainOldArray['id'];

		if($this->sentinel->check())
		{
			$result['email'] = $this->sentinel->getUser()->email;
		}
		else
		{
			$result['email'] = $plainOldArray['email'];
		}

		$result['name'] = $plainOldArray['name'];
		$result['url'] = $plainOldArray['link'];
		$result['gender'] = $plainOldArray['gender'];

		$oauth_client = $this->client->getOAuth2Client();
		$token = $oauth_client->getLongLivedAccessToken($accessToken);
		$result['token'] = $token;

		return $result;

	}

	/**
	 * Update Google OAuth information for user
	 *
	 * @return void
	 * @author 
	 **/
	public function updateOAuthAccount($user,$gUserData)
	{
		$scope = $this->scopes;

		$oauth = OAuthAccount::firstOrCreate(
						array(
							'user_id' => $user->id, 
							'provider' => 'facebook'
						));

		$oauth->url = $gUserData['url'];
		$oauth->uid = $gUserData['uid'];
		$oauth->gender = $gUserData['gender'];

		$accessToken = $this->client->getDefaultAccessToken();

		$oauth_client = $this->client->getOAuth2Client();

		$token = $oauth_client->getLongLivedAccessToken($accessToken);

		$oauth->access_token = $token->getValue();

		$oauth->expires_in = $token->getExpiresAt();

		$oauth->signature = serialize($token);

		if(!is_array($scope))
		{
			$scope = (array) $scope;
		}

		$scopes = array();

		foreach($scope as $s)
		{

			$scopes['facebook.'.$s] = 1;

		}

		$oauth->scopes = $scopes;

		$oauth->save();

		return true;
	}


}
