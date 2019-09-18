<?php

namespace Nouvic\Google2FALaravel\Tests;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Nouvic\Google2FALaravel\Facade as Google2FA;
use Nouvic\Google2FALaravel\Support\Authenticator;
use Nouvic\Google2FALaravel\Tests\Support\User;

class Google2FaLaravelTest extends TestCase
{
    const VIEW_ERROR_MESSAGE = 'WRONG OTP';

    /**
     * @return \Illuminate\Http\Request
     */
    private function createEmptyRequest()
    {
        return $request = (new Request())->createFromBase(
            \Symfony\Component\HttpFoundation\Request::create(
                '/',
                'GET'
            )
        );
    }

    protected function getPackageProviders($app)
    {
        return [
            \PragmaRX\Google2FALaravel\ServiceProvider::class,
            \Illuminate\Auth\AuthServiceProvider::class,
        ];
    }

    public function setUp(): void
    {
        parent::setup();

        $this->app->make('Illuminate\Contracts\Http\Kernel')->pushMiddleware('Illuminate\Session\Middleware\StartSession');

        \View::addLocation(__DIR__.'/views');

        $this->loginUser();
    }

    protected function getPackageAliases($app)
    {
        return [
            'Google2FA' => \PragmaRX\Google2FALaravel\Facade::class,
            'Auth'      => \Illuminate\Support\Facades\Auth::class,
        ];
    }

    protected function home()
    {
        return $this->call('GET', 'home')->getContent();
    }

    private function loginUser()
    {
        $user = new User();

        $user->username = 'foo';

        $user->google2fa_secret = Constants::SECRET;

        Auth::login($user);
    }

    protected function assertLogin($password = null, $message = 'google2fa passed')
    {
        config(['google2fa.error_messages.wrong_otp' => self::VIEW_ERROR_MESSAGE]);

        $renderedView = $this->call('POST', 'login', ['one_time_password' => $password])->getContent();

        $this->assertStringContainsString(
            $message,
            $renderedView
        );

        if ($message !== self::VIEW_ERROR_MESSAGE) {
            $this->assertStringNotContainsString(
                self::VIEW_ERROR_MESSAGE,
                $renderedView
            );
        }
    }

    protected function getEnvironmentSetUp($app)
    {
        config(['app.debug' => true]);

        $app['router']->get('home', ['as' => 'home', 'uses' => function () {
            return 'we are home';
        }])->middleware(\PragmaRX\Google2FALaravel\Middleware::class);

        $app['router']->post('login', ['as' => 'login.post', 'uses' => function () {
            return 'google2fa passed';
        }])->middleware(\PragmaRX\Google2FALaravel\Middleware::class);

        $app['router']->post('logout', ['as' => 'logout.post', 'uses' => function () {
            Google2FA::logout();
        }]);
    }

    public function getOTP()
    {
        return Google2FA::getCurrentOtp(Auth::user()->google2fa_secret);
    }

    // --------------------------------------------- tests

    public function testCanInstantiate()
    {
        $this->assertEquals(16, strlen(Google2FA::generateSecretKey()));
    }

    public function testIsActivated()
    {
        $this->assertTrue(Google2FA::isActivated());
    }

    public function testVerifyGoogle2FA()
    {
        $this->assertFalse(Google2FA::verifyGoogle2FA(Auth::user()->google2fa_secret, '000000'));
    }

    public function testRedirectToGoogle2FAView()
    {
        $this->assertStringContainsString(
            'google2fa view',
            $this->home()
        );
    }

    public function testGoogle2FAPostPasses()
    {
        $this->assertLogin($this->getOTP());

        $this->assertStringContainsString(
            'we are home',
            $this->home()
        );
    }

    public function testWrongOTP()
    {
        $this->assertLogin('9999999', self::VIEW_ERROR_MESSAGE);
    }

    public function testLogout()
    {
        $this->assertStringContainsString(
            'google2fa view',
            $this->home()
        );

        $this->assertLogin($this->getOTP());

        $this->assertStringContainsString(
            'we are home',
            $this->home()
        );

        $this->assertStringContainsString(
            '',
            $this->call('POST', 'logout')->getContent()
        );

        $this->assertStringContainsString(
            'google2fa view',
            $this->home()
        );
    }

    public function testLogin()
    {
        $this->startSession();

        $request = $this->createEmptyRequest();
        $request->setLaravelSession($this->app['session']);

        $authenticator = app(\PragmaRX\Google2FALaravel\Google2FA::class)->boot($request);

        $authenticator->login();

        $this->assertTrue($request->getSession()->get('google2fa.auth_passed'));
    }

    public function testOldPasswords()
    {
        config(['google2fa.forbid_old_passwords' => true]);

        $this->assertStringContainsString(
            'google2fa view',
            $this->home()
        );

        $this->assertLogin($this->getOTP());

        $this->assertStringContainsString(
            'we are home',
            $this->home()
        );

        $this->assertStringContainsString(
            '',
            $this->call('POST', 'logout')->getContent()
        );

        $this->assertStringContainsString(
            'google2fa view',
            $this->home()
        );
    }

    public function testPasswordExpiration()
    {
        config(['google2fa.lifetime' => 1]);

        $this->assertLogin($this->getOTP());

        $this->assertStringContainsString(
            'we are home',
            $this->home()
        );

        Carbon::setTestNow(Carbon::now()->addMinutes(3));

        $this->assertStringContainsString(
            'google2fa view',
            $this->home()
        );
    }

    public function testGoogle2FAEmptyPassword()
    {
        $this->assertLogin('', $message = config('google2fa.error_messages.cannot_be_empty'));

        $this->assertLogin(null, $message);
    }

    public function testQrcodeInline()
    {
        $qrCode = Google2FA::getQRCodeInline('company name', 'email@company.com', Constants::SECRET);

        $this->assertStringStartsWith(
            'data:image/png;base64',
            $qrCode
        );

        $this->assertTrue(
            strlen($qrCode) > 1024
        );
    }

    public function testStateless()
    {
        $authenticator = app(Authenticator::class)->bootStateless($this->createEmptyRequest());

        $this->assertFalse($authenticator->isAuthenticated());
    }

    public function testViewError()
    {
        config(['google2fa.error_messages.wrong_otp' => self::VIEW_ERROR_MESSAGE]);

        $this->assertStringContainsString(
            self::VIEW_ERROR_MESSAGE,
            $this->call('POST', 'login', ['input_one_time_password_missing' => 'missing'])->getContent()
        );
    }
}
