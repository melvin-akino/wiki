@extends('layouts.master')
<?php $class = function ($provider) {
	switch($provider)
	{
		default:
			return $default;
		case 'facebook':
			return 'info';
		case 'twitter':
			return '';
		case 'google':
			return 'alert';
		case 'github':
			return 'secondary';
	}
};?>
@section('content')

	<p class="text-center">{!! _('Login or register with one click using one of these providers') !!}:</p>
        <div class="row">
            <form class="form-horizontal" role="form" method="POST" action="login">
            {{ csrf_field() }}    
            <div class="form-group">
                <label class="col-md-4 control-label">Username</label>
                <div class="col-md-6">
                    <input type="text" class="form-control" name="username" value="{{ old('username') }}">
                </div>
            </div>

            <div class="form-group">
                <label class="col-md-4 control-label">Password</label>
                <div class="col-md-6">
                    <input type="password" class="form-control" name="password">
                </div>
            </div>

            <div class="form-group">
            <div class="col-md-6 col-md-offset-4">
                <div class="checkbox">
                    <label>
                        <input type="checkbox" name="remember"> Remember Me
                    </label>
                </div>
            </div>
            </div>

            <div class="form-group">
                <div class="col-md-6 col-md-offset-4">
                    <button type="submit" class="btn btn-primary" style="margin-right: 15px;">
                            Login
                    </button>
                </div>
            </div>
            </form>
        </div>
	<div class="medium-4 columns medium-centered">
		@foreach ($usableProviders as $provider)
			{!! link_to_route(
				'login.with',
				$provider,
				['provider' => $provider->slug],
				['class' => $class($provider->slug) . ' button expand']
			) !!}<br/>
		@endforeach
	</div>

@stop
