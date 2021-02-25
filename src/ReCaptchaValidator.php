<?php

declare(strict_types=1);

namespace manchenkov\yii\recaptcha;

use Yii;
use yii\base\InvalidConfigException;
use yii\httpclient\Client;
use yii\httpclient\CurlTransport;
use yii\httpclient\Exception;
use yii\httpclient\Response;
use yii\validators\Validator;

/**
 * ReCaptchaValidator class to check value for Google reCAPTCHA v3
 * @package Manchenkov\Yii\Recaptcha
 */
class ReCaptchaValidator extends Validator
{
    /**
     * URL to verify response
     */
    private const VALIDATION_URL = "https://www.google.com/recaptcha/api/";

    /**
     * Secret key attribute name in Yii params file
     */
    private const SECRET_CONFIG_KEY = 'reCAPTCHA.secretKey';

    /**
     * Stop validation if an error occurred
     * @var bool
     */
    public $skipOnError = false;

    /**
     * Validation value is required
     * @var bool
     */
    public $skipOnEmpty = false;

    /**
     * Error message if validation fails
     * @var string
     */
    public $message = "Google reCAPTCHA verification failed";

    /**
     * Acceptance score from Google API
     * @var float
     */
    public float $score = 0.5;

    /**
     * Action name to validate API response
     * @var string
     */
    public string $action = 'homepage';

    /**
     * Google reCAPTCHA v3 secret key
     * @var string
     */
    private string $secretKey;

    /**
     * Checks necessary configuration for initialization
     * @throws InvalidConfigException
     */
    public function init()
    {
        if (!array_key_exists(self::SECRET_CONFIG_KEY, Yii::$app->params)) {
            throw new InvalidConfigException('Google reCAPTCHA secret key must be specified!');
        }

        $this->secretKey = Yii::$app->params[self::SECRET_CONFIG_KEY];

        parent::init();
    }

    /**
     * {@inheritdoc}
     */
    public function validateAttribute($model, $attribute)
    {
        $reCaptchaTokenValue = $model->{$attribute};

        try {
            $response = $this->sendValidationRequest($reCaptchaTokenValue);
        } catch (Exception $exception) {
            $this->addError(
                $model,
                $attribute,
                Yii::t('app', $this->message)
            );

            return;
        }

        if ($this->isResponseValid($response)) {
            return;
        }

        $this->addError(
            $model,
            $attribute,
            Yii::t('app', $this->message)
        );
    }

    /**
     * @param string $token
     *
     * @return Response
     * @throws Exception
     */
    private function sendValidationRequest(string $token): Response
    {
        $httpClient = new Client(
            [
                'baseUrl' => self::VALIDATION_URL,
                'transport' => CurlTransport::class,
            ]
        );

        return $httpClient
            ->post(
                'siteverify',
                [
                    'secret' => $this->secretKey,
                    'response' => $token,
                    'remoteip' => Yii::$app->request->remoteIP,
                ]
            )
            ->send();
    }

    private function isResponseValid(Response $response): bool
    {
        if (!$response->isOk) {
            return false;
        }

        $currentHost = Yii::$app->request->hostName;
        $data = $response->data;

        if ($data['success'] && $data['hostname'] == $currentHost) {
            if ($data['action'] === $this->action && $data['score'] >= $this->score) {
                return true;
            }
        }

        return false;
    }
}