<?php
/**
 * Copyright 1999-2009 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file COPYING for license information (GPL). If you
 * did not receive this file, see http://www.fsf.org/copyleft/gpl.html.
 *
 * @author  Chuck Hagenbuch <chuck@horde.org>
 * @package IMP
 */

function _framesetUrl($url)
{
    if (!$GLOBALS['noframeset'] && Horde_Util::getFormData('load_frameset')) {
        $url = Horde_Util::addParameter(Horde::applicationUrl($GLOBALS['registry']->get('webroot', 'horde') . '/index.php', true), array('url' => _addAnchor($url, 'param')), null, false);

        /* Need to do a loginTasks check here because we must display login
         * tasks before frameset is loaded. */
        IMP_Session::loginTasks($url);
    }
    return $url;
}

function _newSessionUrl($actionID, $isLogin)
{
    $url = '';
    $addActionID = true;

    if ($GLOBALS['url_in']) {
        $url = Horde::url(Horde_Util::removeParameter($GLOBALS['url_in'], session_name()), true);
    } elseif (Auth::getProvider() == 'imp') {
        $url = Horde::applicationUrl($GLOBALS['registry']->get('webroot', 'horde') . '/', true);
        /* Force the initial page to IMP if we're logging in to compose a
         * message. */
        if ($actionID == 'login_compose') {
            $url = Horde_Util::addParameter($url, 'url', _addAnchor(IMP_Session::getInitialUrl('login_compose', false), 'param'));
            $addActionID = false;
        }
    } else {
        $url = IMP_Session::getInitialUrl($actionID, false);
        if ($isLogin) {
            /* Don't show popup window in initial page. */
            $url = Horde_Util::addParameter($url, 'no_newmail_popup', 1, false);
        }
    }

    if ($addActionID && $actionID) {
        /* Preserve the actionID. */
        $url = Horde_Util::addParameter($url, 'actionID', $actionID, false);
    }

    return $url;
}

function _redirect($url)
{
    Horde::redirect(_addAnchor($url, 'url'));
}

/* Add anchor to outgoing URL. */
function _addAnchor($url, $type)
{
    switch ($type) {
    case 'param':
        if (!empty($GLOBALS['url_anchor'])) {
            $url .= '#' . $GLOBALS['url_anchor'];
        }
        break;

    case 'url':
        $anchor = Horde_Util::getFormData('anchor_string');
        if (!empty($anchor)) {
            $url .= '#' . $anchor;
        } else {
            return _addAnchor($url, 'param');
        }
        break;
    }

    return $url;
}

@define('AUTH_HANDLER', true);
$authentication = 'none';
require_once dirname(__FILE__) . '/lib/base.php';

$actionID = (Horde_Util::getFormData('action') == 'compose') ? 'login_compose' : Horde_Util::getFormData('actionID');
$autologin = Horde_Util::getFormData('autologin');
$imapuser = Horde_Util::getPost('imapuser');
$pass = Horde_Util::getPost('pass');
if (!empty($autologin)) {
    $imapuser = IMP_Session::canAutoLogin();
    $pass = Auth::getCredential('password');
}
$isLogin = empty($_SESSION['imp']['logintasks']);
$noframeset = false;

/* Get URL/Anchor strings now. */
$url_anchor = null;
$url_in = $url_form = Horde_Util::getFormData('url');
if (($pos = strrpos($url_in, '#')) !== false) {
    $url_anchor = substr($url_in, $pos + 1);
    $url_in = substr($url_in, 0, $pos);
}

/* If we already have a session: */
if (isset($_SESSION['imp']) && is_array($_SESSION['imp'])) {
    /* Make sure that if a username was specified, it is the current
     * username. */
    if ((!is_null($imapuser) && ($imapuser != $_SESSION['imp']['user'])) ||
        (!is_null($pass) && ($pass != $GLOBALS['imp_imap']->ob->getParam('password')))) {
        /* Disable the old session. */
        unset($_SESSION['imp']);
        _redirect(IMP::getLogoutUrl(AUTH_REASON_FAILED, true));
    }

    $url = $url_in;
    if (empty($url_in)) {
        $url = IMP_Session::getInitialUrl($actionID, false);
    } elseif (!empty($actionID)) {
        $url = Horde_Util::addParameter($url_in, 'actionID', $actionID, false);
    }

    /* Don't show popup window in initial page. */
    if ($isLogin) {
        $url = Horde_Util::addParameter($url, 'no_newmail_popup', 1, false);
    }

    _redirect(_framesetUrl($url));
}

/* Create a new session if we're given the proper parameters. */
if (!is_null($imapuser) && !is_null($pass)) {
    if (Auth::getProvider() == 'imp') {
        /* Destroy any existing session on login and make sure to use a new
         * session ID, to avoid session fixation issues. */
        Horde::getCleanSession();
    }

    if (IMP_Session::createSession($imapuser, $pass, Horde_Util::getFormData('server_key', IMP_Session::getAutoLoginServer()))) {
        $ie_version = Horde_Util::getFormData('ie_version');
        if ($ie_version) {
            $browser->setIEVersion($ie_version);
        }

        if (($horde_language = Horde_Util::getFormData('new_lang'))) {
            $_SESSION['horde_language'] = $horde_language;
        }

        $view = empty($conf['user']['select_view'])
            ? (empty($conf['user']['force_view']) ? 'imp' : $conf['user']['force_view'])
            : Horde_Util::getFormData('select_view', 'imp');

        setcookie('default_imp_view', $view, time() + 30 * 86400,
                  $conf['cookie']['path'],
                  $conf['cookie']['domain']);
        $_SESSION['imp']['view'] = $view;

        if (!empty($conf['hooks']['postlogin'])) {
            Horde::callHook('_imp_hook_postlogin', array($actionID, $isLogin), 'imp');
        }

        _redirect(_framesetUrl(_newSessionUrl($actionID, $isLogin)));
    }

    _redirect(IMP::getLogoutUrl(null, true));
}

/* No session, and no login attempt. Just go to the login page. */
require IMP_BASE . '/login.php';
