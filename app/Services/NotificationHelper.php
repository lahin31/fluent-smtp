<?php

namespace FluentMail\App\Services;


use FluentMail\App\Models\Settings;
use FluentMail\Includes\Support\Arr;

class NotificationHelper
{
    public static function getRemoteServerUrl()
    {
        if (defined('FLUENTSMTP_SERVER_REMOTE_SERVER_URL')) {
            return FLUENTSMTP_SERVER_REMOTE_SERVER_URL;
        }

        return 'https://fluentsmtp.com/wp-json/fluentsmtp_notify/v1/';
    }

    public static function issueTelegramPinCode($data)
    {
        return self::sendTeleRequest('register-site', $data, 'POST');
    }

    public static function registerSlackSite($data)
    {
        return self::sendSlackRequest('register-site', $data, 'POST');
    }

    public static function getTelegramConnectionInfo($token)
    {
        return self::sendTeleRequest('get-site-info', [], 'GET', $token);
    }

    public static function sendTestTelegramMessage($token = '')
    {
        if (!$token) {
            $settings = (new Settings())->notificationSettings();
            $token = $settings['telegram_notify_token'];
        }

        return self::sendTeleRequest('send-test', [], 'POST', $token);
    }

    public static function disconnectTelegram($token)
    {
        self::sendTeleRequest('disconnect', [], 'POST', $token);

        $settings = (new Settings())->notificationSettings();
        $settings['telegram_notify_status'] = 'no';
        $settings['telegram_notify_token'] = '';
        update_option('_fluent_smtp_notify_settings', $settings, false);

        return true;
    }

    public static function getTelegramBotTokenId()
    {
        static $token = null;

        if ($token !== null) {
            return $token;
        }

        $settings = (new Settings())->notificationSettings();

        if (empty($settings['telegram_notify_token'])) {
            $token = false;
            return $token;
        }

        $token = $settings['telegram_notify_token'];

        return $token;
    }

    public static function getSlackWebhookUrl()
    {
        static $url = null;

        if ($url !== null) {
            return $url;
        }

        $settings = (new Settings())->notificationSettings();

        $url = Arr::get($settings, 'slack.webhook_url');

        if (!$url) {
            $url = false;
        }

        return $url;
    }

    public static function sendFailedNotificationTele($data)
    {
        wp_remote_post(self::getRemoteServerUrl() . 'telegram/send-failed-notification', array(
            'timeout'   => 0.01,
            'blocking'  => false,
            'body'      => $data,
            'cookies'   => false,
            'sslverify' => false,
        ));

        return true;
    }

    private static function sendTeleRequest($route, $data = [], $method = 'POST', $token = '')
    {
        $url = self::getRemoteServerUrl() . 'telegram/' . $route;

        if ($token) {
            $url .= '?site_token=' . $token;
        }

        if ($method == 'POST') {
            $response = wp_remote_post($url, [
                'body'      => $data,
                'sslverify' => false,
                'timeout'   => 50
            ]);
        } else {
            $response = wp_remote_get($url, [
                'sslverify' => false,
                'timeout'   => 50
            ]);
        }

        if (is_wp_error($response)) {
            return $response;
        }

        $responseCode = wp_remote_retrieve_response_code($response);

        $body = wp_remote_retrieve_body($response);
        $responseData = json_decode($body, true);

        if (!$responseData || empty($responseData['success']) || $responseCode !== 200) {
            return new \WP_Error('invalid_data', 'Something went wrong', $responseData);
        }

        return $responseData;
    }

    private static function sendSlackRequest($route, $data = [], $method = 'POST', $token = '')
    {
        $url = self::getRemoteServerUrl() . 'slack/' . $route;

        if ($token) {
            $url .= '?site_token=' . $token;
        }

        if ($method == 'POST') {
            $response = wp_remote_post($url, [
                'body'      => $data,
                'sslverify' => false,
                'timeout'   => 50
            ]);
        } else {
            $response = wp_remote_get($url, [
                'sslverify' => false,
                'timeout'   => 50
            ]);
        }

        if (is_wp_error($response)) {
            return $response;
        }

        $responseCode = wp_remote_retrieve_response_code($response);

        $body = wp_remote_retrieve_body($response);

        $responseData = json_decode($body, true);

        if (!$responseData || empty($responseData['success']) || $responseCode !== 200) {
            return new \WP_Error('invalid_data', 'Something went wrong', $responseData);
        }

        return $responseData;
    }


    public static function sendSlackMessage($message, $webhookUrl, $blocking = false)
    {
        $body = wp_json_encode(array('text' => $message));
        $args = array(
            'body'        => $body,
            'headers'     => array(
                'Content-Type' => 'application/json',
            ),
            'timeout'     => 60,
            'redirection' => 5,
            'blocking'    => true,
            'httpversion' => '1.0',
            'sslverify'   => false,
            'data_format' => 'body',
        );

        if (!$blocking) {
            $args['blocking'] = false;
            $args['timeout'] = 0.01;
        }


        $response = wp_remote_post($webhookUrl, $args);

        if (!$blocking) {
            return true;
        }

        if (is_wp_error($response)) {
            return $response;
        }

        $body = wp_remote_retrieve_body($response);
        return json_decode($body, true);
    }
}
