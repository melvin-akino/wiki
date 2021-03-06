<?php

namespace App\Http\Controllers;

use App\Provider;
use App\User;
use Validator;
use App\Http\Requests\SocialLoginRequest;
use Illuminate\Http\Request;
use Laravel\Socialite\Contracts\User as SocialUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Auth\AuthenticatesAndRegistersUsers;
use Illuminate\Foundation\Auth\ThrottlesLogins;
use Illuminate\Foundation\Auth;



class AuthController extends Controller
{
	use AuthenticatesAndRegistersUsers, ThrottlesLogins;
        
        //protected $username = 'username';
        
	/**
	 * Show login form
	 *
	 * @return Response
	 */
	public function showLoginPage()
	{
		return view('login')->withTitle(_('Login'));
	}
        
        /**
	 * Show login form
	 *
	 * @return Response
	 */
	public function postLogin(Request $request)
	{
            // Getting all post data
            $data = $request->all();
            // Applying validation rules.
            //echo "<pre>".print_r($data, true)."</pre>";

            $rules = array(
                        'username' => 'required|min:6',
                        'password' => 'required|min:6',
                     );
            $validator = Validator::make($data, $rules);
            if ($validator->fails()){
              // If validation falis redirect back to login.
              return redirect()->to('login')->withInput($request->except('password'))->withErrors($validator);
            }
            else {
                $userdata = array(
                    'username' => $request->input('username'),
                    'password' => $request->input('password')
                );

                $user = User::validateAuth($userdata);
                //echo "<pre>".print_r($user, true)."</pre>";
                //echo session()->token().' - '.$request->_token;
                //dd($request->token);
                if (auth()->attempt($request->only('username','password'))) {
                    //return redirect()->to('category');
                    return redirect()->intended(route('home'));
                }
                else {
                    return redirect()->to('login');
                }
            }
            //
	}
	/**
	 * Attempt to log in an user using a social authentication provider.
	 *
	 * @param  Illuminate\Http\Request $request
	 * @param  string
	 * @return Response
	 */
	public function loginWithProvider(Request $request, $providerSlug)
	{
		// Use provider name as the throttling cache key
		$request['provider'] = $providerSlug;

		// Check if there are too many login attempts for current provider and IP
		if($this->hasTooManyLoginAttempts($request))
		{
			// Flash error message
			$seconds = (int) \Cache::get($this->getLoginLockExpirationKey($request)) - time();
			$message = sprintf(_('Please try again in %d seconds.'), $seconds);

			return $this->goBack(_('Too many login attempts') . '. ' . $message);
		}
		$this->incrementLoginAttempts($request);

		// If the remote provider sends an error cancel the process
		foreach(['error', 'error_message', 'error_code'] as $error)
			if($request->has($error))
				return $this->goBack(_('Something went wrong') . '. ' . $request->get($error));

		// Get provider
		$provider = Provider::whereSlug($providerSlug)->firstOrFail();

		// Make sure it's usable
		if( ! $provider->isUsable())
			return $this->goBack(_('Unavailable provider'));

		// Set provider callback url
		config(["services.{$provider->slug}.redirect" => \URL::current()]);

		// Create an Oauth service for this provider
		$oauthService = \Socialite::with($provider->slug);

		// Check if current request is a callback from the provider
		if($request->has('oauth_token')/*Oauth 1*/ or $request->has('code')/*Oauth 2*/)
		{
			$this->clearLoginAttempts($request);

			return $this->loginSocialUser($provider, $oauthService->user());
		}

		// If we have configured custom scopes use them
		if($scopes = config("services.{$provider->slug}.scopes"))
			$oauthService->scopes($scopes);

		// Request user to authorize our App
		return $oauthService->redirect();
	}

	/**
	 * Handle callback from provider.
	 *
	 * It creates/gets the user and logs him/her in.
	 *
	 * @param  \App\Provider
	 * @param  \Laravel\Socialite\Contracts\User
	 * @return Response
	 */
	protected function loginSocialUser(Provider $provider, SocialUser $socialUser)
	{
		// Validate response
		$errors = with(new SocialLoginRequest)->validate($socialUser);
		if($errors->any())
		{
			$providerError = sprintf(_('There are problems with data provided by %s'), $provider);

			return $this->goBack($providerError . ': ' . implode(', ', $errors->all()));
		}

		// Get/create an application user matching the social user
		$user = User::findOrCreate($provider, $socialUser);

		// If user has been disabled disallow login
		if($user->trashed())
			return $this->goBack(_('Your account has been disabled'));

		// Login user
		auth()->login($user);

		return redirect()->intended(route('home'));
	}

	/**
	 * Log out the current user
	 *
	 * @return Response
	 */
	public function logout()
	{
		auth()->logout();

		return redirect()->route('home');
	}

	/**
	 * Redirect back flashing error to session.
	 *
	 * @param  string
	 * @return Response
	 */
	protected function goBack($error)
	{
		\Session::flash('error', $error);

		return redirect()->back();
	}

	/**
	 * Get the name of the attribute used to control login throttling.
	 *
	 * @return string
	 */
	protected function loginUsername()
	{
		return 'username';
	}
}
