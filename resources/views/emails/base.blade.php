<style rel="stylesheet" media="all">
    .email-body {
        font-family: "Roboto", sans-serif;
        background-color: #f6f8fb;
        padding-top: 10px;
        padding-bottom: 20px;
    }

    #window-wrapper {
        max-width: 600px;
        margin: auto;
        background: #fff;
        padding: 40px;
        border: 2px solid #ededed;
    }

    #logo-container {
        text-align: center;
        margin-bottom: 40px;
        margin-top: 20px;
    }

    #logo {
        max-height: 40px;
    }

    #copyright {
        text-align: center;
        margin-top: 20px;
        font-weight: 300;
        font-size: 12px;
        color: #5b5b5b;
    }

    .wrapper-container {
        max-width: 500px;
        margin: auto;
        /*text-align: center;*/
    }

    .header {
        font-size: 20px;
        font-weight: 300;
        color: #3c3c3c;
        -moz-osx-font-smoothing: auto;
        text-transform: capitalize;
    }

    .main-text {
        margin: auto;
        font-size: 13px;
        color: #5a5a5a;
        font-weight: 300;
    }

    .gray-text {
        margin: auto;
        font-size: 12px;
        color: #979797;
        font-weight: 300;
    }

    .btn-container {
        text-align: center;
        margin-top: 40px;
    }

    .btn {
        cursor: pointer;
        padding: 15px 25px;
        background-color: #254a94;
        color: #fff;
        text-decoration: none;
        text-transform: capitalize;
        margin: auto;
        text-align: center;
        max-width: 100%;
    }

    a {
        color: #254a94;
        text-decoration: none;
    }

    .mfa-code {
        margin-top: 25px;
        letter-spacing: 2px;
        font-size: 20px;
        text-align: center;
    }
</style>

<div class="email-body">
    <div id='logo-container'>
        <img id='logo' src="" alt='{{ \Illuminate\Support\Facades\Config::get('app.name') }}'/>
    </div>
    <div id='window-wrapper'>
        @yield('content')
    </div>
</div>
