<?php
/**
 * Piwik - free/libre analytics platform
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 *
 */
namespace Piwik\Plugins\Login;

use Exception;
use Piwik\Config;
use Piwik\Cookie;
use Piwik\FrontController;
use Piwik\Option;
use Piwik\Piwik;
use Piwik\Plugins\UsersManager\UsersManager;
use Piwik\Session;
use Piwik\Plugins\Login\SessionInitializer;
use Piwik\Auth as AuthInterface;
use Piwik\Plugins\UsersManager\Model as UserModel;

/**
 *
 */
class Login extends \Piwik\Plugin
{
    /**
     * @see Piwik\Plugin::getListHooksRegistered
     */
    public function getListHooksRegistered()
    {
        $hooks = array(
            'Request.initAuthenticationObject' => 'initAuthenticationObject',
            'User.isNotAuthorized'             => 'noAccess',
            'API.Request.authenticate'         => 'ApiRequestAuthenticate',
            'AssetManager.getJavaScriptFiles'  => 'getJsFiles'
        );
        return $hooks;
    }

    public function getJsFiles(&$jsFiles)
    {
        $jsFiles[] = "plugins/Login/javascripts/login.js";
    }

    /**
     * Redirects to Login form with error message.
     * Listens to User.isNotAuthorized hook.
     */
    public function noAccess(Exception $exception)
    {
        $exceptionMessage = $exception->getMessage();

        echo FrontController::getInstance()->dispatch('Login', 'login', array($exceptionMessage));
    }

    /**
     * Set login name and authentication token for API request.
     * Listens to API.Request.authenticate hook.
     */
    public function ApiRequestAuthenticate($tokenAuth)
    {
        \Piwik\Registry::get('auth')->setLogin($login = null);
        \Piwik\Registry::get('auth')->setTokenAuth($tokenAuth);

        if(isset($_GET['token_auth']) && $_GET['token_auth']) {
            $userModel = new UserModel();
            $user = $userModel->getUserByTokenAuth($_GET['token_auth']);
            if($user) {
                $auth = \Piwik\Registry::get('auth');
                $auth->setLogin($user['login']);
                $auth->setPasswordHash($user['password']);
                $sessionInit = new SessionInitializer();
                $sessionInit->initSession($auth, false);
            }
        }
    }

    protected static function isModuleIsAPI()
    {
        return Piwik::getModule() === 'API'
                && (Piwik::getAction() == '' || Piwik::getAction() == 'index');
    }

    /**
     * Initializes the authentication object.
     * Listens to Request.initAuthenticationObject hook.
     */
    function initAuthenticationObject($activateCookieAuth = false)
    {
        $auth = new Auth();
        \Piwik\Registry::set('auth', $auth);

        $this->initAuthenticationFromCookie($auth, $activateCookieAuth);
    }

    /**
     * @param $auth
     */
    public static function initAuthenticationFromCookie(\Piwik\Auth $auth, $activateCookieAuth)
    {
        if (self::isModuleIsAPI() && !$activateCookieAuth) {
            return;
        }

        $authCookieName = Config::getInstance()->General['login_cookie_name'];
        $authCookieExpiry = 0;
        $authCookiePath = Config::getInstance()->General['login_cookie_path'];
        $authCookie = new Cookie($authCookieName, $authCookieExpiry, $authCookiePath);
        $defaultLogin = 'anonymous';
        $defaultTokenAuth = 'anonymous';
        if ($authCookie->isCookieFound()) {
            $defaultLogin = $authCookie->get('login');
            $defaultTokenAuth = $authCookie->get('token_auth');
        }
        $auth->setLogin($defaultLogin);
        $auth->setTokenAuth($defaultTokenAuth);
    }

}
