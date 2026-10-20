@props(['title'=>'Discover opportunities'])
<!DOCTYPE html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="csrf-token" content="{{ csrf_token() }}"><meta name="description" content="Your focused workspace for PHP and Laravel opportunities in the Gulf, Pakistan, and beyond."><title>{{ $title }} · JobFinder</title>@vite(['resources/css/jobfinder.css','resources/js/app.js'])</head>
<body x-data="{menu:false}"><a href="#main" class="sr-only focus:not-sr-only">Skip to content</a><div class="shell">
<aside class="sidebar" :class="{'open':menu}" aria-label="Main navigation">
    <a href="{{ route('jobs.index') }}" class="brand"><span class="brand-symbol"><x-icon name="scan-line" /></span>jobfinder<small>.</small></a>
    <div class="nav-title">WORKSPACE</div>
    @auth<a class="nav-link {{ request()->routeIs('dashboard')?'active':'' }}" href="{{ route('dashboard') }}"><x-icon name="layout-dashboard"/>Overview</a>@endauth
    <a class="nav-link {{ request()->routeIs('jobs.*')?'active':'' }}" href="{{ route('jobs.index') }}"><x-icon name="search"/>Discover jobs</a>
    @auth
    <a class="nav-link {{ request()->routeIs('saved.*')?'active':'' }}" href="{{ route('saved.index') }}"><x-icon name="bookmark"/>My applications <span class="badge" style="margin-left:auto">{{ auth()->user()->savedJobs()->count() }}</span></a>
    <div class="nav-title">PERSONALIZE</div>
    <a class="nav-link {{ request()->routeIs('preferences.*')?'active':'' }}" href="{{ route('preferences.edit') }}"><x-icon name="sliders-horizontal"/>Preferences & alerts</a>
    @if(auth()->user()->is_admin)<a class="nav-link {{ request()->routeIs('admin.*')?'active':'' }}" href="{{ route('admin.index') }}"><x-icon name="radio-tower"/>Source management</a>@endif
    @else
    <a class="nav-link" href="{{ route('login') }}"><x-icon name="log-in"/>Sign in</a><a class="nav-link" href="{{ route('register') }}"><x-icon name="user-plus"/>Create account</a>
    @endauth
    <div class="sidebar-foot"><div class="mini-promo"><x-icon name="sprout"/><strong>Your next chapter.</strong><p>Less searching. More opportunities that fit your craft.</p></div>
    @auth<div class="account"><span class="avatar">{{ mb_substr(auth()->user()->name,0,1) }}</span><div style="flex:1;min-width:0"><strong class="small">{{ auth()->user()->name }}</strong><div class="muted" style="font-size:10px">{{ auth()->user()->is_admin?'Administrator':'Developer workspace' }}</div></div><form method="POST" action="{{ route('logout') }}">@csrf<button class="btn ghost" aria-label="Sign out"><x-icon name="log-out"/></button></form></div>@else<p class="small muted">Built for the builders.</p>@endauth</div>
</aside>
<div><header class="topbar"><button class="btn ghost mobile-menu" @click="menu=!menu" :aria-expanded="menu" aria-label="Toggle navigation"><x-icon name="menu"/></button><div class="crumb">Workspace <span style="margin:0 10px">/</span><b>{{ $title }}</b></div><div class="top-actions"><span class="small muted"><span class="live-dot"></span>PHP & Laravel opportunities</span>@auth<a href="{{ route('preferences.edit') }}" class="btn ghost" aria-label="Alert preferences"><x-icon name="bell"/></a><span class="avatar">{{ mb_substr(auth()->user()->name,0,1) }}</span>@else<a href="{{ route('login') }}" class="btn">Sign in</a><a href="{{ route('register') }}" class="btn primary">Get started <x-icon name="arrow-up-right"/></a>@endauth</div></header>
<main id="main" class="content">@if(session('status'))<div role="status" class="flash">{{ session('status') }}</div>@endif @if($errors->any())<div role="alert" class="flash error"><strong>Please check the following:</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif{{ $slot }}<footer class="footer-note"><span>JobFinder · A little focus goes a long way.</span><span>Listings belong to their original sources. Remote roles may have location restrictions.</span></footer></main>
</div></div></body></html>
