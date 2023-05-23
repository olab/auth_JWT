<?php

/**
  * @package local_olab_jwt
  * @author oLab Inc
  * @license https://www.gnu.org/licenses/gpl-3.0.en.html
  *
  * Send your users to /local/jwt_auth/auth.php?callback_url={ENCODED_CALLBACK_URL}
  * If you don't send a callback_url parameter, the first whitelisted domain will
  * be used instead, to redirect the user to, following the auth consent, with
  * the bearer appended to the URL as `bearer`
  * @see $user_payload below for jwt claims
  */

// sync this private key with oLab API's
const OLAB_JWT_PRIVATE_KEY = 'abcdef0123456789';

// which hosts to whitelist for bearer redirects -- for ports other than 80/443, please use host:port, e.g localhost:8080
const OLAB_AUTH_REDIRECT_ALLOW_HOSTS = ['example.com', 'www.example.com', 'localhost:5903'];

// cookie params for syncing the JWT with other apps
const OLAB_AUTH_JWT_COOKIE_NAME = 'external_token';
const OLAB_AUTH_JWT_COOKIE_PARAMS = [
    'domain' => 'olab.ca',
    'path' => '/',
    'secure' => true,
    'httponly' => false, // this is needed for the tokenizer script to access the token via document.cookie
];

// one or more hosts for iframes to attach JWT token to via query string
const TOKENIZE_IFRAMES_WITH_HOST = [
    'dev.olab.ca',
    'demo.olab.ca',
    'logan.cardinalcreek.ca',
    'everest.cardinalcreek.ca',
];
